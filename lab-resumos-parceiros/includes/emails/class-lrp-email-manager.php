<?php
/**
 * Gerenciador de Emails
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Email_Manager
 * 
 * Gerencia todos os emails automatizados.
 */
class LRP_Email_Manager {

    /**
     * Instância única
     *
     * @var LRP_Email_Manager|null
     */
    private static $instance = null;

    /**
     * Retorna instância única
     *
     * @return LRP_Email_Manager
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado
     */
    private function __construct() {
        // Afiliado aprovado
        add_action('lrp_affiliate_approved', [$this, 'send_welcome_email']);
        
        // Afiliado rejeitado
        add_action('lrp_affiliate_rejected', [$this, 'send_rejection_email'], 10, 2);
        
        // Referral aprovado (pedido pago) — envia e-mails de venda e comissão de rede
        add_action('lrp_referral_approved', [$this, 'on_referral_approved']);
        
        // Novo sub-afiliado
        add_action('lrp_new_sub_affiliate', [$this, 'send_new_sub_affiliate_email'], 10, 2);
        
        // Fechamento pronto para NF
        add_action('lrp_closing_ready', [$this, 'send_closing_ready_email'], 10, 3);
        
        // NF aprovada
        add_action('lrp_invoice_approved', [$this, 'send_invoice_approved_email'], 10, 2);
        
        // NF rejeitada
        add_action('lrp_invoice_rejected', [$this, 'send_invoice_rejected_email'], 10, 3);
        
        // Pagamento realizado
        add_action('lrp_payment_completed', [$this, 'send_payment_completed_email'], 10, 2);
        
        // NF recebida (para contador)
        add_action('lrp_invoice_received', [$this, 'send_invoice_received_to_accountant'], 10, 2);
        
        // RPA pronto para emissão (para financeiro) (v1.7.1)
        add_action('lrp_rpa_ready', [$this, 'send_rpa_ready_to_accountant'], 10, 3);
        
        // Novo cadastro (para admin)
        add_action('lrp_new_affiliate_application', [$this, 'send_new_application_to_admin']);
    }

    /**
     * Quando um referral é aprovado (pedido pago), envia e-mails de venda direta
     * e de comissão de rede (sub-afiliado).
     *
     * @param LRP_Referral $referral
     */
    public function on_referral_approved($referral) {
        if (!$referral) {
            return;
        }

        try {
            $affiliate = new LRP_Affiliate($referral->get_affiliate_id());
            $order = wc_get_order($referral->get_order_id());

            $this->send_new_sale_email($affiliate, $referral, $order);

            $commissions = LRP_Commission::get_by_referral($referral->get_id());
            foreach ($commissions as $commission) {
                $type = $commission->get_commission_type();
                if ($type === 'level_2' || $type === 'level_3') {
                    $sponsor = new LRP_Affiliate($commission->get_affiliate_id());
                    $sub_affiliate = new LRP_Affiliate($commission->get_source_affiliate_id());
                    $this->send_sub_affiliate_sale_email($sponsor, $sub_affiliate, $commission, $referral);
                }
            }
        } catch (\Throwable $e) {
            lrp_log('Erro ao enviar e-mails de venda aprovada', [
                'referral_id' => $referral->get_id(),
                'error'       => $e->getMessage(),
            ], 'error');

            lrp_send_telegram_alert(
                'Erro ao enviar e-mail de venda aprovada',
                'Referral #' . $referral->get_id() . ' — ' . $e->getMessage()
            );
        }
    }

    /**
     * Email de boas-vindas
     *
     * @param LRP_Affiliate $affiliate
     */
    public function send_welcome_email($affiliate) {
        if (!$affiliate) {
            $this->alert_null_object(__METHOD__, 'affiliate');
            return;
        }

        $subject = __('🎉 Bem-vindo ao Programa de Parceiros Lab Resumos!', 'lab-resumos-parceiros');
        
        $dashboard_url = get_permalink(get_option('lrp_dashboard_page_id'));
        
        $content = $this->get_template('welcome', [
            'affiliate_name' => $affiliate->get_display_name(),
            'coupon_code'    => $affiliate->get_coupon_code(),
            'referral_url'   => $affiliate->get_referral_url(),
            'dashboard_url'  => $dashboard_url,
        ]);
        
        $this->send($affiliate->get_email(), $subject, $content);
    }

