<?php
/**
 * Plugin Name: Programa de Parceiros Lab Resumos
 * Plugin URI: https://labresumos.com.br
 * Description: Sistema completo de afiliados com cupons exclusivos, links de rastreamento, estrutura multi-nível e integração com Guruja.
 * Version: 1.7.6
 * Author: Lab Resumos
 * Author URI: https://labresumos.com.br
 * Text Domain: lab-resumos-parceiros
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * @package Lab_Resumos_Parceiros
 */

// Se acessado diretamente, aborta
if (!defined('ABSPATH')) {
    exit;
}

// Constantes do plugin
define('LRP_VERSION', '1.7.7');
define('LRP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LRP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LRP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('LRP_PLUGIN_FILE', __FILE__);

/**
 * Verifica requisitos mínimos
 */
function lrp_check_requirements() {
    $errors = [];
    
    // Verifica versão do PHP
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $errors[] = sprintf(
            __('Programa de Parceiros Lab Resumos requer PHP 7.4 ou superior. Versão atual: %s', 'lab-resumos-parceiros'),
            PHP_VERSION
        );
    }
    
    // Verifica se WooCommerce está ativo
    if (!class_exists('WooCommerce')) {
        $errors[] = __('Programa de Parceiros Lab Resumos requer WooCommerce ativo.', 'lab-resumos-parceiros');
    }
    
    return $errors;
}

/**
 * Exibe erros de requisitos
 */
function lrp_requirements_notice() {
    $errors = lrp_check_requirements();
    
    if (!empty($errors)) {
        echo '<div class="notice notice-error"><p>';
        echo implode('</p><p>', array_map('esc_html', $errors));
        echo '</p></div>';
    }
}

/**
 * Declaração de compatibilidade com HPOS e Checkout Blocks
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // HPOS (High-Performance Order Storage)
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
        
        // Cart/Checkout Blocks (WooCommerce 10+)
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true
        );
    }
});

/**
 * Ativação do plugin
 */
function lrp_activate() {
    require_once LRP_PLUGIN_DIR . 'includes/class-lrp-activator.php';
    LRP_Activator::activate();
}
register_activation_hook(__FILE__, 'lrp_activate');

/**
 * Desativação do plugin
 */
function lrp_deactivate() {
    require_once LRP_PLUGIN_DIR . 'includes/class-lrp-deactivator.php';
    LRP_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'lrp_deactivate');

/**
 * Verifica e executa upgrades de banco de dados
 */
