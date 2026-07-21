# Programa de Parceiros Lab Resumos - Parte 5: Fechamento, Emails e WooCommerce

## 1. Fechamento Mensal

### 1.1 Fluxo de Status do Fechamento

```
┌─────────────────────────────────────────────────────────────────────┐
│                    CICLO DE VIDA DO FECHAMENTO                       │
└─────────────────────────────────────────────────────────────────────┘

    ┌─────────┐
    │  open   │ ← Mês em andamento (comissões sendo acumuladas)
    └────┬────┘
         │ (Dia 1 do próximo mês - CRON)
         ▼
    ┌─────────┐
    │ closed  │ ← Fechado, calculando totais
    └────┬────┘
         │ (Se total >= mínimo)
         ▼
┌─────────────────┐
│awaiting_invoice │ ← Aguardando afiliado enviar NF
└────────┬────────┘
         │ (Afiliado faz upload)
         ▼
┌─────────────────┐
│invoice_received │ ← NF recebida, aguardando validação
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
    ▼         ▼
┌────────┐ ┌──────────┐
│approved│ │ rejected │ ← Contador valida
└───┬────┘ └────┬─────┘
    │           │ (Afiliado corrige e reenvia)
    │           └──────────────────────────────┐
    │                                          │
    │ (Contador faz PIX e sobe comprovante)    │
    ▼                                          │
┌────────┐                                     │
│  paid  │ ← Pagamento confirmado              │
└────────┘                                     │
                                               │
    ┌──────────────────────────────────────────┘
    ▼
┌─────────────────┐
│awaiting_invoice │ ← Volta para aguardar nova NF
└─────────────────┘
```

### 1.2 Classe de Fechamento

