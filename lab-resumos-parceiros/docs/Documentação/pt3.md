# Programa de Parceiros Lab Resumos - Parte 3: Integração Guruja e Atribuição

## 1. Visão Geral da Integração

O plugin de afiliados deve coexistir harmoniosamente com o plugin existente **Lab Resumos Guruja Discount**. Esta é uma das partes mais críticas do sistema.

### 1.1 Como o Plugin Guruja Funciona (Resumo)

O plugin Guruja:
1. Monitora campos de email/CPF no checkout
2. Faz requisição AJAX para API Guruja verificando elegibilidade
3. Se elegível, salva descontos na sessão WooCommerce
4. Aplica desconto via hook `woocommerce_cart_calculate_fees` usando `$cart->add_fee()` negativo
5. O desconto aparece como "Desconto Aluno Guruja" no resumo

**Hook crítico do Guruja:**
```php
add_action('woocommerce_cart_calculate_fees', [$this, 'apply_discounts'], 20);
```

**Sessão do Guruja:**
```php
WC()->session->get('lrg_guruja_descontos');
// Retorna: ['descontos' => [...], 'email' => '...', 'cpf' => '...']
```

---

## 2. Regras de Coexistência

### 2.1 Princípio Fundamental

> **Desconto Guruja e cupom de afiliado são MUTUAMENTE EXCLUSIVOS.**
> **Apenas UM desconto pode ser aplicado ao cliente.**
> **A atribuição da venda ao afiliado é INDEPENDENTE da fonte do desconto.**

### 2.2 Regras Configuráveis (guruja_rule)

Cada afiliado pode ter uma regra diferente:

| Regra | Desconto Aplicado | Afiliado Ganha Comissão? |
|-------|-------------------|--------------------------|
| `higher_discount` | O MAIOR entre Guruja e cupom | Sim (se veio por link/cupom) |
| `affiliate_priority` | Sempre o do afiliado | Sim |
| `guruja_priority` | Sempre o Guruja (se elegível) | Sim (se veio por link/cupom) |
| `no_commission` | O MAIOR | Não (se Guruja foi aplicado) |

### 2.3 Cenários Detalhados

**Cenário 1: Cliente usa cupom de afiliado + É aluno Guruja**
```
Cupom JOAO10 = 10% desconto
Guruja = 15% desconto

Com guruja_rule = 'higher_discount':
- Cliente recebe: 15% (Guruja, é maior)
- Afiliado João ganha: Comissão sobre valor PAGO (após 15%)

Com guruja_rule = 'affiliate_priority':
- Cliente recebe: 10% (cupom)
- Afiliado João ganha: Comissão sobre valor PAGO (após 10%)

Com guruja_rule = 'no_commission':
- Cliente recebe: 15% (Guruja, é maior)
- Afiliado João ganha: NADA (Guruja prevaleceu)
```

**Cenário 2: Cliente veio por link (cookie) + É aluno Guruja + Não usou cupom**
```
Cookie do afiliado João ativo
Guruja = 15% desconto

Resultado (qualquer regra):
- Cliente recebe: 15% (Guruja)
- Afiliado João ganha: Comissão de LINK (menor) sobre valor PAGO
```

**Cenário 3: Cliente usa cupom + NÃO é Guruja**
```
Cupom JOAO10 = 10% desconto

Resultado:
- Cliente recebe: 10% (cupom)
- Afiliado João ganha: Comissão de CUPOM sobre valor PAGO
```

**Cenário 4: Cliente é o próprio afiliado + É Guruja**
```
Afiliado João tenta comprar com próprio cupom
can_self_refer = false (padrão)

Resultado:
- Cupom é REJEITADO ("Você não pode usar seu próprio cupom")
- Se João é Guruja, recebe desconto Guruja normalmente
- João NÃO ganha comissão (é auto-compra)
```

---

## 3. Implementação Técnica

### 3.1 Classe LRP_Guruja

