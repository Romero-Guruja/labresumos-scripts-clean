<?php
/**
 * Plugin Name: Lab Resumos - Recuperação de Vendas
 * Plugin URI: https://labresumos.com.br
 * Description: Gerencia pedidos com status "Malsucedido" no WooCommerce, fornecendo fluxo estruturado de recuperação de vendas.
 * Version: 1.1.0
 * Author: Lab Resumos
 * Author URI: https://labresumos.com.br
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lr-recuperacao-vendas
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Se acessado diretamente, abortar
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes do plugin
define('LR_RECOVERY_VERSION', '1.1.0');
define('LR_RECOVERY_PLUGIN_FILE', __FILE__);
define('LR_RECOVERY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LR_RECOVERY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LR_RECOVERY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principal do plugin
 */
final class LR_Recuperacao_Vendas {

    /**
     * Instância única do plugin
     * @var LR_Recuperacao_Vendas
     */
    private static $instance = null;

    /**
     * Recovery Manager instance
     * @var LR_Recovery_Manager
     */
    public $manager;

    /**
     * Dashboard instance
     * @var LR_Admin_Dashboard
     */
    public $dashboard;

    /**
     * Notifications instance
     * @var LR_Notifications
     */
    public $notifications;

    /**
     * Metabox instance
     * @var LR_Order_Metabox
     */
    public $metabox;

    /**
     * Autologin Integration instance
     * @var LR_Autologin_Integration
     */
    public $autologin;

