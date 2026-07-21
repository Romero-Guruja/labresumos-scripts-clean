# Programa de Parceiros Lab Resumos - Parte 6: Admin, Relatórios, FAQ e Materiais

## 1. Área Administrativa - Menu

```php
<?php
class LRP_Admin {

    public function add_menu() {
        // Menu principal
        add_menu_page(
            'Parceiros', 'Parceiros', 'lrp_manage_affiliates',
            'lrp-dashboard', [$this, 'render_dashboard'],
            'dashicons-groups', 30
        );
        
        // Submenus
        $submenus = [
            ['lrp-dashboard', 'Dashboard', 'lrp_manage_affiliates', 'render_dashboard'],
            ['lrp-affiliates', 'Afiliados', 'lrp_manage_affiliates', 'render_affiliates'],
            ['lrp-commissions', 'Comissões', 'lrp_manage_commissions', 'render_commissions'],
            ['lrp-payouts', 'Pagamentos', 'lrp_manage_payments', 'render_payouts'],
            ['lrp-reports', 'Relatórios', 'lrp_view_reports', 'render_reports'],
            ['lrp-materials', 'Materiais', 'lrp_manage_settings', 'render_materials'],
            ['lrp-faq', 'FAQ', 'lrp_manage_settings', 'render_faq'],
            ['lrp-settings', 'Configurações', 'lrp_manage_settings', 'render_settings'],
        ];
        
        foreach ($submenus as $submenu) {
            add_submenu_page('lrp-dashboard', $submenu[1], $submenu[1], $submenu[2], $submenu[0], [$this, $submenu[3]]);
        }
    }
}
```

---

## 2. Dashboard Admin - Dados Exibidos

```php
// Estatísticas do mês
$stats = [
    'total_affiliates' => COUNT affiliates WHERE status = 'active',
    'pending_affiliates' => COUNT affiliates WHERE status = 'pending',
    'total_sales_month' => COUNT referrals this month,
    'total_revenue_month' => SUM commission_base this month,
    'total_commissions_month' => SUM commission_amount this month,
    'pending_invoices' => COUNT closings WHERE status = 'invoice_received',
    'pending_payments' => COUNT closings WHERE status = 'approved',
];

// Top 10 afiliados do mês
// Últimas 10 vendas
```

---

## 3. Tela de Edição de Afiliado

### 3.1 Campos Editáveis

```php
$fields = [
    // Status
    'status' => ['pending', 'active', 'inactive', 'rejected'],
    
    // Comissões individuais (NULL = usa global)
    'commission_rate_coupon' => float|null,
    'commission_rate_link' => float|null,
    'commission_rate_l2' => float|null,
    'commission_rate_l3' => float|null,
    
    // Cookie
    'cookie_days' => int|null,
    
    // Regra Guruja
    'guruja_rule' => ['higher_discount', 'affiliate_priority', 'guruja_priority', 'no_commission']|null,
    
    // Auto-referência
    'can_self_refer' => bool,
    
    // Notas internas
    'admin_notes' => text,
];
```

### 3.2 Informações Exibidas (somente leitura)

- Dados do usuário (nome, email)
- Cupom e código de referral
- Sponsor (se houver)
- Estatísticas (vendas, receita, comissões)
- Histórico de vendas (últimas 20)
- Sub-afiliados

---

## 4. Página de Configurações

### 4.1 Campos

```php
$settings_fields = [
    // Geral
    'enabled' => bool,
    
    // Comissões padrão
    'default_commission_coupon' => 10.00,
    'default_commission_link' => 5.00,
    'default_commission_l2' => 3.00,
    'default_commission_l3' => 1.00,
    
    // Cookie
    'default_cookie_days' => 60,
    
    // Desconto cliente
    'default_customer_discount' => 20.00,
    
    // Financeiro
    'minimum_payout' => 200.00,
    'closing_day' => 1,
    
    // Guruja
    'default_guruja_rule' => 'higher_discount',
    
    // Empresa
    'company_name' => 'SOLUCOES EDUCACIONAIS INTELIGENTES LTDA',
    'company_cnpj' => '',
    'company_address' => '',
    
    // Emails
    'accountant_email' => 'financeiro@labresumos.com.br',
    'admin_email' => '',
    
    // Aprovação
    'auto_approve' => false,
    
    // Debug
    'debug_mode' => false,
];
```

