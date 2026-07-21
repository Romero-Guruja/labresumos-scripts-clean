<?php
/**
 * Dashboard do Afiliado - Tab: Resumo (Visão Geral)
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 * - $stats (array)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obtém valores específicos do afiliado
$coupon_rate = $affiliate->get_commission_rate('coupon');
$link_rate = $affiliate->get_commission_rate('link');
$customer_discount = $affiliate->get_customer_discount();
$cookie_days = $affiliate->get_cookie_days();

// Obtém informações de atividade de rede
$network_activity = $affiliate->get_network_activity_info();
?>

<div class="lrp-dashboard-resumo">
    <!-- Cards de estatísticas -->
    <div class="lrp-stats-grid">
        <div class="lrp-stat-card">
            <div class="lrp-stat-icon">💰</div>
            <div class="lrp-stat-content">
                <span class="lrp-stat-value">R$ <?php echo number_format($affiliate->get_data('current_balance'), 2, ',', '.'); ?></span>
                <span class="lrp-stat-label"><?php _e('Saldo Disponível', 'lab-resumos-parceiros'); ?></span>
            </div>
        </div>
        
        <div class="lrp-stat-card">
            <div class="lrp-stat-icon">📊</div>
            <div class="lrp-stat-content">
                <span class="lrp-stat-value"><?php echo (int) $affiliate->get_data('total_sales'); ?></span>
                <span class="lrp-stat-label"><?php _e('Vendas Totais', 'lab-resumos-parceiros'); ?></span>
            </div>
        </div>
        
        <div class="lrp-stat-card">
            <div class="lrp-stat-icon">💵</div>
            <div class="lrp-stat-content">
                <span class="lrp-stat-value">R$ <?php echo number_format($affiliate->get_data('total_commissions'), 2, ',', '.'); ?></span>
                <span class="lrp-stat-label"><?php _e('Comissões Totais', 'lab-resumos-parceiros'); ?></span>
            </div>
        </div>
        
        <div class="lrp-stat-card">
            <div class="lrp-stat-icon">✅</div>
            <div class="lrp-stat-content">
                <span class="lrp-stat-value">R$ <?php echo number_format($affiliate->get_data('total_paid'), 2, ',', '.'); ?></span>
                <span class="lrp-stat-label"><?php _e('Total Recebido', 'lab-resumos-parceiros'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Status de Atividade de Rede -->
    <div class="lrp-network-activity-card">
        <h3>
            <?php _e('Status para Comissões de Rede', 'lab-resumos-parceiros'); ?>
            <span class="lrp-tooltip" title="<?php esc_attr_e('Para receber comissões de vendas da sua rede (níveis 2 e 3), você precisa estar ativo. Afiliado ativo = 3+ vendas nos últimos 3 meses.', 'lab-resumos-parceiros'); ?>">ℹ️</span>
        </h3>
        
        <div class="lrp-activity-status lrp-activity-<?php echo esc_attr($network_activity['status_class']); ?>">
            <div class="lrp-activity-badge">
                <?php if ($network_activity['is_new_affiliate']): ?>
                    <span class="lrp-badge lrp-badge-info">🌟 <?php _e('Novo Parceiro', 'lab-resumos-parceiros'); ?></span>
                <?php elseif ($network_activity['is_active']): ?>
                    <span class="lrp-badge lrp-badge-success">✅ <?php _e('Ativo', 'lab-resumos-parceiros'); ?></span>
                <?php else: ?>
                    <span class="lrp-badge lrp-badge-warning">⚠️ <?php _e('Inativo', 'lab-resumos-parceiros'); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if (!$network_activity['is_new_affiliate']): ?>
            <div class="lrp-activity-progress">
                <div class="lrp-progress-info">
                    <span><?php printf(__('Vendas nos últimos 3 meses: %d/%d', 'lab-resumos-parceiros'), $network_activity['sales_count'], $network_activity['sales_required']); ?></span>
                    <?php if (!$network_activity['is_active']): ?>
                        <span class="lrp-activity-missing">
                            <?php printf(__('Faltam %d vendas para ativar', 'lab-resumos-parceiros'), $network_activity['sales_missing']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="lrp-progress-bar">
                    <div class="lrp-progress-fill <?php echo $network_activity['is_active'] ? 'lrp-progress-success' : 'lrp-progress-warning'; ?>" 
                         style="width: <?php echo esc_attr($network_activity['progress_percent']); ?>%"></div>
                </div>
                <?php if (!empty($network_activity['period_label'])): ?>
                <small class="lrp-activity-period">
                    <?php printf(__('Período considerado: %s', 'lab-resumos-parceiros'), esc_html($network_activity['period_label'])); ?>
                </small>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="lrp-activity-info">
                <p><?php _e('Você está no período de proteção para novos parceiros (3 primeiros meses). Durante este período, você recebe normalmente as comissões de rede.', 'lab-resumos-parceiros'); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!$network_activity['is_active'] && !$network_activity['is_new_affiliate']): ?>
            <div class="lrp-activity-alert">
                <p>⚠️ <?php _e('Enquanto inativo, suas comissões de rede serão direcionadas para o próximo afiliado ativo acima de você. Sua comissão direta (nível 1) não é afetada.', 'lab-resumos-parceiros'); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Estatísticas do mês -->
    <div class="lrp-month-stats">
        <h3><?php _e('Este Mês', 'lab-resumos-parceiros'); ?></h3>
        <div class="lrp-stats-row">
            <div class="lrp-stat-item">
                <span class="lrp-stat-number"><?php echo $stats['month_sales'] ?? 0; ?></span>
                <span class="lrp-stat-text"><?php _e('Vendas', 'lab-resumos-parceiros'); ?></span>
            </div>
            <div class="lrp-stat-item">
                <span class="lrp-stat-number">R$ <?php echo number_format($stats['month_revenue'] ?? 0, 2, ',', '.'); ?></span>
                <span class="lrp-stat-text"><?php _e('Receita', 'lab-resumos-parceiros'); ?></span>
            </div>
            <div class="lrp-stat-item">
                <span class="lrp-stat-number">R$ <?php echo number_format($stats['month_commissions'] ?? 0, 2, ',', '.'); ?></span>
                <span class="lrp-stat-text"><?php _e('Comissões', 'lab-resumos-parceiros'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Links rápidos -->
    <div class="lrp-quick-links">
        <h3><?php _e('Seus Links de Divulgação', 'lab-resumos-parceiros'); ?></h3>
        
        <div class="lrp-link-box">
            <label><?php _e('Seu Cupom de Desconto:', 'lab-resumos-parceiros'); ?></label>
            <div class="lrp-copy-field">
                <input type="text" readonly value="<?php echo esc_attr($affiliate->get_coupon_code()); ?>" id="lrp-coupon-code">
                <button type="button" class="lrp-btn-copy" data-target="lrp-coupon-code">
                    📋 <?php _e('Copiar', 'lab-resumos-parceiros'); ?>
                </button>
            </div>
            <small><?php printf(__('Comissão: %s%% | Desconto para cliente: %s%%', 'lab-resumos-parceiros'), number_format($coupon_rate, 0), number_format($customer_discount, 0)); ?></small>
        </div>
        
        <div class="lrp-link-box">
            <label><?php _e('Seu Link de Afiliado:', 'lab-resumos-parceiros'); ?></label>
            <div class="lrp-copy-field">
                <input type="text" readonly value="<?php echo esc_url($affiliate->get_referral_url()); ?>" id="lrp-referral-url">
                <button type="button" class="lrp-btn-copy" data-target="lrp-referral-url">
                    📋 <?php _e('Copiar', 'lab-resumos-parceiros'); ?>
                </button>
            </div>
            <small><?php printf(__('Comissão: %s%% | Cookie: %s dias', 'lab-resumos-parceiros'), number_format($link_rate, 0), $cookie_days); ?></small>
        </div>
    </div>
    
    <!-- Últimas vendas -->
    <?php if (!empty($stats['recent_sales'])): ?>
    <div class="lrp-recent-sales">
        <h3><?php _e('Últimas Vendas', 'lab-resumos-parceiros'); ?></h3>
        <table class="lrp-table">
            <thead>
                <tr>
                    <th><?php _e('Data', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Pedido', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Tipo', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Valor', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Comissão', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['recent_sales'] as $sale): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($sale->created_at)); ?></td>
                    <td>#<?php echo esc_html($sale->order_id); ?></td>
                    <td>
                        <?php echo LRP_Dashboard::get_attribution_type_label_static($sale->attribution_type); ?>
                    </td>
                    <td>R$ <?php echo number_format($sale->commission_base, 2, ',', '.'); ?></td>
                    <td>R$ <?php echo number_format($sale->commission_amount ?? 0, 2, ',', '.'); ?></td>
                    <td>
                        <span class="lrp-status lrp-status-<?php echo esc_attr($sale->status); ?>">
                            <?php echo LRP_Dashboard::get_status_label($sale->status); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

