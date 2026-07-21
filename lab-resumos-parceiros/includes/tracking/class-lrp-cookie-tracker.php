<?php
/**
 * Rastreamento por Cookie
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Cookie_Tracker
 * 
 * Gerencia cookies de rastreamento de afiliados.
 */
class LRP_Cookie_Tracker {

    /**
     * Nome do cookie de referral
     */
    const COOKIE_NAME = 'lrp_ref';

    /**
     * Nome do cookie de visita
     */
    const VISIT_COOKIE_NAME = 'lrp_visit_hash';

    /**
     * Instância única
     *
     * @var LRP_Cookie_Tracker|null
     */
    private static $instance = null;

    /**
     * Retorna instância única
     *
     * @return LRP_Cookie_Tracker
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado
     */
    private function __construct() {
        // Captura parâmetro ref da URL e seta cookie
        add_action('template_redirect', [$this, 'capture_referral'], 5);
    }

    /**
     * Captura parâmetro ref da URL e seta cookie
     */
    public function capture_referral() {
        if (!isset($_GET['ref'])) {
            return;
        }
        
        $ref_code = sanitize_text_field($_GET['ref']);
        $affiliate = $this->get_affiliate_by_code($ref_code);
        
        if (!$affiliate || !$affiliate->is_active()) {
            return;
        }
        
        // Obtém duração do cookie
        $cookie_days = $affiliate->get_cookie_days();
        
        $expiry = time() + ($cookie_days * DAY_IN_SECONDS);
        
        // Seta cookie com flags de segurança
        setcookie(
            self::COOKIE_NAME,
            $ref_code,
            [
                'expires'  => $expiry,
                'path'     => '/',
                'domain'   => '',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
        
        // Define cookie para esta requisição
        $_COOKIE[self::COOKIE_NAME] = $ref_code;
        
        // Registra visita
        $this->record_visit($affiliate->get_id());
        
        lrp_log('Cookie de referral setado', [
            'ref_code'    => $ref_code,
            'affiliate_id' => $affiliate->get_id(),
            'expiry_days' => $cookie_days,
        ]);
    }

    /**
     * Seta cookie manualmente
     *
     * @param string $referral_code
     * @return bool
     */
    public function set_cookie($referral_code) {
        $affiliate = $this->get_affiliate_by_code($referral_code);
        
        if (!$affiliate || !$affiliate->is_active()) {
            return false;
        }
        
        $cookie_days = $affiliate->get_cookie_days();
        $expiry = time() + ($cookie_days * DAY_IN_SECONDS);
        
        setcookie(
            self::COOKIE_NAME,
            $referral_code,
            [
                'expires'  => $expiry,
                'path'     => '/',
                'domain'   => '',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
        
        $_COOKIE[self::COOKIE_NAME] = $referral_code;
        
        return true;
    }

    /**
     * Verifica se cookie de afiliado é válido
     *
     * @return bool
     */
    public function is_cookie_valid() {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return false;
        }
        
        $ref_code = sanitize_text_field($_COOKIE[self::COOKIE_NAME]);
        $affiliate = $this->get_affiliate_by_code($ref_code);
        
        return $affiliate && $affiliate->is_active();
    }

    /**
     * Obtém código de referral do cookie
     *
     * @return string|null
     */
    public function get_referral_code() {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        
        return sanitize_text_field($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Obtém afiliado do cookie
     *
     * @return LRP_Affiliate|null
     */
    public function get_affiliate_from_cookie() {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        
        $ref_code = sanitize_text_field($_COOKIE[self::COOKIE_NAME]);
        return $this->get_affiliate_by_code($ref_code);
    }

    /**
     * Busca afiliado pelo código de referral
     *
     * @param string $ref_code
     * @return LRP_Affiliate|null
     */
    private function get_affiliate_by_code($ref_code) {
        return LRP_Affiliate::get_by_referral_code($ref_code);
    }

    /**
     * Obtém IP real do cliente
     *
     * @return string
     */
    private function get_client_ip() {
        // Verifica se há proxy confiável configurado
        if (defined('LRP_TRUSTED_PROXY') && LRP_TRUSTED_PROXY) {
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
            if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                $ip = trim($_SERVER['HTTP_X_REAL_IP']);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Obtém ou gera UUID único do visitante
     *
     * @return string
     */
    private function get_visitor_uuid() {
        // Verifica se já existe UUID no cookie
        if (isset($_COOKIE[self::VISIT_COOKIE_NAME])) {
            $uuid = sanitize_text_field($_COOKIE[self::VISIT_COOKIE_NAME]);
            // Valida formato UUID v4
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
                return $uuid;
            }
        }
        
        // Gera novo UUID v4
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        // Armazena UUID em cookie (1 ano)
        setcookie(
            self::VISIT_COOKIE_NAME,
            $uuid,
            [
                'expires'  => time() + (365 * DAY_IN_SECONDS),
                'path'     => '/',
                'domain'   => '',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
        
        $_COOKIE[self::VISIT_COOKIE_NAME] = $uuid;
        
        return $uuid;
    }

    /**
     * Registra visita na tabela lrp_visits
     *
     * @param int $affiliate_id
     */
    private function record_visit($affiliate_id) {
        global $wpdb;
        
        $visitor_uuid = $this->get_visitor_uuid();
        
        // Evita duplicatas recentes (últimas 24h)
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lrp_visits 
             WHERE affiliate_id = %d AND visitor_hash = %s 
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $affiliate_id,
            $visitor_uuid
        ));
        
        if ($exists) {
            return;
        }
        
        $wpdb->insert($wpdb->prefix . 'lrp_visits', [
            'affiliate_id' => $affiliate_id,
            'visitor_ip'   => $this->get_client_ip() ?: null,
            'visitor_hash' => $visitor_uuid,
            'referral_url' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : null,
            'landing_page' => isset($_SERVER['REQUEST_URI']) ? esc_url_raw($_SERVER['REQUEST_URI']) : null,
            'created_at'   => current_time('mysql'),
        ]);
    }

    /**
     * Marca visita como convertida após venda
     *
     * @param int $order_id
     */
    public function mark_visit_converted($order_id) {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return;
        }
        
        global $wpdb;
        
        $visitor_uuid = $this->get_visitor_uuid();
        
        // Atualiza visitas não convertidas deste visitante
        $wpdb->update(
            $wpdb->prefix . 'lrp_visits',
            [
                'converted' => 1,
                'order_id'  => $order_id,
            ],
            [
                'visitor_hash' => $visitor_uuid,
                'converted'    => 0,
            ]
        );
        
        lrp_log('Visita marcada como convertida', [
            'order_id'     => $order_id,
            'visitor_hash' => $visitor_uuid,
        ]);
    }

    /**
     * Limpa cookie de referral
     */
    public function clear_cookie() {
        setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires'  => time() - 3600,
                'path'     => '/',
                'domain'   => '',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
        
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Retorna estatísticas de visitas de um afiliado
     *
     * @param int $affiliate_id
     * @param string $period today|week|month|all
     * @return array
     */
    public function get_visit_stats($affiliate_id, $period = 'month') {
        global $wpdb;
        
        $date_condition = '';
        
        switch ($period) {
            case 'today':
                $date_condition = "AND DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_visits,
                COUNT(DISTINCT visitor_hash) as unique_visitors,
                SUM(converted) as conversions
             FROM {$wpdb->prefix}lrp_visits 
             WHERE affiliate_id = %d $date_condition",
            $affiliate_id
        ));
        
        $total = (int) $stats->total_visits;
        $unique = (int) $stats->unique_visitors;
        $conversions = (int) $stats->conversions;
        
        return [
            'total_visits'    => $total,
            'unique_visitors' => $unique,
            'conversions'     => $conversions,
            'conversion_rate' => $unique > 0 ? round(($conversions / $unique) * 100, 2) : 0,
        ];
    }
}

