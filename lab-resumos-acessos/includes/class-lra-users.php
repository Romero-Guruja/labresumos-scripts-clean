<?php
/**
 * Coluna "Bloqueado" na tela de Usuarios do WordPress.
 *
 * O bloqueio de um aluno NAO existe no WordPress: nao ha marcador em
 * wp_users/wp_usermeta (user_status e 0 para todos os usuarios deste site).
 * O estado real vive no Moodle, em mdl_user.suspended - e e la que o
 * atendimento precisa olhar para saber por que um aluno nao consegue entrar.
 *
 * Esta classe traz esse dado para a tela users.php: uma coluna "Bloqueado"
 * e um filtro "Bloqueados (N)", para o suporte nao precisar de acesso ao
 * Moodle nem de administrador.
 *
 * COMO OS DADOS SAO OBTIDOS
 * O token do web service do Edwiser NAO libera core_user_get_users (testado:
 * "Access control exception"), que seria a forma direta de pedir
 * "suspended=1". O que funciona e core_user_get_users_by_field por e-mail,
 * que retorna o campo suspended. Entao varremos os e-mails do WordPress em
 * lotes e montamos o conjunto de bloqueados.
 *
 * Custo medido em producao: 200 e-mails por chamada em ~0,4s; ~1.4k usuarios
 * = ~7 chamadas (~3s). Por isso o resultado e cacheado (transient) e a
 * varredura NUNCA roda durante o carregamento da tela: quem renderiza usa o
 * cache e, se ele estiver frio, dispara a varredura em background (cron
 * single event) e mostra "-" naquele acesso. Assim a tela de usuarios nunca
 * fica presa esperando o Moodle, mesmo se o Moodle estiver lento ou fora.
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Users
 */
class LRA_Users {

    /**
     * Slug da coluna.
     */
    const COLUMN = 'lra_blocked';

    /**
     * Transient que marca que o mapa esta FRESCO (stale-while-revalidate).
     */
    const CACHE_KEY = 'lra_blocked_users_map';

    /**
     * Option com o ultimo mapa conhecido (persistente, sem expiracao).
     *
     * O transient acima e so o marcador de frescor; o dado em si vive aqui
     * para que a tela sempre possa mostrar o ultimo status conhecido em vez
     * de "-" quando o frescor expira.
     */
    const OPTION_MAP = 'lra_blocked_users_map_data';

    /**
     * Option com o timestamp da ultima sincronizacao bem-sucedida.
     */
    const OPTION_SYNCED_AT = 'lra_blocked_users_synced_at';

    /**
     * Validade do cache, em segundos.
     */
    const CACHE_TTL = 900;

    /**
     * Hook do cron que atualiza o cache (disparo pontual).
     */
    const CRON_HOOK = 'lra_refresh_blocked_users';

    /**
     * Hook do cron recorrente (de hora em hora).
     */
    const CRON_RECURRING_HOOK = 'lra_refresh_blocked_users_recurring';

    /**
     * Tamanho do lote de e-mails por chamada ao web service.
     */
    const BATCH = 200;

    /**
     * Parametro de querystring do filtro.
     */
    const FILTER_ARG = 'lra_blocked_only';

    /**
     * Registra os hooks.
     */
    public static function init() {
        add_filter('manage_users_columns', [__CLASS__, 'add_column']);
        add_filter('manage_users_custom_column', [__CLASS__, 'render_column'], 10, 3);
        add_filter('views_users', [__CLASS__, 'add_view_link']);
        add_action('pre_get_users', [__CLASS__, 'filter_blocked_only']);

        // Varredura em background (nunca no request da tela).
        add_action(self::CRON_HOOK, [__CLASS__, 'refresh_cache']);
        add_action(self::CRON_RECURRING_HOOK, [__CLASS__, 'refresh_cache']);

        // Atualizacao periodica, para o status nao depender de alguem abrir
        // a tela de usuarios.
        add_action('init', [__CLASS__, 'maybe_schedule_recurring']);
    }

