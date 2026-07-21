<?php
/**
 * Área do Contador
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Accountant
 * 
 * Gerencia a área exclusiva do contador.
 */
class LRP_Accountant {

    /**
     * Instância única
     *
     * @var LRP_Accountant|null
     */
    private static $instance = null;

    /**
     * Retorna instância única
     *
     * @return LRP_Accountant
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
        // Adiciona menu para contador
        add_action('admin_menu', [$this, 'add_menu']);
    }

    /**
     * Adiciona menu do contador
     */
    public function add_menu() {
        // Verifica se é contador
        $user = wp_get_current_user();
        
        if (!in_array('lrp_accountant', (array) $user->roles) && 
            !current_user_can('lrp_manage_invoices')) {
            return;
        }
        
        add_menu_page(
            __('Financeiro Parceiros', 'lab-resumos-parceiros'),
            __('Financeiro Parceiros', 'lab-resumos-parceiros'),
            'lrp_manage_invoices',
            'lrp-accountant',
            [$this, 'render_dashboard'],
            'dashicons-money-alt',
            57
        );
        
        add_submenu_page(
            'lrp-accountant',
            __('Dashboard', 'lab-resumos-parceiros'),
            __('Dashboard', 'lab-resumos-parceiros'),
            'lrp_manage_invoices',
            'lrp-accountant',
            [$this, 'render_dashboard']
        );
        
        add_submenu_page(
            'lrp-accountant',
            __('Notas Fiscais', 'lab-resumos-parceiros'),
            __('Notas Fiscais', 'lab-resumos-parceiros'),
            'lrp_manage_invoices',
            'lrp-accountant-invoices',
            [$this, 'render_invoices']
        );
        
        add_submenu_page(
            'lrp-accountant',
            __('Pagamentos', 'lab-resumos-parceiros'),
            __('Pagamentos', 'lab-resumos-parceiros'),
            'lrp_manage_payments',
            'lrp-accountant-payments',
            [$this, 'render_payments']
        );
    }

    /**
     * Renderiza dashboard do contador
     */
    public function render_dashboard() {
        $stats = $this->get_dashboard_stats();
        include LRP_PLUGIN_DIR . 'accountant/partials/dashboard.php';
    }

    /**
     * Renderiza lista de NFs
     */
    public function render_invoices() {
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
        
        if ($action === 'view' && isset($_GET['id'])) {
            $closing = LRP_Closing::get((int) $_GET['id']);
            $affiliate = new LRP_Affiliate($closing->affiliate_id);
            include LRP_PLUGIN_DIR . 'accountant/partials/invoice-view.php';
        } else {
            $pending = LRP_Closing::get_by_status('invoice_received');
            $pending_rpa = LRP_Closing::get_by_status('awaiting_rpa');
            $approved = LRP_Closing::get_by_status('approved', ['limit' => 20]);
            include LRP_PLUGIN_DIR . 'accountant/partials/invoices-list.php';
        }
    }

    /**
     * Renderiza pagamentos
     */
    public function render_payments() {
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
        
        if ($action === 'confirm' && isset($_GET['id'])) {
            $closing = LRP_Closing::get((int) $_GET['id']);
            $affiliate = new LRP_Affiliate($closing->affiliate_id);
            include LRP_PLUGIN_DIR . 'accountant/partials/payment-confirm.php';
        } else {
            $pending = LRP_Closing::get_by_status('approved');
            $history = LRP_Payout::get_payment_history(['limit' => 50]);
            include LRP_PLUGIN_DIR . 'accountant/partials/payments-list.php';
        }
    }

    /**
     * Obtém estatísticas para dashboard
     *
     * @return array
     */
    private function get_dashboard_stats() {
        $pending_invoices = LRP_Closing::count_by_status('invoice_received');
        $pending_rpa = LRP_Closing::count_by_status('awaiting_rpa');
        $pending_payments = LRP_Closing::count_by_status('approved');
        $total_pending_amount = LRP_Closing::sum_pending_amount();
        $month_stats = LRP_Payout::get_month_stats();
        
        return [
            'pending_invoices'      => $pending_invoices,
            'pending_rpa'           => $pending_rpa,
            'pending_payments'      => $pending_payments,
            'total_pending_amount'  => $total_pending_amount,
            'month_payments'        => $month_stats['total_payments'],
            'month_paid_amount'     => $month_stats['total_amount'],
        ];
    }

    /**
     * Verifica se usuário é contador
     *
     * @param int|null $user_id
     * @return bool
     */
    public static function is_accountant($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        return in_array('lrp_accountant', (array) $user->roles) || 
               user_can($user_id, 'lrp_manage_invoices');
    }
}

