<?php
/**
 * Lógica de Atribuição de Vendas
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Attribution
 * 
 * Decide a origem de uma venda e se deve gerar comissão.
 */
class LRP_Attribution {

    /**
     * Instância única
     *
     * @var LRP_Attribution|null
     */
    private static $instance = null;

    /**
     * Retorna instância única
     *
     * @return LRP_Attribution
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
        // Hooks são registrados pelo LRP_WooCommerce
    }

    /**
     * Determina atribuição completa de uma venda
     * 
     * Suporta comissões cumulativas:
     * - Link + Cupom (mesmo afiliado): taxa somada
     * - Link + Cupom (afiliados diferentes): duas comissões separadas
     *
     * @return array|null
     */
    public function determine_attribution() {
        $coupon_handler = LRP_Coupon_Handler::instance();
        $cookie_tracker = LRP_Cookie_Tracker::instance();
        
        $affiliate_from_coupon = null;
        $affiliate_from_link = null;
        $coupon_code = null;
        
        // 1. Verifica cupom de afiliado no carrinho
        $coupon_affiliate = $coupon_handler->get_affiliate_from_cart_coupon();
        if ($coupon_affiliate && $coupon_affiliate->is_active()) {
            if (!$this->should_block_self_referral($coupon_affiliate)) {
                $affiliate_from_coupon = $coupon_affiliate;
                $coupon_code = $coupon_handler->get_affiliate_coupon_from_cart();
            } else {
                lrp_log('Auto-referência bloqueada (cupom)', [
                    'affiliate_id' => $coupon_affiliate->get_id(),
                ]);
            }
        }
        
        // 2. Verifica cookie (link de afiliado)
        if ($cookie_tracker->is_cookie_valid()) {
            $link_affiliate = $cookie_tracker->get_affiliate_from_cookie();
            if ($link_affiliate && $link_affiliate->is_active()) {
                if (!$this->should_block_self_referral($link_affiliate)) {
                    $affiliate_from_link = $link_affiliate;
                } else {
                    lrp_log('Auto-referência bloqueada (cookie)', [
                        'affiliate_id' => $link_affiliate->get_id(),
                    ]);
                }
            }
        }
        
        // 3. Determina tipo de atribuição
        
        // Ambos presentes
        if ($affiliate_from_coupon && $affiliate_from_link) {
            $same_affiliate = $affiliate_from_coupon->get_id() === $affiliate_from_link->get_id();
            
            if ($same_affiliate) {
                // Mesmo afiliado: taxa combinada (link + cupom)
                $link_rate = $affiliate_from_coupon->get_commission_rate('link');
                $coupon_rate = $affiliate_from_coupon->get_commission_rate('coupon');
                $combined_rate = $link_rate + $coupon_rate;
                
                lrp_log('Atribuição cumulativa (mesmo afiliado)', [
                    'affiliate_id' => $affiliate_from_coupon->get_id(),
                    'link_rate'    => $link_rate,
                    'coupon_rate'  => $coupon_rate,
                    'combined_rate' => $combined_rate,
                ]);
                
                return [
                    'type'                 => 'both',
                    'affiliate'            => $affiliate_from_coupon,
                    'coupon_code'          => $coupon_code,
                    'commission_rate_type' => 'combined',
                    'combined_rate'        => $combined_rate,
                    'same_affiliate'       => true,
                ];
            } else {
                // Afiliados diferentes: duas comissões separadas
                lrp_log('Atribuição cumulativa (afiliados diferentes)', [
                    'coupon_affiliate_id' => $affiliate_from_coupon->get_id(),
                    'link_affiliate_id'   => $affiliate_from_link->get_id(),
                ]);
                
                return [
                    'type'                  => 'both',
                    'affiliate'             => $affiliate_from_coupon, // Principal (cupom)
                    'affiliate_coupon'      => $affiliate_from_coupon,
                    'affiliate_link'        => $affiliate_from_link,
                    'coupon_code'           => $coupon_code,
                    'commission_rate_type'  => 'separate',
                    'same_affiliate'        => false,
                ];
            }
        }
        
        // Apenas cupom
        if ($affiliate_from_coupon) {
            return [
                'type'                 => 'coupon',
                'affiliate'            => $affiliate_from_coupon,
                'coupon_code'          => $coupon_code,
                'commission_rate_type' => 'coupon',
            ];
        }
        
        // Apenas link
        if ($affiliate_from_link) {
            return [
                'type'                 => 'link',
                'affiliate'            => $affiliate_from_link,
                'coupon_code'          => null,
                'commission_rate_type' => 'link',
            ];
        }
        
        // Nenhuma atribuição
        return null;
    }

