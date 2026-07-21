<?php
/**
 * Relatórios
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) exit;

$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
$end_date_input = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');

// Adiciona horário para incluir todo o último dia na filtragem
$start_date = $start_date . ' 00:00:00';
$end_date = $end_date_input . ' 23:59:59';

$overview = LRP_Admin_Reports::get_overview($start_date, $end_date);
$ranking = LRP_Admin_Reports::get_affiliate_ranking($start_date, $end_date);
$attribution = LRP_Admin_Reports::get_attribution_breakdown($start_date, $end_date);
?>
<div class="wrap lrp-admin-wrap">
    <h1>📊 <?php _e('Relatórios', 'lab-resumos-parceiros'); ?></h1>
    
    <div class="lrp-filters">
        <form method="get" id="lrp-filter-form">
            <input type="hidden" name="page" value="lrp-reports">
            
            <div class="lrp-filter-group">
                <label><?php _e('Data Início', 'lab-resumos-parceiros'); ?></label>
                <input type="date" name="start_date" id="lrp-start-date" value="<?php echo esc_attr(substr($start_date, 0, 10)); ?>">
            </div>
            
            <div class="lrp-filter-group">
                <label><?php _e('Data Fim', 'lab-resumos-parceiros'); ?></label>
                <input type="date" name="end_date" id="lrp-end-date" value="<?php echo esc_attr($end_date_input); ?>">
            </div>
            
            <div class="lrp-filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="button"><?php _e('Filtrar', 'lab-resumos-parceiros'); ?></button>
            </div>
        </form>
    </div>
    
    <div class="lrp-stats-cards">
        <div class="lrp-stat-card">
            <div class="lrp-stat-value"><?php echo esc_html($overview['total_sales']); ?></div>
            <div class="lrp-stat-label"><?php _e('Vendas', 'lab-resumos-parceiros'); ?></div>
        </div>
        <div class="lrp-stat-card">
            <div class="lrp-stat-value">R$ <?php echo esc_html(number_format($overview['total_revenue'], 2, ',', '.')); ?></div>
            <div class="lrp-stat-label"><?php _e('Receita', 'lab-resumos-parceiros'); ?></div>
        </div>
        <div class="lrp-stat-card">
            <div class="lrp-stat-value">R$ <?php echo esc_html(number_format($overview['total_commissions'], 2, ',', '.')); ?></div>
            <div class="lrp-stat-label"><?php _e('Comissões', 'lab-resumos-parceiros'); ?></div>
        </div>
        <div class="lrp-stat-card">
            <div class="lrp-stat-value"><?php echo esc_html($overview['active_affiliates']); ?></div>
            <div class="lrp-stat-label"><?php _e('Parceiros com Vendas', 'lab-resumos-parceiros'); ?></div>
        </div>
    </div>
    
    <div class="lrp-chart-container">
        <h3><?php _e('Vendas no Período', 'lab-resumos-parceiros'); ?></h3>
        <canvas id="lrp-sales-chart" height="100"></canvas>
    </div>
    
    <div class="lrp-two-columns" style="margin-top: 30px;">
        <div>
            <div class="lrp-table-wrap">
                <div class="lrp-table-header">
                    <h2><?php _e('Ranking de Parceiros', 'lab-resumos-parceiros'); ?></h2>
                </div>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php _e('Parceiro', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Vendas', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Receita', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Comissões', 'lab-resumos-parceiros'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ranking)): ?>
                        <tr><td colspan="5"><?php _e('Nenhum dado.', 'lab-resumos-parceiros'); ?></td></tr>
                        <?php else: ?>
                        <?php foreach ($ranking as $i => $r): ?>
                        <tr>
                            <td><strong><?php echo ($i + 1); ?></strong></td>
                            <td><?php echo esc_html($r['display_name']); ?></td>
                            <td><?php echo esc_html($r['sales']); ?></td>
                            <td>R$ <?php echo esc_html(number_format($r['revenue'], 2, ',', '.')); ?></td>
                            <td>R$ <?php echo esc_html(number_format($r['commissions'], 2, ',', '.')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div>
            <div class="lrp-metabox">
                <div class="lrp-metabox-header"><?php _e('Por Tipo de Atribuição', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content">
                    <table class="wp-list-table widefat">
                        <?php foreach ($attribution as $a): ?>
                        <tr>
                            <td>
                                <?php echo $a['attribution_type'] === 'coupon' ? '🎫 Cupom' : '🔗 Link'; ?>
                            </td>
                            <td><?php echo esc_html($a['count']); ?> vendas</td>
                            <td>R$ <?php echo esc_html(number_format($a['revenue'], 2, ',', '.')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

