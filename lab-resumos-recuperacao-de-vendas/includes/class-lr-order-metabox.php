<?php
/**
 * Classe Metabox do Pedido
 *
 * @package LR_Recuperacao_Vendas
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LR_Order_Metabox
 * Adiciona metabox na tela de edição do pedido WooCommerce
 */
class LR_Order_Metabox {

    /**
     * Construtor
     */
    public function __construct() {
        // Metabox para pedidos (HPOS)
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        
        // Para WooCommerce com HPOS
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'render_hpos_panel']);
    }

    /**
     * Adiciona metabox
     */
    public function add_metabox() {
        // Screen pode ser 'shop_order' (legacy) ou 'woocommerce_page_wc-orders' (HPOS)
        $screen = wc_get_page_screen_id('shop-order');
        
        add_meta_box(
            'lr_recovery_metabox',
            __('🔄 Recuperação de Venda', 'lr-recuperacao-vendas'),
            [$this, 'render_metabox'],
            $screen,
            'side',
            'high'
        );

        // Legacy
        add_meta_box(
            'lr_recovery_metabox',
            __('🔄 Recuperação de Venda', 'lr-recuperacao-vendas'),
            [$this, 'render_metabox'],
            'shop_order',
            'side',
            'high'
        );
    }

    /**
     * Renderiza conteúdo do metabox
     * @param WP_Post|WC_Order $post_or_order
     */
    public function render_metabox($post_or_order) {
        // Compatibilidade HPOS
        if ($post_or_order instanceof WC_Order) {
            $order = $post_or_order;
            $order_id = $order->get_id();
        } else {
            $order_id = $post_or_order->ID;
            $order = wc_get_order($order_id);
        }

        if (!$order) {
            echo '<p>' . esc_html__('Pedido não encontrado.', 'lr-recuperacao-vendas') . '</p>';
            return;
        }

        $this->render_metabox_content($order);
    }

    /**
     * Renderiza painel para HPOS
     * @param WC_Order $order
     */
    public function render_hpos_panel($order) {
        if ($order->get_status() !== 'failed') {
            return;
        }

        echo '<div class="lr-recovery-hpos-panel" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
        $this->render_metabox_content($order);
        echo '</div>';
    }

    /**
     * Renderiza conteúdo interno do metabox
     * @param WC_Order $order
     */
    private function render_metabox_content($order) {
        $order_id = $order->get_id();
        $status = $order->get_status();

        // Se não está com status failed, mostrar mensagem
        if ($status !== 'failed') {
            echo '<p class="description">' . esc_html__('Este pedido não está com status "Malsucedido".', 'lr-recuperacao-vendas') . '</p>';
            
            // Se já foi resolvido, mostrar link para histórico
            $case = lr_recovery()->manager->get_case_by_order($order_id);
            if ($case) {
                echo '<p><a href="' . esc_url(admin_url('admin.php?page=lr-recuperacao-vendas&case=' . $order_id)) . '" class="button">';
                echo esc_html__('Ver Histórico de Recuperação', 'lr-recuperacao-vendas');
                echo '</a></p>';
            }
            return;
        }

        $manager = lr_recovery()->manager;
        $autologin = lr_recovery()->autologin;
        
        $case = $manager->get_case_by_order($order_id);
        $failure_info = $manager->extract_failure_reason($order_id);
        $external_urls = LR_Recuperacao_Vendas::get_external_urls();

        // Dados de contato
        $cellphone = $order->get_meta('billing_cellphone') ?: $order->get_billing_phone();
        $whatsapp_url = $autologin->generate_whatsapp_url($order, '');
        ?>
        
        <style>
            .lr-metabox-section { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
            .lr-metabox-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
            .lr-metabox-label { font-weight: 600; color: #333; display: block; margin-bottom: 5px; }
            .lr-metabox-value { color: #666; }
            .lr-metabox-buttons { display: flex; flex-direction: column; gap: 8px; }
            .lr-metabox-buttons .button { text-align: center; }
            .lr-status-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
            .lr-status-novo { background: #f8d7da; color: #721c24; }
            .lr-status-em_atendimento { background: #fff3cd; color: #856404; }
            .lr-status-aguardando_cliente { background: #d1ecf1; color: #0c5460; }
            .lr-status-resolvido { background: #d4edda; color: #155724; }
            .lr-status-abandonado { background: #e2e3e5; color: #383d41; }
        </style>

        <?php if ($case): ?>
            <div class="lr-metabox-section">
                <span class="lr-metabox-label"><?php esc_html_e('Status do Caso:', 'lr-recuperacao-vendas'); ?></span>
                <span class="lr-status-badge lr-status-<?php echo esc_attr($case->status); ?>">
                    <?php echo esc_html(LR_Admin_Dashboard::get_status_icon($case->status) . ' ' . LR_Admin_Dashboard::get_status_label($case->status)); ?>
                </span>
            </div>
        <?php else: ?>
            <div class="lr-metabox-section">
                <p class="description" style="color: #dc3545;">
                    <?php esc_html_e('⚠️ Caso de recuperação não encontrado.', 'lr-recuperacao-vendas'); ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!empty($failure_info['type']) && $failure_info['type'] !== 'outro'): ?>
            <div class="lr-metabox-section">
                <span class="lr-metabox-label"><?php esc_html_e('Tipo de Falha:', 'lr-recuperacao-vendas'); ?></span>
                <span class="lr-metabox-value">
                    <?php echo esc_html(LR_Admin_Dashboard::get_failure_type_icon($failure_info['type']) . ' ' . LR_Admin_Dashboard::get_failure_type_label($failure_info['type'])); ?>
                </span>
                <?php if ($failure_info['is_antifraud']): ?>
                    <p class="description" style="margin-top: 5px; color: #856404;">
                        <?php esc_html_e('💡 Provável bloqueio por antifraude. Reprocessar sem antifraude na Pagar.me.', 'lr-recuperacao-vendas'); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="lr-metabox-section">
            <span class="lr-metabox-label"><?php esc_html_e('Contato Rápido:', 'lr-recuperacao-vendas'); ?></span>
            <p class="lr-metabox-value">
                📱 <?php echo esc_html($cellphone); ?>
            </p>
        </div>

        <div class="lr-metabox-section lr-metabox-buttons">
            <a href="<?php echo esc_attr($whatsapp_url); ?>" class="button" target="_blank">
                📱 <?php esc_html_e('Abrir WhatsApp', 'lr-recuperacao-vendas'); ?>
            </a>

            <?php if (!empty($failure_info['charge_id'])): ?>
                <a href="<?php echo esc_url($external_urls['pagarme_charge'] . $failure_info['charge_id']); ?>" class="button" target="_blank">
                    🔗 <?php esc_html_e('Ver na Pagar.me', 'lr-recuperacao-vendas'); ?>
                </a>
            <?php endif; ?>

            <a href="<?php echo esc_url(admin_url('admin.php?page=lr-recuperacao-vendas&case=' . $order_id)); ?>" class="button button-primary">
                📋 <?php esc_html_e('Painel Completo', 'lr-recuperacao-vendas'); ?>
            </a>
        </div>
        <?php
    }
}
