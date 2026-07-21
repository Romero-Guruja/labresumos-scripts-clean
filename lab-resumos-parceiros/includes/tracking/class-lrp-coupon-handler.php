<?php
/**
 * Handler de Cupons de Afiliados
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Coupon_Handler
 * 
 * Gerencia cupons de afiliados no WooCommerce.
 */
class LRP_Coupon_Handler {

    /**
     * Instância única
     *
     * @var LRP_Coupon_Handler|null
     */
    private static $instance = null;

    /**
     * Retorna instância única
     *
     * @return LRP_Coupon_Handler
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
        // Valida cupom de afiliado
        add_filter('woocommerce_coupon_is_valid', [$this, 'validate_affiliate_coupon'], 10, 3);
        
        // Quando cupom é aplicado
        add_action('woocommerce_applied_coupon', [$this, 'on_coupon_applied'], 5);
        
        // Quando cupom é removido
        add_action('woocommerce_removed_coupon', [$this, 'on_coupon_removed']);
    }

    /**
     * Verifica se é um cupom de afiliado
     *
     * @param WC_Coupon|string $coupon
     * @return bool
     */
    public function is_affiliate_coupon($coupon) {
        if (is_string($coupon)) {
            $coupon = new WC_Coupon($coupon);
        }
        
        if (!$coupon || !$coupon->get_id()) {
            return false;
        }
        
        return (bool) $coupon->get_meta('_lrp_is_affiliate_coupon');
    }

    /**
     * Obtém afiliado de um cupom
     *
     * @param WC_Coupon|string $coupon
     * @return LRP_Affiliate|null
     */
    public function get_affiliate_from_coupon($coupon) {
        if (is_string($coupon)) {
            $coupon = new WC_Coupon($coupon);
        }
        
        if (!$this->is_affiliate_coupon($coupon)) {
            return null;
        }
        
        $affiliate_id = $coupon->get_meta('_lrp_affiliate_id');
        
        if (!$affiliate_id) {
            // Tenta buscar pelo código
            return LRP_Affiliate::get_by_coupon_code($coupon->get_code());
        }
        
        return new LRP_Affiliate($affiliate_id);
    }

    /**
     * Obtém código do cupom de afiliado do carrinho
     *
     * @return string|null
     */
    public function get_affiliate_coupon_from_cart() {
        if (!WC()->cart) {
            return null;
        }
        
        $coupons = WC()->cart->get_applied_coupons();
        
        foreach ($coupons as $coupon_code) {
            if ($this->is_affiliate_coupon($coupon_code)) {
                return $coupon_code;
            }
        }
        
        return null;
    }

    /**
     * Obtém afiliado do cupom no carrinho
     *
     * @return LRP_Affiliate|null
     */
    public function get_affiliate_from_cart_coupon() {
        $coupon_code = $this->get_affiliate_coupon_from_cart();
        
        if (!$coupon_code) {
            return null;
        }
        
        return $this->get_affiliate_from_coupon($coupon_code);
    }

    /**
     * Valida cupom de afiliado
     *
     * @param bool $valid
     * @param WC_Coupon $coupon
     * @param WC_Discounts $discounts
     * @return bool
     * @throws Exception
     */
    public function validate_affiliate_coupon($valid, $coupon, $discounts) {
        if (!$valid) {
            return $valid;
        }
        
        if (!$this->is_affiliate_coupon($coupon)) {
            return $valid;
        }
        
        $affiliate = $this->get_affiliate_from_coupon($coupon);
        
        if (!$affiliate) {
            throw new Exception(__('Cupom de afiliado inválido.', 'lab-resumos-parceiros'));
        }
        
        // Verifica se afiliado está ativo
        if (!$affiliate->is_active()) {
            throw new Exception(__('Este cupom não está mais disponível.', 'lab-resumos-parceiros'));
        }
        
        // Verifica auto-referência
        if (!$affiliate->can_self_refer()) {
            $current_user_id = get_current_user_id();
            
            if ($current_user_id && $affiliate->get_user_id() === $current_user_id) {
                throw new Exception(__('Você não pode usar seu próprio cupom de afiliado.', 'lab-resumos-parceiros'));
            }
        }
        
        return $valid;
    }

