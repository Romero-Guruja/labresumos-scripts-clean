<?php
/**
 * Integração com WooCommerce
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_WooCommerce
 * 
 * Integração completa com ciclo de vida do pedido.
 */
class LRP_WooCommerce {

    /**
     * Instância única
     *
     * @var LRP_WooCommerce|null
     */
    private static $instance = null;

    /**
     * Cache do status HPOS
     *
     * @var bool|null
     */
    private static $hpos_enabled_cache = null;

    /**
     * Retorna instância única
     *
     * @return LRP_WooCommerce
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
        // Quando pedido é criado
        add_action('woocommerce_checkout_order_created', [$this, 'on_order_created'], 10, 1);
        
        // Quando pedido muda de status
        add_action('woocommerce_order_status_completed', [$this, 'on_order_completed']);
        add_action('woocommerce_order_status_processing', [$this, 'on_order_processing']);
        add_action('woocommerce_order_status_refunded', [$this, 'on_order_refunded']);
        add_action('woocommerce_order_status_cancelled', [$this, 'on_order_cancelled']);
        
        // Exibe info de afiliado no admin do pedido
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_order_affiliate_info']);
        
        // Coluna customizada na lista de pedidos
        // Verifica se HPOS está ativo
        if ($this->is_hpos_enabled()) {
            add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_affiliate_column']);
            add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'render_affiliate_column_hpos'], 10, 2);
        } else {
            add_filter('manage_edit-shop_order_columns', [$this, 'add_affiliate_column']);
            add_action('manage_shop_order_posts_custom_column', [$this, 'render_affiliate_column'], 10, 2);
        }
    }

    /**
     * Verifica se HPOS está ativo de forma segura
     * 
     * Implementa verificação robusta com cache e tratamento de exceções
     * para garantir compatibilidade com todas as versões do WooCommerce.
     *
     * @return bool
     */
    private function is_hpos_enabled() {
        // Retorna cache se já calculado
        if (self::$hpos_enabled_cache !== null) {
            return self::$hpos_enabled_cache;
        }
        
        // Verifica se a classe existe
        if (!class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            return self::$hpos_enabled_cache = false;
        }
        
        // Verifica se o método existe (compatibilidade com versões antigas)
        if (!method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
            return self::$hpos_enabled_cache = false;
        }
        
        try {
            return self::$hpos_enabled_cache = 
                \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        } catch (\Exception $e) {
            // Em caso de qualquer erro, assume modelo legado
            lrp_log('Erro ao verificar HPOS', [
                'error' => $e->getMessage(),
            ], 'warning');
            return self::$hpos_enabled_cache = false;
        } catch (\Error $e) {
            // Captura erros fatais também (PHP 7+)
            lrp_log('Erro fatal ao verificar HPOS', [
                'error' => $e->getMessage(),
            ], 'error');
            return self::$hpos_enabled_cache = false;
        }
    }

    /**
     * Quando pedido é criado (checkout finalizado)
     *
     * @param WC_Order $order
     */
    public function on_order_created($order) {
        try {
            LRP_Attribution::instance()->process_order_attribution($order->get_id());
        } catch (\Throwable $e) {
            lrp_log('Erro ao processar atribuição', [
                'order_id' => $order->get_id(),
                'error'    => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
            ], 'error');

            lrp_send_telegram_alert(
                'Erro crítico no checkout (pedido #' . $order->get_id() . ')',
                $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine()
            );
        }
    }

    /**
     * Quando pedido é processando
     *
     * @param int $order_id
     */
    public function on_order_processing($order_id) {
        $this->approve_commission($order_id);
    }

    /**
     * Quando pedido é completado
     *
     * @param int $order_id
     */
    public function on_order_completed($order_id) {
        $this->approve_commission($order_id);
    }

