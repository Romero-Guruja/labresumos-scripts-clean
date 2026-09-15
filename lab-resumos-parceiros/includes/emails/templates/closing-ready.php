<?php
/**
 * Template de email: Fechamento pronto / saque disponível
 *
 * @package Lab_Resumos_Parceiros
 *
 * Variáveis disponíveis:
 * - $affiliate_name  (string)
 * - $amount          (string, já formatado por wc_price)
 * - $company_name    (string)
 * - $company_cnpj    (string)
 * - $company_address (string)
 * - $dashboard_url   (string)
 * - $billing_type    (string) 'rpa' | 'pj'
 * - $rpa_data        (array, apenas quando billing_type = rpa)
 * - $period_label    (string, opcional)
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_rpa = isset($billing_type) && $billing_type === 'rpa';

echo LRP_Email_UI::hero([
    'eyebrow' => 'Saque liberado',
    'icon'    => '&#128181;', // 💵
    'title'   => 'Suas comissões estão prontas para saque',
    'tone'    => 'gold',
]);

echo LRP_Email_UI::p(
    'Olá, ' . LRP_Email_UI::strong(esc_html($affiliate_name)) . '! Você bateu o valor mínimo e o fechamento já está fechado do nosso lado.',
    ['align' => 'center']
);

echo LRP_Email_UI::amount([
    'label'   => 'Disponível para saque',
    'value'   => $amount,
    'caption' => !empty($period_label) ? 'Período: ' . esc_html($period_label) : '',
    'tone'    => 'gold',
]);

if ($is_rpa) {

    echo LRP_Email_UI::h3('Próximo passo: nenhum');

    echo LRP_Email_UI::p(
        'Você recebe via ' . LRP_Email_UI::strong('RPA (Recibo de Pagamento Autônomo)') . ', então ' . LRP_Email_UI::strong('não precisa enviar nenhum documento') . '. Nossa equipe emite o RPA com os dados abaixo e paga via PIX em até 5 dias úteis.',
        ['size' => 15]
    );

    echo LRP_Email_UI::details([
        ['label' => 'Nome',       'value' => esc_html($rpa_data['nome_completo'] ?? $affiliate_name)],
        ['label' => 'CPF',        'value' => esc_html($rpa_data['cpf_formatted'] ?? '')],
        ['label' => 'Nascimento', 'value' => esc_html($rpa_data['data_nascimento_fmt'] ?? '')],
        ['label' => 'Endereço',   'value' => esc_html($rpa_data['endereco'] ?? '')],
        ['label' => 'Telefone',   'value' => esc_html($rpa_data['telefone'] ?? '')],
        ['label' => 'INSS/PIS',   'value' => esc_html($rpa_data['inss_number'] ?? '')],
    ], ['title' => 'Dados que vamos usar no RPA', 'tone' => 'blue']);

    echo LRP_Email_UI::note(
        LRP_Email_UI::strong('Confira os dados acima.', LRP_Email_UI::GOLD_SOFT) . ' Qualquer coisa errada atrasa o seu pagamento - corrija no perfil antes que a gente emita.',
        ['tone' => 'gold', 'icon' => '&#9888;'] // ⚠
    );

    echo LRP_Email_UI::button($dashboard_url . '?tab=perfil', 'Conferir meus dados', ['tone' => 'blue']);

} else {

    echo LRP_Email_UI::h3('Próximo passo: enviar sua Nota Fiscal');

    echo LRP_Email_UI::p(
        'Emita uma NF de prestação de serviços com os dados abaixo e envie pelo painel. Depois da validação, o PIX sai em até 5 dias úteis.',
        ['size' => 15]
    );

    echo LRP_Email_UI::details([
        ['label' => 'Razão social', 'value' => esc_html($company_name)],
        ['label' => 'CNPJ',         'value' => esc_html($company_cnpj)],
        ['label' => 'Endereço',     'value' => nl2br(esc_html($company_address))],
        ['label' => 'Valor',        'value' => $amount, 'strong' => true],
        ['label' => 'Descrição',    'value' => 'Serviços de divulgação e indicação comercial'],
    ], ['title' => 'Dados para emissão da NF', 'tone' => 'blue']);

    echo LRP_Email_UI::button($dashboard_url . '?tab=financeiro', 'Enviar nota fiscal', ['tone' => 'gold']);
}

echo LRP_Email_UI::signoff();
