<?php
/**
 * Admin - Página de Ajustes Manuais
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.4.0
 * 
 * Variáveis disponíveis:
 * - $adjustments (array)
 * - $total (int)
 * - $total_pages (int)
 * - $paged (int)
 * - $filters (array)
 * - $stats (array)
 * - $affiliates (array)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap lrp-admin-wrap">
    <h1 class="wp-heading-inline"><?php _e('Ajustes Manuais', 'lab-resumos-parceiros'); ?></h1>
    <button type="button" class="page-title-action" id="lrp-new-adjustment-btn">
        <?php _e('Novo Ajuste', 'lab-resumos-parceiros'); ?>
    </button>
    <hr class="wp-header-end">
    
    <!-- Cards de estatísticas -->
    <div class="lrp-stats-cards" style="display: flex; gap: 15px; margin: 20px 0;">
        <div class="lrp-stat-card" style="background: #fff; padding: 15px 20px; border-radius: 8px; border-left: 4px solid #2271b1; flex: 1;">
            <div style="font-size: 24px; font-weight: bold; color: #1d2327;">
                <?php echo esc_html($stats['pending_count']); ?>
            </div>
            <div style="color: #646970; margin-top: 5px;">
                <?php _e('Ajustes Pendentes', 'lab-resumos-parceiros'); ?>
            </div>
        </div>
        <div class="lrp-stat-card" style="background: #fff; padding: 15px 20px; border-radius: 8px; border-left: 4px solid #00a32a; flex: 1;">
            <div style="font-size: 24px; font-weight: bold; color: #00a32a;">
                R$ <?php echo esc_html(number_format($stats['pending_bonus'], 2, ',', '.')); ?>
            </div>
            <div style="color: #646970; margin-top: 5px;">
                <?php _e('Bônus Pendentes', 'lab-resumos-parceiros'); ?>
            </div>
        </div>
        <div class="lrp-stat-card" style="background: #fff; padding: 15px 20px; border-radius: 8px; border-left: 4px solid #d63638; flex: 1;">
            <div style="font-size: 24px; font-weight: bold; color: #d63638;">
                R$ <?php echo esc_html(number_format(abs($stats['pending_discount']), 2, ',', '.')); ?>
            </div>
            <div style="color: #646970; margin-top: 5px;">
                <?php _e('Descontos Pendentes', 'lab-resumos-parceiros'); ?>
            </div>
        </div>
        <div class="lrp-stat-card" style="background: #fff; padding: 15px 20px; border-radius: 8px; border-left: 4px solid #8c8f94; flex: 1;">
            <div style="font-size: 24px; font-weight: bold; color: #1d2327;">
                R$ <?php echo esc_html(number_format($stats['pending_total'], 2, ',', '.')); ?>
            </div>
            <div style="color: #646970; margin-top: 5px;">
                <?php _e('Total Líquido Pendente', 'lab-resumos-parceiros'); ?>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="lrp-filters-wrap" style="background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <form method="get" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="page" value="lrp-adjustments">
            
            <div>
                <label for="filter-affiliate" style="display: block; margin-bottom: 5px; font-weight: 500;">
                    <?php _e('Afiliado', 'lab-resumos-parceiros'); ?>
                </label>
                <select name="affiliate_id" id="filter-affiliate" style="min-width: 200px;">
                    <option value=""><?php _e('Todos', 'lab-resumos-parceiros'); ?></option>
                    <?php foreach ($affiliates as $aff): ?>
                    <option value="<?php echo esc_attr($aff->id); ?>" <?php selected($filters['affiliate_id'], $aff->id); ?>>
                        <?php echo esc_html($aff->display_name . ' (' . $aff->coupon_code . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="filter-status" style="display: block; margin-bottom: 5px; font-weight: 500;">
                    <?php _e('Status', 'lab-resumos-parceiros'); ?>
                </label>
                <select name="status" id="filter-status">
                    <option value=""><?php _e('Todos', 'lab-resumos-parceiros'); ?></option>
                    <option value="pending" <?php selected($filters['status'], 'pending'); ?>><?php _e('Pendente', 'lab-resumos-parceiros'); ?></option>
                    <option value="closed" <?php selected($filters['status'], 'closed'); ?>><?php _e('Fechado', 'lab-resumos-parceiros'); ?></option>
                    <option value="paid" <?php selected($filters['status'], 'paid'); ?>><?php _e('Pago', 'lab-resumos-parceiros'); ?></option>
                    <option value="cancelled" <?php selected($filters['status'], 'cancelled'); ?>><?php _e('Cancelado', 'lab-resumos-parceiros'); ?></option>
                </select>
            </div>
            
            <div>
                <label for="filter-date-from" style="display: block; margin-bottom: 5px; font-weight: 500;">
                    <?php _e('Data Inicial', 'lab-resumos-parceiros'); ?>
                </label>
                <input type="date" name="date_from" id="filter-date-from" 
                       value="<?php echo esc_attr($filters['date_from']); ?>">
            </div>
            
            <div>
                <label for="filter-date-to" style="display: block; margin-bottom: 5px; font-weight: 500;">
                    <?php _e('Data Final', 'lab-resumos-parceiros'); ?>
                </label>
                <input type="date" name="date_to" id="filter-date-to" 
                       value="<?php echo esc_attr($filters['date_to']); ?>">
            </div>
            
            <div>
                <button type="submit" class="button"><?php _e('Filtrar', 'lab-resumos-parceiros'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-adjustments')); ?>" class="button">
                    <?php _e('Limpar', 'lab-resumos-parceiros'); ?>
                </a>
            </div>
        </form>
    </div>
    
    <!-- Tabela de ajustes -->
    <div class="lrp-table-wrap">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php _e('ID', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Afiliado', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Valor', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Motivo', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Criado por', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Data', 'lab-resumos-parceiros'); ?></th>
                    <th style="width: 100px;"><?php _e('Ações', 'lab-resumos-parceiros'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($adjustments)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px;">
                        <?php _e('Nenhum ajuste encontrado.', 'lab-resumos-parceiros'); ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($adjustments as $adj): ?>
                <tr>
                    <td><?php echo esc_html($adj->id); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&action=view&id=' . $adj->affiliate_id)); ?>">
                            <?php echo esc_html($adj->affiliate_name); ?>
                        </a>
                    </td>
                    <td>
                        <?php 
                        $amount = (float) $adj->amount;
                        $class = $amount >= 0 ? 'lrp-text-success' : 'lrp-text-danger';
                        $prefix = $amount >= 0 ? '+' : '';
                        ?>
                        <strong class="<?php echo esc_attr($class); ?>">
                            <?php echo esc_html($prefix . 'R$ ' . number_format($amount, 2, ',', '.')); ?>
                        </strong>
                    </td>
                    <td>
                        <span title="<?php echo esc_attr($adj->reason); ?>">
                            <?php echo esc_html(wp_trim_words($adj->reason, 10)); ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $status_labels = [
                            'pending'   => ['label' => __('Pendente', 'lab-resumos-parceiros'), 'class' => 'lrp-status-pending'],
                            'closed'    => ['label' => __('Fechado', 'lab-resumos-parceiros'), 'class' => 'lrp-status-closed'],
                            'paid'      => ['label' => __('Pago', 'lab-resumos-parceiros'), 'class' => 'lrp-status-paid'],
                            'cancelled' => ['label' => __('Cancelado', 'lab-resumos-parceiros'), 'class' => 'lrp-status-cancelled'],
                        ];
                        $status = $status_labels[$adj->status] ?? ['label' => $adj->status, 'class' => ''];
                        ?>
                        <span class="lrp-status-badge <?php echo esc_attr($status['class']); ?>">
                            <?php echo esc_html($status['label']); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($adj->created_by_name ?? '-'); ?></td>
                    <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($adj->created_at))); ?></td>
                    <td>
                        <?php if ($adj->status === 'pending'): ?>
                        <button type="button" class="button button-small lrp-cancel-adjustment" 
                                data-id="<?php echo esc_attr($adj->id); ?>"
                                title="<?php esc_attr_e('Cancelar ajuste', 'lab-resumos-parceiros'); ?>">
                            <?php _e('Cancelar', 'lab-resumos-parceiros'); ?>
                        </button>
                        <?php else: ?>
                        <span class="lrp-text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Paginação -->
    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php printf(_n('%s item', '%s itens', $total, 'lab-resumos-parceiros'), number_format_i18n($total)); ?>
            </span>
            <?php
            $pagination_args = array_filter($filters);
            $pagination_args['page'] = 'lrp-adjustments';
            
            echo paginate_links([
                'base'      => add_query_arg('paged', '%#%'),
                'format'    => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total'     => $total_pages,
                'current'   => $paged,
            ]);
            ?>
        </div>
    </div>
    <?php endif; ?>
    
    <p style="margin-top: 20px;">
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=lrp_export_csv&type=adjustments'), 'lrp_admin_nonce', 'nonce')); ?>" class="button">
            <?php _e('Exportar CSV', 'lab-resumos-parceiros'); ?>
        </a>
    </p>
</div>

<!-- Modal Novo Ajuste -->
<div id="lrp-new-adjustment-modal" class="lrp-modal" style="display: none;">
    <div class="lrp-modal-content">
        <div class="lrp-modal-header">
            <h3><?php _e('Novo Ajuste', 'lab-resumos-parceiros'); ?></h3>
            <button type="button" class="lrp-modal-close">&times;</button>
        </div>
        <div class="lrp-modal-body">
            <form id="lrp-new-adjustment-form">
                <?php wp_nonce_field('lrp_admin_nonce', 'adjustment_nonce'); ?>
                
                <p class="description" style="margin-bottom: 15px;">
                    <?php _e('Adicione um bônus (valor positivo) ou desconto (valor negativo) para um afiliado. O valor entrará imediatamente no saldo e será incluído no próximo fechamento.', 'lab-resumos-parceiros'); ?>
                </p>
                
                <table class="form-table">
                    <tr>
                        <th><label for="adj-affiliate"><?php _e('Afiliado', 'lab-resumos-parceiros'); ?> <span class="required">*</span></label></th>
                        <td>
                            <select name="affiliate_id" id="adj-affiliate" class="regular-text" required style="width: 100%;">
                                <option value=""><?php _e('Selecione...', 'lab-resumos-parceiros'); ?></option>
                                <?php foreach ($affiliates as $aff): ?>
                                <option value="<?php echo esc_attr($aff->id); ?>">
                                    <?php echo esc_html($aff->display_name . ' (' . $aff->coupon_code . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="adj-amount"><?php _e('Valor (R$)', 'lab-resumos-parceiros'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="number" name="amount" id="adj-amount" 
                                   class="regular-text" step="0.01" required
                                   placeholder="<?php esc_attr_e('Ex: 50.00 ou -30.00', 'lab-resumos-parceiros'); ?>">
                            <p class="description"><?php _e('Positivo = bônus | Negativo = desconto', 'lab-resumos-parceiros'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="adj-reason"><?php _e('Motivo', 'lab-resumos-parceiros'); ?> <span class="required">*</span></label></th>
                        <td>
                            <textarea name="reason" id="adj-reason" 
                                      class="large-text" rows="3" required
                                      placeholder="<?php esc_attr_e('Descreva o motivo do ajuste...', 'lab-resumos-parceiros'); ?>"></textarea>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <div class="lrp-modal-footer">
            <button type="button" class="button lrp-modal-close"><?php _e('Cancelar', 'lab-resumos-parceiros'); ?></button>
            <button type="button" id="lrp-save-adjustment" class="button button-primary"><?php _e('Criar Ajuste', 'lab-resumos-parceiros'); ?></button>
        </div>
    </div>
</div>

<style>
.lrp-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.lrp-modal-content {
    background: #fff;
    border-radius: 8px;
    width: 90%;
    max-width: 550px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.lrp-modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.lrp-modal-header h3 {
    margin: 0;
}
.lrp-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}
.lrp-modal-body {
    padding: 20px;
    max-height: 70vh;
    overflow-y: auto;
}
.lrp-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}
.lrp-modal-footer .button {
    margin-left: 10px;
}
.lrp-text-success { color: #00a32a; }
.lrp-text-danger { color: #d63638; }
.lrp-text-muted { color: #8c8f94; }
.lrp-status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}
.lrp-status-pending { background: #fff3cd; color: #856404; }
.lrp-status-closed { background: #d1ecf1; color: #0c5460; }
.lrp-status-paid { background: #d4edda; color: #155724; }
.lrp-status-cancelled { background: #f8d7da; color: #721c24; }
.required { color: #d63638; }
</style>

<script>
jQuery(document).ready(function($) {
    // Abrir modal novo ajuste
    $('#lrp-new-adjustment-btn').on('click', function() {
        $('#lrp-new-adjustment-form')[0].reset();
        $('#lrp-new-adjustment-modal').fadeIn(200);
    });
    
    // Fechar modal
    $('.lrp-modal-close').on('click', function() {
        $(this).closest('.lrp-modal').fadeOut(200);
    });
    
    // Fechar ao clicar fora
    $('.lrp-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).fadeOut(200);
        }
    });
    
    // Salvar ajuste
    $('#lrp-save-adjustment').on('click', function() {
        var $btn = $(this);
        var $form = $('#lrp-new-adjustment-form');
        
        var affiliateId = $('#adj-affiliate').val();
        var amount = $('#adj-amount').val();
        var reason = $('#adj-reason').val();
        
        if (!affiliateId || !amount || !reason.trim()) {
            alert('<?php _e('Preencha todos os campos obrigatórios.', 'lab-resumos-parceiros'); ?>');
            return;
        }
        
        if (parseFloat(amount) === 0) {
            alert('<?php _e('O valor não pode ser zero.', 'lab-resumos-parceiros'); ?>');
            return;
        }
        
        $btn.prop('disabled', true).text('<?php _e('Salvando...', 'lab-resumos-parceiros'); ?>');
        
        $.ajax({
            url: lrp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'lrp_create_adjustment',
                nonce: lrp_admin.nonce,
                affiliate_id: affiliateId,
                amount: amount,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || '<?php _e('Erro ao criar ajuste.', 'lab-resumos-parceiros'); ?>');
                    $btn.prop('disabled', false).text('<?php _e('Criar Ajuste', 'lab-resumos-parceiros'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Erro de comunicação.', 'lab-resumos-parceiros'); ?>');
                $btn.prop('disabled', false).text('<?php _e('Criar Ajuste', 'lab-resumos-parceiros'); ?>');
            }
        });
    });
    
    // Cancelar ajuste
    $('.lrp-cancel-adjustment').on('click', function() {
        if (!confirm('<?php _e('Cancelar este ajuste? Esta ação não pode ser desfeita.', 'lab-resumos-parceiros'); ?>')) {
            return;
        }
        
        var $btn = $(this);
        var adjustmentId = $btn.data('id');
        
        $btn.prop('disabled', true);
        
        $.ajax({
            url: lrp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'lrp_cancel_adjustment',
                nonce: lrp_admin.nonce,
                adjustment_id: adjustmentId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || '<?php _e('Erro ao cancelar ajuste.', 'lab-resumos-parceiros'); ?>');
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert('<?php _e('Erro de comunicação.', 'lab-resumos-parceiros'); ?>');
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>