```php
<?php
class LRP_Closing {

    /**
     * Executa fechamento mensal (chamado pelo CRON no dia 1)
     */
    public static function run_monthly_closing() {
        global $wpdb;
        
        $table_affiliates = $wpdb->prefix . 'lrp_affiliates';
        $table_closings = $wpdb->prefix . 'lrp_closings';
        $table_commissions = $wpdb->prefix . 'lrp_commissions';
        
        // Mês anterior
        $period_month = (int) date('n', strtotime('-1 month'));
        $period_year = (int) date('Y', strtotime('-1 month'));
        
        // Busca todos os afiliados ativos
        $affiliates = $wpdb->get_col(
            "SELECT id FROM $table_affiliates WHERE status = 'active'"
        );
        
        $settings = LRP_Settings::instance();
        $minimum_payout = $settings->get('minimum_payout', 200);
        
        foreach ($affiliates as $affiliate_id) {
            // Retry logic com exponential backoff para evitar deadlocks
            $max_retries = 3;
            $retry_count = 0;
            $success = false;
            
            while ($retry_count < $max_retries && !$success) {
                try {
                    // Inicia transação para evitar race condition
                    $wpdb->query('START TRANSACTION');
                    
                    // Verifica se já existe fechamento para este período (com lock)
                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $table_closings 
                         WHERE affiliate_id = %d AND period_month = %d AND period_year = %d
                         FOR UPDATE",
                        $affiliate_id, $period_month, $period_year
                    ));
                    
                    if ($existing) {
                        $wpdb->query('COMMIT');
                        $success = true;
                        continue; // Já processado, passa para próximo afiliado
                    }
                    
                    // Calcula comissões do período
                    $start_date = date('Y-m-01', strtotime('-1 month'));
                    $end_date = date('Y-m-t', strtotime('-1 month'));
                    
                    $period_data = $wpdb->get_row($wpdb->prepare(
                        "SELECT 
                            COUNT(DISTINCT r.id) as total_sales,
                            COALESCE(SUM(r.commission_base), 0) as total_revenue,
                            COALESCE(SUM(c.commission_amount), 0) as total_commissions
                         FROM $table_commissions c
                         JOIN {$wpdb->prefix}lrp_referrals r ON c.referral_id = r.id
                         WHERE c.affiliate_id = %d 
                         AND c.status = 'approved'
                         AND c.closing_id IS NULL
                         AND c.created_at BETWEEN %s AND %s",
                        $affiliate_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59'
                    ));
                    
                    // Busca saldo acumulado de fechamentos anteriores não pagos
                    // Parênteses são necessários para garantir precedência correta do OR
                    $accumulated = $wpdb->get_var($wpdb->prepare(
                        "SELECT COALESCE(SUM(total_commissions), 0) 
                         FROM $table_closings 
                         WHERE affiliate_id = %d 
                         AND status IN ('closed', 'awaiting_invoice', 'invoice_received', 'approved', 'rejected')
                         AND (period_year < %d OR (period_year = %d AND period_month < %d))",
                        $affiliate_id, $period_year, $period_year, $period_month
                    ));
                    
                    $total_available = $period_data->total_commissions + $accumulated;
                    
                    // Cria fechamento
                    $closing_id = $wpdb->insert($table_closings, [
                        'affiliate_id' => $affiliate_id,
                        'period_month' => $period_month,
                        'period_year' => $period_year,
                        'total_sales' => $period_data->total_sales,
                        'total_revenue' => $period_data->total_revenue,
                        'total_commissions' => $period_data->total_commissions,
                        'status' => $total_available >= $minimum_payout ? 'awaiting_invoice' : 'closed',
                        'closed_at' => current_time('mysql'),
                    ]);
                    
                    if ($closing_id) {
                        // Vincula comissões ao fechamento
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $table_commissions 
                             SET closing_id = %d 
                             WHERE affiliate_id = %d 
                             AND status = 'approved' 
                             AND closing_id IS NULL
                             AND created_at BETWEEN %s AND %s",
                            $wpdb->insert_id, $affiliate_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59'
                        ));
                        
                        // Commit da transação
                        $wpdb->query('COMMIT');
                        $success = true;
                        
                        // Se atingiu mínimo, envia email (fora da transação)
                        if ($total_available >= $minimum_payout) {
                            $affiliate = new LRP_Affiliate($affiliate_id);
                            do_action('lrp_closing_ready', $affiliate, $wpdb->insert_id, $total_available);
                        }
                    } else {
                        // Rollback em caso de erro
                        $wpdb->query('ROLLBACK');
                        throw new Exception('Falha ao inserir fechamento');
                    }
                } catch (Exception $e) {
                    // Rollback em caso de exceção
                    $wpdb->query('ROLLBACK');
                    $retry_count++;
                    
                    if ($retry_count >= $max_retries) {
                        // Log erro após todas as tentativas
                        if (function_exists('wc_get_logger')) {
                            wc_get_logger()->error(
                                sprintf('Erro no fechamento mensal após %d tentativas: %s', $max_retries, $e->getMessage()),
                                ['source' => 'lab-resumos-parceiros', 'affiliate_id' => $affiliate_id]
                            );
                        }
                        break; // Passa para próximo afiliado
                    }
                    
                    // Exponential backoff: 100ms, 200ms, 400ms
                    usleep(100000 * pow(2, $retry_count - 1));
                }
            }
        }
        
        // Log
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->info(
                sprintf('Fechamento mensal executado: %d/%d', $period_month, $period_year),
                ['source' => 'lab-resumos-parceiros']
            );
        }
    }

    /**
     * Processa upload de NF
     */
    public function upload_invoice($closing_id, $file, $invoice_number = '') {
        $closing = self::get($closing_id);
        
        if (!$closing || $closing->status !== 'awaiting_invoice') {
            return new WP_Error('invalid_status', 'Não é possível enviar NF neste momento.');
        }
        
        // Verifica nonce CSRF
        // IMPORTANTE: Sempre use isset() || wp_verify_nonce() ao invés de ?? wp_verify_nonce()
        // O operador ?? pode permitir bypass de CSRF
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'lrp_upload_invoice_' . $closing_id)) {
            return new WP_Error('invalid_nonce', 'Requisição inválida. Por favor, tente novamente.');
        }
        
        // Valida arquivo
        // IMPORTANTE: Validação server-side é essencial - $file['type'] pode ser falsificado pelo cliente
        $allowed_types = ['application/pdf'];
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_file', 'Apenas arquivos PDF são aceitos.');
        }
        
        // Validação usando wp_check_filetype_and_ext() para verificação server-side
        $file_info = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], null);
        if (!$file_info['ext'] || !$file_info['type']) {
            return new WP_Error('invalid_file', 'Tipo de arquivo não permitido.');
        }
        
        // Verificar magic bytes para PDF (assinatura real do arquivo)
        if ($file['type'] === 'application/pdf') {
            $handle = fopen($file['tmp_name'], 'rb');
            $header = fread($handle, 4);
            fclose($handle);
            
            if ($header !== '%PDF') {
                return new WP_Error('invalid_file', 'Arquivo não é um PDF válido.');
            }
        }
        
        // Valida tamanho máximo (5MB)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', 'Arquivo muito grande. Tamanho máximo: 5MB.');
        }
        
        // Valida se é realmente PDF (verifica extensão e MIME type)
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'pdf') {
            return new WP_Error('invalid_extension', 'Apenas arquivos PDF são aceitos.');
        }
        
        // Verificar extensões perigosas (prevenção adicional)
        if (in_array($file_ext, ['php', 'phtml', 'php3', 'php4', 'php5'])) {
            return new WP_Error('invalid_file', 'Tipo de arquivo não permitido.');
        }
        
        // Faz upload
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/lrp-invoices/' . date('Y/m');
        
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        // Sanitiza nome do arquivo para prevenir path traversal
        $base_filename = sprintf(
            'nf-%d-%d-%d-%s.pdf',
            $closing->affiliate_id,
            $closing->period_month,
            $closing->period_year,
            wp_generate_password(8, false)
        );
        $filename = sanitize_file_name($base_filename);
        
        // Normaliza caminho para prevenir path traversal
        $target_path = wp_normalize_path($target_dir . '/' . $filename);
        
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            return new WP_Error('upload_failed', 'Erro ao fazer upload do arquivo.');
        }
        
        // Atualiza fechamento
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'lrp_closings',
            [
                'invoice_file' => str_replace($upload_dir['basedir'], '', $target_path),
                'invoice_number' => sanitize_text_field($invoice_number),
                'invoice_uploaded_at' => current_time('mysql'),
                'status' => 'invoice_received',
            ],
            ['id' => $closing_id]
        );
        
        // Notifica contador
        $affiliate = new LRP_Affiliate($closing->affiliate_id);
        do_action('lrp_invoice_received', $affiliate, $closing_id);
        
        return true;
    }

    /**
     * Aprova NF
     */
    public function approve_invoice($closing_id, $approver_id) {
        // Verifica permissão do usuário
        if (!current_user_can('lrp_manage_invoices')) {
            return new WP_Error('unauthorized', 'Permissão negada. Apenas contadores podem aprovar NFs.');
        }
        
        // Verifica se o approver_id é válido e tem a capability necessária
        $approver = get_userdata($approver_id);
        if (!$approver || !user_can($approver_id, 'lrp_manage_invoices')) {
            return new WP_Error('invalid_approver', 'Aprovador inválido ou sem permissão.');
        }
        
        // Verifica se o fechamento existe e está no status correto
        $closing = self::get($closing_id);
        if (!$closing || $closing->status !== 'invoice_received') {
            return new WP_Error('invalid_status', 'Não é possível aprovar NF neste momento.');
        }
        
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'lrp_closings',
            [
                'status' => 'approved',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $closing_id]
        );
    }

    /**
     * Rejeita NF
     */
    public function reject_invoice($closing_id, $reason, $rejector_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_closings',
            [
                'status' => 'awaiting_invoice', // Volta para aguardar nova NF
                'rejection_reason' => sanitize_textarea_field($reason),
                'rejected_at' => current_time('mysql'),
                'rejected_by' => $rejector_id,
                'invoice_file' => null,
                'invoice_number' => null,
                'invoice_uploaded_at' => null,
            ],
            ['id' => $closing_id]
        );
        
        if ($result) {
            $closing = self::get($closing_id);
            $affiliate = new LRP_Affiliate($closing->affiliate_id);
            do_action('lrp_invoice_rejected', $affiliate, $closing_id, $reason);
        }
        
        return $result;
    }

    /**
     * Confirma pagamento
     */
    public function confirm_payment($closing_id, $proof_file, $payer_id, $notes = '') {
        // Verifica permissão do usuário
        if (!current_user_can('lrp_manage_payments')) {
            return new WP_Error('unauthorized', 'Permissão negada. Apenas contadores podem confirmar pagamentos.');
        }
        
        // Verifica nonce CSRF
        // IMPORTANTE: Sempre use isset() || wp_verify_nonce() ao invés de ?? wp_verify_nonce()
        // O operador ?? pode permitir bypass de CSRF
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'lrp_confirm_payment_' . $closing_id)) {
            return new WP_Error('invalid_nonce', 'Requisição inválida. Por favor, tente novamente.');
        }
        
        // Verifica se o fechamento existe e está no status correto
        $closing = self::get($closing_id);
        if (!$closing || $closing->status !== 'approved') {
            return new WP_Error('invalid_status', 'Não é possível confirmar pagamento neste momento.');
        }
        
        // Valida arquivo - tipos permitidos: PDF, JPEG, PNG
        // IMPORTANTE: Validação server-side é essencial - $proof_file['type'] pode ser falsificado pelo cliente
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($proof_file['type'], $allowed_types)) {
            return new WP_Error('invalid_file', 'Apenas arquivos PDF, JPEG ou PNG são aceitos.');
        }
        
        // Validação usando wp_check_filetype_and_ext() para verificação server-side
        $file_info = wp_check_filetype_and_ext($proof_file['tmp_name'], $proof_file['name'], null);
        if (!$file_info['ext'] || !$file_info['type']) {
            return new WP_Error('invalid_file', 'Tipo de arquivo não permitido.');
        }
        
        // Verificar magic bytes para PDF
        if ($proof_file['type'] === 'application/pdf') {
            $handle = fopen($proof_file['tmp_name'], 'rb');
            $header = fread($handle, 4);
            fclose($handle);
            
            if ($header !== '%PDF') {
                return new WP_Error('invalid_file', 'Arquivo não é um PDF válido.');
            }
        }
        
        // Valida tamanho máximo (5MB)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($proof_file['size'] > $max_size) {
            return new WP_Error('file_too_large', 'Arquivo muito grande. Tamanho máximo: 5MB.');
        }
        
        // Valida extensão do arquivo
        $file_ext = strtolower(pathinfo($proof_file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_extensions)) {
            return new WP_Error('invalid_extension', 'Apenas arquivos PDF, JPEG ou PNG são aceitos.');
        }
        
        // Verificar extensões perigosas (prevenção adicional)
        if (in_array($file_ext, ['php', 'phtml', 'php3', 'php4', 'php5'])) {
            return new WP_Error('invalid_file', 'Tipo de arquivo não permitido.');
        }
        
        // Upload do comprovante
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/lrp-payments/' . date('Y/m');
        
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        // Sanitiza nome do arquivo para prevenir path traversal
        $base_filename = sprintf('comprovante-%d-%s.%s', $closing_id, wp_generate_password(8, false), $file_ext);
        $filename = sanitize_file_name($base_filename);
        
        // Normaliza caminho para prevenir path traversal
        $target_path = wp_normalize_path($target_dir . '/' . $filename);
        
        if (!move_uploaded_file($proof_file['tmp_name'], $target_path)) {
            return new WP_Error('upload_failed', 'Erro ao fazer upload do comprovante.');
        }
        
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_closings',
            [
                'status' => 'paid',
                'payment_proof_file' => str_replace($upload_dir['basedir'], '', $target_path),
                'paid_at' => current_time('mysql'),
                'paid_by' => $payer_id,
                'payment_notes' => sanitize_textarea_field($notes),
            ],
            ['id' => $closing_id]
        );
        
        if ($result) {
            // Atualiza estatísticas do afiliado
            $closing = self::get($closing_id);
            $affiliate = new LRP_Affiliate($closing->affiliate_id);
            $affiliate->refresh_stats();
            
            // Atualiza status das comissões para 'paid'
            $wpdb->update(
                $wpdb->prefix . 'lrp_commissions',
                ['status' => 'paid'],
                ['closing_id' => $closing_id]
            );
            
            // Notifica afiliado
            do_action('lrp_payment_completed', $affiliate, $closing_id);
        }
        
        return $result;
    }
}
```

