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
     * Status que representam valor DEVIDO ao parceiro (ainda não pago).
     *
     * Fonte de verdade única: tela de Conciliação, CSV, soma do dashboard e
     * `sum_pending_amount()` leem daqui. Antes disso, a tela de Pagamentos
     * mostrava só `approved` enquanto a soma considerava 4 status - dois
     * números diferentes para a mesma pergunta.
     *
     * @since 1.8.0
     * @var array
     */
    const PENDING_STATUSES = [
        'awaiting_invoice',
        'awaiting_rpa',
        'invoice_received',
        'rejected',
        'approved',
    ];

    /**
     * Métodos aceitos na baixa manual (pagamento feito fora do sistema).
     *
     * @since 1.8.0
     * @var array
     */
    const SETTLEMENT_METHODS = [
        'rpa'          => 'RPA',
        'pix'          => 'PIX',
        'transferencia'=> 'Transferência bancária',
        'nf_externa'   => 'NF paga fora do sistema',
        'outro'        => 'Outro',
    ];

    /**
     * Lista de status pendentes pronta para SQL (`'a','b',...`).
     *
     * @since 1.8.0
     * @return string
     */
    public static function pending_statuses_sql() {
        return "'" . implode("','", self::PENDING_STATUSES) . "'";
    }

    /**
     * Rótulo do método de baixa manual.
     *
     * @since 1.8.0
     * @param string $method
     * @return string
     */
    public static function get_settlement_method_label($method) {
        return self::SETTLEMENT_METHODS[$method] ?? ($method ?: '-');
    }

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
        
        $statuses = self::pending_statuses_sql();
        
        // Soma comissões dos fechamentos pendentes
        $commissions_sum = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_commissions + COALESCE(adjustment_amount, 0)), 0) 
             FROM {$wpdb->prefix}lrp_closings 
             WHERE status IN ($statuses)"
        );
        
        // Soma ajustes do novo sistema vinculados a fechamentos pendentes (v1.4.0)
        $adjustments_sum = 0.0;
        if (class_exists('LRP_Adjustment')) {
            $closing_ids = $wpdb->get_col(
                "SELECT id FROM {$wpdb->prefix}lrp_closings 
                 WHERE status IN ($statuses)"
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
     * Baixa manual: registra pagamento feito FORA do sistema.
     *
     * Existe porque a máquina de estados não tinha porta de saída lateral.
     * Caso real: parceira cadastrada como PJ/NF foi paga via RPA por fora, e o
     * fechamento ficou preso em `awaiting_invoice` esperando uma NF que nunca
     * viria - contando como devido para sempre.
     *
     * Aceita qualquer status pendente e leva a `paid` percorrendo exatamente o
     * mesmo caminho de `confirm_payment()` (comissões, ajustes, stats, hook),
     * que é o que evita saldo fantasma.
     *
     * Comprovante é OPCIONAL aqui de propósito: pagamento feito por fora
     * frequentemente não tem PDF, e exigir arquivo é o que faria a pessoa
     * desistir e deixar o registro sujo.
     *
     * @since 1.8.0
     *
     * @param int   $closing_id
     * @param array $args {
     *     @type string $method      Um de self::SETTLEMENT_METHODS (obrigatório).
     *     @type string $date        Data real do pagamento, Y-m-d (obrigatório, pode ser retroativa).
     *     @type string $reason      Motivo/justificativa (obrigatório).
     *     @type array  $proof_file  $_FILES['...'] (opcional).
     *     @type int    $user_id     Quem registrou (default: usuário atual).
     * }
     * @return true|WP_Error
     */
    public static function manual_settle($closing_id, $args = []) {
        global $wpdb;

        if (!current_user_can('lrp_manage_payments')) {
            return new WP_Error('unauthorized', __('Permissão negada. Apenas o financeiro pode registrar pagamento externo.', 'lab-resumos-parceiros'));
        }

        $closing_id = (int) $closing_id;
        $closing    = self::get($closing_id);

        if (!$closing) {
            return new WP_Error('not_found', __('Fechamento não encontrado.', 'lab-resumos-parceiros'));
        }

        if ($closing->status === 'paid') {
            return new WP_Error('already_paid', __('Este fechamento já está pago.', 'lab-resumos-parceiros'));
        }

        if (!in_array($closing->status, self::PENDING_STATUSES, true)) {
            return new WP_Error('invalid_status', sprintf(
                /* translators: %s: status atual do fechamento */
                __('Não é possível dar baixa manual em um fechamento com status "%s".', 'lab-resumos-parceiros'),
                $closing->status
            ));
        }

        $method = sanitize_key($args['method'] ?? '');
        if (!isset(self::SETTLEMENT_METHODS[$method])) {
            return new WP_Error('invalid_method', __('Selecione como o pagamento foi feito.', 'lab-resumos-parceiros'));
        }

        $reason = trim(sanitize_textarea_field($args['reason'] ?? ''));
        if ($reason === '') {
            return new WP_Error('missing_reason', __('O motivo da baixa manual é obrigatório.', 'lab-resumos-parceiros'));
        }

        $date_raw = trim((string) ($args['date'] ?? ''));
        if ($date_raw === '') {
            return new WP_Error('missing_date', __('Informe a data real do pagamento.', 'lab-resumos-parceiros'));
        }

        $date_ts = strtotime($date_raw);
        if (!$date_ts) {
            return new WP_Error('invalid_date', __('Data de pagamento inválida.', 'lab-resumos-parceiros'));
        }

        // Data futura não faz sentido para pagamento já realizado.
        if ($date_ts > current_time('timestamp') + DAY_IN_SECONDS) {
            return new WP_Error('future_date', __('A data do pagamento não pode ser no futuro.', 'lab-resumos-parceiros'));
        }

        $settlement_date = date('Y-m-d', $date_ts);
        $user_id         = (int) ($args['user_id'] ?: get_current_user_id());

        // Comprovante opcional: reaproveita a mesma pasta protegida do fluxo normal.
        $proof_relative = null;
        if (!empty($args['proof_file']) && !empty($args['proof_file']['tmp_name'])) {
            $proof_relative = self::store_payment_proof($args['proof_file'], $closing_id);

            if (is_wp_error($proof_relative)) {
                return $proof_relative;
            }
        }

        $final_amount = self::get_final_amount($closing);

        $update = [
            'status'             => 'paid',
            'paid_at'            => $settlement_date . ' ' . date('H:i:s', current_time('timestamp')),
            'paid_by'            => $user_id,
            'payment_notes'      => $reason,
            'settled_manually'   => 1,
            'settlement_method'  => $method,
            'settlement_reason'  => $reason,
            'settlement_date'    => $settlement_date,
            'settled_by'         => $user_id,
            'settled_at'         => current_time('mysql'),
        ];

        if ($proof_relative) {
            $update['payment_proof_file'] = $proof_relative;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_closings',
            $update,
            ['id' => $closing_id]
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao registrar a baixa manual.', 'lab-resumos-parceiros'));
        }

        // Daqui para baixo é exatamente o que confirm_payment() faz.
        $wpdb->update(
            $wpdb->prefix . 'lrp_commissions',
            ['status' => 'paid'],
            ['closing_id' => $closing_id]
        );

        if (class_exists('LRP_Adjustment')) {
            LRP_Adjustment::mark_as_paid($closing_id);
        }

        $affiliate = new LRP_Affiliate($closing->affiliate_id);
        $affiliate->refresh_stats();

        do_action('lrp_payment_completed', $affiliate, $closing_id);
        do_action('lrp_closing_manually_settled', $closing_id, $affiliate, $method, $final_amount);

        lrp_log('Baixa manual registrada', [
            'closing_id'      => $closing_id,
            'affiliate_id'    => $closing->affiliate_id,
            'previous_status' => $closing->status,
            'method'          => $method,
            'settlement_date' => $settlement_date,
            'amount'          => $final_amount,
            'reason'          => $reason,
            'has_proof'       => (bool) $proof_relative,
            'by'              => $user_id,
        ]);

        return true;
    }

    /**
     * Salva comprovante de pagamento na pasta protegida.
     *
     * @since 1.8.0
     * @param array $file $_FILES entry
     * @param int   $closing_id
     * @return string|WP_Error Caminho relativo ao uploads basedir.
     */
    private static function store_payment_proof($file, $closing_id) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];

        if (!in_array($file['type'] ?? '', $allowed_types, true)) {
            return new WP_Error('invalid_file', __('Tipo de arquivo não permitido. Envie PDF, JPG ou PNG.', 'lab-resumos-parceiros'));
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return new WP_Error('file_too_large', __('Arquivo muito grande. Máximo: 5MB.', 'lab-resumos-parceiros'));
        }

        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/lrp-payments/' . date('Y/m');

        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
            file_put_contents($target_dir . '/.htaccess', 'deny from all');
        }

        $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename    = sanitize_file_name(sprintf('baixa-manual-%d-%s.%s', $closing_id, wp_generate_password(8, false), $ext));
        $target_path = wp_normalize_path($target_dir . '/' . $filename);

        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            return new WP_Error('upload_failed', __('Erro ao fazer upload do comprovante.', 'lab-resumos-parceiros'));
        }

        return str_replace($upload_dir['basedir'], '', $target_path);
    }

    /**
     * Fonte de verdade única de "tudo o que é devido" (conciliação).
     *
     * Devolve TODO fechamento em qualquer status pendente, com comissões +
     * ajustes já consolidados e dias parado. Tela, CSV e cards leem daqui.
     *
     * @since 1.8.0
     *
     * @param array $filters {
     *     @type int    $affiliate_id  Filtra por parceiro.
     *     @type string $status        Filtra por um status pendente específico.
     *     @type string $billing_type  'pj' ou 'rpa'.
     *     @type string $period_from   'YYYY-MM'.
     *     @type string $period_to     'YYYY-MM'.
     * }
     * @return array Lista de objetos com final_amount, adjustments_sum, days_pending.
     */
    public static function get_receivables($filters = []) {
        global $wpdb;

        $statuses = self::pending_statuses_sql();
        $where    = ["c.status IN ($statuses)"];
        $params   = [];

        if (!empty($filters['affiliate_id'])) {
            $where[]  = 'c.affiliate_id = %d';
            $params[] = (int) $filters['affiliate_id'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::PENDING_STATUSES, true)) {
            $where[]  = 'c.status = %s';
            $params[] = $filters['status'];
        }

        if (!empty($filters['billing_type']) && in_array($filters['billing_type'], ['pj', 'rpa'], true)) {
            $where[]  = 'a.billing_type = %s';
            $params[] = $filters['billing_type'];
        }

        // Período no formato YYYY-MM, comparado como ano*100+mês.
        if (!empty($filters['period_from']) && preg_match('/^(\d{4})-(\d{2})$/', $filters['period_from'], $m)) {
            $where[]  = '(c.period_year * 100 + c.period_month) >= %d';
            $params[] = (int) $m[1] * 100 + (int) $m[2];
        }

        if (!empty($filters['period_to']) && preg_match('/^(\d{4})-(\d{2})$/', $filters['period_to'], $m)) {
            $where[]  = '(c.period_year * 100 + c.period_month) <= %d';
            $params[] = (int) $m[1] * 100 + (int) $m[2];
        }

        $sql = "SELECT c.*,
                       a.billing_type,
                       a.holder_name,
                       a.holder_document,
                       a.user_id,
                       u.display_name AS affiliate_name,
                       u.user_email AS affiliate_email
                FROM {$wpdb->prefix}lrp_closings c
                JOIN {$wpdb->prefix}lrp_affiliates a ON c.affiliate_id = a.id
                JOIN {$wpdb->users} u ON a.user_id = u.ID
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.period_year ASC, c.period_month ASC, u.display_name ASC";

        $rows = empty($params)
            ? $wpdb->get_results($sql)
            : $wpdb->get_results($wpdb->prepare($sql, ...$params));

        $now = current_time('timestamp');

        foreach ($rows as $row) {
            $adjustments_sum = 0.0;
            if (class_exists('LRP_Adjustment')) {
                $adjustments_sum = LRP_Adjustment::get_closing_sum($row->id);
            }

            $row->adjustments_sum = $adjustments_sum + (float) ($row->adjustment_amount ?? 0);
            $row->final_amount    = self::get_final_amount($row);

            $reference = $row->closed_at ?: $row->created_at;
            $row->days_pending = $reference
                ? max(0, (int) floor(($now - strtotime($reference)) / DAY_IN_SECONDS))
                : 0;
        }

        return $rows;
    }

    /**
     * Totais de conciliação por status + total geral.
     *
     * Calculado a partir de get_receivables() para garantir que o card do topo
     * e a tabela nunca divirjam.
     *
     * @since 1.8.0
     * @param array $filters Mesmos filtros de get_receivables().
     * @return array
     */
    public static function get_receivables_totals($filters = []) {
        $rows = self::get_receivables($filters);

        $totals = [
            'count'       => 0,
            'total'       => 0.0,
            'by_status'   => [],
        ];

        foreach (self::PENDING_STATUSES as $status) {
            $totals['by_status'][$status] = ['count' => 0, 'amount' => 0.0];
        }

        foreach ($rows as $row) {
            $totals['count']++;
            $totals['total'] += (float) $row->final_amount;

            if (!isset($totals['by_status'][$row->status])) {
                $totals['by_status'][$row->status] = ['count' => 0, 'amount' => 0.0];
            }

            $totals['by_status'][$row->status]['count']++;
            $totals['by_status'][$row->status]['amount'] += (float) $row->final_amount;
        }

        return $totals;
    }

    /**
     * Rótulo legível de status de fechamento (usado em tela e CSV).
     *
     * @since 1.8.0
     * @param string $status
     * @return string
     */
    public static function get_status_label($status) {
        $labels = [
            'open'             => __('Em andamento', 'lab-resumos-parceiros'),
            'closed'           => __('Fechado (abaixo do mínimo)', 'lab-resumos-parceiros'),
            'awaiting_invoice' => __('Aguardando NF do parceiro', 'lab-resumos-parceiros'),
            'awaiting_rpa'     => __('Aguardando emissão de RPA', 'lab-resumos-parceiros'),
            'invoice_received' => __('NF em análise', 'lab-resumos-parceiros'),
            'rejected'         => __('NF rejeitada (reenvio pendente)', 'lab-resumos-parceiros'),
            'approved'         => __('Aprovado - a pagar', 'lab-resumos-parceiros'),
            'paid'             => __('Pago', 'lab-resumos-parceiros'),
        ];

        return $labels[$status] ?? $status;
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

