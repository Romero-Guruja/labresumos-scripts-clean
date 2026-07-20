<?php
/**
 * Plugin Name: Lab Resumos - Acessos
 * Plugin URI: https://labresumos.com.br
 * Description: Concede acesso de cortesia a cursos via pedido WooCommerce, reaproveitando Edwiser Bridge + cpf-sender-api + DRM. Resolve identidade (CPF/email) e provisiona o usuario; a matricula no Moodle e o envio do CPF continuam a cargo da infra existente.
 * Version: 1.3.0
 * Author: Lab Resumos
 * Author URI: https://labresumos.com.br
 * License: GPL v2 or later
 * Text Domain: lab-resumos-acessos
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

define('LRA_VERSION', '1.3.0');
define('LRA_PLUGIN_FILE', __FILE__);
define('LRA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LRA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LRA_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Helper de log simples (delega ao error_log do PHP).
 *
 * @param string $message
 * @param array  $context
 * @param string $level
 */
function lra_log($message, $context = [], $level = 'info') {
    if (!empty($context)) {
        $message .= ' ' . wp_json_encode($context);
    }
    error_log('[lab-resumos-acessos][' . $level . '] ' . $message);
}

/**
 * Classe principal do plugin.
 */
final class Lab_Resumos_Acessos {

    /**
     * Instancia unica.
     *
     * @var Lab_Resumos_Acessos|null
     */
    private static $instance = null;

    /**
     * Retorna instancia unica.
     *
     * @return Lab_Resumos_Acessos
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor.
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Inclui as classes do plugin.
     */
    private function includes() {
        require_once LRA_PLUGIN_DIR . 'includes/class-lra-conflicts.php';
        require_once LRA_PLUGIN_DIR . 'includes/class-lra-roles.php';
        require_once LRA_PLUGIN_DIR . 'includes/class-lra-identity.php';
        require_once LRA_PLUGIN_DIR . 'includes/class-lra-catalog.php';
        require_once LRA_PLUGIN_DIR . 'includes/class-lra-order.php';
        require_once LRA_PLUGIN_DIR . 'includes/class-lra-onboarding.php';
        require_once LRA_PLUGIN_DIR . 'includes/class-lra-access.php';
        require_once LRA_PLUGIN_DIR . 'includes/class-lra-enrollment.php';

        if (is_admin()) {
            require_once LRA_PLUGIN_DIR . 'admin/class-lra-admin.php';
        }
    }

    /**
     * Inicializa hooks.
     */
    private function init_hooks() {
        add_action('admin_init', [$this, 'check_dependencies']);
        add_action('init', [$this, 'load_textdomain']);

        LRA_Roles::init();
        LRA_Enrollment::init();

        // Compatibilidade com HPOS.
        add_action('before_woocommerce_init', function () {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                    'custom_order_tables',
                    LRA_PLUGIN_FILE,
                    true
                );
            }
        });

        if (is_admin()) {
            LRA_Admin::instance();
        }
    }

    /**
     * Verifica dependencia do WooCommerce.
     */
    public function check_dependencies() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function () {
                echo '<div class="error"><p>';
                echo '<strong>Lab Resumos - Acessos</strong> requer o WooCommerce ativo.';
                echo '</p></div>';
            });
        }
    }

    /**
     * Carrega traducoes.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'lab-resumos-acessos',
            false,
            dirname(LRA_PLUGIN_BASENAME) . '/languages'
        );
    }
}

// No escopo raiz do arquivo: numa requisicao de ativacao o plugin e incluido
// DEPOIS do plugins_loaded, entao registrar o hook dentro dele nunca dispara.
require_once LRA_PLUGIN_DIR . 'includes/class-lra-conflicts.php';
register_activation_hook(LRA_PLUGIN_FILE, ['LRA_Conflicts', 'install_table']);

add_action('plugins_loaded', function () {
    Lab_Resumos_Acessos::instance();
});