function lrp_maybe_upgrade() {
    $current_db_version = get_option('lrp_db_version', '1.0.0');
    
    // Upgrade para 1.1.0 - Adiciona campos de zerar comissão e desconto individual
    if (version_compare($current_db_version, '1.1.0', '<')) {
        global $wpdb;
        $table = $wpdb->prefix . 'lrp_affiliates';
        
        // Verifica e adiciona colunas se não existem
        $columns_to_add = [
            'zero_commission_rate_coupon' => 'TINYINT(1) DEFAULT 0',
            'zero_commission_rate_link'   => 'TINYINT(1) DEFAULT 0',
            'zero_commission_rate_l2'     => 'TINYINT(1) DEFAULT 0',
            'zero_commission_rate_l3'     => 'TINYINT(1) DEFAULT 0',
            'customer_discount'           => 'DECIMAL(5,2) DEFAULT NULL',
            'zero_customer_discount'      => 'TINYINT(1) DEFAULT 0',
        ];
        
        foreach ($columns_to_add as $column => $definition) {
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $table,
                $column
            ));
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE $table ADD COLUMN $column $definition");
            }
        }
        
        // Limpa valores 0 que deveriam ser NULL (fix bug anterior)
        $wpdb->query("UPDATE $table SET commission_rate_coupon = NULL WHERE commission_rate_coupon = 0 AND zero_commission_rate_coupon = 0");
        $wpdb->query("UPDATE $table SET commission_rate_link = NULL WHERE commission_rate_link = 0 AND zero_commission_rate_link = 0");
        $wpdb->query("UPDATE $table SET commission_rate_l2 = NULL WHERE commission_rate_l2 = 0 AND zero_commission_rate_l2 = 0");
        $wpdb->query("UPDATE $table SET commission_rate_l3 = NULL WHERE commission_rate_l3 = 0 AND zero_commission_rate_l3 = 0");
        
        update_option('lrp_db_version', '1.1.0');
        
        lrp_log('Upgrade para versão 1.1.0 concluído', [], 'info');
    }
    
    // Upgrade para 1.2.0 - Métricas enriquecidas para afiliados
    if (version_compare($current_db_version, '1.2.0', '<')) {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();
        
        // 1. Adiciona colunas de tracking enriquecido na tabela lrp_visits
        $visits_table = $wpdb->prefix . 'lrp_visits';
        $visits_columns = [
            'utm_source'     => 'VARCHAR(100) DEFAULT NULL',
            'utm_medium'     => 'VARCHAR(100) DEFAULT NULL',
            'utm_campaign'   => 'VARCHAR(100) DEFAULT NULL',
            'utm_term'       => 'VARCHAR(100) DEFAULT NULL',
            'utm_content'    => 'VARCHAR(100) DEFAULT NULL',
            'traffic_source' => 'VARCHAR(50) DEFAULT NULL',
            'device_type'    => "ENUM('desktop', 'mobile', 'tablet') DEFAULT NULL",
            'browser'        => 'VARCHAR(50) DEFAULT NULL',
        ];
        
        foreach ($visits_columns as $column => $definition) {
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $visits_table,
                $column
            ));
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE $visits_table ADD COLUMN $column $definition");
            }
        }
        
        // 2. Adiciona índices de performance
        $wpdb->query("ALTER TABLE $visits_table ADD INDEX idx_traffic_source (traffic_source)");
        $wpdb->query("ALTER TABLE $visits_table ADD INDEX idx_device_type (device_type)");
        $wpdb->query("ALTER TABLE $visits_table ADD INDEX idx_utm_source (utm_source)");
        $wpdb->query("ALTER TABLE $visits_table ADD INDEX idx_created_converted (created_at, converted)");
        
        // 3. Cria tabela de cache de estatísticas
        $cache_table = $wpdb->prefix . 'lrp_affiliate_stats_cache';
        $sql = "CREATE TABLE IF NOT EXISTS $cache_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id BIGINT(20) UNSIGNED NOT NULL,
            period_type ENUM('day', 'week', 'month', 'year', 'all') NOT NULL,
            period_value VARCHAR(10) DEFAULT NULL,
            total_sales INT(11) DEFAULT 0,
            total_revenue DECIMAL(15,2) DEFAULT 0.00,
            total_commission DECIMAL(15,2) DEFAULT 0.00,
            avg_ticket DECIMAL(10,2) DEFAULT 0.00,
            total_clicks INT(11) DEFAULT 0,
            unique_visitors INT(11) DEFAULT 0,
            conversion_rate DECIMAL(5,2) DEFAULT 0.00,
            rank_position INT(11) DEFAULT NULL,
            rank_percentile INT(3) DEFAULT NULL,
            source_distribution JSON DEFAULT NULL,
            state_distribution JSON DEFAULT NULL,
            device_distribution JSON DEFAULT NULL,
            payment_distribution JSON DEFAULT NULL,
            products_distribution JSON DEFAULT NULL,
            calculated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY affiliate_period (affiliate_id, period_type, period_value),
            KEY calculated_at (calculated_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // 4. Agenda cron job para cálculo de estatísticas
        if (!wp_next_scheduled('lrp_calculate_stats')) {
            wp_schedule_event(time(), 'hourly', 'lrp_calculate_stats');
        }
        
        update_option('lrp_db_version', '1.2.0');
        
        lrp_log('Upgrade para versão 1.2.0 concluído - Métricas enriquecidas', [], 'info');
    }
    
    // Upgrade para 1.3.0 - Restrições de produtos por afiliado
    if (version_compare($current_db_version, '1.3.0', '<')) {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();
        
        // Cria tabela de restrições de produtos
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lrp_product_restrictions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id BIGINT(20) UNSIGNED NOT NULL,
            restriction_mode ENUM('blacklist', 'whitelist') NOT NULL,
            item_type ENUM('product', 'category') NOT NULL,
            item_id BIGINT(20) UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE DEFAULT NULL,
            reason TEXT DEFAULT NULL,
            created_by BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY item_lookup (affiliate_id, item_type, item_id),
            KEY active_period (affiliate_id, start_date, end_date)
        ) $charset_collate;";
        dbDelta($sql);
        
        update_option('lrp_db_version', '1.3.0');
        
        lrp_log('Upgrade para versão 1.3.0 concluído - Restrições de produtos', [], 'info');
    }
    
    // Upgrade para 1.4.0 - Suporte a RPA e periodicidade flexível
    if (version_compare($current_db_version, '1.4.0', '<')) {
        global $wpdb;
        
        // 1. Adiciona novos campos na tabela lrp_affiliates
        $affiliates_table = $wpdb->prefix . 'lrp_affiliates';
        $affiliates_columns = [
            'billing_type'            => "ENUM('pj', 'rpa') DEFAULT 'pj'",
            'cpf'                     => 'VARCHAR(14) DEFAULT NULL',
            'full_address'            => 'TEXT DEFAULT NULL',
            'phone'                   => 'VARCHAR(20) DEFAULT NULL',
            'inss_number'             => 'VARCHAR(30) DEFAULT NULL',
            'rpa_service_description' => 'TEXT DEFAULT NULL',
            'payment_period_months'   => 'INT(2) DEFAULT 1',
            'next_payment_date'       => 'DATE DEFAULT NULL',
        ];
        
        foreach ($affiliates_columns as $column => $definition) {
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $affiliates_table,
                $column
            ));
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE $affiliates_table ADD COLUMN $column $definition");
            }
        }
        
        // 2. Adiciona novos campos na tabela lrp_closings para suporte a adiamento
        $closings_table = $wpdb->prefix . 'lrp_closings';
        $closings_columns = [
            'deferred'              => 'TINYINT(1) DEFAULT 0',
            'deferred_at'           => 'DATETIME DEFAULT NULL',
            'deferred_by'           => 'BIGINT(20) UNSIGNED DEFAULT NULL',
            'deferred_reason'       => 'TEXT DEFAULT NULL',
            'original_period_month' => 'INT(2) DEFAULT NULL',
            'original_period_year'  => 'INT(4) DEFAULT NULL',
        ];
        
        foreach ($closings_columns as $column => $definition) {
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $closings_table,
                $column
            ));
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE $closings_table ADD COLUMN $column $definition");
            }
        }
        
        // 3. Adiciona índice para billing_type (ignorando erro se já existir)
        $wpdb->suppress_errors(true);
        $wpdb->query("ALTER TABLE $affiliates_table ADD INDEX idx_billing_type (billing_type)");
        $wpdb->suppress_errors(false);
        
        // 4. Atualiza ENUM do status em closings para incluir 'awaiting_rpa'
        $wpdb->query("ALTER TABLE $closings_table MODIFY COLUMN status ENUM('open', 'closed', 'awaiting_invoice', 'awaiting_rpa', 'invoice_received', 'approved', 'rejected', 'paid') DEFAULT 'open'");
        
        // 5. Atualiza ENUM do pix_key_type para incluir 'cpf'
        $wpdb->query("ALTER TABLE $affiliates_table MODIFY COLUMN pix_key_type ENUM('cpf', 'cnpj', 'email', 'phone', 'random') DEFAULT NULL");
        
        // 6. Atualiza next_payment_date para afiliados existentes (próximo dia 1)
        $next_month = date('Y-m-01', strtotime('first day of next month'));
        $wpdb->query($wpdb->prepare(
            "UPDATE $affiliates_table SET next_payment_date = %s WHERE next_payment_date IS NULL AND status = 'active'",
            $next_month
        ));
        
        // 7. Atualiza configurações padrão
        $settings = get_option('lrp_settings', []);
        $new_defaults = [
            'default_payment_period_months' => 3,
            'allow_affiliate_defer'         => true,
            'defer_message'                 => 'Você pode adiar o recebimento para o próximo período. O saldo será acumulado.',
            'rpa_service_description'       => 'Serviços de divulgação e indicação comercial',
        ];
        
        foreach ($new_defaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }
        update_option('lrp_settings', $settings);
        
        update_option('lrp_db_version', '1.4.0');
        
        lrp_log('Upgrade para versão 1.4.0 concluído - Suporte a RPA e periodicidade flexível', [], 'info');
    }
    
    // Upgrade para 1.5.0 - Regra de Compressão de Rede
    if (version_compare($current_db_version, '1.5.0', '<')) {
        global $wpdb;
        
        // 1. Adiciona campos de atividade de rede na tabela lrp_affiliates
        $affiliates_table = $wpdb->prefix . 'lrp_affiliates';
        $affiliates_columns = [
            'network_active'            => 'TINYINT(1) DEFAULT 1',
            'network_active_updated_at' => 'DATETIME DEFAULT NULL',
        ];
        
        foreach ($affiliates_columns as $column => $definition) {
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $affiliates_table,
                $column
            ));
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE $affiliates_table ADD COLUMN $column $definition");
            }
        }
        
        // 2. Adiciona índice para network_active (ignorando erro se já existir)
        $wpdb->suppress_errors(true);
        $wpdb->query("ALTER TABLE $affiliates_table ADD INDEX idx_network_active (network_active)");
        $wpdb->suppress_errors(false);
        
        // 3. Define todos os afiliados existentes como ativos inicialmente
        $wpdb->query("UPDATE $affiliates_table SET network_active = 1 WHERE network_active IS NULL");
        
        // 4. Adiciona novas FAQs sobre compressão/atividade de rede
        $faq_table = $wpdb->prefix . 'lrp_faq';
        $new_faqs = [
            [
                'question' => 'O que significa estar "ativo" para comissões de rede?',
                'answer' => '<p>Para receber comissões sobre vendas da sua rede (níveis 2 e 3), você precisa estar <strong>ativo</strong>.</p><p><strong>Afiliado Ativo:</strong> Ter realizado pelo menos 3 vendas nos últimos 3 meses fechados.</p><p><em>Exemplo:</em> Para estar ativo em janeiro, você precisa ter feito 3 ou mais vendas somando outubro, novembro e dezembro.</p><p><strong>Importante:</strong> Sua comissão direta (nível 1) sobre suas próprias vendas nunca é afetada!</p>',
                'category' => 'rede',
                'display_order' => 3,
            ],
            [
                'question' => 'Se eu ficar inativo, perco meus afiliados indicados?',
                'answer' => '<p><strong>Não!</strong> A estrutura da sua rede permanece igual. Você só deixa de receber comissões da rede temporariamente.</p><p>Ao voltar a atingir 3 vendas nos últimos 3 meses, você volta a receber comissões de rede automaticamente no próximo fechamento.</p>',
                'category' => 'rede',
                'display_order' => 4,
            ],
            [
                'question' => 'O que acontece com a comissão quando sou pulado?',
                'answer' => '<p>Ela vai para o próximo afiliado <strong>ativo</strong> acima de você na árvore.</p><p><em>Exemplo:</em> Se você (nível 2) está inativo, seu sponsor (nível 3) recebe a comissão de nível 2 em vez de nível 3.</p>',
                'category' => 'rede',
                'display_order' => 5,
            ],
            [
                'question' => 'Se toda a cadeia acima estiver inativa?',
                'answer' => 'Se não houver nenhum afiliado ativo na cadeia acima, a comissão de rede não é paga.',
                'category' => 'rede',
                'display_order' => 6,
            ],
            [
                'question' => 'Quando meu status de atividade é atualizado?',
                'answer' => '<p>No fechamento de cada mês (dia 1), considerando os 3 meses anteriores fechados.</p><table style="width:100%; border-collapse: collapse; margin: 10px 0;"><tr style="background: #f8f9fa;"><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Fechamento de</th><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Considera vendas de</th></tr><tr><td style="padding: 8px; border: 1px solid #ddd;">Janeiro/2026</td><td style="padding: 8px; border: 1px solid #ddd;">Out, Nov, Dez/2025</td></tr><tr><td style="padding: 8px; border: 1px solid #ddd;">Fevereiro/2026</td><td style="padding: 8px; border: 1px solid #ddd;">Nov, Dez/2025, Jan/2026</td></tr></table>',
                'category' => 'rede',
                'display_order' => 7,
            ],
            [
                'question' => 'Sou um parceiro novo, preciso me preocupar com atividade?',
                'answer' => '<p><strong>Não imediatamente!</strong> Novos parceiros têm um período de proteção de 3 meses.</p><p>Durante este período, você é considerado automaticamente ativo para receber comissões de rede, independente do número de vendas.</p>',
                'category' => 'rede',
                'display_order' => 8,
            ],
        ];
        
        foreach ($new_faqs as $faq) {
            // Verifica se FAQ já existe (pela pergunta)
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $faq_table WHERE question = %s",
                $faq['question']
            ));
            
            if (!$exists) {
                $wpdb->insert($faq_table, array_merge($faq, [
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                ]));
            }
        }
        
        update_option('lrp_db_version', '1.5.0');
        
        lrp_log('Upgrade para versão 1.5.0 concluído - Regra de Compressão de Rede', [], 'info');
    }
    
    // Upgrade para 1.6.0 - Sistema de Termos de Afiliação
    if (version_compare($current_db_version, '1.6.0', '<')) {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();
        
        // 1. Tabela de versões dos termos
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lrp_terms_versions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            version VARCHAR(20) NOT NULL,
            title VARCHAR(255) NOT NULL,
            intro TEXT DEFAULT NULL,
            sections LONGTEXT NOT NULL,
            changelog TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_by BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY version (version),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql);
        
        // 2. Tabela de aceites dos termos
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lrp_terms_acceptances (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id BIGINT(20) UNSIGNED NOT NULL,
            version VARCHAR(20) NOT NULL,
            accepted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY affiliate_version (affiliate_id, version),
            KEY affiliate_id (affiliate_id),
            KEY version (version),
            KEY accepted_at (accepted_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // 3. Tabela de notificações de termos
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lrp_terms_notifications (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id BIGINT(20) UNSIGNED NOT NULL,
            version VARCHAR(20) NOT NULL,
            type ENUM('new_version', 'reminder') DEFAULT 'new_version',
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY is_read (is_read)
        ) $charset_collate;";
        dbDelta($sql);
        
        // 4. Agenda criação da página de termos para o hook 'init' (wp_insert_post não funciona em plugins_loaded)
        update_option('lrp_needs_terms_page', true);
        
        // 5. Define versão inicial dos termos
        update_option('lrp_terms_current_version', '1.0');
        
        update_option('lrp_db_version', '1.6.0');
        
        lrp_log('Upgrade para versão 1.6.0 concluído - Sistema de Termos de Afiliação', [], 'info');
    }
    
    // Upgrade para 1.7.0 - Campos obrigatórios de identificação (Nome, Sobrenome, CPF)
    if (version_compare($current_db_version, '1.7.0', '<')) {
        global $wpdb;
        
        // Adiciona campos first_name e last_name na tabela lrp_affiliates
        $affiliates_table = $wpdb->prefix . 'lrp_affiliates';
        $new_columns = [
            'first_name' => 'VARCHAR(100) DEFAULT NULL',
            'last_name'  => 'VARCHAR(100) DEFAULT NULL',
        ];
        
        foreach ($new_columns as $column => $definition) {
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $affiliates_table,
                $column
            ));
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE $affiliates_table ADD COLUMN $column $definition");
            }
        }
        
        // Preenche first_name e last_name dos afiliados existentes a partir do WordPress user
        $affiliates = $wpdb->get_results("SELECT id, user_id FROM $affiliates_table WHERE first_name IS NULL OR last_name IS NULL");
        foreach ($affiliates as $affiliate) {
            $user = get_userdata($affiliate->user_id);
            if ($user) {
                $first_name = $user->first_name ?: '';
                $last_name = $user->last_name ?: '';
                
                // Se não tiver first/last name, tenta extrair do display_name
                if (empty($first_name) && !empty($user->display_name)) {
                    $parts = explode(' ', $user->display_name, 2);
                    $first_name = $parts[0];
                    $last_name = isset($parts[1]) ? $parts[1] : '';
                }
                
                $wpdb->update(
                    $affiliates_table,
                    ['first_name' => $first_name, 'last_name' => $last_name],
                    ['id' => $affiliate->id]
                );
            }
        }
        
        update_option('lrp_db_version', '1.7.0');
        
        lrp_log('Upgrade para versão 1.7.0 concluído - Campos obrigatórios de identificação', [], 'info');
    }

    // Upgrade para 1.7.1 - Acesso completo do financeiro + fluxo RPA
    if (version_compare($current_db_version, '1.7.1', '<')) {
        $all_lrp_caps = [
            'lrp_manage_affiliates',
            'lrp_manage_commissions',
            'lrp_manage_settings',
            'lrp_view_reports',
            'lrp_manage_invoices',
            'lrp_manage_payments',
        ];

        $accountant = get_role('lrp_accountant');
        if ($accountant) {
            foreach ($all_lrp_caps as $cap) {
                $accountant->add_cap($cap);
            }
        }

        $admin = get_role('administrator');
        if ($admin) {
            foreach ($all_lrp_caps as $cap) {
                $admin->add_cap($cap);
            }
        }

        update_option('lrp_db_version', '1.7.1');

        lrp_log('Upgrade para versão 1.7.1 concluído - Acesso completo do financeiro + fluxo RPA', [], 'info');
    }

    // Upgrade para 1.7.3 - Campo data de nascimento para RPA
    if (version_compare($current_db_version, '1.7.3', '<')) {
        global $wpdb;
        $affiliates_table = $wpdb->prefix . 'lrp_affiliates';

        $col_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'birth_date'",
            DB_NAME,
            $affiliates_table
        ));

        if (empty($col_exists)) {
            $wpdb->query("ALTER TABLE $affiliates_table ADD COLUMN birth_date DATE DEFAULT NULL AFTER inss_number");
        }

        update_option('lrp_db_version', '1.7.3');

        lrp_log('Upgrade para versão 1.7.3 concluído - Campo data de nascimento para RPA', [], 'info');
    }

    // Upgrade para 1.7.5 - Nova role "Gerente de Afiliados" (acesso apenas a Dashboard e Afiliados)
    if (version_compare($current_db_version, '1.7.5', '<')) {
        $affiliate_manager_caps = [
            'read'                  => true,
            'lrp_manage_affiliates' => true,
        ];

        if (!get_role('lrp_affiliate_manager')) {
            add_role(
                'lrp_affiliate_manager',
                __('Gerente de Afiliados', 'lab-resumos-parceiros'),
                $affiliate_manager_caps
            );
        }

        $affiliate_manager = get_role('lrp_affiliate_manager');
        if ($affiliate_manager) {
            foreach ($affiliate_manager_caps as $cap => $grant) {
                $affiliate_manager->add_cap($cap);
            }
            $extra_caps_to_revoke = [
                'lrp_manage_commissions',
                'lrp_manage_settings',
                'lrp_view_reports',
                'lrp_manage_invoices',
                'lrp_manage_payments',
            ];
            foreach ($extra_caps_to_revoke as $cap) {
                if ($affiliate_manager->has_cap($cap)) {
                    $affiliate_manager->remove_cap($cap);
                }
            }
        }

        update_option('lrp_db_version', '1.7.5');

        lrp_log('Upgrade para versão 1.7.5 concluído - Role Gerente de Afiliados', [], 'info');
    }

    // Upgrade para 1.7.7 - Auto-referência configurável (padrão global + 3 estados por afiliado)
    if (version_compare($current_db_version, '1.7.7', '<')) {
        global $wpdb;
        $affiliates_table = $wpdb->prefix . 'lrp_affiliates';

        // Ajusta o default da coluna para NULL (novos afiliados herdam o padrão global)
        $wpdb->query("ALTER TABLE $affiliates_table ALTER COLUMN can_self_refer SET DEFAULT NULL");

        // Garante o setting de padrão global (permitir), sem sobrescrever valor já definido
        $settings = get_option('lrp_settings', []);
        if (!array_key_exists('default_can_self_refer', (array) $settings)) {
            $settings['default_can_self_refer'] = true;
            update_option('lrp_settings', $settings);
        }

        update_option('lrp_db_version', '1.7.7');

        lrp_log('Upgrade para versão 1.7.7 concluído - Auto-referência configurável', [], 'info');
    }
}

