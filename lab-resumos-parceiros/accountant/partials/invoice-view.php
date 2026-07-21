<?php
/**
 * Detalhe de Nota Fiscal - Área do Contador
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $closing (object) Fechamento
 * - $affiliate (LRP_Affiliate) Afiliado
 */

if (!defined('ABSPATH')) exit;

$period = sprintf('%02d/%d', $closing->period_month, $closing->period_year);
$is_rpa = $affiliate->is_rpa();
$final_amount = LRP_Closing::get_final_amount($closing);
$invoice_url = LRP_Closing::get_invoice_url($closing);

// Calcula ajustes
$adjustments_sum = 0.0;
if (class_exists('LRP_Adjustment') && !empty($closing->id)) {
    $adjustments_sum = LRP_Adjustment::get_closing_sum($closing->id);
}
$old_adjustment = (float) ($closing->adjustment_amount ?? 0);
$total_adjustments = $adjustments_sum + $old_adjustment;
?>
<div class="wrap lrp-admin-wrap">
    <h1>
        <?php _e('Análise de NF', 'lab-resumos-parceiros'); ?> — 
        <?php echo esc_html($affiliate->get_display_name()); ?> 
        (<?php echo esc_html($period); ?>)
    </h1>
    
    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-accountant-invoices')); ?>" class="button">
            &larr; <?php _e('Voltar para lista', 'lab-resumos-parceiros'); ?>
        </a>
    </p>
    
    <div class="lrp-two-columns">
        <div>
            <!-- Dados do Fechamento -->
            <div class="lrp-metabox">
                <div class="lrp-metabox-header"><?php _e('Dados do Fechamento', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content">
                    <div class="lrp-affiliate-info">
                        <div class="lrp-info-item">
                            <strong><?php echo esc_html($closing->total_sales); ?></strong>
                            <span><?php _e('Vendas', 'lab-resumos-parceiros'); ?></span>
                        </div>
                        <div class="lrp-info-item">
                            <strong>R$ <?php echo esc_html(number_format($closing->total_commissions, 2, ',', '.')); ?></strong>
                            <span><?php _e('Comissões', 'lab-resumos-parceiros'); ?></span>
                        </div>
                        <div class="lrp-info-item">
                            <?php if ($total_adjustments != 0): ?>
                                <strong style="color: <?php echo $total_adjustments > 0 ? '#46b450' : '#dc3232'; ?>;">
                                    <?php echo $total_adjustments > 0 ? '+' : ''; ?>R$ <?php echo esc_html(number_format($total_adjustments, 2, ',', '.')); ?>
                                </strong>
                            <?php else: ?>
                                <strong>—</strong>
                            <?php endif; ?>
                            <span><?php _e('Ajustes', 'lab-resumos-parceiros'); ?></span>
                        </div>
                        <div class="lrp-info-item">
                            <strong>R$ <?php echo esc_html(number_format($final_amount, 2, ',', '.')); ?></strong>
                            <span><?php _e('Total Final', 'lab-resumos-parceiros'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Nota Fiscal -->
            <div class="lrp-metabox" style="margin-top: 20px;">
                <div class="lrp-metabox-header"><?php _e('Nota Fiscal', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content">
                    <p><strong><?php _e('Número:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($closing->invoice_number ?: __('Não informado', 'lab-resumos-parceiros')); ?></p>
                    <p><strong><?php _e('Data de envio:', 'lab-resumos-parceiros'); ?></strong> <?php echo !empty($closing->invoice_uploaded_at) ? esc_html(date_i18n('d/m/Y H:i', strtotime($closing->invoice_uploaded_at))) : '—'; ?></p>
                    
                    <?php if ($invoice_url): ?>
                    <div class="lrp-invoice-preview" style="margin-top: 15px;">
                        <a href="<?php echo esc_url($invoice_url); ?>" target="_blank" class="button button-large">
                            <?php _e('Abrir NF (PDF)', 'lab-resumos-parceiros'); ?>
                        </a>
                        <br><br>
                        <iframe src="<?php echo esc_url($invoice_url); ?>" width="100%" height="500" style="border: 1px solid #ccd0d4;"></iframe>
                    </div>
                    <?php else: ?>
                    <p><em><?php _e('Arquivo da NF não disponível.', 'lab-resumos-parceiros'); ?></em></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($closing->rejection_reason)): ?>
                    <div class="lrp-admin-notice warning" style="margin-top: 15px;">
                        <strong><?php _e('Rejeição anterior:', 'lab-resumos-parceiros'); ?></strong>
                        <?php echo esc_html($closing->rejection_reason); ?>
                        <?php if (!empty($closing->rejected_at)): ?>
                            <br><small><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($closing->rejected_at))); ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div>
            <!-- Dados do Parceiro -->
            <div class="lrp-metabox">
                <div class="lrp-metabox-header"><?php _e('Dados do Parceiro', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content">
                    <p><strong><?php _e('Nome:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_display_name()); ?></p>
                    <p><strong><?php _e('Email:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_email()); ?></p>
                    <p><strong><?php _e('Tipo:', 'lab-resumos-parceiros'); ?></strong> 
                        <?php if ($is_rpa): ?>
                            <span class="lrp-badge" style="background: #d1ecf1; color: #0c5460;">RPA</span>
                        <?php else: ?>
                            <span class="lrp-badge" style="background: #d4edda; color: #155724;">PJ</span>
                        <?php endif; ?>
                    </p>
                    
                    <hr>
                    
                    <?php if ($is_rpa): ?>
                        <?php $rpa_data = $affiliate->get_rpa_data(); ?>
                        <p><strong><?php _e('CPF:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($rpa_data['cpf_formatted']); ?></p>
                        <?php if (!empty($rpa_data['data_nascimento_fmt'])): ?>
                        <p><strong><?php _e('Data de Nascimento:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($rpa_data['data_nascimento_fmt']); ?></p>
                        <?php endif; ?>
                        <p><strong><?php _e('Endereço:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($rpa_data['endereco']); ?></p>
                        <p><strong><?php _e('Telefone:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($rpa_data['telefone']); ?></p>
                        <?php if (!empty($rpa_data['inss_number'])): ?>
                        <p><strong><?php _e('INSS/PIS:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($rpa_data['inss_number']); ?></p>
                        <?php endif; ?>
                        <p><strong><?php _e('Descrição do Serviço:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($rpa_data['descricao_servico']); ?></p>
                    <?php else: ?>
                        <p><strong><?php _e('CNPJ:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_company_cnpj_formatted()); ?></p>
                        <p><strong><?php _e('Razão Social:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_company_name()); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Dados de Pagamento -->
            <div class="lrp-metabox" style="margin-top: 20px;">
                <div class="lrp-metabox-header"><?php _e('Dados de Pagamento', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content">
                    <?php if ($affiliate->get_data('payment_method') === 'pix'): ?>
                        <p><strong><?php _e('Método:', 'lab-resumos-parceiros'); ?></strong> PIX</p>
                        <p><strong><?php _e('Tipo de chave:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html(strtoupper($affiliate->get_data('pix_key_type'))); ?></p>
                        <p><strong><?php _e('Chave PIX:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_decrypted_pix_key()); ?></p>
                    <?php else: ?>
                        <p><strong><?php _e('Método:', 'lab-resumos-parceiros'); ?></strong> <?php _e('Transferência Bancária', 'lab-resumos-parceiros'); ?></p>
                        <p><strong><?php _e('Banco:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('bank_name')); ?></p>
                        <p><strong><?php _e('Agência:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('bank_agency')); ?></p>
                        <p><strong><?php _e('Conta:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('bank_account')); ?></p>
                    <?php endif; ?>
                    <hr>
                    <p><strong><?php _e('Titular:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('holder_name')); ?></p>
                    <p><strong><?php _e('CPF/CNPJ:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('holder_document')); ?></p>
                </div>
            </div>
            
            <!-- Ações -->
            <?php if ($closing->status === 'invoice_received'): ?>
            <div class="lrp-metabox" style="margin-top: 20px;">
                <div class="lrp-metabox-header"><?php _e('Ações', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content" style="text-align: center;">
                    <button type="button" class="button button-primary button-large lrp-approve-invoice" data-id="<?php echo esc_attr($closing->id); ?>" style="margin-right: 10px;">
                        <?php _e('Aprovar NF', 'lab-resumos-parceiros'); ?>
                    </button>
                    <button type="button" class="button button-large lrp-reject-invoice" data-id="<?php echo esc_attr($closing->id); ?>" style="color: #a00;">
                        <?php _e('Rejeitar NF', 'lab-resumos-parceiros'); ?>
                    </button>
                </div>
            </div>
            <?php elseif ($closing->status === 'awaiting_rpa'): ?>
            <div class="lrp-metabox" style="margin-top: 20px;">
                <div class="lrp-metabox-header"><?php _e('Ações - RPA', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content" style="text-align: center;">
                    <p style="margin-bottom: 15px; color: #0c5460;"><?php _e('Emita o RPA fora do sistema e depois clique em aprovar para liberar o pagamento.', 'lab-resumos-parceiros'); ?></p>
                    <button type="button" class="button button-primary button-large lrp-approve-rpa" data-id="<?php echo esc_attr($closing->id); ?>">
                        <?php _e('RPA Emitido - Aprovar', 'lab-resumos-parceiros'); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
