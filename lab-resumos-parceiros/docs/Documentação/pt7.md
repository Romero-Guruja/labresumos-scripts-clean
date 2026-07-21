# Programa de Parceiros Lab Resumos - Parte 7: Ativação, Segurança e Referência

## 1. Ativação do Plugin

### 1.1 Classe LRP_Activator

```php
<?php
class LRP_Activator {

    public static function activate() {
        self::create_tables();
        self::create_roles();
        self::create_pages();
        self::create_default_faqs();
        self::schedule_cron();
        self::set_default_options();
        self::declare_hpos_compatibility();
        self::declare_blocks_compatibility();
        
        flush_rewrite_rules();
    }

    /**
     * Declara compatibilidade com HPOS (High-Performance Order Storage)
     */
    private static function declare_hpos_compatibility() {
        add_action('before_woocommerce_init', function() {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                    'custom_order_tables',
                    __FILE__,
                    true
                );
            }
        });
    }

    /**
     * Declara compatibilidade com WooCommerce Blocks
     */
    private static function declare_blocks_compatibility() {
        add_action('woocommerce_blocks_loaded', function() {
            // Verificar se checkout usa Blocks e ajustar hooks se necessário
            // Por enquanto, apenas registrar compatibilidade
            // Futuras versões podem precisar de ajustes específicos para Blocks
        });
    }

    /**
     * Cria todas as tabelas
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tabela de afiliados
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_affiliates (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            status ENUM('pending', 'active', 'inactive', 'rejected') DEFAULT 'pending',
            coupon_code VARCHAR(50) NOT NULL,
            referral_code VARCHAR(50) NOT NULL,
            sponsor_id BIGINT(20) UNSIGNED DEFAULT NULL,
            level INT(11) DEFAULT 1,
            commission_rate_coupon DECIMAL(5,2) DEFAULT NULL,
            commission_rate_link DECIMAL(5,2) DEFAULT NULL,
            commission_rate_l2 DECIMAL(5,2) DEFAULT NULL,
            commission_rate_l3 DECIMAL(5,2) DEFAULT NULL,
            cookie_days INT(11) DEFAULT NULL,
            guruja_rule ENUM('higher_discount', 'affiliate_priority', 'guruja_priority', 'no_commission') DEFAULT NULL,
            can_self_refer TINYINT(1) DEFAULT 0,
            payment_method ENUM('pix', 'bank_transfer') DEFAULT 'pix',
            pix_key_type ENUM('cpf', 'cnpj', 'email', 'phone', 'random') DEFAULT NULL,
            pix_key VARCHAR(255) DEFAULT NULL,
            bank_name VARCHAR(100) DEFAULT NULL,
            bank_agency VARCHAR(20) DEFAULT NULL,
            bank_account VARCHAR(30) DEFAULT NULL,
            bank_account_type ENUM('checking', 'savings') DEFAULT 'checking',
            holder_name VARCHAR(255) DEFAULT NULL,
            holder_document VARCHAR(20) DEFAULT NULL,
            total_sales INT(11) DEFAULT 0,
            total_revenue DECIMAL(15,2) DEFAULT 0.00,
            total_commissions DECIMAL(15,2) DEFAULT 0.00,
            total_paid DECIMAL(15,2) DEFAULT 0.00,
            current_balance DECIMAL(15,2) DEFAULT 0.00,
            application_notes TEXT DEFAULT NULL,
            admin_notes TEXT DEFAULT NULL,
            application_ip VARCHAR(45) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            approved_at DATETIME DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            UNIQUE KEY coupon_code (coupon_code),
            UNIQUE KEY referral_code (referral_code),
            KEY sponsor_id (sponsor_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Tabela de referrals
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_referrals (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            attribution_type ENUM('coupon', 'link', 'direct') NOT NULL,
            coupon_used VARCHAR(50) DEFAULT NULL,
            order_total DECIMAL(15,2) NOT NULL,
            discount_amount DECIMAL(15,2) DEFAULT 0.00,
            discount_source ENUM('affiliate', 'guruja', 'other') DEFAULT NULL,
            commission_base DECIMAL(15,2) NOT NULL,
            status ENUM('pending', 'approved', 'rejected', 'refunded') DEFAULT 'pending',
            customer_id BIGINT(20) UNSIGNED DEFAULT NULL,
            customer_email VARCHAR(255) DEFAULT NULL,
            is_guruja_student TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_id (order_id),
            KEY affiliate_id (affiliate_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Tabela de comissões
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_commissions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT(20) UNSIGNED NOT NULL,
            affiliate_id BIGINT(20) UNSIGNED NOT NULL,
            commission_type ENUM('direct', 'level_2', 'level_3') NOT NULL,
            source_affiliate_id BIGINT(20) UNSIGNED DEFAULT NULL,
            commission_rate DECIMAL(5,2) NOT NULL,
            commission_amount DECIMAL(15,2) NOT NULL,
            status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
            closing_id BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY referral_id (referral_id),
            KEY affiliate_id (affiliate_id),
            KEY status (status),
            KEY closing_id (closing_id),
            KEY created_at (created_at),
            KEY affiliate_status_created (affiliate_id, status, created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Tabela de fechamentos
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_closings (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id BIGINT(20) UNSIGNED NOT NULL,
            period_month INT(2) NOT NULL,
            period_year INT(4) NOT NULL,
            total_sales INT(11) DEFAULT 0,
            total_revenue DECIMAL(15,2) DEFAULT 0.00,
            total_commissions DECIMAL(15,2) DEFAULT 0.00,
            status ENUM('open', 'closed', 'awaiting_invoice', 'invoice_received', 'approved', 'rejected', 'paid') DEFAULT 'open',
            invoice_file VARCHAR(255) DEFAULT NULL,
            invoice_number VARCHAR(50) DEFAULT NULL,
            invoice_uploaded_at DATETIME DEFAULT NULL,
            invoice_notes TEXT DEFAULT NULL,
            payment_proof_file VARCHAR(255) DEFAULT NULL,
            paid_at DATETIME DEFAULT NULL,
            paid_by BIGINT(20) UNSIGNED DEFAULT NULL,
            payment_notes TEXT DEFAULT NULL,
            rejection_reason TEXT DEFAULT NULL,
            rejected_at DATETIME DEFAULT NULL,
            rejected_by BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            closed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY affiliate_period (affiliate_id, period_month, period_year),
            KEY status (status),
            KEY period_lookup (period_year, period_month),
            KEY affiliate_status (affiliate_id, status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Tabela de visitas
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_visits (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id BIGINT(20) UNSIGNED NOT NULL,
            visitor_ip VARCHAR(45) DEFAULT NULL,
            visitor_hash VARCHAR(64) DEFAULT NULL,
            referral_url TEXT DEFAULT NULL,
            landing_page TEXT DEFAULT NULL,
            converted TINYINT(1) DEFAULT 0,
            order_id BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY visitor_hash (visitor_hash),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Tabela de materiais
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_materials (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            type ENUM('image', 'text', 'video', 'document') NOT NULL,
            file_url VARCHAR(500) DEFAULT NULL,
            content TEXT DEFAULT NULL,
            category VARCHAR(100) DEFAULT NULL,
            display_order INT(11) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Tabela de FAQ
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_faq (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            question TEXT NOT NULL,
            answer TEXT NOT NULL,
            category VARCHAR(100) DEFAULT 'geral',
            display_order INT(11) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Tabela de log de atividades
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_activity_log (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id BIGINT(20) UNSIGNED DEFAULT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT DEFAULT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY action (action),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        update_option('lrp_db_version', LRP_VERSION);
    }

    /**
     * Cria roles e capabilities
     */
    private static function create_roles() {
        // Role: Afiliado
        add_role('lrp_affiliate', 'Parceiro', ['read' => true]);
        
        // Role: Contador
        add_role('lrp_accountant', 'Contador', [
            'read' => true,
            'lrp_manage_invoices' => true,
            'lrp_manage_payments' => true,
        ]);
        
        // Capabilities para admin
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('lrp_manage_affiliates');
            $admin->add_cap('lrp_manage_commissions');
            $admin->add_cap('lrp_manage_settings');
            $admin->add_cap('lrp_view_reports');
            $admin->add_cap('lrp_manage_invoices');
            $admin->add_cap('lrp_manage_payments');
        }
    }

    /**
     * Cria páginas
     */
    private static function create_pages() {
        // Dashboard do afiliado
        $dashboard_page = wp_insert_post([
            'post_title' => 'Meu Painel de Parceiro',
            'post_content' => '[lrp_affiliate_dashboard]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => 'meu-painel-parceiro',
        ]);
        update_option('lrp_dashboard_page_id', $dashboard_page);
        
        // Cadastro
        $registration_page = wp_insert_post([
            'post_title' => 'Seja um Parceiro',
            'post_content' => '[lrp_affiliate_registration]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => 'seja-parceiro',
        ]);
        update_option('lrp_registration_page_id', $registration_page);
    }

    /**
     * Agenda cron jobs
     */
    private static function schedule_cron() {
        // Fechamento mensal - evento diário que verifica se é dia 1
        // IMPORTANTE: Não usar intervalo 'monthly' (30 dias não é sempre um mês)
        if (!wp_next_scheduled('lrp_daily_check')) {
            wp_schedule_event(time(), 'daily', 'lrp_daily_check');
        }
        
        // Limpeza diária
        if (!wp_next_scheduled('lrp_cleanup_expired')) {
            wp_schedule_event(time(), 'daily', 'lrp_cleanup_expired');
        }
        
        // Adiciona handler de limpeza de logs ao evento de limpeza
        add_action('lrp_cleanup_expired', ['LRP_Cron', 'cleanup_old_logs']);
    }

    /**
     * Define opções padrão
     */
    private static function set_default_options() {
        $defaults = [
            'enabled' => true,
            'default_commission_coupon' => 10.00,
            'default_commission_link' => 5.00,
            'default_commission_l2' => 3.00,
            'default_commission_l3' => 1.00,
            'default_cookie_days' => 60,
            'default_customer_discount' => 20.00,
            'minimum_payout' => 200.00,
            'closing_day' => 1,
            'default_guruja_rule' => 'higher_discount',
            'company_name' => 'SOLUCOES EDUCACIONAIS INTELIGENTES LTDA',
            'company_cnpj' => '',
            'company_address' => '',
            'accountant_email' => 'financeiro@labresumos.com.br',
            'admin_email' => '',
            'auto_approve' => false,
            'debug_mode' => false,
        ];
        
        if (!get_option('lrp_settings')) {
            update_option('lrp_settings', $defaults);
        }
    }
}
```