    /**
     * Garante o agendamento recorrente da varredura.
     */
    public static function maybe_schedule_recurring() {
        if (!wp_next_scheduled(self::CRON_RECURRING_HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', self::CRON_RECURRING_HOOK);
        }
    }

    /**
     * Indica se o usuario atual pode ver a coluna de bloqueio.
     *
     * Vale para o suporte (list_users via papel lra_suporte) e para
     * administradores; nao expoe nada a quem nao ja ve a lista de usuarios.
     *
     * @return bool
     */
    private static function current_user_can_view() {
        return current_user_can('list_users');
    }

    /**
     * Adiciona a coluna.
     *
     * @param array $columns
     * @return array
     */
    public static function add_column($columns) {
        if (!self::current_user_can_view()) {
            return $columns;
        }

        $columns[self::COLUMN] = __('Bloqueado', 'lab-resumos-acessos');

        return $columns;
    }

    /**
     * Renderiza o conteudo da coluna.
     *
     * @param string $value
     * @param string $column
     * @param int    $user_id
     * @return string
     */
    public static function render_column($value, $column, $user_id) {
        if (self::COLUMN !== $column) {
            return $value;
        }
        if (!self::current_user_can_view()) {
            return $value;
        }

        $map = self::get_cached_map();

        // Primeira execucao, antes de qualquer sincronizacao.
        if (null === $map) {
            return '<span style="color:#999;" title="'
                . esc_attr__('Status ainda nao sincronizado com o Moodle. Recarregue em instantes.', 'lab-resumos-acessos')
                . '">&mdash;</span>';
        }

        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) {
            return '<span style="color:#999;">&mdash;</span>';
        }

        $email = strtolower($user->user_email);

        // Aluno sem conta no Moodle: nao ha bloqueio a reportar.
        if (!isset($map[$email])) {
            return '<span style="color:#999;" title="'
                . esc_attr__('Sem conta correspondente no Moodle.', 'lab-resumos-acessos')
                . '">&mdash;</span>';
        }

        $title = esc_attr(self::synced_ago());

        if (!empty($map[$email])) {
            return '<span style="color:#d63638;font-weight:600;" title="' . $title . '">'
                . esc_html__('Bloqueado', 'lab-resumos-acessos')
                . '</span>';
        }

        return '<span style="color:#46b450;" title="' . $title . '">'
            . esc_html__('Ativo', 'lab-resumos-acessos') . '</span>';
    }

    /**
     * Adiciona o link de filtro "Bloqueados (N)" acima da lista.
     *
     * @param array $views
     * @return array
     */
    public static function add_view_link($views) {
        if (!self::current_user_can_view()) {
            return $views;
        }

        $map = self::get_cached_map();
        if (null === $map) {
            return $views;
        }

        $blocked_emails = array_keys(array_filter($map));
        $count          = count($blocked_emails);
        if (!$count) {
            return $views;
        }

        $url     = add_query_arg(self::FILTER_ARG, '1', admin_url('users.php'));
        $current = !empty($_GET[self::FILTER_ARG]); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $views['lra_blocked'] = sprintf(
            '<a href="%s"%s>%s <span class="count">(%s)</span></a>',
            esc_url($url),
            $current ? ' class="current" aria-current="page"' : '',
            esc_html__('Bloqueados', 'lab-resumos-acessos'),
            number_format_i18n($count)
        );

        return $views;
    }

    /**
     * Restringe a listagem aos bloqueados quando o filtro esta ativo.
     *
     * @param WP_User_Query $query
     */
    public static function filter_blocked_only($query) {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || 'users' !== $screen->id) {
            return;
        }
        if (empty($_GET[self::FILTER_ARG])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        if (!self::current_user_can_view()) {
            return;
        }

        $map = self::get_cached_map();
        if (null === $map) {
            return;
        }

        $emails = array_keys(array_filter($map));

        // Nenhum bloqueado: força resultado vazio em vez de listar todos.
        $query->set('include', self::emails_to_ids($emails) ?: [0]);
    }