    /**
     * Verifica se deve bloquear por auto-referência
     *
     * @param LRP_Affiliate $affiliate
     * @return bool
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
     *
     * @param int $order_id
     */
    public function process_order_attribution($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Logging estruturado para debug
        $fees_data = [];
        foreach ($order->get_fees() as $fee) {
            $fees_data[] = [
                'name' => $fee->get_name(),
                'total' => $fee->get_total(),
            ];
        }
        
        lrp_log('Iniciando atribuição de pedido', [
            'order_id' => $order_id,
            'order_total' => $order->get_total(),
            'order_subtotal' => $order->get_subtotal(),
            'coupons' => $order->get_coupon_codes(),
            'fees' => $fees_data,
        ]);
        
        // Já processado?
        if ($order->get_meta('_lrp_referral_id')) {
            lrp_log('Pedido já processado', ['order_id' => $order_id]);
            return;
        }
        
        $attribution = $this->determine_attribution();
        
        if (!$attribution) {
            lrp_log('Sem atribuição para pedido', ['order_id' => $order_id]);
            return;
        }
        
        // Processa conforme o tipo de atribuição
        if ($attribution['type'] === 'both') {
            $this->process_cumulative_attribution($order, $attribution);
        } else {
            $this->process_single_attribution($order, $attribution);
        }
    }
    
    /**
     * Processa atribuição cumulativa (link + cupom)
     *
     * @param WC_Order $order
     * @param array $attribution
     */
    private function process_cumulative_attribution($order, $attribution) {
        $order_id = $order->get_id();
        $guruja = LRP_Guruja::instance();
        $discount_source = $guruja->get_discount_source_from_order($order);
        
        // Calcula valores base
        $order_total = (float) $order->get_total();
        $discount_amount = $this->calculate_total_discount($order);
        
        if ($attribution['same_affiliate']) {
            // Mesmo afiliado: uma comissão com taxa combinada
            $this->process_cumulative_same_affiliate($order, $attribution, $discount_source, $order_total, $discount_amount);
        } else {
            // Afiliados diferentes: duas comissões separadas
            $this->process_cumulative_different_affiliates($order, $attribution, $discount_source, $order_total, $discount_amount);
        }
    }
    
