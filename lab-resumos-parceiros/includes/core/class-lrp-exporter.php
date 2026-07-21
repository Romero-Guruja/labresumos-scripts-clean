<?php
/**
 * Exportador CSV
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Exporter
 * 
 * Exporta dados em formato CSV.
 */
class LRP_Exporter {

    /**
     * Exporta afiliados
     *
     * @param array $args Filtros
     */
    public static function export_affiliates($args = []) {
        global $wpdb;
        
        $filename = 'parceiros-afiliados-' . date('Y-m-d') . '.csv';
        
        self::send_headers($filename);
        
        $output = fopen('php://output', 'w');
        self::write_bom($output);
        
        // Cabeçalhos
        fputcsv($output, [
            'ID',
            'Nome',
            'Email',
            'Cupom',
            'Código de Referência',
            'Status',
            'Sponsor',
            'Total Vendas',
            'Total Receita',
            'Total Comissões',
            'Total Pago',
            'Saldo Atual',
            'Data de Cadastro',
            'Data de Aprovação',
        ], ';');
        
        // Dados
        $affiliates = $wpdb->get_results(
            "SELECT a.*, u.display_name, u.user_email,
                    s.user_id as sponsor_user_id,
                    su.display_name as sponsor_name
             FROM {$wpdb->prefix}lrp_affiliates a
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             LEFT JOIN {$wpdb->prefix}lrp_affiliates s ON a.sponsor_id = s.id
             LEFT JOIN {$wpdb->users} su ON s.user_id = su.ID
             ORDER BY a.created_at DESC"
        );
        
        foreach ($affiliates as $affiliate) {
            fputcsv($output, [
                $affiliate->id,
                $affiliate->display_name,
                $affiliate->user_email,
                $affiliate->coupon_code,
                $affiliate->referral_code,
                self::translate_status($affiliate->status),
                $affiliate->sponsor_name ?: '-',
                $affiliate->total_sales,
                number_format($affiliate->total_revenue, 2, ',', '.'),
                number_format($affiliate->total_commissions, 2, ',', '.'),
                number_format($affiliate->total_paid, 2, ',', '.'),
                number_format($affiliate->current_balance, 2, ',', '.'),
                date('d/m/Y H:i', strtotime($affiliate->created_at)),
                $affiliate->approved_at ? date('d/m/Y H:i', strtotime($affiliate->approved_at)) : '-',
            ], ';');
        }
        
        fclose($output);
        exit;
    }

