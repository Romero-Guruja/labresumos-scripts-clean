<?php
/**
 * Sistema de Logs
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Logger
 * 
 * Gerencia logs de atividades para auditoria.
 */
class LRP_Logger {

    /**
     * Registra uma atividade no log
     *
     * @param string $action Ação realizada
     * @param string $description Descrição detalhada
     * @param array $context Dados adicionais
     * @param int|null $affiliate_id ID do afiliado (se aplicável)
     */
    public static function log($action, $description, $context = [], $affiliate_id = null) {
        global $wpdb;
        
        // Verifica se debug está ativo (para logs detalhados)
        $settings = LRP_Settings::instance();
        $debug_mode = $settings->get('debug_mode', false);
        
        // Alguns logs são sempre gravados
        $critical_actions = [
            'affiliate_created',
            'affiliate_approved',
            'affiliate_rejected',
            'referral_created',
            'commission_created',
            'closing_created',
            'invoice_uploaded',
            'invoice_approved',
            'invoice_rejected',
            'payment_completed',
            'settings_updated',
        ];
        
        // Se não é modo debug e não é ação crítica, não loga
        if (!$debug_mode && !in_array($action, $critical_actions)) {
            // Ainda loga no WooCommerce logger se disponível
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->debug(
                    sprintf('[%s] %s', $action, $description),
                    ['source' => 'lab-resumos-parceiros', 'context' => $context]
                );
            }
            return;
        }
        
        $user_id = get_current_user_id();
        $ip_address = self::get_client_ip();
        
        // Monta detalhes em JSON
        $details = wp_json_encode([
            'description' => $description,
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE);
        
        $wpdb->insert(
            $wpdb->prefix . 'lrp_activity_log',
            [
                'affiliate_id' => $affiliate_id,
                'action'       => sanitize_key($action),
                'details'      => $details,
                'user_id'      => $user_id ?: null,
                'ip_address'   => $ip_address,
                'created_at'   => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s']
        );
        
        // Também loga no WooCommerce logger se disponível
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->info(
                sprintf('[%s] %s', $action, $description),
                ['source' => 'lab-resumos-parceiros', 'context' => $context]
            );
        }
    }

    /**
     * Registra erro
     *
     * @param string $action
     * @param string $message
     * @param array $context
     */
    public static function error($action, $message, $context = []) {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error(
                sprintf('[%s] %s', $action, $message),
                ['source' => 'lab-resumos-parceiros', 'context' => $context]
            );
        }
        
        // Erros sempre são gravados na tabela
        self::log('error_' . $action, $message, $context);
    }

    /**
     * Obtém logs filtrados
     *
     * @param array $args Argumentos de filtro
     * @return array
     */
    public static function get_logs($args = []) {
        global $wpdb;
        
        $defaults = [
            'affiliate_id' => null,
            'action'       => null,
            'user_id'      => null,
            'start_date'   => null,
            'end_date'     => null,
            'limit'        => 50,
            'offset'       => 0,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ['1=1'];
        $values = [];
        
        if ($args['affiliate_id']) {
            $where[] = 'affiliate_id = %d';
            $values[] = $args['affiliate_id'];
        }
        
        if ($args['action']) {
            $where[] = 'action = %s';
            $values[] = $args['action'];
        }
        
        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $values[] = $args['user_id'];
        }
        
        if ($args['start_date']) {
            $where[] = 'created_at >= %s';
            $values[] = $args['start_date'];
        }
        
        if ($args['end_date']) {
            $where[] = 'created_at <= %s';
            $values[] = $args['end_date'];
        }
        
        $where_clause = implode(' AND ', $where);
        $values[] = $args['limit'];
        $values[] = $args['offset'];
        
        $sql = "SELECT * FROM {$wpdb->prefix}lrp_activity_log 
                WHERE $where_clause 
                ORDER BY created_at DESC 
                LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    }

    /**
     * Conta total de logs com filtros
     *
     * @param array $args
     * @return int
     */
    public static function count_logs($args = []) {
        global $wpdb;
        
        $where = ['1=1'];
        $values = [];
        
        if (!empty($args['affiliate_id'])) {
            $where[] = 'affiliate_id = %d';
            $values[] = $args['affiliate_id'];
        }
        
        if (!empty($args['action'])) {
            $where[] = 'action = %s';
            $values[] = $args['action'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        if (empty($values)) {
            return (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_activity_log WHERE $where_clause"
            );
        }
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_activity_log WHERE $where_clause",
            $values
        ));
    }

    /**
     * Obtém IP do cliente (com proteção contra falsificação)
     *
     * @return string|null
     */
    private static function get_client_ip() {
        // Ordem de prioridade para headers
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Se for X-Forwarded-For, pega o primeiro IP da lista
                if ($header === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Valida se é um IP válido
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
                
                // Aceita IPs privados também (para ambientes de desenvolvimento)
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return null;
    }

    /**
     * Obtém ações disponíveis para filtro
     *
     * @return array
     */
    public static function get_available_actions() {
        return [
            'affiliate_created'     => __('Afiliado criado', 'lab-resumos-parceiros'),
            'affiliate_approved'    => __('Afiliado aprovado', 'lab-resumos-parceiros'),
            'affiliate_rejected'    => __('Afiliado rejeitado', 'lab-resumos-parceiros'),
            'affiliate_updated'     => __('Afiliado atualizado', 'lab-resumos-parceiros'),
            'referral_created'      => __('Venda registrada', 'lab-resumos-parceiros'),
            'referral_approved'     => __('Venda aprovada', 'lab-resumos-parceiros'),
            'commission_created'    => __('Comissão criada', 'lab-resumos-parceiros'),
            'closing_created'       => __('Fechamento criado', 'lab-resumos-parceiros'),
            'invoice_uploaded'      => __('NF enviada', 'lab-resumos-parceiros'),
            'invoice_approved'      => __('NF aprovada', 'lab-resumos-parceiros'),
            'invoice_rejected'      => __('NF rejeitada', 'lab-resumos-parceiros'),
            'payment_completed'     => __('Pagamento realizado', 'lab-resumos-parceiros'),
            'settings_updated'      => __('Configurações atualizadas', 'lab-resumos-parceiros'),
            'login'                 => __('Login', 'lab-resumos-parceiros'),
            'cron'                  => __('Tarefa agendada', 'lab-resumos-parceiros'),
        ];
    }

    /**
     * Formata detalhes do log para exibição
     *
     * @param string $details JSON string
     * @return array
     */
    public static function format_details($details) {
        $data = json_decode($details, true);
        
        if (!is_array($data)) {
            return ['description' => $details, 'context' => []];
        }
        
        return $data;
    }
}

