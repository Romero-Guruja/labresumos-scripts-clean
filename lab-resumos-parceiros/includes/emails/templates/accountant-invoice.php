<?php
/**
 * Template de email: NF/RPA recebido para o contador (interno)
 *
 * @package Lab_Resumos_Parceiros
 *
 * Variáveis disponíveis:
 * - $affiliate      (LRP_Affiliate)
 * - $closing        (object)
 * - $accountant_url (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!$affiliate || !$closing) {
    return;
}

$period = sprintf('%02d/%d', $closing->period_month, $closing->period_year);
$is_rpa = $affiliate->is_rpa();

echo LRP_Email_UI::hero([
    'eyebrow' => 'Interno &middot; financeiro',
    'icon'    => $is_rpa ? '&#128203;' : '&#128196;', // 📋 / 📄
    'title'   => $is_rpa ? 'RPA pronto para emissão' : 'Nova NF para validação',
    'tone'    => 'blue',
]);

echo LRP_Email_UI::amount([
    'label' => 'Valor do fechamento',
    'value' => 'R$ ' . number_format($closing->total_commissions, 2, ',', '.'),
    'caption' => $is_rpa ? 'Pessoa física &middot; RPA' : 'Pessoa jurídica &middot; NF',
    'tone'  => 'blue',
]);

echo LRP_Email_UI::details([
    ['label' => 'Parceiro', 'value' => esc_html($affiliate->get_display_name()), 'strong' => true],
    ['label' => 'Email',    'value' => esc_html($affiliate->get_email())],
    ['label' => 'Período',  'value' => esc_html($period)],
]);

if ($is_rpa) {

    $rpa_data = $affiliate->get_rpa_data();

    echo LRP_Email_UI::details([
        ['label' => 'Nome completo', 'value' => esc_html($rpa_data['nome_completo'] ?? '')],
        ['label' => 'CPF',           'value' => esc_html($rpa_data['cpf_formatted'] ?? '')],
        ['label' => 'Endereço',      'value' => esc_html($rpa_data['endereco'] ?? '')],
        ['label' => 'Telefone',      'value' => esc_html($rpa_data['telefone'] ?? '')],
        ['label' => 'Email',         'value' => esc_html($rpa_data['email'] ?? '')],
        ['label' => 'INSS/PIS',      'value' => esc_html($rpa_data['inss_number'] ?? '')],
        ['label' => 'Serviço',       'value' => esc_html($rpa_data['descricao_servico'] ?? '')],
    ], ['title' => 'Dados do autônomo para o RPA', 'tone' => 'neutral']);

} else {

    echo LRP_Email_UI::details([
        ['label' => 'NF número',     'value' => esc_html($closing->invoice_number ?: 'Não informado')],
        ['label' => 'Enviada em',    'value' => esc_html(date('d/m/Y H:i', strtotime($closing->invoice_uploaded_at)))],
        ['label' => 'CNPJ',          'value' => esc_html($affiliate->get_company_cnpj_formatted())],
        ['label' => 'Razão social',  'value' => esc_html($affiliate->get_company_name())],
    ], ['title' => 'Empresa emissora', 'tone' => 'neutral']);

    echo LRP_Email_UI::note('A NF está anexada a este e-mail.', ['tone' => 'blue', 'icon' => '&#128206;']); // 📎
}

if ($affiliate->get_data('payment_method') === 'pix') {
    $payment_rows = [
        ['label' => 'Método',       'value' => 'PIX'],
        ['label' => 'Tipo de chave', 'value' => esc_html(strtoupper($affiliate->get_data('pix_key_type')))],
        ['label' => 'Chave PIX',    'value' => esc_html($affiliate->get_decrypted_pix_key()), 'strong' => true],
    ];
} else {
    $payment_rows = [
        ['label' => 'Método',  'value' => 'Transferência bancária'],
        ['label' => 'Banco',   'value' => esc_html($affiliate->get_data('bank_name'))],
        ['label' => 'Agência', 'value' => esc_html($affiliate->get_data('bank_agency'))],
        ['label' => 'Conta',   'value' => esc_html($affiliate->get_data('bank_account'))],
    ];
}

$payment_rows[] = ['label' => 'Titular',   'value' => esc_html($affiliate->get_data('holder_name'))];
$payment_rows[] = ['label' => 'CPF/CNPJ',  'value' => esc_html($affiliate->get_data('holder_document'))];

echo LRP_Email_UI::details($payment_rows, ['title' => 'Dados de pagamento', 'tone' => 'gold']);

echo LRP_Email_UI::button($accountant_url, 'Abrir área do contador', ['tone' => 'blue']);
