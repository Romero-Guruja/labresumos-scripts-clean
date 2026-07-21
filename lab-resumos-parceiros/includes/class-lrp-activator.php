<?php
/**
 * Ativação do plugin
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Activator
 * 
 * Responsável por criar tabelas, roles e configurações iniciais.
 */
class LRP_Activator {

    /**
     * Executa ativação do plugin
     */
    public static function activate() {
        self::create_tables();
        self::create_roles();
        self::create_pages();
        self::create_default_faqs();
        self::schedule_cron();
        self::set_default_options();
        
        // Marca versão do banco
        update_option('lrp_db_version', LRP_VERSION);
        
        // Limpa rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Cria todas as tabelas do banco de dados
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // 1. Tabela de afiliados
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_affiliates (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            status ENUM('pending', 'active', 'inactive', 'rejected') DEFAULT 'pending',
            coupon_code VARCHAR(50) NOT NULL,
            referral_code VARCHAR(50) NOT NULL,
            sponsor_id BIGINT(20) UNSIGNED DEFAULT NULL,
            level INT(11) DEFAULT 1,
            first_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) DEFAULT NULL,
            cpf VARCHAR(14) DEFAULT NULL,
            billing_type ENUM('pj', 'rpa') DEFAULT 'pj',
            company_cnpj VARCHAR(20) DEFAULT NULL,
            company_name VARCHAR(255) DEFAULT NULL,
            can_issue_nf TINYINT(1) DEFAULT 0,
            full_address TEXT DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            inss_number VARCHAR(30) DEFAULT NULL,
            birth_date DATE DEFAULT NULL,
            rpa_service_description TEXT DEFAULT NULL,
            commission_rate_coupon DECIMAL(5,2) DEFAULT NULL,
            commission_rate_link DECIMAL(5,2) DEFAULT NULL,
            commission_rate_l2 DECIMAL(5,2) DEFAULT NULL,
            commission_rate_l3 DECIMAL(5,2) DEFAULT NULL,
            zero_commission_rate_coupon TINYINT(1) DEFAULT 0,
            zero_commission_rate_link TINYINT(1) DEFAULT 0,
            zero_commission_rate_l2 TINYINT(1) DEFAULT 0,
            zero_commission_rate_l3 TINYINT(1) DEFAULT 0,
            customer_discount DECIMAL(5,2) DEFAULT NULL,
            zero_customer_discount TINYINT(1) DEFAULT 0,
            cookie_days INT(11) DEFAULT NULL,
            guruja_rule ENUM('higher_discount', 'affiliate_priority', 'guruja_priority', 'no_commission') DEFAULT NULL,
            can_self_refer TINYINT(1) DEFAULT NULL,
            payment_method ENUM('pix', 'bank_transfer') DEFAULT 'pix',
            pix_key_type ENUM('cpf', 'cnpj', 'email', 'phone', 'random') DEFAULT NULL,
            pix_key VARCHAR(255) DEFAULT NULL,
            bank_name VARCHAR(100) DEFAULT NULL,
            bank_agency VARCHAR(20) DEFAULT NULL,
            bank_account VARCHAR(30) DEFAULT NULL,
            bank_account_type ENUM('checking', 'savings') DEFAULT 'checking',
            holder_name VARCHAR(255) DEFAULT NULL,
            holder_document VARCHAR(20) DEFAULT NULL,
            payment_period_months INT(2) DEFAULT 1,
            next_payment_date DATE DEFAULT NULL,
            total_sales INT(11) DEFAULT 0,
            total_revenue DECIMAL(15,2) DEFAULT 0.00,
            total_commissions DECIMAL(15,2) DEFAULT 0.00,
            total_paid DECIMAL(15,2) DEFAULT 0.00,
            current_balance DECIMAL(15,2) DEFAULT 0.00,
            network_active TINYINT(1) DEFAULT 1,
            network_active_updated_at DATETIME DEFAULT NULL,
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
            KEY status (status),
            KEY company_cnpj (company_cnpj),
            KEY billing_type (billing_type),
            KEY network_active (network_active)
        ) $charset_collate;";
        dbDelta($sql);
        
        // 2. Tabela de referrals (vendas)
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
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // 3. Tabela de comissões
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
        
