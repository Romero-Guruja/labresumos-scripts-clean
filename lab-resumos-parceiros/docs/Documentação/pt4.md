# Programa de Parceiros Lab Resumos - Parte 4: Dashboard e Interfaces

## 1. Dashboard do Afiliado - Abas

1. **Início** - Visão geral com métricas
2. **Links e Cupons** - Ferramentas de divulgação
3. **Vendas** - Histórico de vendas
4. **Minha Rede** - Sub-afiliados
5. **Financeiro** - Fechamentos e NFs
6. **Materiais** - Banners, textos
7. **FAQ** - Perguntas frequentes
8. **Meu Perfil** - Dados bancários

---

## 2. Componentes do Dashboard

### 2.1 Aba Início - Dados a Exibir

```php
// Cards principais
$total_ganho = $affiliate->get_total_commissions();      // Total histórico
$a_receber = $affiliate->get_current_balance();          // Saldo atual
$vendas_mes = $this->get_sales_this_month($affiliate);   // Vendas do mês

// Cards secundários (rede)
$network_stats = LRP_Network::instance()->get_network_stats($affiliate->get_id());
$sub_afiliados = $network_stats['total_affiliates'];
$vendas_rede = $network_stats['total_sales'];
$comissao_rede = $network_stats['total_commissions'];

// Tabela de vendas recentes (últimas 5)
$recent_sales = LRP_Referral::get_recent_by_affiliate($affiliate->get_id(), 5);

// Alertas (se houver ação necessária)
$pending_closing = LRP_Closing::get_pending_invoice($affiliate->get_id());
```

### 2.2 Aba Links e Cupons - Dados a Exibir

```php
// Cupom
$coupon_code = $affiliate->get_coupon_code();
$customer_discount = LRP_Settings::instance()->get('default_customer_discount');
$coupon_commission = $affiliate->get_commission_rate('coupon');

// Link principal
$referral_url = $affiliate->get_referral_url();
$link_commission = $affiliate->get_commission_rate('link');
$cookie_days = $affiliate->get_cookie_days();

// Link para produto específico
$products = wc_get_products(['limit' => -1, 'status' => 'publish']);

// Link de sponsor
$sponsor_url = $affiliate->get_sponsor_url();
$l2_commission = $affiliate->get_commission_rate('l2');
$l3_commission = $affiliate->get_commission_rate('l3');
```

### 2.3 Aba Vendas - Dados a Exibir

```php
// Filtros
$filters = [
    'period' => $_GET['period'] ?? 'this_month',  // this_month, last_month, custom
    'type' => $_GET['type'] ?? 'all',             // all, coupon, link, network
    'status' => $_GET['status'] ?? 'all',         // all, pending, approved, paid
];

// Listagem paginada
$sales = LRP_Referral::get_by_affiliate($affiliate->get_id(), [
    'filters' => $filters,
    'page' => $_GET['paged'] ?? 1,
    'per_page' => 20,
]);

// Cada venda mostra:
// - Data
// - Produto(s)
// - Valor do pedido
// - Valor da comissão
// - Tipo (cupom/link/rede)
// - Status
// - Se foi desconto Guruja
```

### 2.4 Aba Minha Rede - Dados a Exibir

```php
// Estatísticas gerais
$network_stats = LRP_Network::instance()->get_network_stats($affiliate->get_id());

// Árvore de afiliados
$downline = LRP_Network::instance()->get_downline_tree($affiliate->get_id(), 2);

// Para cada sub-afiliado:
// - Nome
// - Data de cadastro
// - Nível (2 ou 3)
// - Total de vendas
// - Total de receita
// - Sua comissão sobre ele

// Link de convite
$sponsor_url = $affiliate->get_sponsor_url();
```

### 2.5 Aba Financeiro - Dados a Exibir

```php
// Resumo
$total_ganho = $affiliate->get_total_commissions();
$total_pago = $affiliate->get_total_paid();
$saldo = $affiliate->get_current_balance();
$minimo_saque = LRP_Settings::instance()->get('minimum_payout');

// Fechamentos
$closings = LRP_Closing::get_by_affiliate($affiliate->get_id());

// Cada fechamento mostra:
// - Período (Mês/Ano)
// - Total de comissões
// - Status (open, closed, awaiting_invoice, invoice_received, approved, rejected, paid)
// - Ações disponíveis por status

// Dados da empresa (para emitir NF)
$company_name = LRP_Settings::instance()->get('company_name');
$company_cnpj = LRP_Settings::instance()->get('company_cnpj');
$company_address = LRP_Settings::instance()->get('company_address');

// Dados bancários do afiliado
$payment_data = [
    'method' => $affiliate->data['payment_method'],
    'pix_key_type' => $affiliate->data['pix_key_type'],
    'pix_key' => $affiliate->data['pix_key'],
    // ... outros campos
];
```

### 2.6 Aba Materiais - Dados a Exibir