```php
<?php
/**
 * Integração com plugin Lab Resumos Guruja Discount
 */
class LRP_Guruja {

    private static $instance = null;
    
    /**
     * Flag para controlar aplicação de desconto
     */
    private $affiliate_discount_applied = false;
    private $guruja_discount_applied = false;
    private $guruja_discount_amount = 0;
    private $affiliate_discount_amount = 0;
    
    /**
     * Flag para bloquear cupom de afiliado (ao invés de removê-lo)
     */
    private $block_affiliate_coupon = false;
    private $blocked_coupon_code = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Só registra hooks se plugin Guruja estiver ativo
        if (!$this->is_guruja_active()) {
            return;
        }
        
        // Intercepta ANTES do Guruja aplicar desconto (prioridade menor que 20)
        add_action('woocommerce_cart_calculate_fees', [$this, 'coordinate_discounts'], 15);
        
        // Intercepta validação de cupom
        add_filter('woocommerce_coupon_is_valid', [$this, 'validate_coupon_with_guruja'], 10, 3);
        
        // Bloqueia desconto do cupom quando necessário (ao invés de remover)
        add_filter('woocommerce_coupon_get_discount_amount', [$this, 'block_coupon_discount'], 10, 5);
        
        // Quando cupom de afiliado é aplicado
        add_action('woocommerce_applied_coupon', [$this, 'on_coupon_applied'], 5);
        
        // Antes do checkout processar
        add_action('woocommerce_checkout_process', [$this, 'final_discount_check']);
    }

    /**
     * Verifica se plugin Guruja está ativo
     */
    public function is_guruja_active() {
        return class_exists('Lab_Resumos_Guruja_Discount') || 
               function_exists('lrg_integration');
    }

    /**
     * Obtém desconto Guruja da sessão
     */
    public function get_guruja_discount_data() {
        // IMPORTANTE: Verificar se sessão foi iniciada antes de acessar
        if (!WC()->session || !WC()->session->has_session()) {
            return null;
        }
        
        $data = WC()->session->get('lrg_guruja_descontos');
        
        if (empty($data) || empty($data['descontos'])) {
            return null;
        }
        
        return $data;
    }

    /**
     * Verifica se cliente é elegível para Guruja
     */
    public function is_guruja_eligible() {
        $data = $this->get_guruja_discount_data();
        return !empty($data);
    }

    /**
     * Calcula total do desconto Guruja
     */
    public function calculate_guruja_discount_total() {
        $data = $this->get_guruja_discount_data();
        
        if (!$data) {
            return 0;
        }
        
        $total = 0;
        $cart = WC()->cart;
        
        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'] ?? 0;
            
            foreach ($data['descontos'] as $desconto) {
                $desconto_product_id = (int) $desconto['product_id'];
                
                if ($desconto_product_id !== $product_id && $desconto_product_id !== $variation_id) {
                    continue;
                }
                
                $tipo = $desconto['tipo'] ?? 'percentual';
                $valor = (float) ($desconto['valor'] ?? 0);
                $preco = (float) $cart_item['data']->get_price();
                $quantidade = $cart_item['quantity'];
                
                if ($tipo === 'percentual') {
                    $desconto_item = ($preco * $valor / 100) * $quantidade;
                } else {
                    $desconto_item = $valor * $quantidade;
                }
                
                $total += min($desconto_item, $preco * $quantidade);
                break;
            }
        }
        
        return $total;
    }

    /**
     * Calcula desconto do cupom de afiliado
     */
    public function calculate_affiliate_coupon_discount() {
        $coupon_handler = LRP_Coupon_Handler::instance();
        $coupon_code = $coupon_handler->get_affiliate_coupon_from_cart();
        
        if (!$coupon_code) {
            return 0;
        }
        
        $coupon = new WC_Coupon($coupon_code);
        $discount_type = $coupon->get_discount_type();
        $amount = $coupon->get_amount();
        
        $cart_total = WC()->cart->get_subtotal();
        
        if ($discount_type === 'percent') {
            return $cart_total * ($amount / 100);
        } else {
            return $amount;
        }
    }

    /**
     * Coordena qual desconto aplicar
     * Este é o método principal que resolve conflitos
     */
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
        
        $this->guruja_discount_amount = $guruja_discount;
        $this->affiliate_discount_amount = $affiliate_discount;
        
        $coupon_code = $coupon_handler->get_affiliate_coupon_from_cart();
        
        switch ($rule) {
            case 'affiliate_priority':
                // Remove desconto Guruja, mantém cupom
                $this->clear_guruja_session();
                $this->block_affiliate_coupon = false;
                $this->blocked_coupon_code = null;
                $this->affiliate_discount_applied = true;
                break;
                
            case 'guruja_priority':
                // Bloqueia cupom, deixa Guruja
                $this->block_affiliate_coupon = true;
                $this->blocked_coupon_code = $coupon_code;
                $this->guruja_discount_applied = true;
                break;
                
            case 'no_commission':
            case 'higher_discount':
            default:
                // Aplica o maior
                if ($guruja_discount >= $affiliate_discount) {
                    // Guruja é maior ou igual - bloqueia cupom
                    $this->block_affiliate_coupon = true;
                    $this->blocked_coupon_code = $coupon_code;
                    $this->guruja_discount_applied = true;
                } else {
                    // Cupom é maior - remove Guruja
                    $this->clear_guruja_session();
                    $this->block_affiliate_coupon = false;
                    $this->blocked_coupon_code = null;
                    $this->affiliate_discount_applied = true;
                }
                break;
        }
    }

    /**
     * Bloqueia desconto do cupom quando necessário (ao invés de remover)
     * Este método é chamado via filtro woocommerce_coupon_get_discount_amount
     */
    public function block_coupon_discount($discount, $discounting_amount, $cart_item, $single, $coupon) {
        if (!$this->block_affiliate_coupon) {
            return $discount;
        }
        
        $coupon_handler = LRP_Coupon_Handler::instance();
        
        // Verifica se é cupom de afiliado bloqueado
        if ($coupon_handler->is_affiliate_coupon($coupon)) {
            $coupon_code = $coupon->get_code();
            
            if ($coupon_code === $this->blocked_coupon_code) {
                // Zera o desconto deste cupom
                return 0;
            }
        }
        
        return $discount;
    }

    /**
     * Limpa sessão do Guruja
     */
    private function clear_guruja_session() {
        if (WC()->session) {
            WC()->session->set('lrg_guruja_descontos', null);
        }
    }

    /**
     * Valida cupom considerando Guruja
     */
    public function validate_coupon_with_guruja($valid, $coupon, $discount) {
        // Se não é cupom de afiliado, não interfere
        $coupon_handler = LRP_Coupon_Handler::instance();
        if (!$coupon_handler->is_affiliate_coupon($coupon)) {
            return $valid;
        }
        
        // Se não é elegível Guruja, cupom funciona normal
        if (!$this->is_guruja_eligible()) {
            return $valid;
        }
        
        // Tem Guruja - verifica regra do afiliado
        $affiliate_id = $coupon->get_meta('_lrp_affiliate_id');
        $affiliate = new LRP_Affiliate($affiliate_id);
        
        if (!$affiliate->is_active()) {
            return $valid;
        }
        
        $rule = $affiliate->get_guruja_rule();
        
        // Se regra é guruja_priority, não permite cupom
        if ($rule === 'guruja_priority') {
            throw new Exception(
                __('Você possui desconto de aluno Guruja que será aplicado automaticamente.', 'lab-resumos-parceiros')
            );
        }
        
        // Se higher_discount e Guruja é maior, avisa
        if ($rule === 'higher_discount' || $rule === 'no_commission') {
            $guruja_discount = $this->calculate_guruja_discount_total();
            $affiliate_discount = $this->calculate_affiliate_coupon_discount();
            
            if ($guruja_discount > $affiliate_discount) {
                // Permite cupom mas avisa que será bloqueado
                wc_add_notice(
                    __('Seu desconto de aluno Guruja é maior e será aplicado no lugar do cupom.', 'lab-resumos-parceiros'),
                    'notice'
                );
            }
        }
        
        return $valid;
    }

    /**
     * Quando cupom de afiliado é aplicado
     */
    public function on_coupon_applied($coupon_code) {
        $coupon_handler = LRP_Coupon_Handler::instance();
        
        if (!$coupon_handler->is_affiliate_coupon($coupon_code)) {
            return;
        }
        
        // Se é elegível Guruja, força recálculo
        if ($this->is_guruja_eligible()) {
            WC()->cart->calculate_totals();
        }
    }

    /**
     * Verificação final antes do checkout
     */
    public function final_discount_check() {
        // Garante que não há dois descontos aplicados
        $guruja_data = $this->get_guruja_discount_data();
        $coupon_handler = LRP_Coupon_Handler::instance();
        $affiliate_coupon = $coupon_handler->get_affiliate_coupon_from_cart();
        
        if ($guruja_data && $affiliate_coupon) {
            // Conflito! Força resolução
            $this->coordinate_discounts(WC()->cart);
        }
    }

    /**
     * Determina fonte do desconto aplicado
     */
    public function get_applied_discount_source() {
        if ($this->affiliate_discount_applied) {
            return 'affiliate';
        }
        
        if ($this->guruja_discount_applied || $this->is_guruja_eligible()) {
            // Verifica se Guruja fee foi aplicada
            $fees = WC()->cart->get_fees();
            foreach ($fees as $fee) {
                if (strpos($fee->name, 'Guruja') !== false && $fee->amount < 0) {
                    return 'guruja';
                }
            }
        }
        
        // Verifica se há cupom de afiliado
        $coupon_handler = LRP_Coupon_Handler::instance();
        if ($coupon_handler->get_affiliate_coupon_from_cart()) {
            return 'affiliate';
        }
        
        return 'none';
    }

    /**
     * Verifica se afiliado deve ganhar comissão considerando regra Guruja
     */
    public function should_affiliate_earn_commission($affiliate, $discount_source) {
        $rule = $affiliate->get_guruja_rule();
        
        // Se regra é no_commission e desconto foi Guruja, não ganha
        if ($rule === 'no_commission' && $discount_source === 'guruja') {
            return false;
        }
        
        // Em todos os outros casos, ganha (se a venda foi atribuída a ele)
        return true;
    }
}
```