/**
 * Inicialização do plugin
 */
function lrp_init() {
    // Verifica requisitos
    $errors = lrp_check_requirements();
    
    if (!empty($errors)) {
        add_action('admin_notices', 'lrp_requirements_notice');
        return;
    }
    
    // Verifica upgrades necessários
    lrp_maybe_upgrade();
    
    // Carrega tradução
    load_plugin_textdomain(
        'lab-resumos-parceiros',
        false,
        dirname(LRP_PLUGIN_BASENAME) . '/languages/'
    );
    
    // Carrega o loader principal
    require_once LRP_PLUGIN_DIR . 'includes/class-lrp-loader.php';
    
    // Inicializa o plugin
    $plugin = new LRP_Loader();
    $plugin->run();
}
add_action('plugins_loaded', 'lrp_init', 20);

/**
 * Cria página de termos após o WordPress estar totalmente carregado
 * (wp_insert_post não funciona corretamente em plugins_loaded)
 */
function lrp_maybe_create_terms_page() {
    // Verifica se precisa criar a página
    if (!get_option('lrp_needs_terms_page')) {
        return;
    }
    
    // Remove a flag primeiro para evitar loops
    delete_option('lrp_needs_terms_page');
    
    // Verifica se já existe página de termos
    $terms_page_id = get_option('lrp_terms_page_id');
    if ($terms_page_id && get_post($terms_page_id)) {
        return;
    }
    
    // Cria a página de termos
    $terms_page = wp_insert_post([
        'post_title'   => __('Termos do Programa de Parceiros', 'lab-resumos-parceiros'),
        'post_content' => '[lrp_affiliate_terms]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_name'    => 'termos-parceiros',
    ]);
    
    if ($terms_page && !is_wp_error($terms_page)) {
        update_option('lrp_terms_page_id', $terms_page);
    }
}
add_action('init', 'lrp_maybe_create_terms_page', 99);