---

## 2. Desativação

```php
<?php
class LRP_Deactivator {

    public static function deactivate() {
        // Remove cron jobs
        wp_clear_scheduled_hook('lrp_monthly_closing');
        wp_clear_scheduled_hook('lrp_cleanup_expired');
        
        flush_rewrite_rules();
    }
}
```

---

## 3. Desinstalação (uninstall.php)

```php
<?php
// Se não foi chamado pelo WordPress, aborta
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Opção: manter dados ou remover tudo
$remove_all_data = get_option('lrp_remove_data_on_uninstall', false);

if ($remove_all_data) {
    global $wpdb;
    
    // Remove tabelas
    $tables = [
        'lrp_affiliates',
        'lrp_referrals',
        'lrp_commissions',
        'lrp_closings',
        'lrp_visits',
        'lrp_materials',
        'lrp_faq',
        'lrp_activity_log',
    ];
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
    }
    
    // Remove opções
    delete_option('lrp_settings');
    delete_option('lrp_db_version');
    delete_option('lrp_dashboard_page_id');
    delete_option('lrp_registration_page_id');
    
    // Remove user meta
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'lrp_%'");
    
    // Remove roles
    remove_role('lrp_affiliate');
    remove_role('lrp_accountant');
    
    // Remove capabilities do admin
    $admin = get_role('administrator');
    if ($admin) {
        $admin->remove_cap('lrp_manage_affiliates');
        $admin->remove_cap('lrp_manage_commissions');
        $admin->remove_cap('lrp_manage_settings');
        $admin->remove_cap('lrp_view_reports');
        $admin->remove_cap('lrp_manage_invoices');
        $admin->remove_cap('lrp_manage_payments');
    }
    
    // Remove páginas
    $dashboard_page = get_option('lrp_dashboard_page_id');
    $registration_page = get_option('lrp_registration_page_id');
    
    if ($dashboard_page) wp_delete_post($dashboard_page, true);
    if ($registration_page) wp_delete_post($registration_page, true);
    
    // Remove arquivos de upload
    $upload_dir = wp_upload_dir();
    $dirs = ['lrp-invoices', 'lrp-payments', 'lrp-materials'];
    
    // Usa WP_Filesystem para remoção segura e recursiva
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
        require_once(ABSPATH . '/wp-admin/includes/file.php');
        WP_Filesystem();
    }
    
    foreach ($dirs as $dir) {
        $path = $upload_dir['basedir'] . '/' . $dir;
        if ($wp_filesystem->exists($path)) {
            // Remove recursivamente usando WP_Filesystem
            $wp_filesystem->rmdir($path, true);
        }
    }
}
```