    /**
     * Email de rejeição
     *
     * @param LRP_Affiliate $affiliate
     * @param string $reason
     */
    public function send_rejection_email($affiliate, $reason = '') {
        if (!$affiliate) {
            $this->alert_null_object(__METHOD__, 'affiliate');
            return;
        }

        $subject = __('Sobre seu cadastro no Programa de Parceiros', 'lab-resumos-parceiros');
        
        $content = $this->get_template('rejection', [
            'affiliate_name' => $affiliate->get_display_name(),
            'reason'         => $reason,
        ]);
        
        $this->send($affiliate->get_email(), $subject, $content);
    }

    /**
     * Email de nova venda
     *
     * @param LRP_Affiliate $affiliate
     * @param LRP_Referral $referral
     * @param WC_Order $order
     */
    public function send_new_sale_email($affiliate, $referral, $order) {
        if (!$affiliate || !$referral || !$order) {
            $this->alert_null_object(__METHOD__, !$affiliate ? 'affiliate' : (!$referral ? 'referral' : 'order'));
            return;
        }

        $subject = sprintf(__('💰 Nova venda! +R$ %s em comissão', 'lab-resumos-parceiros'), 
            number_format($referral->get_direct_commission(), 2, ',', '.')
        );
        
        $dashboard_url = get_permalink(get_option('lrp_dashboard_page_id'));
        
        $content = $this->get_template('new-sale', [
            'affiliate_name'  => $affiliate->get_display_name(),
            'order_id'        => $order->get_id(),
            'order_total'     => wc_price($referral->get_commission_base()),
            'commission'      => wc_price($referral->get_direct_commission()),
            'attribution'     => $referral->get_attribution_type() === 'coupon' ? __('Cupom', 'lab-resumos-parceiros') : __('Link', 'lab-resumos-parceiros'),
            'dashboard_url'   => $dashboard_url,
        ]);
        
        $this->send($affiliate->get_email(), $subject, $content);
    }

    /**
     * Email de venda de sub-afiliado
     *
     * @param LRP_Affiliate $sponsor
     * @param LRP_Affiliate $sub_affiliate
     * @param LRP_Commission $commission
     * @param LRP_Referral $referral
     */
    public function send_sub_affiliate_sale_email($sponsor, $sub_affiliate, $commission, $referral) {
        if (!$sponsor || !$sub_affiliate || !$commission || !$referral) {
            $null_param = !$sponsor ? 'sponsor' : (!$sub_affiliate ? 'sub_affiliate' : (!$commission ? 'commission' : 'referral'));
            $this->alert_null_object(__METHOD__, $null_param);
            return;
        }

        $level = $commission->get_commission_type() === 'level_2' ? 2 : 3;
        
        $subject = sprintf(__('💰 Comissão de rede! +R$ %s do nível %d', 'lab-resumos-parceiros'), 
            number_format($commission->get_commission_amount(), 2, ',', '.'),
            $level
        );
        
        $content = $this->get_template('sub-affiliate-sale', [
            'sponsor'          => $sponsor,
            'sub_affiliate'    => $sub_affiliate,
            'commission'       => $commission,
            'referral'         => $referral,
            'level'            => $level,
            'sponsor_name'     => $sponsor->get_display_name(),
        ]);
        
        $this->send($sponsor->get_email(), $subject, $content);
    }

    /**
     * Email de novo sub-afiliado
     *
     * @param LRP_Affiliate $sponsor
     * @param LRP_Affiliate $new_affiliate
     */
    public function send_new_sub_affiliate_email($sponsor, $new_affiliate) {
        if (!$sponsor || !$new_affiliate) {
            $this->alert_null_object(__METHOD__, !$sponsor ? 'sponsor' : 'new_affiliate');
            return;
        }

        $subject = __('👥 Novo membro na sua rede!', 'lab-resumos-parceiros');
        
        $content = $this->get_template('new-sub-affiliate', [
            'sponsor'            => $sponsor,
            'new_affiliate'      => $new_affiliate,
            'sponsor_name'       => $sponsor->get_display_name(),
            'new_affiliate_name' => $new_affiliate->get_display_name(),
            'commission_l2'      => $sponsor->get_commission_rate('l2') . '%',
        ]);
        
        $this->send($sponsor->get_email(), $subject, $content);
    }