---

## 2. Sistema de Emails

### 2.1 Gerenciador de Emails

```php
<?php
class LRP_Email_Manager {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hooks para disparar emails
        add_action('lrp_affiliate_approved', [$this, 'send_welcome_email']);
        add_action('lrp_new_sale', [$this, 'send_new_sale_email'], 10, 3);
        add_action('lrp_closing_ready', [$this, 'send_closing_email'], 10, 3);
        add_action('lrp_invoice_approved', [$this, 'send_invoice_approved_email'], 10, 2);
        add_action('lrp_invoice_rejected', [$this, 'send_invoice_rejected_email'], 10, 3);
        add_action('lrp_payment_completed', [$this, 'send_payment_email'], 10, 2);
        add_action('lrp_new_sub_affiliate', [$this, 'send_new_sub_affiliate_email'], 10, 2);
        add_action('lrp_sub_affiliate_sale', [$this, 'send_sub_affiliate_sale_email'], 10, 4);
        
        // Emails para admin
        add_action('lrp_affiliate_pending', [$this, 'send_admin_new_affiliate_email']);
        
        // Emails para contador
        add_action('lrp_invoice_received', [$this, 'send_accountant_invoice_email'], 10, 2);
    }

    /**
     * Envia email usando template
     * 
     * @param string $to Email do destinatário
     * @param string $subject Assunto do email
     * @param string $template Nome do template (sem extensão)
     * @param array $data Dados para o template
     * @param array $attachments Array de caminhos de arquivos para anexar
     */
    private function send($to, $subject, $template, $data = [], $attachments = []) {
        // Carrega template
        ob_start();
        extract($data, EXTR_SKIP); // EXTR_SKIP previne sobrescrita de variáveis existentes
        include LRP_PLUGIN_DIR . 'includes/emails/templates/' . $template . '.php';
        $body = ob_get_clean();
        
        // Wrapper HTML
        $html = $this->get_email_wrapper($body, $subject);
        
        // Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Lab Resumos <noreply@labresumos.com.br>',
        ];
        
        return wp_mail($to, $subject, $html, $headers, $attachments);
    }

    /**
     * Wrapper HTML para emails
     */
    private function get_email_wrapper($content, $title) {
        $logo_url = LRP_PLUGIN_URL . 'assets/images/logo.png';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding: 20px 0; border-bottom: 2px solid #2A6B9F; }
                .content { padding: 30px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; border-top: 1px solid #eee; }
                .btn { display: inline-block; padding: 12px 24px; background: #2A6B9F; color: #fff; text-decoration: none; border-radius: 6px; }
                .highlight { background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <img src='{$logo_url}' alt='Lab Resumos' style='max-width: 200px;'>
                </div>
                <div class='content'>
                    {$content}
                </div>
                <div class='footer'>
                    <p>Programa de Parceiros Lab Resumos</p>
                    <p>Este é um email automático, não responda.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * 1. Email de boas-vindas
     */
    public function send_welcome_email($affiliate) {
        $dashboard_url = get_permalink(get_option('lrp_dashboard_page_id'));
        
        $this->send(
            $affiliate->get_email(),
            'Bem-vindo ao Programa de Parceiros Lab Resumos!',
            'welcome',
            [
                'affiliate' => $affiliate,
                'dashboard_url' => $dashboard_url,
            ]
        );
    }

    /**
     * 2. Email de nova venda
     */
    public function send_new_sale_email($affiliate, $referral, $order) {
        $this->send(
            $affiliate->get_email(),
            'Nova venda realizada! 🎉',
            'new-sale',
            [
                'affiliate' => $affiliate,
                'referral' => $referral,
                'order' => $order,
            ]
        );
    }

    /**
     * 3. Email de fechamento mensal
     */
    public function send_closing_email($affiliate, $closing_id, $total) {
        $dashboard_url = get_permalink(get_option('lrp_dashboard_page_id')) . '?tab=financeiro';
        
        $closing = LRP_Closing::get($closing_id);
        $period = sprintf('%02d/%d', $closing->period_month, $closing->period_year);
        
        $this->send(
            $affiliate->get_email(),
            "Fechamento de {$period} disponível - R$ " . number_format($total, 2, ',', '.'),
            'monthly-closing',
            [
                'affiliate' => $affiliate,
                'closing' => $closing,
                'total' => $total,
                'dashboard_url' => $dashboard_url,
            ]
        );
    }

    /**
     * 4. Email de NF aprovada
     */
    public function send_invoice_approved_email($affiliate, $closing_id) {
        $this->send(
            $affiliate->get_email(),
            'Sua Nota Fiscal foi aprovada! ✅',
            'invoice-approved',
            [
                'affiliate' => $affiliate,
                'closing' => LRP_Closing::get($closing_id),
            ]
        );
    }

    /**
     * 5. Email de NF rejeitada
     */
    public function send_invoice_rejected_email($affiliate, $closing_id, $reason) {
        $dashboard_url = get_permalink(get_option('lrp_dashboard_page_id')) . '?tab=financeiro';
        
        $this->send(
            $affiliate->get_email(),
            'Sua Nota Fiscal precisa de correção',
            'invoice-rejected',
            [
                'affiliate' => $affiliate,
                'closing' => LRP_Closing::get($closing_id),
                'reason' => $reason,
                'dashboard_url' => $dashboard_url,
            ]
        );
    }

    /**
     * 6. Email de pagamento realizado
     */
    public function send_payment_email($affiliate, $closing_id) {
        $closing = LRP_Closing::get($closing_id);
        
        $this->send(
            $affiliate->get_email(),
            'Pagamento realizado! 💰',
            'payment-received',
            [
                'affiliate' => $affiliate,
                'closing' => $closing,
            ]
        );
    }

    /**
     * 7. Email de novo sub-afiliado
     */
    public function send_new_sub_affiliate_email($sponsor, $new_affiliate) {
        $this->send(
            $sponsor->get_email(),
            'Você tem um novo parceiro na sua rede! 👥',
            'new-sub-affiliate',
            [
                'sponsor' => $sponsor,
                'new_affiliate' => $new_affiliate,
            ]
        );
    }

    /**
     * 8. Email de venda de sub-afiliado
     */
    public function send_sub_affiliate_sale_email($sponsor, $sub_affiliate, $commission, $referral) {
        $this->send(
            $sponsor->get_email(),
            'Comissão de rede! Seu indicado fez uma venda 🎉',
            'sub-affiliate-sale',
            [
                'sponsor' => $sponsor,
                'sub_affiliate' => $sub_affiliate,
                'commission' => $commission,
                'referral' => $referral,
            ]
        );
    }

    /**
     * 9. Email para admin - novo afiliado
     */
    public function send_admin_new_affiliate_email($affiliate) {
        $settings = LRP_Settings::instance();
        $admin_email = $settings->get('admin_email') ?: get_option('admin_email');
        
        $this->send(
            $admin_email,
            'Novo parceiro aguardando aprovação',
            'admin-new-affiliate',
            [
                'affiliate' => $affiliate,
                'admin_url' => admin_url('admin.php?page=lrp-affiliates&id=' . $affiliate->get_id()),
            ]
        );
    }

    /**
     * 10. Email para contador - NF recebida
     */
    public function send_accountant_invoice_email($affiliate, $closing_id) {
        $settings = LRP_Settings::instance();
        $accountant_email = $settings->get('accountant_email', 'financeiro@labresumos.com.br');
        
        $closing = LRP_Closing::get($closing_id);
        
        // Prepara anexo da NF
        $attachments = [];
        if ($closing->invoice_file) {
            $upload_dir = wp_upload_dir();
            $invoice_path = $upload_dir['basedir'] . $closing->invoice_file;
            
            // Verifica se arquivo existe antes de anexar
            if (file_exists($invoice_path)) {
                $attachments[] = $invoice_path;
            }
        }
        
        $this->send(
            $accountant_email,
            'Nova NF recebida - ' . $affiliate->get_display_name(),
            'accountant-invoice',
            [
                'affiliate' => $affiliate,
                'closing' => $closing,
                'accountant_url' => home_url('/area-contador/'),
            ],
            $attachments
        );
    }
}
```

