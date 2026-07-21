<?php
/**
 * Confirmar Pagamento - Área do Contador
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
        <?php _e('Confirmar Pagamento', 'lab-resumos-parceiros'); ?> — 
        <?php echo esc_html($affiliate->get_display_name()); ?>
        (<?php echo esc_html($period); ?>)
    </h1>
    
    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-accountant-payments')); ?>" class="button">
            &larr; <?php _e('Voltar para lista', 'lab-resumos-parceiros'); ?>
        </a>
    </p>
    
    <div class="lrp-two-columns">
        <div>
            <!-- Resumo do Pagamento -->
            <div class="lrp-metabox">
                <div class="lrp-metabox-header"><?php _e('Resumo do Pagamento', 'lab-resumos-parceiros'); ?></div>
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
                            <strong style="font-size: 28px;">R$ <?php echo esc_html(number_format($final_amount, 2, ',', '.')); ?></strong>
                            <span><?php _e('Total a Pagar', 'lab-resumos-parceiros'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- NF do Parceiro -->
            <?php if ($invoice_url): ?>
            <div class="lrp-metabox" style="margin-top: 20px;">
                <div class="lrp-metabox-header"><?php _e('Nota Fiscal', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content">
                    <p><strong><?php _e('Número:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($closing->invoice_number ?: __('Não informado', 'lab-resumos-parceiros')); ?></p>
                    <a href="<?php echo esc_url($invoice_url); ?>" target="_blank" class="button">
                        <?php _e('Abrir NF (PDF)', 'lab-resumos-parceiros'); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Formulário de Confirmação -->
            <div class="lrp-metabox" style="margin-top: 20px;">
                <div class="lrp-metabox-header"><?php _e('Confirmar Pagamento Realizado', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content">
                    <form id="lrp-confirm-payment-form" enctype="multipart/form-data">
                        <input type="hidden" name="closing_id" value="<?php echo esc_attr($closing->id); ?>">
                        <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('lrp_confirm_payment_' . $closing->id); ?>">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="proof_file"><?php _e('Comprovante de Pagamento *', 'lab-resumos-parceiros'); ?></label>
                                </th>
                                <td>
                                    <input type="file" name="proof_file" id="proof_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                    <p class="description"><?php _e('PDF, JPG ou PNG. Máximo 5MB.', 'lab-resumos-parceiros'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="payment_notes"><?php _e('Observações', 'lab-resumos-parceiros'); ?></label>
                                </th>
                                <td>
                                    <textarea name="notes" id="payment_notes" rows="3" class="large-text" placeholder="<?php esc_attr_e('Observações sobre o pagamento (opcional)', 'lab-resumos-parceiros'); ?>"></textarea>
                                </td>
                            </tr>
                        </table>
                        
                        <div style="text-align: center; padding: 20px 0 10px;">
                            <button type="submit" id="lrp-confirm-payment-btn" class="button button-primary button-hero">
                                <?php _e('Confirmar Pagamento', 'lab-resumos-parceiros'); ?>
                            </button>
                        </div>
                    </form>
                    
                    <div id="lrp-payment-result" style="display: none; margin-top: 15px;"></div>
                </div>
            </div>
        </div>
        
        <div>
            <!-- Dados de Pagamento do Parceiro -->
            <div class="lrp-payment-data">
                <h4><?php _e('Dados de Pagamento do Parceiro', 'lab-resumos-parceiros'); ?></h4>
                
                <?php if ($affiliate->get_data('payment_method') === 'pix'): ?>
                    <p><strong><?php _e('Método:', 'lab-resumos-parceiros'); ?></strong> PIX</p>
                    <p><strong><?php _e('Tipo de chave:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html(strtoupper($affiliate->get_data('pix_key_type'))); ?></p>
                    <p style="background: #f0f7ff; padding: 10px; border-radius: 4px; font-size: 16px; word-break: break-all;">
                        <strong><?php _e('Chave PIX:', 'lab-resumos-parceiros'); ?></strong><br>
                        <?php echo esc_html($affiliate->get_decrypted_pix_key()); ?>
                    </p>
                <?php else: ?>
                    <p><strong><?php _e('Método:', 'lab-resumos-parceiros'); ?></strong> <?php _e('Transferência Bancária', 'lab-resumos-parceiros'); ?></p>
                    <p><strong><?php _e('Banco:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('bank_name')); ?></p>
                    <p><strong><?php _e('Agência:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('bank_agency')); ?></p>
                    <p><strong><?php _e('Conta:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('bank_account')); ?></p>
                    <p><strong><?php _e('Tipo:', 'lab-resumos-parceiros'); ?></strong> <?php echo $affiliate->get_data('bank_account_type') === 'savings' ? __('Poupança', 'lab-resumos-parceiros') : __('Corrente', 'lab-resumos-parceiros'); ?></p>
                <?php endif; ?>
                
                <hr>
                <p><strong><?php _e('Titular:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('holder_name')); ?></p>
                <p><strong><?php _e('CPF/CNPJ:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('holder_document')); ?></p>
            </div>
            
            <!-- Dados do Parceiro -->
            <div class="lrp-metabox" style="margin-top: 20px;">
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
                    <?php if ($is_rpa): ?>
                        <p><strong><?php _e('CPF:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_cpf_formatted()); ?></p>
                    <?php else: ?>
                        <p><strong><?php _e('CNPJ:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_company_cnpj_formatted()); ?></p>
                        <p><strong><?php _e('Razão Social:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_company_name()); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#lrp-confirm-payment-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $('#lrp-confirm-payment-btn');
        var $result = $('#lrp-payment-result');
        var fileInput = document.getElementById('proof_file');
        
        if (!fileInput.files.length) {
            alert('<?php echo esc_js(__('Selecione o comprovante de pagamento.', 'lab-resumos-parceiros')); ?>');
            return;
        }
        
        // Valida tamanho (5MB)
        if (fileInput.files[0].size > 5 * 1024 * 1024) {
            alert('<?php echo esc_js(__('Arquivo muito grande. Máximo: 5MB.', 'lab-resumos-parceiros')); ?>');
            return;
        }
        
        var amount = '<?php echo esc_js('R$ ' . number_format($final_amount, 2, ',', '.')); ?>';
        if (!confirm('<?php echo esc_js(__('Confirmar pagamento de', 'lab-resumos-parceiros')); ?> ' + amount + ' <?php echo esc_js(__('para', 'lab-resumos-parceiros')); ?> <?php echo esc_js($affiliate->get_display_name()); ?>?')) {
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'lrp_confirm_payment');
        formData.append('nonce', lrp_admin.nonce);
        formData.append('closing_id', $form.find('[name="closing_id"]').val());
        formData.append('_wpnonce', $form.find('[name="_wpnonce"]').val());
        formData.append('notes', $form.find('[name="notes"]').val());
        formData.append('proof_file', fileInput.files[0]);
        
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Processando...', 'lab-resumos-parceiros')); ?>');
        $result.hide();
        
        $.ajax({
            url: lrp_admin.ajax_url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $result.show().html(
                        '<div class="lrp-admin-notice success"><strong><?php echo esc_js(__('Pagamento confirmado com sucesso!', 'lab-resumos-parceiros')); ?></strong> <?php echo esc_js(__('Redirecionando...', 'lab-resumos-parceiros')); ?></div>'
                    );
                    setTimeout(function() {
                        window.location.href = '<?php echo esc_js(admin_url('admin.php?page=lrp-accountant-payments')); ?>';
                    }, 1500);
                } else {
                    $result.show().html(
                        '<div class="lrp-admin-notice warning"><strong><?php echo esc_js(__('Erro:', 'lab-resumos-parceiros')); ?></strong> ' + response.data.message + '</div>'
                    );
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Confirmar Pagamento', 'lab-resumos-parceiros')); ?>');
                }
            },
            error: function() {
                $result.show().html(
                    '<div class="lrp-admin-notice warning"><strong><?php echo esc_js(__('Erro de conexão. Tente novamente.', 'lab-resumos-parceiros')); ?></strong></div>'
                );
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Confirmar Pagamento', 'lab-resumos-parceiros')); ?>');
            }
        });
    });
});
</script>