### 3.2 Modificações Necessárias no Plugin Guruja

O plugin Guruja existente precisa de **uma pequena modificação** para permitir que o sistema de afiliados saiba quando o desconto foi aplicado:

```php
// Adicionar no final do método apply_discounts() em class-guruja-integration.php

if ($total_desconto > 0) {
    $cart->add_fee(
        __('Desconto Aluno Guruja', 'lab-resumos-guruja'),
        -$total_desconto,
        false
    );

    $this->log('Desconto total aplicado', ['total' => $total_desconto]);
    
    // NOVO: Dispara ação para integração com outros plugins
    do_action('lrg_guruja_discount_applied', $total_desconto, $session_data);
}
```

Essa é a **única modificação** necessária no plugin Guruja.

---

## 4. Lógica de Atribuição

### 4.1 Classe LRP_Cookie_Tracker

A classe `LRP_Cookie_Tracker` é responsável por gerenciar cookies de rastreamento de afiliados. Ela deve implementar os seguintes métodos:

```php
<?php
/**
 * Rastreamento de afiliados via cookie
 */
class LRP_Cookie_Tracker {

    const COOKIE_NAME = 'lrp_ref';
    const VISIT_COOKIE_NAME = 'lrp_visit_hash';
    
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Captura parâmetro ref da URL e seta cookie
        add_action('template_redirect', [$this, 'capture_referral'], 5);
    }

    /**
     * Captura parâmetro ref da URL e seta cookie
     */
    public function capture_referral() {
        if (!isset($_GET['ref'])) {
            return;
        }
        
        $ref_code = sanitize_text_field($_GET['ref']);
        $affiliate = $this->get_affiliate_by_code($ref_code);
        
        if (!$affiliate || !$affiliate->is_active()) {
            return;
        }
        
        // Obtém duração do cookie (padrão ou individual do afiliado)
        $cookie_days = $affiliate->get_cookie_days();
        if (!$cookie_days) {
            $settings = LRP_Settings::instance();
            $cookie_days = $settings->get('default_cookie_days', 60);
        }
        
        $expiry = time() + ($cookie_days * DAY_IN_SECONDS);
        
        // IMPORTANTE: Sempre usar array de opções com setcookie() para garantir flags de segurança
        // Nunca usar assinatura antiga com parâmetros separados
        setcookie(
            self::COOKIE_NAME,
            $ref_code,
            [
                'expires' => $expiry,
                'path' => '/',
                'domain' => '',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
        
        // Registra visita
        $this->record_visit($affiliate->get_id());
    }

    /**
     * Verifica se cookie de afiliado é válido
     */
    public function is_cookie_valid() {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return false;
        }
        
        $ref_code = sanitize_text_field($_COOKIE[self::COOKIE_NAME]);
        $affiliate = $this->get_affiliate_by_code($ref_code);
        
        return $affiliate && $affiliate->is_active();
    }

    /**
     * Obtém afiliado do cookie
     */
    public function get_affiliate_from_cookie() {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        
        $ref_code = sanitize_text_field($_COOKIE[self::COOKIE_NAME]);
        return $this->get_affiliate_by_code($ref_code);
    }

    /**
     * Busca afiliado pelo código de referral
     */
    private function get_affiliate_by_code($ref_code) {
        global $wpdb;
        
        $affiliate_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lrp_affiliates 
             WHERE referral_code = %s AND status = 'active'",
            $ref_code
        ));
        
        return $affiliate_id ? new LRP_Affiliate($affiliate_id) : null;
    }

    /**
     * Obtém IP real do cliente, considerando proxies confiáveis quando configurado
     * 
     * @return string IP do cliente
     */
    private function get_client_ip() {
        // Verifica se há proxy confiável configurado
        if (defined('LRP_TRUSTED_PROXY') && LRP_TRUSTED_PROXY) {
            // Verifica headers de proxy apenas se proxy confiável
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = trim($ips[0]);
                // Valida formato IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
            if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                $ip = trim($_SERVER['HTTP_X_REAL_IP']);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        // Fallback para REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Obtém ou gera UUID único do visitante (armazenado em cookie)
     * 
     * @return string UUID do visitante
     */
    private function get_visitor_uuid() {
        // Verifica se já existe UUID no cookie
        if (isset($_COOKIE[self::VISIT_COOKIE_NAME])) {
            $uuid = sanitize_text_field($_COOKIE[self::VISIT_COOKIE_NAME]);
            // Valida formato UUID v4
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
                return $uuid;
            }
        }
        
        // Gera novo UUID v4
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, // Versão 4
            mt_rand(0, 0x3fff) | 0x8000, // Variante RFC 4122
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        // Armazena UUID em cookie (válido por 1 ano)
        setcookie(
            self::VISIT_COOKIE_NAME,
            $uuid,
            [
                'expires' => time() + (365 * DAY_IN_SECONDS),
                'path' => '/',
                'domain' => '',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
        
        return $uuid;
    }

    /**
     * Registra visita na tabela lrp_visits
     */
    private function record_visit($affiliate_id) {
        global $wpdb;
        
        // Obtém UUID único do visitante (gerado ou existente no cookie)
        $visitor_uuid = $this->get_visitor_uuid();
        
        // Evita duplicatas recentes (últimas 24h)
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lrp_visits 
             WHERE affiliate_id = %d AND visitor_hash = %s 
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $affiliate_id, $visitor_uuid
        ));
        
        if ($exists) {
            return;
        }
        
        $wpdb->insert($wpdb->prefix . 'lrp_visits', [
            'affiliate_id' => $affiliate_id,
            'visitor_ip' => $this->get_client_ip() ?: null, // Mantido apenas para auditoria/logging
            'visitor_hash' => $visitor_uuid, // Agora usa UUID em vez de hash IP+UA
            'referral_url' => $_SERVER['HTTP_REFERER'] ?? null,
            'landing_page' => $_SERVER['REQUEST_URI'] ?? null,
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Marca visita como convertida após venda
     */
    public function mark_visit_converted($order_id) {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return;
        }
        
        global $wpdb;
        
        // Obtém UUID do visitante do cookie (mesmo usado em record_visit)
        $visitor_uuid = $this->get_visitor_uuid();
        
        // Atualiza visitas não convertidas deste visitante
        $wpdb->update(
            $wpdb->prefix . 'lrp_visits',
            [
                'converted' => 1,
                'order_id' => $order_id,
            ],
            [
                'visitor_hash' => $visitor_uuid,
                'converted' => 0,
            ]
        );
        
        // Remove cookie após conversão (opcional - pode manter para tracking futuro)
        // setcookie(self::COOKIE_NAME, '', time() - 3600, '/');
    }
}
```

