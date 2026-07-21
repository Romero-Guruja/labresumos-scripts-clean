<?php
/**
 * Classe principal do Admin
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Admin
 * 
 * Gerencia menu, páginas e hooks do admin.
 */
class LRP_Admin {

    /**
     * Construtor
     */
    public function __construct() {
        // Inicializado pelo Loader
    }

    /**
     * Adiciona menu do admin
     */
    public function add_menu() {
        // Menu principal
        add_menu_page(
            __('Parceiros', 'lab-resumos-parceiros'),
            __('Parceiros', 'lab-resumos-parceiros'),
            'lrp_manage_affiliates',
            'lrp-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-groups',
            56
        );
        
        // Dashboard
        add_submenu_page(
            'lrp-dashboard',
            __('Dashboard', 'lab-resumos-parceiros'),
            __('Dashboard', 'lab-resumos-parceiros'),
            'lrp_manage_affiliates',
            'lrp-dashboard',
            [$this, 'render_dashboard']
        );
        
        // Afiliados
        add_submenu_page(
            'lrp-dashboard',
            __('Afiliados', 'lab-resumos-parceiros'),
            __('Afiliados', 'lab-resumos-parceiros'),
            'lrp_manage_affiliates',
            'lrp-affiliates',
            [$this, 'render_affiliates']
        );
        
        // Comissões
        add_submenu_page(
            'lrp-dashboard',
            __('Comissões', 'lab-resumos-parceiros'),
            __('Comissões', 'lab-resumos-parceiros'),
            'lrp_manage_commissions',
            'lrp-commissions',
            [$this, 'render_commissions']
        );
        
        // Pagamentos
        add_submenu_page(
            'lrp-dashboard',
            __('Pagamentos', 'lab-resumos-parceiros'),
            __('Pagamentos', 'lab-resumos-parceiros'),
            'lrp_manage_invoices',
            'lrp-payouts',
            [$this, 'render_payouts']
        );
        
        // Fechamento Manual (v1.8.0)
        add_submenu_page(
            'lrp-dashboard',
            __('Fechamento', 'lab-resumos-parceiros'),
            __('Fechamento', 'lab-resumos-parceiros'),
            'lrp_manage_settings',
            'lrp-closing',
            [$this, 'render_closing']
        );
        
        // Relatórios
        add_submenu_page(
            'lrp-dashboard',
            __('Relatórios', 'lab-resumos-parceiros'),
            __('Relatórios', 'lab-resumos-parceiros'),
            'lrp_view_reports',
            'lrp-reports',
            [$this, 'render_reports']
        );
        
        // Materiais
        add_submenu_page(
            'lrp-dashboard',
            __('Materiais', 'lab-resumos-parceiros'),
            __('Materiais', 'lab-resumos-parceiros'),
            'lrp_manage_settings',
            'lrp-materials',
            [$this, 'render_materials']
        );
        
        // FAQ
        add_submenu_page(
            'lrp-dashboard',
            __('FAQ', 'lab-resumos-parceiros'),
            __('FAQ', 'lab-resumos-parceiros'),
            'lrp_manage_settings',
            'lrp-faq',
            [$this, 'render_faq']
        );
        
        // Ajustes
        add_submenu_page(
            'lrp-dashboard',
            __('Ajustes', 'lab-resumos-parceiros'),
            __('Ajustes', 'lab-resumos-parceiros'),
            'lrp_manage_commissions',
            'lrp-adjustments',
            [$this, 'render_adjustments']
        );
        
        // Termos de Afiliação (v1.6.0)
        add_submenu_page(
            'lrp-dashboard',
            __('Termos', 'lab-resumos-parceiros'),
            __('Termos', 'lab-resumos-parceiros'),
            'lrp_manage_settings',
            'lrp-terms',
            [$this, 'render_terms']
        );
        
        // Configurações
        add_submenu_page(
            'lrp-dashboard',
            __('Configurações', 'lab-resumos-parceiros'),
            __('Configurações', 'lab-resumos-parceiros'),
            'lrp_manage_settings',
            'lrp-settings',
            [$this, 'render_settings']
        );
    }

    /**
     * Enfileira estilos do admin
     *
     * @param string $hook
     */
    public function enqueue_styles($hook) {
        if (strpos($hook, 'lrp-') === false) {
            return;
        }
        
        wp_enqueue_style(
            'lrp-admin',
            LRP_PLUGIN_URL . 'admin/css/lrp-admin.css',
            [],
            LRP_VERSION
        );
    }