    /**
     * Email de fechamento pronto
     *
     * @param LRP_Affiliate $affiliate
     * @param int $closing_id
     * @param float $amount
     */
    public function send_closing_ready_email($affiliate, $closing_id, $amount) {
        if (!$affiliate) {
            $this->alert_null_object(__METHOD__, 'affiliate', ['closing_id' => $closing_id]);
            return;
        }

        $subject = sprintf(__('📄 R$ %s disponível para saque!', 'lab-resumos-parceiros'), 
            number_format($amount, 2, ',', '.')
        );
        
        $dashboard_url = add_query_arg('tab', 'financial', get_permalink(get_option('lrp_dashboard_page_id')));
        $settings = LRP_Settings::instance();
        $company = $settings->get_company_data();
        
        $is_rpa = $affiliate->is_rpa();
        $template_vars = [
            'affiliate_name' => $affiliate->get_display_name(),
            'amount'         => wc_price($amount),
            'company_name'   => $company['name'],
            'company_cnpj'   => $company['cnpj'],
            'company_address'=> $company['address'],
            'dashboard_url'  => $dashboard_url,
            'billing_type'   => $is_rpa ? 'rpa' : 'pj',
        ];

        if ($is_rpa) {
            $template_vars['rpa_data'] = $affiliate->get_rpa_data();
        }
        
        $content = $this->get_template('closing-ready', $template_vars);
        
        $this->send($affiliate->get_email(), $subject, $content);
    }

    /**
     * Email de NF aprovada
     *
     * @param LRP_Affiliate $affiliate
     * @param int $closing_id
     */
    public function send_invoice_approved_email($affiliate, $closing_id) {
        if (!$affiliate) {
            $this->alert_null_object(__METHOD__, 'affiliate', ['closing_id' => $closing_id]);
            return;
        }

        $subject = __('✅ NF aprovada! Pagamento em breve', 'lab-resumos-parceiros');
        
        $content = $this->get_template('invoice-approved', [
            'affiliate_name' => $affiliate->get_display_name(),
        ]);
        
        $this->send($affiliate->get_email(), $subject, $content);
    }

    /**
     * Email de NF rejeitada
     *
     * @param LRP_Affiliate $affiliate
     * @param int $closing_id
     * @param string $reason
     */
    public function send_invoice_rejected_email($affiliate, $closing_id, $reason) {
        if (!$affiliate) {
            $this->alert_null_object(__METHOD__, 'affiliate', ['closing_id' => $closing_id]);
            return;
        }

        $subject = __('⚠️ NF rejeitada - Ação necessária', 'lab-resumos-parceiros');
        
        $dashboard_url = add_query_arg('tab', 'financial', get_permalink(get_option('lrp_dashboard_page_id')));
        
        $content = $this->get_template('invoice-rejected', [
            'affiliate_name' => $affiliate->get_display_name(),
            'reason'         => $reason,
            'dashboard_url'  => $dashboard_url,
        ]);
        
        $this->send($affiliate->get_email(), $subject, $content);
    }

    /**
     * Email de pagamento realizado
     *
     * @param LRP_Affiliate $affiliate
     * @param int $closing_id
     */
    public function send_payment_completed_email($affiliate, $closing_id) {
        if (!$affiliate) {
            $this->alert_null_object(__METHOD__, 'affiliate', ['closing_id' => $closing_id]);
            return;
        }

        $closing = LRP_Closing::get($closing_id);
        
        $subject = sprintf(__('💸 Pagamento de R$ %s realizado!', 'lab-resumos-parceiros'), 
            number_format($closing->total_commissions, 2, ',', '.')
        );
        
        $content = $this->get_template('payment-completed', [
            'affiliate_name' => $affiliate->get_display_name(),
            'amount'         => wc_price($closing->total_commissions),
            'period'         => sprintf('%02d/%d', $closing->period_month, $closing->period_year),
        ]);
        
        $this->send($affiliate->get_email(), $subject, $content);
    }

