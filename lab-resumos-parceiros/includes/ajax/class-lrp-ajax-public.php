<?php
/**
 * AJAX - Ações Públicas
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Ajax_Public
 * 
 * Handlers AJAX para o dashboard do afiliado.
 */
class LRP_Ajax_Public {

    /**
     * Instância única
     *
     * @var LRP_Ajax_Public|null
     */
    private static $instance = null;

    /**
     * Retorna instância única
     *
     * @return LRP_Ajax_Public
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado
     */
    private function __construct() {
        // Upload de NF
        add_action('wp_ajax_lrp_upload_invoice', [$this, 'upload_invoice']);
        
        // Atualização de perfil
        add_action('wp_ajax_lrp_update_profile', [$this, 'update_profile']);
        
        // Busca de vendas (paginação)
        add_action('wp_ajax_lrp_get_sales', [$this, 'get_sales']);
        
        // Estatísticas do dashboard
        add_action('wp_ajax_lrp_get_stats', [$this, 'get_stats']);
        
        // Registro de visita enriquecida (para usuários logados e não logados)
        add_action('wp_ajax_lrp_register_visit', [$this, 'register_visit']);
        add_action('wp_ajax_nopriv_lrp_register_visit', [$this, 'register_visit']);
        
        // Estatísticas de tráfego para dashboard
        add_action('wp_ajax_lrp_get_traffic_stats', [$this, 'get_traffic_stats']);
        
        // Estatísticas de desempenho para dashboard
        add_action('wp_ajax_lrp_get_performance_stats', [$this, 'get_performance_stats']);
        
        // Adiamento de fechamento (v1.4.0)
        add_action('wp_ajax_lrp_defer_closing', [$this, 'defer_closing']);
        
        // Aceite de termos (v1.6.0)
        add_action('wp_ajax_lrp_accept_terms', [$this, 'accept_terms']);
    }

