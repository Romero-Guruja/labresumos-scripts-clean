# Documentação - Lab Resumos - Desconto Guruja

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura do Plugin](#arquitetura-do-plugin)
3. [Como Funciona a Aplicação de Desconto](#como-funciona-a-aplicação-de-desconto)
4. [Fluxo Completo de Funcionamento](#fluxo-completo-de-funcionamento)
5. [Estrutura de Arquivos](#estrutura-de-arquivos)
6. [Configurações](#configurações)
7. [Integração com API Guruja](#integração-com-api-guruja)
8. [Hooks e Filtros do WooCommerce](#hooks-e-filtros-do-woocommerce)
9. [Tratamento de Erros](#tratamento-de-erros)
10. [Debug e Logs](#debug-e-logs)

---

## 🎯 Visão Geral

O **Lab Resumos - Desconto Guruja** é um plugin WordPress/WooCommerce que integra com a API Guruja para aplicar descontos automáticos no checkout para alunos elegíveis. O plugin verifica automaticamente se um cliente é elegível para desconto baseado em seu email e CPF, e aplica o desconto diretamente no carrinho de compras.

### Funcionalidades Principais

- ✅ Verificação automática de elegibilidade no checkout
- ✅ Aplicação de descontos percentuais ou fixos por produto
- ✅ Suporte a produtos simples e variações
- ✅ Interface visual com notificações no checkout
- ✅ Armazenamento de informações de desconto no pedido
- ✅ Painel administrativo para configuração
- ✅ Modo debug para troubleshooting
- ✅ Compatibilidade com HPOS (High-Performance Order Storage)

---

## 🏗️ Arquitetura do Plugin

O plugin utiliza o padrão **Singleton** e é organizado em classes especializadas:

### Classes Principais

1. **`Lab_Resumos_Guruja_Discount`** (Classe Principal)
   - Gerencia inicialização e dependências
   - Carrega scripts e estilos
   - Verifica se WooCommerce está ativo

2. **`LRG_Integration`** (Classe de Integração)
   - Comunicação com API Guruja
   - Aplicação de descontos no carrinho
   - Gerenciamento de sessão

3. **`LRG_Ajax`** (Handlers AJAX)
   - Endpoints para verificação de desconto
   - Limpeza de descontos
   - Teste de conexão

4. **`LRG_Admin`** (Painel Administrativo)
   - Interface de configurações
   - Campos de configuração da API
   - Teste de conexão

---

## 🔄 Como Funciona a Aplicação de Desconto

Esta é a parte mais importante do plugin. O desconto é aplicado através de um sistema em camadas que envolve JavaScript no frontend, AJAX no backend, e hooks do WooCommerce.

### 1. Detecção no Frontend (JavaScript)

O arquivo `guruja-checkout.js` monitora os campos de email e CPF no checkout:

```12:66:lab-resumos-guruja-discount/assets/js/guruja-checkout.js
        // Estado
        isChecking: false,
        lastEmail: '',
        lastCpf: '',
        debounceTimer: null,
        hasAppliedDiscount: false,

        /**
         * Inicializa
         */
        init: function() {
            this.bindEvents();
            this.createNoticeContainer();
        },

        /**
         * Cria container para notificações
         */
        createNoticeContainer: function() {
            if ($('#lrg-guruja-notice').length === 0) {
                var container = '<div id="lrg-guruja-notice" class="lrg-guruja-notice" style="display: none;"></div>';
                $('.woocommerce-billing-fields').after(container);
            }
        },

        /**
         * Vincula eventos
         */
        bindEvents: function() {
            var self = this;

            // Monitora mudanças nos campos de email e CPF
            $(document).on('change blur', '#billing_email, input[name="billing_email"], .woocommerce-billing-fields input[type="email"]', function() {
                self.scheduleCheck();
            });

            // Campo de CPF pode ter diferentes IDs dependendo do plugin
            $(document).on('change blur', '#billing_document, input[name="billing_document"], #billing_cpf, input[name="billing_cpf"]', function() {
                self.scheduleCheck();
            });

            // Também verifica quando o checkout é atualizado
            $(document.body).on('updated_checkout', function() {
                // Verifica se já temos desconto na sessão
                self.checkCurrentDiscount();
            });

            // Fluid Checkout compatibility
            $(document).on('change blur', '.fc-step--billing input', function() {
                self.scheduleCheck();
            });

            // Fallback: monitora qualquer input de email ou cpf
            $(document).on('change blur', 'input[type="email"], input[id*="cpf"], input[name*="cpf"]', function() {
                self.scheduleCheck();
            });
        },
```

**Características importantes:**
- **Debounce**: Aguarda 500ms após a última digitação antes de verificar (evita requisições excessivas)
- **Compatibilidade**: Suporta diferentes plugins de checkout (Fluid Checkout, etc.)
- **Validação**: Verifica se email e CPF são válidos antes de fazer requisição

### 2. Requisição AJAX

Quando o usuário preenche email e CPF, o JavaScript faz uma requisição AJAX:

```160:197:lab-resumos-guruja-discount/assets/js/guruja-checkout.js
            // Faz requisição AJAX
            $.ajax({
                url: lrgGuruja.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lrg_check_discount',
                    nonce: lrgGuruja.nonce,
                    email: email,
                    cpf: cpf
                },
                success: function(response) {
                    try {
                        if (response.success && response.data.elegivel) {
                            self.showNotice(lrgGuruja.i18n.applied, 'success');
                            self.hasAppliedDiscount = true;
                            // Atualiza checkout para mostrar novo total
                            $(document.body).trigger('update_checkout');
                        } else if (response.success && !response.data.elegivel) {
                            // Silencioso - não mostra nada para não-alunos
                            self.hasAppliedDiscount = false;
                            self.hideNotice();
                        } else {
                            self.showNotice(response.data.message || lrgGuruja.i18n.error, 'error');
                        }
                    } catch (e) {
                        console.error('[Lab Resumos Guruja] Erro JS:', e);
                        self.hideNotice();
                    }
                },
                error: function(xhr, status, error) {
                    // Silencioso para o cliente - apenas loga
                    console.error('[Lab Resumos Guruja] Erro AJAX:', status, error);
                    self.hideNotice();
                },
                complete: function() {
                    self.isChecking = false;
                }
            });
```

### 3. Handler AJAX no Backend

O handler AJAX recebe a requisição e chama a integração:

```31:74:lab-resumos-guruja-discount/includes/class-guruja-ajax.php
    public function check_discount() {
        try {
            // Verifica nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'lrg_guruja_nonce')) {
                wp_send_json_error(['message' => 'Requisição inválida']);
            }

        $email = sanitize_email($_POST['email'] ?? '');
        $cpf = sanitize_text_field($_POST['cpf'] ?? '');

        if (empty($email) || empty($cpf)) {
            wp_send_json_error(['message' => 'Email e CPF são obrigatórios']);
        }

        // Chama a integração
        $result = lrg_integration()->check_discounts($email, $cpf);

        if ($result['success'] && !empty($result['elegivel'])) {
            // Recalcula totais do carrinho
            WC()->cart->calculate_totals();

            wp_send_json_success([
                'message' => $result['message'],
                'elegivel' => true,
                'descontos' => $result['descontos'] ?? [],
            ]);
        } elseif ($result['success'] && empty($result['elegivel'])) {
            wp_send_json_success([
                'message' => $result['message'],
                'elegivel' => false,
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'],
            ]);
        }
        } catch (\Exception $e) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('[Lab Resumos Guruja] ' . $e->getMessage(), ['source' => 'lab-resumos-guruja']);
            }
            // Retorna erro silencioso - não quebra nada
            wp_send_json_error(['message' => 'Erro interno']);
        }
    }
```

### 4. Verificação na API Guruja

A classe `LRG_Integration` faz a requisição para a API:

```141:260:lab-resumos-guruja-discount/includes/class-guruja-integration.php
    public function check_discounts($email, $cpf) {
        try {
            $this->log('Iniciando verificação de desconto', ['email' => $email, 'cpf' => $cpf]);

        if (!$this->is_enabled()) {
            $this->log('Integração desativada');
            return ['success' => false, 'message' => 'Integração desativada'];
        }

        $api_url = $this->get_api_url();
        $api_token = $this->get_api_token();

        if (empty($api_url) || empty($api_token)) {
            $this->log('API não configurada');
            return ['success' => false, 'message' => 'API não configurada'];
        }

        // Limpa CPF (remove pontos e traços)
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // Valida CPF básico (11 dígitos)
        if (strlen($cpf) !== 11) {
            $this->log('CPF inválido', ['cpf_length' => strlen($cpf)]);
            return ['success' => false, 'message' => 'CPF inválido'];
        }

        // Valida email
        if (!is_email($email)) {
            $this->log('Email inválido');
            return ['success' => false, 'message' => 'Email inválido'];
        }

        // Monta payload
        $payload = [
            'email' => sanitize_email($email),
            'cpf' => $cpf,
            'produtos' => $this->get_cart_products(),
        ];

        $this->log('Enviando request para API', $payload);

        // Faz requisição
        $response = wp_remote_post($api_url, [
            'timeout' => $this->get_api_timeout(),
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        // Verifica erro de conexão
        if (is_wp_error($response)) {
            $this->log('Erro de conexão', ['error' => $response->get_error_message()]);
            return [
                'success' => false,
                'message' => 'Erro de conexão: ' . $response->get_error_message(),
            ];
        }

        // Verifica código HTTP
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $this->log('Código HTTP inesperado', ['code' => $http_code]);
            return [
                'success' => false,
                'message' => 'Erro na API (código ' . $http_code . ')',
            ];
        }

        // Decodifica resposta
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('Erro ao decodificar JSON', ['body' => $body]);
            return [
                'success' => false,
                'message' => 'Resposta inválida da API',
            ];
        }

        $this->log('Resposta da API', $data);

        // Verifica se é elegível
        if (empty($data['elegivel']) || $data['elegivel'] !== true) {
            $this->clear_discounts();
            return [
                'success' => true,
                'elegivel' => false,
                'message' => 'Não elegível para desconto',
            ];
        }

        // Processa descontos
        $descontos = $data['descontos'] ?? [];
        if (empty($descontos)) {
            $this->clear_discounts();
            return [
                'success' => true,
                'elegivel' => false,
                'message' => 'Nenhum desconto disponível',
            ];
        }

        // Salva descontos na sessão
        $this->save_discounts($descontos, $email, $cpf);

        return [
            'success' => true,
            'elegivel' => true,
            'descontos' => $descontos,
            'message' => 'Desconto aplicado!',
        ];
        } catch (\Exception $e) {
            $this->log_error('Erro na verificação: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno'];
        }
    }
```

**Pontos importantes:**
- Envia email, CPF e lista de produtos do carrinho
- Recebe resposta com `elegivel: true/false` e array de `descontos`
- Salva descontos na sessão do WooCommerce

### 5. Armazenamento na Sessão

Os descontos são salvos na sessão do WooCommerce:

```265:278:lab-resumos-guruja-discount/includes/class-guruja-integration.php
    public function save_discounts($descontos, $email, $cpf) {
        if (!WC()->session) {
            return;
        }

        WC()->session->set(self::SESSION_KEY, [
            'descontos' => $descontos,
            'email' => $email,
            'cpf' => $cpf,
            'timestamp' => time(),
        ]);

        $this->log('Descontos salvos na sessão', $descontos);
    }
```

### 6. Aplicação do Desconto no Carrinho (HOOK CRÍTICO)

**Este é o coração do sistema de desconto!** O WooCommerce possui um hook especial chamado `woocommerce_cart_calculate_fees` que permite adicionar taxas (fees) ao carrinho. Como as taxas podem ser negativas, usamos isso para aplicar descontos:

```22:22:lab-resumos-guruja-discount/includes/class-guruja-integration.php
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_discounts'], 20);
```

O método `apply_discounts` é chamado **toda vez que o WooCommerce recalcula os totais do carrinho**:

```304:384:lab-resumos-guruja-discount/includes/class-guruja-integration.php
    public function apply_discounts($cart) {
        try {
            if (is_admin() && !defined('DOING_AJAX')) {
                return;
            }

        $session_data = $this->get_saved_discounts();
        if (empty($session_data) || empty($session_data['descontos'])) {
            return;
        }

        // REMOVIDO: validação de email/CPF que estava limpando o desconto
        
        $descontos = $session_data['descontos'];
        $total_desconto = 0;
        $desconto_detalhes = [];

        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            
            // Verifica também variation_id
            $variation_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;

            // Procura desconto para este produto
            foreach ($descontos as $desconto) {
                $desconto_product_id = (int) $desconto['product_id'];
                
                // Verifica se o desconto é para este produto ou variação
                if ($desconto_product_id !== $product_id && $desconto_product_id !== $variation_id) {
                    continue;
                }

                $tipo = $desconto['tipo'] ?? 'percentual';
                $valor = (float) ($desconto['valor'] ?? 0);
                $preco_produto = (float) $cart_item['data']->get_price();
                $quantidade = $cart_item['quantity'];

                if ($tipo === 'percentual') {
                    $desconto_item = ($preco_produto * $valor / 100) * $quantidade;
                } else {
                    // Desconto fixo por unidade
                    $desconto_item = $valor * $quantidade;
                }

                // Limita desconto ao valor do produto
                $valor_max = $preco_produto * $quantidade;
                $desconto_item = min($desconto_item, $valor_max);

                $total_desconto += $desconto_item;

                $desconto_detalhes[] = [
                    'produto' => $cart_item['data']->get_name(),
                    'desconto' => $desconto_item,
                ];

                $this->log('Desconto aplicado ao produto', [
                    'produto' => $cart_item['data']->get_name(),
                    'tipo' => $tipo,
                    'valor_config' => $valor,
                    'desconto_calculado' => $desconto_item,
                ]);

                break; // Só aplica um desconto por produto
            }
        }

        if ($total_desconto > 0) {
            $cart->add_fee(
                __('Desconto Aluno Guruja', 'lab-resumos-guruja'),
                -$total_desconto,
                false // Não aplicar taxa sobre desconto
            );

            $this->log('Desconto total aplicado', ['total' => $total_desconto]);
        }
        } catch (\Exception $e) {
            $this->log_error('Erro ao aplicar desconto: ' . $e->getMessage());
            // Falha silenciosa - checkout continua normal
            return;
        }
    }
```

**Explicação detalhada:**

1. **Recupera descontos da sessão**: Busca os descontos salvos anteriormente
2. **Itera pelos produtos do carrinho**: Para cada item no carrinho
3. **Busca desconto correspondente**: Verifica se há desconto configurado para aquele `product_id` ou `variation_id`
4. **Calcula desconto**:
   - **Percentual**: `(preço × percentual / 100) × quantidade`
   - **Fixo**: `valor × quantidade`
5. **Limita ao valor do produto**: Garante que o desconto não seja maior que o valor total do produto
6. **Adiciona como fee negativa**: Usa `$cart->add_fee()` com valor negativo para criar um desconto

**Por que usar `add_fee()` com valor negativo?**
- O WooCommerce não possui um método direto para aplicar descontos customizados
- As "fees" (taxas) podem ser negativas, funcionando como descontos
- Aparecem automaticamente no resumo do pedido
- São incluídas no cálculo de totais automaticamente

### 7. Atualização do Checkout

Após aplicar o desconto, o JavaScript dispara a atualização do checkout:

```176:176:lab-resumos-guruja-discount/assets/js/guruja-checkout.js
                            $(document.body).trigger('update_checkout');
```

Isso faz o WooCommerce:
- Recalcular totais (dispara `woocommerce_cart_calculate_fees` novamente)
- Atualizar a interface do checkout
- Mostrar o desconto no resumo do pedido

### 8. Salvamento no Pedido

Quando o pedido é criado, os metadados são salvos:

```389:400:lab-resumos-guruja-discount/includes/class-guruja-integration.php
    public function add_order_meta($order, $data) {
        $session_data = $this->get_saved_discounts();
        if (empty($session_data)) {
            return;
        }

        $order->update_meta_data('_lrg_guruja_email', $session_data['email'] ?? '');
        $order->update_meta_data('_lrg_guruja_cpf', $session_data['cpf'] ?? '');
        $order->update_meta_data('_lrg_guruja_descontos', $session_data['descontos'] ?? []);

        $this->log('Metadados adicionados ao pedido', ['order_id' => $order->get_id()]);
    }
```

---

## 🔄 Fluxo Completo de Funcionamento

```
┌─────────────────────────────────────────────────────────────┐
│ 1. CLIENTE PREENCHE EMAIL/CPF NO CHECKOUT                   │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. JavaScript detecta mudança (debounce 500ms)              │
│    - Valida email e CPF                                     │
│    - Previne requisições duplicadas                         │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Requisição AJAX para WordPress                           │
│    POST: admin-ajax.php?action=lrg_check_discount           │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Handler AJAX (LRG_Ajax::check_discount)                 │
│    - Verifica nonce                                         │
│    - Sanitiza dados                                         │
│    - Chama LRG_Integration::check_discounts()              │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. Integração prepara dados do carrinho                     │
│    - Lista produtos com ID, SKU, valor, quantidade          │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 6. Requisição HTTP para API Guruja                          │
│    POST: https://backoffice.guruja.com.br/...              │
│    Headers: Authorization: Bearer [TOKEN]                    │
│    Body: { email, cpf, produtos[] }                         │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 7. API Guruja responde                                      │
│    { elegivel: true, descontos: [...] }                     │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 8. Salva descontos na sessão WooCommerce                    │
│    WC()->session->set('lrg_guruja_descontos', {...})        │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 9. Resposta AJAX para frontend                              │
│    { success: true, elegivel: true, descontos: [...] }      │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 10. JavaScript dispara update_checkout                      │
│     $(document.body).trigger('update_checkout')             │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 11. WooCommerce recalcula totais                            │
│     - Dispara hook: woocommerce_cart_calculate_fees         │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 12. LRG_Integration::apply_discounts() executa              │
│     - Lê descontos da sessão                                │
│     - Calcula desconto por produto                           │
│     - $cart->add_fee('Desconto Aluno Guruja', -$total)      │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 13. Checkout atualizado visualmente                         │
│     - Desconto aparece no resumo                            │
│     - Total recalculado                                     │
│     - Notificação de sucesso exibida                        │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 14. Cliente finaliza pedido                                 │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 15. Hook woocommerce_checkout_create_order                  │
│     - Salva metadados no pedido                             │
│     - Email, CPF, descontos aplicados                       │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 16. Hook woocommerce_thankyou                               │
│     - Limpa descontos da sessão                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Estrutura de Arquivos

```
lab-resumos-guruja-discount/
├── lab-resumos-guruja-discount.php  # Arquivo principal do plugin
├── includes/
│   ├── class-guruja-admin.php        # Painel administrativo
│   ├── class-guruja-integration.php  # Integração com API e aplicação de descontos
│   └── class-guruja-ajax.php         # Handlers AJAX
├── assets/
│   ├── js/
│   │   └── guruja-checkout.js        # JavaScript do checkout
│   └── css/
│       └── guruja-checkout.css        # Estilos do checkout
└── README.md
```

---

## ⚙️ Configurações

O plugin possui as seguintes configurações no painel administrativo:

### Campos de Configuração

1. **Ativar integração** (`lrg_enabled`)
   - Tipo: Checkbox
   - Padrão: `yes`
   - Descrição: Ativa ou desativa a verificação de desconto

2. **URL da API** (`lrg_api_url`)
   - Tipo: Text
   - Padrão: `https://backoffice.guruja.com.br/woocommerce/verificar-desconto`
   - Descrição: Endpoint da API Guruja

3. **Token de Autenticação** (`lrg_api_token`)
   - Tipo: Password
   - Padrão: (vazio)
   - Descrição: Token Bearer para autenticação

4. **Modo Debug** (`lrg_debug_mode`)
   - Tipo: Checkbox
   - Padrão: `no`
   - Descrição: Ativa logs detalhados

5. **Timeout da API** (`lrg_api_timeout`)
   - Tipo: Number
   - Padrão: `10` segundos
   - Range: 5-60 segundos

### Acesso às Configurações

WooCommerce → Desconto Guruja

---

## 🔌 Integração com API Guruja

### Request (WordPress → Guruja)

```http
POST https://backoffice.guruja.com.br/woocommerce/verificar-desconto
Authorization: Bearer [TOKEN]
Content-Type: application/json

{
  "email": "aluno@email.com",
  "cpf": "12345678900",
  "produtos": [
    {
      "product_id": 123,
      "sku": "CURSO-001",
      "valor": 297.00,
      "quantidade": 1
    },
    {
      "product_id": 456,
      "sku": "CURSO-002",
      "valor": 197.00,
      "quantidade": 2
    }
  ]
}
```

### Response Esperada (Guruja → WordPress)

```json
{
  "elegivel": true,
  "descontos": [
    {
      "product_id": 123,
      "tipo": "percentual",
      "valor": 15
    },
    {
      "product_id": 456,
      "tipo": "fixo",
      "valor": 50.00
    }
  ]
}
```

### Tipos de Desconto

- **`percentual`**: Desconto em porcentagem (ex: `15` = 15%)
- **`fixo`**: Desconto em valor fixo por unidade (ex: `50.00` = R$ 50,00)

### Validações

- CPF deve ter 11 dígitos (após limpeza)
- Email deve ser válido
- `product_id` deve corresponder ao ID do produto ou variação no WooCommerce

---

## 🎣 Hooks e Filtros do WooCommerce

### Hooks Utilizados

1. **`woocommerce_cart_calculate_fees`** (Prioridade: 20)
   - **Quando**: Toda vez que o WooCommerce recalcula os totais do carrinho
   - **Função**: Aplica descontos usando `$cart->add_fee()` com valor negativo
   - **Arquivo**: `class-guruja-integration.php:22`

2. **`woocommerce_cart_emptied`**
   - **Quando**: Carrinho é esvaziado
   - **Função**: Limpa descontos da sessão
   - **Arquivo**: `class-guruja-integration.php:25`

3. **`woocommerce_thankyou`**
   - **Quando**: Página de agradecimento após pedido finalizado
   - **Função**: Limpa descontos da sessão
   - **Arquivo**: `class-guruja-integration.php:28`

4. **`woocommerce_checkout_create_order`**
   - **Quando**: Pedido está sendo criado
   - **Função**: Salva metadados (email, CPF, descontos) no pedido
   - **Arquivo**: `class-guruja-integration.php:31`

5. **`woocommerce_admin_order_data_after_billing_address`**
   - **Quando**: Exibição de dados do pedido no admin
   - **Função**: Mostra informações do desconto aplicado
   - **Arquivo**: `class-guruja-integration.php:34`

### Eventos JavaScript

1. **`update_checkout`**
   - Disparado pelo plugin para forçar recálculo do checkout
   - Arquivo: `guruja-checkout.js:176`

2. **`updated_checkout`**
   - Disparado pelo WooCommerce após atualização
   - Usado para verificar se desconto já está aplicado
   - Arquivo: `guruja-checkout.js:53`

---

## ⚠️ Tratamento de Erros

### Estratégia de Falha Silenciosa

O plugin foi projetado para **nunca quebrar o checkout**, mesmo em caso de erro:

1. **Erros de API**: Se a API não responder ou retornar erro, o checkout continua normalmente sem desconto
2. **Erros de validação**: Mensagens são exibidas ao usuário, mas não impedem o checkout
3. **Erros de cálculo**: Try/catch envolve todas as operações críticas
4. **Sessão inválida**: Se a sessão não existir, simplesmente não aplica desconto

### Níveis de Log

- **Debug Mode OFF**: Apenas erros críticos são logados
- **Debug Mode ON**: Todas as operações são logadas (requests, respostas, cálculos)

### Logs

Os logs são salvos em:
- WooCommerce → Status → Logs → `lab-resumos-guruja`

---

## 🐛 Debug e Logs

### Ativar Modo Debug

1. Acesse: WooCommerce → Desconto Guruja
2. Marque "Modo Debug"
3. Salve configurações

### O que é logado

- ✅ Início de verificação de desconto
- ✅ Dados enviados para API
- ✅ Resposta da API
- ✅ Descontos salvos na sessão
- ✅ Cálculo de desconto por produto
- ✅ Desconto total aplicado
- ✅ Erros de conexão
- ✅ Erros de validação

### Exemplo de Log

```
[Lab Resumos Guruja] Iniciando verificação de desconto | Data: {"email":"aluno@email.com","cpf":"12345678900"}
[Lab Resumos Guruja] Enviando request para API | Data: {"email":"aluno@email.com","cpf":"12345678900","produtos":[...]}
[Lab Resumos Guruja] Resposta da API | Data: {"elegivel":true,"descontos":[...]}
[Lab Resumos Guruja] Descontos salvos na sessão | Data: [...]
[Lab Resumos Guruja] Desconto aplicado ao produto | Data: {"produto":"Curso X","tipo":"percentual","valor_config":15,"desconto_calculado":44.55}
[Lab Resumos Guruja] Desconto total aplicado | Data: {"total":44.55}
```

---

## 🔒 Segurança

### Medidas Implementadas

1. **Nonce Verification**: Todas as requisições AJAX verificam nonce
2. **Sanitização**: Todos os dados de entrada são sanitizados
3. **Validação**: Email e CPF são validados antes de enviar para API
4. **Capability Check**: Ações administrativas verificam permissões
5. **Escape Output**: Todos os outputs são escapados

### Tokens e Credenciais

- Token da API é armazenado como opção do WordPress (criptografado pelo WordPress)
- Token não é exposto no frontend
- Requisições são feitas apenas do servidor

---

## 🎨 Interface do Usuário

### Notificações no Checkout

O plugin exibe notificações visuais no checkout:

- **Loading**: "Verificando desconto..." (spinner animado)
- **Sucesso**: "Desconto Guruja aplicado!" (verde, com ✓)
- **Erro**: "Erro ao verificar desconto..." (vermelho, com ✗)

### Estilos

As notificações são estilizadas com CSS e aparecem abaixo dos campos de billing.

### Responsividade

O plugin é totalmente responsivo e funciona em dispositivos móveis.

---

## 📝 Metadados do Pedido

Quando um pedido é criado com desconto, os seguintes metadados são salvos:

- `_lrg_guruja_email`: Email usado para verificação
- `_lrg_guruja_cpf`: CPF usado para verificação
- `_lrg_guruja_descontos`: Array completo de descontos aplicados

Esses dados aparecem no admin do pedido em uma seção destacada.

---

## 🔄 Limpeza de Dados

Os descontos são automaticamente limpos quando:

1. Carrinho é esvaziado
2. Pedido é finalizado (página de agradecimento)
3. Cliente muda email/CPF no checkout (desconto anterior é removido antes de verificar novo)

---

## 🚀 Compatibilidade

### Requisitos

- WordPress 5.8+
- PHP 7.4+
- WooCommerce 5.0+
- Testado até WooCommerce 8.0

### Compatibilidade com Plugins

- ✅ Fluid Checkout
- ✅ Plugins de campos customizados (CPF)
- ✅ HPOS (High-Performance Order Storage)
- ✅ Qualquer tema compatível com WooCommerce

---

## 📚 Referências Técnicas

### Documentação WooCommerce

- [Hooks de Carrinho](https://woocommerce.github.io/code-reference/hooks/hooks.html)
- [Cart Fees](https://woocommerce.github.io/code-reference/classes/WC-Cart.html#method_add_fee)
- [Session Management](https://woocommerce.github.io/code-reference/classes/WC-Session.html)

### WordPress

- [AJAX no WordPress](https://developer.wordpress.org/plugins/javascript/ajax/)
- [Nonces](https://developer.wordpress.org/plugins/security/nonces/)
- [Sanitização](https://developer.wordpress.org/apis/handbook/sanitization/)

---

## 🆘 Troubleshooting

### Desconto não aparece

1. Verifique se a integração está ativada
2. Verifique se URL e Token estão configurados
3. Ative modo debug e verifique logs
4. Verifique se email/CPF são válidos
5. Verifique se a API está respondendo (use teste de conexão)

### Desconto aparece mas some

- Pode ser limpeza automática da sessão
- Verifique se não há validação removendo desconto
- Verifique logs para erros

### Erro 401 na API

- Token pode estar incorreto ou expirado
- Verifique configuração do token

### Erro de timeout

- Aumente o timeout nas configurações
- Verifique conectividade do servidor com a API

---

## 📄 Licença

GPL v2 or later

---

## 👥 Autor

Lab Resumos - https://labresumos.com.br

---

**Última atualização**: 2024



