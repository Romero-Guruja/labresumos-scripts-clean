<?php
/**
 * Admin - Relatórios
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Admin_Reports
 */
class LRP_Admin_Reports {

    /**
     * Obtém dados para o relatório geral
     *
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public static function get_overview($start_date, $end_date) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT r.id) as total_sales,
                COALESCE(SUM(r.commission_base), 0) as total_revenue,
                COALESCE(SUM(c.commission_amount), 0) as total_commissions,
                COUNT(DISTINCT r.affiliate_id) as active_affiliates
             FROM {$wpdb->prefix}lrp_referrals r
             LEFT JOIN {$wpdb->prefix}lrp_commissions c ON r.id = c.referral_id AND c.commission_type = 'direct'
             WHERE r.created_at BETWEEN %s AND %s",
            $start_date,
            $end_date
        ), ARRAY_A);
    }

    /**
     * Obtém vendas por dia para gráfico
     *
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public static function get_sales_by_day($start_date, $end_date) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as sales,
                COALESCE(SUM(commission_base), 0) as revenue
             FROM {$wpdb->prefix}lrp_referrals
             WHERE created_at BETWEEN %s AND %s
             GROUP BY DATE(created_at)
             ORDER BY date",
            $start_date,
            $end_date
        ), ARRAY_A);
    }

    /**
     * Obtém ranking de afiliados
     *
     * @param string $start_date
     * @param string $end_date
     * @param int $limit
     * @return array
     */
    public static function get_affiliate_ranking($start_date, $end_date, $limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                a.id,
                u.display_name,
                COUNT(r.id) as sales,
                COALESCE(SUM(r.commission_base), 0) as revenue,
                COALESCE(SUM(c.commission_amount), 0) as commissions
             FROM {$wpdb->prefix}lrp_affiliates a
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             LEFT JOIN {$wpdb->prefix}lrp_referrals r ON a.id = r.affiliate_id 
                AND r.created_at BETWEEN %s AND %s
             LEFT JOIN {$wpdb->prefix}lrp_commissions c ON r.id = c.referral_id 
                AND c.commission_type = 'direct'
             WHERE a.status = 'active'
             GROUP BY a.id, u.display_name
             HAVING sales > 0
             ORDER BY revenue DESC
             LIMIT %d",
            $start_date,
            $end_date,
            $limit
        ), ARRAY_A);
    }

    /**
     * Obtém relatório por tipo de atribuição
     *
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public static function get_attribution_breakdown($start_date, $end_date) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                attribution_type,
                COUNT(*) as count,
                COALESCE(SUM(commission_base), 0) as revenue
             FROM {$wpdb->prefix}lrp_referrals
             WHERE created_at BETWEEN %s AND %s
             GROUP BY attribution_type",
            $start_date,
            $end_date
        ), ARRAY_A);
    }

    /**
     * Obtém relatório de pagamentos
     *
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public static function get_payments_report($start_date, $end_date) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(paid_at, '%%Y-%%m') as month,
                COUNT(*) as payments,
                COALESCE(SUM(total_commissions), 0) as total_paid
             FROM {$wpdb->prefix}lrp_closings
             WHERE status = 'paid'
             AND paid_at BETWEEN %s AND %s
             GROUP BY DATE_FORMAT(paid_at, '%%Y-%%m')
             ORDER BY month",
            $start_date,
            $end_date
        ), ARRAY_A);
    }

    /**
     * Obtém relatório de rede (multi-nível)
     *
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public static function get_network_report($start_date, $end_date) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                commission_type,
                COUNT(*) as count,
                COALESCE(SUM(commission_amount), 0) as total
             FROM {$wpdb->prefix}lrp_commissions
             WHERE created_at BETWEEN %s AND %s
             AND status IN ('approved', 'paid')
             GROUP BY commission_type",
            $start_date,
            $end_date
        ), ARRAY_A);
    }
}