    /**
     * Email de NF recebida para contador
     *
     * @param LRP_Affiliate $affiliate
     * @param int $closing_id
     */
    public function send_invoice_received_to_accountant($affiliate, $closing_id) {
        if (!$affiliate) {
            $this->alert_null_object(__METHOD__, 'affiliate', ['closing_id' => $closing_id]);
            return;
        }

        $settings = LRP_Settings::instance();
        $accountant_email = $settings->get_accountant_email();
        
        if (empty($accountant_email)) {
            return;
        }
        
        $closing = LRP_Closing::get($closing_id);
        
        if (!$closing) {
            lrp_log('Email contador: fechamento não encontrado', ['closing_id' => $closing_id], 'error');
            return;
        }
        
        $is_rpa = $affiliate->is_rpa();
        $subject = sprintf(
            $is_rpa 
                ? __('📋 RPA para emissão - %s - R$ %s', 'lab-resumos-parceiros')
                : __('📄 NF recebida de %s - R$ %s', 'lab-resumos-parceiros'), 
            $affiliate->get_display_name(),
            number_format($closing->total_commissions, 2, ',', '.')
        );
        
        $accountant_url = admin_url('admin.php?page=lrp-accountant-invoices&action=view&id=' . $closing_id);
        
        $content = $this->get_template('accountant-invoice', [
            'affiliate'      => $affiliate,
            'closing'        => $closing,
            'affiliate_name' => $affiliate->get_display_name(),
            'amount'         => wc_price($closing->total_commissions),
            'period'         => sprintf('%02d/%d', $closing->period_month, $closing->period_year),
            'invoice_number' => $closing->invoice_number,
            'accountant_url' => $accountant_url,
            'admin_url'      => $accountant_url,
        ]);
        
        $this->send($accountant_email, $subject, $content);
    }

    /**
     * Email de RPA pronto para emissão (v1.7.1) — notifica financeiro
     *
     * @param LRP_Affiliate $affiliate
     * @param int $closing_id
     * @param float $amount
     */
    public function send_rpa_ready_to_accountant($affiliate, $closing_id, $amount) {
        if (!$affiliate) {
            $this->alert_null_object(__METHOD__, 'affiliate', ['closing_id' => $closing_id]);
            return;
        }

        $settings = LRP_Settings::instance();
        $accountant_email = $settings->get_accountant_email();
        
        if (empty($accountant_email)) {
            return;
        }

        $rpa_data = $affiliate->get_rpa_data();

        $subject = sprintf(
            __('📋 RPA para emissão - %s - R$ %s', 'lab-resumos-parceiros'),
            $affiliate->get_display_name(),
            number_format($amount, 2, ',', '.')
        );

        $accountant_url = admin_url('admin.php?page=lrp-accountant-invoices');

        $content = '<h2 style="color: #2A6B9F; margin-top: 0;">RPA para Emissão</h2>';
        $content .= '<p>Um novo RPA precisa ser emitido para pagamento de parceiro.</p>';
        
        $content .= '<div style="background-color: #d1ecf1; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #17a2b8;">';
        $content .= '<h3 style="margin: 0 0 15px 0; color: #0c5460;">Dados do Parceiro</h3>';
        $content .= '<p style="margin: 0 0 8px 0;"><strong>Nome:</strong> ' . esc_html($rpa_data['nome_completo'] ?? $affiliate->get_display_name()) . '</p>';
        $content .= '<p style="margin: 0 0 8px 0;"><strong>CPF:</strong> ' . esc_html($rpa_data['cpf_formatted'] ?? '') . '</p>';
        if (!empty($rpa_data['data_nascimento_fmt'])) {
            $content .= '<p style="margin: 0 0 8px 0;"><strong>Data de Nascimento:</strong> ' . esc_html($rpa_data['data_nascimento_fmt']) . '</p>';
        }
        $content .= '<p style="margin: 0 0 8px 0;"><strong>Endereço:</strong> ' . esc_html($rpa_data['endereco'] ?? '') . '</p>';
        $content .= '<p style="margin: 0 0 8px 0;"><strong>Telefone:</strong> ' . esc_html($rpa_data['telefone'] ?? '') . '</p>';
        if (!empty($rpa_data['inss_number'])) {
            $content .= '<p style="margin: 0 0 8px 0;"><strong>INSS/PIS:</strong> ' . esc_html($rpa_data['inss_number']) . '</p>';
        }
        $content .= '<p style="margin: 0;"><strong>Serviço:</strong> ' . esc_html($rpa_data['descricao_servico'] ?? 'Serviços de divulgação e indicação comercial') . '</p>';
        $content .= '</div>';

        $content .= '<div style="background-color: #cce5ff; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;">';
        $content .= '<p style="margin: 0; font-size: 14px; color: #004085;">Valor do RPA:</p>';
        $content .= '<p style="margin: 10px 0; font-size: 28px; font-weight: bold; color: #2A6B9F;">' . wc_price($amount) . '</p>';
        $content .= '</div>';

        $content .= '<div style="text-align: center; margin: 30px 0;">';
        $content .= '<a href="' . esc_url($accountant_url) . '" style="display: inline-block; background-color: #17a2b8; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Ver RPAs Pendentes</a>';
        $content .= '</div>';

        $content .= '<p style="color: #666; font-size: 14px;">Após emitir o RPA, acesse o painel para aprovar e prosseguir com o pagamento.</p>';

        $this->send($accountant_email, $subject, $content);
    }

