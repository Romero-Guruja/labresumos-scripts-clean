# Programa de Parceiros Lab Resumos - Parte 2: Estrutura Técnica

## 1. Estrutura de Arquivos

```
lab-resumos-parceiros/
├── lab-resumos-parceiros.php              # Arquivo principal
├── uninstall.php                          # Limpeza na desinstalação
├── readme.txt                             # Readme WordPress
│
├── includes/
│   ├── class-lrp-loader.php               # Carregador de dependências
│   ├── class-lrp-activator.php            # Ativação (criar tabelas, etc)
│   ├── class-lrp-deactivator.php          # Desativação
│   │
│   ├── core/
│   │   ├── class-lrp-affiliate.php        # Modelo de Afiliado
│   │   ├── class-lrp-commission.php       # Modelo de Comissão
│   │   ├── class-lrp-referral.php         # Modelo de Referral/Venda
│   │   ├── class-lrp-payout.php           # Modelo de Pagamento/Fechamento
│   │   └── class-lrp-settings.php         # Gerenciador de Configurações
│   │
│   ├── tracking/
│   │   ├── class-lrp-cookie-tracker.php   # Rastreamento por cookie
│   │   ├── class-lrp-coupon-handler.php   # Handler de cupons
│   │   └── class-lrp-attribution.php      # Lógica de atribuição
│   │
│   ├── integrations/
│   │   ├── class-lrp-woocommerce.php      # Integração WooCommerce
│   │   └── class-lrp-guruja.php           # Integração com plugin Guruja
│   │
│   ├── multilevel/
│   │   └── class-lrp-network.php          # Lógica multi-nível
│   │
│   ├── financial/
│   │   ├── class-lrp-calculator.php       # Cálculo de comissões
│   │   └── class-lrp-closing.php          # Fechamento mensal (inclui gestão de NFs)
│   │
│   ├── emails/
│   │   ├── class-lrp-email-manager.php    # Gerenciador de emails
│   │   └── templates/                     # Templates HTML de email
│   │       ├── welcome.php
│   │       ├── new-sale.php
│   │       ├── monthly-closing.php
│   │       ├── invoice-approved.php
│   │       ├── invoice-rejected.php
│   │       ├── payment-received.php
│   │       ├── new-sub-affiliate.php
│   │       ├── sub-affiliate-sale.php
│   │       ├── admin-new-affiliate.php
│   │       ├── admin-invoice-received.php
│   │       └── accountant-invoice.php
│   │
│   └── ajax/
│       ├── class-lrp-ajax-public.php      # AJAX frontend
│       └── class-lrp-ajax-admin.php       # AJAX admin
│
├── admin/
│   ├── class-lrp-admin.php                # Classe principal admin
│   ├── class-lrp-admin-affiliates.php     # Gestão de afiliados
│   ├── class-lrp-admin-commissions.php    # Gestão de comissões
│   ├── class-lrp-admin-payouts.php        # Gestão de pagamentos
│   ├── class-lrp-admin-settings.php       # Configurações
│   ├── class-lrp-admin-reports.php        # Relatórios
│   ├── class-lrp-admin-materials.php      # Materiais de divulgação
│   ├── class-lrp-admin-faq.php            # Gestão de FAQ
│   │
│   ├── partials/                          # Views do admin
│   │   ├── dashboard.php
│   │   ├── affiliates-list.php
│   │   ├── affiliate-edit.php
│   │   ├── commissions-list.php
│   │   ├── payouts-list.php
│   │   ├── settings.php
│   │   ├── reports.php
│   │   ├── materials.php
│   │   └── faq.php
│   │
│   ├── css/
│   │   └── lrp-admin.css
│   └── js/
│       └── lrp-admin.js
│
├── public/
│   ├── class-lrp-public.php               # Classe principal pública
│   ├── class-lrp-dashboard.php            # Dashboard do afiliado
│   ├── class-lrp-registration.php         # Registro de afiliados
│   │
│   ├── partials/                          # Views públicas
│   │   ├── dashboard/
│   │   │   ├── main.php                   # Layout principal
│   │   │   ├── links.php                  # Aba de links e cupons
│   │   │   ├── sales.php                  # Aba de vendas
│   │   │   ├── network.php                # Aba de sub-afiliados
│   │   │   ├── financial.php              # Aba financeira
│   │   │   ├── materials.php              # Aba de materiais
│   │   │   ├── faq.php                    # Aba de FAQ
│   │   │   └── profile.php                # Aba de perfil/dados bancários
│   │   ├── registration-form.php
│   │   └── checkout-notice.php
│   │
│   ├── css/
│   │   └── lrp-public.css
│   └── js/
│       ├── lrp-tracking.js                # Script de tracking (cookie)
│       └── lrp-dashboard.js               # Scripts do dashboard
│
├── accountant/                            # Área do contador
│   ├── class-lrp-accountant.php
│   ├── partials/
│   │   ├── dashboard.php
│   │   ├── pending-invoices.php
│   │   └── payment-confirm.php
│   ├── css/
│   │   └── lrp-accountant.css
│   └── js/
│       └── lrp-accountant.js
│
└── languages/
    └── lab-resumos-parceiros-pt_BR.po
```

