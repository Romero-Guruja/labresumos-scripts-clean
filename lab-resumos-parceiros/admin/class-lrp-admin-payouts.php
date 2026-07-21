<?php
/**
 * Admin - Gerenciamento de Pagamentos
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Admin_Payouts
 */
class LRP_Admin_Payouts {

    /**
     * Aprova NF
     *
     * @param int $closing_id
     * @return bool|WP_Error
     */
    public static function approve_invoice($closing_id) {
        return LRP_Closing::approve_invoice($closing_id, get_current_user_id());
    }

    /**
     * Aprova RPA (v1.7.1)
     *
     * @param int $closing_id
     * @return bool|WP_Error
     */
    public static function approve_rpa($closing_id) {
        return LRP_Closing::approve_rpa($closing_id, get_current_user_id());
    }

    /**
     * Rejeita NF
     *
     * @param int $closing_id
     * @param string $reason
     * @return bool
     */
    public static function reject_invoice($closing_id, $reason) {
        return LRP_Closing::reject_invoice($closing_id, $reason, get_current_user_id());
    }

    /**
     * Confirma pagamento
     *
     * @param int $closing_id
     * @param array $proof_file
     * @param string $notes
     * @return true|WP_Error
     */
    public static function confirm_payment($closing_id, $proof_file, $notes = '') {
        return LRP_Closing::confirm_payment($closing_id, $proof_file, get_current_user_id(), $notes);
    }

    /**
     * Exporta pagamentos para CSV
     *
     * @param array $filters
     */
    public static function export_csv($filters = []) {
        global $wpdb;
        
        $where = "WHERE c.status = 'paid'";
        $params = [];
        
        if (!empty($filters['start_date'])) {
            $where .= " AND c.paid_at >= %s";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where .= " AND c.paid_at <= %s";
            $params[] = $filters['end_date'];
        }
        
        $sql = "SELECT c.*, u.display_name as affiliate_name, u.user_email,
                       a.holder_name, a.holder_document
                FROM {$wpdb->prefix}lrp_closings c
                JOIN {$wpdb->prefix}lrp_affiliates a ON c.affiliate_id = a.id
                JOIN {$wpdb->users} u ON a.user_id = u.ID
                $where
                ORDER BY c.paid_at DESC";
        
        if (!empty($params)) {
            $payments = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        } else {
            $payments = $wpdb->get_results($sql);
        }
        
        $filename = 'pagamentos-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        fputcsv($output, [
            'ID Fechamento',
            'Período',
            'Afiliado',
            'Email',
            'Titular',
            'CPF/CNPJ',
            'NF Número',
            'Valor',
            'Data Pagamento',
        ], ';');
        
        foreach ($payments as $p) {
            fputcsv($output, [
                $p->id,
                sprintf('%02d/%d', $p->period_month, $p->period_year),
                $p->affiliate_name,
                $p->user_email,
                $p->holder_name,
                $p->holder_document,
                $p->invoice_number,
                number_format($p->total_commissions, 2, ',', ''),
                date('d/m/Y', strtotime($p->paid_at)),
            ], ';');
        }
        
        fclose($output);
        exit;
    }
}

