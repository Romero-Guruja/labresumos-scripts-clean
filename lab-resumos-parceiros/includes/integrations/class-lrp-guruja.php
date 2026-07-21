<?php
/**
 * Integração com Plugin Guruja
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Guruja
 * 
 * Coordena descontos entre afiliados e Guruja.
 */
class LRP_Guruja {

    /**
     * Instância única
     *
     * @var LRP_Guruja|null
     */
    private static $instance = null;

    /**
     * Flag: desconto de afiliado aplicado
     *
     * @var bool
     */
    private $affiliate_discount_applied = false;

    /**
     * Flag: desconto Guruja aplicado
     *
     * @var bool
     */
    private $guruja_discount_applied = false;

    /**
     * Valor do desconto Guruja
     *
     * @var float
     */
    private $guruja_discount_amount = 0;

    /**
     * Valor do desconto de afiliado
     *
     * @var float
     */
    private $affiliate_discount_amount = 0;

    /**
     * Flag: bloquear cupom de afiliado
     *
     * @var bool
     */
    private $block_affiliate_coupon = false;

    /**
     * Código do cupom bloqueado
     *
     * @var string|null
     */
    private $blocked_coupon_code = null;

    /**
     * Flag: cupom removido neste request (evita duplicação)
     *
     * @var bool
     */
    private $coupon_removed_this_request = false;

    /**
     * Retorna instância única
     *
     * @return LRP_Guruja
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado
     */
    private function __construct() {
        // Só registra hooks se plugin Guruja estiver ativo
        if (!$this->is_guruja_active()) {
            return;
        }
        
        // Intercepta ANTES do Guruja aplicar desconto (prioridade 15, Guruja usa 20)
        add_action('woocommerce_cart_calculate_fees', [$this, 'coordinate_discounts'], 15);
        
        // Intercepta validação de cupom
        add_filter('woocommerce_coupon_is_valid', [$this, 'validate_coupon_with_guruja'], 10, 3);
        
        // Bloqueia desconto do cupom quando necessário
        add_filter('woocommerce_coupon_get_discount_amount', [$this, 'block_coupon_discount'], 10, 5);
        
        // Quando cupom de afiliado é aplicado
        add_action('woocommerce_applied_coupon', [$this, 'on_coupon_applied'], 5);
        
        // Antes do checkout processar
        add_action('woocommerce_checkout_process', [$this, 'final_discount_check']);
    }

    /**
     * Verifica se plugin Guruja está ativo
     *
     * @return bool
     */
    public function is_guruja_active() {
        return class_exists('Lab_Resumos_Guruja_Discount') || 
               function_exists('lrg_integration') ||
               class_exists('LRG_Integration');
    }