/**
 * Roles do plugin que têm acesso ao wp-admin.
 *
 * Usado para destravar redirects do WooCommerce e exibir a admin bar.
 *
 * @return array
 */
function lrp_admin_access_roles() {
    return [
        'lrp_affiliate_manager',
        'lrp_accountant',
    ];
}

/**
 * Verifica se o usuário atual é um usuário administrativo do plugin
 * (com acesso ao wp-admin mas sem ser administrador padrão).
 *
 * @param int|null $user_id
 * @return bool
 */
function lrp_user_has_admin_access($user_id = null) {
    $user = $user_id ? get_userdata($user_id) : wp_get_current_user();

    if (!$user || empty($user->roles)) {
        return false;
    }

    return (bool) array_intersect(lrp_admin_access_roles(), (array) $user->roles);
}

/**
 * Permite que roles do plugin acessem o wp-admin sem serem redirecionadas
 * pelo WooCommerce para /minha-conta/.
 *
 * @param bool $prevent_access
 * @return bool
 */
function lrp_allow_admin_access_for_plugin_roles($prevent_access) {
    if (lrp_user_has_admin_access()) {
        return false;
    }

    return $prevent_access;
}
add_filter('woocommerce_prevent_admin_access', 'lrp_allow_admin_access_for_plugin_roles');