    /**
     * Processa atribuição cumulativa do mesmo afiliado (taxa somada)
     *
     * @param WC_Order $order
     * @param array $attribution
     * @param string|null $discount_source
     * @param float $order_total
     * @param float $discount_amount
     */
    private function process_cumulative_same_affiliate($order, $attribution, $discount_source, $order_total, $discount_amount) {
        $order_id = $order->get_id();
        $affiliate = $attribution['affiliate'];
        $guruja = LRP_Guruja::instance();
        
        // Verifica regra Guruja
        if (!$guruja->should_affiliate_earn_commission($affiliate, $discount_source)) {
            $order->update_meta_data('_lrp_affiliate_id', $affiliate->get_id());
            $order->update_meta_data('_lrp_attribution_type', 'both');
            $order->update_meta_data('_lrp_no_commission_reason', 'guruja_rule');
            $order->save();
            
            lrp_log('Atribuição cumulativa sem comissão (regra Guruja)', [
                'order_id'     => $order_id,
                'affiliate_id' => $affiliate->get_id(),
            ]);
            return;
        }
        
        // Verifica restrições de produtos
        $restriction_handler = LRP_Product_Restriction::instance();
        $filtered_products = $restriction_handler->filter_order_products($affiliate->get_id(), $order);
        
        $restricted_product_ids = [];
        $has_restrictions = !empty($filtered_products['restricted_items']);
        
        if ($has_restrictions) {
            $restricted_product_ids = array_column($filtered_products['restricted_items'], 'product_id');
        }
        
        if (empty($filtered_products['allowed_items'])) {
            $order->update_meta_data('_lrp_affiliate_id', $affiliate->get_id());
            $order->update_meta_data('_lrp_attribution_type', 'both');
            $order->update_meta_data('_lrp_no_commission_reason', 'all_products_restricted');
            $order->update_meta_data('_lrp_restricted_products', $restricted_product_ids);
            $order->save();
            return;
        }
        
        // Calcula commission_base
        $commission_base = $this->calculate_proportional_commission_base(
            $order,
            $filtered_products['allowed_total'],
            $order_total
        );
        
        // Cria referral com tipo 'both'
        $referral = LRP_Referral::create([
            'affiliate_id'      => $affiliate->get_id(),
            'order_id'          => $order_id,
            'attribution_type'  => 'both',
            'coupon_used'       => $attribution['coupon_code'],
            'order_total'       => (float) $order->get_subtotal(),
            'discount_amount'   => $discount_amount,
            'discount_source'   => $discount_source,
            'commission_base'   => $commission_base,
            'status'            => 'pending',
            'customer_id'       => $order->get_customer_id(),
            'customer_email'    => $order->get_billing_email(),
            'is_guruja_student' => $guruja->is_guruja_eligible() ? 1 : 0,
        ]);
        
        if (is_wp_error($referral)) {
            lrp_log('Erro ao criar referral cumulativo', [
                'order_id' => $order_id,
                'error'    => $referral->get_error_message(),
            ], 'error');
            return;
        }
        
        // Usa taxa combinada (link + cupom)
        $commission_rate = $attribution['combined_rate'];
        $commission_amount = $commission_base * ($commission_rate / 100);
        
        $commission = LRP_Commission::create([
            'referral_id'       => $referral->get_id(),
            'affiliate_id'      => $affiliate->get_id(),
            'commission_type'   => 'direct',
            'commission_rate'   => $commission_rate,
            'commission_amount' => $commission_amount,
            'status'            => 'pending',
        ]);
        
        if (is_wp_error($commission)) {
            lrp_log('Erro ao criar comissão cumulativa', [
                'referral_id' => $referral->get_id(),
                'error'       => $commission->get_error_message(),
            ], 'error');
        }
        
        // Salva metas no pedido
        $order->update_meta_data('_lrp_affiliate_id', $affiliate->get_id());
        $order->update_meta_data('_lrp_referral_id', $referral->get_id());
        $order->update_meta_data('_lrp_attribution_type', 'both');
        $order->update_meta_data('_lrp_coupon_used', $attribution['coupon_code']);
        $order->update_meta_data('_lrp_commission_amount', $commission_amount);
        $order->update_meta_data('_lrp_is_guruja_discount', $discount_source === 'guruja');
        $order->update_meta_data('_lrp_tracking_cookie', LRP_Cookie_Tracker::instance()->get_referral_code());
        $order->update_meta_data('_lrp_cumulative_same_affiliate', true);
        
        if ($has_restrictions) {
            $order->update_meta_data('_lrp_restricted_products', $restricted_product_ids);
            $order->update_meta_data('_lrp_commission_reduced', true);
            $order->update_meta_data('_lrp_original_order_total', $order_total);
        }
        
        $order->save();
        
        LRP_Cookie_Tracker::instance()->mark_visit_converted($order_id);
        
        lrp_log('Atribuição cumulativa (mesmo afiliado) processada', [
            'order_id'          => $order_id,
            'affiliate_id'      => $affiliate->get_id(),
            'referral_id'       => $referral->get_id(),
            'commission_base'   => $commission_base,
            'combined_rate'     => $commission_rate,
            'commission_amount' => $commission_amount,
        ]);
        
        do_action('lrp_new_sale', $affiliate, $referral, $order);
        do_action('lrp_referral_created', $referral);
    }
    
