<?php
/**
 * Página de Fechamento Manual
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.8.0
 * 
 * Variáveis disponíveis:
 * - $last_closing (object|null)
 * - $closing_stats (object|null)
 * - $closing_details (array) Fechamentos individuais do período (v1.7.1)
 * - $accumulated_balances (array) Saldo acumulado por afiliado, indexado por affiliate_id (v1.7.1)
 */

if (!defined('ABSPATH')) exit;

$prev_month = (int) date('n', strtotime('-1 month'));
$prev_year = (int) date('Y', strtotime('-1 month'));
?>
<div class="wrap lrp-admin-wrap">
    <h1><?php _e('Fechamento Mensal', 'lab-resumos-parceiros'); ?></h1>
    <p class="description"><?php _e('Execute o fechamento mensal manualmente. O fechamento calcula as comissões do período selecionado e gera os lançamentos para pagamento.', 'lab-resumos-parceiros'); ?></p>
    
    <?php if ($last_closing): ?>
    <!-- Último fechamento -->
    <div class="lrp-metabox" style="margin-top: 20px;">
        <div class="lrp-metabox-header"><?php _e('Último Fechamento Executado', 'lab-resumos-parceiros'); ?></div>
        <div class="lrp-metabox-content">
            <div class="lrp-stats-cards" style="margin-bottom: 0;">
                <div class="lrp-stat-card">
                    <div class="lrp-stat-value"><?php echo sprintf('%02d/%d', $last_closing->period_month, $last_closing->period_year); ?></div>
                    <div class="lrp-stat-label"><?php _e('Período', 'lab-resumos-parceiros'); ?></div>
                </div>
                <div class="lrp-stat-card">
                    <div class="lrp-stat-value"><?php echo (int) $closing_stats->total; ?></div>
                    <div class="lrp-stat-label"><?php _e('Afiliados', 'lab-resumos-parceiros'); ?></div>
                </div>
                <div class="lrp-stat-card">
                    <div class="lrp-stat-value"><?php echo (int) $closing_stats->awaiting_invoice; ?></div>
                    <div class="lrp-stat-label"><?php _e('Aguardando NF', 'lab-resumos-parceiros'); ?></div>
                </div>
                <div class="lrp-stat-card">
                    <div class="lrp-stat-value"><?php echo (int) $closing_stats->awaiting_rpa; ?></div>
                    <div class="lrp-stat-label"><?php _e('Aguardando RPA', 'lab-resumos-parceiros'); ?></div>
                </div>
                <div class="lrp-stat-card">
                    <div class="lrp-stat-value"><?php echo (int) $closing_stats->closed; ?></div>
                    <div class="lrp-stat-label"><?php _e('Acumulados', 'lab-resumos-parceiros'); ?></div>
                </div>
                <div class="lrp-stat-card success">
                    <div class="lrp-stat-value">R$ <?php echo number_format((float) $closing_stats->total_commissions, 2, ',', '.'); ?></div>
                    <div class="lrp-stat-label"><?php _e('Total em Comissões', 'lab-resumos-parceiros'); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // Panorama detalhado do fechamento (v1.7.1)
    if (!empty($closing_details)):
        $status_groups = [
            'awaiting_invoice'  => ['label' => __('Aguardando NF (PJ)', 'lab-resumos-parceiros'),           'color' => '#856404', 'bg' => '#fff3cd', 'items' => []],
            'invoice_received'  => ['label' => __('NF Recebida (em análise)', 'lab-resumos-parceiros'),     'color' => '#0c5460', 'bg' => '#d1ecf1', 'items' => []],
            'awaiting_rpa'      => ['label' => __('Aguardando RPA (PF)', 'lab-resumos-parceiros'),          'color' => '#1b4f72', 'bg' => '#d6eaf8', 'items' => []],
            'approved'          => ['label' => __('Aprovados (aguardando pagamento)', 'lab-resumos-parceiros'), 'color' => '#155724', 'bg' => '#d4edda', 'items' => []],
            'paid'              => ['label' => __('Pagos', 'lab-resumos-parceiros'),                        'color' => '#155724', 'bg' => '#c3e6cb', 'items' => []],
            'closed'            => ['label' => __('Acumulados (abaixo do mínimo)', 'lab-resumos-parceiros'),'color' => '#383d41', 'bg' => '#e2e3e5', 'items' => []],
        ];

        foreach ($closing_details as $detail) {
            $s = $detail->status;
            if (isset($status_groups[$s])) {
                $status_groups[$s]['items'][] = $detail;
            }
        }

        // Remove grupos vazios
        $status_groups = array_filter($status_groups, function($g) { return !empty($g['items']); });
    ?>
    <div class="lrp-metabox" style="margin-top: 20px;">
        <div class="lrp-metabox-header"><?php _e('Detalhamento por Afiliado', 'lab-resumos-parceiros'); ?></div>
        <div class="lrp-metabox-content" style="padding: 0;">

        <?php foreach ($status_groups as $status_key => $group):
            $items = $group['items'];
            $group_total = 0;
            $is_collapsed = ($status_key === 'closed' || $status_key === 'paid');
        ?>
            <div class="lrp-closing-group" style="border-bottom: 1px solid #e5e5e5;">
                <div class="lrp-closing-group-header" 
                     style="padding: 12px 20px; background: <?php echo $group['bg']; ?>; color: <?php echo $group['color']; ?>; cursor: pointer; display: flex; justify-content: space-between; align-items: center; user-select: none;"
                     onclick="jQuery(this).next('.lrp-closing-group-body').slideToggle(200); jQuery(this).find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');">
                    <strong><?php echo esc_html($group['label']); ?> (<?php echo count($items); ?>)</strong>
                    <span class="dashicons <?php echo $is_collapsed ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-up-alt2'; ?>"></span>
                </div>
                <div class="lrp-closing-group-body" <?php if ($is_collapsed) echo 'style="display:none;"'; ?>>
                    <table class="wp-list-table widefat striped" style="border: none;">
                        <thead>
                            <tr>
                                <th style="width: 25%;"><?php _e('Parceiro', 'lab-resumos-parceiros'); ?></th>
                                <th style="width: 8%; text-align: center;"><?php _e('Tipo', 'lab-resumos-parceiros'); ?></th>
                                <th style="width: 8%; text-align: center;"><?php _e('Vendas', 'lab-resumos-parceiros'); ?></th>
                                <th style="text-align: right;"><?php _e('Comissões Período', 'lab-resumos-parceiros'); ?></th>
                                <th style="text-align: right;"><?php _e('Acumulado Anterior', 'lab-resumos-parceiros'); ?></th>
                                <th style="text-align: right;"><?php _e('Ajustes', 'lab-resumos-parceiros'); ?></th>
                                <th style="text-align: right;"><?php _e('Total Final', 'lab-resumos-parceiros'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item):
                            $is_rpa = ($item->billing_type === 'rpa');
                            $accumulated = isset($accumulated_balances[$item->affiliate_id]) ? (float) $accumulated_balances[$item->affiliate_id]->accumulated : 0;
                            $adjustments_sum = 0.0;
                            if (class_exists('LRP_Adjustment') && !empty($item->id)) {
                                $adjustments_sum = LRP_Adjustment::get_closing_sum($item->id);
                            }
                            $old_adj = (float) ($item->adjustment_amount ?? 0);
                            $total_adj = $adjustments_sum + $old_adj;
                            $final_amount = (float) $item->total_commissions + $accumulated + $total_adj;
                            $group_total += $final_amount;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($item->affiliate_name); ?></strong>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($is_rpa): ?>
                                        <span class="lrp-badge" style="background: #d1ecf1; color: #0c5460; font-size: 11px; padding: 2px 8px; border-radius: 3px;">RPA</span>
                                    <?php else: ?>
                                        <span class="lrp-badge" style="background: #d4edda; color: #155724; font-size: 11px; padding: 2px 8px; border-radius: 3px;">PJ</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;"><?php echo (int) $item->total_sales; ?></td>
                                <td style="text-align: right;">R$ <?php echo number_format((float) $item->total_commissions, 2, ',', '.'); ?></td>
                                <td style="text-align: right;">
                                    <?php if ($accumulated > 0): ?>
                                        <span style="color: #0c5460;">R$ <?php echo number_format($accumulated, 2, ',', '.'); ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($total_adj != 0): ?>
                                        <span style="color: <?php echo $total_adj > 0 ? '#46b450' : '#dc3232'; ?>;">
                                            <?php echo $total_adj > 0 ? '+' : ''; ?>R$ <?php echo number_format($total_adj, 2, ',', '.'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;"><strong>R$ <?php echo number_format($final_amount, 2, ',', '.'); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: <?php echo $group['bg']; ?>;">
                                <td colspan="6" style="text-align: right; padding-right: 15px;"><strong><?php _e('Total do grupo:', 'lab-resumos-parceiros'); ?></strong></td>
                                <td style="text-align: right;"><strong>R$ <?php echo number_format($group_total, 2, ',', '.'); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

        </div>
    </div>
    <?php endif; ?>
    
    <!-- Formulário de fechamento manual -->
    <div class="lrp-metabox" style="margin-top: 20px;">
        <div class="lrp-metabox-header"><?php _e('Executar Fechamento', 'lab-resumos-parceiros'); ?></div>
        <div class="lrp-metabox-content">
            <p><?php _e('Selecione o período de referência e clique em executar. O fechamento irá:', 'lab-resumos-parceiros'); ?></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><?php _e('Calcular comissões aprovadas do período selecionado', 'lab-resumos-parceiros'); ?></li>
                <li><?php _e('Gerar fechamento para cada afiliado ativo', 'lab-resumos-parceiros'); ?></li>
                <li><?php _e('Afiliados PJ com saldo > R$ 0: status "Aguardando NF"', 'lab-resumos-parceiros'); ?></li>
                <li><?php printf(
                    __('Afiliados RPA com saldo >= R$ %s: status "Aguardando RPA"', 'lab-resumos-parceiros'),
                    number_format(LRP_Settings::instance()->get_minimum_payout(), 2, ',', '.')
                ); ?></li>
                <li><?php _e('Notificar afiliados por email', 'lab-resumos-parceiros'); ?></li>
            </ul>
            
            <div class="lrp-admin-notice warning" style="margin: 15px 0;">
                <strong><?php _e('Atenção:', 'lab-resumos-parceiros'); ?></strong>
                <?php _e('Se já existir um fechamento para o período selecionado, os afiliados que já foram processados serão ignorados (sem duplicação). Use a opção "Reavaliar acumulados" para reprocessar fechamentos com status Acumulado.', 'lab-resumos-parceiros'); ?>
            </div>
            
            <form id="lrp-manual-closing-form" style="display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap;">
                <div>
                    <label for="lrp-closing-month"><strong><?php _e('Mês:', 'lab-resumos-parceiros'); ?></strong></label><br>
                    <select id="lrp-closing-month" style="min-width: 150px;">
                        <?php 
                        $months = [
                            1 => __('Janeiro', 'lab-resumos-parceiros'),
                            2 => __('Fevereiro', 'lab-resumos-parceiros'),
                            3 => __('Março', 'lab-resumos-parceiros'),
                            4 => __('Abril', 'lab-resumos-parceiros'),
                            5 => __('Maio', 'lab-resumos-parceiros'),
                            6 => __('Junho', 'lab-resumos-parceiros'),
                            7 => __('Julho', 'lab-resumos-parceiros'),
                            8 => __('Agosto', 'lab-resumos-parceiros'),
                            9 => __('Setembro', 'lab-resumos-parceiros'),
                            10 => __('Outubro', 'lab-resumos-parceiros'),
                            11 => __('Novembro', 'lab-resumos-parceiros'),
                            12 => __('Dezembro', 'lab-resumos-parceiros'),
                        ];
                        foreach ($months as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php selected($num, $prev_month); ?>>
                                <?php echo sprintf('%02d - %s', $num, esc_html($name)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="lrp-closing-year"><strong><?php _e('Ano:', 'lab-resumos-parceiros'); ?></strong></label><br>
                    <select id="lrp-closing-year" style="min-width: 100px;">
                        <?php 
                        $current_year = (int) date('Y');
                        for ($y = $current_year; $y >= $current_year - 2; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php selected($y, $prev_year); ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                        <input type="checkbox" id="lrp-force-reprocess" value="1">
                        <strong><?php _e('Reavaliar acumulados', 'lab-resumos-parceiros'); ?></strong>
                    </label>
                    <span class="description" style="font-size: 12px; color: #666;"><?php _e('Reprocessa fechamentos "Acumulado" com as regras atuais (PJ sem mínimo).', 'lab-resumos-parceiros'); ?></span>
                </div>
                
                <div>
                    <button type="button" id="lrp-run-closing-btn" class="button button-primary button-hero">
                        <?php _e('Executar Fechamento', 'lab-resumos-parceiros'); ?>
                    </button>
                </div>
            </form>
            
            <!-- Resultado -->
            <div id="lrp-closing-result" style="display: none; margin-top: 20px;">
                <div class="lrp-admin-notice" id="lrp-closing-result-notice"></div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#lrp-run-closing-btn').on('click', function() {
        var month = $('#lrp-closing-month').val();
        var year = $('#lrp-closing-year').val();
        var monthName = $('#lrp-closing-month option:selected').text().trim();
        var forceReprocess = $('#lrp-force-reprocess').is(':checked');
        
        var confirmMsg = '<?php echo esc_js(__('Tem certeza que deseja executar o fechamento para', 'lab-resumos-parceiros')); ?>';
        confirmMsg += ' ' + monthName + '/' + year + '?';
        if (forceReprocess) {
            confirmMsg += '\n\n<?php echo esc_js(__('REAVALIAR ACUMULADOS: Fechamentos com status "Acumulado" serão reavaliados com as regras atuais.', 'lab-resumos-parceiros')); ?>';
        }
        confirmMsg += '\n\n<?php echo esc_js(__('Esta ação irá processar todas as comissões do período e notificar os afiliados.', 'lab-resumos-parceiros')); ?>';
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        var $btn = $(this);
        var $result = $('#lrp-closing-result');
        var $notice = $('#lrp-closing-result-notice');
        
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Processando...', 'lab-resumos-parceiros')); ?>');
        $result.hide();
        
        var requestData = {
            action: 'lrp_run_manual_closing',
            nonce: lrp_admin.nonce,
            period_month: month,
            period_year: year
        };
        
        if (forceReprocess) {
            requestData.force_reprocess = 1;
        }
        
        $.ajax({
            url: lrp_admin.ajax_url,
            method: 'POST',
            data: requestData,
            success: function(response) {
                $result.show();
                
                if (response.success) {
                    var r = response.data.result;
                    var html = '<strong>' + response.data.message + '</strong>';
                    html += '<br><br>';
                    html += '<strong><?php echo esc_js(__('Detalhes:', 'lab-resumos-parceiros')); ?></strong><br>';
                    html += '<?php echo esc_js(__('Total de afiliados:', 'lab-resumos-parceiros')); ?> ' + r.total + '<br>';
                    html += '<?php echo esc_js(__('Novos processados:', 'lab-resumos-parceiros')); ?> ' + r.processed + '<br>';
                    if (r.reprocessed > 0) {
                        html += '<?php echo esc_js(__('Reavaliados (acumulado → pagamento):', 'lab-resumos-parceiros')); ?> ' + r.reprocessed + '<br>';
                    }
                    html += '<?php echo esc_js(__('Erros:', 'lab-resumos-parceiros')); ?> ' + r.errors;
                    
                    $notice.removeClass('warning error').addClass('success').html(html);
                } else {
                    $notice.removeClass('success warning').addClass('error').html(
                        '<strong><?php echo esc_js(__('Erro:', 'lab-resumos-parceiros')); ?></strong> ' + response.data.message
                    );
                }
            },
            error: function() {
                $result.show();
                $notice.removeClass('success warning').addClass('error').html(
                    '<strong><?php echo esc_js(__('Erro de conexão. Tente novamente.', 'lab-resumos-parceiros')); ?></strong>'
                );
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Executar Fechamento', 'lab-resumos-parceiros')); ?>');
            }
        });
    });
});
</script>
