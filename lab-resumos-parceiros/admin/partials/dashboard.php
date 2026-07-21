<?php
/**
 * Admin Dashboard
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) exit;
?>
<div class="wrap lrp-admin-wrap">
    <h1>🤝 <?php _e('Programa de Parceiros', 'lab-resumos-parceiros'); ?></h1>
    
    <div class="lrp-stats-cards">
        <div class="lrp-stat-card highlight">
            <div class="lrp-stat-value"><?php echo esc_html($stats['affiliates_total']); ?></div>
            <div class="lrp-stat-label"><?php _e('Parceiros Ativos', 'lab-resumos-parceiros'); ?></div>
        </div>
        
        <?php if ($stats['affiliates_pending'] > 0): ?>
        <div class="lrp-stat-card warning">
            <div class="lrp-stat-value"><?php echo esc_html($stats['affiliates_pending']); ?></div>
            <div class="lrp-stat-label"><?php _e('Aguardando Aprovação', 'lab-resumos-parceiros'); ?></div>
        </div>
        <?php endif; ?>
        
        <div class="lrp-stat-card">
            <div class="lrp-stat-value"><?php echo esc_html($stats['monthly_sales']); ?></div>
            <div class="lrp-stat-label"><?php _e('Vendas Este Mês', 'lab-resumos-parceiros'); ?></div>
        </div>
        
        <div class="lrp-stat-card">
            <div class="lrp-stat-value">R$ <?php echo esc_html(number_format($stats['monthly_revenue'], 2, ',', '.')); ?></div>
            <div class="lrp-stat-label"><?php _e('Receita Este Mês', 'lab-resumos-parceiros'); ?></div>
        </div>
        
        <div class="lrp-stat-card success">
            <div class="lrp-stat-value">R$ <?php echo esc_html(number_format($stats['pending_commissions'], 2, ',', '.')); ?></div>
            <div class="lrp-stat-label"><?php _e('Comissões Pendentes', 'lab-resumos-parceiros'); ?></div>
        </div>
    </div>
    
    <div class="lrp-two-columns">
        <div>
            <div class="lrp-table-wrap">
                <div class="lrp-table-header">
                    <h2><?php _e('Vendas Recentes', 'lab-resumos-parceiros'); ?></h2>
                </div>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Data', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Pedido', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Parceiro', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Valor', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stats['recent_sales'])): ?>
                        <tr>
                            <td colspan="5"><?php _e('Nenhuma venda registrada.', 'lab-resumos-parceiros'); ?></td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($stats['recent_sales'] as $sale): ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($sale->created_at))); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $sale->order_id . '&action=edit')); ?>">
                                    #<?php echo esc_html($sale->order_id); ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&action=view&id=' . $sale->affiliate_id)); ?>">
                                    <?php echo esc_html($sale->affiliate_name); ?>
                                </a>
                            </td>
                            <td>R$ <?php echo esc_html(number_format($sale->commission_base, 2, ',', '.')); ?></td>
                            <td><span class="lrp-badge lrp-badge-<?php echo esc_attr($sale->status); ?>"><?php echo esc_html($sale->status); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div>
            <div class="lrp-metabox">
                <div class="lrp-metabox-header"><?php _e('Top Parceiros', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content">
                    <?php if (empty($stats['top_affiliates'])): ?>
                        <p><?php _e('Nenhum parceiro com vendas ainda.', 'lab-resumos-parceiros'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat">
                            <?php foreach ($stats['top_affiliates'] as $i => $top): ?>
                            <tr>
                                <td><strong><?php echo ($i + 1); ?>.</strong></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&action=view&id=' . $top->id)); ?>">
                                        <?php echo esc_html($top->display_name); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($top->total_sales); ?> vendas</td>
                                <td>R$ <?php echo esc_html(number_format($top->total_revenue, 0, ',', '.')); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="lrp-metabox" style="margin-top: 20px;">
                <div class="lrp-metabox-header"><?php _e('Ações Rápidas', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content">
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&status=pending')); ?>" class="button">
                            👤 <?php _e('Ver Pendentes', 'lab-resumos-parceiros'); ?>
                            <?php if ($stats['affiliates_pending'] > 0): ?>
                                (<?php echo esc_html($stats['affiliates_pending']); ?>)
                            <?php endif; ?>
                        </a>
                    </p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-reports')); ?>" class="button">
                            📊 <?php _e('Ver Relatórios', 'lab-resumos-parceiros'); ?>
                        </a>
                    </p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-settings')); ?>" class="button">
                            ⚙️ <?php _e('Configurações', 'lab-resumos-parceiros'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