    /**
     * Exporta vendas/referrals
     *
     * @param string $start_date
     * @param string $end_date
     */
    public static function export_sales($start_date, $end_date) {
        global $wpdb;
        
        $filename = 'parceiros-vendas-' . date('Y-m-d') . '.csv';
        
        self::send_headers($filename);
        
        $output = fopen('php://output', 'w');
        self::write_bom($output);
        
        // Cabeçalhos
        fputcsv($output, [
            'ID',
            'Data',
            'Pedido',
            'Parceiro',
            'Tipo de Atribuição',
            'Cupom Usado',
            'Valor do Pedido',
            'Desconto',
            'Base Comissão',
            'Status',
            'Aluno Guruja',
        ], ';');
        
        // Dados
        $referrals = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name as affiliate_name
             FROM {$wpdb->prefix}lrp_referrals r
             JOIN {$wpdb->prefix}lrp_affiliates a ON r.affiliate_id = a.id
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE r.created_at BETWEEN %s AND %s
             ORDER BY r.created_at DESC",
            $start_date,
            $end_date
        ));
        
        foreach ($referrals as $referral) {
            fputcsv($output, [
                $referral->id,
                date('d/m/Y H:i', strtotime($referral->created_at)),
                '#' . $referral->order_id,
                $referral->affiliate_name,
                self::translate_attribution($referral->attribution_type),
                $referral->coupon_used ?: '-',
                number_format($referral->order_total, 2, ',', '.'),
                number_format($referral->discount_amount, 2, ',', '.'),
                number_format($referral->commission_base, 2, ',', '.'),
                self::translate_status($referral->status),
                $referral->is_guruja_student ? 'Sim' : 'Não',
            ], ';');
        }
        
        fclose($output);
        exit;
    }

    /**
     * Exporta comissões
     *
     * @param string $start_date
     * @param string $end_date
     */
    public static function export_commissions($start_date, $end_date) {
        global $wpdb;
        
        $filename = 'parceiros-comissoes-' . date('Y-m-d') . '.csv';
        
        self::send_headers($filename);
        
        $output = fopen('php://output', 'w');
        self::write_bom($output);
        
        // Cabeçalhos
        fputcsv($output, [
            'ID',
            'Data',
            'Parceiro',
            'Pedido',
            'Tipo',
            'Taxa (%)',
            'Valor',
            'Status',
            'Fechamento',
        ], ';');
        
        // Dados
        $commissions = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, r.order_id, u.display_name as affiliate_name
             FROM {$wpdb->prefix}lrp_commissions c
             JOIN {$wpdb->prefix}lrp_referrals r ON c.referral_id = r.id
             JOIN {$wpdb->prefix}lrp_affiliates a ON c.affiliate_id = a.id
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE c.created_at BETWEEN %s AND %s
             ORDER BY c.created_at DESC",
            $start_date,
            $end_date
        ));
        
        foreach ($commissions as $commission) {
            fputcsv($output, [
                $commission->id,
                date('d/m/Y H:i', strtotime($commission->created_at)),
                $commission->affiliate_name,
                '#' . $commission->order_id,
                self::translate_commission_type($commission->commission_type),
                number_format($commission->commission_rate, 2, ',', '.') . '%',
                number_format($commission->commission_amount, 2, ',', '.'),
                self::translate_status($commission->status),
                $commission->closing_id ? '#' . $commission->closing_id : '-',
            ], ';');
        }
        
        fclose($output);
        exit;
    }

    /**
     * Exporta fechamentos/pagamentos
     *
     * @param string $start_date
     * @param string $end_date
     */
    public static function export_closings($start_date, $end_date) {
        global $wpdb;
        
        $filename = 'parceiros-fechamentos-' . date('Y-m-d') . '.csv';
        
        self::send_headers($filename);
        
        $output = fopen('php://output', 'w');
        self::write_bom($output);
        
        // Cabeçalhos
        fputcsv($output, [
            'ID',
            'Período',
            'Parceiro',
            'Total Vendas',
            'Total Receita',
            'Total Comissões',
            'Status',
            'NF Número',
            'Data NF',
            'Data Pagamento',
        ], ';');
        
        // Dados
        $closings = $wpdb->get_results($wpdb->prepare(
            "SELECT cl.*, u.display_name as affiliate_name
             FROM {$wpdb->prefix}lrp_closings cl
             JOIN {$wpdb->prefix}lrp_affiliates a ON cl.affiliate_id = a.id
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE cl.created_at BETWEEN %s AND %s
             ORDER BY cl.period_year DESC, cl.period_month DESC",
            $start_date,
            $end_date
        ));
        
        foreach ($closings as $closing) {
            fputcsv($output, [
                $closing->id,
                sprintf('%02d/%d', $closing->period_month, $closing->period_year),
                $closing->affiliate_name,
                $closing->total_sales,
                number_format($closing->total_revenue, 2, ',', '.'),
                number_format($closing->total_commissions, 2, ',', '.'),
                self::translate_closing_status($closing->status),
                $closing->invoice_number ?: '-',
                $closing->invoice_uploaded_at ? date('d/m/Y', strtotime($closing->invoice_uploaded_at)) : '-',
                $closing->paid_at ? date('d/m/Y', strtotime($closing->paid_at)) : '-',
            ], ';');
        }
        
        fclose($output);
        exit;
    }

    /**
     * Exporta logs de atividade
     *
     * @param array $args Filtros
     */
    public static function export_logs($args = []) {
        global $wpdb;
        
        $filename = 'parceiros-logs-' . date('Y-m-d') . '.csv';
        
        self::send_headers($filename);
        
        $output = fopen('php://output', 'w');
        self::write_bom($output);
        
        // Cabeçalhos
        fputcsv($output, [
            'ID',
            'Data',
            'Ação',
            'Afiliado',
            'Usuário',
            'IP',
            'Detalhes',
        ], ';');
        
        $where = ['1=1'];
        $values = [];
        
        if (!empty($args['start_date'])) {
            $where[] = 'l.created_at >= %s';
            $values[] = $args['start_date'];
        }
        
        if (!empty($args['end_date'])) {
            $where[] = 'l.created_at <= %s';
            $values[] = $args['end_date'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT l.*, 
                       au.display_name as affiliate_name,
                       u.display_name as user_name
                FROM {$wpdb->prefix}lrp_activity_log l
                LEFT JOIN {$wpdb->prefix}lrp_affiliates a ON l.affiliate_id = a.id
                LEFT JOIN {$wpdb->users} au ON a.user_id = au.ID
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                WHERE $where_clause
                ORDER BY l.created_at DESC
                LIMIT 10000";
        
        $logs = empty($values) ? $wpdb->get_results($sql) : $wpdb->get_results($wpdb->prepare($sql, $values));
        
        foreach ($logs as $log) {
            $details = json_decode($log->details, true);
            $description = is_array($details) ? ($details['description'] ?? '') : $log->details;
            
            fputcsv($output, [
                $log->id,
                date('d/m/Y H:i', strtotime($log->created_at)),
                $log->action,
                $log->affiliate_name ?: '-',
                $log->user_name ?: '-',
                $log->ip_address ?: '-',
                $description,
            ], ';');
        }
        
        fclose($output);
        exit;
    }

    /**
     * Envia headers para download CSV
     *
     * @param string $filename
     */
    private static function send_headers($filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    /**
     * Escreve BOM UTF-8 para compatibilidade com Excel
     *
     * @param resource $output
     */
    private static function write_bom($output) {
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    }

    /**
     * Traduz status
     *
     * @param string $status
     * @return string
     */
    private static function translate_status($status) {
        $statuses = [
            'pending'   => 'Pendente',
            'active'    => 'Ativo',
            'inactive'  => 'Inativo',
            'rejected'  => 'Rejeitado',
            'approved'  => 'Aprovado',
            'refunded'  => 'Reembolsado',
            'paid'      => 'Pago',
            'cancelled' => 'Cancelado',
        ];
        
        return $statuses[$status] ?? $status;
    }

    /**
     * Traduz tipo de atribuição
     *
     * @param string $type
     * @return string
     */
    private static function translate_attribution($type) {
        $types = [
            'coupon' => 'Cupom',
            'link'   => 'Link',
            'direct' => 'Direto',
        ];
        
        return $types[$type] ?? $type;
    }

    /**
     * Traduz tipo de comissão
     *
     * @param string $type
     * @return string
     */
    private static function translate_commission_type($type) {
        $types = [
            'direct'  => 'Direta',
            'level_2' => 'Nível 2',
            'level_3' => 'Nível 3',
        ];
        
        return $types[$type] ?? $type;
    }

    /**
     * Traduz status de fechamento
     *
     * @param string $status
     * @return string
     */
    private static function translate_closing_status($status) {
        $statuses = [
            'open'             => 'Em andamento',
            'closed'           => 'Fechado (abaixo do mínimo)',
            'awaiting_invoice' => 'Aguardando NF',
            'invoice_received' => 'NF recebida',
            'approved'         => 'NF aprovada',
            'rejected'         => 'NF rejeitada',
            'paid'             => 'Pago',
        ];
        
        return $statuses[$status] ?? $status;
    }
}