    /**
     * Enfileira scripts do admin
     *
     * @param string $hook
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'lrp-') === false) {
            return;
        }
        
        wp_enqueue_script(
            'lrp-admin',
            LRP_PLUGIN_URL . 'admin/js/lrp-admin.js',
            ['jquery'],
            LRP_VERSION,
            true
        );
        
        wp_localize_script('lrp-admin', 'lrp_admin', [
            'ajax_url'       => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('lrp_admin_nonce'),
            'confirm_delete' => __('Tem certeza que deseja excluir?', 'lab-resumos-parceiros'),
            'confirm_approve'=> __('Aprovar este afiliado?', 'lab-resumos-parceiros'),
            'confirm_reject' => __('Rejeitar este afiliado?', 'lab-resumos-parceiros'),
        ]);
        
        // Chart.js para relatórios
        if (strpos($hook, 'lrp-reports') !== false || strpos($hook, 'lrp-dashboard') !== false) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                [],
                '4.4.1',
                true
            );
        }
        
        // Select2 para página de afiliados (restrições de produtos)
        if (strpos($hook, 'lrp-affiliates') !== false) {
            wp_enqueue_style(
                'select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
                [],
                '4.1.0'
            );
            wp_enqueue_script(
                'select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                ['jquery'],
                '4.1.0',
                true
            );
        }
    }

    /**
     * Registra configurações
     */
    public function register_settings() {
        register_setting('lrp_settings', 'lrp_settings', [$this, 'sanitize_settings']);
    }

