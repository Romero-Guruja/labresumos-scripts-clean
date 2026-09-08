<?php
/**
 * Admin - Conciliação de comissões devidas
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.8.0
 *
 * Variáveis disponíveis:
 * - $receivables (array) linhas de LRP_Closing::get_receivables()
 * - $totals (array) de LRP_Closing::get_receivables_totals()
 * - $filters (array)
 * - $affiliates (array)
 */

if (!defined('ABSPATH')) {
    exit;
}

$status_cards = [
    'awaiting_invoice' => ['label' => __('Aguardando NF do parceiro', 'lab-resumos-parceiros'), 'color' => '#d63638'],
    'awaiting_rpa'     => ['label' => __('Aguardando emissão de RPA', 'lab-resumos-parceiros'), 'color' => '#dba617'],
    'invoice_received' => ['label' => __('NF em análise', 'lab-resumos-parceiros'), 'color' => '#2271b1'],
    'rejected'         => ['label' => __('NF rejeitada', 'lab-resumos-parceiros'), 'color' => '#8c8f94'],
    'approved'         => ['label' => __('Aprovado - a pagar', 'lab-resumos-parceiros'), 'color' => '#00a32a'],
];

$export_url = wp_nonce_url(
    add_query_arg(array_merge(
        ['action' => 'lrp_export_csv', 'type' => 'receivables'],
        array_filter([
            'affiliate_id' => $filters['affiliate_id'] ?: null,
            'status'       => $filters['status'] ?: null,
            'billing_type' => $filters['billing_type'] ?: null,
            'period_from'  => $filters['period_from'] ?: null,
            'period_to'    => $filters['period_to'] ?: null,
        ])
    ), admin_url('admin-ajax.php')),
    'lrp_admin_nonce',
    'nonce'
);

