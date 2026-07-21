<?php
/**
 * Dashboard do Afiliado - Tab: Perfil
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 * - $user (WP_User)
 */

if (!defined('ABSPATH')) {
    exit;
}

$user = get_userdata($affiliate->get_user_id());
$billing_type = $affiliate->get_billing_type();
?>

<div class="lrp-dashboard-perfil">
    <h2><?php _e('Meu Perfil', 'lab-resumos-parceiros'); ?></h2>
    
    <?php if (isset($_GET['updated'])): ?>
    <div class="lrp-alert lrp-alert-success">
        <?php _e('Perfil atualizado com sucesso!', 'lab-resumos-parceiros'); ?>
    </div>
    <?php endif; ?>
    
    <form method="post" class="lrp-form" id="lrp-profile-form">
        <?php wp_nonce_field('lrp_update_profile', '_wpnonce'); ?>
        <input type="hidden" name="action" value="lrp_update_profile">
        
        <!-- Dados Pessoais -->
        <div class="lrp-form-section">
            <h3><?php _e('Dados Pessoais', 'lab-resumos-parceiros'); ?></h3>
            
            <div class="lrp-form-group">
                <label for="user_email"><?php _e('Email', 'lab-resumos-parceiros'); ?></label>
                <input type="email" name="user_email" id="user_email" 
                       value="<?php echo esc_attr($user->user_email); ?>" required>
            </div>
        </div>
        
        <!-- Dados de Identificação (sempre obrigatórios) -->
        <div class="lrp-form-section">
            <h3><?php _e('Dados de Identificação', 'lab-resumos-parceiros'); ?></h3>
            <p class="lrp-help-text"><?php _e('Estes dados são obrigatórios para emissão de documentos fiscais.', 'lab-resumos-parceiros'); ?></p>
            
            <div class="lrp-form-row">
                <div class="lrp-form-group">
                    <label for="first_name"><?php _e('Nome', 'lab-resumos-parceiros'); ?> *</label>
                    <input type="text" name="first_name" id="first_name" 
                           value="<?php echo esc_attr($affiliate->get_first_name()); ?>" required>
                </div>
                
                <div class="lrp-form-group">
                    <label for="last_name"><?php _e('Sobrenome', 'lab-resumos-parceiros'); ?> *</label>
                    <input type="text" name="last_name" id="last_name" 
                           value="<?php echo esc_attr($affiliate->get_last_name()); ?>" required>
                </div>
            </div>
            
            <div class="lrp-form-group">
                <label for="cpf"><?php _e('CPF', 'lab-resumos-parceiros'); ?> *</label>
                <input type="text" name="cpf" id="cpf" class="lrp-cpf-mask"
                       value="<?php echo esc_attr($affiliate->get_cpf_formatted()); ?>" required>
            </div>
        </div>
        
        <!-- Tipo de Recebimento -->
        <div class="lrp-form-section">
            <h3><?php _e('Tipo de Recebimento', 'lab-resumos-parceiros'); ?></h3>
            <p class="lrp-help-text"><?php _e('Escolha como você deseja receber suas comissões.', 'lab-resumos-parceiros'); ?></p>
            
            <div class="lrp-billing-type-selector">
                <label class="lrp-billing-type-option <?php echo $billing_type === 'pj' ? 'selected' : ''; ?>">
                    <input type="radio" name="billing_type" value="pj" <?php checked($billing_type, 'pj'); ?>>
                    <div class="lrp-billing-type-card">
                        <span class="lrp-billing-type-icon">🏢</span>
                        <span class="lrp-billing-type-title"><?php _e('Pessoa Jurídica (PJ)', 'lab-resumos-parceiros'); ?></span>
                        <span class="lrp-billing-type-desc"><?php _e('Tenho CNPJ e emito Nota Fiscal', 'lab-resumos-parceiros'); ?></span>
                    </div>
                </label>
                
                <label class="lrp-billing-type-option <?php echo $billing_type === 'rpa' ? 'selected' : ''; ?>">
                    <input type="radio" name="billing_type" value="rpa" <?php checked($billing_type, 'rpa'); ?>>
                    <div class="lrp-billing-type-card">
                        <span class="lrp-billing-type-icon">👤</span>
                        <span class="lrp-billing-type-title"><?php _e('Pessoa Física (RPA)', 'lab-resumos-parceiros'); ?></span>
                        <span class="lrp-billing-type-desc"><?php _e('Recebo via RPA (Recibo de Pagamento Autônomo)', 'lab-resumos-parceiros'); ?></span>
                    </div>
                </label>
            </div>
        </div>
        
        <!-- Dados PJ -->
        <div class="lrp-form-section lrp-billing-pj-fields" style="<?php echo $billing_type !== 'pj' ? 'display:none;' : ''; ?>">
            <h3><?php _e('Dados da Empresa', 'lab-resumos-parceiros'); ?></h3>
            
            <div class="lrp-form-row">
                <div class="lrp-form-group">
                    <label for="company_cnpj"><?php _e('CNPJ da Empresa', 'lab-resumos-parceiros'); ?></label>
                    <input type="text" name="company_cnpj" id="company_cnpj" class="lrp-cnpj-mask"
                           value="<?php echo esc_attr($affiliate->get_company_cnpj_formatted()); ?>">
                </div>
                
                <div class="lrp-form-group">
                    <label for="company_name"><?php _e('Razão Social', 'lab-resumos-parceiros'); ?></label>
                    <input type="text" name="company_name" id="company_name" 
                           value="<?php echo esc_attr($affiliate->get_company_name()); ?>">
                </div>
            </div>
        </div>
        
        <!-- Dados RPA -->
        <div class="lrp-form-section lrp-billing-rpa-fields" style="<?php echo $billing_type !== 'rpa' ? 'display:none;' : ''; ?>">
            <h3><?php _e('Dados Adicionais para RPA', 'lab-resumos-parceiros'); ?></h3>
            <p class="lrp-help-text"><?php _e('Estes dados serão usados para emissão do RPA (Recibo de Pagamento Autônomo).', 'lab-resumos-parceiros'); ?></p>
            
            <div class="lrp-form-row">
                <div class="lrp-form-group">
                    <label for="phone"><?php _e('Telefone', 'lab-resumos-parceiros'); ?></label>
                    <input type="text" name="phone" id="phone" class="lrp-phone-mask"
                           value="<?php echo esc_attr($affiliate->get_phone()); ?>">
                </div>
            </div>
            
            <div class="lrp-form-group">
                <label for="full_address"><?php _e('Endereço Completo', 'lab-resumos-parceiros'); ?></label>
                <textarea name="full_address" id="full_address" rows="2"
                          placeholder="<?php esc_attr_e('Rua, número, complemento, bairro, cidade, estado, CEP', 'lab-resumos-parceiros'); ?>"><?php echo esc_textarea($affiliate->get_full_address()); ?></textarea>
            </div>
            
            <div class="lrp-form-group">
                <label for="birth_date"><?php _e('Data de Nascimento', 'lab-resumos-parceiros'); ?></label>
                <input type="date" name="birth_date" id="birth_date"
                       value="<?php echo esc_attr($affiliate->get_data('birth_date') ?? ''); ?>">
            </div>

            <div class="lrp-form-group">
                <label for="inss_number"><?php _e('Número de Inscrição no INSS (PIS/PASEP)', 'lab-resumos-parceiros'); ?></label>
                <input type="text" name="inss_number" id="inss_number" 
                       value="<?php echo esc_attr($affiliate->get_inss_number()); ?>"
                       placeholder="<?php esc_attr_e('Opcional', 'lab-resumos-parceiros'); ?>">
                <small><?php _e('Número do PIS/PASEP para emissão do RPA.', 'lab-resumos-parceiros'); ?></small>
            </div>
        </div>
        
        <!-- Dados de Pagamento -->
        <div class="lrp-form-section">
            <h3><?php _e('Dados de Pagamento', 'lab-resumos-parceiros'); ?></h3>
            
            <div class="lrp-form-group">
                <label><?php _e('Método de Pagamento', 'lab-resumos-parceiros'); ?></label>
                <div class="lrp-radio-group">
                    <label class="lrp-radio">
                        <input type="radio" name="payment_method" value="pix" 
                               <?php checked($affiliate->get_data('payment_method'), 'pix'); ?>>
                        <span>PIX</span>
                    </label>
                    <label class="lrp-radio">
                        <input type="radio" name="payment_method" value="bank_transfer" 
                               <?php checked($affiliate->get_data('payment_method'), 'bank_transfer'); ?>>
                        <span><?php _e('Transferência Bancária', 'lab-resumos-parceiros'); ?></span>
                    </label>
                </div>
            </div>
            
            <!-- PIX -->
            <div class="lrp-payment-fields lrp-pix-fields" 
                 style="<?php echo $affiliate->get_data('payment_method') !== 'pix' ? 'display:none' : ''; ?>">
                 
                <div class="lrp-form-row">
                    <div class="lrp-form-group">
                        <label for="pix_key_type"><?php _e('Tipo de Chave PIX', 'lab-resumos-parceiros'); ?></label>
                        <select name="pix_key_type" id="pix_key_type">
                            <option value="cpf" <?php selected($affiliate->get_data('pix_key_type'), 'cpf'); ?>>CPF</option>
                            <option value="cnpj" <?php selected($affiliate->get_data('pix_key_type'), 'cnpj'); ?>>CNPJ</option>
                            <option value="email" <?php selected($affiliate->get_data('pix_key_type'), 'email'); ?>>Email</option>
                            <option value="phone" <?php selected($affiliate->get_data('pix_key_type'), 'phone'); ?>><?php _e('Telefone', 'lab-resumos-parceiros'); ?></option>
                            <option value="random" <?php selected($affiliate->get_data('pix_key_type'), 'random'); ?>><?php _e('Chave Aleatória', 'lab-resumos-parceiros'); ?></option>
                        </select>
                    </div>
                    
                    <div class="lrp-form-group">
                        <label for="pix_key"><?php _e('Chave PIX', 'lab-resumos-parceiros'); ?></label>
                        <input type="text" name="pix_key" id="pix_key" 
                               value="<?php echo esc_attr($affiliate->get_masked_pix_key()); ?>"
                               placeholder="<?php _e('Digite sua chave PIX', 'lab-resumos-parceiros'); ?>">
                        <small><?php _e('Deixe em branco para manter a chave atual', 'lab-resumos-parceiros'); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- Transferência Bancária -->
            <div class="lrp-payment-fields lrp-bank-fields" 
                 style="<?php echo $affiliate->get_data('payment_method') !== 'bank_transfer' ? 'display:none' : ''; ?>">
                 
                <div class="lrp-form-row">
                    <div class="lrp-form-group">
                        <label for="bank_name"><?php _e('Banco', 'lab-resumos-parceiros'); ?></label>
                        <input type="text" name="bank_name" id="bank_name" 
                               value="<?php echo esc_attr($affiliate->get_data('bank_name')); ?>">
                    </div>
                    
                    <div class="lrp-form-group">
                        <label for="bank_agency"><?php _e('Agência', 'lab-resumos-parceiros'); ?></label>
                        <input type="text" name="bank_agency" id="bank_agency" 
                               value="<?php echo esc_attr($affiliate->get_data('bank_agency')); ?>">
                    </div>
                </div>
                
                <div class="lrp-form-row">
                    <div class="lrp-form-group">
                        <label for="bank_account"><?php _e('Número da Conta', 'lab-resumos-parceiros'); ?></label>
                        <input type="text" name="bank_account" id="bank_account" 
                               value="<?php echo esc_attr($affiliate->get_data('bank_account')); ?>">
                    </div>
                    
                    <div class="lrp-form-group">
                        <label for="bank_account_type"><?php _e('Tipo de Conta', 'lab-resumos-parceiros'); ?></label>
                        <select name="bank_account_type" id="bank_account_type">
                            <option value="checking" <?php selected($affiliate->get_data('bank_account_type'), 'checking'); ?>><?php _e('Corrente', 'lab-resumos-parceiros'); ?></option>
                            <option value="savings" <?php selected($affiliate->get_data('bank_account_type'), 'savings'); ?>><?php _e('Poupança', 'lab-resumos-parceiros'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Titular -->
            <div class="lrp-form-row">
                <div class="lrp-form-group">
                    <label for="holder_name"><?php _e('Nome do Titular', 'lab-resumos-parceiros'); ?></label>
                    <input type="text" name="holder_name" id="holder_name" 
                           value="<?php echo esc_attr($affiliate->get_data('holder_name')); ?>" required>
                </div>
                
                <div class="lrp-form-group">
                    <label for="holder_document"><?php _e('CPF/CNPJ do Titular', 'lab-resumos-parceiros'); ?></label>
                    <input type="text" name="holder_document" id="holder_document" 
                           value="<?php echo esc_attr($affiliate->get_data('holder_document')); ?>" required>
                </div>
            </div>
        </div>
        
        <!-- Informações da Conta -->
        <div class="lrp-form-section lrp-readonly-section">
            <h3><?php _e('Informações da Conta', 'lab-resumos-parceiros'); ?></h3>
            
            <div class="lrp-info-grid">
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Status:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-status lrp-status-<?php echo esc_attr($affiliate->get_data('status')); ?>">
                        <?php echo LRP_Dashboard::get_status_label($affiliate->get_data('status')); ?>
                    </span>
                </div>
                
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Tipo:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-info-value">
                        <?php echo $affiliate->is_pj() ? __('Pessoa Jurídica (NF)', 'lab-resumos-parceiros') : __('Pessoa Física (RPA)', 'lab-resumos-parceiros'); ?>
                    </span>
                </div>
                
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Cupom:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-info-value"><?php echo esc_html($affiliate->get_coupon_code()); ?></span>
                </div>
                
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Código de Referência:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-info-value"><?php echo esc_html($affiliate->get_referral_code()); ?></span>
                </div>
                
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Período de Pagamento:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-info-value"><?php echo esc_html($affiliate->get_payment_period_label()); ?></span>
                </div>
                
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Próximo Fechamento:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-info-value"><?php echo esc_html($affiliate->get_next_payment_date_formatted()); ?></span>
                </div>
                
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Membro desde:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-info-value"><?php echo date('d/m/Y', strtotime($affiliate->get_data('created_at'))); ?></span>
                </div>
                
                <?php if ($affiliate->get_sponsor_id()): 
                    $sponsor = new LRP_Affiliate($affiliate->get_sponsor_id());
                ?>
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Indicado por:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-info-value"><?php echo esc_html($sponsor->get_display_name()); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="lrp-form-actions">
            <button type="submit" class="lrp-btn lrp-btn-primary">
                <?php _e('Salvar Alterações', 'lab-resumos-parceiros'); ?>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle método de pagamento
    var paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    var pixFields = document.querySelector('.lrp-pix-fields');
    var bankFields = document.querySelector('.lrp-bank-fields');
    
    paymentRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.value === 'pix') {
                pixFields.style.display = 'block';
                bankFields.style.display = 'none';
            } else {
                pixFields.style.display = 'none';
                bankFields.style.display = 'block';
            }
        });
    });
    
    // Toggle tipo de faturamento
    var billingTypeInputs = document.querySelectorAll('input[name="billing_type"]');
    var pjFields = document.querySelector('.lrp-billing-pj-fields');
    var rpaFields = document.querySelector('.lrp-billing-rpa-fields');
    var billingOptions = document.querySelectorAll('.lrp-billing-type-option');
    
    function toggleBillingFields(billingType) {
        if (billingType === 'pj') {
            pjFields.style.display = 'block';
            rpaFields.style.display = 'none';
        } else {
            pjFields.style.display = 'none';
            rpaFields.style.display = 'block';
        }
        
        // Update selected class
        billingOptions.forEach(function(opt) {
            var input = opt.querySelector('input');
            if (input.value === billingType) {
                opt.classList.add('selected');
            } else {
                opt.classList.remove('selected');
            }
        });
    }
    
    billingTypeInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            toggleBillingFields(this.value);
        });
    });
});
</script>
