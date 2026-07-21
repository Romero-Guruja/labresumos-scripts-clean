<?php
/**
 * Carregador principal do plugin
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Loader
 * 
 * Responsável por carregar todas as dependências e inicializar os componentes.
 */
class LRP_Loader {

    /**
     * Array de actions registradas
     *
     * @var array
     */
    protected $actions = [];

    /**
     * Array de filters registradas
     *
     * @var array
     */
    protected $filters = [];

    /**
     * Construtor
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_cron_hooks();
    }

    /**
     * Carrega todas as dependências
     */
    private function load_dependencies() {
        // Core
        require_once LRP_PLUGIN_DIR . 'includes/core/class-lrp-settings.php';
        require_once LRP_PLUGIN_DIR . 'includes/core/class-lrp-affiliate.php';
        require_once LRP_PLUGIN_DIR . 'includes/core/class-lrp-commission.php';
        require_once LRP_PLUGIN_DIR . 'includes/core/class-lrp-referral.php';
        require_once LRP_PLUGIN_DIR . 'includes/core/class-lrp-payout.php';
        require_once LRP_PLUGIN_DIR . 'includes/core/class-lrp-logger.php';
        require_once LRP_PLUGIN_DIR . 'includes/core/class-lrp-cron.php';
        require_once LRP_PLUGIN_DIR . 'includes/core/class-lrp-exporter.php';
        require_once LRP_PLUGIN_DIR . 'includes/core/class-lrp-stats-calculator.php';
        require_once LRP_PLUGIN_DIR . 'includes/core/class-lrp-ranking.php';
        require_once LRP_PLUGIN_DIR . 'includes/core/class-lrp-product-restriction.php';
        require_once LRP_PLUGIN_DIR . 'includes/core/class-lrp-activity-calculator.php';
        require_once LRP_PLUGIN_DIR . 'includes/core/class-lrp-terms.php';
        
        // Tracking
        require_once LRP_PLUGIN_DIR . 'includes/tracking/class-lrp-cookie-tracker.php';
        require_once LRP_PLUGIN_DIR . 'includes/tracking/class-lrp-coupon-handler.php';
        require_once LRP_PLUGIN_DIR . 'includes/tracking/class-lrp-attribution.php';
        require_once LRP_PLUGIN_DIR . 'includes/tracking/class-lrp-attribution-reader.php';
        
        // Integrations
        require_once LRP_PLUGIN_DIR . 'includes/integrations/class-lrp-woocommerce.php';
        require_once LRP_PLUGIN_DIR . 'includes/integrations/class-lrp-guruja.php';
        
        // Multi-level
        require_once LRP_PLUGIN_DIR . 'includes/multilevel/class-lrp-network.php';
        
        // Financial
        require_once LRP_PLUGIN_DIR . 'includes/financial/class-lrp-calculator.php';
        require_once LRP_PLUGIN_DIR . 'includes/financial/class-lrp-closing.php';
        require_once LRP_PLUGIN_DIR . 'includes/financial/class-lrp-adjustment.php';
        
        // Emails
        require_once LRP_PLUGIN_DIR . 'includes/emails/class-lrp-email-manager.php';
        
        // AJAX
        require_once LRP_PLUGIN_DIR . 'includes/ajax/class-lrp-ajax-public.php';
        require_once LRP_PLUGIN_DIR . 'includes/ajax/class-lrp-ajax-admin.php';
        
        // Admin
        if (is_admin()) {
            require_once LRP_PLUGIN_DIR . 'admin/class-lrp-admin.php';
            require_once LRP_PLUGIN_DIR . 'admin/class-lrp-admin-affiliates.php';
            require_once LRP_PLUGIN_DIR . 'admin/class-lrp-admin-commissions.php';
            require_once LRP_PLUGIN_DIR . 'admin/class-lrp-admin-payouts.php';
            require_once LRP_PLUGIN_DIR . 'admin/class-lrp-admin-settings.php';
            require_once LRP_PLUGIN_DIR . 'admin/class-lrp-admin-reports.php';
            require_once LRP_PLUGIN_DIR . 'admin/class-lrp-admin-materials.php';
            require_once LRP_PLUGIN_DIR . 'admin/class-lrp-admin-faq.php';
            require_once LRP_PLUGIN_DIR . 'admin/class-lrp-admin-adjustments.php';
        }
        
        // Public
        require_once LRP_PLUGIN_DIR . 'public/class-lrp-public.php';
        require_once LRP_PLUGIN_DIR . 'public/class-lrp-dashboard.php';
        require_once LRP_PLUGIN_DIR . 'public/class-lrp-registration.php';
        
        // Accountant
        require_once LRP_PLUGIN_DIR . 'accountant/class-lrp-accountant.php';
    }

    /**
     * Define hooks do admin
     */
    private function define_admin_hooks() {
        if (!is_admin()) {
            return;
        }
        
        $admin = new LRP_Admin();
        
        $this->add_action('admin_menu', $admin, 'add_menu');
        $this->add_action('admin_enqueue_scripts', $admin, 'enqueue_styles');
        $this->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');
        $this->add_action('admin_init', $admin, 'register_settings');
    }

    /**
     * Define hooks públicos
     */
    private function define_public_hooks() {
        $public = new LRP_Public();
        
        $this->add_action('wp_enqueue_scripts', $public, 'enqueue_styles');
        $this->add_action('wp_enqueue_scripts', $public, 'enqueue_scripts');
        $this->add_action('init', $public, 'register_shortcodes');
        
        // Inicializa componentes
        LRP_Cookie_Tracker::instance();
        LRP_Coupon_Handler::instance();
        LRP_Attribution::instance();
        LRP_WooCommerce::instance();
        LRP_Guruja::instance();
        LRP_Network::instance();
        LRP_Email_Manager::instance();
        LRP_Ajax_Public::instance();
        LRP_Ajax_Admin::instance();
        LRP_Accountant::instance();
        LRP_Product_Restriction::instance();
        LRP_Terms::instance();
    }

    /**
     * Define hooks de cron
     */
    private function define_cron_hooks() {
        // Inicializa classe de cron que registra todos os hooks
        LRP_Cron::init();
    }

    /**
     * Adiciona uma action
     *
     * @param string $hook
     * @param object|null $component
     * @param string $callback
     * @param int $priority
     * @param int $accepted_args
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }

    /**
     * Adiciona um filter
     *
     * @param string $hook
     * @param object|null $component
     * @param string $callback
     * @param int $priority
     * @param int $accepted_args
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }

    /**
     * Executa o plugin registrando todos os hooks
     */
    public function run() {
        // Registra actions
        foreach ($this->actions as $hook) {
            if ($hook['component']) {
                add_action(
                    $hook['hook'],
                    [$hook['component'], $hook['callback']],
                    $hook['priority'],
                    $hook['accepted_args']
                );
            } else {
                add_action(
                    $hook['hook'],
                    $hook['callback'],
                    $hook['priority'],
                    $hook['accepted_args']
                );
            }
        }
        
        // Registra filters
        foreach ($this->filters as $hook) {
            if ($hook['component']) {
                add_filter(
                    $hook['hook'],
                    [$hook['component'], $hook['callback']],
                    $hook['priority'],
                    $hook['accepted_args']
                );
            } else {
                add_filter(
                    $hook['hook'],
                    $hook['callback'],
                    $hook['priority'],
                    $hook['accepted_args']
                );
            }
        }
    }
}


