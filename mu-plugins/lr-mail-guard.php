<?php
/**
 * Plugin Name: LR Mail Guard
 * Description: Impede que qualquer ambiente que não seja a produção real (labresumos.com.br) envie e-mail para terceiros. Fora de produção, todos os destinatários são reescritos para o e-mail do responsável e o envio é registrado em log.
 * Version: 1.0.0
 * Author: Guruja / Hermes
 *
 * Instalação: wp-content/mu-plugins/lr-mail-guard.php (produção E clones).
 *
 * Lógica: produção é identificada POSITIVAMENTE (allowlist de host).
 * Qualquer outra coisa (clone /venture, subdomínio, cópia local, clone novo
 * com outro nome) cai automaticamente no modo cofre. Isso é o inverso de
 * "detectar staging", que falharia em silêncio no dia em que um clone novo
 * fosse criado com outro caminho.
 *
 * Constantes opcionais (wp-config.php):
 *   LR_MAIL_GUARD_REDIRECT_TO  - destino do redirecionamento (default romero@guruja.com.br)
 *   LR_MAIL_GUARD_HARD_BLOCK   - true = bloqueia o envio por completo (silêncio absoluto)
 *   LR_MAIL_GUARD_DISABLE      - true = desliga o guard (uso emergencial, deixa rastro no log)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('LR_MAIL_GUARD_REDIRECT_TO')) {
    define('LR_MAIL_GUARD_REDIRECT_TO', 'romero@guruja.com.br');
}

if (!class_exists('LR_Mail_Guard')) {

class LR_Mail_Guard {

    /**
     * Hosts+paths que SÃO produção. Match exato, sem barra final.
     * Nada além disso é considerado produção.
     */
    const PRODUCTION_URLS = [
        'https://labresumos.com.br',
        'http://labresumos.com.br',
    ];

    /**
     * Cache do resultado de is_production().
     *
     * @var bool|null
     */
    private static $is_production = null;

    /**
     * Bootstrap.
     */
    public static function init() {
        if (defined('LR_MAIL_GUARD_DISABLE') && LR_MAIL_GUARD_DISABLE) {
            return;
        }

        if (self::is_production()) {
            // Em produção o guard não interfere em nada. Nenhum filtro registrado.
            return;
        }

        if (defined('LR_MAIL_GUARD_HARD_BLOCK') && LR_MAIL_GUARD_HARD_BLOCK) {
            add_filter('pre_wp_mail', [__CLASS__, 'hard_block'], 1, 2);
        } else {
            // Prioridade 1: roda antes de qualquer coisa que monte destinatário.
            add_filter('wp_mail', [__CLASS__, 'rewrite_recipients'], 1);
        }

        // Segunda camada: e-mails do WooCommerce montam destinatário via filtro próprio.
        add_filter('woocommerce_email_recipient_new_order', [__CLASS__, 'force_recipient'], 99);
        add_filter('woocommerce_email_recipient_cancelled_order', [__CLASS__, 'force_recipient'], 99);
        add_filter('woocommerce_email_recipient_failed_order', [__CLASS__, 'force_recipient'], 99);
        add_filter('woocommerce_email_recipient_customer_on_hold_order', [__CLASS__, 'force_recipient'], 99);
        add_filter('woocommerce_email_recipient_customer_processing_order', [__CLASS__, 'force_recipient'], 99);
        add_filter('woocommerce_email_recipient_customer_completed_order', [__CLASS__, 'force_recipient'], 99);
        add_filter('woocommerce_email_recipient_customer_refunded_order', [__CLASS__, 'force_recipient'], 99);
        add_filter('woocommerce_email_recipient_customer_invoice', [__CLASS__, 'force_recipient'], 99);
        add_filter('woocommerce_email_recipient_customer_note', [__CLASS__, 'force_recipient'], 99);
        add_filter('woocommerce_email_recipient_customer_reset_password', [__CLASS__, 'force_recipient'], 99);
        add_filter('woocommerce_email_recipient_customer_new_account', [__CLASS__, 'force_recipient'], 99);

        // Terceira camada: mata disparo em LOTE fora de produção. Redirecionar 300
        // e-mails para uma caixa só continua sendo 300 e-mails saindo do SendGrid.
        add_action('init', [__CLASS__, 'unhook_bulk_senders'], 99);

        // Aviso visual no wp-admin do clone.
        add_action('admin_notices', [__CLASS__, 'admin_notice']);
        add_action('all_admin_notices', [__CLASS__, 'admin_bar_banner']);
    }

    /**
     * Este site é a produção real?
     *
     * @return bool
     */
    public static function is_production() {
        if (self::$is_production !== null) {
            return self::$is_production;
        }

        // Sinal mais forte e barato: WP Staging marca o clone no wp-config dele.
        if (defined('WPSTAGING_DEV_SITE') && WPSTAGING_DEV_SITE) {
            self::$is_production = false;
            return false;
        }

        if (defined('LR_IS_STAGING') && LR_IS_STAGING) {
            self::$is_production = false;
            return false;
        }

        $home = self::home_url_raw();
        self::$is_production = in_array($home, self::PRODUCTION_URLS, true);

        return self::$is_production;
    }

    /**
     * home_url normalizada, sem depender de funções que ainda não carregaram.
     *
     * @return string
     */
    private static function home_url_raw() {
        if (defined('WP_HOME') && WP_HOME) {
            return untrailingslashit(strtolower(WP_HOME));
        }

        // get_option é seguro em mu-plugin (wpdb já existe neste ponto do boot).
        $home = get_option('home');

        return $home ? untrailingslashit(strtolower($home)) : '';
    }

    /**
     * Destino do redirecionamento.
     *
     * @return string
     */
    private static function redirect_to() {
        $to = LR_MAIL_GUARD_REDIRECT_TO;

        return is_email($to) ? $to : 'romero@guruja.com.br';
    }

    /**
     * Reescreve todos os destinatários e marca o assunto/corpo.
     *
     * @param array $args Argumentos do wp_mail.
     * @return array
     */
    public static function rewrite_recipients($args) {
        $original_to = self::flatten($args['to'] ?? '');
        $headers     = $args['headers'] ?? '';
        $original_cc = self::extract_header_recipients($headers, 'cc');
        $original_bcc = self::extract_header_recipients($headers, 'bcc');

        $args['to'] = self::redirect_to();

        // Remove Cc/Bcc dos headers: eles não passam por $args['to'].
        $args['headers'] = self::strip_recipient_headers($headers);

        $marker = sprintf(
            '[STAGING -> destino original: %s]',
            $original_to !== '' ? $original_to : '(vazio)'
        );

        $args['subject'] = $marker . ' ' . (string) ($args['subject'] ?? '');

        $args['message'] = self::prepend_notice(
            (string) ($args['message'] ?? ''),
            $original_to,
            $original_cc,
            $original_bcc,
            self::is_html($args['headers'])
        );

        self::log('redirected', [
            'to'      => $original_to,
            'cc'      => $original_cc,
            'bcc'     => $original_bcc,
            'subject' => (string) ($args['subject'] ?? ''),
        ]);

        return $args;
    }

    /**
     * Bloqueio total (modo LR_MAIL_GUARD_HARD_BLOCK).
     *
     * @param null|bool $short_circuit
     * @param array     $atts
     * @return bool
     */
    public static function hard_block($short_circuit, $atts) {
        self::log('hard_blocked', [
            'to'      => self::flatten($atts['to'] ?? ''),
            'subject' => (string) ($atts['subject'] ?? ''),
        ]);

        // true = wp_mail devolve sucesso sem enviar nada. Retornar false faria
        // o chamador tratar como falha de envio e, em alguns fluxos, tentar de novo.
        return true;
    }

    /**
     * Força destinatário nos filtros próprios do WooCommerce.
     *
     * @return string
     */
    public static function force_recipient() {
        return self::redirect_to();
    }

    /**
     * Desregistra remetentes de LOTE fora de produção.
     *
     * Não desliga e-mail transacional (fechamento individual, NF, boas-vindas):
     * esses continuam funcionando redirecionados, que é o que permite testar.
     */
    public static function unhook_bulk_senders() {
        // Recuperação de vendas / campanhas em massa (plugin custom).
        remove_all_actions('lr_rdv_send_batch');
        remove_all_actions('lr_rdv_cron_dispatch');

        // Resumo semanal do programa de parceiros (dispara para todos os afiliados).
        remove_all_actions('lrp_weekly_summary');

        // Relatório semanal do WP Mail SMTP.
        add_filter('wp_mail_smtp_reports_emails_summary_is_disabled', '__return_true', 99);
    }

    /**
     * Aviso no topo do wp-admin.
     */
    public static function admin_notice() {
        $mode = (defined('LR_MAIL_GUARD_HARD_BLOCK') && LR_MAIL_GUARD_HARD_BLOCK)
            ? 'BLOQUEADOS (nenhum e-mail sai)'
            : 'redirecionados para ' . esc_html(self::redirect_to());

        printf(
            '<div class="notice notice-error"><p><strong>AMBIENTE DE TESTES.</strong> Este NÃO é o site de produção (%s). E-mails estão %s.</p></div>',
            esc_html(self::home_url_raw()),
            $mode
        );
    }

    /**
     * Faixa vermelha fixa, para não confundir clone com produção nem de relance.
     */
    public static function admin_bar_banner() {
        echo '<style>#wpadminbar{background:#8b0000 !important}#wpadminbar .ab-item{color:#fff !important}</style>';
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Normaliza to/cc/bcc (string ou array) em string separada por vírgula.
     *
     * @param mixed $value
     * @return string
     */
    private static function flatten($value) {
        if (is_array($value)) {
            return implode(', ', array_filter(array_map('trim', $value)));
        }

        return trim((string) $value);
    }

    /**
     * Extrai destinatários de um header Cc/Bcc.
     *
     * @param mixed  $headers
     * @param string $type 'cc' ou 'bcc'
     * @return string
     */
    private static function extract_header_recipients($headers, $type) {
        $lines = is_array($headers) ? $headers : preg_split("/\r\n|\n|\r/", (string) $headers);
        $found = [];

        foreach ((array) $lines as $line) {
            if (preg_match('/^\s*' . preg_quote($type, '/') . '\s*:\s*(.+)$/i', (string) $line, $m)) {
                $found[] = trim($m[1]);
            }
        }

        return implode(', ', $found);
    }

    /**
     * Remove headers Cc/Bcc, mantendo o resto intacto.
     *
     * @param mixed $headers
     * @return mixed
     */
    private static function strip_recipient_headers($headers) {
        if (is_array($headers)) {
            return array_values(array_filter($headers, function ($line) {
                return !preg_match('/^\s*(cc|bcc)\s*:/i', (string) $line);
            }));
        }

        $lines = preg_split("/\r\n|\n|\r/", (string) $headers);
        $kept  = array_filter((array) $lines, function ($line) {
            return $line !== '' && !preg_match('/^\s*(cc|bcc)\s*:/i', (string) $line);
        });

        return implode("\r\n", $kept);
    }

    /**
     * O e-mail é HTML?
     *
     * @param mixed $headers
     * @return bool
     */
    private static function is_html($headers) {
        $flat = is_array($headers) ? implode("\n", $headers) : (string) $headers;

        return stripos($flat, 'text/html') !== false;
    }

    /**
     * Injeta bloco de aviso no topo do corpo.
     *
     * @param string $message
     * @param string $to
     * @param string $cc
     * @param string $bcc
     * @param bool   $is_html
     * @return string
     */
    private static function prepend_notice($message, $to, $cc, $bcc, $is_html) {
        $rows = [
            'Destinatário original' => $to !== '' ? $to : '(vazio)',
            'Cc original'           => $cc !== '' ? $cc : '-',
            'Bcc original'          => $bcc !== '' ? $bcc : '-',
            'Ambiente'              => self::home_url_raw(),
            'Data/hora'             => date_i18n('d/m/Y H:i:s'),
        ];

        if ($is_html) {
            $html = '<div style="border:2px solid #8b0000;background:#fff5f5;padding:12px;margin-bottom:16px;font-family:Arial,sans-serif;font-size:13px;color:#8b0000">'
                . '<strong>E-MAIL DE AMBIENTE DE TESTES - NÃO FOI ENTREGUE AO DESTINATÁRIO REAL</strong><ul style="margin:8px 0 0;padding-left:18px">';

            foreach ($rows as $label => $value) {
                $html .= '<li>' . esc_html($label) . ': ' . esc_html($value) . '</li>';
            }

            $html .= '</ul></div>';

            return $html . $message;
        }

        $text = "=== E-MAIL DE AMBIENTE DE TESTES - NAO ENTREGUE AO DESTINATARIO REAL ===\n";

        foreach ($rows as $label => $value) {
            $text .= $label . ': ' . $value . "\n";
        }

        return $text . "======================================================================\n\n" . $message;
    }

    /**
     * Log de tudo que foi interceptado.
     *
     * @param string $action
     * @param array  $data
     */
    private static function log($action, array $data) {
        $line = sprintf(
            'lr-mail-guard[%s] site=%s to=%s cc=%s bcc=%s subject=%s',
            $action,
            self::home_url_raw(),
            $data['to'] ?? '',
            $data['cc'] ?? '',
            $data['bcc'] ?? '',
            $data['subject'] ?? ''
        );

        if (class_exists('LR_Log')) {
            LR_Log::info('mail-guard', $line);
            return;
        }

        if (function_exists('lrp_log')) {
            lrp_log('Mail Guard: ' . $action, $data);
            return;
        }

        error_log($line);
    }
}

LR_Mail_Guard::init();

}