    /**
     * Processa atribuição cumulativa de afiliados diferentes (duas comissões)
     *
     * @param WC_Order $order
     * @param array $attribution
     * @param string|null $discount_source
     * @param float $order_total
     * @param float $discount_amount
     */
    private function process_cumulative_different_affiliates($order, $attribution, $discount_source, $order_total, $discount_amount) {
        $order_id = $order->get_id();
        $guruja = LRP_Guruja::instance();
        
        $affiliate_coupon = $attribution['affiliate_coupon'];
        $affiliate_link = $attribution['affiliate_link'];
        
        $referral_ids = [];
        $commission_amounts = [];
        $affiliate_ids = [];
        
        // Processa afiliado do cupom
        $coupon_result = $this->create_commission_for_affiliate(
            $order,
            $affiliate_coupon,
            'coupon',
            $attribution['coupon_code'],
            $discount_source,
            $order_total,
            $discount_amount,
            $guruja
        );
        
        if ($coupon_result) {
            $referral_ids[] = $coupon_result['referral_id'];
            $commission_amounts[] = $coupon_result['commission_amount'];
            $affiliate_ids[] = $affiliate_coupon->get_id();
        }
        
        // Processa afiliado do link
        $link_result = $this->create_commission_for_affiliate(
            $order,
            $affiliate_link,
            'link',
            null,
            $discount_source,
            $order_total,
            $discount_amount,
            $guruja
        );
        
        if ($link_result) {
            $referral_ids[] = $link_result['referral_id'];
            $commission_amounts[] = $link_result['commission_amount'];
            $affiliate_ids[] = $affiliate_link->get_id();
        }
        
        // Salva metas no pedido (usando o primeiro afiliado como principal)
        if (!empty($referral_ids)) {
            $order->update_meta_data('_lrp_affiliate_id', $affiliate_ids[0]);
            $order->update_meta_data('_lrp_referral_id', $referral_ids[0]);
            $order->update_meta_data('_lrp_attribution_type', 'both');
            $order->update_meta_data('_lrp_coupon_used', $attribution['coupon_code']);
            $order->update_meta_data('_lrp_commission_amount', array_sum($commission_amounts));
            $order->update_meta_data('_lrp_is_guruja_discount', $discount_source === 'guruja');
            $order->update_meta_data('_lrp_tracking_cookie', LRP_Cookie_Tracker::instance()->get_referral_code());
            
            // Metas específicas para afiliados diferentes
            $order->update_meta_data('_lrp_cumulative_different_affiliates', true);
            $order->update_meta_data('_lrp_affiliate_coupon_id', $affiliate_coupon->get_id());
            $order->update_meta_data('_lrp_affiliate_link_id', $affiliate_link->get_id());
            $order->update_meta_data('_lrp_referral_ids', $referral_ids);
            
            $order->save();
            
            LRP_Cookie_Tracker::instance()->mark_visit_converted($order_id);
            
            lrp_log('Atribuição cumulativa (afiliados diferentes) processada', [
                'order_id'            => $order_id,
                'coupon_affiliate_id' => $affiliate_coupon->get_id(),
                'link_affiliate_id'   => $affiliate_link->get_id(),
                'referral_ids'        => $referral_ids,
                'total_commission'    => array_sum($commission_amounts),
            ]);
        }
    }
    
