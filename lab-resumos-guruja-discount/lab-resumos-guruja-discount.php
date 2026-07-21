<?php
/**
 * Plugin Name: Lab Resumos - Desconto Guruja
 * Plugin URI: https://labresumos.com.br
 * Description: Integração com API Guruja para descontos automáticos para alunos no checkout do WooCommerce.
 * Version: 1.0.2
 * Author: Lab Resumos
 * Author URI: https://labresumos.com.br
 * License: GPL v2 or later
 * Text Domain: lab-resumos-guruja
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

defined('ABSPATH') || exit;

// Constantes do plugin
define('LRG_VERSION', '1.0.2');
define('LRG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LRG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LRG_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principal do plugin
 */
final class Lab_Resumos_Guruja_Discount {

    /**
     * Instância única
     */
    private static $instance = null;

    /**
     * Retorna instância única
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor
     */
    private function __construct() {
        $this->check_dependencies();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Verifica dependências
     */
    private function check_dependencies() {
        add_action('admin_init', function() {
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', function() {
                    echo '<div class="error"><p>';
                    echo '<strong>Lab Resumos - Desconto Guruja</strong> requer o WooCommerce ativo.';
                    echo '</p></div>';
                });
                deactivate_plugins(LRG_PLUGIN_BASENAME);
            }
        });
    }

    /**
     * Inclui arquivos necessários
     */
    private function includes() {
        require_once LRG_PLUGIN_DIR . 'includes/class-guruja-admin.php';
        require_once LRG_PLUGIN_DIR . 'includes/class-guruja-integration.php';
        require_once LRG_PLUGIN_DIR . 'includes/class-guruja-ajax.php';
    }

    /**
     * Inicializa hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'load_textdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Declarar compatibilidade com HPOS
        add_action('before_woocommerce_init', function() {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                    'custom_order_tables',
                    __FILE__,
                    true
                );
            }
        });
    }

    /**
     * Carrega traduções
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'lab-resumos-guruja',
            false,
            dirname(LRG_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Enfileira scripts e estilos
     */
    public function enqueue_scripts() {
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_style(
            'lrg-checkout',
            LRG_PLUGIN_URL . 'assets/css/guruja-checkout.css',
            [],
            LRG_VERSION
        );

        wp_enqueue_script(
            'lrg-checkout',
            LRG_PLUGIN_URL . 'assets/js/guruja-checkout.js',
            ['jquery', 'wc-checkout'],
            LRG_VERSION,
            true
        );

        wp_localize_script('lrg-checkout', 'lrgGuruja', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lrg_guruja_nonce'),
            'i18n' => [
                'checking' => __('Verificando desconto...', 'lab-resumos-guruja'),
                'applied' => __('Desconto Guruja aplicado!', 'lab-resumos-guruja'),
                'notEligible' => __('Desconto não disponível para este email/CPF.', 'lab-resumos-guruja'),
                'error' => __('Erro ao verificar desconto. Tente novamente.', 'lab-resumos-guruja'),
            ],
        ]);
    }
}

/**
 * Inicializa o plugin
 */
function lrg_init() {
    return Lab_Resumos_Guruja_Discount::instance();
}

// Inicializa após plugins carregados
add_action('plugins_loaded', 'lrg_init');

/**
 * Ativação do plugin
 */
register_activation_hook(__FILE__, function() {
    // Cria opções padrão
    add_option('lrg_api_url', 'https://backoffice.guruja.com.br/woocommerce/verificar-desconto');
    add_option('lrg_api_token', '');
    add_option('lrg_enabled', 'yes');
    add_option('lrg_debug_mode', 'no');
    add_option('lrg_api_timeout', 10);
    
    // Limpa cache
    flush_rewrite_rules();
});

/**
 * Desativação do plugin
 */
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});
