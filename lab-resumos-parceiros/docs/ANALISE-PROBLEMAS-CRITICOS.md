# 🔧 Análise e Soluções para Problemas Críticos

## Plugin: Programa de Parceiros Lab Resumos v1.3.0

**Data da Análise:** Janeiro 2026  
**Autor:** Análise Técnica  
**Escopo:** Integração de descontos e cálculo de comissões

---

## 📋 Índice

1. [Visão Geral dos Problemas](#1-visão-geral-dos-problemas)
2. [Problema 1: Race Condition nas Flags de Desconto](#2-problema-1-race-condition-nas-flags-de-desconto)
3. [Problema 2: Conflito Visual do Cupom Bloqueado](#3-problema-2-conflito-visual-do-cupom-bloqueado)
4. [Problema 3: Verificação HPOS Frágil](#4-problema-3-verificação-hpos-frágil)
5. [Problema 4: Re-aplicação do Desconto Guruja](#5-problema-4-re-aplicação-do-desconto-guruja)
6. [Problema 5: Comissão Inclui Frete](#6-problema-5-comissão-inclui-frete)
7. [Plano de Implementação](#7-plano-de-implementação)
8. [Testes Recomendados](#8-testes-recomendados)

---

## 1. Visão Geral dos Problemas

### Resumo Executivo

| # | Problema | Severidade | Impacto | Esforço |
|---|----------|------------|---------|---------|
| 1 | Race Condition nas Flags | 🔴 Alta | Comissão calculada incorretamente | Médio |
| 2 | Conflito Visual do Cupom | 🟡 Média | Confusão do cliente no checkout | Baixo |
| 3 | Verificação HPOS Frágil | 🟡 Média | Erro em ambientes específicos | Baixo |
| 4 | Re-aplicação Guruja | 🟡 Média | Desconto errado após update | Médio |
| 5 | Comissão Inclui Frete | 🟢 Baixa | Comissão maior que o esperado | Baixo |

### Arquivos Afetados

```
lab-resumos-parceiros/
├── includes/
│   ├── integrations/
│   │   ├── class-lrp-guruja.php          # Problemas 1, 2, 4
│   │   └── class-lrp-woocommerce.php     # Problema 3
│   └── tracking/
│       └── class-lrp-attribution.php     # Problemas 1, 5
```

---

## 2. Problema 1: Race Condition nas Flags de Desconto

### 2.1 Descrição do Problema

A classe `LRP_Guruja` usa flags de instância (`$affiliate_discount_applied`, `$guruja_discount_applied`) que são definidas durante o hook `woocommerce_cart_calculate_fees`. Porém, quando `process_order_attribution()` é chamado no hook `woocommerce_checkout_order_created`, essas flags podem estar resetadas ou incorretas.

### 2.2 Código Atual com Problema

```php
// Em class-lrp-guruja.php - Flags de instância
private $affiliate_discount_applied = false;
private $guruja_discount_applied = false;

// Em class-lrp-attribution.php - Dependência das flags
$discount_source = $guruja->get_applied_discount_source();

// Em class-lrp-guruja.php - Método que depende das flags
public function get_applied_discount_source() {
    if ($this->affiliate_discount_applied) {
        return 'affiliate';
    }
    
    if ($this->guruja_discount_applied) {
        return 'guruja';
    }
    
    // Fallback que pode falhar se WC()->cart estiver vazio
    if (WC()->cart) {
        $fees = WC()->cart->get_fees();
        // ...
    }
    
    return 'none';
}
```

### 2.3 Solução Proposta

**Estratégia:** Verificar a fonte de desconto diretamente no objeto `$order` (não no `cart`), pois o pedido já foi criado e contém todas as informações.

#### 2.3.1 Novo Método em `class-lrp-guruja.php`

```php
/**
 * Determina fonte do desconto a partir do ORDER (não do cart)
 * 
 * Este método é mais confiável pois o order já foi criado
 * e contém todas as fees e cupons aplicados.
 *
 * @param WC_Order $order
 * @return string none|affiliate|guruja
 */
public function get_discount_source_from_order($order) {
    if (!$order || !is_a($order, 'WC_Order')) {
        return 'none';
    }
    
    // 1. Verifica se há fee do Guruja no pedido
    foreach ($order->get_fees() as $fee) {
        $fee_name = strtolower($fee->get_name());
        if (strpos($fee_name, 'guruja') !== false && $fee->get_total() < 0) {
            return 'guruja';
        }
    }
    
    // 2. Verifica se há cupom de afiliado no pedido
    $coupon_handler = LRP_Coupon_Handler::instance();
    $coupons = $order->get_coupon_codes();
    
    foreach ($coupons as $coupon_code) {
        if ($coupon_handler->is_affiliate_coupon($coupon_code)) {
            // Verifica se o cupom realmente deu desconto
            foreach ($order->get_items('coupon') as $coupon_item) {
                if (strtolower($coupon_item->get_code()) === strtolower($coupon_code)) {
                    if ($coupon_item->get_discount() > 0) {
                        return 'affiliate';
                    }
                }
            }
            // Cupom presente mas sem desconto (foi bloqueado)
            // Verifica se há fee Guruja (desconto veio do Guruja)
            return 'none'; // Será reavaliado se houver Guruja
        }
    }
    
    return 'none';
}
```

#### 2.3.2 Atualização em `class-lrp-attribution.php`

```php
public function process_order_attribution($order_id) {
    $order = wc_get_order($order_id);
    
    if (!$order) {
        return;
    }
    
    // ... código existente ...
    
    $affiliate = $attribution['affiliate'];
    $guruja = LRP_Guruja::instance();
    
    // CORREÇÃO: Usar método que analisa o ORDER, não flags voláteis
    $discount_source = $guruja->get_discount_source_from_order($order);
    
    // Verifica se deve ganhar comissão (regra Guruja)
    $should_earn = $guruja->should_affiliate_earn_commission($affiliate, $discount_source);
    
    // ... resto do código ...
}
```

### 2.4 Testes para Validar a Correção

```php
/**
 * Testes unitários para get_discount_source_from_order()
 */

// Teste 1: Pedido com fee Guruja
$order = wc_create_order();
$order->add_fee(new WC_Order_Item_Fee([
    'name' => 'Desconto Aluno Guruja',
    'total' => -50.00,
]));
$source = LRP_Guruja::instance()->get_discount_source_from_order($order);
assert($source === 'guruja');

// Teste 2: Pedido com cupom de afiliado
$order = wc_create_order();
$order->apply_coupon('PARCEIRO10');
$source = LRP_Guruja::instance()->get_discount_source_from_order($order);
assert($source === 'affiliate');

// Teste 3: Pedido sem desconto
$order = wc_create_order();
$source = LRP_Guruja::instance()->get_discount_source_from_order($order);
assert($source === 'none');
```

---

## 3. Problema 2: Conflito Visual do Cupom Bloqueado

### 3.1 Descrição do Problema

Quando a regra é `higher_discount` ou `guruja_priority` e o desconto Guruja prevalece, o cupom do afiliado continua aparecendo no checkout como "aplicado", mas com valor R$ 0,00. Isso causa confusão ao cliente.

### 3.2 Comportamento Atual

```
┌─────────────────────────────────────┐
│ Resumo do Pedido                    │
├─────────────────────────────────────┤
│ Subtotal              R$ 297,00     │
│ Cupom: PARCEIRO10     R$ 0,00  ⚠️   │  <-- CONFUSO!
│ Desconto Aluno Guruja -R$ 89,10     │
│ Total                 R$ 207,90     │
└─────────────────────────────────────┘
```

### 3.3 Solução Proposta

**Estratégia:** Remover o cupom do carrinho quando o desconto Guruja prevalece, e adicionar uma mensagem explicativa.

#### 3.3.1 Atualização em `coordinate_discounts()` - `class-lrp-guruja.php`

```php
public function coordinate_discounts($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    $coupon_handler = LRP_Coupon_Handler::instance();
    $affiliate = $coupon_handler->get_affiliate_from_cart_coupon();
    
    // Se não tem cupom de afiliado, deixa Guruja funcionar normalmente
    if (!$affiliate) {
        return;
    }
    
    // Tem cupom de afiliado - precisa decidir
    $guruja_discount = $this->calculate_guruja_discount_total();
    $affiliate_discount = $this->calculate_affiliate_coupon_discount();
    
    // Se não é elegível Guruja, não faz nada (cupom funciona normal)
    if ($guruja_discount <= 0) {
        $this->affiliate_discount_applied = true;
        return;
    }
    
    // Ambos os descontos disponíveis - aplica regra
    $rule = $affiliate->get_guruja_rule();
    $coupon_code = $coupon_handler->get_affiliate_coupon_from_cart();
    
    $this->guruja_discount_amount = $guruja_discount;
    $this->affiliate_discount_amount = $affiliate_discount;
    
    switch ($rule) {
        case 'affiliate_priority':
            // Remove desconto Guruja, mantém cupom
            $this->clear_guruja_session();
            $this->block_affiliate_coupon = false;
            $this->blocked_coupon_code = null;
            $this->affiliate_discount_applied = true;
            
            lrp_log('Regra affiliate_priority: cupom prevalece', [
                'affiliate_id'       => $affiliate->get_id(),
                'affiliate_discount' => $affiliate_discount,
                'guruja_discount'    => $guruja_discount,
            ]);
            break;
            
        case 'guruja_priority':
            // CORREÇÃO: Remove cupom do carrinho (não apenas bloqueia)
            $this->remove_affiliate_coupon_gracefully($coupon_code, $guruja_discount);
            $this->guruja_discount_applied = true;
            
            lrp_log('Regra guruja_priority: Guruja prevalece', [
                'affiliate_id'       => $affiliate->get_id(),
                'affiliate_discount' => $affiliate_discount,
                'guruja_discount'    => $guruja_discount,
            ]);
            break;
            
        case 'no_commission':
        case 'higher_discount':
        default:
            // Aplica o maior
            if ($guruja_discount >= $affiliate_discount) {
                // CORREÇÃO: Remove cupom do carrinho (não apenas bloqueia)
                $this->remove_affiliate_coupon_gracefully($coupon_code, $guruja_discount);
                $this->guruja_discount_applied = true;
                
                lrp_log('Regra higher_discount: Guruja maior', [
                    'affiliate_id'       => $affiliate->get_id(),
                    'affiliate_discount' => $affiliate_discount,
                    'guruja_discount'    => $guruja_discount,
                ]);
            } else {
                // Cupom é maior - remove Guruja
                $this->clear_guruja_session();
                $this->block_affiliate_coupon = false;
                $this->blocked_coupon_code = null;
                $this->affiliate_discount_applied = true;
                
                lrp_log('Regra higher_discount: Cupom maior', [
                    'affiliate_id'       => $affiliate->get_id(),
                    'affiliate_discount' => $affiliate_discount,
                    'guruja_discount'    => $guruja_discount,
                ]);
            }
            break;
    }
}

/**
 * Remove cupom de afiliado de forma elegante
 * 
 * @param string $coupon_code
 * @param float $guruja_discount Valor do desconto Guruja para mensagem
 */
private function remove_affiliate_coupon_gracefully($coupon_code, $guruja_discount) {
    if (!WC()->cart) {
        return;
    }
    
    // Armazena info para tracking (ainda atribui venda ao afiliado)
    WC()->session->set('lrp_removed_coupon_for_guruja', [
        'coupon_code' => $coupon_code,
        'guruja_discount' => $guruja_discount,
        'timestamp' => time(),
    ]);
    
    // Remove cupom
    WC()->cart->remove_coupon($coupon_code);
    
    // Adiciona notice informativo (não erro)
    if (!wc_has_notice(__('Seu desconto de aluno Guruja é maior e foi aplicado automaticamente!', 'lab-resumos-parceiros'), 'success')) {
        wc_add_notice(
            sprintf(
                __('Seu desconto de aluno Guruja (R$ %s) é maior que o cupom e foi aplicado automaticamente!', 'lab-resumos-parceiros'),
                number_format($guruja_discount, 2, ',', '.')
            ),
            'success'
        );
    }
    
    // Reset flags de bloqueio (não precisa mais bloquear, cupom foi removido)
    $this->block_affiliate_coupon = false;
    $this->blocked_coupon_code = null;
}
```

### 3.4 Comportamento Corrigido

```
┌─────────────────────────────────────┐
│ Resumo do Pedido                    │
├─────────────────────────────────────┤
│ Subtotal              R$ 297,00     │
│ Desconto Aluno Guruja -R$ 89,10     │
│ Total                 R$ 207,90     │
└─────────────────────────────────────┘

✓ Seu desconto de aluno Guruja (R$ 89,10) 
  é maior e foi aplicado automaticamente!
```

### 3.5 Preservar Atribuição ao Afiliado

A venda ainda será atribuída ao afiliado através da sessão ou cookie:

```php
/**
 * Verifica se cupom foi removido em favor do Guruja
 * 
 * @return array|null
 */
public function get_removed_coupon_for_guruja() {
    if (!WC()->session) {
        return null;
    }
    
    $data = WC()->session->get('lrp_removed_coupon_for_guruja');
    
    // Expira após 1 hora
    if ($data && (time() - $data['timestamp']) > 3600) {
        WC()->session->set('lrp_removed_coupon_for_guruja', null);
        return null;
    }
    
    return $data;
}
```

---

## 4. Problema 3: Verificação HPOS Frágil

### 4.1 Descrição do Problema

A verificação `is_hpos_enabled()` pode lançar exceção se a classe `OrderUtil` não estiver carregada corretamente ou se o método não existir em versões específicas do WooCommerce.

### 4.2 Código Atual

```php
private function is_hpos_enabled() {
    return class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && 
           \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}
```

### 4.3 Solução Proposta

```php
/**
 * Verifica se HPOS está ativo de forma segura
 *
 * @return bool
 */
private function is_hpos_enabled() {
    // Verifica se a classe existe
    if (!class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
        return false;
    }
    
    // Verifica se o método existe (para compatibilidade com versões antigas)
    if (!method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
        return false;
    }
    
    try {
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    } catch (\Exception $e) {
        // Em caso de qualquer erro, assume modelo legado
        lrp_log('Erro ao verificar HPOS', [
            'error' => $e->getMessage(),
        ], 'warning');
        return false;
    } catch (\Error $e) {
        // Captura erros fatais também (PHP 7+)
        lrp_log('Erro fatal ao verificar HPOS', [
            'error' => $e->getMessage(),
        ], 'error');
        return false;
    }
}

/**
 * Cache do resultado para evitar verificações repetidas
 *
 * @return bool
 */
private function is_hpos_enabled_cached() {
    static $is_hpos = null;
    
    if ($is_hpos === null) {
        $is_hpos = $this->is_hpos_enabled();
    }
    
    return $is_hpos;
}
```

---

## 5. Problema 4: Re-aplicação do Desconto Guruja

### 5.1 Descrição do Problema

Quando a regra `affiliate_priority` limpa a sessão do Guruja, se o cliente atualizar o checkout ou editar campos, o JavaScript do plugin Guruja pode chamar a API novamente e re-aplicar o desconto.

### 5.2 Fluxo do Problema

```
1. Cliente aplica cupom PARCEIRO10
2. JavaScript Guruja detecta CPF e verifica API
3. API retorna desconto disponível
4. LRP_Guruja.coordinate_discounts() decide: affiliate_priority
5. Sessão Guruja é limpa
6. Cliente edita endereço de entrega
7. Checkout atualiza (update_checkout)
8. JavaScript Guruja detecta CPF novamente
9. API retorna desconto disponível NOVAMENTE
10. Desconto Guruja é re-aplicado ❌
```

### 5.3 Solução Proposta

**Estratégia:** Marcar na sessão que o desconto Guruja foi rejeitado em favor do cupom, e ignorar verificações subsequentes.

#### 5.3.1 Em `class-lrp-guruja.php`

```php
/**
 * Marca que desconto Guruja foi rejeitado em favor do cupom
 *
 * @param string $coupon_code
 */
private function mark_guruja_rejected_for_coupon($coupon_code) {
    if (!WC()->session) {
        return;
    }
    
    WC()->session->set('lrp_guruja_rejected', [
        'coupon_code' => $coupon_code,
        'timestamp' => time(),
        'cart_hash' => WC()->cart ? WC()->cart->get_cart_hash() : '',
    ]);
    
    lrp_log('Guruja marcado como rejeitado para cupom', [
        'coupon_code' => $coupon_code,
    ]);
}

/**
 * Verifica se Guruja foi rejeitado para o cupom atual
 *
 * @return bool
 */
public function was_guruja_rejected_for_coupon() {
    if (!WC()->session) {
        return false;
    }
    
    $data = WC()->session->get('lrp_guruja_rejected');
    
    if (empty($data)) {
        return false;
    }
    
    // Verifica se o cupom ainda está no carrinho
    $coupon_handler = LRP_Coupon_Handler::instance();
    $current_coupon = $coupon_handler->get_affiliate_coupon_from_cart();
    
    if (!$current_coupon || strtolower($current_coupon) !== strtolower($data['coupon_code'])) {
        // Cupom diferente ou removido - limpa flag
        WC()->session->set('lrp_guruja_rejected', null);
        return false;
    }
    
    // Expira após 2 horas (sessão do checkout)
    if ((time() - $data['timestamp']) > 7200) {
        WC()->session->set('lrp_guruja_rejected', null);
        return false;
    }
    
    return true;
}

/**
 * Limpa flag de rejeição
 */
public function clear_guruja_rejection() {
    if (WC()->session) {
        WC()->session->set('lrp_guruja_rejected', null);
    }
}
```

#### 5.3.2 Atualizar `coordinate_discounts()`

```php
public function coordinate_discounts($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    $coupon_handler = LRP_Coupon_Handler::instance();
    $affiliate = $coupon_handler->get_affiliate_from_cart_coupon();
    
    // Se não tem cupom de afiliado, deixa Guruja funcionar normalmente
    if (!$affiliate) {
        // Se tinha flag de rejeição mas cupom foi removido, limpa
        $this->clear_guruja_rejection();
        return;
    }
    
    // NOVO: Se Guruja já foi rejeitado para este cupom, não processa
    if ($this->was_guruja_rejected_for_coupon()) {
        $this->affiliate_discount_applied = true;
        return;
    }
    
    // ... resto do código existente ...
    
    switch ($rule) {
        case 'affiliate_priority':
            // Remove desconto Guruja, mantém cupom
            $this->clear_guruja_session();
            
            // NOVO: Marca que Guruja foi rejeitado
            $this->mark_guruja_rejected_for_coupon($coupon_code);
            
            $this->block_affiliate_coupon = false;
            $this->blocked_coupon_code = null;
            $this->affiliate_discount_applied = true;
            break;
            
        // ... outros cases ...
    }
}
```

#### 5.3.3 Atualizar plugin Guruja para respeitar flag

No arquivo `class-guruja-integration.php` do plugin **lab-resumos-guruja-discount**:

```php
public function apply_discounts($cart) {
    try {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        // NOVO: Verifica se desconto Guruja foi rejeitado pelo plugin de parceiros
        if (class_exists('LRP_Guruja') && LRP_Guruja::instance()->was_guruja_rejected_for_coupon()) {
            $this->log('Desconto Guruja ignorado - rejeitado em favor de cupom de afiliado');
            return;
        }
        
        // ... resto do código existente ...
    } catch (\Exception $e) {
        // ...
    }
}
```

---

## 6. Problema 5: Comissão Inclui Frete

### 6.1 Descrição do Problema

O `commission_base` é calculado usando `$order->get_total()`, que inclui frete, taxas e fees. Dependendo da política do programa, a comissão deveria ser apenas sobre o valor dos produtos.

### 6.2 Análise do Código Atual

```php
// Em process_order_attribution()
$order_total = (float) $order->get_total();

// Em calculate_proportional_commission_base()
$proportional_base = $order_total * $proportion;
```

### 6.3 Solução Proposta

**Estratégia:** Adicionar configuração para escolher base de comissão e calcular corretamente.

#### 6.3.1 Nova Configuração em `class-lrp-settings.php`

```php
private $defaults = [
    // ... existentes ...
    'commission_base_type' => 'order_total', // order_total | subtotal_only | subtotal_minus_discount
];

/**
 * Obtém tipo de base para comissão
 *
 * @return string
 */
public function get_commission_base_type() {
    return $this->get('commission_base_type', 'order_total');
}
```

#### 6.3.2 Atualizar `calculate_proportional_commission_base()` em `class-lrp-attribution.php`

```php
/**
 * Calcula commission_base proporcional aos produtos permitidos
 *
 * @param WC_Order $order
 * @param float $allowed_subtotal Subtotal dos produtos permitidos
 * @param float $order_total Total pago do pedido (não mais usado diretamente)
 * @return float
 */
private function calculate_proportional_commission_base($order, $allowed_subtotal, $order_total) {
    $order_subtotal = (float) $order->get_subtotal();
    
    // Se subtotal é zero, evita divisão por zero
    if ($order_subtotal <= 0) {
        return 0;
    }
    
    // Calcula a proporção do subtotal permitido em relação ao subtotal total
    $proportion = $allowed_subtotal / $order_subtotal;
    
    // Obtém tipo de base de comissão configurado
    $base_type = lrp_settings()->get_commission_base_type();
    
    switch ($base_type) {
        case 'subtotal_only':
            // Comissão apenas sobre subtotal (sem desconto, sem frete)
            $base_value = $order_subtotal;
            break;
            
        case 'subtotal_minus_discount':
            // Comissão sobre subtotal menos descontos (sem frete)
            $discount = $this->calculate_total_discount($order);
            $base_value = max(0, $order_subtotal - $discount);
            break;
            
        case 'order_total':
        default:
            // Comissão sobre total pago (inclui frete, descontos aplicados)
            $base_value = $order_total;
            break;
    }
    
    // Aplica a proporção
    $proportional_base = $base_value * $proportion;
    
    lrp_log('Base de comissão calculada', [
        'base_type' => $base_type,
        'order_subtotal' => $order_subtotal,
        'order_total' => $order_total,
        'base_value' => $base_value,
        'proportion' => $proportion,
        'proportional_base' => $proportional_base,
    ]);
    
    return round($proportional_base, 2);
}
```

#### 6.3.3 UI de Configuração

Adicionar no admin settings:

```php
// Em class-lrp-admin-settings.php

<tr>
    <th scope="row">
        <label for="commission_base_type">
            <?php esc_html_e('Base para Cálculo de Comissão', 'lab-resumos-parceiros'); ?>
        </label>
    </th>
    <td>
        <select name="lrp_settings[commission_base_type]" id="commission_base_type">
            <option value="order_total" <?php selected($settings['commission_base_type'] ?? 'order_total', 'order_total'); ?>>
                <?php esc_html_e('Total Pago (inclui frete e taxas)', 'lab-resumos-parceiros'); ?>
            </option>
            <option value="subtotal_minus_discount" <?php selected($settings['commission_base_type'] ?? 'order_total', 'subtotal_minus_discount'); ?>>
                <?php esc_html_e('Subtotal menos Descontos (sem frete)', 'lab-resumos-parceiros'); ?>
            </option>
            <option value="subtotal_only" <?php selected($settings['commission_base_type'] ?? 'order_total', 'subtotal_only'); ?>>
                <?php esc_html_e('Subtotal Bruto (sem descontos nem frete)', 'lab-resumos-parceiros'); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e('Define qual valor será usado como base para calcular as comissões.', 'lab-resumos-parceiros'); ?>
        </p>
    </td>
</tr>
```

---

## 7. Plano de Implementação

### 7.1 Ordem de Prioridade

| Ordem | Problema | Motivo |
|-------|----------|--------|
| 1º | Race Condition | Impacto direto no valor das comissões |
| 2º | Re-aplicação Guruja | Pode causar desconto incorreto |
| 3º | Conflito Visual | Melhora UX significativamente |
| 4º | HPOS Frágil | Prevenção de erros |
| 5º | Comissão com Frete | Opcional, depende de política |

### 7.2 Checklist de Implementação

```
□ 1. Race Condition nas Flags
  □ 1.1 Criar método get_discount_source_from_order() em class-lrp-guruja.php
  □ 1.2 Atualizar process_order_attribution() em class-lrp-attribution.php
  □ 1.3 Testar com pedido via cupom
  □ 1.4 Testar com pedido via Guruja
  □ 1.5 Testar com pedido sem desconto

□ 2. Re-aplicação Guruja
  □ 2.1 Criar métodos de flag de rejeição em class-lrp-guruja.php
  □ 2.2 Atualizar coordinate_discounts()
  □ 2.3 Atualizar plugin guruja-discount para respeitar flag
  □ 2.4 Testar cenário de atualização de checkout

□ 3. Conflito Visual do Cupom
  □ 3.1 Criar método remove_affiliate_coupon_gracefully()
  □ 3.2 Atualizar coordinate_discounts() para remover cupom
  □ 3.3 Testar visual do checkout
  □ 3.4 Verificar que atribuição ainda funciona

□ 4. HPOS Frágil
  □ 4.1 Atualizar is_hpos_enabled() com try/catch
  □ 4.2 Adicionar cache do resultado
  □ 4.3 Testar em ambiente com HPOS
  □ 4.4 Testar em ambiente sem HPOS

□ 5. Comissão com Frete (Opcional)
  □ 5.1 Adicionar configuração commission_base_type
  □ 5.2 Atualizar calculate_proportional_commission_base()
  □ 5.3 Adicionar UI no admin
  □ 5.4 Documentar diferenças
```

### 7.3 Backup e Rollback

Antes de implementar, criar branch e backup:

```bash
# Criar branch de feature
git checkout -b fix/commission-calculation-issues

# Commit após cada problema resolvido
git commit -m "fix(guruja): resolve race condition in discount flags"
git commit -m "fix(guruja): prevent re-application after rejection"
git commit -m "fix(checkout): remove coupon gracefully when guruja prevails"
git commit -m "fix(hpos): add safe check for HPOS enabled"
git commit -m "feat(settings): add commission base type configuration"
```

---

## 8. Testes Recomendados

### 8.1 Cenários de Teste Manual

#### Cenário 1: Cupom de Afiliado sem Guruja

```
1. Criar pedido como cliente não-aluno
2. Aplicar cupom de afiliado (ex: PARCEIRO10)
3. Finalizar pedido
4. Verificar:
   □ Desconto de 10% aplicado
   □ Comissão calculada corretamente
   □ Atribuição tipo "coupon"
   □ discount_source = "affiliate"
```

#### Cenário 2: Guruja sem Cupom de Afiliado

```
1. Criar pedido como aluno Guruja
2. Preencher email/CPF elegível
3. NÃO aplicar cupom
4. Finalizar pedido
5. Verificar:
   □ Desconto Guruja aplicado
   □ Sem comissão (nenhuma atribuição)
   □ Metadados _lrg_guruja_* salvos
```

#### Cenário 3: Cupom + Guruja com Regra "higher_discount"

```
1. Criar pedido como aluno Guruja
2. Aplicar cupom de afiliado
3. Preencher email/CPF elegível
4. Verificar qual desconto é maior
5. Finalizar pedido
6. Verificar:
   □ Maior desconto aplicado
   □ Cupom removido se Guruja maior
   □ Comissão calculada (se cupom aplicado)
   □ Ou sem comissão (se Guruja aplicado e regra no_commission)
```

#### Cenário 4: Cupom + Guruja com Regra "affiliate_priority"

```
1. Configurar afiliado com regra "affiliate_priority"
2. Criar pedido como aluno Guruja
3. Aplicar cupom do afiliado
4. Preencher email/CPF elegível
5. Atualizar checkout múltiplas vezes
6. Verificar:
   □ Cupom sempre prevalece
   □ Guruja NÃO re-aplicado após updates
   □ Comissão calculada corretamente
```

### 8.2 Testes Automatizados (PHPUnit)

```php
<?php
/**
 * Testes para correções de comissão
 */

class LRP_Commission_Fixes_Test extends WP_UnitTestCase {
    
    /**
     * Testa detecção de fonte de desconto a partir do pedido
     */
    public function test_get_discount_source_from_order_with_guruja() {
        $order = wc_create_order();
        
        // Simula fee do Guruja
        $fee = new WC_Order_Item_Fee();
        $fee->set_name('Desconto Aluno Guruja');
        $fee->set_total(-50.00);
        $order->add_item($fee);
        $order->save();
        
        $source = LRP_Guruja::instance()->get_discount_source_from_order($order);
        
        $this->assertEquals('guruja', $source);
    }
    
    /**
     * Testa que rejeição do Guruja persiste durante sessão
     */
    public function test_guruja_rejection_persists() {
        // Simula sessão WC
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
        
        // Simula cupom no carrinho
        WC()->cart->add_to_cart(/* product_id */);
        WC()->cart->apply_coupon('PARCEIRO10');
        
        // Marca como rejeitado
        LRP_Guruja::instance()->mark_guruja_rejected_for_coupon('PARCEIRO10');
        
        // Verifica que permanece rejeitado
        $this->assertTrue(LRP_Guruja::instance()->was_guruja_rejected_for_coupon());
        
        // Simula update do checkout
        WC()->cart->calculate_totals();
        
        // Ainda deve estar rejeitado
        $this->assertTrue(LRP_Guruja::instance()->was_guruja_rejected_for_coupon());
    }
    
    /**
     * Testa cálculo de base de comissão por tipo
     */
    public function test_commission_base_calculation_types() {
        // Configura tipo subtotal_only
        lrp_settings()->set('commission_base_type', 'subtotal_only');
        
        $order = wc_create_order();
        // Adiciona produto de R$ 100
        // Adiciona frete de R$ 20
        // Adiciona desconto de R$ 10
        
        $attribution = new LRP_Attribution();
        $base = $attribution->calculate_proportional_commission_base($order, 100, 110);
        
        // Deve retornar 100 (subtotal) não 110 (total com frete menos desconto)
        $this->assertEquals(100, $base);
    }
}
```

### 8.3 Monitoramento Pós-Deploy

Após implementar as correções, monitorar por 1 semana:

```
□ Logs de erro do WooCommerce
□ Logs do plugin (modo debug ativado)
□ Relatório de comissões vs vendas
□ Feedback de afiliados
□ Reclamações de clientes sobre checkout
```

---

## 9. Conclusão

As correções propostas abordam os problemas críticos identificados na integração entre o plugin de afiliados e o plugin de desconto Guruja. A implementação deve seguir a ordem de prioridade definida, com testes completos após cada correção.

**Impacto esperado:**
- ✅ Comissões calculadas corretamente em 100% dos casos
- ✅ UX melhorada no checkout
- ✅ Sem re-aplicação indesejada de descontos
- ✅ Compatibilidade total com HPOS
- ✅ Flexibilidade na base de comissão

---

*Documento gerado em Janeiro 2026*