---

## 2. Banco de Dados - Tabelas

### 2.1 Tabela: `{prefix}lrp_affiliates`

Armazena os afiliados do programa.

```sql
CREATE TABLE {prefix}lrp_affiliates (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    status ENUM('pending', 'active', 'inactive', 'rejected') DEFAULT 'pending',
    
    -- Códigos únicos
    coupon_code VARCHAR(50) NOT NULL,
    referral_code VARCHAR(50) NOT NULL,
    
    -- Multi-nível
    sponsor_id BIGINT(20) UNSIGNED DEFAULT NULL,
    level INT(11) DEFAULT 1,
    
    -- Configurações individuais (NULL = usa global)
    commission_rate_coupon DECIMAL(5,2) DEFAULT NULL,
    commission_rate_link DECIMAL(5,2) DEFAULT NULL,
    commission_rate_l2 DECIMAL(5,2) DEFAULT NULL,
    commission_rate_l3 DECIMAL(5,2) DEFAULT NULL,
    cookie_days INT(11) DEFAULT NULL,
    
    -- Regras especiais
    guruja_rule ENUM('higher_discount', 'affiliate_priority', 'guruja_priority', 'no_commission') DEFAULT NULL,
    can_self_refer TINYINT(1) DEFAULT 0,
    
    -- Dados financeiros
    payment_method ENUM('pix', 'bank_transfer') DEFAULT 'pix',
    pix_key_type ENUM('cpf', 'cnpj', 'email', 'phone', 'random') DEFAULT NULL,
    -- IMPORTANTE: Chaves PIX devem ser criptografadas antes de salvar. Usar métodos encrypt_pix_key() e decrypt_pix_key()
    pix_key VARCHAR(255) DEFAULT NULL,
    bank_name VARCHAR(100) DEFAULT NULL,
    bank_agency VARCHAR(20) DEFAULT NULL,
    bank_account VARCHAR(30) DEFAULT NULL,
    bank_account_type ENUM('checking', 'savings') DEFAULT 'checking',
    holder_name VARCHAR(255) DEFAULT NULL,
    holder_document VARCHAR(20) DEFAULT NULL,
    
    -- Estatísticas (cache)
    total_sales INT(11) DEFAULT 0,
    total_revenue DECIMAL(15,2) DEFAULT 0.00,
    total_commissions DECIMAL(15,2) DEFAULT 0.00,
    total_paid DECIMAL(15,2) DEFAULT 0.00,
    current_balance DECIMAL(15,2) DEFAULT 0.00,
    
    -- Notas
    application_notes TEXT DEFAULT NULL,
    admin_notes TEXT DEFAULT NULL,
    application_ip VARCHAR(45) DEFAULT NULL,
    
    -- Timestamps
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Tabela: `{prefix}lrp_referrals`

Cada venda atribuída a um afiliado.

```sql
CREATE TABLE {prefix}lrp_referrals (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    affiliate_id BIGINT(20) UNSIGNED NOT NULL,
    order_id BIGINT(20) UNSIGNED NOT NULL,
    
    -- Atribuição
    attribution_type ENUM('coupon', 'link', 'direct') NOT NULL,
    coupon_used VARCHAR(50) DEFAULT NULL,
    
    -- Valores
    order_total DECIMAL(15,2) NOT NULL,
    discount_amount DECIMAL(15,2) DEFAULT 0.00,
    discount_source ENUM('affiliate', 'guruja', 'other') DEFAULT NULL,
    commission_base DECIMAL(15,2) NOT NULL,  -- Valor efetivamente pago
    
    -- Status
    status ENUM('pending', 'approved', 'rejected', 'refunded') DEFAULT 'pending',
    
    -- Cliente
    customer_id BIGINT(20) UNSIGNED DEFAULT NULL,
    customer_email VARCHAR(255) DEFAULT NULL,
    is_guruja_student TINYINT(1) DEFAULT 0,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY order_id (order_id),
    KEY affiliate_id (affiliate_id),
    KEY status (status),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.3 Tabela: `{prefix}lrp_commissions`

Comissões geradas (pode haver múltiplas por venda devido ao multi-nível).

```sql
CREATE TABLE {prefix}lrp_commissions (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    referral_id BIGINT(20) UNSIGNED NOT NULL,
    affiliate_id BIGINT(20) UNSIGNED NOT NULL,
    
    -- Tipo
    commission_type ENUM('direct', 'level_2', 'level_3') NOT NULL,
    source_affiliate_id BIGINT(20) UNSIGNED DEFAULT NULL,
    
    -- Valores
    commission_rate DECIMAL(5,2) NOT NULL,
    commission_amount DECIMAL(15,2) NOT NULL,
    
    -- Status e fechamento
    status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
    closing_id BIGINT(20) UNSIGNED DEFAULT NULL,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY referral_id (referral_id),
    KEY affiliate_id (affiliate_id),
    KEY status (status),
    KEY closing_id (closing_id),
    KEY created_at (created_at),
    KEY affiliate_status_created (affiliate_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.4 Tabela: `{prefix}lrp_closings`

Fechamentos mensais.

```sql
CREATE TABLE {prefix}lrp_closings (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    affiliate_id BIGINT(20) UNSIGNED NOT NULL,
    
    -- Período
    period_month INT(2) NOT NULL,
    period_year INT(4) NOT NULL,
    
    -- Valores
    total_sales INT(11) DEFAULT 0,
    total_revenue DECIMAL(15,2) DEFAULT 0.00,
    total_commissions DECIMAL(15,2) DEFAULT 0.00,
    
    -- Status
    status ENUM('open', 'closed', 'awaiting_invoice', 'invoice_received', 'approved', 'rejected', 'paid') DEFAULT 'open',
    
    -- Nota Fiscal
    invoice_file VARCHAR(255) DEFAULT NULL,
    invoice_number VARCHAR(50) DEFAULT NULL,
    invoice_uploaded_at DATETIME DEFAULT NULL,
    invoice_notes TEXT DEFAULT NULL,
    
    -- Pagamento
    payment_proof_file VARCHAR(255) DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    paid_by BIGINT(20) UNSIGNED DEFAULT NULL,
    payment_notes TEXT DEFAULT NULL,
    
    -- Rejeição
    rejection_reason TEXT DEFAULT NULL,
    rejected_at DATETIME DEFAULT NULL,
    rejected_by BIGINT(20) UNSIGNED DEFAULT NULL,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME DEFAULT NULL,
    
    PRIMARY KEY (id),
    UNIQUE KEY affiliate_period (affiliate_id, period_month, period_year),
    KEY status (status),
    KEY period_lookup (period_year, period_month),
    KEY affiliate_status (affiliate_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.5 Tabela: `{prefix}lrp_visits`

Rastreamento de visitas para analytics.

```sql
CREATE TABLE {prefix}lrp_visits (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    affiliate_id BIGINT(20) UNSIGNED NOT NULL,
    
    -- Dados da visita
    -- IMPORTANTE: IPs são dados pessoais sob LGPD
    -- Considerar hash ou anonimização após período de retenção (90 dias)
    -- Adicionar cron para limpar IPs antigos
    visitor_ip VARCHAR(45) DEFAULT NULL,
    visitor_hash VARCHAR(64) DEFAULT NULL,
    referral_url TEXT DEFAULT NULL,
    landing_page TEXT DEFAULT NULL,
    
    -- Conversão
    converted TINYINT(1) DEFAULT 0,
    order_id BIGINT(20) UNSIGNED DEFAULT NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY affiliate_id (affiliate_id),
    KEY visitor_hash (visitor_hash),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.6 Tabela: `{prefix}lrp_materials`

Materiais de divulgação.

```sql
CREATE TABLE {prefix}lrp_materials (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.7 Tabela: `{prefix}lrp_faq`

Perguntas frequentes editáveis.

```sql
CREATE TABLE {prefix}lrp_faq (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.8 Tabela: `{prefix}lrp_activity_log`

Log de atividades para auditoria.

```sql
CREATE TABLE {prefix}lrp_activity_log (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Metadados

### 3.1 User Meta (WordPress)

```php
// Afiliados
'lrp_affiliate_id' => 123,
'lrp_is_affiliate' => true,

// Contador
'lrp_is_accountant' => true,
```

### 3.2 Order Meta (WooCommerce)

```php
'_lrp_affiliate_id' => 123,
'_lrp_referral_id' => 456,
'_lrp_attribution_type' => 'coupon',  // coupon, link, direct
'_lrp_coupon_used' => 'JOAO10',
'_lrp_commission_amount' => 29.70,
'_lrp_is_guruja_discount' => true,
'_lrp_tracking_cookie' => 'abc123...',
```

### 3.3 Coupon Meta (WooCommerce)

```php
'_lrp_affiliate_id' => 123,
'_lrp_is_affiliate_coupon' => true,
```

---

## 4. Opções do WordPress (wp_options)

```php
// Configurações principais
'lrp_settings' => [
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
],

// IDs de páginas criadas
'lrp_dashboard_page_id' => 123,
'lrp_registration_page_id' => 124,

// Versão do banco
'lrp_db_version' => '1.0.0',
```

---

## 5. Roles e Capabilities

### 5.1 Roles Criadas

```php
// Afiliado
add_role('lrp_affiliate', 'Parceiro', [
    'read' => true,
]);

// Contador
add_role('lrp_accountant', 'Contador', [
    'read' => true,
    'lrp_manage_invoices' => true,
    'lrp_manage_payments' => true,
]);
```

### 5.2 Capabilities do Admin

```php
'lrp_manage_affiliates'
'lrp_manage_commissions'
'lrp_manage_settings'
'lrp_view_reports'
'lrp_manage_invoices'
'lrp_manage_payments'
```

---

## 6. Cron Jobs

```php
// Fechamento mensal (dia 1 às 00:01)
'lrp_monthly_closing' => 'monthly'

// Limpeza de dados expirados (diário)
'lrp_cleanup_expired' => 'daily'

// Resumo semanal para admin
'lrp_weekly_summary' => 'weekly'
```