### 2.2 Templates de Email

**Template: welcome.php**
```php
<h2>Olá, <?php echo esc_html($affiliate->get_display_name()); ?>! 🎉</h2>

<p>Seja bem-vindo ao <strong>Programa de Parceiros Lab Resumos</strong>!</p>

<p>Seu cadastro foi aprovado e você já pode começar a divulgar nossos cursos e ganhar comissões.</p>

<div class="highlight">
    <p><strong>Seu cupom exclusivo:</strong> <?php echo esc_html($affiliate->get_coupon_code()); ?></p>
    <p><strong>Seu link de afiliado:</strong> <?php echo esc_url($affiliate->get_referral_url()); ?></p>
</div>

<p>Acesse seu painel para ver todas as suas ferramentas de divulgação:</p>

<p style="text-align: center;">
    <a href="<?php echo esc_url($dashboard_url); ?>" class="btn">Acessar Meu Painel</a>
</p>

<p>Boas vendas!</p>
```

**Template: new-sale.php**
```php
<h2>Parabéns pela venda! 🎉</h2>

<p>Olá, <?php echo esc_html($affiliate->get_display_name()); ?>!</p>

<p>Uma nova venda foi atribuída a você:</p>

<div class="highlight">
    <p><strong>Pedido:</strong> #<?php echo esc_html($order->get_id()); ?></p>
    <p><strong>Valor:</strong> R$ <?php echo esc_html(number_format($referral->commission_base, 2, ',', '.')); ?></p>
    <p><strong>Sua comissão:</strong> R$ <?php echo esc_html(number_format($referral->get_direct_commission(), 2, ',', '.')); ?></p>
    <p><strong>Tipo:</strong> <?php echo esc_html($referral->attribution_type === 'coupon' ? '🎫 Cupom' : '🔗 Link'); ?></p>
</div>

<p>Continue divulgando!</p>
```