---

## 5. Relatórios

### 5.1 Tipos de Relatórios

1. **Visão Geral**: Estatísticas do período
2. **Por Afiliado**: Detalhamento individual
3. **Vendas**: Lista de todas as vendas
4. **Comissões**: Lista de todas as comissões
5. **Pagamentos**: Histórico de pagamentos
6. **Rede**: Performance multi-nível

### 5.2 Exportação CSV

```php
public function export_csv($report_type, $start_date, $end_date) {
    $filename = "parceiros-{$report_type}-" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    
    // Headers e dados conforme tipo
    switch ($report_type) {
        case 'affiliates':
            fputcsv($output, ['ID', 'Nome', 'Email', 'Cupom', 'Status', 'Vendas', 'Receita', 'Comissões']);
            // ... dados
            break;
            
        case 'sales':
            fputcsv($output, ['Data', 'Pedido', 'Parceiro', 'Tipo', 'Valor', 'Comissão', 'Status']);
            // ... dados
            break;
            
        case 'commissions':
            fputcsv($output, ['Data', 'Parceiro', 'Pedido', 'Tipo', 'Taxa', 'Valor', 'Status']);
            // ... dados
            break;
    }
    
    fclose($output);
    exit;
}
```

---

## 6. Gestão de FAQ

### 6.1 CRUD

```php
// Criar FAQ
$wpdb->insert($table, [
    'question' => 'Como funciona?',
    'answer' => '<p>Resposta com HTML...</p>',
    'category' => 'como-funciona',
    'display_order' => 1,
    'is_active' => 1,
]);

// Categorias sugeridas
$categories = [
    'como-funciona' => 'Como Funciona',
    'comissoes' => 'Comissões',
    'pagamentos' => 'Pagamentos',
    'rede' => 'Minha Rede',
    'links-cupons' => 'Links e Cupons',
];
```

### 6.2 FAQs Padrão (criadas na ativação)

```php
$default_faqs = [
    // COMO FUNCIONA
    [
        'question' => 'Como funciona o Programa de Parceiros?',
        'answer' => 'Você divulga nossos cursos usando seu cupom exclusivo ou link de afiliado. Quando alguém compra usando seu cupom ou link, você ganha uma comissão sobre a venda.',
        'category' => 'como-funciona',
    ],
    [
        'question' => 'Qual a diferença entre usar cupom e link?',
        'answer' => '<p><strong>Cupom:</strong> 100% certeza de atribuição. Comissão: 10%.</p><p><strong>Link:</strong> Cookie de 60 dias. Comissão: 5%.</p><p>Recomendamos sempre incentivar o uso do cupom!</p>',
        'category' => 'como-funciona',
    ],
    
    // COMISSÕES
    [
        'question' => 'Quanto eu ganho por venda?',
        'answer' => '<ul><li>Via cupom: 10%</li><li>Via link: 5%</li><li>Sub-afiliado (nível 2): 3%</li><li>Nível 3: 1%</li></ul>',
        'category' => 'comissoes',
    ],
    [
        'question' => 'A comissão é calculada sobre qual valor?',
        'answer' => 'Sempre sobre o valor PAGO pelo cliente (após descontos).',
        'category' => 'comissoes',
    ],
    [
        'question' => 'O que acontece se o cliente for aluno Guruja?',
        'answer' => 'Se o desconto Guruja for maior, o cliente recebe o Guruja. Você ainda ganha comissão sobre o valor pago.',
        'category' => 'comissoes',
    ],
    
    // PAGAMENTOS
    [
        'question' => 'Quando recebo minhas comissões?',
        'answer' => 'Fechamento no dia 1 de cada mês. Se tiver R$ 200+, envie sua NF. Pagamento via PIX em até 5 dias úteis após validação.',
        'category' => 'pagamentos',
    ],
    [
        'question' => 'E se eu não atingir o mínimo de R$ 200?',
        'answer' => 'Seu saldo acumula para o próximo mês. Você não perde nada!',
        'category' => 'pagamentos',
    ],
    [
        'question' => 'Preciso emitir Nota Fiscal?',
        'answer' => 'Sim, NF de prestação de serviços. Os dados ficam na aba Financeiro.',
        'category' => 'pagamentos',
    ],
    
    // REDE
    [
        'question' => 'O que é a Minha Rede?',
        'answer' => 'Convide outros parceiros. Você ganha 3% das vendas deles e 1% das vendas dos indicados deles.',
        'category' => 'rede',
    ],
    [
        'question' => 'Como convido alguém para ser parceiro?',
        'answer' => 'Use seu link de convite na aba "Minha Rede".',
        'category' => 'rede',
    ],
];
```