    /**
     * Aprova comissão
     *
     * @param int $order_id
     */
    private function approve_commission($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Suporta múltiplos referrals (atribuição cumulativa com afiliados diferentes)
        $referral_ids = $order->get_meta('_lrp_referral_ids', true);
        if (empty($referral_ids)) {
            $referral_id = $order->get_meta('_lrp_referral_id', true);
            $referral_ids = $referral_id ? [$referral_id] : [];
        }
        
        if (empty($referral_ids)) {
            return;
        }
        
        global $wpdb;
        
        $affiliate_ids_updated = [];
        
        foreach ($referral_ids as $referral_id) {
            // Atualiza referral apenas se ainda pendente (evita duplicidade)
            $updated = $wpdb->update(
                $wpdb->prefix . 'lrp_referrals',
                ['status' => 'approved'],
                ['id' => $referral_id, 'status' => 'pending']
            );
            
            // Atualiza comissões
            $wpdb->update(
                $wpdb->prefix . 'lrp_commissions',
                ['status' => 'approved'],
                ['referral_id' => $referral_id, 'status' => 'pending']
            );
            
            // Dispara action apenas quando houve transição real pending → approved
            $referral = LRP_Referral::get($referral_id);
            if ($referral) {
                if ($updated) {
                    do_action('lrp_referral_approved', $referral);
                }
                $affiliate_ids_updated[] = $referral->get_affiliate_id();
            }
        }
        
        // Atualiza stats dos afiliados
        $affiliate_ids_updated = array_unique($affiliate_ids_updated);
        foreach ($affiliate_ids_updated as $affiliate_id) {
            $affiliate = new LRP_Affiliate($affiliate_id);
            $affiliate->refresh_stats();
        }
        
        lrp_log('Comissão(ões) aprovada(s)', [
            'order_id'     => $order_id,
            'referral_ids' => $referral_ids,
        ]);
    }

    /**
     * Quando pedido é reembolsado
     *
     * @param int $order_id
     */
    public function on_order_refunded($order_id) {
        $this->cancel_commission($order_id, 'refunded');
    }

    /**
     * Quando pedido é cancelado
     *
     * @param int $order_id
     */
    public function on_order_cancelled($order_id) {
        $this->cancel_commission($order_id, 'cancelled');
    }

    /**
     * Cancela comissão
     *
     * @param int $order_id
     * @param string $reason
     */
    private function cancel_commission($order_id, $reason) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Suporta múltiplos referrals (atribuição cumulativa com afiliados diferentes)
        $referral_ids = $order->get_meta('_lrp_referral_ids', true);
        if (empty($referral_ids)) {
            $referral_id = $order->get_meta('_lrp_referral_id', true);
            $referral_ids = $referral_id ? [$referral_id] : [];
        }
        
        if (empty($referral_ids)) {
            return;
        }
        
        global $wpdb;
        
        $affiliate_ids_updated = [];
        
