<?php
/**
 * Classe do Dashboard Administrativo
 *
 * @package LR_Recuperacao_Vendas
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LR_Admin_Dashboard
 * Gerencia o dashboard administrativo do plugin
 */
class LR_Admin_Dashboard {

    /**
     * Construtor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Adiciona menu no admin
     */
    public function add_menu() {
        $pending_count = lr_recovery()->manager->count_pending_cases();
        $badge = $pending_count > 0 ? '<span class="awaiting-mod update-plugins count-' . $pending_count . '"><span class="pending-count">' . $pending_count . '</span></span>' : '';

        add_submenu_page(
            'woocommerce',
            __('Recuperação de Vendas', 'lr-recuperacao-vendas'),
            __('Recuperação', 'lr-recuperacao-vendas') . ' ' . $badge,
            'manage_woocommerce',
            'lr-recuperacao-vendas',
            [$this, 'render_page']
        );

        // Submenu para importação
        add_submenu_page(
            'lr-recuperacao-vendas-hidden',
            __('Importar Pedidos', 'lr-recuperacao-vendas'),
            __('Importar Pedidos', 'lr-recuperacao-vendas'),
            'manage_woocommerce',
            'lr-recuperacao-importar',
            [$this, 'render_import_page']
        );
    }

    /**
     * Carrega assets CSS e JS
     * @param string $hook
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'lr-recuperacao-vendas') === false) {
            return;
        }

        wp_enqueue_style(
            'lr-recovery-admin',
            LR_RECOVERY_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            LR_RECOVERY_VERSION
        );

        wp_enqueue_script(
            'lr-recovery-admin',
            LR_RECOVERY_PLUGIN_URL . 'assets/js/admin-script.js',
            ['jquery'],
            LR_RECOVERY_VERSION,
            true
        );

        wp_localize_script('lr-recovery-admin', 'lrRecovery', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lr_recovery_nonce'),
            'i18n' => [
                'confirm_abandon' => __('Tem certeza que deseja marcar este caso como abandonado?', 'lr-recuperacao-vendas'),
                'confirm_complete' => __('Tem certeza que deseja marcar o pedido como concluído?', 'lr-recuperacao-vendas'),
                'loading' => __('Carregando...', 'lr-recuperacao-vendas'),
                'error' => __('Ocorreu um erro. Tente novamente.', 'lr-recuperacao-vendas'),
                'copied' => __('Copiado!', 'lr-recuperacao-vendas'),
            ],
        ]);
    }

    /**
     * Renderiza a página principal
     */
    public function render_page() {
        // Verificar se está visualizando um caso específico
        if (isset($_GET['case'])) {
            $this->render_case_detail(absint($_GET['case']));
            return;
        }

        // Verificar se está na página de importação
        if (isset($_GET['view']) && $_GET['view'] === 'import') {
            $this->render_import_page();
            return;
        }

        // Renderizar dashboard
        $this->render_dashboard();
    }

    /**
     * Renderiza página de importação de pedidos existentes
     */
    public function render_import_page() {
        $manager = lr_recovery()->manager;

        // Processar importação se houver
        if (isset($_POST['lr_import_orders']) && isset($_POST['order_ids'])) {
            check_admin_referer('lr_import_orders_nonce');
            
            $order_ids = array_map('absint', $_POST['order_ids']);
            $imported = 0;
            
            foreach ($order_ids as $order_id) {
                $existing = $manager->get_case_by_order($order_id);
                if (!$existing) {
                    $failure_info = $manager->extract_failure_reason($order_id);
                    if ($manager->create_case($order_id, $failure_info)) {
                        $imported++;
                    }
                }
            }
            
            if ($imported > 0) {
                echo '<div class="notice notice-success"><p>' . 
                    sprintf(
                        _n('%d pedido importado com sucesso.', '%d pedidos importados com sucesso.', $imported, 'lr-recuperacao-vendas'),
                        $imported
                    ) . '</p></div>';
            }
        }

        // Buscar pedidos failed sem caso
        $failed_orders = $this->get_failed_orders_without_case();
        
        include LR_RECOVERY_PLUGIN_DIR . 'templates/import-orders.php';
    }