---

## 3. Integração WooCommerce

### 3.1 Hooks Principais

```php
<?php
class LRP_WooCommerce {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Quando pedido é criado
        add_action('woocommerce_checkout_order_created', [$this, 'on_order_created'], 10, 1);
        
        // Quando pedido muda de status
        add_action('woocommerce_order_status_completed', [$this, 'on_order_completed']);
        add_action('woocommerce_order_status_processing', [$this, 'on_order_processing']);
        add_action('woocommerce_order_status_refunded', [$this, 'on_order_refunded']);
        add_action('woocommerce_order_status_cancelled', [$this, 'on_order_cancelled']);
        
        // Exibe info de afiliado no admin do pedido
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_order_affiliate_info']);
        
        // Coluna customizada na lista de pedidos
        // IMPORTANTE: Verificar se HPOS está ativo antes de registrar hooks
        // HPOS usa hooks diferentes do modelo legado
        // ATENÇÃO: Garantir que apenas um conjunto de hooks é registrado. Não registrar ambos simultaneamente.
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && 
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            // HPOS ativo - usar hooks novos
            add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_affiliate_column']);
            add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'render_affiliate_column_hpos'], 10, 2);
        } else {
            // Modelo legado - usar hooks antigos
            add_filter('manage_edit-shop_order_columns', [$this, 'add_affiliate_column']);
            add_action('manage_shop_order_posts_custom_column', [$this, 'render_affiliate_column'], 10, 2);
        }
    }

    /**
     * Quando pedido é criado (checkout finalizado)
     */
    public function on_order_created($order) {
        // Processa atribuição
        LRP_Attribution::instance()->process_order_attribution($order->get_id());
    }

    /**
     * Quando pedido é pago/processando
     */
    public function on_order_processing($order_id) {
        $this->approve_commission($order_id);
    }

    /**
     * Quando pedido é completado
     */
    public function on_order_completed($order_id) {
        $this->approve_commission($order_id);
    }

    /**
     * Aprova comissão
     */
    private function approve_commission($order_id) {
        // Obtém objeto do pedido para compatibilidade com HPOS
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $referral_id = $order->get_meta('_lrp_referral_id', true);
        
        if (!$referral_id) {
            return;
        }
        
        global $wpdb;
        
        // Atualiza referral
        $wpdb->update(
            $wpdb->prefix . 'lrp_referrals',
            ['status' => 'approved'],
            ['id' => $referral_id]
        );
        
        // Atualiza comissões
        $wpdb->update(
            $wpdb->prefix . 'lrp_commissions',
            ['status' => 'approved'],
            ['referral_id' => $referral_id, 'status' => 'pending']
        );
        
        // Atualiza stats do afiliado
        $affiliate_id = $order->get_meta('_lrp_affiliate_id', true);
        if ($affiliate_id) {
            $affiliate = new LRP_Affiliate($affiliate_id);
            $affiliate->refresh_stats();
        }
        
        // Dispara distribuição multi-nível
        $referral = LRP_Referral::get($referral_id);
        do_action('lrp_referral_approved', $referral);
    }

    /**
     * Quando pedido é reembolsado
     */
    public function on_order_refunded($order_id) {
        $this->cancel_commission($order_id, 'refunded');
    }

    /**
     * Quando pedido é cancelado
     */
    public function on_order_cancelled($order_id) {
        $this->cancel_commission($order_id, 'cancelled');
    }

    /**
     * Cancela comissão
     */
    private function cancel_commission($order_id, $reason) {
        // Obtém objeto do pedido para compatibilidade com HPOS
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $referral_id = $order->get_meta('_lrp_referral_id', true);
        
        if (!$referral_id) {
            return;
        }
        
        global $wpdb;
        
        // Atualiza referral
        $wpdb->update(
            $wpdb->prefix . 'lrp_referrals',
            ['status' => $reason],
            ['id' => $referral_id]
        );
        
        // Cancela comissões que ainda não foram pagas
        // Usa query direta pois $wpdb->update() não suporta arrays no WHERE
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}lrp_commissions 
             SET status = 'cancelled' 
             WHERE referral_id = %d AND status IN ('pending', 'approved')",
            $referral_id
        ));
        
        // Atualiza stats
        $affiliate_id = $order->get_meta('_lrp_affiliate_id', true);
        if ($affiliate_id) {
            $affiliate = new LRP_Affiliate($affiliate_id);
            $affiliate->refresh_stats();
        }
    }

    /**
     * Exibe info do afiliado no admin do pedido
     */
    public function display_order_affiliate_info($order) {
        $affiliate_id = $order->get_meta('_lrp_affiliate_id');
        
        if (!$affiliate_id) {
            return;
        }
        
        $affiliate = new LRP_Affiliate($affiliate_id);
        $attribution_type = $order->get_meta('_lrp_attribution_type');
        $coupon_used = $order->get_meta('_lrp_coupon_used');
        $commission = $order->get_meta('_lrp_commission_amount');
        $is_guruja = $order->get_meta('_lrp_is_guruja_discount');
        
        ?>
        <div class="lrp-order-affiliate-info" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #2A6B9F;">
            <h4 style="margin-top: 0;">🤝 Venda de Parceiro</h4>
            <p><strong>Parceiro:</strong> <?php echo esc_html($affiliate->get_display_name()); ?> (#<?php echo $affiliate->get_id(); ?>)</p>
            <p><strong>Atribuição:</strong> <?php echo $attribution_type === 'coupon' ? '🎫 Cupom (' . $coupon_used . ')' : '🔗 Link'; ?></p>
            <p><strong>Comissão:</strong> R$ <?php echo number_format($commission, 2, ',', '.'); ?></p>
            <?php if ($is_guruja): ?>
                <p><strong>Desconto:</strong> 🎓 Aluno Guruja</p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Adiciona coluna de afiliado na lista de pedidos
     */
    public function add_affiliate_column($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'order_status') {
                $new_columns['lrp_affiliate'] = 'Parceiro';
            }
        }
        
        return $new_columns;
    }

    /**
     * Renderiza coluna de afiliado (modelo legado)
     */
    public function render_affiliate_column($column, $post_id) {
        if ($column !== 'lrp_affiliate') {
            return;
        }
        
        $order = wc_get_order($post_id);
        if (!$order) {
            echo '—';
            return;
        }
        
        $affiliate_id = $order->get_meta('_lrp_affiliate_id');
        
        if (!$affiliate_id) {
            echo '—';
            return;
        }
        
        $affiliate = new LRP_Affiliate($affiliate_id);
        $type = $order->get_meta('_lrp_attribution_type');
        
        $icon = $type === 'coupon' ? '🎫' : '🔗';
        
        echo sprintf(
            '%s <a href="%s">%s</a>',
            $icon,
            admin_url('admin.php?page=lrp-affiliates&id=' . $affiliate_id),
            esc_html($affiliate->get_display_name())
        );
    }

    /**
     * Renderiza coluna de afiliado (HPOS)
     */
    public function render_affiliate_column_hpos($column, $order) {
        if ($column !== 'lrp_affiliate') {
            return;
        }
        
        // $order já é um objeto WC_Order no HPOS
        if (!$order || !is_a($order, 'WC_Order')) {
            echo '—';
            return;
        }
        
        $affiliate_id = $order->get_meta('_lrp_affiliate_id');
        
        if (!$affiliate_id) {
            echo '—';
            return;
        }
        
        $affiliate = new LRP_Affiliate($affiliate_id);
        $type = $order->get_meta('_lrp_attribution_type');
        
        $icon = $type === 'coupon' ? '🎫' : '🔗';
        
        echo sprintf(
            '%s <a href="%s">%s</a>',
            $icon,
            admin_url('admin.php?page=lrp-affiliates&id=' . $affiliate_id),
            esc_html($affiliate->get_display_name())
        );
    }
}
```

