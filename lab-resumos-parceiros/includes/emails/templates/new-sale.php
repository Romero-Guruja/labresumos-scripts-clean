<?php
/**
 * Template de email: Nova venda direta
 *
 * @package Lab_Resumos_Parceiros
 *
 * Variáveis disponíveis:
 * - $affiliate_name (string)
 * - $order_id       (int)
 * - $order_total    (string, já formatado por wc_price)
 * - $commission     (string, já formatado por wc_price)
 * - $attribution    (string) "Cupom" ou "Link"
 * - $dashboard_url  (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

echo LRP_Email_UI::hero([
    'eyebrow' => 'Nova venda atribuída a você',
    'icon'    => '&#128176;', // 💰
    'title'   => 'Mais uma venda no seu nome',
    'tone'    => 'gold',
]);

echo LRP_Email_UI::p(
    'Olá, ' . LRP_Email_UI::strong(esc_html($affiliate_name)) . '! Uma compra acabou de ser atribuída à sua divulgação.',
    ['align' => 'center']
);

echo LRP_Email_UI::amount([
    'label' => 'Sua comissão',
    'value' => $commission,
    'tone'  => 'gold',
]);

echo LRP_Email_UI::details([
    ['label' => 'Pedido',          'value' => '#' . esc_html($order_id)],
    ['label' => 'Valor da compra', 'value' => $order_total],
    ['label' => 'Atribuída por',   'value' => esc_html($attribution)],
]);

echo LRP_Email_UI::note(
    'A comissão fica ' . LRP_Email_UI::strong('pendente', '#9CCBF5') . ' até a confirmação do pagamento pelo cliente. Depois disso, é aprovada automaticamente e entra no seu próximo fechamento.',
    ['tone' => 'blue', 'icon' => '&#8987;'] // ⏳
);

echo LRP_Email_UI::button($dashboard_url, 'Ver no meu painel', ['tone' => 'gold']);

echo LRP_Email_UI::p(
    'Continue divulgando: cada indicação sua vira material na mão de quem está estudando.',
    ['align' => 'center', 'size' => 15, 'color' => LRP_Email_UI::MUTED]
);

echo LRP_Email_UI::signoff();