    /**
     * Cria comissão para um afiliado específico (usado em atribuição cumulativa)
     *
     * @param WC_Order $order
     * @param LRP_Affiliate $affiliate
     * @param string $attribution_type 'link' ou 'coupon'
     * @param string|null $coupon_code
     * @param string|null $discount_source
     * @param float $order_total
     * @param float $discount_amount
     * @param LRP_Guruja $guruja
     * @return array|null
     */
    private function create_commission_for_affiliate($order, $affiliate, $attribution_type, $coupon_code, $discount_source, $order_total, $discount_amount, $guruja) {
        $order_id = $order->get_id();
        
        // Verifica regra Guruja
        if (!$guruja->should_affiliate_earn_commission($affiliate, $discount_source)) {
            lrp_log('Afiliado não ganha comissão (regra Guruja)', [
                'order_id'     => $order_id,
                'affiliate_id' => $affiliate->get_id(),
                'type'         => $attribution_type,
            ]);
            return null;
        }
        
        // Verifica restrições de produtos
        $restriction_handler = LRP_Product_Restriction::instance();
        $filtered_products = $restriction_handler->filter_order_products($affiliate->get_id(), $order);
        
        if (empty($filtered_products['allowed_items'])) {
            lrp_log('Afiliado sem produtos permitidos', [
                'order_id'     => $order_id,
                'affiliate_id' => $affiliate->get_id(),
                'type'         => $attribution_type,
            ]);
            return null;
        }
        
        // Calcula commission_base
        $commission_base = $this->calculate_proportional_commission_base(
            $order,
            $filtered_products['allowed_total'],
            $order_total
        );
        
        // Cria referral (permite múltiplos para atribuição cumulativa com afiliados diferentes)
        $referral = LRP_Referral::create([
            'affiliate_id'      => $affiliate->get_id(),
            'order_id'          => $order_id,
            'attribution_type'  => $attribution_type, // 'link' ou 'coupon' para identificar a origem
            'coupon_used'       => $coupon_code,
            'order_total'       => (float) $order->get_subtotal(),
            'discount_amount'   => $discount_amount,
            'discount_source'   => $discount_source,
            'commission_base'   => $commission_base,
            'status'            => 'pending',
            'customer_id'       => $order->get_customer_id(),
            'customer_email'    => $order->get_billing_email(),
            'is_guruja_student' => $guruja->is_guruja_eligible() ? 1 : 0,
        ], true); // allow_multiple = true para comissões cumulativas
        
        if (is_wp_error($referral)) {
            lrp_log('Erro ao criar referral para afiliado', [
                'order_id'     => $order_id,
                'affiliate_id' => $affiliate->get_id(),
                'type'         => $attribution_type,
                'error'        => $referral->get_error_message(),
            ], 'error');
            return null;
        }
        
        // Calcula comissão
        $commission_rate = $affiliate->get_commission_rate($attribution_type);
        $commission_amount = $commission_base * ($commission_rate / 100);
        
        $commission = LRP_Commission::create([
            'referral_id'       => $referral->get_id(),
            'affiliate_id'      => $affiliate->get_id(),
            'commission_type'   => 'direct',
            'commission_rate'   => $commission_rate,
            'commission_amount' => $commission_amount,
            'status'            => 'pending',
        ]);
        
        if (is_wp_error($commission)) {
            lrp_log('Erro ao criar comissão para afiliado', [
                'referral_id' => $referral->get_id(),
                'error'       => $commission->get_error_message(),
            ], 'error');
        }
        
        do_action('lrp_new_sale', $affiliate, $referral, $order);
        do_action('lrp_referral_created', $referral);
        
        return [
            'referral_id'       => $referral->get_id(),
            'commission_rate'   => $commission_rate,
            'commission_amount' => $commission_amount,
        ];
    }
    
