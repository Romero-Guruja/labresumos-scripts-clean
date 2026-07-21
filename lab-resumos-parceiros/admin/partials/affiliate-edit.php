<?php
/**
 * Edição de Afiliado
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) exit;

$user = $affiliate->get_user();
$payment_data = $affiliate->get_payment_data();
?>
<div class="wrap lrp-admin-wrap">
    <h1>
        👤 <?php echo esc_html($affiliate->get_display_name()); ?>
        <span class="lrp-badge lrp-badge-<?php echo esc_attr($affiliate->get_status()); ?>">
            <?php echo esc_html($affiliate->get_status()); ?>
        </span>
    </h1>
    
    <?php 
    // Link para preview do dashboard do afiliado
    $dashboard_page_id = get_option('lrp_dashboard_page_id');
    if ($dashboard_page_id): 
        $preview_url = add_query_arg('preview_as', $affiliate->get_id(), get_permalink($dashboard_page_id));
    ?>
    <p class="lrp-preview-link">
        <a href="<?php echo esc_url($preview_url); ?>" class="button" target="_blank">
            👁️ <?php _e('Visualizar Dashboard do Afiliado', 'lab-resumos-parceiros'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-adjustments&affiliate_id=' . $affiliate->get_id())); ?>" class="button">
            🎁 <?php _e('Ajustes e Bônus', 'lab-resumos-parceiros'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliate-restrictions&affiliate_id=' . $affiliate->get_id())); ?>" class="button">
            🚫 <?php _e('Restrições', 'lab-resumos-parceiros'); ?>
        </a>
    </p>
    <?php endif; ?>
    
    <div class="lrp-affiliate-info">
        <div class="lrp-info-item">
            <strong><?php echo esc_html($affiliate->get_total_sales()); ?></strong>
            <span><?php _e('Vendas', 'lab-resumos-parceiros'); ?></span>
        </div>
        <div class="lrp-info-item">
            <strong>R$ <?php echo esc_html(number_format($affiliate->get_total_revenue(), 2, ',', '.')); ?></strong>
            <span><?php _e('Receita', 'lab-resumos-parceiros'); ?></span>
        </div>
        <div class="lrp-info-item">
            <strong>R$ <?php echo esc_html(number_format($affiliate->get_total_commissions(), 2, ',', '.')); ?></strong>
            <span><?php _e('Comissões', 'lab-resumos-parceiros'); ?></span>
        </div>
        <div class="lrp-info-item">
            <strong>R$ <?php echo esc_html(number_format($affiliate->get_current_balance(), 2, ',', '.')); ?></strong>
            <span><?php _e('Saldo Atual', 'lab-resumos-parceiros'); ?></span>
        </div>
    </div>
    
    <div class="lrp-two-columns">
        <div>
            <div class="lrp-metabox">
                <div class="lrp-metabox-header"><?php _e('Informações do Afiliado', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content">
                    <table class="form-table">
                        <tr>
                            <th><?php _e('ID', 'lab-resumos-parceiros'); ?></th>
                            <td><?php echo esc_html($affiliate->get_id()); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Email', 'lab-resumos-parceiros'); ?></th>
                            <td><?php echo esc_html($user->user_email); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Cupom', 'lab-resumos-parceiros'); ?></th>
                            <td>
                                <code><?php echo esc_html($affiliate->get_coupon_code()); ?></code>
                                <span class="description" style="margin-left: 10px;"><?php _e('(editável abaixo)', 'lab-resumos-parceiros'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Link de Referral', 'lab-resumos-parceiros'); ?></th>
                            <td><code><?php echo esc_url($affiliate->get_referral_url()); ?></code></td>
                        </tr>
                        <tr>
                            <th><?php _e('Data de Cadastro', 'lab-resumos-parceiros'); ?></th>
                            <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($affiliate->get_data('created_at') ?? ''))); ?></td>
                        </tr>
                        <?php if ($affiliate->get_sponsor_id()): ?>
                        <tr>
                            <th><?php _e('Sponsor', 'lab-resumos-parceiros'); ?></th>
                            <td>
                                <?php $sponsor = $affiliate->get_sponsor(); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&action=view&id=' . $sponsor->get_id())); ?>">
                                    <?php echo esc_html($sponsor->get_display_name()); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <div class="lrp-metabox" style="margin-top: 20px;">
                <div class="lrp-metabox-header"><?php _e('Configurações Individuais', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content">
                    <form method="post" id="lrp-affiliate-form">
                        <table class="form-table">
                            <tr>
                                <th>
                                    <?php _e('Status', 'lab-resumos-parceiros'); ?>
                                    <span class="lrp-tooltip" data-tooltip="<?php esc_attr_e('Define se o afiliado está ativo, inativo ou pendente. Apenas afiliados ativos podem gerar comissões.', 'lab-resumos-parceiros'); ?>">?</span>
                                </th>
                                <td>
                                    <select name="status">
                                        <option value="active" <?php selected($affiliate->get_status(), 'active'); ?>><?php _e('Ativo', 'lab-resumos-parceiros'); ?></option>
                                        <option value="inactive" <?php selected($affiliate->get_status(), 'inactive'); ?>><?php _e('Inativo', 'lab-resumos-parceiros'); ?></option>
                                        <option value="pending" <?php selected($affiliate->get_status(), 'pending'); ?>><?php _e('Pendente', 'lab-resumos-parceiros'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <?php _e('Código do Cupom', 'lab-resumos-parceiros'); ?>
                                    <span class="lrp-tooltip" data-tooltip="<?php esc_attr_e('Código do cupom de desconto do afiliado. Use apenas letras e números (3-20 caracteres). Ao alterar, o cupom antigo será substituído no WooCommerce.', 'lab-resumos-parceiros'); ?>">?</span>
                                </th>
                                <td>
                                    <input type="text" name="coupon_code" id="coupon_code" 
                                           value="<?php echo esc_attr($affiliate->get_coupon_code()); ?>" 
                                           pattern="[A-Za-z0-9]+" minlength="3" maxlength="20"
                                           style="text-transform: uppercase; font-family: monospace;">
                                    <input type="hidden" name="original_coupon_code" value="<?php echo esc_attr($affiliate->get_coupon_code()); ?>">
                                    <p class="description"><?php _e('Apenas letras e números, 3-20 caracteres.', 'lab-resumos-parceiros'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <?php _e('Desconto Cliente (%)', 'lab-resumos-parceiros'); ?>
                                    <span class="lrp-tooltip" data-tooltip="<?php echo esc_attr(sprintf(__('Percentual de desconto que o cliente receberá ao usar o cupom deste afiliado. Deixe vazio para usar o padrão do sistema (%s%%). Marque "Zerar desconto" para que o cupom não dê desconto (útil para rastreamento apenas).', 'lab-resumos-parceiros'), number_format(LRP_Settings::instance()->get_customer_discount(), 0))); ?>">?</span>
                                </th>
                                <td>
                                    <input type="number" name="customer_discount" id="customer_discount" step="1" min="1" max="100" 
                                           value="<?php echo esc_attr($affiliate->get_data('customer_discount') ?? ''); ?>" 
                                           placeholder="<?php echo esc_attr(LRP_Settings::instance()->get_customer_discount()); ?>"
                                           <?php echo !empty($affiliate->get_data('zero_customer_discount')) ? 'disabled' : ''; ?>>
                                    <label style="margin-left: 10px;">
                                        <input type="checkbox" name="zero_customer_discount" value="1" 
                                               <?php checked(!empty($affiliate->get_data('zero_customer_discount'))); ?>
                                               onchange="lrpToggleZero(this, 'customer_discount')">
                                        <?php _e('Zerar desconto', 'lab-resumos-parceiros'); ?>
                                    </label>
                                    <p class="description"><?php _e('Deixe vazio para usar o padrão. Marque o checkbox para 0%.', 'lab-resumos-parceiros'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <?php _e('Comissão Cupom (%)', 'lab-resumos-parceiros'); ?>
                                    <span class="lrp-tooltip" data-tooltip="<?php echo esc_attr(sprintf(__('Percentual de comissão que o afiliado receberá quando um cliente usar seu cupom de desconto. Deixe vazio para usar o padrão (%s%%). Marque "Zerar comissão" para que vendas via cupom não gerem comissão (útil para afiliados que só ganham com rede).', 'lab-resumos-parceiros'), number_format(LRP_Settings::instance()->get_commission_rate('coupon'), 0))); ?>">?</span>
                                </th>
                                <td>
                                    <input type="number" name="commission_rate_coupon" id="commission_rate_coupon" step="0.01" min="0.01" max="100" 
                                           value="<?php echo esc_attr($affiliate->get_data('commission_rate_coupon') ?? ''); ?>" 
                                           placeholder="<?php echo esc_attr(LRP_Settings::instance()->get_commission_rate('coupon')); ?>"
                                           <?php echo !empty($affiliate->get_data('zero_commission_rate_coupon')) ? 'disabled' : ''; ?>>
                                    <label style="margin-left: 10px;">
                                        <input type="checkbox" name="zero_commission_rate_coupon" value="1" 
                                               <?php checked(!empty($affiliate->get_data('zero_commission_rate_coupon'))); ?>
                                               onchange="lrpToggleZero(this, 'commission_rate_coupon')">
                                        <?php _e('Zerar comissão', 'lab-resumos-parceiros'); ?>
                                    </label>
                                    <p class="description"><?php _e('Deixe vazio para usar o padrão. Marque o checkbox para 0%.', 'lab-resumos-parceiros'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <?php _e('Comissão Link (%)', 'lab-resumos-parceiros'); ?>
                                    <span class="lrp-tooltip" data-tooltip="<?php esc_attr_e('Percentual de comissão que o afiliado receberá quando uma venda for atribuída via link de rastreamento (cookie). Deixe vazio para usar o padrão (5%). Marque "Zerar comissão" para desativar comissões por link.', 'lab-resumos-parceiros'); ?>">?</span>
                                </th>
                                <td>
                                    <input type="number" name="commission_rate_link" id="commission_rate_link" step="0.01" min="0.01" max="100" 
                                           value="<?php echo esc_attr($affiliate->get_data('commission_rate_link') ?? ''); ?>" 
                                           placeholder="<?php echo esc_attr(LRP_Settings::instance()->get_commission_rate('link')); ?>"
                                           <?php echo !empty($affiliate->get_data('zero_commission_rate_link')) ? 'disabled' : ''; ?>>
                                    <label style="margin-left: 10px;">
                                        <input type="checkbox" name="zero_commission_rate_link" value="1" 
                                               <?php checked(!empty($affiliate->get_data('zero_commission_rate_link'))); ?>
                                               onchange="lrpToggleZero(this, 'commission_rate_link')">
                                        <?php _e('Zerar comissão', 'lab-resumos-parceiros'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <?php _e('Comissão Rede L2 (%)', 'lab-resumos-parceiros'); ?>
                                    <span class="lrp-tooltip" data-tooltip="<?php esc_attr_e('Comissão que este afiliado receberá sobre as vendas dos afiliados que ele recrutou diretamente (nível 2). Deixe vazio para usar o padrão (3%).', 'lab-resumos-parceiros'); ?>">?</span>
                                </th>
                                <td>
                                    <input type="number" name="commission_rate_l2" id="commission_rate_l2" step="0.01" min="0.01" max="100" 
                                           value="<?php echo esc_attr($affiliate->get_data('commission_rate_l2') ?? ''); ?>" 
                                           placeholder="<?php echo esc_attr(LRP_Settings::instance()->get_commission_rate('l2')); ?>"
                                           <?php echo !empty($affiliate->get_data('zero_commission_rate_l2')) ? 'disabled' : ''; ?>>
                                    <label style="margin-left: 10px;">
                                        <input type="checkbox" name="zero_commission_rate_l2" value="1" 
                                               <?php checked(!empty($affiliate->get_data('zero_commission_rate_l2'))); ?>
                                               onchange="lrpToggleZero(this, 'commission_rate_l2')">
                                        <?php _e('Zerar comissão', 'lab-resumos-parceiros'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <?php _e('Comissão Rede L3 (%)', 'lab-resumos-parceiros'); ?>
                                    <span class="lrp-tooltip" data-tooltip="<?php esc_attr_e('Comissão que este afiliado receberá sobre as vendas dos afiliados recrutados pelos seus sub-afiliados (nível 3). Deixe vazio para usar o padrão (1%).', 'lab-resumos-parceiros'); ?>">?</span>
                                </th>
                                <td>
                                    <input type="number" name="commission_rate_l3" id="commission_rate_l3" step="0.01" min="0.01" max="100" 
                                           value="<?php echo esc_attr($affiliate->get_data('commission_rate_l3') ?? ''); ?>" 
                                           placeholder="<?php echo esc_attr(LRP_Settings::instance()->get_commission_rate('l3')); ?>"
                                           <?php echo !empty($affiliate->get_data('zero_commission_rate_l3')) ? 'disabled' : ''; ?>>
                                    <label style="margin-left: 10px;">
                                        <input type="checkbox" name="zero_commission_rate_l3" value="1" 
                                               <?php checked(!empty($affiliate->get_data('zero_commission_rate_l3'))); ?>
                                               onchange="lrpToggleZero(this, 'commission_rate_l3')">
                                        <?php _e('Zerar comissão', 'lab-resumos-parceiros'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <?php _e('Regra Guruja', 'lab-resumos-parceiros'); ?>
                                    <span class="lrp-tooltip" data-tooltip="<?php esc_attr_e('Define como o sistema deve tratar quando um cliente tem desconto Guruja E cupom de afiliado: Maior desconto (aplica o maior), Cupom prevalece (sempre usa cupom), Guruja prevalece (sempre usa Guruja), Sem comissão se Guruja (não gera comissão se tiver Guruja).', 'lab-resumos-parceiros'); ?>">?</span>
                                </th>
                                <td>
                                    <select name="guruja_rule">
                                        <option value=""><?php _e('Usar padrão', 'lab-resumos-parceiros'); ?></option>
                                        <option value="higher_discount" <?php selected($affiliate->get_data('guruja_rule') ?? '', 'higher_discount'); ?>><?php _e('Maior desconto', 'lab-resumos-parceiros'); ?></option>
                                        <option value="affiliate_priority" <?php selected($affiliate->get_data('guruja_rule') ?? '', 'affiliate_priority'); ?>><?php _e('Cupom prevalece', 'lab-resumos-parceiros'); ?></option>
                                        <option value="guruja_priority" <?php selected($affiliate->get_data('guruja_rule') ?? '', 'guruja_priority'); ?>><?php _e('Guruja prevalece', 'lab-resumos-parceiros'); ?></option>
                                        <option value="no_commission" <?php selected($affiliate->get_data('guruja_rule') ?? '', 'no_commission'); ?>><?php _e('Sem comissão se Guruja', 'lab-resumos-parceiros'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <?php _e('Auto-referência', 'lab-resumos-parceiros'); ?>
                                    <span class="lrp-tooltip" data-tooltip="<?php esc_attr_e('Define se este afiliado pode usar o próprio cupom/link e ganhar comissão da própria compra. "Usar padrão" segue a configuração global em Configurações.', 'lab-resumos-parceiros'); ?>">?</span>
                                </th>
                                <td>
                                    <?php $csr = $affiliate->get_data('can_self_refer'); ?>
                                    <select name="can_self_refer">
                                        <option value="" <?php selected($csr === null || $csr === ''); ?>><?php _e('Usar padrão', 'lab-resumos-parceiros'); ?></option>
                                        <option value="1" <?php selected($csr !== null && $csr !== '' && (string) $csr === '1'); ?>><?php _e('Permitir', 'lab-resumos-parceiros'); ?></option>
                                        <option value="0" <?php selected($csr !== null && $csr !== '' && (string) $csr === '0'); ?>><?php _e('Bloquear', 'lab-resumos-parceiros'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <?php _e('Notas Admin', 'lab-resumos-parceiros'); ?>
                                    <span class="lrp-tooltip" data-tooltip="<?php esc_attr_e('Anotações internas sobre este afiliado. Apenas administradores podem ver estas notas. Útil para registrar informações importantes, acordos especiais ou observações.', 'lab-resumos-parceiros'); ?>">?</span>
                                </th>
                                <td>
                                    <textarea name="admin_notes" rows="3" class="large-text"><?php echo esc_textarea($affiliate->get_data('admin_notes') ?? ''); ?></textarea>
                                </td>
                            </tr>
                        </table>
                        <input type="hidden" name="affiliate_id" value="<?php echo esc_attr($affiliate->get_id()); ?>">
                        <p>
                            <button type="button" class="button button-primary" onclick="lrpSaveAffiliate()">
                                <?php _e('Salvar Alterações', 'lab-resumos-parceiros'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        
        <div>
            <!-- Tipo de Faturamento e Periodicidade -->
            <div class="lrp-metabox">
                <div class="lrp-metabox-header"><?php _e('Faturamento e Periodicidade', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content">
                    <form method="post" id="lrp-billing-form">
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Tipo de Recebimento', 'lab-resumos-parceiros'); ?></th>
                                <td>
                                    <select name="billing_type" id="billing_type_select">
                                        <option value="pj" <?php selected($affiliate->get_billing_type(), 'pj'); ?>><?php _e('PJ - Nota Fiscal', 'lab-resumos-parceiros'); ?></option>
                                        <option value="rpa" <?php selected($affiliate->get_billing_type(), 'rpa'); ?>><?php _e('PF - RPA', 'lab-resumos-parceiros'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <!-- Dados PJ -->
                            <tr class="lrp-pj-field" style="<?php echo $affiliate->is_rpa() ? 'display:none;' : ''; ?>">
                                <th><?php _e('CNPJ', 'lab-resumos-parceiros'); ?></th>
                                <td>
                                    <input type="text" name="company_cnpj" value="<?php echo esc_attr($affiliate->get_company_cnpj_formatted()); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr class="lrp-pj-field" style="<?php echo $affiliate->is_rpa() ? 'display:none;' : ''; ?>">
                                <th><?php _e('Razão Social', 'lab-resumos-parceiros'); ?></th>
                                <td>
                                    <input type="text" name="company_name" value="<?php echo esc_attr($affiliate->get_company_name()); ?>" class="regular-text">
                                </td>
                            </tr>
                            
                            <!-- Dados RPA -->
                            <tr class="lrp-rpa-field" style="<?php echo $affiliate->is_pj() ? 'display:none;' : ''; ?>">
                                <th><?php _e('CPF', 'lab-resumos-parceiros'); ?></th>
                                <td>
                                    <input type="text" name="cpf" value="<?php echo esc_attr($affiliate->get_cpf_formatted()); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr class="lrp-rpa-field" style="<?php echo $affiliate->is_pj() ? 'display:none;' : ''; ?>">
                                <th><?php _e('Endereço Completo', 'lab-resumos-parceiros'); ?></th>
                                <td>
                                    <textarea name="full_address" rows="2" class="large-text"><?php echo esc_textarea($affiliate->get_full_address()); ?></textarea>
                                </td>
                            </tr>
                            <tr class="lrp-rpa-field" style="<?php echo $affiliate->is_pj() ? 'display:none;' : ''; ?>">
                                <th><?php _e('Telefone', 'lab-resumos-parceiros'); ?></th>
                                <td>
                                    <input type="text" name="phone" value="<?php echo esc_attr($affiliate->get_phone()); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr class="lrp-rpa-field" style="<?php echo $affiliate->is_pj() ? 'display:none;' : ''; ?>">
                                <th><?php _e('Data de Nascimento', 'lab-resumos-parceiros'); ?></th>
                                <td>
                                    <input type="date" name="birth_date" value="<?php echo esc_attr($affiliate->get_data('birth_date') ?? ''); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr class="lrp-rpa-field" style="<?php echo $affiliate->is_pj() ? 'display:none;' : ''; ?>">
                                <th><?php _e('INSS (PIS/PASEP)', 'lab-resumos-parceiros'); ?></th>
                                <td>
                                    <input type="text" name="inss_number" value="<?php echo esc_attr($affiliate->get_inss_number()); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr class="lrp-rpa-field" style="<?php echo $affiliate->is_pj() ? 'display:none;' : ''; ?>">
                                <th><?php _e('Descrição do Serviço', 'lab-resumos-parceiros'); ?></th>
                                <td>
                                    <input type="text" name="rpa_service_description" value="<?php echo esc_attr($affiliate->get_data('rpa_service_description') ?? ''); ?>" class="large-text" placeholder="<?php echo esc_attr(LRP_Settings::instance()->get('rpa_service_description', '')); ?>">
                                    <p class="description"><?php _e('Deixe vazio para usar o padrão.', 'lab-resumos-parceiros'); ?></p>
                                </td>
                            </tr>
                            
                            <!-- Periodicidade -->
                            <tr>
                                <th><?php _e('Período de Pagamento', 'lab-resumos-parceiros'); ?></th>
                                <td>
                                    <select name="payment_period_months">
                                        <option value="1" <?php selected($affiliate->get_payment_period_months(), 1); ?>><?php _e('Mensal', 'lab-resumos-parceiros'); ?></option>
                                        <option value="2" <?php selected($affiliate->get_payment_period_months(), 2); ?>><?php _e('Bimestral', 'lab-resumos-parceiros'); ?></option>
                                        <option value="3" <?php selected($affiliate->get_payment_period_months(), 3); ?>><?php _e('Trimestral', 'lab-resumos-parceiros'); ?></option>
                                        <option value="4" <?php selected($affiliate->get_payment_period_months(), 4); ?>><?php _e('Quadrimestral', 'lab-resumos-parceiros'); ?></option>
                                        <option value="6" <?php selected($affiliate->get_payment_period_months(), 6); ?>><?php _e('Semestral', 'lab-resumos-parceiros'); ?></option>
                                        <option value="12" <?php selected($affiliate->get_payment_period_months(), 12); ?>><?php _e('Anual', 'lab-resumos-parceiros'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Próximo Fechamento', 'lab-resumos-parceiros'); ?></th>
                                <td>
                                    <input type="date" name="next_payment_date" value="<?php echo esc_attr($affiliate->get_next_payment_date()); ?>" class="regular-text">
                                    <p class="description"><?php _e('Data em que o próximo fechamento será gerado.', 'lab-resumos-parceiros'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <input type="hidden" name="affiliate_id" value="<?php echo esc_attr($affiliate->get_id()); ?>">
                        <p>
                            <button type="button" class="button button-primary" onclick="lrpSaveBilling()">
                                <?php _e('Salvar Faturamento', 'lab-resumos-parceiros'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>
            
            <div class="lrp-payment-data" style="margin-top: 20px;">
                <h4><?php _e('Dados de Pagamento PIX', 'lab-resumos-parceiros'); ?></h4>
                <p><strong><?php _e('Método:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html(strtoupper($payment_data['method'])); ?></p>
                <?php if ($payment_data['method'] === 'pix'): ?>
                <p><strong><?php _e('Tipo PIX:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html(strtoupper($payment_data['pix_key_type'])); ?></p>
                <p><strong><?php _e('Chave PIX:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($payment_data['pix_key']); ?></p>
                <?php endif; ?>
                <p><strong><?php _e('Titular:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($payment_data['holder_name']); ?></p>
                <p><strong><?php _e('CPF/CNPJ:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($payment_data['holder_document']); ?></p>
            </div>
            
            <?php if (!empty($affiliate->get_data('application_notes'))): ?>
            <div class="lrp-metabox" style="margin-top: 20px;">
                <div class="lrp-metabox-header"><?php _e('Notas do Cadastro', 'lab-resumos-parceiros'); ?></div>
                <div class="lrp-metabox-content">
                    <p><?php echo nl2br(esc_html($affiliate->get_data('application_notes'))); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Seção de Restrições de Produtos -->
    <?php include LRP_PLUGIN_DIR . 'admin/partials/affiliate-restrictions.php'; ?>
</div>

<script>
function lrpToggleZero(checkbox, fieldId) {
    var field = document.getElementById(fieldId);
    if (checkbox.checked) {
        field.disabled = true;
        field.value = '';
    } else {
        field.disabled = false;
    }
}

function lrpSaveAffiliate() {
    var $ = jQuery;
    var formData = $('#lrp-affiliate-form').serialize();
    formData += '&action=lrp_update_affiliate&nonce=' + lrp_admin.nonce;
    
    $.post(lrp_admin.ajax_url, formData, function(response) {
        if (response.success) {
            alert(response.data.message);
            location.reload();
        } else {
            alert(response.data.message || 'Erro ao salvar');
        }
    });
}

function lrpSaveBilling() {
    var $ = jQuery;
    var formData = $('#lrp-billing-form').serialize();
    formData += '&action=lrp_update_affiliate_billing&nonce=' + lrp_admin.nonce;
    
    $.post(lrp_admin.ajax_url, formData, function(response) {
        if (response.success) {
            alert(response.data.message);
            location.reload();
        } else {
            alert(response.data.message || 'Erro ao salvar');
        }
    });
}

// Toggle campos PJ/RPA
document.addEventListener('DOMContentLoaded', function() {
    var billingSelect = document.getElementById('billing_type_select');
    var pjFields = document.querySelectorAll('.lrp-pj-field');
    var rpaFields = document.querySelectorAll('.lrp-rpa-field');
    
    if (billingSelect) {
        billingSelect.addEventListener('change', function() {
            var isPJ = this.value === 'pj';
            
            pjFields.forEach(function(field) {
                field.style.display = isPJ ? '' : 'none';
            });
            
            rpaFields.forEach(function(field) {
                field.style.display = isPJ ? 'none' : '';
            });
        });
    }
    
    // Ajusta posição dos tooltips próximos à borda
    var tooltips = document.querySelectorAll('.lrp-tooltip');
    tooltips.forEach(function(tooltip) {
        tooltip.addEventListener('mouseenter', function() {
            var rect = this.getBoundingClientRect();
            var tooltipWidth = 280;
            var spaceRight = window.innerWidth - rect.right;
            
            // Se não há espaço suficiente à direita, posiciona à esquerda
            if (spaceRight < tooltipWidth && rect.left > tooltipWidth) {
                this.setAttribute('data-tooltip-position', 'right');
            } else {
                this.removeAttribute('data-tooltip-position');
            }
        });
    });
});
</script>