$can_settle = current_user_can('lrp_manage_payments');
?>
<div class="wrap lrp-admin-wrap">
    <h1 class="wp-heading-inline"><?php _e('Conciliação de Comissões Devidas', 'lab-resumos-parceiros'); ?></h1>
    <a href="<?php echo esc_url($export_url); ?>" class="page-title-action">
        <?php _e('Exportar CSV', 'lab-resumos-parceiros'); ?>
    </a>
    <hr class="wp-header-end">

    <p class="description" style="margin-bottom: 15px;">
        <?php _e('Tudo o que o programa deve aos parceiros e ainda não foi pago, em qualquer etapa do fluxo - inclusive fechamentos que não aparecem na tela de Pagamentos porque a NF ainda não foi enviada.', 'lab-resumos-parceiros'); ?>
    </p>

    <!-- Totais por status -->
    <div class="lrp-stats-cards" style="display: flex; gap: 15px; margin: 20px 0; flex-wrap: wrap;">
        <?php foreach ($status_cards as $status => $meta):
            $bucket = $totals['by_status'][$status] ?? ['count' => 0, 'amount' => 0.0];
        ?>
        <div class="lrp-stat-card" style="background:#fff;padding:15px 20px;border-radius:8px;border-left:4px solid <?php echo esc_attr($meta['color']); ?>;flex:1;min-width:190px;">
            <div style="font-size:22px;font-weight:bold;color:#1d2327;">
                R$ <?php echo esc_html(number_format($bucket['amount'], 2, ',', '.')); ?>
            </div>
            <div style="color:#646970;margin-top:5px;">
                <?php echo esc_html($meta['label']); ?>
                (<?php echo (int) $bucket['count']; ?>)
            </div>
        </div>
        <?php endforeach; ?>

        <div class="lrp-stat-card" style="background:#1d2327;padding:15px 20px;border-radius:8px;flex:1;min-width:190px;">
            <div style="font-size:22px;font-weight:bold;color:#fff;">
                R$ <?php echo esc_html(number_format($totals['total'], 2, ',', '.')); ?>
            </div>
            <div style="color:#c3c4c7;margin-top:5px;">
                <?php _e('TOTAL DEVIDO', 'lab-resumos-parceiros'); ?>
                (<?php echo (int) $totals['count']; ?>)
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="lrp-filters-wrap" style="background:#fff;padding:15px;border-radius:8px;margin-bottom:20px;">
        <form method="get" style="display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="page" value="lrp-receivables">

            <div>
                <label for="rec-affiliate" style="display:block;margin-bottom:5px;font-weight:500;">
                    <?php _e('Parceiro', 'lab-resumos-parceiros'); ?>
                </label>
                <select name="affiliate_id" id="rec-affiliate" style="min-width:220px;">
                    <option value=""><?php _e('Todos', 'lab-resumos-parceiros'); ?></option>
                    <?php foreach ($affiliates as $aff): ?>
                    <option value="<?php echo esc_attr($aff->id); ?>" <?php selected($filters['affiliate_id'], $aff->id); ?>>
                        <?php echo esc_html($aff->display_name . ($aff->coupon_code ? ' (' . $aff->coupon_code . ')' : '')); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="rec-status" style="display:block;margin-bottom:5px;font-weight:500;">
                    <?php _e('Status', 'lab-resumos-parceiros'); ?>
                </label>
                <select name="status" id="rec-status">
                    <option value=""><?php _e('Todos os pendentes', 'lab-resumos-parceiros'); ?></option>
                    <?php foreach (LRP_Closing::PENDING_STATUSES as $status): ?>
                    <option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>>
                        <?php echo esc_html(LRP_Closing::get_status_label($status)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="rec-billing" style="display:block;margin-bottom:5px;font-weight:500;">
                    <?php _e('Tipo', 'lab-resumos-parceiros'); ?>
                </label>
                <select name="billing_type" id="rec-billing">
                    <option value=""><?php _e('Todos', 'lab-resumos-parceiros'); ?></option>
                    <option value="pj" <?php selected($filters['billing_type'], 'pj'); ?>><?php _e('PJ (NF)', 'lab-resumos-parceiros'); ?></option>
                    <option value="rpa" <?php selected($filters['billing_type'], 'rpa'); ?>><?php _e('RPA', 'lab-resumos-parceiros'); ?></option>
                </select>
            </div>

            <div>
                <label for="rec-from" style="display:block;margin-bottom:5px;font-weight:500;">
                    <?php _e('Período de', 'lab-resumos-parceiros'); ?>
                </label>
                <input type="month" name="period_from" id="rec-from" value="<?php echo esc_attr($filters['period_from']); ?>">
            </div>

            <div>
                <label for="rec-to" style="display:block;margin-bottom:5px;font-weight:500;">
                    <?php _e('até', 'lab-resumos-parceiros'); ?>
                </label>
                <input type="month" name="period_to" id="rec-to" value="<?php echo esc_attr($filters['period_to']); ?>">
            </div>

            <div>
                <button type="submit" class="button button-primary"><?php _e('Filtrar', 'lab-resumos-parceiros'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-receivables')); ?>" class="button">
                    <?php _e('Limpar', 'lab-resumos-parceiros'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Tabela -->
    <div class="lrp-table-wrap">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Período', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Parceiro', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Tipo', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                    <th style="text-align:right;"><?php _e('Comissões', 'lab-resumos-parceiros'); ?></th>
                    <th style="text-align:right;"><?php _e('Ajustes', 'lab-resumos-parceiros'); ?></th>
                    <th style="text-align:right;"><?php _e('Total Devido', 'lab-resumos-parceiros'); ?></th>
                    <th style="text-align:center;"><?php _e('Dias Parado', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Ações', 'lab-resumos-parceiros'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($receivables)): ?>
                <tr>
                    <td colspan="9" style="text-align:center;padding:25px;">
                        <?php _e('Nenhuma comissão devida com os filtros atuais.', 'lab-resumos-parceiros'); ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($receivables as $r): ?>
                <tr>
                    <td><?php printf('%02d/%d', $r->period_month, $r->period_year); ?></td>
                    <td>
                        <strong><?php echo esc_html($r->affiliate_name); ?></strong><br>
                        <span class="lrp-text-muted" style="font-size:12px;"><?php echo esc_html($r->affiliate_email); ?></span>
                    </td>
                    <td><?php echo esc_html(strtoupper($r->billing_type ?: '-')); ?></td>
                    <td>
                        <span class="lrp-status-badge lrp-status-<?php echo esc_attr($r->status); ?>">
                            <?php echo esc_html(LRP_Closing::get_status_label($r->status)); ?>
                        </span>
                    </td>
                    <td style="text-align:right;">R$ <?php echo esc_html(number_format((float) $r->total_commissions, 2, ',', '.')); ?></td>
                    <td style="text-align:right;">
                        <?php if ((float) $r->adjustments_sum != 0): ?>
                            <span class="<?php echo $r->adjustments_sum > 0 ? 'lrp-text-success' : 'lrp-text-danger'; ?>">
                                <?php echo $r->adjustments_sum > 0 ? '+' : ''; ?>R$ <?php echo esc_html(number_format((float) $r->adjustments_sum, 2, ',', '.')); ?>
                            </span>
                        <?php else: ?>
                            <span class="lrp-text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;"><strong>R$ <?php echo esc_html(number_format((float) $r->final_amount, 2, ',', '.')); ?></strong></td>
                    <td style="text-align:center;">
                        <?php
                        $days  = (int) $r->days_pending;
                        $color = $days >= 60 ? '#d63638' : ($days >= 30 ? '#dba617' : '#646970');
                        ?>
                        <span style="color:<?php echo esc_attr($color); ?>;font-weight:<?php echo $days >= 30 ? 'bold' : 'normal'; ?>;">
                            <?php echo $days; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($can_settle): ?>
                        <button type="button"
                                class="button lrp-manual-settle-btn"
                                data-id="<?php echo esc_attr($r->id); ?>"
                                data-name="<?php echo esc_attr($r->affiliate_name); ?>"
                                data-period="<?php echo esc_attr(sprintf('%02d/%d', $r->period_month, $r->period_year)); ?>"
                                data-amount="<?php echo esc_attr(number_format((float) $r->final_amount, 2, ',', '.')); ?>"
                                data-billing="<?php echo esc_attr(strtoupper($r->billing_type ?: '-')); ?>">
                            <?php _e('Registrar pagamento externo', 'lab-resumos-parceiros'); ?>
                        </button>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-adjustments&affiliate_id=' . $r->affiliate_id)); ?>" class="button">
                            <?php _e('Ajustes', 'lab-resumos-parceiros'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($receivables)): ?>
            <tfoot>
                <tr>
                    <th colspan="6" style="text-align:right;"><?php _e('TOTAL DEVIDO', 'lab-resumos-parceiros'); ?></th>
                    <th style="text-align:right;">R$ <?php echo esc_html(number_format($totals['total'], 2, ',', '.')); ?></th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if ($can_settle): ?>
<!-- Modal de baixa manual -->
<div id="lrp-manual-settle-modal" class="lrp-modal" style="display:none;">
    <div class="lrp-modal-content">
        <div class="lrp-modal-header">
            <h3><?php _e('Registrar pagamento externo', 'lab-resumos-parceiros'); ?></h3>
            <button type="button" class="lrp-modal-close">&times;</button>
        </div>
        <div class="lrp-modal-body">
            <div class="notice notice-warning inline" style="margin:0 0 15px;">
                <p style="margin:8px 0;">
                    <?php _e('Use isto quando a comissão <strong>já foi paga por fora do sistema</strong> (RPA, PIX, transferência) e o fechamento continua aberto. O fechamento vai direto para "Pago", sem passar pelo fluxo de NF, e fica marcado como baixa manual.', 'lab-resumos-parceiros'); ?>
                </p>
            </div>

            <table class="form-table" style="margin-top:0;">
                <tr>
                    <th><?php _e('Fechamento', 'lab-resumos-parceiros'); ?></th>
                    <td>
                        <strong id="lrp-settle-name"></strong><br>
                        <span id="lrp-settle-meta" class="lrp-text-muted"></span>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Valor a baixar', 'lab-resumos-parceiros'); ?></th>
                    <td><strong style="font-size:18px;">R$ <span id="lrp-settle-amount"></span></strong></td>
                </tr>
                <tr>
                    <th><label for="lrp-settle-method"><?php _e('Como foi pago', 'lab-resumos-parceiros'); ?> <span class="required">*</span></label></th>
                    <td>
                        <select id="lrp-settle-method" class="regular-text" required>
                            <option value=""><?php _e('Selecione...', 'lab-resumos-parceiros'); ?></option>
                            <?php foreach (LRP_Closing::SETTLEMENT_METHODS as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="lrp-settle-date"><?php _e('Data real do pagamento', 'lab-resumos-parceiros'); ?> <span class="required">*</span></label></th>
                    <td>
                        <input type="date" id="lrp-settle-date" required
                               max="<?php echo esc_attr(date('Y-m-d', current_time('timestamp'))); ?>">
                        <p class="description"><?php _e('Pode ser retroativa - use a data em que o dinheiro saiu.', 'lab-resumos-parceiros'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="lrp-settle-reason"><?php _e('Motivo', 'lab-resumos-parceiros'); ?> <span class="required">*</span></label></th>
                    <td>
                        <textarea id="lrp-settle-reason" class="large-text" rows="3" required
                                  placeholder="<?php esc_attr_e('Ex: parceira cadastrada como PJ, mas foi paga via RPA em agosto.', 'lab-resumos-parceiros'); ?>"></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label for="lrp-settle-proof"><?php _e('Comprovante', 'lab-resumos-parceiros'); ?></label></th>
                    <td>
                        <input type="file" id="lrp-settle-proof" accept=".pdf,.jpg,.jpeg,.png">
                        <p class="description"><?php _e('Opcional - PDF, JPG ou PNG, até 5MB.', 'lab-resumos-parceiros'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <div class="lrp-modal-footer">
            <button type="button" class="button lrp-modal-close"><?php _e('Cancelar', 'lab-resumos-parceiros'); ?></button>
            <button type="button" id="lrp-confirm-settle" class="button button-primary">
                <?php _e('Confirmar baixa manual', 'lab-resumos-parceiros'); ?>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.lrp-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100000; display: flex; align-items: center; justify-content: center; }
.lrp-modal-content { background: #fff; border-radius: 8px; width: 90%; max-width: 620px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
.lrp-modal-header { padding: 15px 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
.lrp-modal-header h3 { margin: 0; }
.lrp-modal-header .lrp-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }
.lrp-modal-body { padding: 20px; max-height: 70vh; overflow-y: auto; }
.lrp-modal-footer { padding: 15px 20px; border-top: 1px solid #ddd; text-align: right; }
.lrp-modal-footer .button { margin-left: 10px; }
.lrp-text-success { color: #00a32a; }
.lrp-text-danger { color: #d63638; }
.lrp-text-muted { color: #8c8f94; }
.required { color: #d63638; }
.lrp-status-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
.lrp-status-awaiting_invoice { background: #fcf0f1; color: #8a1f22; }
.lrp-status-awaiting_rpa { background: #fcf9e8; color: #7a5c00; }
.lrp-status-invoice_received { background: #e5f0f8; color: #0a4b78; }
.lrp-status-rejected { background: #f0f0f1; color: #50575e; }
.lrp-status-approved { background: #edfaef; color: #0a6b1c; }
.lrp-badge-manual { background: #f0e6ff; color: #5b2d90; }
</style>

<?php if ($can_settle): ?>
<script>
jQuery(document).ready(function($) {
    var $modal = $('#lrp-manual-settle-modal');
    var currentId = 0;

    $('.lrp-manual-settle-btn').on('click', function() {
        var $b = $(this);
        currentId = $b.data('id');

        $('#lrp-settle-name').text($b.data('name'));
        $('#lrp-settle-meta').text(
            '<?php echo esc_js(__('Período', 'lab-resumos-parceiros')); ?> ' + $b.data('period') +
            ' | <?php echo esc_js(__('Cadastro', 'lab-resumos-parceiros')); ?>: ' + $b.data('billing') +
            ' | #' + currentId
        );
        $('#lrp-settle-amount').text($b.data('amount'));

        $('#lrp-settle-method').val('');
        $('#lrp-settle-date').val('');
        $('#lrp-settle-reason').val('');
        $('#lrp-settle-proof').val('');

        $modal.fadeIn(200);
    });

    $('.lrp-modal-close').on('click', function() {
        $(this).closest('.lrp-modal').fadeOut(200);
    });

    $modal.on('click', function(e) {
        if (e.target === this) { $(this).fadeOut(200); }
    });

    $('#lrp-confirm-settle').on('click', function() {
        var $btn = $(this);
        var method = $('#lrp-settle-method').val();
        var date = $('#lrp-settle-date').val();
        var reason = $.trim($('#lrp-settle-reason').val());

        if (!method || !date || !reason) {
            alert('<?php echo esc_js(__('Preencha método, data e motivo.', 'lab-resumos-parceiros')); ?>');
            return;
        }

        var confirmMsg = '<?php echo esc_js(__('Confirmar baixa manual de R$', 'lab-resumos-parceiros')); ?> ' +
            $('#lrp-settle-amount').text() + ' <?php echo esc_js(__('para', 'lab-resumos-parceiros')); ?> ' +
            $('#lrp-settle-name').text() + '?';

        if (!confirm(confirmMsg)) { return; }

        var data = new FormData();
        data.append('action', 'lrp_manual_settle');
        data.append('nonce', lrp_admin.nonce);
        data.append('closing_id', currentId);
        data.append('method', method);
        data.append('date', date);
        data.append('reason', reason);

        var proof = $('#lrp-settle-proof')[0].files[0];
        if (proof) { data.append('proof_file', proof); }

        $btn.prop('disabled', true).text('<?php echo esc_js(__('Registrando...', 'lab-resumos-parceiros')); ?>');

        $.ajax({
            url: lrp_admin.ajax_url,
            type: 'POST',
            data: data,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert((response.data && response.data.message) || '<?php echo esc_js(__('Erro ao registrar baixa manual.', 'lab-resumos-parceiros')); ?>');
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Confirmar baixa manual', 'lab-resumos-parceiros')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Erro de comunicação.', 'lab-resumos-parceiros')); ?>');
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Confirmar baixa manual', 'lab-resumos-parceiros')); ?>');
            }
        });
    });
});
</script>
<?php endif; ?>
