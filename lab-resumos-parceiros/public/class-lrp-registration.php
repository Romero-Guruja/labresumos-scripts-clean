<?php
/**
 * Formulário de Cadastro de Afiliado
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Registration
 * 
 * Renderiza e processa o formulário de cadastro.
 */
class LRP_Registration {

    /**
     * Sponsor (se veio de link de convite)
     *
     * @var LRP_Affiliate|null
     */
    private $sponsor = null;

    /**
     * Mensagens de erro/sucesso
     *
     * @var array
     */
    private $messages = [];

    /**
     * Construtor
     */
    public function __construct() {
        // Verifica sponsor
        if (isset($_GET['sponsor'])) {
            $sponsor_code = sanitize_text_field($_GET['sponsor']);
            $this->sponsor = LRP_Affiliate::get_by_referral_code($sponsor_code);
        }
        
        // Processa formulário
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lrp_registration_nonce'])) {
            $this->process_registration();
        }
    }

    /**
     * Renderiza formulário
     *
     * @return string
     */
    public function render() {
        ob_start();
        ?>
        <div class="lrp-registration">
            <div class="lrp-registration-header">
                <h2><?php _e('Seja um Parceiro Lab Resumos', 'lab-resumos-parceiros'); ?></h2>
            </div>
            
            <?php if ($this->sponsor): ?>
            <div class="lrp-sponsor-info">
                <p><?php printf(__('Você foi convidado por <strong>%s</strong>', 'lab-resumos-parceiros'), 
                    esc_html($this->sponsor->get_display_name())); ?></p>
            </div>
            <?php endif; ?>
            
            <?php $this->render_messages(); ?>
            
            <?php $this->render_form(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza formulário
     */
    private function render_form() {
        $is_logged_in = is_user_logged_in();
        $user = $is_logged_in ? wp_get_current_user() : null;
        $selected_billing_type = sanitize_key($_POST['billing_type'] ?? '');
        if (!in_array($selected_billing_type, ['pj', 'rpa'], true)) {
            $selected_billing_type = '';
        }
        ?>
        <form class="lrp-registration-form" method="post" id="lrp-registration-form">
            <?php wp_nonce_field('lrp_registration', 'lrp_registration_nonce'); ?>
            
            <?php if ($this->sponsor): ?>
                <input type="hidden" name="sponsor_id" value="<?php echo esc_attr($this->sponsor->get_id()); ?>">
            <?php endif; ?>
            
            <div class="lrp-form-section">
                <h4><?php _e('Seus Dados', 'lab-resumos-parceiros'); ?></h4>
                
                <?php if ($is_logged_in): ?>
                    <div class="lrp-form-group">
                        <label><?php _e('Email', 'lab-resumos-parceiros'); ?></label>
                        <input type="email" value="<?php echo esc_attr($user->user_email); ?>" readonly class="lrp-input">
                    </div>
                <?php else: ?>
                    <p class="lrp-info-box">
                        <?php _e('Já tem conta?', 'lab-resumos-parceiros'); ?> 
                        <a href="<?php echo esc_url(wp_login_url(add_query_arg(['sponsor' => $this->sponsor ? $this->sponsor->get_referral_code() : ''], get_permalink()))); ?>">
                            <?php _e('Faça login aqui', 'lab-resumos-parceiros'); ?>
                        </a>
                    </p>
                    
                    <div class="lrp-form-group">
                        <label><?php _e('Seu Email', 'lab-resumos-parceiros'); ?> *</label>
                        <input type="email" name="email" required class="lrp-input" 
                               placeholder="<?php esc_attr_e('seu@email.com', 'lab-resumos-parceiros'); ?>"
                               value="<?php echo esc_attr($_POST['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="lrp-form-group">
                        <label><?php _e('Crie uma Senha', 'lab-resumos-parceiros'); ?> *</label>
                        <input type="password" name="password" required class="lrp-input" minlength="6"
                               placeholder="<?php esc_attr_e('Mínimo 6 caracteres', 'lab-resumos-parceiros'); ?>">
                    </div>
                    
                    <div class="lrp-form-group">
                        <label><?php _e('Confirme a Senha', 'lab-resumos-parceiros'); ?> *</label>
                        <input type="password" name="password_confirm" required class="lrp-input" minlength="6">
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Dados de Identificação (sempre obrigatório) -->
            <div class="lrp-form-section">
                <h4><?php _e('Dados de Identificação', 'lab-resumos-parceiros'); ?></h4>
                <p class="lrp-help-text"><?php _e('Seus dados pessoais são obrigatórios para emissão de documentos fiscais.', 'lab-resumos-parceiros'); ?></p>
                
                <div class="lrp-form-row">
                    <div class="lrp-form-group lrp-form-group-half">
                        <label><?php _e('Nome', 'lab-resumos-parceiros'); ?> *</label>
                        <input type="text" name="first_name" required class="lrp-input" 
                               placeholder="<?php esc_attr_e('João', 'lab-resumos-parceiros'); ?>"
                               value="<?php echo esc_attr($_POST['first_name'] ?? ($user ? $user->first_name : '')); ?>">
                    </div>
                    
                    <div class="lrp-form-group lrp-form-group-half">
                        <label><?php _e('Sobrenome', 'lab-resumos-parceiros'); ?> *</label>
                        <input type="text" name="last_name" required class="lrp-input" 
                               placeholder="<?php esc_attr_e('da Silva', 'lab-resumos-parceiros'); ?>"
                               value="<?php echo esc_attr($_POST['last_name'] ?? ($user ? $user->last_name : '')); ?>">
                    </div>
                </div>
                
                <div class="lrp-form-group">
                    <label><?php _e('CPF', 'lab-resumos-parceiros'); ?> *</label>
                    <input type="text" name="cpf" required class="lrp-input lrp-cpf-mask" 
                           placeholder="<?php esc_attr_e('000.000.000-00', 'lab-resumos-parceiros'); ?>"
                           value="<?php echo esc_attr($_POST['cpf'] ?? ''); ?>">
                </div>
            </div>
            
            <!-- Seleção de Tipo de Faturamento -->
            <div class="lrp-form-section lrp-billing-type-section">
                <h4><?php _e('Como você deseja receber?', 'lab-resumos-parceiros'); ?> *</h4>
                <p class="lrp-help-text"><?php _e('Escolha a forma de recebimento das suas comissões. Esta escolha é obrigatória e define quais dados precisaremos a seguir.', 'lab-resumos-parceiros'); ?></p>
                
                <div class="lrp-billing-type-selector">
                    <label class="lrp-billing-type-option <?php echo $selected_billing_type === 'pj' ? 'selected' : ''; ?>">
                        <input type="radio" name="billing_type" value="pj" required <?php checked($selected_billing_type, 'pj'); ?>>
                        <div class="lrp-billing-type-card">
                            <span class="lrp-billing-type-icon">🏢</span>
                            <span class="lrp-billing-type-title"><?php _e('Pessoa Jurídica (PJ)', 'lab-resumos-parceiros'); ?></span>
                            <span class="lrp-billing-type-desc"><?php _e('Tenho CNPJ e emito Nota Fiscal', 'lab-resumos-parceiros'); ?></span>
                        </div>
                    </label>
                    
                    <label class="lrp-billing-type-option <?php echo $selected_billing_type === 'rpa' ? 'selected' : ''; ?>">
                        <input type="radio" name="billing_type" value="rpa" required <?php checked($selected_billing_type, 'rpa'); ?>>
                        <div class="lrp-billing-type-card">
                            <span class="lrp-billing-type-icon">👤</span>
                            <span class="lrp-billing-type-title"><?php _e('Pessoa Física (RPA)', 'lab-resumos-parceiros'); ?></span>
                            <span class="lrp-billing-type-desc"><?php _e('Recebo via RPA (Recibo de Pagamento Autônomo)', 'lab-resumos-parceiros'); ?></span>
                        </div>
                    </label>
                </div>
            </div>
            
            <!-- Dados PJ -->
            <div class="lrp-form-section lrp-billing-pj-fields" style="<?php echo $selected_billing_type === 'pj' ? '' : 'display:none;'; ?>">
                <h4><?php _e('Dados da Empresa', 'lab-resumos-parceiros'); ?></h4>
                
                <div class="lrp-form-group">
                    <label><?php _e('CNPJ da Empresa', 'lab-resumos-parceiros'); ?> *</label>
                    <input type="text" name="company_cnpj" class="lrp-input lrp-cnpj-mask lrp-pj-required" 
                           placeholder="<?php esc_attr_e('00.000.000/0000-00', 'lab-resumos-parceiros'); ?>"
                           value="<?php echo esc_attr($_POST['company_cnpj'] ?? ''); ?>">
                    <small><?php _e('Informe o CNPJ da empresa que emitirá as Notas Fiscais.', 'lab-resumos-parceiros'); ?></small>
                </div>
                
                <div class="lrp-form-group">
                    <label><?php _e('Razão Social', 'lab-resumos-parceiros'); ?> *</label>
                    <input type="text" name="company_name" class="lrp-input lrp-pj-required" 
                           placeholder="<?php esc_attr_e('Nome da empresa conforme CNPJ', 'lab-resumos-parceiros'); ?>"
                           value="<?php echo esc_attr($_POST['company_name'] ?? ''); ?>">
                </div>
            </div>
            
            <!-- Dados RPA -->
            <div class="lrp-form-section lrp-billing-rpa-fields" style="<?php echo $selected_billing_type === 'rpa' ? '' : 'display:none;'; ?>">
                <h4><?php _e('Dados Adicionais para RPA', 'lab-resumos-parceiros'); ?></h4>
                <p class="lrp-help-text"><?php _e('Estes dados serão usados para emissão do RPA (Recibo de Pagamento Autônomo).', 'lab-resumos-parceiros'); ?></p>
                
                <div class="lrp-form-group">
                    <label><?php _e('Endereço Completo', 'lab-resumos-parceiros'); ?> *</label>
                    <textarea name="full_address" class="lrp-input lrp-rpa-required" rows="2"
                              placeholder="<?php esc_attr_e('Rua, número, complemento, bairro, cidade, estado, CEP', 'lab-resumos-parceiros'); ?>"><?php echo esc_textarea($_POST['full_address'] ?? ''); ?></textarea>
                </div>
                
                <div class="lrp-form-group">
                    <label><?php _e('Telefone', 'lab-resumos-parceiros'); ?> *</label>
                    <input type="text" name="phone" class="lrp-input lrp-phone-mask lrp-rpa-required" 
                           placeholder="<?php esc_attr_e('(00) 00000-0000', 'lab-resumos-parceiros'); ?>"
                           value="<?php echo esc_attr($_POST['phone'] ?? ''); ?>">
                </div>
                
                <div class="lrp-form-group">
                    <label><?php _e('Data de Nascimento', 'lab-resumos-parceiros'); ?> *</label>
                    <input type="date" name="birth_date" class="lrp-input lrp-rpa-required"
                           value="<?php echo esc_attr($_POST['birth_date'] ?? ''); ?>">
                </div>

                <div class="lrp-form-group">
                    <label><?php _e('Número de Inscrição no INSS (PIS/PASEP)', 'lab-resumos-parceiros'); ?></label>
                    <input type="text" name="inss_number" class="lrp-input" 
                           placeholder="<?php esc_attr_e('Opcional - informe se possuir', 'lab-resumos-parceiros'); ?>"
                           value="<?php echo esc_attr($_POST['inss_number'] ?? ''); ?>">
                    <small><?php _e('Número do PIS/PASEP para emissão do RPA. Se não tiver, deixe em branco.', 'lab-resumos-parceiros'); ?></small>
                </div>
            </div>
            
            <div class="lrp-form-section">
                <h4><?php _e('Código do Cupom', 'lab-resumos-parceiros'); ?></h4>
                <p class="lrp-help-text"><?php _e('Escolha um código para seu cupom de desconto (opcional). Será gerado automaticamente se deixar em branco.', 'lab-resumos-parceiros'); ?></p>
                
                <div class="lrp-form-group">
                    <label><?php _e('Código desejado', 'lab-resumos-parceiros'); ?></label>
                    <input type="text" name="coupon_code" maxlength="20" pattern="[A-Za-z0-9]+" 
                           placeholder="<?php esc_attr_e('Ex: JOAO10', 'lab-resumos-parceiros'); ?>" class="lrp-input"
                           value="<?php echo esc_attr($_POST['coupon_code'] ?? ''); ?>">
                    <small><?php _e('Apenas letras e números, sem espaços.', 'lab-resumos-parceiros'); ?></small>
                </div>
            </div>
            
            <div class="lrp-form-section">
                <h4><?php _e('Dados para Pagamento via PIX', 'lab-resumos-parceiros'); ?></h4>
                <p class="lrp-help-text"><?php _e('Informe os dados bancários para recebimento das comissões.', 'lab-resumos-parceiros'); ?></p>
                
                <div class="lrp-form-group">
                    <label><?php _e('Tipo de Chave PIX', 'lab-resumos-parceiros'); ?> *</label>
                    <select name="pix_key_type" required class="lrp-input" id="lrp-pix-key-type">
                        <option value=""><?php _e('Selecione', 'lab-resumos-parceiros'); ?></option>
                        <option value="cpf" <?php selected($_POST['pix_key_type'] ?? '', 'cpf'); ?>>CPF</option>
                        <option value="cnpj" <?php selected($_POST['pix_key_type'] ?? '', 'cnpj'); ?>>CNPJ</option>
                        <option value="email" <?php selected($_POST['pix_key_type'] ?? '', 'email'); ?>>Email</option>
                        <option value="phone" <?php selected($_POST['pix_key_type'] ?? '', 'phone'); ?>><?php _e('Telefone', 'lab-resumos-parceiros'); ?></option>
                        <option value="random" <?php selected($_POST['pix_key_type'] ?? '', 'random'); ?>><?php _e('Chave Aleatória', 'lab-resumos-parceiros'); ?></option>
                    </select>
                </div>
                
                <div class="lrp-form-group">
                    <label><?php _e('Chave PIX', 'lab-resumos-parceiros'); ?> *</label>
                    <input type="text" name="pix_key" required class="lrp-input"
                           value="<?php echo esc_attr($_POST['pix_key'] ?? ''); ?>">
                </div>
                
                <div class="lrp-form-group">
                    <label><?php _e('Nome do Titular da Conta', 'lab-resumos-parceiros'); ?> *</label>
                    <input type="text" name="holder_name" required class="lrp-input" 
                           value="<?php echo esc_attr($_POST['holder_name'] ?? ($user ? $user->display_name : '')); ?>">
                    <small><?php _e('Nome completo ou Razão Social conforme cadastrado no banco.', 'lab-resumos-parceiros'); ?></small>
                </div>
                
                <div class="lrp-form-group">
                    <label class="lrp-holder-document-label"><?php _e('CPF/CNPJ do Titular da Conta', 'lab-resumos-parceiros'); ?> *</label>
                    <input type="text" name="holder_document" required class="lrp-input lrp-document-mask" 
                           placeholder="<?php esc_attr_e('CPF ou CNPJ', 'lab-resumos-parceiros'); ?>"
                           value="<?php echo esc_attr($_POST['holder_document'] ?? ''); ?>">
                    <small><?php _e('CPF ou CNPJ vinculado à conta PIX.', 'lab-resumos-parceiros'); ?></small>
                </div>
            </div>
            
            <div class="lrp-form-section">
                <h4><?php _e('Conte-nos sobre você', 'lab-resumos-parceiros'); ?></h4>
                
                <div class="lrp-form-group">
                    <label><?php _e('Como pretende divulgar?', 'lab-resumos-parceiros'); ?></label>
                    <textarea name="application_notes" rows="4" class="lrp-input" 
                              placeholder="<?php esc_attr_e('Ex: Instagram, blog, grupo de estudos...', 'lab-resumos-parceiros'); ?>"><?php echo esc_textarea($_POST['application_notes'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="lrp-form-section lrp-form-confirmations">
                <!-- Confirmação PJ -->
                <label class="lrp-checkbox lrp-confirm-pj" style="<?php echo $selected_billing_type === 'pj' ? '' : 'display:none;'; ?>">
                    <input type="checkbox" name="confirm_nf" class="lrp-pj-required-checkbox">
                    <?php _e('Confirmo que minha empresa está apta a emitir Nota Fiscal de Serviços (NFS-e) e me comprometo a emitir a NF para cada pagamento de comissão recebido.', 'lab-resumos-parceiros'); ?>
                </label>
                
                <!-- Confirmação RPA -->
                <label class="lrp-checkbox lrp-confirm-rpa" style="<?php echo $selected_billing_type === 'rpa' ? '' : 'display:none;'; ?>">
                    <input type="checkbox" name="confirm_rpa" class="lrp-rpa-required-checkbox">
                    <?php _e('Declaro que os dados informados estão corretos e autorizo a emissão de RPA (Recibo de Pagamento Autônomo) para pagamento das comissões.', 'lab-resumos-parceiros'); ?>
                </label>
                
                <label class="lrp-checkbox">
                    <input type="checkbox" name="terms" required>
                    <?php printf(__('Li e aceito os <a href="%s" target="_blank">Termos do Programa de Parceiros</a>', 'lab-resumos-parceiros'), 
                        esc_url(home_url('/termos-parceiros/'))); ?>
                </label>
            </div>
            
            <button type="submit" class="lrp-btn lrp-btn-primary lrp-btn-large">
                <?php _e('Enviar Cadastro', 'lab-resumos-parceiros'); ?>
            </button>
        </form>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var billingTypeInputs = document.querySelectorAll('input[name="billing_type"]');
            var pjFields = document.querySelector('.lrp-billing-pj-fields');
            var rpaFields = document.querySelector('.lrp-billing-rpa-fields');
            var confirmPj = document.querySelector('.lrp-confirm-pj');
            var confirmRpa = document.querySelector('.lrp-confirm-rpa');
            var billingOptions = document.querySelectorAll('.lrp-billing-type-option');
            
            function toggleFields(billingType) {
                // Toggle visibility
                if (billingType === 'pj') {
                    pjFields.style.display = 'block';
                    rpaFields.style.display = 'none';
                    confirmPj.style.display = 'block';
                    confirmRpa.style.display = 'none';
                    
                    // Toggle required
                    document.querySelectorAll('.lrp-pj-required').forEach(function(el) {
                        el.required = true;
                    });
                    document.querySelectorAll('.lrp-rpa-required').forEach(function(el) {
                        el.required = false;
                    });
                    document.querySelector('.lrp-pj-required-checkbox').required = true;
                    document.querySelector('.lrp-rpa-required-checkbox').required = false;
                } else {
                    pjFields.style.display = 'none';
                    rpaFields.style.display = 'block';
                    confirmPj.style.display = 'none';
                    confirmRpa.style.display = 'block';
                    
                    // Toggle required
                    document.querySelectorAll('.lrp-pj-required').forEach(function(el) {
                        el.required = false;
                    });
                    document.querySelectorAll('.lrp-rpa-required').forEach(function(el) {
                        el.required = true;
                    });
                    document.querySelector('.lrp-pj-required-checkbox').required = false;
                    document.querySelector('.lrp-rpa-required-checkbox').required = true;
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
                    toggleFields(this.value);
                });
            });
            
            // Initialize
            var checkedType = document.querySelector('input[name="billing_type"]:checked');
            if (checkedType) {
                toggleFields(checkedType.value);
            }
        });
        </script>
        <?php
    }

    /**
     * Renderiza mensagens
     */
    private function render_messages() {
        foreach ($this->messages as $message) {
            $class = $message['type'] === 'error' ? 'lrp-notice-error' : 'lrp-notice-success';
            echo '<div class="lrp-notice ' . esc_attr($class) . '"><p>' . esc_html($message['text']) . '</p></div>';
        }
    }

    /**
     * Processa cadastro
     */
    private function process_registration() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['lrp_registration_nonce'], 'lrp_registration')) {
            $this->messages[] = ['type' => 'error', 'text' => __('Erro de segurança. Tente novamente.', 'lab-resumos-parceiros')];
            return;
        }
        
        // Verifica rate limit
        if (!$this->check_rate_limit()) {
            $this->messages[] = ['type' => 'error', 'text' => __('Muitas tentativas. Aguarde alguns minutos.', 'lab-resumos-parceiros')];
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Se não está logado, cria conta WordPress primeiro
        if (!$user_id) {
            $user_id = $this->create_wordpress_account();
            
            if (is_wp_error($user_id)) {
                $this->messages[] = ['type' => 'error', 'text' => $user_id->get_error_message()];
                return;
            }
        }
        
        // Verifica se já é afiliado
        if (LRP_Affiliate::get_by_user_id($user_id)) {
            $this->messages[] = ['type' => 'error', 'text' => __('Você já é um parceiro!', 'lab-resumos-parceiros')];
            return;
        }
        
        // Obtém tipo de faturamento (escolha obrigatória, sem padrão silencioso)
        $billing_type = sanitize_key($_POST['billing_type'] ?? '');
        if (!in_array($billing_type, ['pj', 'rpa'], true)) {
            $this->messages[] = ['type' => 'error', 'text' => __('Selecione como você deseja receber: Pessoa Jurídica (PJ) ou Pessoa Física (RPA).', 'lab-resumos-parceiros')];
            return;
        }
        
        // Valida dados de identificação (sempre obrigatórios)
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $cpf = sanitize_text_field($_POST['cpf'] ?? '');
        
        if (empty($first_name) || empty($last_name)) {
            $this->messages[] = ['type' => 'error', 'text' => __('Nome e Sobrenome são obrigatórios.', 'lab-resumos-parceiros')];
            return;
        }
        
        if (empty($cpf)) {
            $this->messages[] = ['type' => 'error', 'text' => __('CPF é obrigatório.', 'lab-resumos-parceiros')];
            return;
        }
        
        // Valida CPF
        $clean_cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (!LRP_Affiliate::validate_cpf($clean_cpf)) {
            $this->messages[] = ['type' => 'error', 'text' => __('CPF inválido.', 'lab-resumos-parceiros')];
            return;
        }
        
        // Inicializa dados do afiliado
        $data = [
            'user_id'           => $user_id,
            'status'            => LRP_Settings::instance()->is_auto_approve() ? 'active' : 'pending',
            'billing_type'      => $billing_type,
            'first_name'        => $first_name,
            'last_name'         => $last_name,
            'cpf'               => $clean_cpf,
            'application_notes' => sanitize_textarea_field($_POST['application_notes'] ?? ''),
            'application_ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
        ];
        
        // Validações específicas por tipo
        if ($billing_type === 'pj') {
            // Valida dados da empresa
            $company_cnpj = sanitize_text_field($_POST['company_cnpj'] ?? '');
            $company_name = sanitize_text_field($_POST['company_name'] ?? '');
            
            if (empty($company_cnpj) || empty($company_name)) {
                $this->messages[] = ['type' => 'error', 'text' => __('Preencha os dados da empresa (CNPJ e Razão Social).', 'lab-resumos-parceiros')];
                return;
            }
            
            // Valida CNPJ da empresa
            $clean_company_cnpj = preg_replace('/[^0-9]/', '', $company_cnpj);
            if (!LRP_Affiliate::validate_cnpj($clean_company_cnpj)) {
                $this->messages[] = ['type' => 'error', 'text' => __('CNPJ da empresa inválido.', 'lab-resumos-parceiros')];
                return;
            }
            
            // Valida confirmação de emissão de NF
            if (empty($_POST['confirm_nf'])) {
                $this->messages[] = ['type' => 'error', 'text' => __('Você precisa confirmar que pode emitir Nota Fiscal de Serviços.', 'lab-resumos-parceiros')];
                return;
            }
            
            $data['company_cnpj'] = $clean_company_cnpj;
            $data['company_name'] = $company_name;
            $data['can_issue_nf'] = 1;
            
        } else {
            // billing_type === 'rpa'
            $full_address = sanitize_textarea_field($_POST['full_address'] ?? '');
            $phone = sanitize_text_field($_POST['phone'] ?? '');
            $inss_number = sanitize_text_field($_POST['inss_number'] ?? '');
            $birth_date = sanitize_text_field($_POST['birth_date'] ?? '');
            
            if (empty($full_address) || empty($phone) || empty($birth_date)) {
                $this->messages[] = ['type' => 'error', 'text' => __('Preencha todos os dados obrigatórios para RPA (Endereço, Telefone e Data de Nascimento).', 'lab-resumos-parceiros')];
                return;
            }
            
            // Valida confirmação de RPA
            if (empty($_POST['confirm_rpa'])) {
                $this->messages[] = ['type' => 'error', 'text' => __('Você precisa concordar com a emissão de RPA.', 'lab-resumos-parceiros')];
                return;
            }
            
            $data['full_address'] = $full_address;
            $data['phone'] = preg_replace('/[^0-9]/', '', $phone);
            $data['inss_number'] = $inss_number;
            $data['birth_date'] = $birth_date;
            $data['can_issue_nf'] = 0;
        }
        
        // Valida campos de pagamento (comum a ambos)
        $pix_key_type = sanitize_text_field($_POST['pix_key_type'] ?? '');
        $pix_key = sanitize_text_field($_POST['pix_key'] ?? '');
        $holder_name = sanitize_text_field($_POST['holder_name'] ?? '');
        $holder_document = sanitize_text_field($_POST['holder_document'] ?? '');
        
        if (empty($pix_key_type) || empty($pix_key) || empty($holder_name) || empty($holder_document)) {
            $this->messages[] = ['type' => 'error', 'text' => __('Preencha todos os dados de pagamento.', 'lab-resumos-parceiros')];
            return;
        }
        
        // Valida documento do titular (CPF ou CNPJ)
        $clean_holder_doc = preg_replace('/[^0-9]/', '', $holder_document);
        if (strlen($clean_holder_doc) === 11) {
            if (!LRP_Affiliate::validate_cpf($clean_holder_doc)) {
                $this->messages[] = ['type' => 'error', 'text' => __('CPF do titular inválido.', 'lab-resumos-parceiros')];
                return;
            }
        } elseif (strlen($clean_holder_doc) === 14) {
            if (!LRP_Affiliate::validate_cnpj($clean_holder_doc)) {
                $this->messages[] = ['type' => 'error', 'text' => __('CNPJ do titular inválido.', 'lab-resumos-parceiros')];
                return;
            }
        } else {
            $this->messages[] = ['type' => 'error', 'text' => __('Documento do titular inválido. Informe CPF (11 dígitos) ou CNPJ (14 dígitos).', 'lab-resumos-parceiros')];
            return;
        }
        
        $data['pix_key_type'] = $pix_key_type;
        $data['pix_key'] = $pix_key;
        $data['holder_name'] = $holder_name;
        $data['holder_document'] = $clean_holder_doc;
        
        // Valida cupom (se fornecido)
        if (!empty($_POST['coupon_code'])) {
            $coupon_code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['coupon_code']));
            
            if (strlen($coupon_code) < 3) {
                $this->messages[] = ['type' => 'error', 'text' => __('Código do cupom deve ter pelo menos 3 caracteres.', 'lab-resumos-parceiros')];
                return;
            }
            
            // Verifica se cupom já existe
            global $wpdb;
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}lrp_affiliates WHERE coupon_code = %s",
                $coupon_code
            ));
            
            if ($exists) {
                $this->messages[] = ['type' => 'error', 'text' => __('Este código de cupom já está em uso.', 'lab-resumos-parceiros')];
                return;
            }
            
            $data['coupon_code'] = $coupon_code;
        }
        
        // Sponsor
        if (!empty($_POST['sponsor_id'])) {
            $sponsor_id = (int) $_POST['sponsor_id'];
            $sponsor = new LRP_Affiliate($sponsor_id);
            
            if ($sponsor->exists() && $sponsor->is_active()) {
                $data['sponsor_id'] = $sponsor_id;
                $data['level'] = $sponsor->get_level() + 1;
            }
        }
        
        // Cria afiliado
        $affiliate = LRP_Affiliate::create($data);
        
        if (is_wp_error($affiliate)) {
            $this->messages[] = ['type' => 'error', 'text' => $affiliate->get_error_message()];
            return;
        }
        
        // Sucesso
        if ($affiliate->is_active()) {
            // Aprovação automática
            $dashboard_url = get_permalink(get_option('lrp_dashboard_page_id'));
            $this->messages[] = ['type' => 'success', 'text' => __('Cadastro realizado com sucesso! Redirecionando...', 'lab-resumos-parceiros')];
            
            // Email de boas-vindas
            do_action('lrp_affiliate_approved', $affiliate);
            
            // Redireciona após 2 segundos
            echo '<script>setTimeout(function(){ window.location.href = "' . esc_url($dashboard_url) . '"; }, 2000);</script>';
        } else {
            $this->messages[] = ['type' => 'success', 'text' => __('Cadastro enviado com sucesso! Você receberá um email quando for aprovado.', 'lab-resumos-parceiros')];
            
            // Notifica admin
            do_action('lrp_new_affiliate_application', $affiliate);
        }
    }

    /**
     * Verifica rate limit
     *
     * @return bool
     */
    private function check_rate_limit() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $transient_key = 'lrp_registration_' . md5($ip);
        
        $attempts = (int) get_transient($transient_key);
        
        if ($attempts >= 3) {
            return false;
        }
        
        set_transient($transient_key, $attempts + 1, 5 * MINUTE_IN_SECONDS);
        
        return true;
    }

    /**
     * Cria conta WordPress para visitante
     *
     * @return int|WP_Error ID do usuário ou erro
     */
    private function create_wordpress_account() {
        // Valida campos obrigatórios
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            return new WP_Error('missing_fields', __('Preencha todos os campos obrigatórios.', 'lab-resumos-parceiros'));
        }
        
        // Valida email
        if (!is_email($email)) {
            return new WP_Error('invalid_email', __('Email inválido.', 'lab-resumos-parceiros'));
        }
        
        // Verifica se email já existe
        if (email_exists($email)) {
            return new WP_Error('email_exists', __('Este email já está cadastrado. Faça login para continuar.', 'lab-resumos-parceiros'));
        }
        
        // Valida senha
        if (strlen($password) < 6) {
            return new WP_Error('weak_password', __('A senha deve ter pelo menos 6 caracteres.', 'lab-resumos-parceiros'));
        }
        
        if ($password !== $password_confirm) {
            return new WP_Error('password_mismatch', __('As senhas não conferem.', 'lab-resumos-parceiros'));
        }
        
        // Gera username a partir do email
        $username = sanitize_user(current(explode('@', $email)), true);
        $base_username = $username;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }
        
        // Cria usuário
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return new WP_Error('create_failed', __('Erro ao criar conta. Tente novamente.', 'lab-resumos-parceiros'));
        }
        
        // Atualiza nome
        $display_name = trim($first_name . ' ' . $last_name);
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $display_name,
            'first_name' => $first_name,
            'last_name' => $last_name,
        ]);
        
        // Faz login automático
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        // Log
        lrp_log('Conta WordPress criada para novo parceiro', [
            'user_id' => $user_id,
            'email' => $email,
        ]);
        
        return $user_id;
    }
}