---

## 4. Cron Jobs

```php
<?php
class LRP_Cron {

    public static function init() {
        // IMPORTANTE: Não usar intervalo 'monthly' customizado (30 dias não é sempre um mês)
        // Ao invés disso, usar evento diário que verifica se é dia 1
        
        // Hooks
        add_action('lrp_daily_check', [__CLASS__, 'check_monthly_closing']);
        add_action('lrp_cleanup_expired', [__CLASS__, 'cleanup_expired']);
        add_action('lrp_cleanup_expired', [__CLASS__, 'cleanup_old_logs']);
        add_action('lrp_weekly_summary', [__CLASS__, 'send_weekly_summary']);
    }

    /**
     * Verifica se é dia 1 e executa fechamento mensal
     */
    public static function check_monthly_closing() {
        // Verifica se é dia 1 do mês
        if ((int) date('j') === 1) {
            LRP_Closing::run_monthly_closing();
        }
    }

    /**
     * Limpa dados expirados
     */
    public static function cleanup_expired() {
        global $wpdb;
        
        // Remove visitas antigas (mais de 90 dias)
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}lrp_visits 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
    }

    /**
     * Limpa logs antigos (mais de 90 dias)
     */
    public static function cleanup_old_logs() {
        global $wpdb;
        
        // Remove logs com mais de 90 dias
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}lrp_activity_log 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        
        // Remove IPs antigos de visitas (mais de 90 dias) - conformidade LGPD
        $wpdb->query(
            "UPDATE {$wpdb->prefix}lrp_visits 
             SET visitor_ip = NULL 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        
        // Remove IPs antigos de afiliados (mais de 90 dias) - conformidade LGPD
        $wpdb->query(
            "UPDATE {$wpdb->prefix}lrp_affiliates 
             SET application_ip = NULL 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
    }

    /**
     * Envia resumo semanal para admin
     */
    public static function send_weekly_summary() {
        // TODO: Implementar resumo semanal
    }
}
```