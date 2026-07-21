<?php
/**
 * Dashboard do Afiliado - Tab: Vendas
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 * - $sales (array)
 * - $pagination (array)
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$period = isset($_GET['period']) ? sanitize_key($_GET['period']) : 'all';
?>

<div class="lrp-dashboard-vendas">
    <h2><?php _e('Minhas Vendas', 'lab-resumos-parceiros'); ?></h2>
    
    <!-- Filtros -->
    <div class="lrp-filters">
        <form method="get" class="lrp-filter-form">
            <input type="hidden" name="tab" value="vendas">
            
            <div class="lrp-filter-group">
                <label for="lrp-period"><?php _e('Período:', 'lab-resumos-parceiros'); ?></label>
                <select name="period" id="lrp-period">
                    <option value="all" <?php selected($period, 'all'); ?>><?php _e('Todos', 'lab-resumos-parceiros'); ?></option>
                    <option value="this_month" <?php selected($period, 'this_month'); ?>><?php _e('Este mês', 'lab-resumos-parceiros'); ?></option>
                    <option value="last_month" <?php selected($period, 'last_month'); ?>><?php _e('Mês passado', 'lab-resumos-parceiros'); ?></option>
                    <option value="last_3_months" <?php selected($period, 'last_3_months'); ?>><?php _e('Últimos 3 meses', 'lab-resumos-parceiros'); ?></option>
                    <option value="this_year" <?php selected($period, 'this_year'); ?>><?php _e('Este ano', 'lab-resumos-parceiros'); ?></option>
                </select>
            </div>
            
            <button type="submit" class="lrp-btn lrp-btn-secondary"><?php _e('Filtrar', 'lab-resumos-parceiros'); ?></button>
        </form>
    </div>
    
    <?php if (!empty($sales)): ?>
    
    <!-- Resumo do período -->
    <div class="lrp-period-summary">
        <div class="lrp-summary-item">
            <span class="lrp-summary-value"><?php echo count($sales); ?></span>
            <span class="lrp-summary-label"><?php _e('Vendas', 'lab-resumos-parceiros'); ?></span>
        </div>
        <div class="lrp-summary-item">
            <span class="lrp-summary-value">R$ <?php echo number_format(array_sum(array_column($sales, 'commission_base')), 2, ',', '.'); ?></span>
            <span class="lrp-summary-label"><?php _e('Total em vendas', 'lab-resumos-parceiros'); ?></span>
        </div>
        <div class="lrp-summary-item">
            <span class="lrp-summary-value">R$ <?php echo number_format(array_sum(array_column($sales, 'commission_amount')), 2, ',', '.'); ?></span>
            <span class="lrp-summary-label"><?php _e('Total em comissões', 'lab-resumos-parceiros'); ?></span>
        </div>
    </div>
    
    <!-- Tabela de vendas -->
    <div class="lrp-table-responsive">
        <table class="lrp-table lrp-table-striped">
            <thead>
                <tr>
                    <th><?php _e('Data', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Pedido', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Tipo', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Valor da Venda', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Desconto', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Base Comissão', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Comissão', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                <tr>
                    <td data-label="<?php _e('Data', 'lab-resumos-parceiros'); ?>">
                        <?php echo date('d/m/Y', strtotime($sale->created_at)); ?>
                        <small><?php echo date('H:i', strtotime($sale->created_at)); ?></small>
                    </td>
                    <td data-label="<?php _e('Pedido', 'lab-resumos-parceiros'); ?>">
                        #<?php echo esc_html($sale->order_id); ?>
                    </td>
                    <td data-label="<?php _e('Tipo', 'lab-resumos-parceiros'); ?>">
                        <?php if ($sale->attribution_type === 'both'): ?>
                            <span class="lrp-badge lrp-badge-both">🔗🎫 <?php _e('Link + Cupom', 'lab-resumos-parceiros'); ?></span>
                        <?php elseif ($sale->attribution_type === 'coupon'): ?>
                            <span class="lrp-badge lrp-badge-coupon">🎫 <?php _e('Cupom', 'lab-resumos-parceiros'); ?></span>
                        <?php else: ?>
                            <span class="lrp-badge lrp-badge-link">🔗 <?php _e('Link', 'lab-resumos-parceiros'); ?></span>
                        <?php endif; ?>
                        <?php if ($sale->is_guruja_student): ?>
                            <span class="lrp-badge lrp-badge-guruja" title="<?php _e('Cliente é aluno Guruja', 'lab-resumos-parceiros'); ?>">🎓</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php _e('Valor', 'lab-resumos-parceiros'); ?>">
                        R$ <?php echo number_format($sale->order_total, 2, ',', '.'); ?>
                    </td>
                    <td data-label="<?php _e('Desconto', 'lab-resumos-parceiros'); ?>">
                        <?php if ($sale->discount_amount > 0): ?>
                            -R$ <?php echo number_format($sale->discount_amount, 2, ',', '.'); ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php _e('Base', 'lab-resumos-parceiros'); ?>">
                        R$ <?php echo number_format($sale->commission_base, 2, ',', '.'); ?>
                    </td>
                    <td data-label="<?php _e('Comissão', 'lab-resumos-parceiros'); ?>">
                        <strong>R$ <?php echo number_format($sale->commission_amount ?? 0, 2, ',', '.'); ?></strong>
                    </td>
                    <td data-label="<?php _e('Status', 'lab-resumos-parceiros'); ?>">
                        <span class="lrp-status lrp-status-<?php echo esc_attr($sale->status); ?>">
                            <?php echo LRP_Dashboard::get_status_label($sale->status); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Paginação -->
    <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
    <div class="lrp-pagination">
        <?php
        $base_url = add_query_arg(['tab' => 'vendas', 'period' => $period], get_permalink());
        
        if ($current_page > 1): ?>
            <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1, $base_url)); ?>" class="lrp-page-link">
                &laquo; <?php _e('Anterior', 'lab-resumos-parceiros'); ?>
            </a>
        <?php endif; ?>
        
        <span class="lrp-page-info">
            <?php printf(__('Página %d de %d', 'lab-resumos-parceiros'), $current_page, $pagination['total_pages']); ?>
        </span>
        
        <?php if ($current_page < $pagination['total_pages']): ?>
            <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1, $base_url)); ?>" class="lrp-page-link">
                <?php _e('Próxima', 'lab-resumos-parceiros'); ?> &raquo;
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    
    <div class="lrp-empty-state">
        <div class="lrp-empty-icon">📊</div>
        <h3><?php _e('Nenhuma venda encontrada', 'lab-resumos-parceiros'); ?></h3>
        <p><?php _e('Comece a divulgar seu cupom e link para ver suas vendas aqui!', 'lab-resumos-parceiros'); ?></p>
        <a href="?tab=links" class="lrp-btn lrp-btn-primary"><?php _e('Ver Links e Cupons', 'lab-resumos-parceiros'); ?></a>
    </div>
    
    <?php endif; ?>
</div>

