<?php
/**
 * Dashboard do Afiliado - Tab: Financeiro
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 * - $closings (array)
 * - $pending_closing (object|null)
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = LRP_Settings::instance();
$minimum_payout = $settings->get('minimum_payout', 200);
$is_rpa = $affiliate->is_rpa();
$allow_defer = $settings->get('allow_affiliate_defer', true);
?>

<div class="lrp-dashboard-financeiro">
    <h2><?php _e('Financeiro', 'lab-resumos-parceiros'); ?></h2>
    
    <!-- Saldo atual -->
    <div class="lrp-balance-section">
        <div class="lrp-balance-card">
            <div class="lrp-balance-header">
                <h3><?php _e('Saldo Disponível', 'lab-resumos-parceiros'); ?></h3>
            </div>
            <div class="lrp-balance-value">
                R$ <?php echo number_format($affiliate->get_data('current_balance'), 2, ',', '.'); ?>
            </div>
            <div class="lrp-balance-info">
                <?php if ($is_rpa): ?>
                    <?php printf(
                        __('Mínimo para pagamento via RPA: R$ %s', 'lab-resumos-parceiros'),
                        number_format($minimum_payout, 2, ',', '.')
                    ); ?>
                <?php else: ?>
                    <?php _e('Sem valor mínimo para pagamento via NF', 'lab-resumos-parceiros'); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="lrp-balance-summary">
            <div class="lrp-summary-item">
                <span class="lrp-summary-label"><?php _e('Total em comissões:', 'lab-resumos-parceiros'); ?></span>
                <span class="lrp-summary-value">R$ <?php echo number_format($affiliate->get_data('total_commissions'), 2, ',', '.'); ?></span>
            </div>
            <div class="lrp-summary-item">
                <span class="lrp-summary-label"><?php _e('Total recebido:', 'lab-resumos-parceiros'); ?></span>
                <span class="lrp-summary-value">R$ <?php echo number_format($affiliate->get_data('total_paid'), 2, ',', '.'); ?></span>
            </div>
            <div class="lrp-summary-item">
                <span class="lrp-summary-label"><?php _e('Tipo de Recebimento:', 'lab-resumos-parceiros'); ?></span>
                <span class="lrp-summary-value">
                    <?php echo $is_rpa ? __('RPA (Pessoa Física)', 'lab-resumos-parceiros') : __('Nota Fiscal (PJ)', 'lab-resumos-parceiros'); ?>
                </span>
            </div>
            <div class="lrp-summary-item">
                <span class="lrp-summary-label"><?php _e('Período de Pagamento:', 'lab-resumos-parceiros'); ?></span>
                <span class="lrp-summary-value"><?php echo esc_html($affiliate->get_payment_period_label()); ?></span>
            </div>
            <div class="lrp-summary-item">
                <span class="lrp-summary-label"><?php _e('Próximo Fechamento:', 'lab-resumos-parceiros'); ?></span>
                <span class="lrp-summary-value"><?php echo esc_html($affiliate->get_next_payment_date_formatted()); ?></span>
            </div>
        </div>
    </div>
    
    <?php if (!$is_rpa): ?>
    <!-- ============================================= -->
    <!-- DADOS PARA EMISSÃO DE NF (sempre visível PJ) -->
    <!-- ============================================= -->
    <div class="lrp-nf-reference-data">
        <h3><?php _e('Dados para Emissão de Nota Fiscal', 'lab-resumos-parceiros'); ?></h3>
        <p class="lrp-help-text"><?php _e('Use estes dados ao emitir sua NF de serviços. Você pode pré-cadastrar o tomador no sistema da sua prefeitura.', 'lab-resumos-parceiros'); ?></p>
        
        <div class="lrp-company-data">
            <p><strong><?php _e('Tomador:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($settings->get('company_name')); ?></p>
            <p><strong><?php _e('CNPJ:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($settings->get('company_cnpj')); ?></p>
            <p><strong><?php _e('Endereço:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($settings->get('company_address')); ?></p>
            <p><strong><?php _e('Descrição do Serviço:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($settings->get('nf_service_description', 'Serviços de divulgação e indicação comercial')); ?></p>
        </div>
        
        <?php 
        $nf_instructions_ref = $settings->get('nf_instructions');
        if (!empty($nf_instructions_ref)): 
        ?>
        <div class="lrp-nf-instructions">
            <h4><?php _e('Como emitir sua Nota Fiscal:', 'lab-resumos-parceiros'); ?></h4>
            <div class="lrp-nf-instructions-content">
                <?php echo wp_kses_post($nf_instructions_ref); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="lrp-nf-contact">
            <p>
                <strong><?php _e('Dúvidas sobre emissão de NF?', 'lab-resumos-parceiros'); ?></strong> 
                <?php 
                $nf_contact_ref = $settings->get('nf_contact_email', 'financeiro@labresumos.com.br');
                printf(
                    __('Entre em contato: %s', 'lab-resumos-parceiros'),
                    '<a href="mailto:' . esc_attr($nf_contact_ref) . '">' . esc_html($nf_contact_ref) . '</a>'
                ); 
                ?>
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($is_rpa): ?>
    <!-- ================================ -->
    <!-- SEÇÃO PARA RPA (PESSOA FÍSICA)  -->
    <!-- ================================ -->
    
    <!-- Fechamento pendente RPA (aguardando emissão de RPA pela empresa) -->
    <?php if ($pending_closing && $pending_closing->status === 'awaiting_rpa'): ?>
    <div class="lrp-alert lrp-alert-info">
        <h3>📋 <?php _e('Aguardando Emissão de RPA', 'lab-resumos-parceiros'); ?></h3>
        
        <p><?php printf(
            __('Seu fechamento de %s/%d está pronto. A empresa irá emitir o RPA (Recibo de Pagamento Autônomo) para pagamento.', 'lab-resumos-parceiros'),
            sprintf('%02d', $pending_closing->period_month),
            $pending_closing->period_year
        ); ?></p>
        
        <p><strong><?php _e('Valor:', 'lab-resumos-parceiros'); ?></strong> R$ <?php echo number_format($pending_closing->total_commissions, 2, ',', '.'); ?></p>
        
        <div class="lrp-rpa-info">
            <h4><?php _e('Seus dados cadastrados para RPA:', 'lab-resumos-parceiros'); ?></h4>
            <?php $rpa_data = $affiliate->get_rpa_data(); ?>
            <div class="lrp-rpa-data">
                <p><strong><?php _e('Nome:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($rpa_data['nome_completo']); ?></p>
                <p><strong><?php _e('CPF:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($rpa_data['cpf_formatted']); ?></p>
                <p><strong><?php _e('Endereço:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($rpa_data['endereco']); ?></p>
                <p><strong><?php _e('Telefone:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($rpa_data['telefone']); ?></p>
                <p><strong><?php _e('Email:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($rpa_data['email']); ?></p>
                <?php if (!empty($rpa_data['inss_number'])): ?>
                <p><strong><?php _e('INSS:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($rpa_data['inss_number']); ?></p>
                <?php endif; ?>
            </div>
            
            <p class="lrp-help-text">
                <?php _e('Caso algum dado esteja incorreto, atualize no seu perfil antes do pagamento.', 'lab-resumos-parceiros'); ?>
                <a href="?tab=perfil"><?php _e('Atualizar Perfil', 'lab-resumos-parceiros'); ?></a>
            </p>
        </div>
        
        <?php if ($allow_defer && !$pending_closing->deferred): ?>
        <div class="lrp-defer-section">
            <p><?php echo esc_html($settings->get('defer_message', __('Você pode adiar o recebimento para o próximo período. O saldo será acumulado.', 'lab-resumos-parceiros'))); ?></p>
            <button type="button" class="lrp-btn lrp-btn-secondary lrp-btn-sm" onclick="lrpDeferClosing(<?php echo (int) $pending_closing->id; ?>)">
                ⏭️ <?php _e('Adiar para Próximo Período', 'lab-resumos-parceiros'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- RPA em processamento -->
    <?php if ($pending_closing && $pending_closing->status === 'approved'): ?>
    <div class="lrp-alert lrp-alert-success">
        <h3>✅ <?php _e('RPA Emitido - Pagamento em Processamento', 'lab-resumos-parceiros'); ?></h3>
        <p><?php _e('O RPA foi emitido! O pagamento será realizado via PIX em até 5 dias úteis.', 'lab-resumos-parceiros'); ?></p>
        <p><strong><?php _e('Valor:', 'lab-resumos-parceiros'); ?></strong> R$ <?php echo number_format($pending_closing->total_commissions, 2, ',', '.'); ?></p>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <!-- ================================ -->
    <!-- SEÇÃO PARA PJ (NOTA FISCAL)     -->
    <!-- ================================ -->
    
    <!-- Fechamento pendente (aguardando NF) -->
    <?php if ($pending_closing && $pending_closing->status === 'awaiting_invoice'): ?>
    <div class="lrp-alert lrp-alert-warning">
        <h3>📄 <?php _e('Envio de Nota Fiscal Pendente', 'lab-resumos-parceiros'); ?></h3>
        
        <p><?php printf(
            __('Você tem um fechamento de %s/%d aguardando envio de NF.', 'lab-resumos-parceiros'),
            sprintf('%02d', $pending_closing->period_month),
            $pending_closing->period_year
        ); ?></p>
        
        <p><strong><?php _e('Valor:', 'lab-resumos-parceiros'); ?></strong> R$ <?php echo number_format($pending_closing->total_commissions, 2, ',', '.'); ?></p>
        
        <div class="lrp-invoice-upload">
            <h4><?php _e('Dados para emissão da NF:', 'lab-resumos-parceiros'); ?></h4>
            <div class="lrp-company-data">
                <p><strong><?php _e('Tomador:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($settings->get('company_name')); ?></p>
                <p><strong><?php _e('CNPJ:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($settings->get('company_cnpj')); ?></p>
                <p><strong><?php _e('Endereço:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($settings->get('company_address')); ?></p>
                <p><strong><?php _e('Descrição do Serviço:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($settings->get('nf_service_description', 'Serviços de divulgação e indicação comercial')); ?></p>
            </div>
            
            <?php 
            $nf_instructions = $settings->get('nf_instructions');
            if (!empty($nf_instructions)): 
            ?>
            <div class="lrp-nf-instructions">
                <h4><?php _e('Como emitir sua Nota Fiscal:', 'lab-resumos-parceiros'); ?></h4>
                <div class="lrp-nf-instructions-content">
                    <?php echo wp_kses_post($nf_instructions); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="lrp-nf-contact">
                <p>
                    <strong><?php _e('Dúvidas sobre emissão de NF?', 'lab-resumos-parceiros'); ?></strong> 
                    <?php 
                    $nf_contact_email = $settings->get('nf_contact_email', 'financeiro@labresumos.com.br');
                    printf(
                        __('Entre em contato: %s', 'lab-resumos-parceiros'),
                        '<a href="mailto:' . esc_attr($nf_contact_email) . '">' . esc_html($nf_contact_email) . '</a>'
                    ); 
                    ?>
                </p>
            </div>
            
            <form method="post" enctype="multipart/form-data" class="lrp-upload-form" id="lrp-invoice-form">
                <?php wp_nonce_field('lrp_upload_invoice_' . $pending_closing->id, '_wpnonce'); ?>
                <input type="hidden" name="action" value="lrp_upload_invoice">
                <input type="hidden" name="closing_id" value="<?php echo (int) $pending_closing->id; ?>">
                
                <div class="lrp-form-group">
                    <label for="invoice_number"><?php _e('Número da NF:', 'lab-resumos-parceiros'); ?></label>
                    <input type="text" name="invoice_number" id="invoice_number" required>
                </div>
                
                <div class="lrp-form-group">
                    <label for="invoice_file"><?php _e('Arquivo da NF (PDF):', 'lab-resumos-parceiros'); ?></label>
                    <input type="file" name="invoice_file" id="invoice_file" accept=".pdf" required>
                    <small><?php _e('Apenas arquivos PDF, máximo 5MB', 'lab-resumos-parceiros'); ?></small>
                </div>
                
                <div class="lrp-form-actions-row">
                    <button type="submit" class="lrp-btn lrp-btn-primary">
                        📤 <?php _e('Enviar Nota Fiscal', 'lab-resumos-parceiros'); ?>
                    </button>
                    
                    <?php if ($allow_defer && !$pending_closing->deferred): ?>
                    <button type="button" class="lrp-btn lrp-btn-secondary" onclick="lrpDeferClosing(<?php echo (int) $pending_closing->id; ?>)">
                        ⏭️ <?php _e('Adiar para Próximo Período', 'lab-resumos-parceiros'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- NF rejeitada -->
    <?php if ($pending_closing && $pending_closing->status === 'rejected'): ?>
    <div class="lrp-alert lrp-alert-danger">
        <h3><?php _e('Nota Fiscal Rejeitada', 'lab-resumos-parceiros'); ?></h3>
        
        <p><?php _e('Sua NF precisa de correção. Por favor, emita uma nova.', 'lab-resumos-parceiros'); ?></p>
        
        <p><strong><?php _e('Motivo:', 'lab-resumos-parceiros'); ?></strong></p>
        <blockquote><?php echo esc_html($pending_closing->rejection_reason); ?></blockquote>
        
        <div class="lrp-company-data">
            <p><strong><?php _e('Tomador:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($settings->get('company_name')); ?></p>
            <p><strong><?php _e('CNPJ:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($settings->get('company_cnpj')); ?></p>
            <p><strong><?php _e('Endereço:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($settings->get('company_address')); ?></p>
            <p><strong><?php _e('Descrição do Serviço:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($settings->get('nf_service_description', 'Serviços de divulgação e indicação comercial')); ?></p>
        </div>
        
        <div class="lrp-nf-contact">
            <p>
                <strong><?php _e('Dúvidas?', 'lab-resumos-parceiros'); ?></strong> 
                <?php 
                $nf_contact_email = $settings->get('nf_contact_email', 'financeiro@labresumos.com.br');
                printf(
                    __('Entre em contato: %s', 'lab-resumos-parceiros'),
                    '<a href="mailto:' . esc_attr($nf_contact_email) . '">' . esc_html($nf_contact_email) . '</a>'
                ); 
                ?>
            </p>
        </div>
        
        <!-- Formulário de upload -->
        <form method="post" enctype="multipart/form-data" class="lrp-upload-form">
            <?php wp_nonce_field('lrp_upload_invoice_' . $pending_closing->id, '_wpnonce'); ?>
            <input type="hidden" name="action" value="lrp_upload_invoice">
            <input type="hidden" name="closing_id" value="<?php echo (int) $pending_closing->id; ?>">
            
            <div class="lrp-form-group">
                <label for="invoice_number"><?php _e('Número da NF:', 'lab-resumos-parceiros'); ?></label>
                <input type="text" name="invoice_number" id="invoice_number" required>
            </div>
            
            <div class="lrp-form-group">
                <label for="invoice_file"><?php _e('Arquivo da NF (PDF):', 'lab-resumos-parceiros'); ?></label>
                <input type="file" name="invoice_file" id="invoice_file" accept=".pdf" required>
            </div>
            
            <button type="submit" class="lrp-btn lrp-btn-primary">
                <?php _e('Enviar Nova NF', 'lab-resumos-parceiros'); ?>
            </button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- NF em análise -->
    <?php if ($pending_closing && $pending_closing->status === 'invoice_received'): ?>
    <div class="lrp-alert lrp-alert-info">
        <h3>⏳ <?php _e('NF em Análise', 'lab-resumos-parceiros'); ?></h3>
        <p><?php _e('Sua Nota Fiscal está sendo validada. Você será notificado por email assim que o pagamento for processado.', 'lab-resumos-parceiros'); ?></p>
        <p><strong><?php _e('NF Número:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($pending_closing->invoice_number); ?></p>
        <p><strong><?php _e('Valor:', 'lab-resumos-parceiros'); ?></strong> R$ <?php echo number_format($pending_closing->total_commissions, 2, ',', '.'); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- NF aprovada, aguardando pagamento -->
    <?php if ($pending_closing && $pending_closing->status === 'approved'): ?>
    <div class="lrp-alert lrp-alert-success">
        <h3>✅ <?php _e('NF Aprovada - Pagamento em Processamento', 'lab-resumos-parceiros'); ?></h3>
        <p><?php _e('Sua NF foi aprovada! O pagamento será realizado via PIX em até 5 dias úteis.', 'lab-resumos-parceiros'); ?></p>
        <p><strong><?php _e('Valor:', 'lab-resumos-parceiros'); ?></strong> R$ <?php echo number_format($pending_closing->total_commissions, 2, ',', '.'); ?></p>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
    
    <!-- Histórico de fechamentos -->
    <div class="lrp-closings-history">
        <h3><?php _e('Histórico de Fechamentos', 'lab-resumos-parceiros'); ?></h3>
        
        <?php if (!empty($closings)): ?>
        <div class="lrp-table-responsive">
            <table class="lrp-table lrp-table-striped">
                <thead>
                    <tr>
                        <th><?php _e('Período', 'lab-resumos-parceiros'); ?></th>
                        <th><?php _e('Vendas', 'lab-resumos-parceiros'); ?></th>
                        <th><?php _e('Valor', 'lab-resumos-parceiros'); ?></th>
                        <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                        <th><?php echo $is_rpa ? __('RPA', 'lab-resumos-parceiros') : __('NF', 'lab-resumos-parceiros'); ?></th>
                        <th><?php _e('Pago em', 'lab-resumos-parceiros'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($closings as $closing): ?>
                    <tr>
                        <td>
                            <?php echo sprintf('%02d/%d', $closing->period_month, $closing->period_year); ?>
                            <?php if (!empty($closing->deferred)): ?>
                                <span class="lrp-badge lrp-badge-deferred" title="<?php esc_attr_e('Adiado', 'lab-resumos-parceiros'); ?>">↪️</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int) $closing->total_sales; ?></td>
                        <td>R$ <?php echo number_format($closing->total_commissions, 2, ',', '.'); ?></td>
                        <td>
                            <span class="lrp-status lrp-status-<?php echo esc_attr($closing->status); ?>">
                                <?php echo LRP_Dashboard::get_closing_status_label($closing->status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($closing->invoice_number): ?>
                                <?php echo esc_html($closing->invoice_number); ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($closing->paid_at): ?>
                                <?php echo date('d/m/Y', strtotime($closing->paid_at)); ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="lrp-empty-text"><?php _e('Nenhum fechamento registrado ainda.', 'lab-resumos-parceiros'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Dados de pagamento -->
    <div class="lrp-payment-data">
        <h3><?php _e('Dados de Pagamento', 'lab-resumos-parceiros'); ?></h3>
        
        <div class="lrp-payment-info">
            <?php if ($affiliate->get_data('payment_method') === 'pix'): ?>
                <p><strong><?php _e('Método:', 'lab-resumos-parceiros'); ?></strong> PIX</p>
                <p><strong><?php _e('Tipo de Chave:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html(strtoupper($affiliate->get_data('pix_key_type'))); ?></p>
                <p><strong><?php _e('Chave PIX:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_masked_pix_key()); ?></p>
            <?php else: ?>
                <p><strong><?php _e('Método:', 'lab-resumos-parceiros'); ?></strong> <?php _e('Transferência Bancária', 'lab-resumos-parceiros'); ?></p>
                <p><strong><?php _e('Banco:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('bank_name')); ?></p>
                <p><strong><?php _e('Agência:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('bank_agency')); ?></p>
                <p><strong><?php _e('Conta:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('bank_account')); ?></p>
            <?php endif; ?>
            
            <p><strong><?php _e('Titular:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($affiliate->get_data('holder_name')); ?></p>
            
            <a href="?tab=perfil" class="lrp-btn lrp-btn-secondary lrp-btn-sm">
                <?php _e('Atualizar Dados', 'lab-resumos-parceiros'); ?>
            </a>
        </div>
    </div>
</div>

<?php if ($allow_defer): ?>
<script>
function lrpDeferClosing(closingId) {
    if (!confirm('<?php echo esc_js(__('Tem certeza que deseja adiar este fechamento para o próximo período? O saldo será acumulado.', 'lab-resumos-parceiros')); ?>')) {
        return;
    }
    
    var formData = new FormData();
    formData.append('action', 'lrp_defer_closing');
    formData.append('closing_id', closingId);
    formData.append('nonce', '<?php echo wp_create_nonce('lrp_defer_closing'); ?>');
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            alert(data.data.message);
            location.reload();
        } else {
            alert(data.data.message || '<?php echo esc_js(__('Erro ao adiar fechamento.', 'lab-resumos-parceiros')); ?>');
        }
    })
    .catch(function(error) {
        alert('<?php echo esc_js(__('Erro de conexão. Tente novamente.', 'lab-resumos-parceiros')); ?>');
    });
}
</script>
<?php endif; ?>