<?php
/**
 * Dashboard do Contador
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) exit;
?>
<div class="wrap lrp-admin-wrap">
    <h1>💰 <?php _e('Financeiro - Parceiros', 'lab-resumos-parceiros'); ?></h1>
    
    <div class="lrp-stats-cards">
        <?php if ($stats['pending_invoices'] > 0): ?>
        <div class="lrp-stat-card warning">
            <div class="lrp-stat-value"><?php echo esc_html($stats['pending_invoices']); ?></div>
            <div class="lrp-stat-label"><?php _e('NFs para Analisar', 'lab-resumos-parceiros'); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($stats['pending_rpa'] > 0): ?>
        <div class="lrp-stat-card warning">
            <div class="lrp-stat-value"><?php echo esc_html($stats['pending_rpa']); ?></div>
            <div class="lrp-stat-label"><?php _e('RPAs para Processar', 'lab-resumos-parceiros'); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($stats['pending_payments'] > 0): ?>
        <div class="lrp-stat-card highlight">
            <div class="lrp-stat-value"><?php echo esc_html($stats['pending_payments']); ?></div>
            <div class="lrp-stat-label"><?php _e('Pagamentos Pendentes', 'lab-resumos-parceiros'); ?></div>
        </div>
        <?php endif; ?>
        
        <div class="lrp-stat-card">
            <div class="lrp-stat-value">R$ <?php echo esc_html(number_format($stats['total_pending_amount'], 2, ',', '.')); ?></div>
            <div class="lrp-stat-label"><?php _e('Total a Pagar', 'lab-resumos-parceiros'); ?></div>
        </div>
        
        <div class="lrp-stat-card success">
            <div class="lrp-stat-value"><?php echo esc_html($stats['month_payments']); ?></div>
            <div class="lrp-stat-label"><?php _e('Pagamentos Este Mês', 'lab-resumos-parceiros'); ?></div>
        </div>
        
        <div class="lrp-stat-card success">
            <div class="lrp-stat-value">R$ <?php echo esc_html(number_format($stats['month_paid_amount'], 2, ',', '.')); ?></div>
            <div class="lrp-stat-label"><?php _e('Pago Este Mês', 'lab-resumos-parceiros'); ?></div>
        </div>
    </div>
    
    <div class="lrp-quick-actions">
        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-accountant-invoices')); ?>" class="button button-primary button-hero">
            📄 <?php _e('Analisar NFs', 'lab-resumos-parceiros'); ?>
            <?php if ($stats['pending_invoices'] > 0): ?>
                <span class="count">(<?php echo esc_html($stats['pending_invoices']); ?>)</span>
            <?php endif; ?>
        </a>
        
        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-accountant-payments')); ?>" class="button button-hero">
            💳 <?php _e('Realizar Pagamentos', 'lab-resumos-parceiros'); ?>
            <?php if ($stats['pending_payments'] > 0): ?>
                <span class="count">(<?php echo esc_html($stats['pending_payments']); ?>)</span>
            <?php endif; ?>
        </a>
    </div>
</div>