/**
 * Garante que a admin bar apareça para roles do plugin (o WooCommerce
 * costuma escondê-la para quem não tem edit_posts).
 *
 * @param bool $show_admin_bar
 * @return bool
 */
function lrp_show_admin_bar_for_plugin_roles($show_admin_bar) {
    if (lrp_user_has_admin_access()) {
        return true;
    }

    return $show_admin_bar;
}
add_filter('show_admin_bar', 'lrp_show_admin_bar_for_plugin_roles');

/**
 * Verifica se o usuário é exclusivamente um "Gerente de Afiliados"
 * (sem outras roles). Usado para esconder a página Dashboard dele.
 *
 * @param int|null $user_id
 * @return bool
 */
function lrp_user_is_only_affiliate_manager($user_id = null) {
    $user = $user_id ? get_userdata($user_id) : wp_get_current_user();

    if (!$user || empty($user->roles)) {
        return false;
    }

    $roles = (array) $user->roles;

    return count($roles) === 1 && in_array('lrp_affiliate_manager', $roles, true);
}

/**
 * Remove o submenu "Dashboard" do menu Parceiros para usuários que são
 * apenas Gerente de Afiliados (deixa visível somente "Afiliados").
 */
function lrp_adjust_admin_menu_for_affiliate_manager() {
    if (!lrp_user_is_only_affiliate_manager()) {
        return;
    }

    global $submenu;

    if (!empty($submenu['lrp-dashboard'])) {
        foreach ($submenu['lrp-dashboard'] as $key => $item) {
            if (isset($item[2]) && $item[2] === 'lrp-dashboard') {
                unset($submenu['lrp-dashboard'][$key]);
            }
        }
    }
}
add_action('admin_menu', 'lrp_adjust_admin_menu_for_affiliate_manager', 999);