    /**
     * Sanitiza configurações
     *
     * @param array $input
     * @return array
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        
        $sanitized['enabled'] = isset($input['enabled']);
        $sanitized['default_commission_coupon'] = floatval($input['default_commission_coupon'] ?? 10);
        $sanitized['default_commission_link'] = floatval($input['default_commission_link'] ?? 5);
        $sanitized['default_commission_l2'] = floatval($input['default_commission_l2'] ?? 3);
        $sanitized['default_commission_l3'] = floatval($input['default_commission_l3'] ?? 1);
        $sanitized['default_cookie_days'] = intval($input['default_cookie_days'] ?? 60);
        $sanitized['default_customer_discount'] = floatval($input['default_customer_discount'] ?? 20);
        $sanitized['minimum_payout'] = floatval($input['minimum_payout'] ?? 200);
        $sanitized['closing_day'] = intval($input['closing_day'] ?? 1);
        $sanitized['default_guruja_rule'] = sanitize_key($input['default_guruja_rule'] ?? 'higher_discount');
        $sanitized['default_can_self_refer'] = isset($input['default_can_self_refer']);
        $sanitized['company_name'] = sanitize_text_field($input['company_name'] ?? '');
        $sanitized['company_cnpj'] = sanitize_text_field($input['company_cnpj'] ?? '');
        $sanitized['company_address'] = sanitize_textarea_field($input['company_address'] ?? '');
        $sanitized['accountant_email'] = sanitize_email($input['accountant_email'] ?? '');
        $sanitized['admin_email'] = sanitize_email($input['admin_email'] ?? '');
        $sanitized['auto_approve'] = isset($input['auto_approve']);
        $sanitized['debug_mode'] = isset($input['debug_mode']);
        
        return $sanitized;
    }

    /**
     * Renderiza Dashboard
     */
    public function render_dashboard() {
        $stats = $this->get_dashboard_stats();
        include LRP_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    /**
     * Renderiza lista de Afiliados
     */
    public function render_affiliates() {
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
        
        switch ($action) {
            case 'edit':
            case 'view':
                $affiliate_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
                $affiliate = new LRP_Affiliate($affiliate_id);
                
                if (!$affiliate->exists()) {
                    wp_die(__('Afiliado não encontrado.', 'lab-resumos-parceiros'));
                }
                
                include LRP_PLUGIN_DIR . 'admin/partials/affiliate-edit.php';
                break;
                
            case 'add':
                include LRP_PLUGIN_DIR . 'admin/partials/affiliate-add.php';
                break;
                
            default:
                $affiliates = $this->get_affiliates_list();
                include LRP_PLUGIN_DIR . 'admin/partials/affiliates-list.php';
        }
    }

    /**
     * Renderiza Comissões
     */
    public function render_commissions() {
        $commissions = $this->get_commissions_list();
        include LRP_PLUGIN_DIR . 'admin/partials/commissions.php';
    }

    /**
     * Renderiza Pagamentos
     */
    public function render_payouts() {
        $pending_invoices = LRP_Closing::get_by_status('invoice_received');
        $pending_payments = LRP_Closing::get_by_status('approved');
        $payment_history = LRP_Payout::get_payment_history(['limit' => 50]);
        
        include LRP_PLUGIN_DIR . 'admin/partials/payouts.php';
    }

    /**
     * Renderiza Relatórios
     */
    public function render_reports() {
        include LRP_PLUGIN_DIR . 'admin/partials/reports.php';
    }

    /**
     * Renderiza Materiais
     */
    public function render_materials() {
        // Enfileira media uploader para upload de arquivos
        wp_enqueue_media();
        include LRP_PLUGIN_DIR . 'admin/partials/materials.php';
    }

    /**
     * Renderiza FAQ
     */
    public function render_faq() {
        include LRP_PLUGIN_DIR . 'admin/partials/faq.php';
    }

    /**
     * Renderiza Configurações
     */
    public function render_settings() {
        $settings = LRP_Settings::instance()->get_all();
        include LRP_PLUGIN_DIR . 'admin/partials/settings.php';
    }

    /**
     * Renderiza Fechamento Manual
     * 
     * @since 1.8.0
     */
    public function render_closing() {
        // Busca último fechamento executado
        global $wpdb;
        $last_closing = $wpdb->get_row(
            "SELECT period_month, period_year, created_at, COUNT(*) as total_closings
             FROM {$wpdb->prefix}lrp_closings
             GROUP BY period_month, period_year
             ORDER BY period_year DESC, period_month DESC
             LIMIT 1"
        );
        
        // Estatísticas do último fechamento
        $closing_stats = null;
        $closing_details = [];
        $accumulated_balances = [];
        if ($last_closing) {
            $closing_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'awaiting_invoice' THEN 1 ELSE 0 END) as awaiting_invoice,
                    SUM(CASE WHEN status = 'awaiting_rpa' THEN 1 ELSE 0 END) as awaiting_rpa,
                    SUM(CASE WHEN status IN ('invoice_received', 'approved') THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                    COALESCE(SUM(total_commissions), 0) as total_commissions
                 FROM {$wpdb->prefix}lrp_closings
                 WHERE period_month = %d AND period_year = %d",
                $last_closing->period_month,
                $last_closing->period_year
            ));

            // Detalhes individuais de cada afiliado no período (v1.7.1)
            $closing_details = $wpdb->get_results($wpdb->prepare(
                "SELECT c.*, a.billing_type, a.user_id, u.display_name as affiliate_name
                 FROM {$wpdb->prefix}lrp_closings c
                 JOIN {$wpdb->prefix}lrp_affiliates a ON c.affiliate_id = a.id
                 JOIN {$wpdb->users} u ON a.user_id = u.ID
                 WHERE c.period_month = %d AND c.period_year = %d
                 ORDER BY FIELD(c.status, 'awaiting_invoice', 'invoice_received', 'awaiting_rpa', 'approved', 'closed', 'paid'), c.total_commissions DESC",
                $last_closing->period_month,
                $last_closing->period_year
            ));

            // Saldo acumulado de períodos anteriores por afiliado (v1.7.1)
            // Inclui todos os status não-pagos (mesma lógica do fechamento mensal)
            $accumulated_balances = $wpdb->get_results($wpdb->prepare(
                "SELECT affiliate_id, COALESCE(SUM(total_commissions), 0) as accumulated
                 FROM {$wpdb->prefix}lrp_closings
                 WHERE status IN ('closed', 'awaiting_invoice', 'awaiting_rpa', 'invoice_received', 'approved', 'rejected')
                 AND (period_year < %d OR (period_year = %d AND period_month < %d))
                 GROUP BY affiliate_id",
                $last_closing->period_year,
                $last_closing->period_year,
                $last_closing->period_month
            ), OBJECT_K);
        }
        