```php
// Materiais por categoria
$materials = LRP_Material::get_active_grouped_by_category();

// Categorias sugeridas:
// - Banners para redes sociais
// - Stories
// - Textos prontos (copy)
// - Vídeos explicativos
// - Documentos/PDFs

// Cada material:
// - Título
// - Descrição
// - Tipo (image, text, video, document)
// - Ação (download, copiar, assistir)
```

### 2.7 Aba FAQ - Dados a Exibir

```php
// FAQs por categoria
$faqs = LRP_FAQ::get_active_grouped_by_category();

// Categorias sugeridas:
// - Como funciona
// - Comissões
// - Pagamentos
// - Links e cupons
// - Minha rede

// Cada FAQ:
// - Pergunta
// - Resposta (com suporte a HTML básico)
```

### 2.8 Aba Meu Perfil - Dados a Exibir/Editar

```php
// Dados pessoais (somente leitura, vem do WP)
$user = $affiliate->get_user();
$name = $user->display_name;
$email = $user->user_email;

// Dados editáveis
$editable_fields = [
    'payment_method',      // pix ou bank_transfer
    'pix_key_type',        // cpf, cnpj, email, phone, random
    'pix_key',
    'bank_name',
    'bank_agency',
    'bank_account',
    'bank_account_type',   // checking, savings
    'holder_name',
    'holder_document',
];
```

---

## 3. Área do Contador

### 3.1 Dashboard do Contador

```php
// Contadores
$pending_invoices = LRP_Closing::count_by_status('invoice_received');
$pending_payments = LRP_Closing::count_by_status('approved');
$total_pending = LRP_Closing::sum_pending_amount();

// Lista de NFs pendentes
$invoices_to_review = LRP_Closing::get_by_status('invoice_received', [
    'orderby' => 'invoice_uploaded_at',
    'order' => 'ASC',
]);

// Lista de pagamentos aprovados (aguardando comprovante)
$payments_pending = LRP_Closing::get_by_status('approved', [
    'orderby' => 'updated_at',
    'order' => 'ASC',
]);
```

### 3.2 Tela de Análise de NF

```php
// Dados do fechamento
$closing = new LRP_Closing($closing_id);
$affiliate = new LRP_Affiliate($closing->affiliate_id);

// Detalhamento das comissões
$commissions = LRP_Commission::get_by_closing($closing_id);

// Arquivo da NF
$invoice_url = $closing->get_invoice_url();

// Dados bancários
$payment_data = $affiliate->get_payment_data();

// Ações disponíveis:
// - Aprovar NF
// - Rejeitar NF (com motivo obrigatório)
```

### 3.3 Tela de Confirmar Pagamento

```php
// Dados do pagamento
$closing = new LRP_Closing($closing_id);
$affiliate = new LRP_Affiliate($closing->affiliate_id);

// Dados para PIX (para copiar)
$pix_data = $affiliate->get_pix_data();

// Upload de comprovante
// - Aceita: PDF, PNG, JPG
// - Campo de observações opcional

// Ação: Confirmar pagamento
// - Faz upload do comprovante
// - Muda status para 'paid'
// - Envia email para afiliado
```

---

## 4. Shortcodes

```php
// Dashboard completo (requer login + ser afiliado ativo)
[lrp_affiliate_dashboard]

// Formulário de cadastro (público)
[lrp_affiliate_registration]

// Widget de estatísticas (para sidebar)
[lrp_affiliate_stats]

// Exibe link de afiliado (se logado como afiliado)
[lrp_affiliate_link product_id="123"]

// Exibe cupom do afiliado (se logado como afiliado)
[lrp_affiliate_coupon]
```

---

## 5. Páginas Criadas na Ativação

1. **Meu Painel de Parceiro** (`/meu-painel-parceiro/`)
   - Shortcode: `[lrp_affiliate_dashboard]`

2. **Seja um Parceiro** (`/seja-parceiro/`)
   - Shortcode: `[lrp_affiliate_registration]`

---

## 6. JavaScript do Dashboard

### 6.1 Funcionalidades