---

## 4. Segurança

### 4.1 Criptografia de Dados Sensíveis

**Chaves PIX devem ser criptografadas antes de salvar no banco de dados:**

```php
/**
 * Criptografa chave PIX antes de salvar
 * 
 * @param string $key Chave PIX a criptografar
 * @return string|null Chave criptografada em base64 ou null se vazia
 */
private function encrypt_pix_key($key) {
    if (empty($key)) {
        return null;
    }
    
    $encryption_key = hash('sha256', wp_salt('lrp_pix_encryption'));
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
    $encrypted = openssl_encrypt($key, 'AES-256-CBC', $encryption_key, 0, $iv);
    
    return base64_encode($iv . $encrypted);
}

/**
 * Descriptografa chave PIX quando necessário
 * 
 * @param string $encrypted_key Chave criptografada em base64
 * @return string|null Chave descriptografada ou null se vazia
 */
private function decrypt_pix_key($encrypted_key) {
    if (empty($encrypted_key)) {
        return null;
    }
    
    $encryption_key = hash('sha256', wp_salt('lrp_pix_encryption'));
    $data = base64_decode($encrypted_key);
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    
    return openssl_decrypt($encrypted, 'AES-256-CBC', $encryption_key, 0, $iv);
}
```

**Uso:** Sempre criptografar antes de salvar e descriptografar apenas quando necessário para exibição ou processamento de pagamento.

