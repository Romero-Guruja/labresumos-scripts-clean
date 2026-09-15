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
        
        $this->send($affiliate->get_email(), $subject, $content, [
            'preheader'   => 'Seu cupom, seu link e como as comissões funcionam.',
            'footer_note' => 'Você recebeu este e-mail porque seu cadastro no Programa de Parceiros foi aprovado.',
        ]);
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
        
        $this->send($affiliate->get_email(), $subject, $content, [
            'preheader' => 'Sobre a análise do seu cadastro no Programa de Parceiros.',
        ]);
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
        
        $this->send($affiliate->get_email(), $subject, $content, [
            'preheader' => sprintf(
                'Pedido #%s - R$ %s de comissão entraram para você.',
                $order->get_id(),
                number_format($referral->get_direct_commission(), 2, ',', '.')
            ),
            'footer_note' => 'Você recebe este aviso a cada venda atribuída ao seu cupom ou link.',
        ]);
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
        
        $this->send($sponsor->get_email(), $subject, $content, [
            'preheader' => sprintf(
                '%s vendeu e R$ %s da comissão são seus.',
                $sub_affiliate->get_display_name(),
                number_format($commission->get_commission_amount(), 2, ',', '.')
            ),
            'footer_note' => 'Comissão de rede: você ganha sobre as vendas de quem você indicou.',
        ]);
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
        
        $this->send($sponsor->get_email(), $subject, $content, [
            'preheader' => sprintf(
                '%s entrou na sua rede pelo seu link de indicação.',
                $new_affiliate->get_display_name()
            ),
        ]);
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
        
        $this->send($affiliate->get_email(), $subject, $content, [
            'preheader' => $is_rpa
                ? sprintf('R$ %s liberados. Você não precisa enviar documento nenhum.', number_format($amount, 2, ',', '.'))
                : sprintf('R$ %s liberados. Falta só enviar a sua nota fiscal.', number_format($amount, 2, ',', '.')),
        ]);
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

        $closing = LRP_Closing::get($closing_id);
        if (!$closing) {
            lrp_log('Email NF aprovada: fechamento não encontrado', ['closing_id' => $closing_id], 'error');
            return;
        }

        $subject = __('✅ NF aprovada! Pagamento em breve', 'lab-resumos-parceiros');

        $content = $this->get_template('invoice-approved', [
            'affiliate'      => $affiliate,
            'closing'        => $closing,
            'affiliate_name' => $affiliate->get_display_name(),
        ]);

        $this->send($affiliate->get_email(), $subject, $content, [
            'preheader' => 'Nota fiscal validada. O PIX sai em até 5 dias úteis.',
        ]);
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

        $closing = LRP_Closing::get($closing_id);
        if (!$closing) {
            lrp_log('Email NF rejeitada: fechamento não encontrado', ['closing_id' => $closing_id], 'error');
            return;
        }

        $subject = __('⚠️ NF rejeitada - Ação necessária', 'lab-resumos-parceiros');

        $dashboard_url = add_query_arg('tab', 'financial', get_permalink(get_option('lrp_dashboard_page_id')));

        $content = $this->get_template('invoice-rejected', [
            'affiliate'      => $affiliate,
            'closing'        => $closing,
            'affiliate_name' => $affiliate->get_display_name(),
            'reason'         => $reason,
            'dashboard_url'  => $dashboard_url,
        ]);

        $this->send($affiliate->get_email(), $subject, $content, [
            'preheader' => 'Precisamos de uma correção na NF antes de pagar. É rápido.',
        ]);
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
        if (!$closing) {
            lrp_log('Email pagamento realizado: fechamento não encontrado', ['closing_id' => $closing_id], 'error');
            return;
        }

        $subject = sprintf(__('💸 Pagamento de R$ %s realizado!', 'lab-resumos-parceiros'),
            number_format($closing->total_commissions, 2, ',', '.')
        );

        $content = $this->get_template('payment-received', [
            'affiliate'      => $affiliate,
            'closing'        => $closing,
            'affiliate_name' => $affiliate->get_display_name(),
            'amount'         => wc_price($closing->total_commissions),
            'period'         => sprintf('%02d/%d', $closing->period_month, $closing->period_year),
        ]);
        
        $this->send($affiliate->get_email(), $subject, $content, [
            'preheader' => sprintf(
                'R$ %s enviados via PIX para a chave do seu perfil.',
                number_format($closing->total_commissions, 2, ',', '.')
            ),
        ]);
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
        
        $this->send($accountant_email, $subject, $content, [
            'preheader'   => sprintf(
                '%s - R$ %s - período %s.',
                $affiliate->get_display_name(),
                number_format($closing->total_commissions, 2, ',', '.'),
                sprintf('%02d/%d', $closing->period_month, $closing->period_year)
            ),
            'footer_note' => 'Mensagem interna do Programa de Parceiros.',
        ]);
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

        $content = $this->get_template('accountant-rpa-ready', [
            'affiliate'      => $affiliate,
            'affiliate_name' => $affiliate->get_display_name(),
            'rpa_data'       => $rpa_data,
            'amount'         => wc_price($amount),
            'accountant_url' => $accountant_url,
        ]);

        $this->send($accountant_email, $subject, $content, [
            'preheader' => sprintf(
                'R$ %s para %s. Emitir o RPA e aprovar no painel.',
                number_format($amount, 2, ',', '.'),
                $affiliate->get_display_name()
            ),
            'footer_note' => 'Mensagem interna do Programa de Parceiros.',
        ]);
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
        
        $this->send($admin_email, $subject, $content, [
            'preheader'   => sprintf('%s aguarda aprovação no Programa de Parceiros.', $affiliate->get_display_name()),
            'footer_note' => 'Mensagem interna do Programa de Parceiros.',
        ]);
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
     * @param array  $args     preheader (linha de prévia na caixa de entrada),
     *                         footer_note (linha final específica do e-mail)
     * @return bool
     */
    private function send($to, $subject, $content, $args = []) {
        $from_name  = 'Lab Resumos';
        $from_email = apply_filters('lrp_email_from_address', get_option('admin_email'));

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        // Wrap em template HTML
        $html = $this->wrap_html($subject, $content, $args);

        return wp_mail($to, $subject, $html, $headers);
    }

    /**
     * Envolve o conteúdo no "envelope" visual da Lab Resumos.
     *
     * Estrutura: fundo navy -> cartão creme 600px -> cabeçalho com a marca ->
     * conteúdo -> rodapé. Cores travadas contra o dark mode dos clientes
     * (ver LRP_Email_UI::bg / ::ink para a explicação da técnica).
     *
     * @param string $subject
     * @param string $content
     * @param array  $args    preheader, footer_note
     * @return string
     */
    private function wrap_html($subject, $content, $args = []) {
        $args = array_merge([
            'preheader'   => '',
            'footer_note' => '',
        ], $args);

        $shell  = LRP_Email_UI::SHELL;
        $navy   = LRP_Email_UI::NAVY;
        $gold   = LRP_Email_UI::GOLD;
        $font   = LRP_Email_UI::FONT;
        $site   = home_url('/');

        // Linha de prévia: aparece ao lado do assunto na caixa de entrada e
        // some no corpo da mensagem.
        $preheader = '';
        if ($args['preheader'] !== '') {
            $preheader = '<div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; opacity:0;">'
                . esc_html($args['preheader'])
                . str_repeat('&#847;&zwnj;&nbsp;', 60)
                . '</div>';
        }

        $footer_note = $args['footer_note'] !== ''
            ? '<div style="font-family:' . $font . '; font-size:12px; line-height:1.6; text-align:center; margin:0 0 10px; ' . LRP_Email_UI::ink('#9AA2AF') . '">' . esc_html($args['footer_note']) . '</div>'
            : '';

        $html = '<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="pt-BR">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="x-apple-disable-message-reformatting" />
<meta name="color-scheme" content="dark" />
<meta name="supported-color-schemes" content="dark" />
<title>' . esc_html($subject) . '</title>
<!--LRP_GMAIL_INK_STYLES-->
</head>
<body class="body" style="margin:0; padding:0; width:100%; ' . LRP_Email_UI::bg($shell) . '">
' . $preheader . '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' . $shell . '" style="' . LRP_Email_UI::bg($shell) . '">
<tr>
<td align="center" style="padding:28px 12px; ' . LRP_Email_UI::bg($shell) . '">

  <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="' . $navy . '" style="width:600px; max-width:100%; border-radius:22px; overflow:hidden; ' . LRP_Email_UI::bg($navy) . '">

    <tr>
      <td bgcolor="' . $shell . '" style="padding:26px 30px; border-bottom:1px solid ' . LRP_Email_UI::LINE . '; ' . LRP_Email_UI::bg($shell) . '">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td align="left" valign="middle">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
              <td width="8" valign="middle" style="width:8px; padding:0 10px 0 0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                  <td width="8" height="30" bgcolor="' . $gold . '" style="width:8px; height:30px; border-radius:3px; line-height:30px; font-size:0; ' . LRP_Email_UI::bg($gold) . '">&nbsp;</td>
                </tr></table>
              </td>
              <td valign="middle" style="font-family:' . $font . ';">
                <div style="font-size:19px; font-weight:bold; letter-spacing:-0.01em; line-height:1.15; ' . LRP_Email_UI::ink($gold) . '">LAB RESUMOS</div>
                <div style="font-size:11px; font-weight:bold; letter-spacing:1.4px; text-transform:uppercase; line-height:1.3; margin:3px 0 0; ' . LRP_Email_UI::ink('#9AA2AF') . '">Programa de Parceiros</div>
              </td>
            </tr></table>
          </td>
        </tr></table>
      </td>
    </tr>

    <tr>
      <td bgcolor="' . $navy . '" style="padding:36px 30px 34px; ' . LRP_Email_UI::bg($navy) . '">
' . $content . '
      </td>
    </tr>

    <tr>
      <td align="center" bgcolor="' . $shell . '" style="padding:24px 30px; border-top:1px solid ' . LRP_Email_UI::LINE . '; ' . LRP_Email_UI::bg($shell) . '">
        ' . $footer_note . '
        <div style="font-family:' . $font . '; font-size:12px; line-height:1.6; text-align:center; margin:0 0 6px; ' . LRP_Email_UI::ink('#9AA2AF') . '">
          Dúvidas? É só responder este e-mail - uma pessoa de verdade vai ler.
        </div>
        <div style="font-family:' . $font . '; font-size:11px; line-height:1.6; text-align:center; margin:0; ' . LRP_Email_UI::ink('#8A919E') . '">
          <a href="' . esc_url($site) . '" style="text-decoration:none; ' . LRP_Email_UI::ink('#9AA2AF') . '">labresumos.com.br</a>
          &nbsp;&middot;&nbsp; &copy; ' . esc_html(date('Y')) . ' Lab Resumos
        </div>
      </td>
    </tr>

  </table>

</td>
</tr>
</table>
</body>
</html>';

        // Camada 1: toda tag com `color:#hex` inline ganha a classe lr-ink-<hex>.
        // Camada 2: uma regra `u + .body .lr-ink-<hex>` por cor, só no Gmail.
        // Ver LRP_Email_UI::ink() para o porquê.
        $html = LRP_Email_UI::bind_ink_classes($html);
        $html = str_replace('<!--LRP_GMAIL_INK_STYLES-->', LRP_Email_UI::gmail_ink_styles(), $html);
        LRP_Email_UI::$inks = [];

        return $html;
    }
}