### 4.2 Classe LRP_Attribution

```php
<?php
/**
 * Decide a origem de uma venda e se deve gerar comissão
 */
class LRP_Attribution {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Determina atribuição completa de uma venda
     * 
     * @return array|null
     */
    public function determine_attribution() {
        $coupon_handler = LRP_Coupon_Handler::instance();
        $cookie_tracker = LRP_Cookie_Tracker::instance();
        $guruja = LRP_Guruja::instance();
        
        // 1. Verifica cupom de afiliado no carrinho
        $affiliate_from_coupon = $coupon_handler->get_affiliate_from_cart_coupon();
        
        if ($affiliate_from_coupon && $affiliate_from_coupon->is_active()) {
            // Verifica auto-referência
            if ($this->should_block_self_referral($affiliate_from_coupon)) {
                return null;
            }
            
            return [
                'type' => 'coupon',
                'affiliate' => $affiliate_from_coupon,
                'coupon_code' => $coupon_handler->get_affiliate_coupon_from_cart(),
                'commission_rate_type' => 'coupon',
            ];
        }
        
        // 2. Verifica cookie
        if ($cookie_tracker->is_cookie_valid()) {
            $affiliate_from_cookie = $cookie_tracker->get_affiliate_from_cookie();
            
            if ($affiliate_from_cookie && $affiliate_from_cookie->is_active()) {
                // Verifica auto-referência
                if ($this->should_block_self_referral($affiliate_from_cookie)) {
                    return null;
                }
                
                return [
                    'type' => 'link',
                    'affiliate' => $affiliate_from_cookie,
                    'coupon_code' => null,
                    'commission_rate_type' => 'link',
                ];
            }
        }
        
        // 3. Nenhuma atribuição
        return null;
    }

    /**
     * Verifica se deve bloquear por auto-referência
     */
    private function should_block_self_referral($affiliate) {
        if ($affiliate->can_self_refer()) {
            return false;
        }
        
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return false;
        }
        
        return $affiliate->get_user_id() === $current_user_id;
    }

    /**
     * Processa atribuição quando pedido é criado
     */
    public function process_order_attribution($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Já processado?
        if ($order->get_meta('_lrp_referral_id')) {
            return;
        }
        
        $attribution = $this->determine_attribution();
        
        if (!$attribution) {
            return;
        }
        
        $affiliate = $attribution['affiliate'];
        $guruja = LRP_Guruja::instance();
        
        // Determina fonte do desconto
        $discount_source = $guruja->get_applied_discount_source();
        
        // Verifica se deve ganhar comissão (regra Guruja)
        $should_earn = $guruja->should_affiliate_earn_commission($affiliate, $discount_source);
        
        if (!$should_earn) {
            // Registra atribuição mas sem comissão
            $order->update_meta_data('_lrp_affiliate_id', $affiliate->get_id());
            $order->update_meta_data('_lrp_attribution_type', $attribution['type']);
            $order->update_meta_data('_lrp_no_commission_reason', 'guruja_rule');
            $order->save();
            return;
        }
        
        // Calcula valores
        $order_total = (float) $order->get_total();
        $discount_amount = $this->calculate_total_discount($order);
        $commission_base = $order_total; // Já é o valor PAGO (após descontos)
        
        // Cria referral
        $referral = LRP_Referral::create([
            'affiliate_id' => $affiliate->get_id(),
            'order_id' => $order_id,
            'attribution_type' => $attribution['type'],
            'coupon_used' => $attribution['coupon_code'],
            'order_total' => (float) $order->get_subtotal(),
            'discount_amount' => $discount_amount,
            'discount_source' => $discount_source,
            'commission_base' => $commission_base,
            'status' => 'pending',
            'customer_id' => $order->get_customer_id(),
            'customer_email' => $order->get_billing_email(),
            'is_guruja_student' => $guruja->is_guruja_eligible() ? 1 : 0,
        ]);
        
        if (!$referral) {
            return;
        }
        
        // Calcula e cria comissão direta
        $commission_rate = $affiliate->get_commission_rate($attribution['commission_rate_type']);
        $commission_amount = $commission_base * ($commission_rate / 100);
        
        LRP_Commission::create([
            'referral_id' => $referral->get_id(),
            'affiliate_id' => $affiliate->get_id(),
            'commission_type' => 'direct',
            'commission_rate' => $commission_rate,
            'commission_amount' => $commission_amount,
            'status' => 'pending',
        ]);
        
        // Salva metas no pedido
        $order->update_meta_data('_lrp_affiliate_id', $affiliate->get_id());
        $order->update_meta_data('_lrp_referral_id', $referral->get_id());
        $order->update_meta_data('_lrp_attribution_type', $attribution['type']);
        $order->update_meta_data('_lrp_coupon_used', $attribution['coupon_code']);
        $order->update_meta_data('_lrp_commission_amount', $commission_amount);
        $order->update_meta_data('_lrp_is_guruja_discount', $discount_source === 'guruja');
        $order->save();
        
        // Limpa cookie
        LRP_Cookie_Tracker::instance()->mark_visit_converted($order_id);
        
        // Dispara email de nova venda
        do_action('lrp_new_sale', $affiliate, $referral, $order);
        
        // Dispara distribuição multi-nível
        do_action('lrp_referral_created', $referral);
    }

    /**
     * Calcula total de desconto do pedido
     */
    private function calculate_total_discount($order) {
        $discount = 0;
        
        // Desconto de cupons
        $discount += (float) $order->get_discount_total();
        
        // Desconto de fees negativas (Guruja)
        foreach ($order->get_fees() as $fee) {
            if ($fee->get_total() < 0) {
                $discount += abs($fee->get_total());
            }
        }
        
        return $discount;
    }
}
```