/**
 * Se um "Gerente de Afiliados" tentar acessar a página Dashboard
 * (seja pelo clique no menu principal "Parceiros" ou por URL direta),
 * redireciona para a página Afiliados.
 */
function lrp_redirect_dashboard_for_affiliate_manager() {
    if (!is_admin() || wp_doing_ajax()) {
        return;
    }

    if (!lrp_user_is_only_affiliate_manager()) {
        return;
    }

    global $pagenow;

    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

    if ($pagenow === 'admin.php' && $page === 'lrp-dashboard') {
        wp_safe_redirect(admin_url('admin.php?page=lrp-affiliates'));
        exit;
    }
}
add_action('admin_init', 'lrp_redirect_dashboard_for_affiliate_manager');

/**
 * Redireciona usuários com roles do plugin direto para a página de
 * Parceiros ao logar (em vez do dashboard padrão do WordPress).
 *
 * @param string $redirect_to
 * @param string $request
 * @param WP_User|WP_Error $user
 * @return string
 */
function lrp_login_redirect_for_plugin_roles($redirect_to, $request, $user) {
    if (is_wp_error($user) || !($user instanceof WP_User)) {
        return $redirect_to;
    }

    if (lrp_user_is_only_affiliate_manager($user->ID)) {
        return admin_url('admin.php?page=lrp-affiliates');
    }

    if (lrp_user_has_admin_access($user->ID)) {
        return admin_url('admin.php?page=lrp-dashboard');
    }

    return $redirect_to;
}
add_filter('login_redirect', 'lrp_login_redirect_for_plugin_roles', 10, 3);