        include LRP_PLUGIN_DIR . 'admin/partials/closing.php';
    }

    /**
     * Renderiza Ajustes
     */
    public function render_adjustments() {
        LRP_Admin_Adjustments::render();
    }

    /**
     * Renderiza Termos de Afiliação
     * 
     * @since 1.6.0
     */
    public function render_terms() {
        include LRP_PLUGIN_DIR . 'admin/partials/terms.php';
    }

    /**
     * Obtém estatísticas do dashboard
     *
     * @return array
     */
    private function get_dashboard_stats() {
        global $wpdb;
        
        // Totais gerais
        $affiliates_total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_affiliates WHERE status = 'active'"
        );
        
        $affiliates_pending = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_affiliates WHERE status = 'pending'"
        );
        
        // Vendas do mês
        $monthly = $wpdb->get_row(
            "SELECT COUNT(*) as sales, COALESCE(SUM(commission_base), 0) as revenue
             FROM {$wpdb->prefix}lrp_referrals
             WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
             AND YEAR(created_at) = YEAR(CURRENT_DATE())"
        );
        
        // Comissões pendentes
        $pending_commissions = $wpdb->get_var(
            "SELECT COALESCE(SUM(commission_amount), 0) FROM {$wpdb->prefix}lrp_commissions WHERE status = 'approved'"
        );
        
        // Top afiliados
        $top_affiliates = $wpdb->get_results(
            "SELECT a.id, u.display_name, a.total_sales, a.total_revenue, a.total_commissions
             FROM {$wpdb->prefix}lrp_affiliates a
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE a.status = 'active'
             ORDER BY a.total_revenue DESC
             LIMIT 5"
        );
        
        // Vendas recentes
        $recent_sales = $wpdb->get_results(
            "SELECT r.*, a.user_id, u.display_name as affiliate_name
             FROM {$wpdb->prefix}lrp_referrals r
             JOIN {$wpdb->prefix}lrp_affiliates a ON r.affiliate_id = a.id
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             ORDER BY r.created_at DESC
             LIMIT 10"
        );
        
        return [
            'affiliates_total'    => (int) $affiliates_total,
            'affiliates_pending'  => (int) $affiliates_pending,
            'monthly_sales'       => (int) $monthly->sales,
            'monthly_revenue'     => (float) $monthly->revenue,
            'pending_commissions' => (float) $pending_commissions,
            'top_affiliates'      => $top_affiliates,
            'recent_sales'        => $recent_sales,
        ];
    }

    /**
     * Obtém lista de afiliados
     *
     * @return array
     */
    private function get_affiliates_list() {
        global $wpdb;
        
        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 20;
        
        $where = "WHERE 1=1";
        $params = [];
        
        if ($status) {
            $where .= " AND a.status = %s";
            $params[] = $status;
        }
        
        if ($search) {
            $where .= " AND (u.display_name LIKE %s OR u.user_email LIKE %s OR a.coupon_code LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_affiliates a
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             $where",
            ...$params
        ));
        
        $params[] = $per_page;
        $params[] = ($paged - 1) * $per_page;
        
        $affiliates = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name, u.user_email
             FROM {$wpdb->prefix}lrp_affiliates a
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             $where
             ORDER BY a.created_at DESC
             LIMIT %d OFFSET %d",
            ...$params
        ));
        
        return [
            'items'      => $affiliates,
            'total'      => (int) $total,
            'pages'      => ceil($total / $per_page),
            'current'    => $paged,
            'per_page'   => $per_page,
        ];
    }

    /**
     * Obtém lista de comissões
     *
     * @return array
     */
    private function get_commissions_list() {
        global $wpdb;
        
        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $affiliate_id = isset($_GET['affiliate_id']) ? (int) $_GET['affiliate_id'] : 0;
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 30;
        
        $where = "WHERE 1=1";
        $params = [];
        
        if ($status) {
            $where .= " AND c.status = %s";
            $params[] = $status;
        }
        
        if ($affiliate_id) {
            $where .= " AND c.affiliate_id = %d";
            $params[] = $affiliate_id;
        }
        
        $count_params = $params;
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_commissions c $where",
            ...$count_params
        ));
        
        $params[] = $per_page;
        $params[] = ($paged - 1) * $per_page;
        
        $commissions = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, r.order_id, r.attribution_type, u.display_name as affiliate_name
             FROM {$wpdb->prefix}lrp_commissions c
             JOIN {$wpdb->prefix}lrp_referrals r ON c.referral_id = r.id
             JOIN {$wpdb->prefix}lrp_affiliates a ON c.affiliate_id = a.id
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             $where
             ORDER BY c.created_at DESC
             LIMIT %d OFFSET %d",
            ...$params
        ));
        
        return [
            'items'    => $commissions,
            'total'    => (int) $total,
            'pages'    => ceil($total / $per_page),
            'current'  => $paged,
        ];
    }
}

