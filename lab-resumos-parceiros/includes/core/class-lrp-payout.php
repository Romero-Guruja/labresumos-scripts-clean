<?php
/**
 * Modelo de Payout/Pagamento
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Payout
 * 
 * Helper para operações de pagamento/fechamento.
 */
class LRP_Payout {

    /**
     * Retorna fechamentos pendentes de pagamento
     *
     * @return array
     */
    public static function get_pending_payments() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT c.*, a.user_id, u.display_name as affiliate_name
             FROM {$wpdb->prefix}lrp_closings c
             JOIN {$wpdb->prefix}lrp_affiliates a ON c.affiliate_id = a.id
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE c.status = 'approved'
             ORDER BY c.updated_at ASC"
        );
        
        return $results ?: [];
    }

    /**
     * Retorna total pendente de pagamento
     *
     * @return float
     */
    public static function get_total_pending() {
        global $wpdb;
        
        return (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_commissions), 0) 
             FROM {$wpdb->prefix}lrp_closings 
             WHERE status = 'approved'"
        );
    }

    /**
     * Retorna fechamentos com NF pendente de análise
     *
     * @return array
     */
    public static function get_pending_invoices() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT c.*, a.user_id, u.display_name as affiliate_name
             FROM {$wpdb->prefix}lrp_closings c
             JOIN {$wpdb->prefix}lrp_affiliates a ON c.affiliate_id = a.id
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE c.status = 'invoice_received'
             ORDER BY c.invoice_uploaded_at ASC"
        );
        
        return $results ?: [];
    }

    /**
     * Conta fechamentos por status
     *
     * @param string $status
     * @return int
     */
    public static function count_by_status($status) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_closings WHERE status = %s",
            $status
        ));
    }

    /**
     * Retorna histórico de pagamentos
     *
     * @param array $args
     * @return array
     */
    public static function get_payment_history($args = []) {
        global $wpdb;
        
        $defaults = [
            'limit'  => 20,
            'offset' => 0,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, a.user_id, u.display_name as affiliate_name, 
                    payer.display_name as paid_by_name
             FROM {$wpdb->prefix}lrp_closings c
             JOIN {$wpdb->prefix}lrp_affiliates a ON c.affiliate_id = a.id
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             LEFT JOIN {$wpdb->users} payer ON c.paid_by = payer.ID
             WHERE c.status = 'paid'
             ORDER BY c.paid_at DESC
             LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        ));
        
        return $results ?: [];
    }

    /**
     * Retorna estatísticas de pagamentos do mês
     *
     * @return array
     */
    public static function get_month_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_payments,
                COALESCE(SUM(total_commissions), 0) as total_amount
             FROM {$wpdb->prefix}lrp_closings 
             WHERE status = 'paid'
             AND MONTH(paid_at) = MONTH(CURRENT_DATE())
             AND YEAR(paid_at) = YEAR(CURRENT_DATE())"
        );
        
        return [
            'total_payments' => (int) $stats->total_payments,
            'total_amount'   => (float) $stats->total_amount,
        ];
    }
}