    /**
     * Converte e-mails em IDs de usuario do WordPress.
     *
     * @param string[] $emails
     * @return int[]
     */
    private static function emails_to_ids($emails) {
        global $wpdb;

        if (empty($emails)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($emails), '%s'));

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} WHERE LOWER(user_email) IN ({$placeholders})",
                $emails
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return array_map('absint', (array) $ids);
    }

    /**
     * Retorna o ultimo mapa conhecido (email => bool bloqueado).
     *
     * Estrategia stale-while-revalidate: devolve o dado persistido mesmo
     * quando o marcador de frescor expirou, e nesse caso agenda a revalidacao
     * em background. Retorna null apenas na primeira execucao, antes de
     * existir qualquer sincronizacao.
     *
     * @return array|null
     */
    private static function get_cached_map() {
        $fresh = (bool) get_transient(self::CACHE_KEY);
        $map   = get_option(self::OPTION_MAP);

        if (!is_array($map)) {
            self::schedule_refresh();
            return null;
        }

        if (!$fresh) {
            self::schedule_refresh();
        }

        return $map;
    }

    /**
     * Ha quanto tempo o mapa foi sincronizado, em texto curto.
     *
     * @return string
     */
    private static function synced_ago() {
        $ts = (int) get_option(self::OPTION_SYNCED_AT);
        if (!$ts) {
            return '';
        }

        return sprintf(
            /* translators: %s: tempo legivel, ex. "5 mins" */
            __('Status do Moodle sincronizado ha %s.', 'lab-resumos-acessos'),
            human_time_diff($ts, time())
        );
    }

    /**
     * Agenda a varredura em background, sem duplicar agendamentos.
     */
    private static function schedule_refresh() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time() + 5, self::CRON_HOOK);
        }
    }

    /**
     * Varre o Moodle e regrava o cache de bloqueados.
     *
     * Roda em background (cron), nunca no carregamento da tela.
     *
     * @return array|WP_Error Mapa email => bool, ou erro.
     */
    public static function refresh_cache() {
        if (!LRA_Enrollment::edwiser_available()) {
            return new WP_Error('lra_edwiser_unavailable', __('Edwiser Bridge indisponivel.', 'lab-resumos-acessos'));
        }

        global $wpdb;

        $emails = $wpdb->get_col("SELECT user_email FROM {$wpdb->users} WHERE user_email <> ''");
        if (empty($emails)) {
            return [];
        }

        $edwiser = \app\wisdmlabs\edwiserBridge\EdwiserBridge::instance();
        $helper  = $edwiser->connection_helper();

        $map     = [];
        $batches = array_chunk($emails, self::BATCH);
        $failed  = 0;

        foreach ($batches as $batch) {
            $response = $helper->connect_moodle_with_args_helper(
                'core_user_get_users_by_field',
                ['field' => 'email', 'values' => array_values($batch)]
            );

            if (empty($response['success'])) {
                $failed++;
                continue;
            }

            foreach ((array) ($response['response_data'] ?? []) as $moodle_user) {
                $moodle_user = (array) $moodle_user;
                if (empty($moodle_user['email'])) {
                    continue;
                }
                $map[strtolower($moodle_user['email'])] = !empty($moodle_user['suspended']);
            }
        }

        // Se TODOS os lotes falharam, nao grava cache vazio (que apareceria
        // como "todo mundo sem conta no Moodle"); deixa frio e tenta depois.
        if ($failed === count($batches)) {
            lra_log('[usuarios] varredura de bloqueio falhou em todos os lotes', [
                'lotes' => count($batches),
            ], 'warning');

            return new WP_Error('lra_scan_failed', __('Falha ao consultar o Moodle.', 'lab-resumos-acessos'));
        }

        set_transient(self::CACHE_KEY, 1, self::CACHE_TTL);
        update_option(self::OPTION_MAP, $map, false);
        update_option(self::OPTION_SYNCED_AT, time(), false);

        lra_log('[usuarios] mapa de bloqueio atualizado', [
            'usuarios_moodle' => count($map),
            'bloqueados'      => count(array_filter($map)),
            'lotes_falhos'    => $failed,
        ]);

        return $map;
    }
}