```javascript
// lrp-dashboard.js

const LRP_Dashboard = {
    
    init() {
        this.bindTabs();
        this.bindCopyButtons();
        this.bindQRCodeButtons();
        this.bindShareButtons();
        this.bindFilters();
        this.bindFileUpload();
        this.bindProfileForm();
    },
    
    // Navegação por abas
    bindTabs() {
        document.querySelectorAll('.lrp-tab-link').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                const target = e.target.dataset.tab;
                this.switchTab(target);
                // Atualiza URL sem reload
                history.pushState({}, '', `?tab=${target}`);
            });
        });
    },
    
    // Botões de copiar
    bindCopyButtons() {
        document.querySelectorAll('[data-copy]').forEach(btn => {
            btn.addEventListener('click', () => {
                const text = btn.dataset.copy;
                navigator.clipboard.writeText(text).then(() => {
                    this.showToast('Copiado!');
                });
            });
        });
    },
    
    // Gerar QR Code
    bindQRCodeButtons() {
        document.querySelectorAll('[data-qrcode]').forEach(btn => {
            btn.addEventListener('click', () => {
                const url = btn.dataset.qrcode;
                this.showQRCodeModal(url);
            });
        });
    },
    
    // Compartilhar no WhatsApp
    bindShareButtons() {
        document.querySelectorAll('[data-share-whatsapp]').forEach(btn => {
            btn.addEventListener('click', () => {
                const text = btn.dataset.shareWhatsapp;
                const url = `https://wa.me/?text=${encodeURIComponent(text)}`;
                window.open(url, '_blank');
            });
        });
    },
    
    // Filtros de vendas
    bindFilters() {
        const filterForm = document.getElementById('lrp-sales-filters');
        if (filterForm) {
            filterForm.addEventListener('change', () => {
                filterForm.submit();
            });
        }
    },
    
    // Upload de NF
    bindFileUpload() {
        const uploadForm = document.getElementById('lrp-invoice-upload');
        if (uploadForm) {
            uploadForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(uploadForm);
                
                try {
                    const response = await this.ajaxPost('lrp_upload_invoice', formData);
                    if (response.success) {
                        this.showToast('NF enviada com sucesso!');
                        location.reload();
                    } else {
                        this.showToast(response.data.message, 'error');
                    }
                } catch (error) {
                    this.showToast('Erro ao enviar NF', 'error');
                }
            });
        }
    },
    
    // Formulário de perfil
    bindProfileForm() {
        const profileForm = document.getElementById('lrp-profile-form');
        if (profileForm) {
            profileForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(profileForm);
                
                try {
                    const response = await this.ajaxPost('lrp_update_profile', formData);
                    if (response.success) {
                        this.showToast('Dados atualizados!');
                    } else {
                        this.showToast(response.data.message, 'error');
                    }
                } catch (error) {
                    this.showToast('Erro ao salvar', 'error');
                }
            });
        }
    },
    
    // Helpers
    async ajaxPost(action, data) {
        data.append('action', action);
        data.append('nonce', lrpDashboard.nonce);
        
        const response = await fetch(lrpDashboard.ajaxUrl, {
            method: 'POST',
            body: data,
        });
        
        return response.json();
    },
    
    showToast(message, type = 'success') {
        // Implementar toast notification
    },
    
    showQRCodeModal(url) {
        // Usar biblioteca QRCode.js para gerar
    }
};

document.addEventListener('DOMContentLoaded', () => {
    LRP_Dashboard.init();
});
```

---

## 7. CSS Base

```css
/* lrp-public.css */

/* Container principal */
.lrp-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* Cards de estatísticas */
.lrp-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.lrp-stat-card {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.lrp-stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #2A6B9F; /* Azul Principal Lab Resumos */
}

.lrp-stat-label {
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}

/* Navegação por abas */
.lrp-tabs {
    display: flex;
    border-bottom: 2px solid #eee;
    margin-bottom: 30px;
    overflow-x: auto;
}

.lrp-tab-link {
    padding: 12px 20px;
    color: #666;
    text-decoration: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    white-space: nowrap;
}

.lrp-tab-link.active {
    color: #2A6B9F;
    border-bottom-color: #2A6B9F;
}

/* Boxes de informação */
.lrp-info-box {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.lrp-info-box-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Botões */
.lrp-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.lrp-btn-primary {
    background: #2A6B9F;
    color: #fff;
}

.lrp-btn-primary:hover {
    background: #1e5680;
}

.lrp-btn-secondary {
    background: #f5f5f5;
    color: #333;
}

.lrp-btn-secondary:hover {
    background: #e5e5e5;
}

/* Tabelas */
.lrp-table {
    width: 100%;
    border-collapse: collapse;
}

.lrp-table th,
.lrp-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.lrp-table th {
    font-weight: 600;
    color: #666;
    font-size: 13px;
    text-transform: uppercase;
}

/* Status badges */
.lrp-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.lrp-badge-success {
    background: #d4edda;
    color: #155724;
}

.lrp-badge-warning {
    background: #fff3cd;
    color: #856404;
}

.lrp-badge-info {
    background: #d1ecf1;
    color: #0c5460;
}

.lrp-badge-danger {
    background: #f8d7da;
    color: #721c24;
}

/* Alertas */
.lrp-alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.lrp-alert-warning {
    background: #fff3cd;
    border-left: 4px solid #F1CC00; /* Amarelo Lab Resumos */
}

.lrp-alert-info {
    background: #e7f3ff;
    border-left: 4px solid #2A6B9F;
}

/* Responsivo */
@media (max-width: 768px) {
    .lrp-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .lrp-tabs {
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
    }
    
    .lrp-table {
        display: block;
        overflow-x: auto;
    }
}
```