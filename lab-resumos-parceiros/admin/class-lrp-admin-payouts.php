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
            'Baixa Manual',
            'Método da Baixa',
            'Motivo da Baixa',
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
                !empty($p->settled_manually) ? 'Sim' : 'Não',
                !empty($p->settled_manually) ? LRP_Closing::get_settlement_method_label($p->settlement_method) : '-',
                !empty($p->settled_manually) ? (string) $p->settlement_reason : '-',
            ], ';');
        }
        
        fclose($output);
        exit;
    }

    /**
     * Exporta a conciliação (tudo que é devido) para CSV
     *
     * Mesmas colunas da tela de Conciliação e mesma fonte de dados
     * (LRP_Closing::get_receivables), para que tela e CSV nunca divirjam.
     *
     * @since 1.8.0
     * @param array $filters
     */
    public static function export_receivables_csv($filters = []) {
        $rows   = LRP_Closing::get_receivables($filters);
        $totals = 0.0;

        $filename = 'comissoes-devidas-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, [
            'ID Fechamento',
            'Período',
            'Parceiro',
            'Email',
            'Tipo',
            'Titular',
            'CPF/CNPJ',
            'Status',
            'Comissões',
            'Ajustes',
            'Total Devido',
            'NF Número',
            'Dias Parado',
        ], ';');

        foreach ($rows as $r) {
            $totals += (float) $r->final_amount;

            fputcsv($output, [
                $r->id,
                sprintf('%02d/%d', $r->period_month, $r->period_year),
                $r->affiliate_name,
                $r->affiliate_email,
                strtoupper($r->billing_type ?: '-'),
                $r->holder_name,
                $r->holder_document,
                LRP_Closing::get_status_label($r->status),
                number_format((float) $r->total_commissions, 2, ',', ''),
                number_format((float) $r->adjustments_sum, 2, ',', ''),
                number_format((float) $r->final_amount, 2, ',', ''),
                $r->invoice_number ?: '-',
                $r->days_pending,
            ], ';');
        }

        // Linha de total, para conferência imediata no Excel.
        fputcsv($output, [], ';');
        fputcsv($output, [
            'TOTAL',
            '',
            count($rows) . ' fechamento(s)',
            '', '', '', '', '', '', '',
            number_format($totals, 2, ',', ''),
            '', '',
        ], ';');

        fclose($output);
        exit;
    }
}

