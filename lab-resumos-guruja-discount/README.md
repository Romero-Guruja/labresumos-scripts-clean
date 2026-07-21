# Lab Resumos - Desconto Guruja

Plugin WordPress/WooCommerce para integração com a API Guruja, aplicando descontos automáticos para alunos no checkout.

## Instalação

1. Faça upload da pasta `lab-resumos-guruja-discount` para `/wp-content/plugins/`
2. Ative o plugin em **Plugins** no painel WordPress
3. Configure em **WooCommerce → Desconto Guruja**

## Configuração

### Campos obrigatórios

| Campo | Descrição |
|-------|-----------|
| **URL da API** | Endpoint da API Guruja (ex: `https://backoffice.guruja.com.br/woocommerce/verificar-desconto`) |
| **Token** | Token Bearer para autenticação |

### Campos opcionais

| Campo | Descrição | Padrão |
|-------|-----------|--------|
| **Ativar integração** | Liga/desliga a verificação | Ativo |
| **Modo Debug** | Loga requisições em `wp-content/debug.log` | Desativo |
| **Timeout** | Tempo máximo de espera da API | 10s |

## Como funciona

### Fluxo no checkout

1. Cliente preenche **email** e **CPF** nos campos de cobrança
2. Plugin aguarda 500ms (debounce) e envia requisição para API Guruja
3. API retorna descontos elegíveis por produto
4. Descontos são aplicados como "fee negativo" no carrinho
5. Cliente vê "Desconto Aluno Guruja" no resumo do pedido

### Contrato da API

**Request (WordPress → Guruja)**

```http
POST https://backoffice.guruja.com.br/woocommerce/verificar-desconto
Authorization: Bearer {token}
Content-Type: application/json

{
  "email": "aluno@email.com",
  "cpf": "12345678900",
  "produtos": [
    { "product_id": 123, "sku": "DIR-CONST-2025", "valor": 297.00, "quantidade": 1 },
    { "product_id": 456, "sku": "DIR-ADM-2025", "valor": 197.00, "quantidade": 1 }
  ]
}
```

**Response (Guruja → WordPress)**

```json
{
  "elegivel": true,
  "descontos": [
    { "product_id": 123, "tipo": "percentual", "valor": 15 },
    { "product_id": 456, "tipo": "fixo", "valor": 50.00 }
  ]
}
```

### Tipos de desconto

| Tipo | Descrição | Exemplo |
|------|-----------|---------|
| `percentual` | Porcentagem sobre o preço | `15` = 15% de desconto |
| `fixo` | Valor fixo em reais | `50.00` = R$ 50,00 de desconto |

## Requisitos

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+
- Plugin de CPF no checkout (ex: Brazilian Market on WooCommerce)

## Compatibilidade

- ✅ WooCommerce HPOS (High-Performance Order Storage)
- ✅ WooCommerce Blocks checkout
- ✅ Temas Astra, Storefront, OceanWP
- ✅ Brazilian Market on WooCommerce
- ✅ WooCommerce Extra Checkout Fields for Brazil

## Debug

Para ativar logs detalhados:

1. Ative "Modo Debug" nas configurações do plugin
2. Adicione no `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
3. Verifique logs em `wp-content/debug.log` ou via **WooCommerce → Status → Logs**

## Hooks disponíveis

### Filtros

```php
// Modificar payload antes de enviar para API
add_filter('lrg_api_payload', function($payload, $email, $cpf) {
    // Adiciona campo customizado
    $payload['origem'] = 'checkout';
    return $payload;
}, 10, 3);

// Modificar descontos antes de aplicar
add_filter('lrg_descontos_aplicar', function($descontos) {
    // Limita desconto máximo
    foreach ($descontos as &$d) {
        if ($d['tipo'] === 'percentual' && $d['valor'] > 20) {
            $d['valor'] = 20;
        }
    }
    return $descontos;
});
```

### Actions

```php
// Após desconto ser aplicado
add_action('lrg_desconto_aplicado', function($total_desconto, $descontos) {
    // Loga ou notifica
}, 10, 2);

// Após pedido com desconto Guruja
add_action('lrg_pedido_com_desconto', function($order_id, $descontos) {
    // Envia para analytics
}, 10, 2);
```

## Estrutura do plugin

```
lab-resumos-guruja-discount/
├── lab-resumos-guruja-discount.php  # Arquivo principal
├── includes/
│   ├── class-guruja-admin.php       # Página de configurações
│   ├── class-guruja-integration.php # Lógica de integração
│   └── class-guruja-ajax.php        # Handlers AJAX
├── assets/
│   ├── css/
│   │   └── guruja-checkout.css      # Estilos do checkout
│   └── js/
│       └── guruja-checkout.js       # Script do checkout
└── README.md
```

## Suporte

Em caso de problemas:

1. Verifique se WooCommerce está ativo
2. Confirme URL e Token nas configurações
3. Teste conexão via botão na página de configurações
4. Ative modo debug e verifique logs
5. Confirme que o campo CPF existe no checkout

## Changelog

### 1.0.0
- Versão inicial
- Integração com API Guruja
- Descontos por produto (percentual e fixo)
- Página de configurações
- Modo debug
- Metadados no pedido