        // 4. Tabela de fechamentos
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_closings (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id BIGINT(20) UNSIGNED NOT NULL,
            period_month INT(2) NOT NULL,
            period_year INT(4) NOT NULL,
            total_sales INT(11) DEFAULT 0,
            total_revenue DECIMAL(15,2) DEFAULT 0.00,
            total_commissions DECIMAL(15,2) DEFAULT 0.00,
            adjustment_amount DECIMAL(15,2) DEFAULT 0.00,
            adjustment_reason TEXT DEFAULT NULL,
            adjusted_at DATETIME DEFAULT NULL,
            adjusted_by BIGINT(20) UNSIGNED DEFAULT NULL,
            status ENUM('open', 'closed', 'awaiting_invoice', 'awaiting_rpa', 'invoice_received', 'approved', 'rejected', 'paid') DEFAULT 'open',
            deferred TINYINT(1) DEFAULT 0,
            deferred_at DATETIME DEFAULT NULL,
            deferred_by BIGINT(20) UNSIGNED DEFAULT NULL,
            deferred_reason TEXT DEFAULT NULL,
            original_period_month INT(2) DEFAULT NULL,
            original_period_year INT(4) DEFAULT NULL,
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
            KEY affiliate_status (affiliate_id, status),
            KEY deferred (deferred)
        ) $charset_collate;";
        dbDelta($sql);
        
        // 5. Tabela de visitas (com campos enriquecidos v1.2.0)
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_visits (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id BIGINT(20) UNSIGNED NOT NULL,
            visitor_ip VARCHAR(45) DEFAULT NULL,
            visitor_hash VARCHAR(64) DEFAULT NULL,
            referral_url TEXT DEFAULT NULL,
            landing_page TEXT DEFAULT NULL,
            utm_source VARCHAR(100) DEFAULT NULL,
            utm_medium VARCHAR(100) DEFAULT NULL,
            utm_campaign VARCHAR(100) DEFAULT NULL,
            utm_term VARCHAR(100) DEFAULT NULL,
            utm_content VARCHAR(100) DEFAULT NULL,
            traffic_source VARCHAR(50) DEFAULT NULL,
            device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT NULL,
            browser VARCHAR(50) DEFAULT NULL,
            converted TINYINT(1) DEFAULT 0,
            order_id BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY visitor_hash (visitor_hash),
            KEY created_at (created_at),
            KEY traffic_source (traffic_source),
            KEY device_type (device_type),
            KEY utm_source (utm_source),
            KEY created_converted (created_at, converted)
        ) $charset_collate;";
        dbDelta($sql);
        
        // 6. Tabela de materiais
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
        