    /**
     * Processa atribuição simples (link OU cupom, não ambos)
     *
     * @param WC_Order $order
     * @param array $attribution
     */
    private function process_single_attribution($order, $attribution) {
        $order_id = $order->get_id();
        $affiliate = $attribution['affiliate'];
        $guruja = LRP_Guruja::instance();
        
        // Determina fonte do desconto a partir do ORDER (mais confiável que flags)
        $discount_source = $guruja->get_discount_source_from_order($order);
        
        // Verifica se deve ganhar comissão (regra Guruja)
        $should_earn = $guruja->should_affiliate_earn_commission($affiliate, $discount_source);
        
        if (!$should_earn) {
            // Registra atribuição mas sem comissão
            $order->update_meta_data('_lrp_affiliate_id', $affiliate->get_id());
            $order->update_meta_data('_lrp_attribution_type', $attribution['type']);
            $order->update_meta_data('_lrp_no_commission_reason', 'guruja_rule');
            $order->save();
            
            lrp_log('Atribuição sem comissão (regra Guruja)', [
                'order_id'     => $order_id,
                'affiliate_id' => $affiliate->get_id(),
            ]);
            return;
        }
        
        // Calcula valores base
        $order_total = (float) $order->get_total();
        $discount_amount = $this->calculate_total_discount($order);
        
        // Verifica restrições de produtos do afiliado
        $restriction_handler = LRP_Product_Restriction::instance();
        $filtered_products = $restriction_handler->filter_order_products($affiliate->get_id(), $order);
        
        $restricted_product_ids = [];
        $has_restrictions = false;
        
        // Se há produtos restritos
        if (!empty($filtered_products['restricted_items'])) {
            $has_restrictions = true;
            $restricted_product_ids = array_column($filtered_products['restricted_items'], 'product_id');
            
            lrp_log('Pedido com produtos restritos', [
                'order_id'            => $order_id,
                'affiliate_id'        => $affiliate->get_id(),
                'restricted_products' => $restricted_product_ids,
                'allowed_total'       => $filtered_products['allowed_total'],
            ]);
        }
        
        // Se nenhum produto é permitido, não gera comissão
        if (empty($filtered_products['allowed_items'])) {
            $order->update_meta_data('_lrp_affiliate_id', $affiliate->get_id());
            $order->update_meta_data('_lrp_attribution_type', $attribution['type']);
            $order->update_meta_data('_lrp_no_commission_reason', 'all_products_restricted');
            $order->update_meta_data('_lrp_restricted_products', $restricted_product_ids);
            $order->save();
            
            lrp_log('Atribuição sem comissão (todos produtos restritos)', [
                'order_id'     => $order_id,
                'affiliate_id' => $affiliate->get_id(),
            ]);
            return;
        }
        
        // Calcula commission_base proporcional aos produtos permitidos
        $commission_base = $this->calculate_proportional_commission_base(
            $order,
            $filtered_products['allowed_total'],
            $order_total
        );
        
        // Cria referral
        $referral = LRP_Referral::create([
            'affiliate_id'      => $affiliate->get_id(),
            'order_id'          => $order_id,
            'attribution_type'  => $attribution['type'],
            'coupon_used'       => $attribution['coupon_code'],
            'order_total'       => (float) $order->get_subtotal(),
            'discount_amount'   => $discount_amount,
            'discount_source'   => $discount_source,
            'commission_base'   => $commission_base,
            'status'            => 'pending',
            'customer_id'       => $order->get_customer_id(),
            'customer_email'    => $order->get_billing_email(),
            'is_guruja_student' => $guruja->is_guruja_eligible() ? 1 : 0,
        ]);
        
        if (is_wp_error($referral)) {
            lrp_log('Erro ao criar referral', [
                'order_id' => $order_id,
                'error'    => $referral->get_error_message(),
            ], 'error');
            return;
        }
        
        // Calcula e cria comissão direta
        $commission_rate = $affiliate->get_commission_rate($attribution['commission_rate_type']);
        $commission_amount = $commission_base * ($commission_rate / 100);
        
        $commission = LRP_Commission::create([
            'referral_id'       => $referral->get_id(),
            'affiliate_id'      => $affiliate->get_id(),
            'commission_type'   => 'direct',
            'commission_rate'   => $commission_rate,
            'commission_amount' => $commission_amount,
            'status'            => 'pending',
        ]);
        
        if (is_wp_error($commission)) {
            lrp_log('Erro ao criar comissão', [
                'referral_id' => $referral->get_id(),
                'error'       => $commission->get_error_message(),
            ], 'error');
        }
        
        // Salva metas no pedido
        $order->update_meta_data('_lrp_affiliate_id', $affiliate->get_id());
        $order->update_meta_data('_lrp_referral_id', $referral->get_id());
        $order->update_meta_data('_lrp_attribution_type', $attribution['type']);
        $order->update_meta_data('_lrp_coupon_used', $attribution['coupon_code']);
        $order->update_meta_data('_lrp_commission_amount', $commission_amount);
        $order->update_meta_data('_lrp_is_guruja_discount', $discount_source === 'guruja');
        $order->update_meta_data('_lrp_tracking_cookie', LRP_Cookie_Tracker::instance()->get_referral_code());
        
        // Salva produtos restritos se houver
        if ($has_restrictions) {
            $order->update_meta_data('_lrp_restricted_products', $restricted_product_ids);
            $order->update_meta_data('_lrp_commission_reduced', true);
            $order->update_meta_data('_lrp_original_order_total', $order_total);
        }
        
        $order->save();
        
        // Marca visita como convertida
        LRP_Cookie_Tracker::instance()->mark_visit_converted($order_id);
        
        lrp_log('Atribuição processada com sucesso', [
            'order_id'             => $order_id,
            'affiliate_id'         => $affiliate->get_id(),
            'referral_id'          => $referral->get_id(),
            'attribution_type'     => $attribution['type'],
            'commission_base'      => $commission_base,
            'commission_rate'      => $commission_rate,
            'commission_amount'    => $commission_amount,
            'has_restrictions'     => $has_restrictions,
            'restricted_products'  => $restricted_product_ids,
        ]);
        
        // Dispara actions
        do_action('lrp_new_sale', $affiliate, $referral, $order);
        do_action('lrp_referral_created', $referral);
    }
    
