<?php
/**
 * Expiracao de acesso por prazo.
 *
 * Substitui o motor nativo do Edwiser Bridge, que e inviavel aqui:
 * ele engancha `unenroll_on_course_access_expire` em `eb_before_single_course`,
 * ou seja, so roda quando alguem abre a pagina publica de um curso eb_course.
 * Como 48 dos 53 cursos estao em draft e o aluno vai direto ao Moodle, na
 * pratica aquele gancho quase nunca dispara -- e quando dispara, varre a
 * tabela inteira sem LIMIT dentro do request de um visitante, disparando uma
 * chamada de webservice por matricula vencida (timeout garantido no pico de
 * ~3.000 vencimentos de jul/ago 2027).
 *
 * Aqui o processamento e por cron, em lote, com teto por execucao.
 *
 * ACAO NO VENCIMENTO: suspender (reversivel), nunca desmatricular.
 * O corte efetivo de acesso ao material acontece no Moodle, via
 * mdl_user_enrolments.status=1 -- e o que `is_enrolled($ctx,$uid,'',true)`
 * consulta em local/apimaterials/ajax/download.php.
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Expiration
 */
class LRA_Expiration {

    /** Hook do cron de expiracao. */
    const CRON_HOOK = 'lra_expire_access';

    /** Hook do cron de avisos previos. */
    const CRON_NOTICE_HOOK = 'lra_expire_notice';

    /** Maximo de matriculas processadas por execucao. */
    const BATCH = 100;

    /** Dias de antecedencia dos avisos. */
    const NOTICE_DAYS = [30, 7];

    /** Option: liga/desliga a suspensao efetiva (dry-run por padrao). */
    const OPT_ENABLED = 'lra_expiration_enabled';