### 4.2 Validação e Sanitização

```php
// Sempre sanitizar inputs
$email = sanitize_email($_POST['email']);
$text = sanitize_text_field($_POST['text']);
$textarea = sanitize_textarea_field($_POST['textarea']);
$html = wp_kses_post($_POST['html']);
$url = esc_url_raw($_POST['url']);
$int = intval($_POST['number']);
$float = floatval($_POST['decimal']);

// Para parâmetros de query string, usar sanitize_key() e whitelist
$period = sanitize_key($_GET['period'] ?? 'this_month');
$type = sanitize_key($_GET['type'] ?? 'all');

// Whitelist de valores permitidos
$allowed_periods = ['this_month', 'last_month', 'this_year', 'all'];
if (!in_array($period, $allowed_periods)) {
    $period = 'this_month';
}

// Sempre escapar outputs
echo esc_html($text);
echo esc_url($url);
echo esc_attr($attribute);
echo wp_kses_post($html);
```

### 4.2 Verificação de Nonces

**IMPORTANTE:** Sempre use `isset() || wp_verify_nonce()` ao invés de `?? wp_verify_nonce()`. O operador `??` pode permitir bypass de CSRF quando `$_POST['_wpnonce']` não existe.

```php
// Criar nonce
wp_nonce_field('lrp_action_name', 'lrp_nonce');

// Verificar nonce (CORRETO)
if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'lrp_action_name')) {
    wp_die('Ação não autorizada');
}

// INCORRETO - NÃO USE:
// if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'lrp_action_name')) {

// Para AJAX
check_ajax_referer('lrp_ajax_action', 'nonce');
```

