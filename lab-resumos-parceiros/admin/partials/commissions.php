<?php
/**
 * Lista de Comissões
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) exit;

$current_status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
?>
<div class="wrap lrp-admin-wrap">
    <h1>💰 <?php _e('Comissões', 'lab-resumos-parceiros'); ?></h1>
    
    <ul class="subsubsub">
        <li><a href="<?php echo esc_url(admin_url('admin.php?page=lrp-commissions')); ?>" <?php echo !$current_status ? 'class="current"' : ''; ?>><?php _e('Todas', 'lab-resumos-parceiros'); ?></a> |</li>
        <li><a href="<?php echo esc_url(admin_url('admin.php?page=lrp-commissions&status=pending')); ?>" <?php echo $current_status === 'pending' ? 'class="current"' : ''; ?>><?php _e('Pendentes', 'lab-resumos-parceiros'); ?></a> |</li>
        <li><a href="<?php echo esc_url(admin_url('admin.php?page=lrp-commissions&status=approved')); ?>" <?php echo $current_status === 'approved' ? 'class="current"' : ''; ?>><?php _e('Aprovadas', 'lab-resumos-parceiros'); ?></a> |</li>
        <li><a href="<?php echo esc_url(admin_url('admin.php?page=lrp-commissions&status=paid')); ?>" <?php echo $current_status === 'paid' ? 'class="current"' : ''; ?>><?php _e('Pagas', 'lab-resumos-parceiros'); ?></a></li>
    </ul>
    
    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th width="30">#</th>
                <th><?php _e('Data', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Parceiro', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Pedido', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Tipo', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Nível', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Taxa', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Valor', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($commissions['items'])): ?>
            <tr><td colspan="9"><?php _e('Nenhuma comissão encontrada.', 'lab-resumos-parceiros'); ?></td></tr>
            <?php else: ?>
            <?php foreach ($commissions['items'] as $c): ?>
            <tr>
                <td><?php echo esc_html($c->id); ?></td>
                <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($c->created_at))); ?></td>
                <td><?php echo esc_html($c->affiliate_name); ?></td>
                <td>#<?php echo esc_html($c->order_id); ?></td>
                <td><?php echo $c->attribution_type === 'coupon' ? '🎫 Cupom' : '🔗 Link'; ?></td>
                <td>
                    <?php 
                    $levels = ['direct' => 'Direta', 'level_2' => 'Nível 2', 'level_3' => 'Nível 3'];
                    echo esc_html($levels[$c->commission_type] ?? $c->commission_type);
                    ?>
                </td>
                <td><?php echo esc_html($c->commission_rate); ?>%</td>
                <td>R$ <?php echo esc_html(number_format($c->commission_amount, 2, ',', '.')); ?></td>
                <td><span class="lrp-badge lrp-badge-<?php echo esc_attr($c->status); ?>"><?php echo esc_html($c->status); ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <p style="margin-top: 20px;">
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=lrp_export_csv&type=commissions'), 'lrp_admin_nonce', 'nonce')); ?>" class="button">
            📥 <?php _e('Exportar CSV', 'lab-resumos-parceiros'); ?>
        </a>
    </p>
</div>