    /**
     * Registra hooks.
     */
    public static function init() {
        add_action(self::CRON_HOOK, [__CLASS__, 'run']);
        // DESATIVADO 2026-08-26: avisos migraram para LRA_Expiry_Mail, que
        // AGRUPA por aluno. O run_notices() enviava um e-mail por matricula --
        // aluno com 24 materiais vencendo no mesmo dia receberia 24 mensagens
        // de uma vez (risco real de marcacao como spam no SendGrid).
        // add_action(self::CRON_NOTICE_HOOK, [__CLASS__, 'run_notices']);
        add_action('init', [__CLASS__, 'maybe_schedule']);

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('lra expire', [__CLASS__, 'cli_expire']);
            \WP_CLI::add_command('lra expire-notices', [__CLASS__, 'cli_notices']);
        }
    }

    /**
     * Agenda os crons se ainda nao existirem.
     */
    public static function maybe_schedule() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', self::CRON_HOOK);
        }
        if (!wp_next_scheduled(self::CRON_NOTICE_HOOK)) {
            wp_schedule_event(time() + 600, 'daily', self::CRON_NOTICE_HOOK);
        }
    }

    /**
     * Remove os agendamentos (chamado na desativacao do plugin).
     */
    public static function unschedule() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::CRON_NOTICE_HOOK);
    }

    /**
     * A suspensao efetiva esta ligada?
     *
     * Padrao: NAO. Enquanto desligado o cron apenas registra em log o que
     * faria, permitindo observar alguns ciclos antes de cortar acesso real.
     *
     * @return bool
     */
    public static function is_enabled() {
        return 'yes' === get_option(self::OPT_ENABLED, 'no');
    }

    /**
     * Busca matriculas vencidas ainda nao suspensas.
     *
     * @param int $limit Teto de linhas.
     * @return array
     */
    public static function get_expired($limit = self::BATCH) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, course_id, time, expire_time, act_cnt
                   FROM {$wpdb->prefix}moodle_enrollment
                  WHERE expire_time <> '0000-00-00 00:00:00'
                    AND expire_time < UTC_TIMESTAMP()
                    AND suspended = 0
               ORDER BY expire_time ASC
                  LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Processa um lote de vencidos.
     *
     * @return array Resumo da execucao.
     */
    public static function run() {
        $rows    = self::get_expired();
        $enabled = self::is_enabled();
        $done    = 0;
        $failed  = 0;

        foreach ($rows as $row) {
            if (!$enabled) {
                lra_log('[expiracao][dry-run] suspenderia matricula', [
                    'user_id'   => (int) $row->user_id,
                    'course_id' => (int) $row->course_id,
                    'expirou'   => $row->expire_time,
                ]);
                $done++;
                continue;
            }

            if (self::suspend($row)) {
                $done++;
            } else {
                $failed++;
            }
        }

        if ($rows) {
            lra_log('[expiracao] lote processado', [
                'encontrados' => count($rows),
                'processados' => $done,
                'falhas'      => $failed,
                'modo'        => $enabled ? 'efetivo' : 'dry-run',
            ]);
        }

        return ['found' => count($rows), 'done' => $done, 'failed' => $failed];
    }

    /**
     * Suspende uma matricula no Moodle e marca no WordPress.
     *
     * IMPORTANTE: nao usamos update_user_course_enrollment() do Edwiser com
     * suspend=1 porque o caminho dele chama update_user_course_suspend_status(),
     * que ZERA expire_time (grava '0000-00-00 00:00:00'). Perder a data de
     * vencimento impediria reativar o aluno com o prazo correto e faria a
     * matricula reentrar no fluxo como se fosse vitalicia. Aqui preservamos
     * expire_time e escrevemos apenas suspended=1.
     *
     * @param object $row Linha de wp_moodle_enrollment.
     * @return bool
     */
    public static function suspend($row) {
        global $wpdb;

        if (!class_exists('\app\wisdmlabs\edwiserBridge\EdwiserBridge')) {
            lra_log('[expiracao] Edwiser indisponivel, abortando lote', [], 'error');
            return false;
        }

        $moodle_user_id   = get_user_meta($row->user_id, 'moodle_user_id', true);
        $course_options   = get_post_meta($row->course_id, 'eb_course_options', true);
        $moodle_course_id = isset($course_options['moodle_course_id'])
            ? $course_options['moodle_course_id']
            : '';

        if (empty($moodle_user_id) || empty($moodle_course_id)) {
            lra_log('[expiracao] sem mapeamento Moodle, pulando', [
                'user_id'   => (int) $row->user_id,
                'course_id' => (int) $row->course_id,
            ], 'warning');
            return false;
        }

        // Suspende no Moodle preservando a matricula (status=1, reversivel).
        //
        // ATENCAO: enrol_manual_enrol_users cai em enrol_user(), que trata
        // timestart/timeend como VALORES ABSOLUTOS -- omiti-los faz o Moodle
        // gravar 0 nos dois campos (lib/enrollib.php:2132-2136), APAGANDO o
        // prazo que acabamos de aplicar no backfill. A matricula viraria
        // "sem data" e sairia do controle de expiracao para sempre.
        // Por isso reenviamos timestart/timeend explicitamente.
        $timestart = ('0000-00-00 00:00:00' === $row->time) ? 0 : strtotime($row->time . ' UTC');
        $timeend   = strtotime($row->expire_time . ' UTC');

        $response = \app\wisdmlabs\edwiserBridge\edwiser_bridge_instance()->connection_helper()->connect_moodle_with_args_helper(
            'enrol_manual_enrol_users',
            [
                'enrolments' => [
                    [
                        'roleid'    => 5,
                        'userid'    => $moodle_user_id,
                        'courseid'  => $moodle_course_id,
                        'timestart' => $timestart,
                        'timeend'   => $timeend,
                        'suspend'   => 1,
                    ],
                ],
            ]
        );

        if (empty($response['success'])) {
            lra_log('[expiracao] falha ao suspender no Moodle', [
                'user_id'   => (int) $row->user_id,
                'course_id' => (int) $row->course_id,
                'resposta'  => isset($response['response_message']) ? $response['response_message'] : '',
            ], 'error');
            return false;
        }

        // Marca no WordPress SEM zerar expire_time (ver nota no docblock).
        $wpdb->update(
            $wpdb->prefix . 'moodle_enrollment',
            ['suspended' => 1],
            ['id' => (int) $row->id],
            ['%d'],
            ['%d']
        );

        lra_log('[expiracao] acesso suspenso por vencimento', [
            'user_id'   => (int) $row->user_id,
            'course_id' => (int) $row->course_id,
            'expirou'   => $row->expire_time,
        ]);

        do_action('lra_access_expired', $row->user_id, $row->course_id, $row->expire_time);

        return true;
    }

    /**
     * Dispara avisos previos de vencimento.
     *
     * Idempotente: grava um user_meta por (curso, marco de dias) para nunca
     * enviar o mesmo aviso duas vezes, mesmo se o cron rodar varias vezes.
     *
     * @return int Quantidade de avisos enviados.
     */
    public static function run_notices() {
        global $wpdb;
        $enviados = 0;

        foreach (self::NOTICE_DAYS as $dias) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, user_id, course_id, expire_time
                       FROM {$wpdb->prefix}moodle_enrollment
                      WHERE expire_time <> '0000-00-00 00:00:00'
                        AND suspended = 0
                        AND DATE(expire_time) = DATE(DATE_ADD(UTC_TIMESTAMP(), INTERVAL %d DAY))
                      LIMIT %d",
                    $dias,
                    self::BATCH
                )
            );

            foreach ($rows as $row) {
                $meta_key = '_lra_expire_notice_' . (int) $row->course_id . '_' . (int) $dias;
                if (get_user_meta($row->user_id, $meta_key, true)) {
                    continue;
                }

                if (self::send_notice($row, $dias)) {
                    update_user_meta($row->user_id, $meta_key, time());
                    $enviados++;
                }
            }
        }

        if ($enviados) {
            lra_log('[expiracao] avisos previos enviados', ['total' => $enviados]);
        }

        return $enviados;
    }

    /**
     * Envia o aviso de vencimento proximo.
     *
     * @param object $row  Linha de wp_moodle_enrollment.
     * @param int    $dias Dias restantes.
     * @return bool
     */
    public static function send_notice($row, $dias) {
        $user = get_userdata($row->user_id);
        if (!$user || !is_email($user->user_email)) {
            return false;
        }

        $curso = get_the_title($row->course_id);
        $data  = date_i18n('d/m/Y', strtotime($row->expire_time));

        $assunto = sprintf('Seu acesso a %s vence em %d dias', $curso, $dias);

        $corpo  = sprintf("Ola, %s!\n\n", $user->first_name ? $user->first_name : $user->display_name);
        $corpo .= sprintf(
            "Seu acesso ao material \"%s\" vence em %s (%d dias).\n\n",
            $curso,
            $data,
            $dias
        );
        $corpo .= "Para continuar com acesso ao material, e so renovar pelo site:\n";
        $corpo .= home_url('/') . "\n\n";
        $corpo .= "Qualquer duvida, e so responder este e-mail.\n\n";
        $corpo .= "Equipe Lab Resumos";

        $enviado = wp_mail($user->user_email, $assunto, $corpo);

        if (!$enviado) {
            lra_log('[expiracao] falha ao enviar aviso', [
                'user_id' => (int) $row->user_id,
                'dias'    => $dias,
            ], 'warning');
        }

        return $enviado;
    }

    /**
     * WP-CLI: processa vencidos.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Apenas relata, sem suspender.
     *
     * [--limit=<n>]
     * : Teto de linhas nesta execucao.
     *
     * @param array $args       Args.
     * @param array $assoc_args Assoc args.
     */
    public static function cli_expire($args, $assoc_args) {
        $dry   = isset($assoc_args['dry-run']);
        $limit = isset($assoc_args['limit']) ? absint($assoc_args['limit']) : self::BATCH;
        $rows  = self::get_expired($limit);

        \WP_CLI::log(sprintf('Matriculas vencidas encontradas: %d', count($rows)));

        foreach ($rows as $row) {
            $user  = get_userdata($row->user_id);
            $curso = get_the_title($row->course_id);
            \WP_CLI::log(sprintf(
                '  %s | %s | venceu em %s%s',
                $user ? $user->user_email : ('#' . $row->user_id),
                $curso,
                $row->expire_time,
                $dry ? '' : ' -> suspendendo'
            ));

            if (!$dry) {
                self::suspend($row);
            }
        }

        \WP_CLI::success($dry ? 'Dry-run concluido (nada alterado).' : 'Processamento concluido.');
    }

    /**
     * WP-CLI: dispara avisos previos.
     */
    public static function cli_notices() {
        $n = self::run_notices();
        \WP_CLI::success(sprintf('Avisos enviados: %d', $n));
    }
}