---

## 5. Resumo das Interações

### 5.1 Matriz de Decisão

| Cupom Afiliado | Cookie | É Guruja | Regra | Desconto Cliente | Comissão Afiliado |
|----------------|--------|----------|-------|------------------|-------------------|
| ✅ | - | ❌ | - | Cupom | ✅ Taxa cupom |
| ✅ | - | ✅ | higher | Maior | ✅ Taxa cupom |
| ✅ | - | ✅ | affiliate | Cupom | ✅ Taxa cupom |
| ✅ | - | ✅ | guruja | Guruja | ✅ Taxa cupom |
| ✅ | - | ✅ | no_commission | Maior | ❌ Se Guruja |
| ❌ | ✅ | ❌ | - | Nenhum | ✅ Taxa link |
| ❌ | ✅ | ✅ | - | Guruja | ✅ Taxa link |
| ❌ | ❌ | ✅ | - | Guruja | ❌ Venda orgânica |
| ❌ | ❌ | ❌ | - | Nenhum | ❌ Venda orgânica |

### 5.2 Prioridade de Hooks

**Ordem de execução:**
- Prioridade 5: LRP_Coupon_Handler::on_coupon_applied (detecta cupom)
- Prioridade 10: Validações gerais
- Prioridade 15: LRP_Guruja::coordinate_discounts (resolve conflitos ANTES do Guruja)
- Prioridade 20: Guruja::apply_discounts (aplica desconto Guruja)
- Prioridade 25: Totais finais

**Nota sobre Fluid Checkout:**
Fluid Checkout geralmente usa prioridades 10-30. Nossa prioridade 15 está segura e não deve conflitar. Testar exaustivamente com Fluid Checkout ativo.