        foreach ($referral_ids as $referral_id) {
            // Obtém affiliate_id antes de atualizar
            $referral = LRP_Referral::get($referral_id);
            if ($referral) {
                $affiliate_ids_updated[] = $referral->get_affiliate_id();
            }
            
            // Atualiza referral
            $wpdb->update(
                $wpdb->prefix . 'lrp_referrals',
                ['status' => $reason],
                ['id' => $referral_id]
            );
            
            // Cancela comissões que ainda não foram pagas
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}lrp_commissions 
                 SET status = 'cancelled' 
                 WHERE referral_id = %d AND status IN ('pending', 'approved')",
                $referral_id
            ));
        }
        
        // Atualiza stats dos afiliados
        $affiliate_ids_updated = array_unique($affiliate_ids_updated);
        foreach ($affiliate_ids_updated as $affiliate_id) {
            $affiliate = new LRP_Affiliate($affiliate_id);
            $affiliate->refresh_stats();
        }
        
        lrp_log('Comissão(ões) cancelada(s)', [
            'order_id'     => $order_id,
            'referral_ids' => $referral_ids,
            'reason'       => $reason,
        ]);
    }

    /**
     * Exibe info do afiliado no admin do pedido
     *
     * @param WC_Order $order
     */
    public function display_order_affiliate_info($order) {
        $affiliate_id = $order->get_meta('_lrp_affiliate_id');
        
        if (!$affiliate_id) {
            return;
        }
        
        $affiliate = new LRP_Affiliate($affiliate_id);
        $attribution_type = $order->get_meta('_lrp_attribution_type');
        $coupon_used = $order->get_meta('_lrp_coupon_used');
        $commission = $order->get_meta('_lrp_commission_amount');
        $is_guruja = $order->get_meta('_lrp_is_guruja_discount');
        $no_commission_reason = $order->get_meta('_lrp_no_commission_reason');
        
        // Metas de atribuição cumulativa
        $is_cumulative_same = $order->get_meta('_lrp_cumulative_same_affiliate');
        $is_cumulative_different = $order->get_meta('_lrp_cumulative_different_affiliates');
        $link_affiliate_id = $order->get_meta('_lrp_affiliate_link_id');
        
        ?>
        <div class="lrp-order-affiliate-info" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #2A6B9F; border-radius: 0 4px 4px 0;">
            <h4 style="margin-top: 0; color: #2A6B9F;">🤝 <?php esc_html_e('Venda de Parceiro', 'lab-resumos-parceiros'); ?></h4>
            
            <?php if ($attribution_type === 'both' && $is_cumulative_different && $link_affiliate_id): ?>
                <?php // Atribuição cumulativa com afiliados diferentes ?>
                <?php $link_affiliate = new LRP_Affiliate($link_affiliate_id); ?>
                <p><strong><?php esc_html_e('Parceiro (Cupom):', 'lab-resumos-parceiros'); ?></strong> 
                    <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&action=edit&id=' . $affiliate_id)); ?>">
                        <?php echo esc_html($affiliate->get_display_name()); ?>
                    </a>
                    (#<?php echo esc_html($affiliate_id); ?>)
                </p>
                <p><strong><?php esc_html_e('Parceiro (Link):', 'lab-resumos-parceiros'); ?></strong> 
                    <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&action=edit&id=' . $link_affiliate_id)); ?>">
                        <?php echo esc_html($link_affiliate->get_display_name()); ?>
                    </a>
                    (#<?php echo esc_html($link_affiliate_id); ?>)
                </p>
            <?php else: ?>
                <p><strong><?php esc_html_e('Parceiro:', 'lab-resumos-parceiros'); ?></strong> 
                    <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&action=edit&id=' . $affiliate_id)); ?>">
                        <?php echo esc_html($affiliate->get_display_name()); ?>
                    </a>
                    (#<?php echo esc_html($affiliate_id); ?>)
                </p>
            <?php endif; ?>
            
            <p><strong><?php esc_html_e('Atribuição:', 'lab-resumos-parceiros'); ?></strong> 
                <?php if ($attribution_type === 'both'): ?>
                    🔗🎫 <?php esc_html_e('Link + Cupom', 'lab-resumos-parceiros'); ?>
                    <?php if ($coupon_used): ?>
                        (<?php echo esc_html($coupon_used); ?>)
                    <?php endif; ?>
                    <?php if ($is_cumulative_same): ?>
                        <span style="color: #28a745; font-size: 11px; margin-left: 5px;">
                            <?php esc_html_e('(taxa combinada)', 'lab-resumos-parceiros'); ?>
                        </span>
                    <?php elseif ($is_cumulative_different): ?>
                        <span style="color: #17a2b8; font-size: 11px; margin-left: 5px;">
                            <?php esc_html_e('(comissões separadas)', 'lab-resumos-parceiros'); ?>
                        </span>
                    <?php endif; ?>
                <?php elseif ($attribution_type === 'coupon'): ?>
                    🎫 <?php esc_html_e('Cupom', 'lab-resumos-parceiros'); ?> (<?php echo esc_html($coupon_used); ?>)
                <?php else: ?>
                    🔗 <?php esc_html_e('Link', 'lab-resumos-parceiros'); ?>
                <?php endif; ?>
            </p>
            
            <?php if ($no_commission_reason): ?>
                <p style="color: #856404;">
                    <strong><?php esc_html_e('Comissão:', 'lab-resumos-parceiros'); ?></strong> 
                    ⚠️ <?php esc_html_e('Sem comissão (regra Guruja)', 'lab-resumos-parceiros'); ?>
                </p>
            <?php else: ?>
                <p><strong><?php esc_html_e('Comissão:', 'lab-resumos-parceiros'); ?></strong> 
                    R$ <?php echo esc_html(number_format((float) $commission, 2, ',', '.')); ?>
                    <?php if ($attribution_type === 'both' && $is_cumulative_different): ?>
                        <span style="font-size: 11px; color: #666;">
                            <?php esc_html_e('(total para ambos parceiros)', 'lab-resumos-parceiros'); ?>
                        </span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            
            <?php if ($is_guruja): ?>
                <p><strong><?php esc_html_e('Desconto:', 'lab-resumos-parceiros'); ?></strong> 
                    🎓 <?php esc_html_e('Aluno Guruja', 'lab-resumos-parceiros'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Adiciona coluna de afiliado na lista de pedidos
     *
     * @param array $columns
     * @return array
     */
    public function add_affiliate_column($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'order_status') {
                $new_columns['lrp_affiliate'] = __('Parceiro', 'lab-resumos-parceiros');
            }
        }
        
        return $new_columns;
    }

    /**
     * Renderiza coluna de afiliado (modelo legado)
     *
     * @param string $column
     * @param int $post_id
     */
    public function render_affiliate_column($column, $post_id) {
        if ($column !== 'lrp_affiliate') {
            return;
        }
        
        $order = wc_get_order($post_id);
        $this->render_affiliate_column_content($order);
    }

    /**
     * Renderiza coluna de afiliado (HPOS)
     *
     * @param string $column
     * @param WC_Order $order
     */
    public function render_affiliate_column_hpos($column, $order) {
        if ($column !== 'lrp_affiliate') {
            return;
        }
        
        $this->render_affiliate_column_content($order);
    }

    /**
     * Conteúdo da coluna de afiliado
     *
     * @param WC_Order|null $order
     */
    private function render_affiliate_column_content($order) {
        if (!$order || !is_a($order, 'WC_Order')) {
            echo '—';
            return;
        }
        
        $affiliate_id = $order->get_meta('_lrp_affiliate_id');
        
        if (!$affiliate_id) {
            echo '—';
            return;
        }
        
        $affiliate = new LRP_Affiliate($affiliate_id);
        $type = $order->get_meta('_lrp_attribution_type');
        $is_cumulative_different = $order->get_meta('_lrp_cumulative_different_affiliates');
        
        // Define ícone baseado no tipo de atribuição
        if ($type === 'both') {
            $icon = '🔗🎫';
        } elseif ($type === 'coupon') {
            $icon = '🎫';
        } else {
            $icon = '🔗';
        }
        
        printf(
            '%s <a href="%s">%s</a>',
            esc_html($icon),
            esc_url(admin_url('admin.php?page=lrp-affiliates&action=edit&id=' . $affiliate_id)),
            esc_html($affiliate->get_display_name())
        );
        
        // Se são afiliados diferentes, mostra o segundo
        if ($type === 'both' && $is_cumulative_different) {
            $link_affiliate_id = $order->get_meta('_lrp_affiliate_link_id');
            if ($link_affiliate_id && $link_affiliate_id != $affiliate_id) {
                $link_affiliate = new LRP_Affiliate($link_affiliate_id);
                printf(
                    '<br><small>+ <a href="%s">%s</a></small>',
                    esc_url(admin_url('admin.php?page=lrp-affiliates&action=edit&id=' . $link_affiliate_id)),
                    esc_html($link_affiliate->get_display_name())
                );
            }
        }
    }

    /**
     * Retorna estatísticas do mês para o programa
     *
     * @return array
     */
    public function get_monthly_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(DISTINCT r.id) as total_sales,
                COALESCE(SUM(r.commission_base), 0) as total_revenue,
                COALESCE(SUM(c.commission_amount), 0) as total_commissions
             FROM {$wpdb->prefix}lrp_referrals r
             LEFT JOIN {$wpdb->prefix}lrp_commissions c ON r.id = c.referral_id
             WHERE MONTH(r.created_at) = MONTH(CURRENT_DATE())
             AND YEAR(r.created_at) = YEAR(CURRENT_DATE())"
        );
        
        return [
            'total_sales'       => (int) $stats->total_sales,
            'total_revenue'     => (float) $stats->total_revenue,
            'total_commissions' => (float) $stats->total_commissions,
        ];
    }
}

