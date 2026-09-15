<?php
/**
 * Template de email: NF rejeitada
 *
 * @package Lab_Resumos_Parceiros
 *
 * Variáveis disponíveis:
 * - $affiliate      (LRP_Affiliate)
 * - $closing        (object)
 * - $affiliate_name (string)
 * - $reason         (string)
 * - $dashboard_url  (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

$period   = sprintf('%02d/%d', $closing->period_month, $closing->period_year);
$settings = LRP_Settings::instance();

echo LRP_Email_UI::hero([
    'eyebrow' => 'Ação necessária',
    'icon'    => '&#128221;', // 📝
    'title'   => 'Sua NF precisa de um ajuste',
    'tone'    => 'red',
]);

echo LRP_Email_UI::p(
    'Olá, ' . LRP_Email_UI::strong(esc_html($affiliate->get_display_name())) . '! A nota fiscal do período ' . LRP_Email_UI::strong(esc_html($period)) . ' não passou na validação. Nada foi perdido: é só reenviar corrigida.',
    ['align' => 'center']
);

echo LRP_Email_UI::note(
    LRP_Email_UI::strong('Motivo:', '#F6A6A0') . ' ' . esc_html($reason),
    ['tone' => 'red', 'icon' => '&#9888;'] // ⚠
);

echo LRP_Email_UI::h3('Dados corretos para a nova NF');

echo LRP_Email_UI::details([
    ['label' => 'Tomador',   'value' => esc_html($settings->get('company_name'))],
    ['label' => 'CNPJ',      'value' => esc_html($settings->get('company_cnpj'))],
    ['label' => 'Endereço',  'value' => esc_html($settings->get('company_address'))],
    ['label' => 'Valor',     'value' => 'R$ ' . number_format($closing->total_commissions, 2, ',', '.'), 'strong' => true],
    ['label' => 'Descrição', 'value' => 'Serviços de divulgação e indicação comercial - Período ' . esc_html($period)],
], ['tone' => 'blue']);

echo LRP_Email_UI::button($dashboard_url, 'Enviar nova NF', ['tone' => 'gold']);

echo LRP_Email_UI::p(
    'Ficou em dúvida sobre o que corrigir? Responda este e-mail que a gente explica.',
    ['align' => 'center', 'size' => 15, 'color' => LRP_Email_UI::MUTED]
);

echo LRP_Email_UI::signoff('Conte com a gente,');
