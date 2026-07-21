<?php
/**
 * Admin - Ajustes Manuais
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Admin_Adjustments
 * 
 * Gerencia a página de ajustes no admin.
 */
class LRP_Admin_Adjustments {

    /**
     * Renderiza a página de ajustes
     */
    public static function render() {
        // Filtros
        $filters = [
            'affiliate_id' => isset($_GET['affiliate_id']) ? (int) $_GET['affiliate_id'] : null,
            'status'       => isset($_GET['status']) ? sanitize_key($_GET['status']) : null,
            'date_from'    => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : null,
            'date_to'      => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : null,
        ];
        
        // Paginação
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 30;
        
        // Busca ajustes
        $result = LRP_Adjustment::get_all(array_merge($filters, [
            'limit'  => $per_page,
            'offset' => ($paged - 1) * $per_page,
        ]));
        
        $adjustments = $result['items'];
        $total = $result['total'];
        $total_pages = ceil($total / $per_page);
        
        // Estatísticas
        $stats = LRP_Adjustment::get_stats();
        
        // Lista de afiliados para o select
        $affiliates = self::get_affiliates_for_select();
        
        include LRP_PLUGIN_DIR . 'admin/partials/adjustments.php';
    }

    /**
     * Obtém lista de afiliados para o select
     *
     * @return array
     */
    private static function get_affiliates_for_select() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT a.id, u.display_name, a.coupon_code
             FROM {$wpdb->prefix}lrp_affiliates a
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE a.status = 'active'
             ORDER BY u.display_name ASC"
        );
    }
}