### 4.3 Verificação de Capabilities

```php
// Em páginas admin
if (!current_user_can('lrp_manage_affiliates')) {
    wp_die('Você não tem permissão para acessar esta página.');
}

// Em AJAX
if (!current_user_can('lrp_manage_settings')) {
    wp_send_json_error(['message' => 'Permissão negada']);
}
```

### 4.4 Prepared Statements

```php
// SEMPRE usar prepare para queries com variáveis
$affiliate = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}lrp_affiliates WHERE id = %d",
    $affiliate_id
));

// Para IN clauses
$ids = [1, 2, 3];
$placeholders = implode(',', array_fill(0, count($ids), '%d'));
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}lrp_affiliates WHERE id IN ($placeholders)",
    ...$ids
));
```

---

## 5. Referência Rápida

### 5.1 Hooks Disponíveis

```php
// Actions
do_action('lrp_affiliate_created', $affiliate);
do_action('lrp_affiliate_approved', $affiliate);
do_action('lrp_affiliate_rejected', $affiliate, $reason);
do_action('lrp_new_sale', $affiliate, $referral, $order);
do_action('lrp_referral_created', $referral);
do_action('lrp_referral_approved', $referral);
do_action('lrp_commission_created', $commission);
do_action('lrp_closing_ready', $affiliate, $closing_id, $total);
do_action('lrp_invoice_received', $affiliate, $closing_id);
do_action('lrp_invoice_approved', $affiliate, $closing_id);
do_action('lrp_invoice_rejected', $affiliate, $closing_id, $reason);
do_action('lrp_payment_completed', $affiliate, $closing_id);
do_action('lrp_new_sub_affiliate', $sponsor, $new_affiliate);
do_action('lrp_sub_affiliate_sale', $sponsor, $sub_affiliate, $commission, $referral);

// Webhooks para integrações externas
do_action('lrp_webhook_trigger', 'sale_completed', [
    'affiliate_id' => $affiliate->get_id(),
    'order_id' => $order->get_id(),
    'commission' => $commission_amount,
    'timestamp' => current_time('mysql'),
]);
do_action('lrp_webhook_trigger', 'affiliate_approved', [
    'affiliate_id' => $affiliate->get_id(),
    'user_id' => $affiliate->get_user_id(),
    'timestamp' => current_time('mysql'),
]);
do_action('lrp_webhook_trigger', 'payment_completed', [
    'affiliate_id' => $affiliate->get_id(),
    'closing_id' => $closing_id,
    'amount' => $closing->total_commissions,
    'timestamp' => current_time('mysql'),
]);

// Filters
$rate = apply_filters('lrp_commission_rate', $rate, $affiliate, $type);
$cookie_days = apply_filters('lrp_cookie_days', $days, $affiliate);
$minimum = apply_filters('lrp_minimum_payout', $minimum);
$email_enabled = apply_filters('lrp_send_email', true, $email_type, $recipient);
```

### 5.2 Constantes

```php
define('LRP_VERSION', '1.0.0');
define('LRP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LRP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LRP_PLUGIN_BASENAME', plugin_basename(__FILE__));
```

### 5.3 Classes Principais

