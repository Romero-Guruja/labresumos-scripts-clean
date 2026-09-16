<?php
/**
 * Regra de prazo de acesso: 1 ano da última compra aprovada.
 *
 * REGRA DO NEGÓCIO (definida em 26/08/2026):
 *   expire_time = data da compra aprovada + N dias
 *   onde N = `num_days_course_access` do curso (padrão 365).
 *   NÃO acumula: comprar de novo NÃO soma ao saldo que restava, apenas
 *   reinicia a contagem a partir da nova compra.
 *
 * POR QUE ESTE FILTRO EXISTE:
 * o Edwiser Bridge calcula `$start_date + ($act_cnt * $num_days)` em
 * `calc_course_acess_expiry_date()` (class-eb-enrollment-manager.php:568),
 * ou seja, ACUMULA crédito a cada recompra e ancora sempre na PRIMEIRA
 * matrícula. Para um aluno que comprou em maio/2026 e recomprou em
 * agosto/2026 isso gera 05/05/2028 -- quase 2 anos. A regra da casa é
 * 1 ano da última compra (13/08/2027).
 *
 * Não existe filtro dentro de `calc_course_acess_expiry_date()`, então
 * corrigimos no hook `eb_user_courses_updated`, disparado ao final de
 * `update_user_course_enrollment()`. Nenhum arquivo do Edwiser é alterado --
 * a correção sobrevive a update do plugin.
 *
 * PRAZO CUSTOMIZADO POR CURSO: se o curso tiver `num_days_course_access`
 * diferente de 365, ele é respeitado (a regra é "1 ano" só como PADRÃO).
 * Curso sem `course_expirey=yes` continua vitalício e não é tocado.
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Expiry_Anchor
 */
class LRA_Expiry_Anchor {

    /** Dias de acesso padrão quando o curso não especifica. */
    const DIAS_PADRAO = 365;

    /**
     * Registra hooks.
     */
    public static function init() {
        add_action('eb_user_courses_updated', [__CLASS__, 'fix_anchor'], 20, 3);
    }

    /**
     * Recalcula expire_time como "agora + N dias" após uma compra aprovada.
     *
     * @param int   $user_id WordPress user id.
     * @param int   $success 1 quando a operação no Moodle deu certo.
     * @param array $courses Cursos afetados.
     */
    public static function fix_anchor($user_id, $success, $courses) {
        global $wpdb;

        if (empty($success) || empty($courses) || !is_array($courses)) {
            return;
        }

        foreach ($courses as $course_id) {
            $course_id = absint($course_id);
            if (!$course_id) {
                continue;
            }

            $opts = get_post_meta($course_id, 'eb_course_options', true);

            // Curso sem prazo configurado segue vitalício: não tocamos.
            if (empty($opts['course_expirey']) || 'yes' !== $opts['course_expirey']) {
                continue;
            }

            // Prazo customizado do curso tem precedência sobre o padrão.
            $dias = !empty($opts['num_days_course_access'])
                ? absint($opts['num_days_course_access'])
                : self::DIAS_PADRAO;

            if ($dias < 1) {
                continue;
            }

            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, act_cnt, expire_time, suspended
                       FROM {$wpdb->prefix}moodle_enrollment
                      WHERE user_id = %d AND course_id = %d",
                    $user_id,
                    $course_id
                )
            );

            // Sem linha = desmatrícula em andamento. Nada a ajustar.
            if (!$row) {
                continue;
            }

            // A REGRA: sempre 1 ano (ou N do curso) a partir de AGORA, que é
            // o instante da compra aprovada. Sem somar saldo remanescente.
            $agora = current_time('timestamp', true);
            $novo  = gmdate('Y-m-d H:i:s', $agora + ($dias * DAY_IN_SECONDS));

            // Idempotência: se já está no valor certo (mesmo minuto), sai.
            if ($row->expire_time === $novo) {
                continue;
            }

            $wpdb->update(
                $wpdb->prefix . 'moodle_enrollment',
                [
                    'expire_time' => $novo,
                    // Comprar de novo reativa acesso suspenso por vencimento.
                    'suspended'   => 0,
                ],
                ['id' => (int) $row->id],
                ['%s', '%d'],
                ['%d']
            );

            // Espelha no Moodle, que é onde o acesso é de fato barrado.
            self::sync_moodle($user_id, $course_id, $novo);

            lra_log('[prazo] expiracao definida em 1 ano da compra', [
                'user_id'   => (int) $user_id,
                'course_id' => (int) $course_id,
                'act_cnt'   => (int) $row->act_cnt,
                'dias'      => $dias,
                'anterior'  => $row->expire_time,
                'novo'      => $novo,
            ]);
        }
    }

    /**
     * Propaga o novo prazo para mdl_user_enrolments.
     *
     * @param int    $user_id   WordPress user id.
     * @param int    $course_id WordPress course id.
     * @param string $expira    Novo expire_time (UTC).
     * @return bool
     */
    public static function sync_moodle($user_id, $course_id, $expira) {
        $eb = '\app\wisdmlabs\edwiserBridge\edwiser_bridge_instance';
        if (!function_exists($eb)) {
            return false;
        }

        $moodle_user_id = get_user_meta($user_id, 'moodle_user_id', true);
        $opts           = get_post_meta($course_id, 'eb_course_options', true);
        $moodle_course  = isset($opts['moodle_course_id']) ? $opts['moodle_course_id'] : '';

        if (empty($moodle_user_id) || empty($moodle_course)) {
            return false;
        }

        // timestart=0 preserva "sem data de início"; timeend carrega o prazo.
        // Reenviar AMBOS é obrigatório: enrol_user() (lib/enrollib.php:2132)
        // trata os dois como valores absolutos e grava 0 no que não vier,
        // apagando o prazo que acabamos de definir.
        $resp = $eb()->connection_helper()->connect_moodle_with_args_helper(
            'enrol_manual_enrol_users',
            [
                'enrolments' => [
                    [
                        'roleid'    => 5,
                        'userid'    => $moodle_user_id,
                        'courseid'  => $moodle_course,
                        'timestart' => 0,
                        'timeend'   => strtotime($expira . ' UTC'),
                        'suspend'   => 0,
                    ],
                ],
            ]
        );

        return !empty($resp['success']);
    }
}
