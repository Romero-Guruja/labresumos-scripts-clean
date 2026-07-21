<?php
/**
 * Admin - Configurações
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Admin_Settings
 */
class LRP_Admin_Settings {

    /**
     * Obtém todas as configurações formatadas
     *
     * @return array
     */
    public static function get_settings_fields() {
        return [
            'general' => [
                'title'  => __('Geral', 'lab-resumos-parceiros'),
                'fields' => [
                    'enabled' => [
                        'label' => __('Programa Ativo', 'lab-resumos-parceiros'),
                        'type'  => 'checkbox',
                        'desc'  => __('Ativa/desativa o programa de parceiros', 'lab-resumos-parceiros'),
                    ],
                    'auto_approve' => [
                        'label' => __('Aprovação Automática', 'lab-resumos-parceiros'),
                        'type'  => 'checkbox',
                        'desc'  => __('Aprovar novos afiliados automaticamente', 'lab-resumos-parceiros'),
                    ],
                    'debug_mode' => [
                        'label' => __('Modo Debug', 'lab-resumos-parceiros'),
                        'type'  => 'checkbox',
                        'desc'  => __('Registra logs detalhados (apenas para desenvolvimento)', 'lab-resumos-parceiros'),
                    ],
                ],
            ],
            'commissions' => [
                'title'  => __('Comissões Padrão', 'lab-resumos-parceiros'),
                'fields' => [
                    'default_commission_coupon' => [
                        'label' => __('Comissão via Cupom (%)', 'lab-resumos-parceiros'),
                        'type'  => 'number',
                        'step'  => '0.01',
                        'min'   => 0,
                        'max'   => 100,
                    ],
                    'default_commission_link' => [
                        'label' => __('Comissão via Link (%)', 'lab-resumos-parceiros'),
                        'type'  => 'number',
                        'step'  => '0.01',
                        'min'   => 0,
                        'max'   => 100,
                    ],
                    'default_commission_l2' => [
                        'label' => __('Comissão Nível 2 (%)', 'lab-resumos-parceiros'),
                        'type'  => 'number',
                        'step'  => '0.01',
                        'min'   => 0,
                        'max'   => 100,
                    ],
                    'default_commission_l3' => [
                        'label' => __('Comissão Nível 3 (%)', 'lab-resumos-parceiros'),
                        'type'  => 'number',
                        'step'  => '0.01',
                        'min'   => 0,
                        'max'   => 100,
                    ],
                    'commission_base_type' => [
                        'label'   => __('Base de Cálculo da Comissão', 'lab-resumos-parceiros'),
                        'type'    => 'select',
                        'options' => [
                            'order_total'            => __('Total Pago (inclui frete e taxas)', 'lab-resumos-parceiros'),
                            'subtotal_only'          => __('Apenas Subtotal dos Produtos', 'lab-resumos-parceiros'),
                            'subtotal_minus_discount' => __('Subtotal menos Descontos', 'lab-resumos-parceiros'),
                        ],
                        'desc' => __('Define qual valor será usado como base para calcular a comissão.', 'lab-resumos-parceiros'),
                    ],
                ],
            ],
            'tracking' => [
                'title'  => __('Rastreamento', 'lab-resumos-parceiros'),
                'fields' => [
                    'default_cookie_days' => [
                        'label' => __('Duração do Cookie (dias)', 'lab-resumos-parceiros'),
                        'type'  => 'number',
                        'min'   => 1,
                        'max'   => 365,
                    ],
                    'default_customer_discount' => [
                        'label' => __('Desconto para Cliente (%)', 'lab-resumos-parceiros'),
                        'type'  => 'number',
                        'step'  => '0.01',
                        'min'   => 0,
                        'max'   => 100,
                    ],
                    'default_can_self_refer' => [
                        'label' => __('Permitir Auto-referência', 'lab-resumos-parceiros'),
                        'type'  => 'checkbox',
                        'desc'  => __('Permite que o afiliado use o próprio cupom/link e ganhe comissão da própria compra (padrão para novos afiliados; pode ser ajustado individualmente).', 'lab-resumos-parceiros'),
                    ],
                ],
            ],
            'financial' => [
                'title'  => __('Financeiro', 'lab-resumos-parceiros'),
                'fields' => [
                    'minimum_payout' => [
                        'label' => __('Valor Mínimo para Pagamento via RPA (R$)', 'lab-resumos-parceiros'),
                        'type'  => 'number',
                        'step'  => '0.01',
                        'min'   => 0,
                        'desc'  => __('Valor mínimo para pagamento de afiliados Pessoa Física (RPA). Afiliados PJ (Nota Fiscal) podem receber qualquer valor.', 'lab-resumos-parceiros'),
                    ],
                    'closing_day' => [
                        'label' => __('Dia do Fechamento', 'lab-resumos-parceiros'),
                        'type'  => 'number',
                        'min'   => 1,
                        'max'   => 28,
                    ],
                ],
            ],
            'guruja' => [
                'title'  => __('Integração Guruja', 'lab-resumos-parceiros'),
                'fields' => [
                    'default_guruja_rule' => [
                        'label'   => __('Regra Padrão', 'lab-resumos-parceiros'),
                        'type'    => 'select',
                        'options' => [
                            'higher_discount'    => __('Maior desconto prevalece', 'lab-resumos-parceiros'),
                            'affiliate_priority' => __('Cupom sempre prevalece', 'lab-resumos-parceiros'),
                            'guruja_priority'    => __('Guruja sempre prevalece', 'lab-resumos-parceiros'),
                            'no_commission'      => __('Maior desconto, sem comissão se Guruja', 'lab-resumos-parceiros'),
                        ],
                    ],
                ],
            ],
            'company' => [
                'title'  => __('Dados do Tomador (para NF)', 'lab-resumos-parceiros'),
                'fields' => [
                    'company_name' => [
                        'label' => __('Razão Social', 'lab-resumos-parceiros'),
                        'type'  => 'text',
                    ],
                    'company_cnpj' => [
                        'label' => __('CNPJ', 'lab-resumos-parceiros'),
                        'type'  => 'text',
                    ],
                    'company_address' => [
                        'label' => __('Endereço Completo', 'lab-resumos-parceiros'),
                        'type'  => 'textarea',
                        'desc'  => __('Endereço completo para emissão da NF (rua, número, bairro, cidade, estado, CEP)', 'lab-resumos-parceiros'),
                    ],
                ],
            ],
            'nf_instructions' => [
                'title'  => __('Instruções de Emissão de NF', 'lab-resumos-parceiros'),
                'fields' => [
                    'nf_contact_email' => [
                        'label' => __('Email para Dúvidas', 'lab-resumos-parceiros'),
                        'type'  => 'email',
                        'desc'  => __('Email de contato para dúvidas sobre emissão de NF', 'lab-resumos-parceiros'),
                    ],
                    'nf_service_description' => [
                        'label' => __('Descrição do Serviço na NF', 'lab-resumos-parceiros'),
                        'type'  => 'text',
                        'desc'  => __('Descrição do serviço que deve constar na NF', 'lab-resumos-parceiros'),
                    ],
                    'nf_instructions' => [
                        'label' => __('Instruções de Emissão', 'lab-resumos-parceiros'),
                        'type'  => 'wysiwyg',
                        'desc'  => __('Instruções detalhadas que serão exibidas ao afiliado sobre como emitir a NF. Suporta formatação.', 'lab-resumos-parceiros'),
                    ],
                ],
            ],
            'rpa' => [
                'title'  => __('RPA (Pessoa Física)', 'lab-resumos-parceiros'),
                'fields' => [
                    'rpa_service_description' => [
                        'label' => __('Descrição Padrão do Serviço', 'lab-resumos-parceiros'),
                        'type'  => 'text',
                        'desc'  => __('Descrição do serviço para emissão de RPA. Pode ser personalizada por afiliado.', 'lab-resumos-parceiros'),
                    ],
                ],
            ],
            'periodicity' => [
                'title'  => __('Periodicidade de Pagamento', 'lab-resumos-parceiros'),
                'fields' => [
                    'default_payment_period_months' => [
                        'label'   => __('Período Padrão', 'lab-resumos-parceiros'),
                        'type'    => 'select',
                        'options' => [
                            '1'  => __('Mensal', 'lab-resumos-parceiros'),
                            '2'  => __('Bimestral', 'lab-resumos-parceiros'),
                            '3'  => __('Trimestral', 'lab-resumos-parceiros'),
                            '4'  => __('Quadrimestral', 'lab-resumos-parceiros'),
                            '6'  => __('Semestral', 'lab-resumos-parceiros'),
                            '12' => __('Anual', 'lab-resumos-parceiros'),
                        ],
                        'desc' => __('Período padrão de pagamento para novos afiliados.', 'lab-resumos-parceiros'),
                    ],
                    'allow_affiliate_defer' => [
                        'label' => __('Permitir Adiamento pelo Afiliado', 'lab-resumos-parceiros'),
                        'type'  => 'checkbox',
                        'desc'  => __('Permite que afiliados adiem seus fechamentos para o próximo período.', 'lab-resumos-parceiros'),
                    ],
                    'defer_message' => [
                        'label' => __('Mensagem de Adiamento', 'lab-resumos-parceiros'),
                        'type'  => 'text',
                        'desc'  => __('Mensagem exibida ao afiliado sobre a opção de adiamento.', 'lab-resumos-parceiros'),
                    ],
                ],
            ],
            'emails' => [
                'title'  => __('Emails', 'lab-resumos-parceiros'),
                'fields' => [
                    'accountant_email' => [
                        'label' => __('Email do Contador', 'lab-resumos-parceiros'),
                        'type'  => 'email',
                        'desc'  => __('Recebe notificações de NFs', 'lab-resumos-parceiros'),
                    ],
                    'admin_email' => [
                        'label' => __('Email do Admin', 'lab-resumos-parceiros'),
                        'type'  => 'email',
                        'desc'  => __('Deixe vazio para usar email padrão do WordPress', 'lab-resumos-parceiros'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Renderiza campo de configuração
     *
     * @param string $key
     * @param array $field
     * @param mixed $value
     */
    public static function render_field($key, $field, $value) {
        $name = 'lrp_settings[' . $key . ']';
        
        switch ($field['type']) {
            case 'checkbox':
                ?>
                <label>
                    <input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($value); ?>>
                    <?php echo isset($field['desc']) ? esc_html($field['desc']) : ''; ?>
                </label>
                <?php
                break;
                
            case 'text':
            case 'email':
                ?>
                <input type="<?php echo esc_attr($field['type']); ?>" 
                       name="<?php echo esc_attr($name); ?>" 
                       value="<?php echo esc_attr($value); ?>" 
                       class="regular-text">
                <?php if (isset($field['desc'])): ?>
                    <p class="description"><?php echo esc_html($field['desc']); ?></p>
                <?php endif; ?>
                <?php
                break;
                
            case 'number':
                $step = $field['step'] ?? 1;
                $min = $field['min'] ?? '';
                $max = $field['max'] ?? '';
                ?>
                <input type="number" 
                       name="<?php echo esc_attr($name); ?>" 
                       value="<?php echo esc_attr($value); ?>"
                       step="<?php echo esc_attr($step); ?>"
                       <?php echo $min !== '' ? 'min="' . esc_attr($min) . '"' : ''; ?>
                       <?php echo $max !== '' ? 'max="' . esc_attr($max) . '"' : ''; ?>
                       class="small-text">
                <?php if (isset($field['desc'])): ?>
                    <p class="description"><?php echo esc_html($field['desc']); ?></p>
                <?php endif; ?>
                <?php
                break;
                
            case 'textarea':
                ?>
                <textarea name="<?php echo esc_attr($name); ?>" rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
                <?php if (isset($field['desc'])): ?>
                    <p class="description"><?php echo esc_html($field['desc']); ?></p>
                <?php endif; ?>
                <?php
                break;
            
            case 'wysiwyg':
                $editor_id = 'lrp_' . str_replace(['[', ']'], '_', $key);
                $editor_settings = [
                    'textarea_name' => $name,
                    'textarea_rows' => 8,
                    'media_buttons' => false,
                    'teeny'         => true,
                    'quicktags'     => true,
                ];
                wp_editor($value, $editor_id, $editor_settings);
                if (isset($field['desc'])): ?>
                    <p class="description"><?php echo esc_html($field['desc']); ?></p>
                <?php endif;
                break;
                
            case 'select':
                ?>
                <select name="<?php echo esc_attr($name); ?>">
                    <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                        <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($value, $opt_value); ?>>
                            <?php echo esc_html($opt_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($field['desc'])): ?>
                    <p class="description"><?php echo esc_html($field['desc']); ?></p>
                <?php endif; ?>
                <?php
                break;
        }
    }
}