---

## 7. Gestão de Materiais

### 7.1 Tipos de Materiais

```php
$material_types = [
    'image' => 'Imagem (Banner)',
    'text' => 'Texto (Copy)',
    'video' => 'Vídeo',
    'document' => 'Documento',
];

$material_categories = [
    'banners' => 'Banners para Redes Sociais',
    'stories' => 'Stories',
    'textos' => 'Textos Prontos (Copy)',
    'videos' => 'Vídeos',
    'documentos' => 'Documentos',
];
```

### 7.2 CRUD

```php
// Criar material
$data = [
    'title' => 'Banner Instagram - Curso ICMS',
    'description' => 'Tamanho 1080x1080',
    'type' => 'image',
    'file_url' => '/uploads/lrp-materials/banner-icms.png',
    'category' => 'banners',
    'display_order' => 1,
    'is_active' => 1,
];

// Para texto (copy)
$data = [
    'title' => 'Copy para WhatsApp',
    'type' => 'text',
    'content' => 'Quer dominar a Reforma Tributária? 🎯 O Lab Resumos tem o curso perfeito! Use meu cupom NOME10 e ganhe 10% de desconto!',
    'category' => 'textos',
];
```

---

## 8. Views do Admin (Partials)

### 8.1 Template: settings.php

```php
<div class="wrap">
    <h1>Configurações do Programa de Parceiros</h1>
    
    <?php settings_errors('lrp'); ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('lrp_settings'); ?>
        
        <h2>Geral</h2>
        <table class="form-table">
            <tr>
                <th>Programa Ativo</th>
                <td>
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked($settings->get('enabled')); ?>>
                        Ativar programa de parceiros
                    </label>
                </td>
            </tr>
        </table>
        
        <h2>Comissões Padrão</h2>
        <table class="form-table">
            <tr>
                <th>Comissão via Cupom</th>
                <td>
                    <input type="number" name="default_commission_coupon" 
                           value="<?php echo esc_attr($settings->get('default_commission_coupon')); ?>" 
                           step="0.01" min="0" max="100" class="small-text"> %
                </td>
            </tr>
            <tr>
                <th>Comissão via Link</th>
                <td>
                    <input type="number" name="default_commission_link" 
                           value="<?php echo esc_attr($settings->get('default_commission_link')); ?>" 
                           step="0.01" min="0" max="100" class="small-text"> %
                </td>
            </tr>
            <tr>
                <th>Comissão Nível 2</th>
                <td>
                    <input type="number" name="default_commission_l2" 
                           value="<?php echo esc_attr($settings->get('default_commission_l2')); ?>" 
                           step="0.01" min="0" max="100" class="small-text"> %
                    <p class="description">Comissão sobre vendas de sub-afiliados diretos</p>
                </td>
            </tr>
            <tr>
                <th>Comissão Nível 3</th>
                <td>
                    <input type="number" name="default_commission_l3" 
                           value="<?php echo esc_attr($settings->get('default_commission_l3')); ?>" 
                           step="0.01" min="0" max="100" class="small-text"> %
                </td>
            </tr>
        </table>
        
        <h2>Desconto e Cookie</h2>
        <table class="form-table">
            <tr>
                <th>Desconto para Cliente</th>
                <td>
                    <input type="number" name="default_customer_discount" 
                           value="<?php echo esc_attr($settings->get('default_customer_discount')); ?>" 
                           step="0.01" min="0" max="100" class="small-text"> %
                    <p class="description">Desconto que o cliente recebe ao usar cupom de afiliado</p>
                </td>
            </tr>
            <tr>
                <th>Duração do Cookie</th>
                <td>
                    <input type="number" name="default_cookie_days" 
                           value="<?php echo esc_attr($settings->get('default_cookie_days')); ?>" 
                           min="1" max="365" class="small-text"> dias
                </td>
            </tr>
        </table>
        
        <h2>Financeiro</h2>
        <table class="form-table">
            <tr>
                <th>Mínimo para Saque</th>
                <td>
                    R$ <input type="number" name="minimum_payout" 
                              value="<?php echo esc_attr($settings->get('minimum_payout')); ?>" 
                              step="0.01" min="0" class="small-text">
                </td>
            </tr>
        </table>
        
        <h2>Integração Guruja</h2>
        <table class="form-table">
            <tr>
                <th>Regra Padrão</th>
                <td>
                    <select name="default_guruja_rule">
                        <option value="higher_discount" <?php selected($settings->get('default_guruja_rule'), 'higher_discount'); ?>>
                            Cliente recebe o maior desconto
                        </option>
                        <option value="affiliate_priority" <?php selected($settings->get('default_guruja_rule'), 'affiliate_priority'); ?>>
                            Cupom do afiliado sempre prevalece
                        </option>
                        <option value="guruja_priority" <?php selected($settings->get('default_guruja_rule'), 'guruja_priority'); ?>>
                            Guruja sempre prevalece
                        </option>
                        <option value="no_commission" <?php selected($settings->get('default_guruja_rule'), 'no_commission'); ?>>
                            Sem comissão se Guruja aplicar
                        </option>
                    </select>
                </td>
            </tr>
        </table>
        
        <h2>Dados da Empresa (para NF)</h2>
        <table class="form-table">
            <tr>
                <th>Razão Social</th>
                <td>
                    <input type="text" name="company_name" class="regular-text"
                           value="<?php echo esc_attr($settings->get('company_name')); ?>">
                </td>
            </tr>
            <tr>
                <th>CNPJ</th>
                <td>
                    <input type="text" name="company_cnpj" class="regular-text"
                           value="<?php echo esc_attr($settings->get('company_cnpj')); ?>">
                </td>
            </tr>
            <tr>
                <th>Endereço</th>
                <td>
                    <textarea name="company_address" rows="3" class="large-text"><?php 
                        echo esc_textarea($settings->get('company_address')); 
                    ?></textarea>
                </td>
            </tr>
        </table>
        
        <h2>Emails</h2>
        <table class="form-table">
            <tr>
                <th>Email do Contador</th>
                <td>
                    <input type="email" name="accountant_email" class="regular-text"
                           value="<?php echo esc_attr($settings->get('accountant_email')); ?>">
                    <p class="description">Recebe notificações de NFs enviadas</p>
                </td>
            </tr>
            <tr>
                <th>Email Admin (notificações)</th>
                <td>
                    <input type="email" name="admin_email" class="regular-text"
                           value="<?php echo esc_attr($settings->get('admin_email')); ?>">
                    <p class="description">Deixe vazio para usar o email do admin do WordPress</p>
                </td>
            </tr>
        </table>
        
        <h2>Outras Configurações</h2>
        <table class="form-table">
            <tr>
                <th>Aprovação Automática</th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_approve" value="1" 
                               <?php checked($settings->get('auto_approve')); ?>>
                        Aprovar novos afiliados automaticamente
                    </label>
                </td>
            </tr>
            <tr>
                <th>Modo Debug</th>
                <td>
                    <label>
                        <input type="checkbox" name="debug_mode" value="1" 
                               <?php checked($settings->get('debug_mode')); ?>>
                        Ativar logs detalhados
                    </label>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Salvar Configurações'); ?>
    </form>
</div>
```