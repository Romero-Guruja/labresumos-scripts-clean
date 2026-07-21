<?php
/**
 * Admin - Gerenciamento de Comissões
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Admin_Commissions
 */
class LRP_Admin_Commissions {

    /**
     * Aprova comissões manualmente
     *
     * @param array $commission_ids
     * @return int Número de comissões aprovadas
     */
    public static function approve_bulk($commission_ids) {
        global $wpdb;
        
        $count = 0;
        
        foreach ($commission_ids as $id) {
            $result = $wpdb->update(
                $wpdb->prefix . 'lrp_commissions',
                ['status' => 'approved'],
                ['id' => (int) $id, 'status' => 'pending']
            );
            
            if ($result) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Cancela comissões
     *
     * @param array $commission_ids
     * @return int
     */
    public static function cancel_bulk($commission_ids) {
        global $wpdb;
        
        $count = 0;
        
        foreach ($commission_ids as $id) {
            $result = $wpdb->update(
                $wpdb->prefix . 'lrp_commissions',
                ['status' => 'cancelled'],
                ['id' => (int) $id]
            );
            
            if ($result) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Exporta comissões para CSV
     *
     * @param array $filters
     */
    public static function export_csv($filters = []) {
        global $wpdb;
        
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $where .= " AND c.status = %s";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['start_date'])) {
            $where .= " AND c.created_at >= %s";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where .= " AND c.created_at <= %s";
            $params[] = $filters['end_date'];
        }
        
        $sql = "SELECT c.*, r.order_id, r.attribution_type, r.commission_base,
                       u.display_name as affiliate_name, u.user_email
                FROM {$wpdb->prefix}lrp_commissions c
                JOIN {$wpdb->prefix}lrp_referrals r ON c.referral_id = r.id
                JOIN {$wpdb->prefix}lrp_affiliates a ON c.affiliate_id = a.id
                JOIN {$wpdb->users} u ON a.user_id = u.ID
                $where
                ORDER BY c.created_at DESC";
        
        if (!empty($params)) {
            $commissions = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        } else {
            $commissions = $wpdb->get_results($sql);
        }
        
        $filename = 'comissoes-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        fputcsv($output, [
            'ID',
            'Afiliado',
            'Email',
            'Pedido',
            'Tipo',
            'Nível',
            'Base',
            'Taxa',
            'Comissão',
            'Status',
            'Data',
        ], ';');
        
        foreach ($commissions as $c) {
            $tipo_nivel = [
                'direct'  => 'Direta',
                'level_2' => 'Nível 2',
                'level_3' => 'Nível 3',
            ];
            
            fputcsv($output, [
                $c->id,
                $c->affiliate_name,
                $c->user_email,
                $c->order_id,
                $c->attribution_type === 'coupon' ? 'Cupom' : 'Link',
                $tipo_nivel[$c->commission_type] ?? $c->commission_type,
                number_format($c->commission_base, 2, ',', ''),
                $c->commission_rate . '%',
                number_format($c->commission_amount, 2, ',', ''),
                $c->status,
                date('d/m/Y H:i', strtotime($c->created_at)),
            ], ';');
        }
        
        fclose($output);
        exit;
    }
}

