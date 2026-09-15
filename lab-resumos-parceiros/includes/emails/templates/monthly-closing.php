<?php
/**
 * Template de email: Fechamento mensal disponível
 *
 * @package Lab_Resumos_Parceiros
 *
 * Variáveis disponíveis:
 * - $affiliate     (LRP_Affiliate)
 * - $closing       (object)
 * - $total         (float)
 * - $dashboard_url (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

$period   = sprintf('%02d/%d', $closing->period_month, $closing->period_year);
$is_rpa   = $affiliate->is_rpa();
$settings = LRP_Settings::instance();

echo LRP_Email_UI::hero([
    'eyebrow' => 'Fechamento ' . $period,
    'icon'    => '&#128202;', // 📊
    'title'   => 'O resumo do seu mês está pronto',
    'tone'    => 'gold',
]);

echo LRP_Email_UI::p(
    'Olá, ' . LRP_Email_UI::strong(esc_html($affiliate->get_display_name())) . '! Fechamos o período ' . LRP_Email_UI::strong(esc_html($period)) . '. Veja como foi:',
    ['align' => 'center']
);

echo LRP_Email_UI::amount([
    'label'   => 'Suas comissões no período',
    'value'   => 'R$ ' . number_format($total, 2, ',', '.'),
    'tone'    => 'gold',
]);

echo LRP_Email_UI::details([
    ['label' => 'Vendas realizadas', 'value' => (int) $closing->total_sales],
    ['label' => 'Receita gerada',    'value' => 'R$ ' . number_format($closing->total_revenue, 2, ',', '.')],
]);

if (!empty($closing->deferred) && !empty($closing->original_period_month)) {
    echo LRP_Email_UI::note(
        'Este fechamento inclui valores acumulados de período(s) anterior(es) que ainda não tinham atingido o mínimo para saque.',
        ['tone' => 'blue', 'icon' => '&#8505;'] // ℹ
    );
}

echo LRP_Email_UI::divider();

if ($is_rpa) {

    $rpa_data = $affiliate->get_rpa_data();

    echo LRP_Email_UI::h3('Próximo passo: aguarde o RPA');

    echo LRP_Email_UI::p(
        'Você bateu o mínimo para saque. Como recebe via ' . LRP_Email_UI::strong('RPA') . ', ' . LRP_Email_UI::strong('não precisa enviar documento nenhum') . ': nossa equipe emite com os dados abaixo e paga via PIX em até 5 dias úteis.',
        ['size' => 15]
    );

    echo LRP_Email_UI::details([
        ['label' => 'Nome',     'value' => esc_html($rpa_data['nome_completo'] ?? '')],
        ['label' => 'CPF',      'value' => esc_html($rpa_data['cpf_formatted'] ?? '')],
        ['label' => 'Endereço', 'value' => esc_html($rpa_data['endereco'] ?? '')],
        ['label' => 'Telefone', 'value' => esc_html($rpa_data['telefone'] ?? '')],
    ], ['title' => 'Dados cadastrados', 'tone' => 'blue']);

    echo LRP_Email_UI::note(
        LRP_Email_UI::strong('Dado errado atrasa pagamento.', '#5C4A00') . ' Se algo acima estiver desatualizado, corrija no perfil o quanto antes.',
        ['tone' => 'gold', 'icon' => '&#9888;']
    );

    echo LRP_Email_UI::button($dashboard_url . '?tab=perfil', 'Verificar meus dados', ['tone' => 'blue']);

} else {

    echo LRP_Email_UI::h3('Próximo passo: enviar sua Nota Fiscal');

    echo LRP_Email_UI::p(
        'Você bateu o mínimo para saque. Emita a NF de prestação de serviços com os dados abaixo e envie pelo painel.',
        ['size' => 15]
    );

    echo LRP_Email_UI::details([
        ['label' => 'Tomador',   'value' => esc_html($settings->get('company_name'))],
        ['label' => 'CNPJ',      'value' => esc_html($settings->get('company_cnpj'))],
        ['label' => 'Endereço',  'value' => esc_html($settings->get('company_address'))],
        ['label' => 'Valor',     'value' => 'R$ ' . number_format($total, 2, ',', '.'), 'strong' => true],
        ['label' => 'Descrição', 'value' => 'Serviços de divulgação e indicação comercial - Período ' . esc_html($period)],
    ], ['title' => 'Dados para emissão da NF', 'tone' => 'blue']);

    echo LRP_Email_UI::button($dashboard_url . '?tab=financeiro', 'Enviar nota fiscal', ['tone' => 'gold']);

    echo LRP_Email_UI::p(
        'Depois do envio, validamos a NF e o pagamento sai via PIX em até 5 dias úteis.',
        ['align' => 'center', 'size' => 14, 'color' => LRP_Email_UI::MUTED]
    );
}

echo LRP_Email_UI::signoff();