/**
 * Helper para obter instância das configurações
 * 
 * @return LRP_Settings
 */
function lrp_settings() {
    return LRP_Settings::instance();
}

/**
 * Helper para logar eventos (modo debug)
 * 
 * @param string $message Mensagem
 * @param array $context Contexto adicional
 * @param string $level Nível: info, warning, error
 */
function lrp_log($message, $context = [], $level = 'info') {
    // Verifica se as classes já foram carregadas (evita erro durante upgrade)
    if (!class_exists('LRP_Settings')) {
        return;
    }
    
    $settings = lrp_settings();
    
    if (!$settings->get('debug_mode', false)) {
        return;
    }
    
    if (function_exists('wc_get_logger')) {
        $logger = wc_get_logger();
        $logger->log($level, $message, array_merge(['source' => 'lab-resumos-parceiros'], $context));
    }
}

/**
 * Envia alerta para o Telegram via webhook
 * 
 * Chamada non-blocking para não atrasar o fluxo do WordPress/checkout.
 * 
 * @param string $evento Título curto do alerta
 * @param string $descricao Texto complementar com detalhes
 */
function lrp_send_telegram_alert($evento, $descricao = '') {
    $webhook_url = 'https://automation.guruja.com.br/webhook/b87b165b-6017-4156-97a6-1431cec04356';

    wp_remote_post($webhook_url, [
        'timeout'  => 5,
        'blocking' => false,
        'body'     => wp_json_encode([
            'evento'    => $evento,
            'descricao' => $descricao,
        ]),
        'headers'  => ['Content-Type' => 'application/json'],
    ]);
}

