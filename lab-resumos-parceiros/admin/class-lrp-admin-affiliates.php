<?php
/**
 * Admin - Gerenciamento de Afiliados
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Admin_Affiliates
 */
class LRP_Admin_Affiliates {

    /**
     * Processa ações de afiliados
     *
     * @param string $action
     * @param int $affiliate_id
     * @return bool|WP_Error
     */
    public static function process_action($action, $affiliate_id) {
        $affiliate = new LRP_Affiliate($affiliate_id);
        
        if (!$affiliate->exists()) {
            return new WP_Error('not_found', __('Afiliado não encontrado.', 'lab-resumos-parceiros'));
        }
        
        switch ($action) {
            case 'approve':
                return self::approve($affiliate);
                
            case 'reject':
                $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
                return self::reject($affiliate, $reason);
                
            case 'deactivate':
                return self::deactivate($affiliate);
                
            case 'reactivate':
                return self::reactivate($affiliate);
                
            default:
                return new WP_Error('invalid_action', __('Ação inválida.', 'lab-resumos-parceiros'));
        }
    }

    /**
     * Aprova afiliado
     *
     * @param LRP_Affiliate $affiliate
     * @return bool
     */
    public static function approve($affiliate) {
        $result = $affiliate->update(['status' => 'active']);
        
        if ($result) {
            // Cria cupom
            LRP_Coupon_Handler::instance()->create_affiliate_coupon($affiliate);
            
            // Dispara action (envia email)
            do_action('lrp_affiliate_approved', $affiliate);
        }
        
        return $result;
    }

    /**
     * Rejeita afiliado
     *
     * @param LRP_Affiliate $affiliate
     * @param string $reason
     * @return bool
     */
    public static function reject($affiliate, $reason = '') {
        $result = $affiliate->update([
            'status'      => 'rejected',
            'admin_notes' => $reason,
        ]);
        
        if ($result) {
            do_action('lrp_affiliate_rejected', $affiliate, $reason);
        }
        
        return $result;
    }

    /**
     * Desativa afiliado
     *
     * @param LRP_Affiliate $affiliate
     * @return bool
     */
    public static function deactivate($affiliate) {
        return $affiliate->update(['status' => 'inactive']);
    }

    /**
     * Reativa afiliado
     *
     * @param LRP_Affiliate $affiliate
     * @return bool
     */
    public static function reactivate($affiliate) {
        return $affiliate->update(['status' => 'active']);
    }

    /**
     * Atualiza dados do afiliado
     *
     * @param int $affiliate_id
     * @param array $data
     * @return bool
     */
    public static function update($affiliate_id, $data) {
        $affiliate = new LRP_Affiliate($affiliate_id);
        
        if (!$affiliate->exists()) {
            return false;
        }
        
        return $affiliate->update($data);
    }

    /**
     * Exporta afiliados para CSV
     *
     * @param array $filters
     */
    public static function export_csv($filters = []) {
        global $wpdb;
        
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $where .= " AND a.status = %s";
            $params[] = $filters['status'];
        }
        
        $sql = "SELECT a.*, u.display_name, u.user_email
                FROM {$wpdb->prefix}lrp_affiliates a
                JOIN {$wpdb->users} u ON a.user_id = u.ID
                $where
                ORDER BY a.created_at DESC";
        
        if (!empty($params)) {
            $affiliates = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        } else {
            $affiliates = $wpdb->get_results($sql);
        }
        
        $filename = 'afiliados-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // Header
        fputcsv($output, [
            'ID',
            'Nome',
            'Email',
            'Cupom',
            'Status',
            'Vendas',
            'Receita',
            'Comissões',
            'Saldo',
            'Data Cadastro',
        ], ';');
        
        foreach ($affiliates as $a) {
            fputcsv($output, [
                $a->id,
                $a->display_name,
                $a->user_email,
                $a->coupon_code,
                $a->status,
                $a->total_sales,
                number_format($a->total_revenue, 2, ',', ''),
                number_format($a->total_commissions, 2, ',', ''),
                number_format($a->current_balance, 2, ',', ''),
                date('d/m/Y', strtotime($a->created_at)),
            ], ';');
        }
        
        fclose($output);
        exit;
    }
}

