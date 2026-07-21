<?php
/**
 * Fechamento Mensal
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Closing
 * 
 * Gerencia fechamentos mensais e ciclo de pagamento.
 */
class LRP_Closing {

    /**
     * Busca fechamento por ID
     *
     * @param int $id
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lrp_closings WHERE id = %d",
            $id
        ));
    }

    /**
     * Busca fechamento por afiliado e período
     *
     * @param int $affiliate_id
     * @param int $month
     * @param int $year
     * @return object|null
     */
    public static function get_by_period($affiliate_id, $month, $year) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lrp_closings 
             WHERE affiliate_id = %d AND period_month = %d AND period_year = %d",
            $affiliate_id,
            $month,
            $year
        ));
    }

    /**
     * Busca fechamentos de um afiliado
     *
     * @param int $affiliate_id
     * @param array $args
     * @return array
     */
    public static function get_by_affiliate($affiliate_id, $args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => null,
            'limit'  => 20,
            'offset' => 0,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM {$wpdb->prefix}lrp_closings WHERE affiliate_id = %d";
        $params = [$affiliate_id];
        
        if ($args['status']) {
            $sql .= " AND status = %s";
            $params[] = $args['status'];
        }
        
        $sql .= " ORDER BY period_year DESC, period_month DESC";
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }

    /**
     * Busca fechamentos por status
     *
     * @param string $status
     * @param array $args
     * @return array
     */
    public static function get_by_status($status, $args = []) {
        global $wpdb;
        
        $defaults = [
            'orderby' => 'updated_at',
            'order'   => 'ASC',
            'limit'   => 100,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, a.user_id, u.display_name as affiliate_name
             FROM {$wpdb->prefix}lrp_closings c
             JOIN {$wpdb->prefix}lrp_affiliates a ON c.affiliate_id = a.id
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE c.status = %s
             ORDER BY c.{$args['orderby']} {$args['order']}
             LIMIT %d",
            $status,
            $args['limit']
        ));
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
     * Soma total pendente de pagamento
     *
     * @return float
     */
    public static function sum_pending_amount() {
        global $wpdb;
        
        // Soma comissões dos fechamentos pendentes
        $commissions_sum = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_commissions + COALESCE(adjustment_amount, 0)), 0) 
             FROM {$wpdb->prefix}lrp_closings 
             WHERE status IN ('awaiting_invoice', 'awaiting_rpa', 'invoice_received', 'approved')"
        );
        
        // Soma ajustes do novo sistema vinculados a fechamentos pendentes (v1.4.0)
        $adjustments_sum = 0.0;
        if (class_exists('LRP_Adjustment')) {
            $closing_ids = $wpdb->get_col(
                "SELECT id FROM {$wpdb->prefix}lrp_closings 
                 WHERE status IN ('awaiting_invoice', 'awaiting_rpa', 'invoice_received', 'approved')"
            );
            
            if (!empty($closing_ids)) {
                $adjustments_sum = (float) $wpdb->get_var(
                    "SELECT COALESCE(SUM(amount), 0) 
                     FROM {$wpdb->prefix}lrp_adjustments 
                     WHERE closing_id IN (" . implode(',', array_map('intval', $closing_ids)) . ")
                     AND status IN ('closed', 'paid')"
                );
            }
        }
        
        return $commissions_sum + $adjustments_sum;
    }

    /**
     * Retorna o valor total final do fechamento (comissões + ajustes)
     *
     * @param object $closing
     * @return float
     */
    public static function get_final_amount($closing) {
        $commissions = (float) ($closing->total_commissions ?? 0);
        
        // Ajustes do novo sistema (v1.4.0)
        $adjustments_sum = 0.0;
        if (class_exists('LRP_Adjustment') && !empty($closing->id)) {
            $adjustments_sum = LRP_Adjustment::get_closing_sum($closing->id);
        }
        
        // Mantém compatibilidade com ajuste antigo (se existir)
        $old_adjustment = (float) ($closing->adjustment_amount ?? 0);
        
        return $commissions + $adjustments_sum + $old_adjustment;
    }

    /**
     * Busca fechamento pendente (NF ou RPA)
     * 
     * Busca o fechamento mais recente com status pendente de ação:
     * - awaiting_invoice (PJ precisa enviar NF)
     * - awaiting_rpa (RPA aguardando emissão pela empresa)
     * - invoice_received (NF em análise)
     * - rejected (NF rejeitada, precisa reenviar)
     * - approved (aprovado, aguardando pagamento)
     *
     * @param int $affiliate_id
     * @return object|null
     */
    public static function get_pending_invoice($affiliate_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lrp_closings 
             WHERE affiliate_id = %d AND status IN ('awaiting_invoice', 'awaiting_rpa', 'invoice_received', 'rejected', 'approved')
             ORDER BY period_year DESC, period_month DESC
             LIMIT 1",
            $affiliate_id
        ));
    }

    /**
     * Retorna TODOS os fechamentos pendentes de um afiliado (sem LIMIT).
     * Ordenados por período ASC para incentivar envio cronológico.
     *
     * @param int $affiliate_id
     * @return array
     */
    public static function get_all_pending_closings($affiliate_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lrp_closings 
             WHERE affiliate_id = %d AND status IN ('awaiting_invoice', 'awaiting_rpa', 'invoice_received', 'rejected', 'approved')
             ORDER BY period_year ASC, period_month ASC",
            $affiliate_id
        ));
    }

    /**
     * Executa fechamento mensal
     * 
     * @param int|null $month Mês do período (null = mês anterior)
     * @param int|null $year Ano do período (null = ano do mês anterior)
     * @param bool $force_reprocess Se true, reavalia fechamentos com status 'closed' usando regras atuais
     * @return array Resultado com contagem de processados e erros
     */
    public static function run_monthly_closing($month = null, $year = null, $force_reprocess = false) {
        global $wpdb;
        
        $table_affiliates = $wpdb->prefix . 'lrp_affiliates';
        $table_closings = $wpdb->prefix . 'lrp_closings';
        $table_commissions = $wpdb->prefix . 'lrp_commissions';
        $table_referrals = $wpdb->prefix . 'lrp_referrals';
        
        // Período: parâmetros ou mês anterior
        $period_month = $month !== null ? (int) $month : (int) date('n', strtotime('-1 month'));
        $period_year = $year !== null ? (int) $year : (int) date('Y', strtotime('-1 month'));
        
        // Atualiza status de atividade de rede de todos os afiliados
        // Isso deve ser feito ANTES de processar os fechamentos
        if (class_exists('LRP_Activity_Calculator')) {
            $activity_result = LRP_Activity_Calculator::update_all_statuses();
            
            lrp_log('Status de atividade de rede atualizado no fechamento', [
                'period'  => sprintf('%02d/%d', $period_month, $period_year),
                'updated' => $activity_result['updated'],
                'errors'  => $activity_result['errors'],
            ]);
        }
        
        // Busca todos os afiliados ativos
        $affiliates = $wpdb->get_col(
            "SELECT id FROM $table_affiliates WHERE status = 'active'"
        );
        
        $settings = LRP_Settings::instance();
        $minimum_payout = $settings->get_minimum_payout();
        
        $processed = 0;
        $reprocessed = 0;
        $errors = 0;
        
        foreach ($affiliates as $affiliate_id) {
            $max_retries = 3;
            $retry_count = 0;
            $success = false;
            
            while ($retry_count < $max_retries && !$success) {
                try {
                    $wpdb->query('START TRANSACTION');
                    
                    // Verifica se já existe fechamento para este período
                    $existing = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, status, total_commissions FROM $table_closings 
                         WHERE affiliate_id = %d AND period_month = %d AND period_year = %d
                         FOR UPDATE",
                        $affiliate_id,
                        $period_month,
                        $period_year
                    ));
                    
                    if ($existing) {
                        // Se force_reprocess e status é 'closed', reavalia com regras atuais
                        if ($force_reprocess && $existing->status === 'closed') {
                            $affiliate_obj = new LRP_Affiliate($affiliate_id);
                            $is_rpa = $affiliate_obj->is_rpa();
                            
                            // Recalcula o total disponível
                            $existing_commissions = (float) $existing->total_commissions;
                            
                            // Busca saldo acumulado de períodos anteriores com status closed
                            $accumulated = (float) $wpdb->get_var($wpdb->prepare(
                                "SELECT COALESCE(SUM(total_commissions), 0) 
                                 FROM $table_closings 
                                 WHERE affiliate_id = %d 
                                 AND status = 'closed'
                                 AND id != %d
                                 AND (period_year < %d OR (period_year = %d AND period_month < %d))",
                                $affiliate_id,
                                $existing->id,
                                $period_year,
                                $period_year,
                                $period_month
                            ));
                            
                            $total_available = $existing_commissions + $accumulated;
                            
                            // Determina novo status com regras atuais
                            if ($is_rpa) {
                                $new_status = $total_available >= $minimum_payout ? 'awaiting_rpa' : 'closed';
                            } else {
                                $new_status = $total_available > 0 ? 'awaiting_invoice' : 'closed';
                            }
                            
                            // Se o status mudou, atualiza
                            if ($new_status !== 'closed') {
                                $wpdb->update(
                                    $table_closings,
                                    [
                                        'status'     => $new_status,
                                        'updated_at' => current_time('mysql'),
                                    ],
                                    ['id' => $existing->id]
                                );
                                
                                $wpdb->query('COMMIT');
                                $success = true;
                                $reprocessed++;
                                
                                // Notifica afiliado
                                do_action('lrp_closing_ready', $affiliate_obj, $existing->id, $total_available);

                                // Notifica financeiro sobre RPA pendente (v1.7.1)
                                if ($new_status === 'awaiting_rpa') {
                                    do_action('lrp_rpa_ready', $affiliate_obj, $existing->id, $total_available);
                                }
                                
                                lrp_log('Fechamento reprocessado', [
                                    'closing_id'   => $existing->id,
                                    'affiliate_id' => $affiliate_id,
                                    'old_status'   => 'closed',
                                    'new_status'   => $new_status,
                                    'total'        => $total_available,
                                ]);
                                
                                continue;
                            }
                        }
                        
                        $wpdb->query('COMMIT');
                        $success = true;
                        continue;
                    }
                    
                    // Calcula comissões do período
                    $start_date = date('Y-m-01', mktime(0, 0, 0, $period_month, 1, $period_year));
                    $end_date = date('Y-m-t', mktime(0, 0, 0, $period_month, 1, $period_year));
                    
                    $period_data = $wpdb->get_row($wpdb->prepare(
                        "SELECT 
                            COUNT(DISTINCT r.id) as total_sales,
                            COALESCE(SUM(r.commission_base), 0) as total_revenue,
                            COALESCE(SUM(c.commission_amount), 0) as total_commissions
                         FROM $table_commissions c
                         JOIN $table_referrals r ON c.referral_id = r.id
                         WHERE c.affiliate_id = %d 
                         AND c.status = 'approved'
                         AND c.closing_id IS NULL
                         AND c.created_at BETWEEN %s AND %s",
                        $affiliate_id,
                        $start_date . ' 00:00:00',
                        $end_date . ' 23:59:59'
                    ));
                    
                    // Busca saldo acumulado
                    $accumulated = $wpdb->get_var($wpdb->prepare(
                        "SELECT COALESCE(SUM(total_commissions), 0) 
                         FROM $table_closings 
                         WHERE affiliate_id = %d 
                         AND status IN ('closed', 'awaiting_invoice', 'awaiting_rpa', 'invoice_received', 'approved', 'rejected')
                         AND (period_year < %d OR (period_year = %d AND period_month < %d))",
                        $affiliate_id,
                        $period_year,
                        $period_year,
                        $period_month
                    ));
                    
                    // Soma ajustes pendentes (v1.4.0)
                    $pending_adjustments = 0.0;
                    if (class_exists('LRP_Adjustment')) {
                        $pending_adjustments = LRP_Adjustment::get_pending_sum($affiliate_id);
                    }
                    
                    $total_available = (float) $period_data->total_commissions + (float) $accumulated + $pending_adjustments;
                    
                    // Determina status baseado no tipo de faturamento
                    // PJ: qualquer valor > 0 pode ser pago (sem mínimo, afiliado emite NF)
                    // RPA: mínimo de R$200 para pagamento (empresa emite RPA)
                    $affiliate_obj = new LRP_Affiliate($affiliate_id);
                    $is_rpa = $affiliate_obj->is_rpa();
                    
                    if ($is_rpa) {
                        $status = $total_available >= $minimum_payout ? 'awaiting_rpa' : 'closed';
                    } else {
                        $status = $total_available > 0 ? 'awaiting_invoice' : 'closed';
                    }
                    
                    // Cria fechamento
                    $wpdb->insert($table_closings, [
                        'affiliate_id'     => $affiliate_id,
                        'period_month'     => $period_month,
                        'period_year'      => $period_year,
                        'total_sales'      => (int) $period_data->total_sales,
                        'total_revenue'    => (float) $period_data->total_revenue,
                        'total_commissions'=> (float) $period_data->total_commissions,
                        'status'           => $status,
                        'created_at'       => current_time('mysql'),
                        'closed_at'        => current_time('mysql'),
                    ]);
                    
                    $closing_id = $wpdb->insert_id;
                    
                    if ($closing_id) {
                        // Vincula comissões ao fechamento
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $table_commissions 
                             SET closing_id = %d 
                             WHERE affiliate_id = %d 
                             AND status = 'approved' 
                             AND closing_id IS NULL
                             AND created_at BETWEEN %s AND %s",
                            $closing_id,
                            $affiliate_id,
                            $start_date . ' 00:00:00',
                            $end_date . ' 23:59:59'
                        ));
                        
                        // Vincula ajustes pendentes ao fechamento (v1.4.0)
                        if (class_exists('LRP_Adjustment')) {
                            LRP_Adjustment::link_to_closing(
                                $affiliate_id, 
                                $closing_id, 
                                $end_date . ' 23:59:59'
                            );
                        }
                        
                        $wpdb->query('COMMIT');
                        $success = true;
                        $processed++;
                        
                        // Se fechamento pronto para pagamento, notifica
                        if (in_array($status, ['awaiting_invoice', 'awaiting_rpa'])) {
                            if (!isset($affiliate_obj) || $affiliate_obj->get_id() !== (int) $affiliate_id) {
                                $affiliate_obj = new LRP_Affiliate($affiliate_id);
                            }
                            do_action('lrp_closing_ready', $affiliate_obj, $closing_id, $total_available);

                            // Notifica financeiro sobre RPA pendente (v1.7.1)
                            if ($status === 'awaiting_rpa') {
                                do_action('lrp_rpa_ready', $affiliate_obj, $closing_id, $total_available);
                            }
                        }
                    } else {
                        $wpdb->query('ROLLBACK');
                        throw new Exception('Falha ao inserir fechamento');
                    }
                } catch (Exception $e) {
                    $wpdb->query('ROLLBACK');
                    $retry_count++;
                    
                    if ($retry_count >= $max_retries) {
                        $errors++;
                        lrp_log('Erro no fechamento após retries', [
                            'affiliate_id' => $affiliate_id,
                            'error'        => $e->getMessage(),
                        ], 'error');
                    }
                    
                    // Exponential backoff
                    usleep(100000 * pow(2, $retry_count - 1));
                }
            }
        }
        
        lrp_log('Fechamento mensal executado', [
            'period'       => sprintf('%02d/%d', $period_month, $period_year),
            'processed'    => $processed,
            'reprocessed'  => $reprocessed,
            'errors'       => $errors,
        ]);
        
        return [
            'period_month' => $period_month,
            'period_year'  => $period_year,
            'processed'    => $processed,
            'reprocessed'  => $reprocessed,
            'errors'       => $errors,
            'total'        => count($affiliates),
        ];
    }

    /**
     * Processa upload de NF
     *
     * @param int $closing_id
     * @param array $file $_FILES
     * @param string $invoice_number
     * @return true|WP_Error
     */
    public static function upload_invoice($closing_id, $file, $invoice_number = '') {
        // Verifica nonce CSRF
        // Aceita tanto 'lrp_nonce' (AJAX público) quanto '_wpnonce' (formulário direto)
        $nonce_valid = false;
        if (isset($_POST['lrp_nonce']) && wp_verify_nonce($_POST['lrp_nonce'], 'lrp_upload_invoice')) {
            $nonce_valid = true;
        } elseif (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'lrp_upload_invoice')) {
            $nonce_valid = true;
        }
        
        if (!$nonce_valid) {
            return new WP_Error('invalid_nonce', __('Requisição inválida. Por favor, tente novamente.', 'lab-resumos-parceiros'));
        }
        
        $closing = self::get($closing_id);
        
        if (!$closing || $closing->status !== 'awaiting_invoice') {
            return new WP_Error('invalid_status', __('Não é possível enviar NF neste momento.', 'lab-resumos-parceiros'));
        }
        
        // Verifica se o usuário atual é o dono do fechamento
        $affiliate = new LRP_Affiliate($closing->affiliate_id);
        if ($affiliate->get_user_id() !== get_current_user_id() && !current_user_can('lrp_manage_affiliates')) {
            return new WP_Error('unauthorized', __('Você não tem permissão para enviar esta NF.', 'lab-resumos-parceiros'));
        }
        
        // Valida arquivo
        $allowed_types = ['application/pdf'];
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_file', __('Apenas arquivos PDF são aceitos.', 'lab-resumos-parceiros'));
        }
        
        // Valida tamanho (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            return new WP_Error('file_too_large', __('Arquivo muito grande. Máximo: 5MB.', 'lab-resumos-parceiros'));
        }
        
        // Verifica magic bytes do PDF
        $handle = fopen($file['tmp_name'], 'rb');
        $header = fread($handle, 4);
        fclose($handle);
        
        if ($header !== '%PDF') {
            return new WP_Error('invalid_file', __('Arquivo não é um PDF válido.', 'lab-resumos-parceiros'));
        }
        
        // Upload
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/lrp-invoices/' . date('Y/m');
        
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
            
            // .htaccess para segurança
            file_put_contents($target_dir . '/.htaccess', 'deny from all');
        }
        
        $filename = sanitize_file_name(sprintf(
            'nf-%d-%d-%d-%s.pdf',
            $closing->affiliate_id,
            $closing->period_month,
            $closing->period_year,
            wp_generate_password(8, false)
        ));
        
        $target_path = wp_normalize_path($target_dir . '/' . $filename);
        
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            return new WP_Error('upload_failed', __('Erro ao fazer upload.', 'lab-resumos-parceiros'));
        }
        
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'lrp_closings',
            [
                'invoice_file'        => str_replace($upload_dir['basedir'], '', $target_path),
                'invoice_number'      => sanitize_text_field($invoice_number),
                'invoice_uploaded_at' => current_time('mysql'),
                'status'              => 'invoice_received',
            ],
            ['id' => $closing_id]
        );
        
        lrp_log('NF enviada', [
            'closing_id'   => $closing_id,
            'affiliate_id' => $closing->affiliate_id,
        ]);
        
        // Notifica contador (protegido contra erros para não afetar o upload)
        try {
            $affiliate = new LRP_Affiliate($closing->affiliate_id);
            do_action('lrp_invoice_received', $affiliate, $closing_id);
        } catch (\Throwable $e) {
            lrp_log('Erro ao notificar contador sobre NF', [
                'closing_id' => $closing_id,
                'error'      => $e->getMessage(),
            ], 'error');
        }
        
        return true;
    }

    /**
     * Aprova NF
     *
     * @param int $closing_id
     * @param int $approver_id
     * @return bool|WP_Error
     */
    public static function approve_invoice($closing_id, $approver_id) {
        // Verifica permissão do usuário
        if (!current_user_can('lrp_manage_invoices')) {
            return new WP_Error('unauthorized', __('Permissão negada. Apenas contadores podem aprovar NFs.', 'lab-resumos-parceiros'));
        }
        
        // Verifica se o approver_id é válido e tem a capability necessária
        $approver = get_userdata($approver_id);
        if (!$approver || !user_can($approver_id, 'lrp_manage_invoices')) {
            return new WP_Error('invalid_approver', __('Aprovador inválido ou sem permissão.', 'lab-resumos-parceiros'));
        }
        
        $closing = self::get($closing_id);
        
        if (!$closing || $closing->status !== 'invoice_received') {
            return new WP_Error('invalid_status', __('Não é possível aprovar NF neste momento.', 'lab-resumos-parceiros'));
        }
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_closings',
            ['status' => 'approved'],
            ['id' => $closing_id]
        );
        
        if ($result !== false) {
            $affiliate = new LRP_Affiliate($closing->affiliate_id);
            do_action('lrp_invoice_approved', $affiliate, $closing_id);
            
            lrp_log('NF aprovada', [
                'closing_id'  => $closing_id,
                'approver_id' => $approver_id,
            ]);
        }
        
        return $result !== false;
    }

    /**
     * Aprova RPA (v1.7.1) — transiciona awaiting_rpa → approved
     *
     * @param int $closing_id
     * @param int $approver_id
     * @return bool|WP_Error
     */
    public static function approve_rpa($closing_id, $approver_id) {
        if (!current_user_can('lrp_manage_invoices')) {
            return new WP_Error('unauthorized', __('Permissão negada.', 'lab-resumos-parceiros'));
        }

        $approver = get_userdata($approver_id);
        if (!$approver || !user_can($approver_id, 'lrp_manage_invoices')) {
            return new WP_Error('invalid_approver', __('Aprovador inválido ou sem permissão.', 'lab-resumos-parceiros'));
        }

        $closing = self::get($closing_id);

        if (!$closing || $closing->status !== 'awaiting_rpa') {
            return new WP_Error('invalid_status', __('Não é possível aprovar RPA neste momento.', 'lab-resumos-parceiros'));
        }

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_closings',
            ['status' => 'approved'],
            ['id' => $closing_id]
        );

        if ($result !== false) {
            $affiliate = new LRP_Affiliate($closing->affiliate_id);
            do_action('lrp_rpa_approved', $affiliate, $closing_id);

            lrp_log('RPA aprovado', [
                'closing_id'  => $closing_id,
                'approver_id' => $approver_id,
            ]);
        }

        return $result !== false;
    }

    /**
     * Rejeita NF
     *
     * @param int $closing_id
     * @param string $reason
     * @param int $rejector_id
     * @return bool|WP_Error
     */
    public static function reject_invoice($closing_id, $reason, $rejector_id) {
        // Verifica permissão do usuário
        if (!current_user_can('lrp_manage_invoices')) {
            return new WP_Error('unauthorized', __('Permissão negada. Apenas contadores podem rejeitar NFs.', 'lab-resumos-parceiros'));
        }
        
        // Verifica se o fechamento existe e está no status correto
        $closing = self::get($closing_id);
        if (!$closing || $closing->status !== 'invoice_received') {
            return new WP_Error('invalid_status', __('Não é possível rejeitar NF neste momento.', 'lab-resumos-parceiros'));
        }
        
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_closings',
            [
                'status'            => 'awaiting_invoice',
                'rejection_reason'  => sanitize_textarea_field($reason),
                'rejected_at'       => current_time('mysql'),
                'rejected_by'       => $rejector_id,
                'invoice_file'      => null,
                'invoice_number'    => null,
                'invoice_uploaded_at' => null,
            ],
            ['id' => $closing_id]
        );
        
        if ($result !== false) {
            $affiliate = new LRP_Affiliate($closing->affiliate_id);
            do_action('lrp_invoice_rejected', $affiliate, $closing_id, $reason);
            
            lrp_log('NF rejeitada', [
                'closing_id'  => $closing_id,
                'rejector_id' => $rejector_id,
                'reason'      => $reason,
            ]);
        }
        
        return $result !== false;
    }

    /**
     * Confirma pagamento
     *
     * @param int $closing_id
     * @param array $proof_file
     * @param int $payer_id
     * @param string $notes
     * @return true|WP_Error
     */
    public static function confirm_payment($closing_id, $proof_file, $payer_id, $notes = '') {
        // Verifica permissão do usuário
        if (!current_user_can('lrp_manage_payments')) {
            return new WP_Error('unauthorized', __('Permissão negada. Apenas contadores podem confirmar pagamentos.', 'lab-resumos-parceiros'));
        }
        
        // Verifica nonce CSRF
        // IMPORTANTE: Sempre use isset() || wp_verify_nonce() ao invés de ?? wp_verify_nonce()
        // O operador ?? pode permitir bypass de CSRF se o campo estiver ausente
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'lrp_confirm_payment_' . $closing_id)) {
            return new WP_Error('invalid_nonce', __('Requisição inválida. Por favor, tente novamente.', 'lab-resumos-parceiros'));
        }
        
        $closing = self::get($closing_id);
        
        if (!$closing || $closing->status !== 'approved') {
            return new WP_Error('invalid_status', __('Não é possível confirmar pagamento neste momento.', 'lab-resumos-parceiros'));
        }
        
        // Valida arquivo
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($proof_file['type'], $allowed_types)) {
            return new WP_Error('invalid_file', __('Tipo de arquivo não permitido.', 'lab-resumos-parceiros'));
        }
        
        if ($proof_file['size'] > 5 * 1024 * 1024) {
            return new WP_Error('file_too_large', __('Arquivo muito grande. Máximo: 5MB.', 'lab-resumos-parceiros'));
        }
        
        // Upload
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/lrp-payments/' . date('Y/m');
        
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
            file_put_contents($target_dir . '/.htaccess', 'deny from all');
        }
        
        $ext = strtolower(pathinfo($proof_file['name'], PATHINFO_EXTENSION));
        $filename = sanitize_file_name(sprintf('comprovante-%d-%s.%s', $closing_id, wp_generate_password(8, false), $ext));
        $target_path = wp_normalize_path($target_dir . '/' . $filename);
        
        if (!move_uploaded_file($proof_file['tmp_name'], $target_path)) {
            return new WP_Error('upload_failed', __('Erro ao fazer upload.', 'lab-resumos-parceiros'));
        }
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_closings',
            [
                'status'              => 'paid',
                'payment_proof_file'  => str_replace($upload_dir['basedir'], '', $target_path),
                'paid_at'             => current_time('mysql'),
                'paid_by'             => $payer_id,
                'payment_notes'       => sanitize_textarea_field($notes),
            ],
            ['id' => $closing_id]
        );
        
        if ($result !== false) {
            // Atualiza comissões para paid
            $wpdb->update(
                $wpdb->prefix . 'lrp_commissions',
                ['status' => 'paid'],
                ['closing_id' => $closing_id]
            );
            
            // Marca ajustes como pagos (v1.4.0)
            if (class_exists('LRP_Adjustment')) {
                LRP_Adjustment::mark_as_paid($closing_id);
            }
            
            // Atualiza stats do afiliado
            $affiliate = new LRP_Affiliate($closing->affiliate_id);
            $affiliate->refresh_stats();
            
            do_action('lrp_payment_completed', $affiliate, $closing_id);
            
            lrp_log('Pagamento confirmado', [
                'closing_id' => $closing_id,
                'payer_id'   => $payer_id,
                'amount'     => $closing->total_commissions,
            ]);
        }
        
        return $result !== false ? true : new WP_Error('db_error', __('Erro ao atualizar.', 'lab-resumos-parceiros'));
    }

    /**
     * Retorna URL da NF
     *
     * @param object $closing
     * @return string|null
     */
    public static function get_invoice_url($closing) {
        if (empty($closing->invoice_file)) {
            return null;
        }
        
        // Retorna URL segura via AJAX (arquivos protegidos por .htaccess)
        return self::get_secure_file_url($closing->id, 'invoice');
    }

    /**
     * Retorna URL do comprovante
     *
     * @param object $closing
     * @return string|null
     */
    public static function get_payment_proof_url($closing) {
        if (empty($closing->payment_proof_file)) {
            return null;
        }
        
        // Retorna URL segura via AJAX (arquivos protegidos por .htaccess)
        return self::get_secure_file_url($closing->id, 'proof');
    }

    /**
     * Gera URL segura para download de arquivo via AJAX
     * 
     * Os arquivos de NF e comprovantes ficam em pastas protegidas por .htaccess.
     * Este método gera uma URL que passa pelo admin-ajax.php com verificação de permissão.
     *
     * @param int $closing_id
     * @param string $type 'invoice' ou 'proof'
     * @return string
     */
    public static function get_secure_file_url($closing_id, $type) {
        return wp_nonce_url(
            add_query_arg([
                'action'     => 'lrp_download_file',
                'type'       => $type,
                'closing_id' => $closing_id,
            ], admin_url('admin-ajax.php')),
            'lrp_admin_nonce',
            'nonce'
        );
    }
}