    /**
     * Email de novo cadastro para admin
     *
     * @param LRP_Affiliate $affiliate
     */
    public function send_new_application_to_admin($affiliate) {
        if (!$affiliate) {
            $this->alert_null_object(__METHOD__, 'affiliate');
            return;
        }

        $settings = LRP_Settings::instance();
        $admin_email = $settings->get_admin_email();
        
        $subject = sprintf(__('👤 Novo cadastro de parceiro: %s', 'lab-resumos-parceiros'), 
            $affiliate->get_display_name()
        );
        
        $admin_url = admin_url('admin.php?page=lrp-affiliates&action=view&id=' . $affiliate->get_id());
        
        $content = $this->get_template('admin-new-affiliate', [
            'affiliate'  => $affiliate,
            'admin_url'  => $admin_url,
        ]);
        
        $this->send($admin_email, $subject, $content);
    }

    /**
     * Loga e alerta sobre objeto nulo recebido em método de e-mail
     *
     * @param string $method Nome do método que detectou o problema
     * @param string $param Nome do parâmetro nulo
     * @param array $context Dados extras para o log
     */
    private function alert_null_object($method, $param, $context = []) {
        $msg = sprintf('[Lab Resumos Parceiros] %s: parâmetro $%s é null', $method, $param);
        error_log($msg . ($context ? ' | ' . wp_json_encode($context) : ''));

        lrp_send_telegram_alert(
            'Objeto nulo em e-mail de parceiros',
            sprintf('%s — $%s é null. %s', $method, $param, $context ? wp_json_encode($context) : '')
        );
    }

    /**
     * Obtém template de email
     *
     * @param string $template
     * @param array $vars
     * @return string
     */
    private function get_template($template, $vars = []) {
        $template_file = LRP_PLUGIN_DIR . 'includes/emails/templates/' . $template . '.php';
        
        if (!file_exists($template_file)) {
            // Template básico
            return $this->get_basic_template($vars);
        }
        
        extract($vars);
        
        ob_start();
        include $template_file;
        return ob_get_clean();
    }

    /**
     * Template básico
     *
     * @param array $vars
     * @return string
     */
    private function get_basic_template($vars) {
        $content = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">';
        
        foreach ($vars as $key => $value) {
            if ($key === 'affiliate_name') {
                $content .= '<p>' . sprintf(__('Olá, %s!', 'lab-resumos-parceiros'), esc_html($value)) . '</p>';
            }
        }
        
        $content .= '</div>';
        
        return $content;
    }

    /**
     * Envia email
     *
     * @param string $to
     * @param string $subject
     * @param string $content
     * @return bool
     */
    private function send($to, $subject, $content) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Lab Resumos <' . get_option('admin_email') . '>',
        ];
        
        // Wrap em template HTML
        $html = $this->wrap_html($subject, $content);
        
        return wp_mail($to, $subject, $html, $headers);
    }

    /**
     * Envolve conteúdo em HTML
     *
     * @param string $subject
     * @param string $content
     * @return string
     */
    private function wrap_html($subject, $content) {
        $logo_url = ''; // Pode ser configurável
        
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($subject) . '</title>
        </head>
        <body style="margin: 0; padding: 0; background-color: #f4f4f4;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                            <!-- Header -->
                            <tr>
                                <td style="background-color: #2A6B9F; padding: 30px; text-align: center;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Lab Resumos</h1>
                                    <p style="color: #ffffff; margin: 10px 0 0 0; opacity: 0.9;">Programa de Parceiros</p>
                                </td>
                            </tr>
                            <!-- Content -->
                            <tr>
                                <td style="padding: 30px;">
                                    ' . $content . '
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666;">
                                    <p style="margin: 0;">&copy; ' . date('Y') . ' Lab Resumos. Todos os direitos reservados.</p>
                                    <p style="margin: 5px 0 0 0;">Este email foi enviado automaticamente. Por favor, não responda.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }
}