    /**
     * Obtém desconto Guruja da sessão
     *
     * @return array|null
     */
    public function get_guruja_discount_data() {
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
     *
     * @return bool
     */
    public function is_guruja_eligible() {
        $data = $this->get_guruja_discount_data();
        return !empty($data);
    }

    /**
     * Calcula total do desconto Guruja
     *
     * @return float
     */
    public function calculate_guruja_discount_total() {
        $data = $this->get_guruja_discount_data();
        
        if (!$data || !WC()->cart) {
            return 0;
        }
        
        $total = 0;
        
        foreach (WC()->cart->get_cart() as $cart_item) {
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
     *
     * @return float
     */
    public function calculate_affiliate_coupon_discount() {
        return LRP_Coupon_Handler::instance()->calculate_affiliate_coupon_discount();
    }

    /**
     * Coordena qual desconto aplicar
     *
     * @param WC_Cart $cart
     */
    public function coordinate_discounts($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        // Verifica se Guruja já foi rejeitado em favor de um cupom
        if ($this->was_guruja_rejected_for_coupon()) {
            $this->affiliate_discount_applied = true;
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
                
                // Marca para evitar que Guruja re-aplique via JavaScript
                $this->mark_guruja_rejected_for_coupon($coupon_code);
                
                lrp_log('Regra affiliate_priority: cupom prevalece', [
                    'affiliate_id'       => $affiliate->get_id(),
                    'affiliate_discount' => $affiliate_discount,
                    'guruja_discount'    => $guruja_discount,
                ]);
                break;
                
            case 'guruja_priority':
                // Remove cupom graciosamente, deixa Guruja
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
                    // Guruja é maior ou igual - remove cupom graciosamente
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
                    
                    // Marca para evitar que Guruja re-aplique via JavaScript
                    $this->mark_guruja_rejected_for_coupon($coupon_code);
                    
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
     * Bloqueia desconto do cupom quando necessário
     *
     * @param float $discount
     * @param float $discounting_amount
     * @param array $cart_item
     * @param bool $single
     * @param WC_Coupon $coupon
     * @return float
     */
    public function block_coupon_discount($discount, $discounting_amount, $cart_item, $single, $coupon) {
        if (!$this->block_affiliate_coupon) {
            return $discount;
        }
        
        $coupon_handler = LRP_Coupon_Handler::instance();
        
        // Verifica se é cupom de afiliado bloqueado
        if ($coupon_handler->is_affiliate_coupon($coupon)) {
            $coupon_code = $coupon->get_code();
            
            if (strtolower($coupon_code) === strtolower($this->blocked_coupon_code)) {
                return 0;
            }
        }
        
        return $discount;
    }

    /**
     * Remove cupom de afiliado de forma elegante
     * 
     * Em vez de apenas bloquear o desconto do cupom (que causa confusão visual),
     * remove o cupom do carrinho e exibe mensagem explicativa.
     *
     * @param string $coupon_code Código do cupom
     * @param float $guruja_discount Valor do desconto Guruja para mensagem
     */
    private function remove_affiliate_coupon_gracefully($coupon_code, $guruja_discount) {
        // Evita processamento duplicado no mesmo request
        if ($this->coupon_removed_this_request) {
            return;
        }
        
        if (!WC()->cart || !WC()->cart->has_discount($coupon_code)) {
            return;
        }
        
        $this->coupon_removed_this_request = true;
        
        // Armazena info para tracking (ainda atribui venda ao afiliado)
        if (WC()->session) {
            WC()->session->set('lrp_removed_coupon_for_guruja', [
                'coupon_code' => $coupon_code,
                'guruja_discount' => $guruja_discount,
                'timestamp' => time(),
            ]);
        }
        
        // Remove cupom do carrinho
        WC()->cart->remove_coupon($coupon_code);
        
        // Adiciona notice informativo (não erro)
        $message = sprintf(
            __('Seu desconto de aluno Guruja (R$ %s) é maior e foi aplicado automaticamente!', 'lab-resumos-parceiros'),
            number_format($guruja_discount, 2, ',', '.')
        );
        
        if (!wc_has_notice($message, 'success')) {
            wc_add_notice($message, 'success');
        }
        
        // Reset flags de bloqueio (não precisa mais bloquear, cupom foi removido)
        $this->block_affiliate_coupon = false;
        $this->blocked_coupon_code = null;
        
        lrp_log('Cupom removido em favor do Guruja', [
            'coupon_code' => $coupon_code,
            'guruja_discount' => $guruja_discount,
        ]);
    }

    /**
     * Obtém cupom removido em favor do Guruja (para tracking)
     *
     * @return array|null
     */
    public function get_removed_coupon_for_guruja() {
        if (!WC()->session) {
            return null;
        }
        
        $data = WC()->session->get('lrp_removed_coupon_for_guruja');
        
        // Expira após 1 hora
        if ($data && (time() - ($data['timestamp'] ?? 0)) > 3600) {
            WC()->session->set('lrp_removed_coupon_for_guruja', null);
            return null;
        }
        
        return $data;
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
     * Marca Guruja como rejeitado em favor de cupom de afiliado
     * 
     * Usado quando regra affiliate_priority é aplicada para evitar
     * que o plugin Guruja re-aplique o desconto via JavaScript.
     *
     * @param string $coupon_code Código do cupom que prevaleceu
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
     * Verifica se Guruja foi rejeitado em favor de um cupom
     * 
     * Valida também se o carrinho mudou e se o cupom ainda está aplicado.
     * A flag expira após 2 horas para evitar comportamento indefinido.
     *
     * @return bool
     */
    public function was_guruja_rejected_for_coupon() {
        if (!WC()->session || !WC()->cart) {
            return false;
        }
        
        $data = WC()->session->get('lrp_guruja_rejected');
        
        if (empty($data)) {
            return false;
        }
        
        // Invalida se carrinho mudou (produtos alterados)
        if (!empty($data['cart_hash']) && $data['cart_hash'] !== WC()->cart->get_cart_hash()) {
            WC()->session->set('lrp_guruja_rejected', null);
            return false;
        }
        
        // Verifica se o cupom ainda está no carrinho
        $current_coupon = LRP_Coupon_Handler::instance()->get_affiliate_coupon_from_cart();
        if (!$current_coupon || strtolower($current_coupon) !== strtolower($data['coupon_code'])) {
            WC()->session->set('lrp_guruja_rejected', null);
            return false;
        }
        
        // Invalida se a sessão Guruja foi atualizada DEPOIS da rejeição
        // (nova verificação elegível deve sempre prevalecer)
        $guruja_session = WC()->session->get('lrg_guruja_descontos');
        if (!empty($guruja_session) && !empty($guruja_session['timestamp'])) {
            $rejected_ts = (int) ($data['timestamp'] ?? 0);
            $guruja_ts   = (int) $guruja_session['timestamp'];
            if ($guruja_ts > $rejected_ts) {
                WC()->session->set('lrp_guruja_rejected', null);
                lrp_log('Flag de rejeição invalidada: nova verificação Guruja mais recente', [
                    'rejected_ts' => $rejected_ts,
                    'guruja_ts'   => $guruja_ts,
                ]);
                return false;
            }
        }
        
        // Expira após 2 horas
        if ((time() - ($data['timestamp'] ?? 0)) > 7200) {
            WC()->session->set('lrp_guruja_rejected', null);
            return false;
        }
        
        return true;
    }

    /**
     * Limpa flag de rejeição do Guruja
     */
    public function clear_guruja_rejection() {
        if (WC()->session) {
            WC()->session->set('lrp_guruja_rejected', null);
        }
    }

    /**
     * Valida cupom considerando Guruja
     *
     * @param bool $valid
     * @param WC_Coupon $coupon
     * @param WC_Discounts $discounts
     * @return bool
     * @throws Exception
     */
    public function validate_coupon_with_guruja($valid, $coupon, $discounts) {
        if (!$valid) {
            return $valid;
        }
        
        $coupon_handler = LRP_Coupon_Handler::instance();
        
        if (!$coupon_handler->is_affiliate_coupon($coupon)) {
            return $valid;
        }
        
        // Se não é elegível Guruja, cupom funciona normal
        if (!$this->is_guruja_eligible()) {
            return $valid;
        }
        
        // Tem Guruja - verifica regra do afiliado
        $affiliate = $coupon_handler->get_affiliate_from_coupon($coupon);
        
        if (!$affiliate || !$affiliate->is_active()) {
            return $valid;
        }
        
        $rule = $affiliate->get_guruja_rule();
        
        // Se regra é guruja_priority, avisa que Guruja será usado
        if ($rule === 'guruja_priority') {
            wc_add_notice(
                __('Você possui desconto de aluno Guruja que será aplicado automaticamente.', 'lab-resumos-parceiros'),
                'notice'
            );
        }
        
        // Se higher_discount e Guruja é maior, avisa
        if ($rule === 'higher_discount' || $rule === 'no_commission') {
            $guruja_discount = $this->calculate_guruja_discount_total();
            $affiliate_discount = $this->calculate_affiliate_coupon_discount();
            
            if ($guruja_discount > $affiliate_discount) {
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
     *
     * @param string $coupon_code
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
        $guruja_data = $this->get_guruja_discount_data();
        $coupon_handler = LRP_Coupon_Handler::instance();
        $affiliate_coupon = $coupon_handler->get_affiliate_coupon_from_cart();
        
        if ($guruja_data && $affiliate_coupon) {
            // Conflito! Força resolução
            $this->coordinate_discounts(WC()->cart);
        }
    }

    /**
     * Determina fonte do desconto aplicado (método legado - usa flags voláteis)
     *
     * @deprecated Use get_discount_source_from_order() para maior confiabilidade
     * @return string none|affiliate|guruja
     */
    public function get_applied_discount_source() {
        if ($this->affiliate_discount_applied) {
            return 'affiliate';
        }
        
        if ($this->guruja_discount_applied) {
            return 'guruja';
        }
        
        // Verifica se Guruja fee foi aplicada
        if (WC()->cart) {
            $fees = WC()->cart->get_fees();
            foreach ($fees as $fee) {
                if (strpos(strtolower($fee->name), 'guruja') !== false && $fee->amount < 0) {
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
     * Determina fonte do desconto a partir do ORDER (mais confiável)
     * 
     * Este método analisa o pedido já criado em vez de depender de flags
     * voláteis, garantindo detecção correta da fonte de desconto.
     *
     * @param WC_Order $order
     * @return string none|affiliate|guruja
     */
    public function get_discount_source_from_order($order) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return 'none';
        }
        
        $has_guruja_discount = false;
        $has_affiliate_discount = false;
        
        // 1. Verifica fee do Guruja no pedido
        foreach ($order->get_fees() as $fee) {
            $fee_name = strtolower($fee->get_name());
            if (strpos($fee_name, 'guruja') !== false && $fee->get_total() < 0) {
                $has_guruja_discount = true;
                break;
            }
        }
        
        // 2. Verifica cupom de afiliado COM desconto efetivo
        $coupon_handler = LRP_Coupon_Handler::instance();
        foreach ($order->get_items('coupon') as $coupon_item) {
            $code = $coupon_item->get_code();
            if ($coupon_handler->is_affiliate_coupon($code) && $coupon_item->get_discount() > 0) {
                $has_affiliate_discount = true;
                break;
            }
        }
        
        // 3. Decide com base no que foi encontrado
        // Prioriza affiliate se ambos (caso raro, mas possível em conflitos)
        if ($has_affiliate_discount) {
            return 'affiliate';
        }
        
        if ($has_guruja_discount) {
            return 'guruja';
        }
        
        return 'none';
    }

    /**
     * Verifica se afiliado deve ganhar comissão considerando regra Guruja
     *
     * @param LRP_Affiliate $affiliate
     * @param string $discount_source
     * @return bool
     */
    public function should_affiliate_earn_commission($affiliate, $discount_source) {
        $rule = $affiliate->get_guruja_rule();
        
        // Se regra é no_commission e desconto foi Guruja, não ganha
        if ($rule === 'no_commission' && $discount_source === 'guruja') {
            lrp_log('Comissão bloqueada por regra no_commission', [
                'affiliate_id'    => $affiliate->get_id(),
                'discount_source' => $discount_source,
            ]);
            return false;
        }
        
        // Em todos os outros casos, ganha (se a venda foi atribuída a ele)
        return true;
    }

    /**
     * Reseta flags (útil para testes)
     */
    public function reset_flags() {
        $this->affiliate_discount_applied = false;
        $this->guruja_discount_applied = false;
        $this->guruja_discount_amount = 0;
        $this->affiliate_discount_amount = 0;
        $this->block_affiliate_coupon = false;
        $this->blocked_coupon_code = null;
    }
}

