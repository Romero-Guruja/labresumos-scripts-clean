<?php
/**
 * AJAX - Ações Admin
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Ajax_Admin
 * 
 * Handlers AJAX para o admin.
 */
class LRP_Ajax_Admin {

    /**
     * Instância única
     *
     * @var LRP_Ajax_Admin|null
     */
    private static $instance = null;

    /**
     * Retorna instância única
     *
     * @return LRP_Ajax_Admin
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
        // Aprovar afiliado
        add_action('wp_ajax_lrp_approve_affiliate', [$this, 'approve_affiliate']);
        
        // Rejeitar afiliado
        add_action('wp_ajax_lrp_reject_affiliate', [$this, 'reject_affiliate']);
        
        // Atualizar afiliado
        add_action('wp_ajax_lrp_update_affiliate', [$this, 'update_affiliate']);
        
        // Atualizar faturamento do afiliado (v1.4.0)
        add_action('wp_ajax_lrp_update_affiliate_billing', [$this, 'update_affiliate_billing']);
        
        // Adiar fechamento pelo admin (v1.4.0)
        add_action('wp_ajax_lrp_admin_defer_closing', [$this, 'admin_defer_closing']);
        
        // Aprovar comissões em massa
        add_action('wp_ajax_lrp_bulk_approve_commissions', [$this, 'bulk_approve_commissions']);
        
        // Aprovar NF
        add_action('wp_ajax_lrp_approve_invoice', [$this, 'approve_invoice']);
        
        // Aprovar RPA (v1.7.1)
        add_action('wp_ajax_lrp_approve_rpa', [$this, 'approve_rpa']);
        
        // Rejeitar NF
        add_action('wp_ajax_lrp_reject_invoice', [$this, 'reject_invoice']);
        
        // Confirmar pagamento
        add_action('wp_ajax_lrp_confirm_payment', [$this, 'confirm_payment']);
        
        // Salvar material
        add_action('wp_ajax_lrp_save_material', [$this, 'save_material']);
        
        // Excluir material
        add_action('wp_ajax_lrp_delete_material', [$this, 'delete_material']);
        
        // Salvar FAQ
        add_action('wp_ajax_lrp_save_faq', [$this, 'save_faq']);
        
        // Excluir FAQ
        add_action('wp_ajax_lrp_delete_faq', [$this, 'delete_faq']);
        
        // Exportar CSV
        add_action('wp_ajax_lrp_export_csv', [$this, 'export_csv']);
        
        // Dados do gráfico
        add_action('wp_ajax_lrp_get_chart_data', [$this, 'get_chart_data']);
        
        
        // Restrições de produtos
        add_action('wp_ajax_lrp_add_product_restriction', [$this, 'add_product_restriction']);
        add_action('wp_ajax_lrp_remove_product_restriction', [$this, 'remove_product_restriction']);
        add_action('wp_ajax_lrp_search_items', [$this, 'search_items']);
        
        // Ajustes manuais (novo sistema v1.4.0)
        add_action('wp_ajax_lrp_create_adjustment', [$this, 'create_adjustment']);
        add_action('wp_ajax_lrp_cancel_adjustment', [$this, 'cancel_adjustment']);
        
        // Fechamento manual (v1.8.0)
        add_action('wp_ajax_lrp_run_manual_closing', [$this, 'run_manual_closing']);
        
        // Download seguro de arquivos (NF e comprovantes)
        add_action('wp_ajax_lrp_download_file', [$this, 'download_file']);
    }

    /**
     * Aprova afiliado
     */
    public function approve_affiliate() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_affiliates')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $affiliate_id = (int) ($_POST['affiliate_id'] ?? 0);
        