    /**
     * Obtém pedidos com status failed que não têm caso de recuperação
     * @return array
     */
    public function get_failed_orders_without_case() {
        global $wpdb;
        
        $manager = lr_recovery()->manager;
        
        // Buscar pedidos failed
        $args = [
            'status' => 'failed',
            'limit' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        $orders = wc_get_orders($args);
        $orders_without_case = [];
        
        foreach ($orders as $order) {
            $existing = $manager->get_case_by_order($order->get_id());
            if (!$existing) {
                $orders_without_case[] = $order;
            }
        }
        
        return $orders_without_case;
    }

    /**
     * Renderiza o dashboard principal
     */
    private function render_dashboard() {
        $manager = lr_recovery()->manager;

        // Processar filtros
        $filters = [
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'assigned_to' => isset($_GET['assigned_to']) ? absint($_GET['assigned_to']) : '',
            'failure_type' => isset($_GET['failure_type']) ? sanitize_text_field($_GET['failure_type']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
        ];

        // Paginação
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Obter casos
        $cases = $manager->get_cases(array_merge($filters, [
            'limit' => $per_page,
            'offset' => $offset,
        ]));

        // Obter estatísticas
        $stats = $manager->get_statistics();

        // Obter usuários com capability
        $users = get_users(['capability' => 'manage_woocommerce']);

        include LR_RECOVERY_PLUGIN_DIR . 'templates/dashboard.php';
    }

    /**
     * Renderiza detalhe do caso
     * @param int $order_id
     */
    private function render_case_detail($order_id) {
        $manager = lr_recovery()->manager;
        $autologin = lr_recovery()->autologin;

        $case = $manager->get_case_by_order($order_id);
        
        if (!$case) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Caso não encontrado.', 'lr-recuperacao-vendas') . '</p></div>';
            return;
        }

        $order = wc_get_order($order_id);
        
        if (!$order) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Pedido não encontrado.', 'lr-recuperacao-vendas') . '</p></div>';
            return;
        }

        // Dados do caso
        $checklist = json_decode($case->checklist, true) ?: [];
        $logs = $manager->get_case_logs($case->id);
        $failure_info = $manager->extract_failure_reason($order_id);
        
        // URLs externas
        $external_urls = LR_Recuperacao_Vendas::get_external_urls();

        // Dados do cliente
        $customer = [
            'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'first_name' => $order->get_billing_first_name(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'cellphone' => $order->get_meta('billing_cellphone') ?: $order->get_billing_phone(),
            'cpf' => $order->get_meta('billing_cpf'),
        ];

        // Dados do pedido
        $order_data = [
            'id' => $order_id,
            'total' => $order->get_formatted_order_total(),
            'total_raw' => $order->get_total(),
            'date' => $order->get_date_created()->date_i18n('d/m/Y \à\s H:i'),
            'status' => $order->get_status(),
            'coupon' => '',
            'coupon_discount' => 0,
            'payment_method' => $order->get_payment_method_title(),
            'items' => [],
        ];

        // Cupom
        $coupons = $order->get_coupon_codes();
        if (!empty($coupons)) {
            $order_data['coupon'] = implode(', ', $coupons);
            $order_data['coupon_discount'] = $order->get_discount_total();
        }

        // Itens do pedido
        foreach ($order->get_items() as $item) {
            $order_data['items'][] = [
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => wc_price($item->get_total()),
                'product_id' => $item->get_product_id(),
            ];
        }

        // Responsável atual
        $assigned_user = null;
        if ($case->assigned_to) {
            $assigned_user = get_userdata($case->assigned_to);
        }

        // Usuários disponíveis
        $users = get_users(['capability' => 'manage_woocommerce']);

        // Link WhatsApp
        $whatsapp_url = $autologin->generate_whatsapp_url($order, '');

        include LR_RECOVERY_PLUGIN_DIR . 'templates/case-detail.php';
    }

    /**
     * Obtém label do status formatado
     * @param string $status
     * @return string
     */
    public static function get_status_label($status) {
        $labels = [
            'novo' => __('Novo', 'lr-recuperacao-vendas'),
            'em_atendimento' => __('Em atendimento', 'lr-recuperacao-vendas'),
            'aguardando_cliente' => __('Aguardando cliente', 'lr-recuperacao-vendas'),
            'resolvido' => __('Resolvido', 'lr-recuperacao-vendas'),
            'abandonado' => __('Abandonado', 'lr-recuperacao-vendas'),
        ];

        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * Obtém ícone do status
     * @param string $status
     * @return string
     */
    public static function get_status_icon($status) {
        $icons = [
            'novo' => '🔴',
            'em_atendimento' => '🟡',
            'aguardando_cliente' => '🔵',
            'resolvido' => '🟢',
            'abandonado' => '⚫',
        ];

        return isset($icons[$status]) ? $icons[$status] : '⚪';
    }

    /**
     * Obtém label do tipo de falha
     * @param string $type
     * @return string
     */
    public static function get_failure_type_label($type) {
        $labels = [
            'antifraude' => __('Antifraude', 'lr-recuperacao-vendas'),
            'banco' => __('Banco recusou', 'lr-recuperacao-vendas'),
            'retentativas' => __('Excesso de retentativas', 'lr-recuperacao-vendas'),
            'outro' => __('Outro', 'lr-recuperacao-vendas'),
        ];

        return isset($labels[$type]) ? $labels[$type] : $type;
    }

    /**
     * Obtém ícone do tipo de falha
     * @param string $type
     * @return string
     */
    public static function get_failure_type_icon($type) {
        $icons = [
            'antifraude' => '🛡️',
            'banco' => '🏦',
            'retentativas' => '🔄',
            'outro' => '❓',
        ];

        return isset($icons[$type]) ? $icons[$type] : '❓';
    }
}