    /**
     * Upload de NF
     */
    public function upload_invoice() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['lrp_nonce'] ?? '', 'lrp_upload_invoice')) {
            wp_send_json_error(['message' => __('Erro de segurança.', 'lab-resumos-parceiros')]);
        }
        
        $closing_id = (int) ($_POST['closing_id'] ?? 0);
        $invoice_number = sanitize_text_field($_POST['invoice_number'] ?? '');
        
        // Verifica se tem arquivo
        if (empty($_FILES['invoice_file'])) {
            wp_send_json_error(['message' => __('Selecione um arquivo.', 'lab-resumos-parceiros')]);
        }
        
        // Busca o fechamento
        $closing = LRP_Closing::get($closing_id);
        
        if (!$closing) {
            wp_send_json_error(['message' => __('Fechamento não encontrado.', 'lab-resumos-parceiros')]);
        }
        
        // Verifica permissão: afiliado dono OU admin com permissão
        $affiliate = LRP_Affiliate::get_by_user_id(get_current_user_id());
        $is_own_closing = $affiliate && $affiliate->is_active() && (int) $closing->affiliate_id === $affiliate->get_id();
        $is_admin = current_user_can('lrp_manage_affiliates');
        
        if (!$is_own_closing && !$is_admin) {
            wp_send_json_error(['message' => __('Fechamento inválido.', 'lab-resumos-parceiros')]);
        }
        
        // Processa upload (o método já valida status e segurança)
        $result = LRP_Closing::upload_invoice($closing_id, $_FILES['invoice_file'], $invoice_number);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success([
            'message' => __('NF enviada com sucesso! Aguarde validação.', 'lab-resumos-parceiros'),
        ]);
    }

    /**
     * Atualização de perfil
     */
    public function update_profile() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['lrp_nonce'] ?? '', 'lrp_update_profile')) {
            wp_send_json_error(['message' => __('Erro de segurança.', 'lab-resumos-parceiros')]);
        }
        
        // Verifica rate limit
        if (!$this->check_rate_limit('profile_update')) {
            wp_send_json_error(['message' => __('Muitas tentativas. Aguarde.', 'lab-resumos-parceiros')]);
        }
        
        $affiliate = LRP_Affiliate::get_by_user_id(get_current_user_id());
        
        if (!$affiliate || !$affiliate->is_active()) {
            wp_send_json_error(['message' => __('Acesso negado.', 'lab-resumos-parceiros')]);
        }
        
        // Dados permitidos
        $data = [];
        
        // Dados de identificação (v1.7.0 - sempre obrigatórios)
        if (isset($_POST['first_name'])) {
            $data['first_name'] = sanitize_text_field($_POST['first_name']);
        }
        
        if (isset($_POST['last_name'])) {
            $data['last_name'] = sanitize_text_field($_POST['last_name']);
        }
        
        if (isset($_POST['cpf'])) {
            $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']);
            if (strlen($cpf) === 11) {
                $data['cpf'] = $cpf;
            }
        }
        
        // Tipo de faturamento (v1.4.0)
        if (isset($_POST['billing_type'])) {
            $billing_type = sanitize_key($_POST['billing_type']);
            if (in_array($billing_type, ['pj', 'rpa'])) {
                $data['billing_type'] = $billing_type;
            }
        }
        
        // Dados PJ
        if (isset($_POST['company_cnpj'])) {
            $cnpj = preg_replace('/[^0-9]/', '', $_POST['company_cnpj']);
            if (strlen($cnpj) === 14) {
                $data['company_cnpj'] = $cnpj;
            }
        }
        
        if (isset($_POST['company_name'])) {
            $data['company_name'] = sanitize_text_field($_POST['company_name']);
        }
        
        // Dados RPA (v1.4.0)
        if (isset($_POST['full_address'])) {
            $data['full_address'] = sanitize_textarea_field($_POST['full_address']);
        }
        
        if (isset($_POST['phone'])) {
            $data['phone'] = preg_replace('/[^0-9]/', '', $_POST['phone']);
        }
        
        if (isset($_POST['inss_number'])) {
            $data['inss_number'] = sanitize_text_field($_POST['inss_number']);
        }

        if (isset($_POST['birth_date'])) {
            $data['birth_date'] = sanitize_text_field($_POST['birth_date']);
        }
        
        // Dados de pagamento
        if (isset($_POST['payment_method'])) {
            $data['payment_method'] = sanitize_key($_POST['payment_method']);
        }
        
        if (isset($_POST['pix_key_type'])) {
            $data['pix_key_type'] = sanitize_key($_POST['pix_key_type']);
        }
        
        if (isset($_POST['pix_key']) && !empty($_POST['pix_key'])) {
            // Só atualiza se não estiver mascarado
            $pix_key = sanitize_text_field($_POST['pix_key']);
            if (strpos($pix_key, '*') === false) {
                $data['pix_key'] = $pix_key;
            }
        }
        
        if (isset($_POST['holder_name'])) {
            $data['holder_name'] = sanitize_text_field($_POST['holder_name']);
        }
        
        if (isset($_POST['holder_document'])) {
            $data['holder_document'] = preg_replace('/[^0-9]/', '', $_POST['holder_document']);
        }
        
        // Dados bancários
        if (isset($_POST['bank_name'])) {
            $data['bank_name'] = sanitize_text_field($_POST['bank_name']);
        }
        
        if (isset($_POST['bank_agency'])) {
            $data['bank_agency'] = sanitize_text_field($_POST['bank_agency']);
        }
        
        if (isset($_POST['bank_account'])) {
            $data['bank_account'] = sanitize_text_field($_POST['bank_account']);
        }
        
        if (isset($_POST['bank_account_type'])) {
            $data['bank_account_type'] = sanitize_key($_POST['bank_account_type']);
        }
        
        // Atualiza dados do usuário WordPress
        $user_id = $affiliate->get_user_id();
        $user_data = [];
        
        // Sincroniza Nome e Sobrenome com WordPress (v1.7.0)
        if (isset($_POST['first_name']) && !empty($_POST['first_name'])) {
            $user_data['first_name'] = sanitize_text_field($_POST['first_name']);
        }
        
        if (isset($_POST['last_name']) && !empty($_POST['last_name'])) {
            $user_data['last_name'] = sanitize_text_field($_POST['last_name']);
        }
        
        // Atualiza display_name automaticamente se first_name ou last_name foram alterados
        if (!empty($user_data['first_name']) || !empty($user_data['last_name'])) {
            $first = $user_data['first_name'] ?? sanitize_text_field($_POST['first_name'] ?? '');
            $last = $user_data['last_name'] ?? sanitize_text_field($_POST['last_name'] ?? '');
            $user_data['display_name'] = trim($first . ' ' . $last);
        }
        
        if (isset($_POST['user_email']) && is_email($_POST['user_email'])) {
            $user_data['user_email'] = sanitize_email($_POST['user_email']);
        }
        
        if (!empty($user_data)) {
            $user_data['ID'] = $user_id;
            wp_update_user($user_data);
        }
        
        // Atualiza afiliado
        $result = $affiliate->update($data);
        
        if ($result) {
            wp_send_json_success([
                'message' => __('Perfil atualizado com sucesso!', 'lab-resumos-parceiros'),
            ]);
        }
        
        wp_send_json_error(['message' => __('Erro ao atualizar.', 'lab-resumos-parceiros')]);
    }
    
    /**
     * Adiamento de fechamento pelo afiliado
     * 
     * @since 1.4.0
     */
    public function defer_closing() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'lrp_defer_closing')) {
            wp_send_json_error(['message' => __('Erro de segurança.', 'lab-resumos-parceiros')]);
        }
        
        // Verifica se configuração permite
        $settings = LRP_Settings::instance();
        if (!$settings->get('allow_affiliate_defer', true)) {
            wp_send_json_error(['message' => __('Adiamento não permitido.', 'lab-resumos-parceiros')]);
        }
        
        $affiliate = LRP_Affiliate::get_by_user_id(get_current_user_id());
        
        if (!$affiliate || !$affiliate->is_active()) {
            wp_send_json_error(['message' => __('Acesso negado.', 'lab-resumos-parceiros')]);
        }
        
        $closing_id = (int) ($_POST['closing_id'] ?? 0);
        
        if (!$closing_id) {
            wp_send_json_error(['message' => __('Fechamento inválido.', 'lab-resumos-parceiros')]);
        }
        
        // Verifica se fechamento pertence ao afiliado
        global $wpdb;
        $closing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lrp_closings WHERE id = %d AND affiliate_id = %d",
            $closing_id,
            $affiliate->get_id()
        ));
        
        if (!$closing) {
            wp_send_json_error(['message' => __('Fechamento não encontrado.', 'lab-resumos-parceiros')]);
        }
        
        // Verifica se pode ser adiado (status deve ser awaiting_invoice ou awaiting_rpa)
        if (!in_array($closing->status, ['awaiting_invoice', 'awaiting_rpa'])) {
            wp_send_json_error(['message' => __('Este fechamento não pode ser adiado.', 'lab-resumos-parceiros')]);
        }
        
        // Verifica se já foi adiado
        if (!empty($closing->deferred)) {
            wp_send_json_error(['message' => __('Este fechamento já foi adiado anteriormente.', 'lab-resumos-parceiros')]);
        }
        
        // Executa o adiamento
        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_closings',
            [
                'deferred'              => 1,
                'deferred_at'           => current_time('mysql'),
                'deferred_by'           => get_current_user_id(),
                'deferred_reason'       => __('Solicitado pelo afiliado', 'lab-resumos-parceiros'),
                'original_period_month' => $closing->period_month,
                'original_period_year'  => $closing->period_year,
                'status'                => 'closed', // Fecha o período atual
            ],
            ['id' => $closing_id]
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => __('Erro ao adiar fechamento.', 'lab-resumos-parceiros')]);
        }
        
        // Atualiza próxima data de pagamento
        $affiliate->update_next_payment_date();
        
        // Log
        $affiliate->log_activity('closing_deferred', [
            'closing_id' => $closing_id,
            'period'     => sprintf('%02d/%d', $closing->period_month, $closing->period_year),
            'amount'     => $closing->total_commissions,
        ]);
        
        $next_date = $affiliate->get_next_payment_date_formatted();
        
        wp_send_json_success([
            'message' => sprintf(
                __('Fechamento adiado com sucesso! O saldo foi acumulado para o próximo período (%s).', 'lab-resumos-parceiros'),
                $next_date
            ),
            'next_payment_date' => $next_date,
        ]);
    }

    /**
     * Busca vendas paginadas
     */
    public function get_sales() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'lrp_dashboard_nonce')) {
            wp_send_json_error(['message' => __('Erro de segurança.', 'lab-resumos-parceiros')]);
        }
        
        $affiliate = LRP_Affiliate::get_by_user_id(get_current_user_id());
        
        if (!$affiliate) {
            wp_send_json_error(['message' => __('Acesso negado.', 'lab-resumos-parceiros')]);
        }
        
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $per_page = 20;
        
        $sales = LRP_Referral::get_by_affiliate($affiliate->get_id(), [
            'limit'  => $per_page,
            'offset' => ($page - 1) * $per_page,
        ]);
        
        $formatted = [];
        
        foreach ($sales as $sale) {
            $formatted[] = [
                'id'          => $sale->get_id(),
                'order_id'    => $sale->get_order_id(),
                'date'        => date_i18n('d/m/Y', strtotime($sale->get_created_at())),
                'type'        => $sale->get_attribution_type(),
                'value'       => number_format($sale->get_commission_base(), 2, ',', '.'),
                'commission'  => number_format($sale->get_direct_commission(), 2, ',', '.'),
                'status'      => $sale->get_status(),
            ];
        }
        
        wp_send_json_success(['sales' => $formatted]);
    }

    /**
     * Obtém estatísticas atualizadas
     */
    public function get_stats() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'lrp_dashboard_nonce')) {
            wp_send_json_error(['message' => __('Erro de segurança.', 'lab-resumos-parceiros')]);
        }
        
        $affiliate = LRP_Affiliate::get_by_user_id(get_current_user_id());
        
        if (!$affiliate) {
            wp_send_json_error(['message' => __('Acesso negado.', 'lab-resumos-parceiros')]);
        }
        
        // Limpa cache e recalcula
        delete_transient('lrp_affiliate_stats_' . $affiliate->get_id());
        $affiliate->refresh_stats();
        
        wp_send_json_success([
            'balance'     => number_format($affiliate->get_current_balance(), 2, ',', '.'),
            'total_sales' => $affiliate->get_total_sales(),
            'total_earned'=> number_format($affiliate->get_total_commissions(), 2, ',', '.'),
        ]);
    }

    /**
     * Verifica rate limit
     *
     * @param string $action
     * @return bool
     */
    private function check_rate_limit($action) {
        $user_id = get_current_user_id();
        $key = 'lrp_rate_' . $action . '_' . $user_id;
        
        $count = (int) get_transient($key);
        
        if ($count >= 10) {
            return false;
        }
        
        set_transient($key, $count + 1, MINUTE_IN_SECONDS);
        
        return true;
    }
    
    /**
     * Registra visita com dados enriquecidos
     * 
     * @since 1.2.0
     */
    public function register_visit() {
        try {
            // Verifica nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'lrp_tracking_nonce')) {
                $this->log_visit_error('Nonce inválido', ['received' => substr($_POST['nonce'] ?? '', 0, 10) . '...']);
                wp_send_json_error(['message' => 'Invalid nonce', 'code' => 'invalid_nonce']);
                return;
            }
            
            $ref_code = sanitize_text_field($_POST['ref'] ?? '');
            
            if (empty($ref_code)) {
                wp_send_json_error(['message' => 'Missing ref code', 'code' => 'missing_ref']);
                return;
            }
            
            // Verifica se classe existe
            if (!class_exists('LRP_Affiliate')) {
                $this->log_visit_error('Classe LRP_Affiliate não encontrada');
                wp_send_json_error(['message' => 'System error', 'code' => 'class_not_found']);
                return;
            }
            
            // Busca afiliado pelo código
            $affiliate = LRP_Affiliate::get_by_referral_code($ref_code);
            
            if (!$affiliate) {
                // Log silencioso - pode ser tentativa de código inválido
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $this->log_visit_error('Código de referral não encontrado', ['ref' => $ref_code]);
                }
                wp_send_json_error(['message' => 'Invalid affiliate', 'code' => 'affiliate_not_found']);
                return;
            }
            
            if (!$affiliate->is_active()) {
                wp_send_json_error(['message' => 'Affiliate inactive', 'code' => 'affiliate_inactive']);
                return;
            }
            
            global $wpdb;
            
            // Verifica se tabela existe
            $table = $wpdb->prefix . 'lrp_visits';
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            
            if (!$table_exists) {
                $this->log_visit_error('Tabela lrp_visits não existe. Execute a ativação do plugin.');
                wp_send_json_error(['message' => 'System not configured', 'code' => 'table_not_found']);
                return;
            }
            
            // Gera ou obtém visitor hash
            $visitor_hash = $this->get_visitor_hash();
            
            // Verifica se já existe visita recente deste visitante (evita duplicatas)
            $wpdb->suppress_errors(true);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} 
                 WHERE affiliate_id = %d AND visitor_hash = %s 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                $affiliate->get_id(),
                $visitor_hash
            ));
            $wpdb->suppress_errors(false);
            
            if ($wpdb->last_error) {
                $this->log_visit_error('Erro SQL ao verificar duplicata', ['error' => $wpdb->last_error]);
                // Continua mesmo com erro - melhor registrar do que perder
            }
            
            if ($exists) {
                wp_send_json_success(['message' => 'Visit already recorded', 'duplicate' => true]);
                return;
            }
            
            // Sanitiza dados com limites de tamanho
            $data = [
                'affiliate_id'   => $affiliate->get_id(),
                'visitor_ip'     => $this->get_client_ip(),
                'visitor_hash'   => $visitor_hash,
                'referral_url'   => $this->sanitize_url($_POST['referrer_url'] ?? '', 2000),
                'landing_page'   => $this->sanitize_text($_POST['landing_page'] ?? '', 500),
                'utm_source'     => $this->sanitize_text($_POST['utm_source'] ?? '', 100),
                'utm_medium'     => $this->sanitize_text($_POST['utm_medium'] ?? '', 100),
                'utm_campaign'   => $this->sanitize_text($_POST['utm_campaign'] ?? '', 100),
                'utm_term'       => $this->sanitize_text($_POST['utm_term'] ?? '', 100),
                'utm_content'    => $this->sanitize_text($_POST['utm_content'] ?? '', 100),
                'traffic_source' => $this->sanitize_text($_POST['traffic_source'] ?? '', 50),
                'device_type'    => $this->sanitize_device_type($_POST['device_type'] ?? ''),
                'browser'        => $this->sanitize_text($_POST['browser'] ?? '', 50),
                'converted'      => 0,
                'created_at'     => current_time('mysql'),
            ];
            
            // Limpa valores vazios para NULL (banco aceita melhor)
            foreach ($data as $key => $value) {
                if ($value === '' && !in_array($key, ['converted', 'affiliate_id', 'created_at'])) {
                    $data[$key] = null;
                }
            }
            
            // Insere com tratamento de erro
            $wpdb->suppress_errors(true);
            $result = $wpdb->insert($table, $data);
            $last_error = $wpdb->last_error;
            $insert_id = $wpdb->insert_id;
            $wpdb->suppress_errors(false);
            
            if ($result === false || $last_error) {
                $this->log_visit_error('Erro ao inserir visita', [
                    'error' => $last_error,
                    'affiliate_id' => $affiliate->get_id(),
                    'ref' => $ref_code,
                ]);
                wp_send_json_error(['message' => 'Failed to record visit', 'code' => 'insert_failed']);
                return;
            }
            
            // Log de sucesso apenas em debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[LRP Ajax] Visita registrada: affiliate=%d, source=%s, device=%s',
                    $affiliate->get_id(),
                    $data['traffic_source'] ?? 'direct',
                    $data['device_type'] ?? 'unknown'
                ));
            }
            
            wp_send_json_success([
                'message' => 'Visit recorded',
                'id' => $insert_id,
            ]);
            
        } catch (Exception $e) {
            $this->log_visit_error('Exceção em register_visit', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            wp_send_json_error(['message' => 'System error', 'code' => 'exception']);
        } catch (Error $e) {
            $this->log_visit_error('Erro fatal em register_visit', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            wp_send_json_error(['message' => 'System error', 'code' => 'fatal_error']);
        }
    }
    
    /**
     * Log de erro de visita
     *
     * @param string $message
     * @param array $context
     */
    private function log_visit_error($message, $context = []) {
        $formatted = '[LRP Ajax] ' . $message;
        if (!empty($context)) {
            $formatted .= ' | ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        error_log($formatted);
        
        if (function_exists('lrp_log')) {
            lrp_log($message, $context, 'error');
        }
    }
    
    /**
     * Sanitiza texto com limite de tamanho
     *
     * @param string $text
     * @param int $max_length
     * @return string
     */
    private function sanitize_text($text, $max_length = 255) {
        $text = sanitize_text_field($text);
        return mb_substr($text, 0, $max_length);
    }
    
    /**
     * Sanitiza URL com limite de tamanho
     *
     * @param string $url
     * @param int $max_length
     * @return string
     */
    private function sanitize_url($url, $max_length = 2000) {
        $url = esc_url_raw($url);
        return mb_substr($url, 0, $max_length);
    }
    
    /**
     * Obtém estatísticas de tráfego do afiliado
     */
    public function get_traffic_stats() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'lrp_dashboard_nonce')) {
            wp_send_json_error(['message' => __('Erro de segurança.', 'lab-resumos-parceiros')]);
        }
        
        $affiliate = LRP_Affiliate::get_by_user_id(get_current_user_id());
        
        if (!$affiliate) {
            wp_send_json_error(['message' => __('Acesso negado.', 'lab-resumos-parceiros')]);
        }
        
        $period = sanitize_key($_GET['period'] ?? 'month');
        
        // Busca do cache
        $stats = LRP_Stats_Calculator::get_cached_stats($affiliate->get_id(), $period);
        
        if ($stats) {
            wp_send_json_success([
                'source_distribution' => json_decode($stats->source_distribution, true) ?: [],
                'device_distribution' => json_decode($stats->device_distribution, true) ?: [],
                'total_clicks'        => (int) $stats->total_clicks,
                'unique_visitors'     => (int) $stats->unique_visitors,
                'conversion_rate'     => (float) $stats->conversion_rate,
            ]);
        }
        
        wp_send_json_success([
            'source_distribution' => [],
            'device_distribution' => [],
            'total_clicks'        => 0,
            'unique_visitors'     => 0,
            'conversion_rate'     => 0,
        ]);
    }
    
    /**
     * Obtém estatísticas de desempenho do afiliado
     */
    public function get_performance_stats() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'lrp_dashboard_nonce')) {
            wp_send_json_error(['message' => __('Erro de segurança.', 'lab-resumos-parceiros')]);
        }
        
        $affiliate = LRP_Affiliate::get_by_user_id(get_current_user_id());
        
        if (!$affiliate) {
            wp_send_json_error(['message' => __('Acesso negado.', 'lab-resumos-parceiros')]);
        }
        
        $period = sanitize_key($_GET['period'] ?? 'month');
        
        // Busca do cache
        $stats = LRP_Stats_Calculator::get_cached_stats($affiliate->get_id(), $period);
        
        if ($stats) {
            wp_send_json_success([
                'rank_position'        => (int) $stats->rank_position,
                'rank_percentile'      => (int) $stats->rank_percentile,
                'state_distribution'   => json_decode($stats->state_distribution, true) ?: [],
                'payment_distribution' => json_decode($stats->payment_distribution, true) ?: [],
                'products_distribution'=> json_decode($stats->products_distribution, true) ?: [],
                'total_sales'          => (int) $stats->total_sales,
                'total_revenue'        => (float) $stats->total_revenue,
                'avg_ticket'           => (float) $stats->avg_ticket,
            ]);
        }
        
        wp_send_json_success([
            'rank_position'        => 0,
            'rank_percentile'      => 0,
            'state_distribution'   => [],
            'payment_distribution' => [],
            'products_distribution'=> [],
            'total_sales'          => 0,
            'total_revenue'        => 0,
            'avg_ticket'           => 0,
        ]);
    }
    
    /**
     * Gera hash único para o visitante
     */
    private function get_visitor_hash() {
        // Verifica se já existe cookie
        if (isset($_COOKIE['lrp_visit_hash'])) {
            $hash = sanitize_text_field($_COOKIE['lrp_visit_hash']);
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $hash)) {
                return $hash;
            }
        }
        
        // Gera novo UUID v4
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        return $uuid;
    }
    
    /**
     * Obtém IP real do cliente
     */
    private function get_client_ip() {
        // Verifica se há proxy confiável
        if (defined('LRP_TRUSTED_PROXY') && LRP_TRUSTED_PROXY) {
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
            if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                $ip = trim($_SERVER['HTTP_X_REAL_IP']);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Sanitiza tipo de dispositivo
     */
    private function sanitize_device_type($device) {
        $valid = ['desktop', 'mobile', 'tablet'];
        $device = strtolower(sanitize_key($device));
        return in_array($device, $valid) ? $device : null;
    }
    
    /**
     * Aceite de termos pelo afiliado
     * 
     * @since 1.6.0
     */
    public function accept_terms() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['lrp_terms_nonce'] ?? '', 'lrp_accept_terms')) {
            wp_send_json_error(__('Erro de segurança. Recarregue a página e tente novamente.', 'lab-resumos-parceiros'));
        }
        
        // Verifica se usuário é afiliado
        $affiliate = LRP_Affiliate::get_by_user_id(get_current_user_id());
        
        if (!$affiliate || !$affiliate->is_active()) {
            wp_send_json_error(__('Você precisa ser um parceiro ativo para aceitar os termos.', 'lab-resumos-parceiros'));
        }
        
        // Verifica se checkbox foi marcado
        if (empty($_POST['accept_terms'])) {
            wp_send_json_error(__('Você precisa marcar a caixa de aceite.', 'lab-resumos-parceiros'));
        }
        
        $version = sanitize_text_field($_POST['version'] ?? '');
        
        if (empty($version)) {
            wp_send_json_error(__('Versão dos termos inválida.', 'lab-resumos-parceiros'));
        }
        
        // Registra aceite
        $terms_manager = LRP_Terms::instance();
        $result = $terms_manager->record_acceptance($affiliate->get_id(), $version);
        
        if ($result) {
            // Log de atividade
            $affiliate->log_activity('terms_accepted', [
                'version' => $version,
                'ip'      => $this->get_client_ip(),
            ]);
            
            wp_send_json_success([
                'message' => __('Termos aceitos com sucesso! Você receberá um email de confirmação.', 'lab-resumos-parceiros'),
            ]);
        }
        
        wp_send_json_error(__('Erro ao registrar aceite. Tente novamente.', 'lab-resumos-parceiros'));
    }
}