        $result = LRP_Admin_Affiliates::process_action('approve', $affiliate_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => __('Afiliado aprovado!', 'lab-resumos-parceiros')]);
    }

    /**
     * Rejeita afiliado
     */
    public function reject_affiliate() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_affiliates')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $affiliate_id = (int) ($_POST['affiliate_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        
        $affiliate = new LRP_Affiliate($affiliate_id);
        $result = LRP_Admin_Affiliates::reject($affiliate, $reason);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Erro ao rejeitar.', 'lab-resumos-parceiros')]);
        }
        
        wp_send_json_success(['message' => __('Afiliado rejeitado.', 'lab-resumos-parceiros')]);
    }

    /**
     * Atualiza afiliado
     */
    public function update_affiliate() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_affiliates')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $affiliate_id = (int) ($_POST['affiliate_id'] ?? 0);
        
        // Coleta dados permitidos
        $data = [];
        $allowed = [
            'status', 'commission_rate_coupon', 'commission_rate_link',
            'commission_rate_l2', 'commission_rate_l3', 'customer_discount',
            'cookie_days', 'guruja_rule', 'can_self_refer', 'admin_notes',
        ];
        
        // Campos numéricos que devem ser NULL quando vazios (para usar padrão global)
        $numeric_nullable = [
            'commission_rate_coupon', 'commission_rate_link',
            'commission_rate_l2', 'commission_rate_l3', 
            'customer_discount', 'cookie_days',
            // Auto-referência: "" (Usar padrão) => NULL; "0"/"1" => valor explícito
            'can_self_refer',
        ];
        
        // Campos de checkbox "zerar"
        $zero_fields = [
            'zero_commission_rate_coupon', 'zero_commission_rate_link',
            'zero_commission_rate_l2', 'zero_commission_rate_l3',
            'zero_customer_discount',
        ];
        
        foreach ($allowed as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                
                // Campos numéricos vazios devem ser NULL para usar valor padrão
                if (in_array($field, $numeric_nullable) && $value === '') {
                    $data[$field] = null;
                } else {
                    $data[$field] = $value;
                }
            }
        }
        
        // Processa checkboxes de zerar (se não enviado = 0)
        foreach ($zero_fields as $field) {
            $data[$field] = isset($_POST[$field]) ? 1 : 0;
        }
        
        // Processa alteração de cupom
        $new_coupon_code = isset($_POST['coupon_code']) ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['coupon_code'])) : '';
        $original_coupon_code = isset($_POST['original_coupon_code']) ? sanitize_text_field($_POST['original_coupon_code']) : '';
        
        if (!empty($new_coupon_code) && $new_coupon_code !== $original_coupon_code) {
            // Valida formato do cupom
            if (strlen($new_coupon_code) < 3 || strlen($new_coupon_code) > 20) {
                wp_send_json_error(['message' => __('Código do cupom deve ter entre 3 e 20 caracteres.', 'lab-resumos-parceiros')]);
            }
            
            // Verifica se o cupom já existe em outro afiliado
            global $wpdb;
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}lrp_affiliates WHERE coupon_code = %s AND id != %d",
                $new_coupon_code,
                $affiliate_id
            ));
            
            if ($existing) {
                wp_send_json_error(['message' => __('Este código de cupom já está em uso por outro afiliado.', 'lab-resumos-parceiros')]);
            }
            
            // Verifica se existe um cupom WooCommerce com este código (que não seja do afiliado)
            $wc_coupon = new WC_Coupon($new_coupon_code);
            if ($wc_coupon->get_id() > 0) {
                $coupon_affiliate_id = $wc_coupon->get_meta('_lrp_affiliate_id');
                if (empty($coupon_affiliate_id) || (int) $coupon_affiliate_id !== $affiliate_id) {
                    wp_send_json_error(['message' => __('Já existe um cupom WooCommerce com este código.', 'lab-resumos-parceiros')]);
                }
            }
            
            $data['coupon_code'] = $new_coupon_code;
            $data['original_coupon_code'] = $original_coupon_code;
        }
        
        $result = LRP_Admin_Affiliates::update($affiliate_id, $data);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Erro ao atualizar.', 'lab-resumos-parceiros')]);
        }
        
        wp_send_json_success(['message' => __('Afiliado atualizado!', 'lab-resumos-parceiros')]);
    }

    /**
     * Atualiza dados de faturamento e periodicidade do afiliado
     * 
     * @since 1.4.0
     */
    public function update_affiliate_billing() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_affiliates')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $affiliate_id = (int) ($_POST['affiliate_id'] ?? 0);
        
        if (!$affiliate_id) {
            wp_send_json_error(['message' => __('Afiliado inválido.', 'lab-resumos-parceiros')]);
        }
        
        $affiliate = new LRP_Affiliate($affiliate_id);
        
        if (!$affiliate->exists()) {
            wp_send_json_error(['message' => __('Afiliado não encontrado.', 'lab-resumos-parceiros')]);
        }
        
        $data = [];
        
        // Tipo de faturamento
        if (isset($_POST['billing_type'])) {
            $billing_type = sanitize_key($_POST['billing_type']);
            if (in_array($billing_type, ['pj', 'rpa'])) {
                $data['billing_type'] = $billing_type;
            }
        }
        
        // Dados PJ
        if (isset($_POST['company_cnpj'])) {
            $data['company_cnpj'] = preg_replace('/[^0-9]/', '', $_POST['company_cnpj']);
        }
        
        if (isset($_POST['company_name'])) {
            $data['company_name'] = sanitize_text_field($_POST['company_name']);
        }
        
        // Dados RPA
        if (isset($_POST['cpf'])) {
            $data['cpf'] = preg_replace('/[^0-9]/', '', $_POST['cpf']);
        }
        
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
        
        if (isset($_POST['rpa_service_description'])) {
            $data['rpa_service_description'] = sanitize_textarea_field($_POST['rpa_service_description']);
        }
        
        // Periodicidade
        if (isset($_POST['payment_period_months'])) {
            $data['payment_period_months'] = (int) $_POST['payment_period_months'];
        }
        
        if (isset($_POST['next_payment_date']) && !empty($_POST['next_payment_date'])) {
            $data['next_payment_date'] = sanitize_text_field($_POST['next_payment_date']);
        }
        
        $result = $affiliate->update($data);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Erro ao atualizar.', 'lab-resumos-parceiros')]);
        }
        
        // Log da atividade
        $affiliate->log_activity('billing_updated_by_admin', [
            'admin_id' => get_current_user_id(),
            'changes'  => array_keys($data),
        ]);
        
        wp_send_json_success(['message' => __('Dados de faturamento atualizados!', 'lab-resumos-parceiros')]);
    }

    /**
     * Adia fechamento pelo admin
     * 
     * @since 1.4.0
     */
    public function admin_defer_closing() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_payments')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $closing_id = (int) ($_POST['closing_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        
        if (!$closing_id) {
            wp_send_json_error(['message' => __('Fechamento inválido.', 'lab-resumos-parceiros')]);
        }
        
        global $wpdb;
        
        $closing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lrp_closings WHERE id = %d",
            $closing_id
        ));
        
        if (!$closing) {
            wp_send_json_error(['message' => __('Fechamento não encontrado.', 'lab-resumos-parceiros')]);
        }
        
        // Verifica se pode ser adiado
        if (!in_array($closing->status, ['awaiting_invoice', 'awaiting_rpa', 'invoice_received'])) {
            wp_send_json_error(['message' => __('Este fechamento não pode ser adiado.', 'lab-resumos-parceiros')]);
        }
        
        // Executa o adiamento
        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_closings',
            [
                'deferred'              => 1,
                'deferred_at'           => current_time('mysql'),
                'deferred_by'           => get_current_user_id(),
                'deferred_reason'       => $reason ?: __('Adiado pelo administrador', 'lab-resumos-parceiros'),
                'original_period_month' => $closing->period_month,
                'original_period_year'  => $closing->period_year,
                'status'                => 'closed',
            ],
            ['id' => $closing_id]
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => __('Erro ao adiar fechamento.', 'lab-resumos-parceiros')]);
        }
        
        // Atualiza próxima data de pagamento do afiliado
        $affiliate = new LRP_Affiliate($closing->affiliate_id);
        $affiliate->update_next_payment_date();
        
        // Log
        $affiliate->log_activity('closing_deferred_by_admin', [
            'closing_id' => $closing_id,
            'period'     => sprintf('%02d/%d', $closing->period_month, $closing->period_year),
            'amount'     => $closing->total_commissions,
            'reason'     => $reason,
            'admin_id'   => get_current_user_id(),
        ]);
        
        // Notifica o afiliado por email
        do_action('lrp_closing_deferred_by_admin', $closing, $affiliate, $reason);
        
        wp_send_json_success([
            'message' => __('Fechamento adiado com sucesso! O afiliado será notificado.', 'lab-resumos-parceiros'),
        ]);
    }

    /**
     * Aprova comissões em massa
     */
    public function bulk_approve_commissions() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_commissions')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $ids = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : [];
        
        $count = LRP_Admin_Commissions::approve_bulk($ids);
        
        wp_send_json_success([
            'message' => sprintf(__('%d comissões aprovadas.', 'lab-resumos-parceiros'), $count),
        ]);
    }

    /**
     * Aprova NF
     */
    public function approve_invoice() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_invoices')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $closing_id = (int) ($_POST['closing_id'] ?? 0);
        
        $result = LRP_Admin_Payouts::approve_invoice($closing_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => __('NF aprovada!', 'lab-resumos-parceiros')]);
    }

    /**
     * Aprova RPA (v1.7.1)
     */
    public function approve_rpa() {
        $this->verify_admin_nonce();

        if (!current_user_can('lrp_manage_invoices')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }

        $closing_id = (int) ($_POST['closing_id'] ?? 0);

        $result = LRP_Admin_Payouts::approve_rpa($closing_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('RPA aprovado! Pronto para pagamento.', 'lab-resumos-parceiros')]);
    }

    /**
     * Rejeita NF
     */
    public function reject_invoice() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_invoices')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $closing_id = (int) ($_POST['closing_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        
        if (empty($reason)) {
            wp_send_json_error(['message' => __('Informe o motivo da rejeição.', 'lab-resumos-parceiros')]);
        }
        
        $result = LRP_Admin_Payouts::reject_invoice($closing_id, $reason);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Erro ao rejeitar.', 'lab-resumos-parceiros')]);
        }
        
        wp_send_json_success(['message' => __('NF rejeitada. Afiliado será notificado.', 'lab-resumos-parceiros')]);
    }

    /**
     * Confirma pagamento
     */
    public function confirm_payment() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_payments')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $closing_id = (int) ($_POST['closing_id'] ?? 0);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        if (empty($_FILES['proof_file'])) {
            wp_send_json_error(['message' => __('Anexe o comprovante.', 'lab-resumos-parceiros')]);
        }
        
        $result = LRP_Admin_Payouts::confirm_payment($closing_id, $_FILES['proof_file'], $notes);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => __('Pagamento confirmado!', 'lab-resumos-parceiros')]);
    }

    /**
     * Salva material
     */
    public function save_material() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_settings')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $data = [
            'id'            => (int) ($_POST['id'] ?? 0),
            'title'         => sanitize_text_field($_POST['title'] ?? ''),
            'description'   => sanitize_textarea_field($_POST['description'] ?? ''),
            'type'          => sanitize_key($_POST['type'] ?? 'text'),
            'file_url'      => esc_url_raw($_POST['file_url'] ?? ''),
            'content'       => wp_kses_post($_POST['content'] ?? ''),
            'category'      => sanitize_key($_POST['category'] ?? 'geral'),
            'display_order' => (int) ($_POST['display_order'] ?? 0),
            'is_active'     => isset($_POST['is_active']),
        ];
        
        $result = LRP_Admin_Materials::save($data);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Erro ao salvar.', 'lab-resumos-parceiros')]);
        }
        
        wp_send_json_success([
            'message' => __('Material salvo!', 'lab-resumos-parceiros'),
            'id'      => $result,
        ]);
    }

    /**
     * Exclui material
     */
    public function delete_material() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_settings')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $id = (int) ($_POST['id'] ?? 0);
        
        if (LRP_Admin_Materials::delete($id)) {
            wp_send_json_success(['message' => __('Material excluído.', 'lab-resumos-parceiros')]);
        }
        
        wp_send_json_error(['message' => __('Erro ao excluir.', 'lab-resumos-parceiros')]);
    }

    /**
     * Salva FAQ
     */
    public function save_faq() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_settings')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $data = [
            'id'            => (int) ($_POST['id'] ?? 0),
            'question'      => sanitize_text_field($_POST['question'] ?? ''),
            'answer'        => wp_kses_post($_POST['answer'] ?? ''),
            'category'      => sanitize_key($_POST['category'] ?? 'geral'),
            'display_order' => (int) ($_POST['display_order'] ?? 0),
            'is_active'     => isset($_POST['is_active']),
        ];
        
        $result = LRP_Admin_FAQ::save($data);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Erro ao salvar.', 'lab-resumos-parceiros')]);
        }
        
        wp_send_json_success([
            'message' => __('FAQ salva!', 'lab-resumos-parceiros'),
            'id'      => $result,
        ]);
    }

    /**
     * Exclui FAQ
     */
    public function delete_faq() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_settings')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $id = (int) ($_POST['id'] ?? 0);
        
        if (LRP_Admin_FAQ::delete($id)) {
            wp_send_json_success(['message' => __('FAQ excluída.', 'lab-resumos-parceiros')]);
        }
        
        wp_send_json_error(['message' => __('Erro ao excluir.', 'lab-resumos-parceiros')]);
    }

    /**
     * Exporta CSV
     */
    public function export_csv() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_view_reports')) {
            wp_die(__('Sem permissão.', 'lab-resumos-parceiros'));
        }
        
        $type = sanitize_key($_GET['type'] ?? 'affiliates');
        
        // Prepara datas com horário para filtros BETWEEN/range funcionarem corretamente
        $start_date = sanitize_text_field($_GET['start_date'] ?? '');
        $end_date = sanitize_text_field($_GET['end_date'] ?? '');
        
        $filters = [
            'status'     => sanitize_key($_GET['status'] ?? ''),
            'start_date' => $start_date ? $start_date . ' 00:00:00' : '',
            'end_date'   => $end_date ? $end_date . ' 23:59:59' : '',
        ];
        
        switch ($type) {
            case 'commissions':
                LRP_Admin_Commissions::export_csv($filters);
                break;
            case 'payments':
                LRP_Admin_Payouts::export_csv($filters);
                break;
            default:
                LRP_Admin_Affiliates::export_csv($filters);
        }
    }

    /**
     * Obtém dados para gráficos
     */
    public function get_chart_data() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_view_reports')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $type = sanitize_key($_GET['type'] ?? 'sales');
        $start_date = sanitize_text_field($_GET['start_date'] ?? date('Y-m-01'));
        $end_date = sanitize_text_field($_GET['end_date'] ?? date('Y-m-t'));
        
        // Adiciona horário para incluir todo o dia nas queries BETWEEN
        $start_date = $start_date . ' 00:00:00';
        $end_date = $end_date . ' 23:59:59';
        
        switch ($type) {
            case 'sales':
                $data = LRP_Admin_Reports::get_sales_by_day($start_date, $end_date);
                break;
            case 'attribution':
                $data = LRP_Admin_Reports::get_attribution_breakdown($start_date, $end_date);
                break;
            case 'network':
                $data = LRP_Admin_Reports::get_network_report($start_date, $end_date);
                break;
            default:
                $data = [];
        }
        
        wp_send_json_success(['data' => $data]);
    }

    /**
     * Adiciona restrição de produto
     * 
     * @since 1.3.0
     */
    public function add_product_restriction() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_affiliates')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $data = [
            'affiliate_id'     => (int) ($_POST['affiliate_id'] ?? 0),
            'restriction_mode' => sanitize_key($_POST['restriction_mode'] ?? 'blacklist'),
            'item_type'        => sanitize_key($_POST['item_type'] ?? 'product'),
            'item_id'          => (int) ($_POST['item_id'] ?? 0),
            'start_date'       => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date'         => sanitize_text_field($_POST['end_date'] ?? ''),
            'reason'           => sanitize_textarea_field($_POST['reason'] ?? ''),
        ];
        
        $restriction_handler = LRP_Product_Restriction::instance();
        $result = $restriction_handler->add_restriction($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success([
            'message' => __('Restrição adicionada com sucesso!', 'lab-resumos-parceiros'),
            'id'      => $result,
        ]);
    }

    /**
     * Remove restrição de produto
     * 
     * @since 1.3.0
     */
    public function remove_product_restriction() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_affiliates')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $restriction_id = (int) ($_POST['restriction_id'] ?? 0);
        
        if (!$restriction_id) {
            wp_send_json_error(['message' => __('ID da restrição inválido.', 'lab-resumos-parceiros')]);
        }
        
        $restriction_handler = LRP_Product_Restriction::instance();
        $result = $restriction_handler->remove_restriction($restriction_id);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Erro ao remover restrição.', 'lab-resumos-parceiros')]);
        }
        
        wp_send_json_success(['message' => __('Restrição removida com sucesso!', 'lab-resumos-parceiros')]);
    }

    /**
     * Busca produtos ou categorias para o select
     * 
     * @since 1.3.0
     */
    public function search_items() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_affiliates')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'lab-resumos-parceiros')]);
        }
        
        $search = sanitize_text_field($_GET['search'] ?? $_POST['search'] ?? '');
        $type = sanitize_key($_GET['type'] ?? $_POST['type'] ?? 'product');
        
        if (strlen($search) < 2) {
            wp_send_json_success([]);
        }
        
        $results = [];
        
        if ($type === 'product') {
            // Busca produtos
            $products = wc_get_products([
                's'        => $search,
                'limit'    => 20,
                'status'   => 'publish',
                'orderby'  => 'title',
                'order'    => 'ASC',
            ]);
            
            foreach ($products as $product) {
                $results[] = [
                    'id'   => $product->get_id(),
                    'text' => $product->get_name() . ' (ID: ' . $product->get_id() . ')',
                ];
            }
        } else {
            // Busca categorias
            $categories = get_terms([
                'taxonomy'   => 'product_cat',
                'search'     => $search,
                'number'     => 20,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]);
            
            if (!is_wp_error($categories)) {
                foreach ($categories as $category) {
                    $results[] = [
                        'id'   => $category->term_id,
                        'text' => $category->name . ' (' . $category->count . ' produtos)',
                    ];
                }
            }
        }
        
        wp_send_json_success($results);
    }

    /**
     * Cria um novo ajuste manual
     * 
     * @since 1.4.0
     */
    public function create_adjustment() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_commissions')) {
            wp_send_json_error(__('Sem permissão.', 'lab-resumos-parceiros'));
        }
        
        $affiliate_id = (int) ($_POST['affiliate_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        
        if (!$affiliate_id) {
            wp_send_json_error(__('Selecione um afiliado.', 'lab-resumos-parceiros'));
        }
        
        $result = LRP_Adjustment::create($affiliate_id, $amount, $reason);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(['message' => __('Ajuste criado com sucesso!', 'lab-resumos-parceiros')]);
    }

    /**
     * Cancela um ajuste pendente
     * 
     * @since 1.4.0
     */
    public function cancel_adjustment() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_commissions')) {
            wp_send_json_error(__('Sem permissão.', 'lab-resumos-parceiros'));
        }
        
        $adjustment_id = (int) ($_POST['adjustment_id'] ?? 0);
        
        if (!$adjustment_id) {
            wp_send_json_error(__('Ajuste inválido.', 'lab-resumos-parceiros'));
        }
        
        $result = LRP_Adjustment::cancel($adjustment_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(['message' => __('Ajuste cancelado com sucesso!', 'lab-resumos-parceiros')]);
    }

    /**
     * Executa fechamento mensal manualmente
     * 
     * @since 1.8.0
     */
    public function run_manual_closing() {
        $this->verify_admin_nonce();
        
        if (!current_user_can('lrp_manage_settings')) {
            wp_send_json_error(['message' => __('Sem permissão. Apenas administradores podem executar fechamentos.', 'lab-resumos-parceiros')]);
        }
        
        $period_month = (int) ($_POST['period_month'] ?? 0);
        $period_year = (int) ($_POST['period_year'] ?? 0);
        
        if ($period_month < 1 || $period_month > 12) {
            wp_send_json_error(['message' => __('Mês inválido.', 'lab-resumos-parceiros')]);
        }
        
        if ($period_year < 2020 || $period_year > (int) date('Y') + 1) {
            wp_send_json_error(['message' => __('Ano inválido.', 'lab-resumos-parceiros')]);
        }
        
        // Não permite fechar mês atual ou futuro
        $current_month = (int) date('n');
        $current_year = (int) date('Y');
        if ($period_year > $current_year || ($period_year === $current_year && $period_month >= $current_month)) {
            wp_send_json_error(['message' => __('Não é possível fechar o mês atual ou meses futuros.', 'lab-resumos-parceiros')]);
        }
        
        // Opção de reprocessar fechamentos "acumulados" (closed) com regras atuais
        $force_reprocess = !empty($_POST['force_reprocess']);
        
        try {
            $result = LRP_Closing::run_monthly_closing($period_month, $period_year, $force_reprocess);
            
            lrp_log('Fechamento manual executado', [
                'period'         => sprintf('%02d/%d', $period_month, $period_year),
                'by'             => get_current_user_id(),
                'force_reprocess'=> $force_reprocess,
                'result'         => $result,
            ]);
            
            $reprocessed = isset($result['reprocessed']) ? $result['reprocessed'] : 0;
            $msg_parts = [];
            if ($result['processed'] > 0) {
                $msg_parts[] = sprintf('%d novos processados', $result['processed']);
            }
            if ($reprocessed > 0) {
                $msg_parts[] = sprintf('%d reavaliados (acumulado → pagamento)', $reprocessed);
            }
            if ($result['errors'] > 0) {
                $msg_parts[] = sprintf('%d erros', $result['errors']);
            }
            if (empty($msg_parts)) {
                $msg_parts[] = 'nenhuma alteração necessária';
            }
            
            wp_send_json_success([
                'message' => sprintf(
                    __('Fechamento de %02d/%d executado! %s.', 'lab-resumos-parceiros'),
                    $result['period_month'],
                    $result['period_year'],
                    implode(', ', $msg_parts)
                ),
                'result' => $result,
            ]);
        } catch (Exception $e) {
            lrp_log('Erro no fechamento manual', [
                'error' => $e->getMessage(),
                'by'    => get_current_user_id(),
            ], 'error');
            
            wp_send_json_error(['message' => sprintf(
                __('Erro ao executar fechamento: %s', 'lab-resumos-parceiros'),
                $e->getMessage()
            )]);
        }
    }

    /**
     * Download seguro de arquivos (NF e comprovantes de pagamento)
     * 
     * Serve os arquivos protegidos por .htaccess verificando permissões.
     * Uso: admin-ajax.php?action=lrp_download_file&nonce=X&type=invoice|proof&closing_id=Y
     */
    public function download_file() {
        // Verifica nonce
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'lrp_admin_nonce')) {
            wp_die(__('Erro de segurança.', 'lab-resumos-parceiros'), 403);
        }
        
        $type = sanitize_key($_GET['type'] ?? '');
        $closing_id = (int) ($_GET['closing_id'] ?? 0);
        
        if (!$closing_id || !in_array($type, ['invoice', 'proof'])) {
            wp_die(__('Parâmetros inválidos.', 'lab-resumos-parceiros'), 400);
        }
        
        $closing = LRP_Closing::get($closing_id);
        if (!$closing) {
            wp_die(__('Fechamento não encontrado.', 'lab-resumos-parceiros'), 404);
        }
        
        // Verifica permissão: contador, admin, ou o próprio afiliado
        $can_access = current_user_can('lrp_manage_invoices') || current_user_can('lrp_manage_affiliates');
        
        if (!$can_access) {
            // Verifica se é o próprio afiliado
            $affiliate = new LRP_Affiliate($closing->affiliate_id);
            if ($affiliate->get_user_id() === get_current_user_id()) {
                $can_access = true;
            }
        }
        
        if (!$can_access) {
            wp_die(__('Sem permissão.', 'lab-resumos-parceiros'), 403);
        }
        
        // Determina qual arquivo servir
        $relative_path = '';
        if ($type === 'invoice') {
            $relative_path = $closing->invoice_file ?? '';
        } elseif ($type === 'proof') {
            $relative_path = $closing->payment_proof_file ?? '';
        }
        
        if (empty($relative_path)) {
            wp_die(__('Arquivo não encontrado.', 'lab-resumos-parceiros'), 404);
        }
        
        $upload_dir = wp_upload_dir();
        $file_path = wp_normalize_path($upload_dir['basedir'] . $relative_path);
        
        // Segurança: garante que o arquivo está dentro do diretório de uploads
        if (strpos($file_path, wp_normalize_path($upload_dir['basedir'])) !== 0 || !file_exists($file_path)) {
            wp_die(__('Arquivo não encontrado.', 'lab-resumos-parceiros'), 404);
        }
        
        // Determina content-type
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $content_types = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
        ];
        $content_type = $content_types[$ext] ?? 'application/octet-stream';
        
        // Serve o arquivo
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private, max-age=3600');
        
        readfile($file_path);
        exit;
    }

    /**
     * Verifica nonce do admin
     */
    private function verify_admin_nonce() {
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', 'lrp_admin_nonce')) {
            wp_send_json_error(['message' => __('Erro de segurança.', 'lab-resumos-parceiros')]);
        }
    }
}

