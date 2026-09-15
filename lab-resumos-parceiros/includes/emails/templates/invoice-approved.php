<?php
/**
 * Template de email: NF aprovada
 *
 * @package Lab_Resumos_Parceiros
 *
 * Variáveis disponíveis:
 * - $affiliate      (LRP_Affiliate)
 * - $closing        (object)
 * - $affiliate_name (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

$period = sprintf('%02d/%d', $closing->period_month, $closing->period_year);

echo LRP_Email_UI::hero([
    'eyebrow' => 'Nota fiscal aprovada',
    'icon'    => '&#9989;', // ✅
    'title'   => 'Tudo certo com a sua NF',
    'tone'    => 'blue',
]);

echo LRP_Email_UI::p(
    'Olá, ' . LRP_Email_UI::strong(esc_html($affiliate->get_display_name())) . '! Sua nota fiscal do período ' . LRP_Email_UI::strong(esc_html($period)) . ' foi validada e seguiu para pagamento.',
    ['align' => 'center']
);

echo LRP_Email_UI::amount([
    'label'   => 'A receber',
    'value'   => 'R$ ' . number_format($closing->total_commissions, 2, ',', '.'),
    'caption' => 'Via PIX, em até 5 dias úteis',
    'tone'    => 'blue',
]);

echo LRP_Email_UI::details([
    ['label' => 'Nota fiscal', 'value' => esc_html($closing->invoice_number ?: 'N/A')],
    ['label' => 'Período',     'value' => esc_html($period)],
]);

echo LRP_Email_UI::p(
    'Você vai receber outro e-mail assim que o pagamento for processado. Não precisa fazer mais nada.',
    ['align' => 'center', 'size' => 15, 'color' => LRP_Email_UI::MUTED]
);

echo LRP_Email_UI::signoff();
