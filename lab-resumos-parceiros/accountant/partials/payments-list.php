<?php
/**
 * Lista de Pagamentos - Área do Contador
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $pending (array) Fechamentos com status 'approved' (prontos para pagamento)
 * - $history (array) Últimos 50 pagamentos realizados
 */

if (!defined('ABSPATH')) exit;
?>
<div class="wrap lrp-admin-wrap">
    <h1><?php _e('Pagamentos', 'lab-resumos-parceiros'); ?></h1>
    
    <?php if (!empty($pending)): ?>
    <div class="lrp-table-wrap">
        <div class="lrp-table-header">
            <h2><?php _e('Pagamentos Pendentes', 'lab-resumos-parceiros'); ?> <span class="count">(<?php echo count($pending); ?>)</span></h2>
        </div>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Parceiro', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Período', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Vendas', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Comissões', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Ajustes', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Total a Pagar', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('NF', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Ações', 'lab-resumos-parceiros'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $p): 
                    // Calcula ajustes do novo sistema
                    $adjustments_sum = 0.0;
                    if (class_exists('LRP_Adjustment') && !empty($p->id)) {
                        $adjustments_sum = LRP_Adjustment::get_closing_sum($p->id);
                    }
                    // Mantém compatibilidade com ajuste antigo
                    $old_adjustment = (float) ($p->adjustment_amount ?? 0);
                    $total_adjustments = $adjustments_sum + $old_adjustment;
                    
                    $final_amount = LRP_Closing::get_final_amount($p);
                ?>
                <tr>
                    <td><?php echo esc_html($p->affiliate_name); ?></td>
                    <td><?php printf('%02d/%d', $p->period_month, $p->period_year); ?></td>
                    <td><?php echo esc_html($p->total_sales); ?></td>
                    <td>R$ <?php echo esc_html(number_format($p->total_commissions, 2, ',', '.')); ?></td>
                    <td>
                        <?php if ($total_adjustments != 0): ?>
                            <span class="<?php echo $total_adjustments > 0 ? 'lrp-text-success' : 'lrp-text-danger'; ?>">
                                <?php echo $total_adjustments > 0 ? '+' : ''; ?>R$ <?php echo esc_html(number_format($total_adjustments, 2, ',', '.')); ?>
                            </span>
                        <?php else: ?>
                            <span class="lrp-text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><strong>R$ <?php echo esc_html(number_format($final_amount, 2, ',', '.')); ?></strong></td>
                    <td><?php echo esc_html($p->invoice_number ?: '—'); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-accountant-payments&action=confirm&id=' . $p->id)); ?>" class="button button-primary">
                            <?php _e('Pagar', 'lab-resumos-parceiros'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="lrp-admin-notice success" style="margin-top: 20px;">
        <strong><?php _e('Tudo em dia!', 'lab-resumos-parceiros'); ?></strong>
        <?php _e('Nenhum pagamento pendente no momento.', 'lab-resumos-parceiros'); ?>
    </div>
    <?php endif; ?>
    
    <div class="lrp-table-wrap" style="margin-top: 30px;">
        <div class="lrp-table-header">
            <h2><?php _e('Histórico de Pagamentos', 'lab-resumos-parceiros'); ?></h2>
        </div>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Parceiro', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Período', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Valor Pago', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Ajustes', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Data Pagamento', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Pago por', 'lab-resumos-parceiros'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                <tr><td colspan="6"><?php _e('Nenhum pagamento realizado.', 'lab-resumos-parceiros'); ?></td></tr>
                <?php else: ?>
                <?php foreach ($history as $h): 
                    // Calcula ajustes do novo sistema
                    $h_adjustments_sum = 0.0;
                    if (class_exists('LRP_Adjustment') && !empty($h->id)) {
                        $h_adjustments_sum = LRP_Adjustment::get_closing_sum($h->id);
                    }
                    // Mantém compatibilidade com ajuste antigo
                    $h_old_adjustment = (float) ($h->adjustment_amount ?? 0);
                    $h_total_adjustments = $h_adjustments_sum + $h_old_adjustment;
                    
                    $h_final = LRP_Closing::get_final_amount($h);
                ?>
                <tr>
                    <td><?php echo esc_html($h->affiliate_name); ?></td>
                    <td><?php printf('%02d/%d', $h->period_month, $h->period_year); ?></td>
                    <td>R$ <?php echo esc_html(number_format($h_final, 2, ',', '.')); ?></td>
                    <td>
                        <?php if ($h_total_adjustments != 0): ?>
                            <span class="<?php echo $h_total_adjustments > 0 ? 'lrp-text-success' : 'lrp-text-danger'; ?>">
                                <?php echo $h_total_adjustments > 0 ? '+' : ''; ?>R$ <?php echo esc_html(number_format($h_total_adjustments, 2, ',', '.')); ?>
                            </span>
                        <?php else: ?>
                            <span class="lrp-text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($h->paid_at))); ?></td>
                    <td><?php echo esc_html($h->paid_by_name ?: '—'); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.lrp-text-success { color: #46b450; }
.lrp-text-danger { color: #dc3232; }
.lrp-text-muted { color: #999; }
</style>