        // 7. Tabela de FAQ
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
            KEY is_active (is_active),
            KEY display_order (display_order)
        ) $charset_collate;";
        dbDelta($sql);
        
        // 8. Tabela de log de atividades
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
        
        // 9. Tabela de cache de estatísticas de afiliados (v1.2.0)
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_affiliate_stats_cache (
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
        
        // 10. Tabela de restrições de produtos por afiliado (v1.3.0)
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_product_restrictions (
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
        
        // 11. Tabela de ajustes manuais (v1.4.0)
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_adjustments (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id BIGINT(20) UNSIGNED NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            reason TEXT NOT NULL,
            status ENUM('pending', 'closed', 'paid', 'cancelled') DEFAULT 'pending',
            closing_id BIGINT(20) UNSIGNED DEFAULT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            cancelled_at DATETIME DEFAULT NULL,
            cancelled_by BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY status (status),
            KEY closing_id (closing_id),
            KEY created_at (created_at),
            KEY affiliate_status (affiliate_id, status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // 12. Tabela de versões dos termos (v1.6.0)
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_terms_versions (
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
        
        // 13. Tabela de aceites dos termos (v1.6.0)
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_terms_acceptances (
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
        
        // 14. Tabela de notificações de termos (v1.6.0)
        $sql = "CREATE TABLE {$wpdb->prefix}lrp_terms_notifications (
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
    }

    /**
     * Cria roles e capabilities
     */
    private static function create_roles() {
        $all_lrp_caps = [
            'read'                   => true,
            'lrp_manage_affiliates'  => true,
            'lrp_manage_commissions' => true,
            'lrp_manage_settings'    => true,
            'lrp_view_reports'       => true,
            'lrp_manage_invoices'    => true,
            'lrp_manage_payments'    => true,
        ];

        // Role: Afiliado/Parceiro
        add_role('lrp_affiliate', __('Parceiro', 'lab-resumos-parceiros'), [
            'read' => true,
        ]);
        
        // Role: Contador/Financeiro (v1.7.0: acesso completo ao plugin)
        add_role('lrp_accountant', __('Contador', 'lab-resumos-parceiros'), $all_lrp_caps);
        
        // Atualiza role existente (add_role não atualiza se já existe)
        $accountant = get_role('lrp_accountant');
        if ($accountant) {
            foreach ($all_lrp_caps as $cap => $grant) {
                $accountant->add_cap($cap);
            }
        }

        // Role: Gerente de Afiliados (v1.7.5)
        // Acesso SOMENTE às páginas Dashboard e Afiliados do plugin.
        // Não enxerga demais menus do WordPress pois só tem `read` + `lrp_manage_affiliates`.
        $affiliate_manager_caps = [
            'read'                  => true,
            'lrp_manage_affiliates' => true,
        ];
        add_role('lrp_affiliate_manager', __('Gerente de Afiliados', 'lab-resumos-parceiros'), $affiliate_manager_caps);

        // Garante caps atualizadas mesmo se a role já existe
        $affiliate_manager = get_role('lrp_affiliate_manager');
        if ($affiliate_manager) {
            foreach ($affiliate_manager_caps as $cap => $grant) {
                $affiliate_manager->add_cap($cap);
            }
            // Remove qualquer capability extra que não deveria estar presente
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
        
        // Adiciona capabilities ao admin
        $admin = get_role('administrator');
        if ($admin) {
            foreach ($all_lrp_caps as $cap => $grant) {
                if ($cap !== 'read') {
                    $admin->add_cap($cap);
                }
            }
        }
    }

    /**
     * Cria páginas do plugin
     */
    private static function create_pages() {
        // Verifica se páginas já existem
        $dashboard_page_id = get_option('lrp_dashboard_page_id');
        $registration_page_id = get_option('lrp_registration_page_id');
        $terms_page_id = get_option('lrp_terms_page_id');
        
        // Página do Dashboard do Afiliado
        if (!$dashboard_page_id || !get_post($dashboard_page_id)) {
            $dashboard_page = wp_insert_post([
                'post_title'   => __('Meu Painel de Parceiro', 'lab-resumos-parceiros'),
                'post_content' => '[lrp_affiliate_dashboard]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => 'meu-painel-parceiro',
            ]);
            
            if ($dashboard_page && !is_wp_error($dashboard_page)) {
                update_option('lrp_dashboard_page_id', $dashboard_page);
            }
        }
        
        // Página de Cadastro
        if (!$registration_page_id || !get_post($registration_page_id)) {
            $registration_page = wp_insert_post([
                'post_title'   => __('Seja um Parceiro', 'lab-resumos-parceiros'),
                'post_content' => '[lrp_affiliate_registration]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => 'seja-parceiro',
            ]);
            
            if ($registration_page && !is_wp_error($registration_page)) {
                update_option('lrp_registration_page_id', $registration_page);
            }
        }
        
        // Página de Termos de Afiliação (v1.6.0)
        if (!$terms_page_id || !get_post($terms_page_id)) {
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
    }

    /**
     * Cria FAQs padrão
     */
    private static function create_default_faqs() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lrp_faq';
        
        // Verifica se já tem FAQs
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > 0) {
            return;
        }
        
        // FAQs usam placeholders que são substituídos pelos valores específicos de cada afiliado:
        // {comissao_cupom}, {comissao_link}, {comissao_l2}, {comissao_l3}, {desconto_cliente}, {cookie_dias}, {cupom}
        $faqs = [
            // Como funciona
            [
                'question' => 'Como funciona o Programa de Parceiros?',
                'answer' => 'Você divulga nossos cursos usando seu cupom exclusivo ou link de afiliado. Quando alguém compra usando seu cupom ou link, você ganha uma comissão sobre a venda.',
                'category' => 'como-funciona',
                'display_order' => 1,
            ],
            [
                'question' => 'Qual a diferença entre usar cupom e link?',
                'answer' => '<p><strong>Cupom:</strong> 100% certeza de atribuição. Comissão: {comissao_cupom}.</p><p><strong>Link:</strong> Cookie de {cookie_dias} dias. Comissão: {comissao_link}.</p><p>Recomendamos sempre incentivar o uso do cupom!</p>',
                'category' => 'como-funciona',
                'display_order' => 2,
            ],
            // Comissões
            [
                'question' => 'Quanto eu ganho por venda?',
                'answer' => '<ul><li>Via cupom: {comissao_cupom}</li><li>Via link: {comissao_link}</li><li>Sub-afiliado (nível 2): {comissao_l2}</li><li>Nível 3: {comissao_l3}</li></ul>',
                'category' => 'comissoes',
                'display_order' => 1,
            ],
            [
                'question' => 'A comissão é calculada sobre qual valor?',
                'answer' => 'Sempre sobre o valor PAGO pelo cliente (após descontos).',
                'category' => 'comissoes',
                'display_order' => 2,
            ],
            [
                'question' => 'O que acontece se o cliente for aluno Guruja?',
                'answer' => 'Se o desconto Guruja for maior, o cliente recebe o Guruja. Você ainda ganha comissão sobre o valor pago.',
                'category' => 'comissoes',
                'display_order' => 3,
            ],
            // Pagamentos
            [
                'question' => 'Quando recebo minhas comissões?',
                'answer' => 'Fechamento no dia 1 de cada mês. Se tiver R$ 200+, envie sua NF. Pagamento via PIX em até 5 dias úteis após validação.',
                'category' => 'pagamentos',
                'display_order' => 1,
            ],
            [
                'question' => 'E se eu não atingir o mínimo de R$ 200?',
                'answer' => 'Seu saldo acumula para o próximo mês. Você não perde nada!',
                'category' => 'pagamentos',
                'display_order' => 2,
            ],
            [
                'question' => 'Preciso emitir Nota Fiscal?',
                'answer' => 'Sim, NF de prestação de serviços. Os dados da empresa para emissão ficam na aba Financeiro.',
                'category' => 'pagamentos',
                'display_order' => 3,
            ],
            // Rede
            [
                'question' => 'O que é a Minha Rede?',
                'answer' => 'Convide outros parceiros usando seu link de convite. Você ganha {comissao_l2} das vendas deles e {comissao_l3} das vendas dos indicados deles.',
                'category' => 'rede',
                'display_order' => 1,
            ],
            [
                'question' => 'Como convido alguém para ser parceiro?',
                'answer' => 'Use seu link de convite disponível na aba "Minha Rede" do seu painel.',
                'category' => 'rede',
                'display_order' => 2,
            ],
            // Rede - Regra de Atividade/Compressão (v1.5.0)
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
        
        foreach ($faqs as $faq) {
            $wpdb->insert($table, array_merge($faq, [
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ]));
        }
    }

    /**
     * Agenda cron jobs
     */
    private static function schedule_cron() {
        // Verificação diária (executa fechamento no dia 1)
        if (!wp_next_scheduled('lrp_daily_check')) {
            wp_schedule_event(time(), 'daily', 'lrp_daily_check');
        }
        
        // Limpeza de dados expirados (diário)
        if (!wp_next_scheduled('lrp_cleanup_expired')) {
            wp_schedule_event(time(), 'daily', 'lrp_cleanup_expired');
        }
        
        // Resumo semanal para admin
        if (!wp_next_scheduled('lrp_weekly_summary')) {
            wp_schedule_event(time(), 'weekly', 'lrp_weekly_summary');
        }
        
        // Cálculo de estatísticas de afiliados (horário) - v1.2.0
        if (!wp_next_scheduled('lrp_calculate_stats')) {
            wp_schedule_event(time(), 'hourly', 'lrp_calculate_stats');
        }
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
            'default_can_self_refer' => true,
            'company_name' => 'SOLUCOES EDUCACIONAIS INTELIGENTES LTDA',
            'company_cnpj' => '',
            'company_address' => '',
            'accountant_email' => 'financeiro@labresumos.com.br',
            'admin_email' => '',
            'auto_approve' => false,
            'debug_mode' => false,
            // Configurações de RPA e periodicidade (v1.4.0)
            'default_payment_period_months' => 3,
            'allow_affiliate_defer' => true,
            'defer_message' => 'Você pode adiar o recebimento para o próximo período. O saldo será acumulado.',
            'rpa_service_description' => 'Serviços de divulgação e indicação comercial',
        ];
        
        if (!get_option('lrp_settings')) {
            update_option('lrp_settings', $defaults);
        }
    }
}

