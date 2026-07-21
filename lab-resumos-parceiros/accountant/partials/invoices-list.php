<?php
/**
 * Lista de Notas Fiscais - Área do Contador
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $pending (array) Fechamentos com status 'invoice_received'
 * - $approved (array) Últimos 20 fechamentos com status 'approved'
 */

if (!defined('ABSPATH')) exit;
?>
<div class="wrap lrp-admin-wrap">
    <h1><?php _e('Notas Fiscais', 'lab-resumos-parceiros'); ?></h1>
    
    <?php if (!empty($pending)): ?>
    <div class="lrp-table-wrap">
        <div class="lrp-table-header">
            <h2><?php _e('NFs para Analisar', 'lab-resumos-parceiros'); ?> <span class="count">(<?php echo count($pending); ?>)</span></h2>
        </div>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Parceiro', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Tipo', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Período', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Valor', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('NF Número', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Data Envio', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Ações', 'lab-resumos-parceiros'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $item): 
                    $affiliate_obj = new LRP_Affiliate($item->affiliate_id);
                    $is_rpa = $affiliate_obj->is_rpa();
                    $final_amount = LRP_Closing::get_final_amount($item);
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($item->affiliate_name); ?></strong>
                        <br><small><?php echo esc_html($affiliate_obj->get_email()); ?></small>
                    </td>
                    <td>
                        <?php if ($is_rpa): ?>
                            <span class="lrp-badge" style="background: #d1ecf1; color: #0c5460;">RPA</span>
                        <?php else: ?>
                            <span class="lrp-badge" style="background: #d4edda; color: #155724;">PJ</span>
                        <?php endif; ?>
                    </td>
                    <td><?php printf('%02d/%d', $item->period_month, $item->period_year); ?></td>
                    <td><strong>R$ <?php echo esc_html(number_format($final_amount, 2, ',', '.')); ?></strong></td>
                    <td><?php echo esc_html($item->invoice_number ?: '—'); ?></td>
                    <td><?php echo !empty($item->invoice_uploaded_at) ? esc_html(date_i18n('d/m/Y H:i', strtotime($item->invoice_uploaded_at))) : '—'; ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-accountant-invoices&action=view&id=' . $item->id)); ?>" class="button">
                            <?php _e('Ver Detalhes', 'lab-resumos-parceiros'); ?>
                        </a>
                        <button type="button" class="button button-primary lrp-approve-invoice" data-id="<?php echo esc_attr($item->id); ?>">
                            <?php _e('Aprovar', 'lab-resumos-parceiros'); ?>
                        </button>
                        <button type="button" class="button lrp-reject-invoice" data-id="<?php echo esc_attr($item->id); ?>" style="color: #a00;">
                            <?php _e('Rejeitar', 'lab-resumos-parceiros'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="lrp-admin-notice success" style="margin-top: 20px;">
        <strong><?php _e('Tudo em dia!', 'lab-resumos-parceiros'); ?></strong>
        <?php _e('Nenhuma Nota Fiscal pendente de análise.', 'lab-resumos-parceiros'); ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($pending_rpa)): ?>
    <div class="lrp-table-wrap" style="margin-top: 30px;">
        <div class="lrp-table-header">
            <h2><?php _e('RPAs para Processar', 'lab-resumos-parceiros'); ?> <span class="count">(<?php echo count($pending_rpa); ?>)</span></h2>
            <p class="description"><?php _e('Afiliados PF que atingiram o valor mínimo. Emita o RPA e depois aprove aqui.', 'lab-resumos-parceiros'); ?></p>
        </div>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Parceiro', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('CPF', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Período', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Valor', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Dados PIX', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Ações', 'lab-resumos-parceiros'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_rpa as $item):
                    $rpa_affiliate = new LRP_Affiliate($item->affiliate_id);
                    $rpa_data = $rpa_affiliate->get_rpa_data();
                    $rpa_final_amount = LRP_Closing::get_final_amount($item);
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($item->affiliate_name); ?></strong>
                        <br><small><?php echo esc_html($rpa_affiliate->get_email()); ?></small>
                        <br><small><?php echo esc_html($rpa_data['telefone'] ?? ''); ?></small>
                    </td>
                    <td>
                        <?php echo esc_html($rpa_data['cpf_formatted'] ?? ''); ?>
                        <?php if (!empty($rpa_data['data_nascimento_fmt'])): ?>
                        <br><small><?php echo esc_html($rpa_data['data_nascimento_fmt']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php printf('%02d/%d', $item->period_month, $item->period_year); ?></td>
                    <td><strong>R$ <?php echo esc_html(number_format($rpa_final_amount, 2, ',', '.')); ?></strong></td>
                    <td>
                        <?php
                        $pix_key = $rpa_affiliate->get_decrypted_pix_key();
                        $pix_type = strtoupper($rpa_affiliate->get_data('pix_key_type'));
                        if ($pix_key): ?>
                            <small><?php echo esc_html($pix_type); ?>:</small><br>
                            <code><?php echo esc_html($pix_key); ?></code>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-accountant-invoices&action=view&id=' . $item->id)); ?>" class="button">
                            <?php _e('Ver Detalhes', 'lab-resumos-parceiros'); ?>
                        </a>
                        <button type="button" class="button button-primary lrp-approve-rpa" data-id="<?php echo esc_attr($item->id); ?>">
                            <?php _e('RPA Emitido - Aprovar', 'lab-resumos-parceiros'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($approved)): ?>
    <div class="lrp-table-wrap" style="margin-top: 30px;">
        <div class="lrp-table-header">
            <h2><?php _e('Aprovadas Recentemente', 'lab-resumos-parceiros'); ?></h2>
        </div>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Parceiro', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Período', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Valor', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('NF Número', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($approved as $item): 
                    $final_amount = LRP_Closing::get_final_amount($item);
                ?>
                <tr>
                    <td><?php echo esc_html($item->affiliate_name); ?></td>
                    <td><?php printf('%02d/%d', $item->period_month, $item->period_year); ?></td>
                    <td>R$ <?php echo esc_html(number_format($final_amount, 2, ',', '.')); ?></td>
                    <td><?php echo esc_html($item->invoice_number ?: '—'); ?></td>
                    <td><span class="lrp-badge lrp-badge-approved"><?php _e('Aprovada', 'lab-resumos-parceiros'); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