```php
// Core
LRP_Affiliate        // Modelo de afiliado
LRP_Commission       // Modelo de comissão
LRP_Referral         // Modelo de venda
LRP_Closing          // Modelo de fechamento
LRP_Settings         // Gerenciador de configurações

// Tracking
LRP_Cookie_Tracker   // Rastreamento por cookie
LRP_Coupon_Handler   // Handler de cupons
LRP_Attribution      // Lógica de atribuição

// Integrations
LRP_WooCommerce      // Integração WooCommerce
LRP_Guruja           // Integração Guruja

// Multi-level
LRP_Network          // Lógica de rede

// Financial
LRP_Calculator       // Cálculo de comissões (classe: class-lrp-calculator.php)

// Emails
LRP_Email_Manager    // Gerenciador de emails

// Admin
LRP_Admin            // Área administrativa
LRP_Admin_Reports    // Relatórios
LRP_Admin_FAQ        // Gestão de FAQ
LRP_Admin_Materials  // Gestão de materiais

// Public
LRP_Public           // Frontend
LRP_Dashboard        // Dashboard do afiliado
LRP_Registration     // Cadastro
LRP_Shortcodes       // Shortcodes (métodos estáticos na classe LRP_Public ou classe separada)

// Accountant
LRP_Accountant       // Área do contador
```

### 5.4 Tabelas do Banco

| Tabela | Descrição |
|--------|-----------|
| `lrp_affiliates` | Afiliados |
| `lrp_referrals` | Vendas atribuídas |
| `lrp_commissions` | Comissões geradas |
| `lrp_closings` | Fechamentos mensais |
| `lrp_visits` | Rastreamento de visitas |
| `lrp_materials` | Materiais de divulgação |
| `lrp_faq` | Perguntas frequentes |
| `lrp_activity_log` | Log de atividades (auditoria) |

### 5.5 Status

**Afiliado:**
- `pending` - Aguardando aprovação
- `active` - Ativo
- `inactive` - Inativo
- `rejected` - Rejeitado

**Referral:**
- `pending` - Aguardando pagamento do pedido
- `approved` - Aprovado
- `rejected` - Rejeitado
- `refunded` - Reembolsado

**Comissão:**
- `pending` - Pendente
- `approved` - Aprovada
- `paid` - Paga
- `cancelled` - Cancelada

**Fechamento:**
- `open` - Em andamento
- `closed` - Fechado (abaixo do mínimo)
- `awaiting_invoice` - Aguardando NF
- `invoice_received` - NF recebida
- `approved` - NF aprovada
- `rejected` - NF rejeitada
- `paid` - Pago

---

## 6. Checklist de Implementação

### Fase 1: Core
- [ ] Estrutura de arquivos
- [ ] Tabelas do banco
- [ ] Classe LRP_Affiliate
- [ ] Classe LRP_Settings
- [ ] Ativação/Desativação

### Fase 2: Tracking
- [ ] Cookie Tracker
- [ ] Coupon Handler
- [ ] Attribution

### Fase 3: WooCommerce
- [ ] Hooks de pedido
- [ ] Criação de referral
- [ ] Cálculo de comissão

### Fase 4: Guruja
- [ ] Detecção de conflito
- [ ] Coordenação de descontos
- [ ] Regras configuráveis

### Fase 5: Multi-nível
- [ ] Network class
- [ ] Distribuição de comissões
- [ ] Árvore de afiliados

### Fase 6: Dashboard Afiliado
- [ ] Página principal
- [ ] Abas
- [ ] Links e cupons
- [ ] Vendas
- [ ] Rede
- [ ] Financeiro
- [ ] Perfil

### Fase 7: Admin
- [ ] Menu
- [ ] Lista de afiliados
- [ ] Edição de afiliado
- [ ] Configurações
- [ ] Relatórios

### Fase 8: Financeiro
- [ ] Fechamento mensal
- [ ] Upload de NF
- [ ] Área do contador
- [ ] Confirmação de pagamento

### Fase 9: Emails
- [ ] Templates
- [ ] Triggers
- [ ] Testes

### Fase 10: Extras
- [ ] FAQ
- [ ] Materiais
- [ ] Exportação CSV
- [ ] Logs