    /**
     * Calcula commission_base proporcional aos produtos permitidos
     * 
     * O cálculo respeita a configuração commission_base_type:
     * - order_total: Baseado no total pago (inclui frete, taxas, descontos)
     * - subtotal_only: Apenas subtotal dos produtos
     * - subtotal_minus_discount: Subtotal menos descontos aplicados
     *
     * @param WC_Order $order
     * @param float $allowed_subtotal Subtotal dos produtos permitidos
     * @param float $order_total Total pago do pedido
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
        
        // Obtém tipo de base configurado
        $base_type = lrp_settings()->get_commission_base_type();
        
        switch ($base_type) {
            case 'subtotal_only':
                // Usa apenas o subtotal dos produtos (sem frete, sem taxas)
                $base_value = $order_subtotal;
                break;
                
            case 'subtotal_minus_discount':
                // Subtotal menos descontos aplicados
                $total_discount = $this->calculate_total_discount($order);
                $base_value = max(0, $order_subtotal - $total_discount);
                break;
                
            case 'order_total':
            default:
                // Total pago (comportamento padrão original)
                $base_value = $order_total;
                break;
        }
        
        // Aplica a proporção ao valor base calculado
        $proportional_base = $base_value * $proportion;
        
        lrp_log('Base de comissão calculada', [
            'base_type' => $base_type,
            'order_subtotal' => $order_subtotal,
            'order_total' => $order_total,
            'allowed_subtotal' => $allowed_subtotal,
            'proportion' => $proportion,
            'base_value' => $base_value,
            'proportional_base' => $proportional_base,
        ]);
        
        return round($proportional_base, 2);
    }

    /**
     * Calcula total de desconto do pedido
     *
     * @param WC_Order $order
     * @return float
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

    /**
     * Retorna dados de atribuição para exibição
     *
     * @return array|null
     */
    public function get_attribution_data() {
        $attribution = $this->determine_attribution();
        
        if (!$attribution) {
            return null;
        }
        
        $affiliate = $attribution['affiliate'];
        
        // Atribuição cumulativa
        if ($attribution['type'] === 'both') {
            if ($attribution['same_affiliate']) {
                return [
                    'type'            => 'both',
                    'affiliate_id'    => $affiliate->get_id(),
                    'affiliate_name'  => $affiliate->get_display_name(),
                    'coupon_code'     => $attribution['coupon_code'],
                    'commission_rate' => $attribution['combined_rate'],
                    'same_affiliate'  => true,
                ];
            } else {
                return [
                    'type'                  => 'both',
                    'affiliate_id'          => $affiliate->get_id(),
                    'affiliate_name'        => $affiliate->get_display_name(),
                    'coupon_code'           => $attribution['coupon_code'],
                    'commission_rate'       => $attribution['affiliate_coupon']->get_commission_rate('coupon'),
                    'same_affiliate'        => false,
                    'link_affiliate_id'     => $attribution['affiliate_link']->get_id(),
                    'link_affiliate_name'   => $attribution['affiliate_link']->get_display_name(),
                    'link_commission_rate'  => $attribution['affiliate_link']->get_commission_rate('link'),
                ];
            }
        }
        
        // Atribuição simples
        return [
            'type'            => $attribution['type'],
            'affiliate_id'    => $affiliate->get_id(),
            'affiliate_name'  => $affiliate->get_display_name(),
            'coupon_code'     => $attribution['coupon_code'],
            'commission_rate' => $affiliate->get_commission_rate($attribution['commission_rate_type']),
        ];
    }
}