    /**
     * Quando cupom é aplicado
     *
     * @param string $coupon_code
     */
    public function on_coupon_applied($coupon_code) {
        if (!$this->is_affiliate_coupon($coupon_code)) {
            return;
        }
        
        $affiliate = $this->get_affiliate_from_coupon($coupon_code);
        
        if (!$affiliate) {
            return;
        }
        
        lrp_log('Cupom de afiliado aplicado', [
            'coupon_code'  => $coupon_code,
            'affiliate_id' => $affiliate->get_id(),
        ]);
        
        // Recalcula carrinho
        WC()->cart->calculate_totals();
    }

    /**
     * Quando cupom é removido
     *
     * @param string $coupon_code
     */
    public function on_coupon_removed($coupon_code) {
        if (!$this->is_affiliate_coupon($coupon_code)) {
            return;
        }
        
        lrp_log('Cupom de afiliado removido', [
            'coupon_code' => $coupon_code,
        ]);
    }

    /**
     * Cria cupom WooCommerce para afiliado
     *
     * @param LRP_Affiliate $affiliate
     * @return int|false ID do cupom ou false
     */
    public function create_affiliate_coupon($affiliate) {
        $coupon_code = $affiliate->get_coupon_code();
        
        // Verifica se cupom já existe
        $existing = new WC_Coupon($coupon_code);
        if ($existing->get_id() > 0) {
            // Atualiza desconto se já existe
            $discount = $affiliate->get_customer_discount();
            $existing->set_amount($discount);
            $existing->save();
            return $existing->get_id();
        }
        
        // Obtém desconto do afiliado (individual ou global)
        $discount = $affiliate->get_customer_discount();
        
        // Cria cupom
        $coupon = new WC_Coupon();
        $coupon->set_code($coupon_code);
        $coupon->set_discount_type('percent');
        $coupon->set_amount($discount);
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(0);
        $coupon->set_usage_limit_per_user(0);
        $coupon->set_free_shipping(false);
        $coupon->set_exclude_sale_items(false);
        
        // Meta dados para identificação
        $coupon->add_meta_data('_lrp_affiliate_id', $affiliate->get_id());
        $coupon->add_meta_data('_lrp_is_affiliate_coupon', true);
        
        $coupon_id = $coupon->save();
        
        if (is_wp_error($coupon_id)) {
            lrp_log('Erro ao criar cupom de afiliado', [
                'affiliate_id' => $affiliate->get_id(),
                'error'        => $coupon_id->get_error_message(),
            ], 'error');
            return false;
        }
        
        lrp_log('Cupom de afiliado criado', [
            'coupon_id'    => $coupon_id,
            'coupon_code'  => $coupon_code,
            'affiliate_id' => $affiliate->get_id(),
            'discount'     => $discount,
        ]);
        
        return $coupon_id;
    }

    /**
     * Atualiza desconto do cupom de afiliado
     *
     * @param LRP_Affiliate $affiliate
     * @param float $discount Novo desconto
     * @return bool
     */
    public function update_affiliate_coupon_discount($affiliate, $discount) {
        $coupon_code = $affiliate->get_coupon_code();
        $coupon = new WC_Coupon($coupon_code);
        
        if (!$coupon->get_id()) {
            return false;
        }
        
        $coupon->set_amount($discount);
        $coupon->save();
        
        return true;
    }

    /**
     * Remove cupom de afiliado
     *
     * @param LRP_Affiliate $affiliate
     * @return bool
     */
    public function delete_affiliate_coupon($affiliate) {
        $coupon_code = $affiliate->get_coupon_code();
        $coupon = new WC_Coupon($coupon_code);
        
        if (!$coupon->get_id()) {
            return true;
        }
        
        return wp_delete_post($coupon->get_id(), true) !== false;
    }

    /**
     * Calcula desconto do cupom de afiliado no carrinho
     *
     * @return float
     */
    public function calculate_affiliate_coupon_discount() {
        $coupon_code = $this->get_affiliate_coupon_from_cart();
        
        if (!$coupon_code) {
            return 0;
        }
        
        $coupon = new WC_Coupon($coupon_code);
        $discount_type = $coupon->get_discount_type();
        $amount = $coupon->get_amount();
        
        $cart_total = WC()->cart->get_subtotal();
        
        if ($discount_type === 'percent') {
            return $cart_total * ($amount / 100);
        }
        
        return $amount;
    }
}