    /**
     * Obtém a instância única do plugin
     * @return LR_Recuperacao_Vendas
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Inicializa hooks do plugin
     */
    private function init_hooks() {
        // Hook de ativação
        register_activation_hook(__FILE__, [$this, 'activate']);
        
        // Hook de desativação
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Verificar dependências após plugins carregados
        add_action('plugins_loaded', [$this, 'check_dependencies']);
        
        // Inicializar plugin
        add_action('plugins_loaded', [$this, 'init'], 20);

        // Carregar traduções
        add_action('init', [$this, 'load_textdomain']);

        // Declarar compatibilidade com HPOS do WooCommerce
        add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);
    }

    /**
     * Verifica dependências do plugin
     */
    public function check_dependencies() {
        $missing = $this->get_missing_dependencies();

        if (!empty($missing)) {
            add_action('admin_notices', function() use ($missing) {
                $message = sprintf(
                    __('Lab Resumos - Recuperação de Vendas requer os seguintes plugins: %s', 'lr-recuperacao-vendas'),
                    '<strong>' . implode(', ', $missing) . '</strong>'
                );
                echo '<div class="notice notice-error"><p>' . wp_kses_post($message) . '</p></div>';
            });
            return false;
        }

        return true;
    }

    /**
     * Retorna lista de dependências ausentes
     * @return array
     */
    public function get_missing_dependencies() {
        $missing = [];

        if (!class_exists('WooCommerce')) {
            $missing[] = 'WooCommerce';
        }

        return $missing;
    }

    /**
     * Inicializa o plugin
     */
    public function init() {
        // Se WooCommerce não está ativo, não inicializar
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Carregar arquivos de classe
        $this->includes();

        // Instanciar classes
        $this->manager = new LR_Recovery_Manager();
        $this->dashboard = new LR_Admin_Dashboard();
        $this->notifications = new LR_Notifications();
        $this->metabox = new LR_Order_Metabox();
        $this->autologin = new LR_Autologin_Integration();

        // Registrar endpoints AJAX
        $this->register_ajax_handlers();
    }

    /**
     * Inclui arquivos necessários
     */
    private function includes() {
        require_once LR_RECOVERY_PLUGIN_DIR . 'includes/class-lr-recovery-manager.php';
        require_once LR_RECOVERY_PLUGIN_DIR . 'includes/class-lr-admin-dashboard.php';
        require_once LR_RECOVERY_PLUGIN_DIR . 'includes/class-lr-notifications.php';
        require_once LR_RECOVERY_PLUGIN_DIR . 'includes/class-lr-order-metabox.php';
        require_once LR_RECOVERY_PLUGIN_DIR . 'includes/class-lr-autologin-integration.php';
    }

    /**
     * Registra handlers AJAX
     */
    private function register_ajax_handlers() {
        // Atualizar item do checklist
        add_action('wp_ajax_lr_update_checklist', [$this->manager, 'ajax_update_checklist']);
        
        // Gerar link de autologin
        add_action('wp_ajax_lr_generate_autologin', [$this->autologin, 'ajax_generate_autologin']);
        
        // Atribuir caso
        add_action('wp_ajax_lr_assign_case', [$this->manager, 'ajax_assign_case']);
        
        // Adicionar observação
        add_action('wp_ajax_lr_add_note', [$this->manager, 'ajax_add_note']);
        
        // Atualizar status do caso
        add_action('wp_ajax_lr_update_case_status', [$this->manager, 'ajax_update_case_status']);

        // Marcar pedido como concluído
        add_action('wp_ajax_lr_complete_order', [$this->manager, 'ajax_complete_order']);
    }

    /**
     * Ativação do plugin
     */
    public function activate() {
        // Verificar dependências
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('Lab Resumos - Recuperação de Vendas requer o WooCommerce instalado e ativo.', 'lr-recuperacao-vendas'),
                __('Erro de Ativação', 'lr-recuperacao-vendas'),
                ['back_link' => true]
            );
        }

        // Criar tabelas
        $this->create_tables();

        // Criar opções padrão
        $this->create_default_options();

        // Limpar rewrite rules
        flush_rewrite_rules();

        // Registrar versão
        update_option('lr_recovery_version', LR_RECOVERY_VERSION);
    }

    /**
     * Desativação do plugin
     */
    public function deactivate() {
        // Limpar transients
        delete_transient('lr_recovery_pending_count');
        
        // Limpar rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Cria as tabelas do plugin
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Tabela de casos de recuperação
        $table_cases = $wpdb->prefix . 'lr_recovery_cases';
        $sql_cases = "CREATE TABLE $table_cases (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            status ENUM('novo', 'em_atendimento', 'aguardando_cliente', 'resolvido', 'abandonado') DEFAULT 'novo',
            assigned_to BIGINT UNSIGNED DEFAULT NULL,
            failure_reason VARCHAR(255),
            failure_type ENUM('antifraude', 'banco', 'retentativas', 'outro') DEFAULT 'outro',
            charge_id VARCHAR(100) DEFAULT NULL,
            checklist LONGTEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at DATETIME DEFAULT NULL,
            UNIQUE KEY order_id (order_id),
            KEY status (status),
            KEY assigned_to (assigned_to),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Tabela de logs
        $table_logs = $wpdb->prefix . 'lr_recovery_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY case_id (case_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_cases);
        dbDelta($sql_logs);
    }

    /**
     * Cria opções padrão
     */
    private function create_default_options() {
        // Template de mensagem WhatsApp (sem acentos para evitar problemas de encoding)
        // NOTA: Não salvamos mais o template como opção - usamos direto da classe
        // Isso evita problemas de template desatualizado no banco
        
        // Deletar opção antiga se existir (força usar o template do código)
        delete_option('lr_recovery_whatsapp_template');
        
        add_option('lr_recovery_email_enabled', 'yes');
        add_option('lr_recovery_email_recipients', get_option('admin_email'));
    }

    /**
     * Carrega traduções
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'lr-recuperacao-vendas',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Declara compatibilidade com HPOS do WooCommerce
     */
    public function declare_hpos_compatibility() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
        }
    }

    /**
     * Retorna URLs importantes
     * @return array
     */
    public static function get_external_urls() {
        return [
            'pagarme_base' => 'https://dash.pagar.me/merch_Elb249gOTPimekPw/acc_JY0Oa4xT6SgQaLo8',
            'pagarme_charge' => 'https://dash.pagar.me/merch_Elb249gOTPimekPw/acc_JY0Oa4xT6SgQaLo8/charges/',
            'edwiser_enrollment' => admin_url('edit.php?post_type=eb_course&page=mucp-manage-enrollment'),
        ];
    }
}

/**
 * Função para acessar a instância do plugin
 * @return LR_Recuperacao_Vendas
 */
function lr_recovery() {
    return LR_Recuperacao_Vendas::get_instance();
}

// Inicializar plugin
lr_recovery();
