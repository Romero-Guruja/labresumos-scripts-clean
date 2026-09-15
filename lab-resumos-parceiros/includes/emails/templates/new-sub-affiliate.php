<?php
/**
 * Template de email: Novo sub-afiliado na rede
 *
 * @package Lab_Resumos_Parceiros
 *
 * Variáveis disponíveis:
 * - $sponsor            (LRP_Affiliate)
 * - $new_affiliate      (LRP_Affiliate)
 * - $sponsor_name       (string)
 * - $new_affiliate_name (string)
 * - $commission_l2      (string, ex. "3%")
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!$sponsor || !$new_affiliate) {
    return;
}

echo LRP_Email_UI::hero([
    'eyebrow' => 'Sua rede cresceu',
    'icon'    => '&#129309;', // 🤝
    'title'   => 'Você tem um novo parceiro indicado',
    'tone'    => 'blue',
]);

echo LRP_Email_UI::p(
    'Olá, ' . LRP_Email_UI::strong(esc_html($sponsor->get_display_name())) . '! Alguém se cadastrou usando o seu link de indicação.',
    ['align' => 'center']
);

echo LRP_Email_UI::details([
    ['label' => 'Novo parceiro', 'value' => esc_html($new_affiliate->get_display_name()), 'strong' => true],
    ['label' => 'Entrou em',     'value' => esc_html(date('d/m/Y \à\s H:i'))],
], ['tone' => 'blue']);

echo LRP_Email_UI::h3('O que isso significa para você');

echo LRP_Email_UI::steps([
    ['title' => 'Nível 2', 'text' => '3% sobre cada venda direta dele'],
    ['title' => 'Nível 3', 'text' => '1% sobre as vendas de quem ele indicar'],
]);

echo LRP_Email_UI::p(
    'Sem esforço adicional da sua parte: enquanto ele vende, você recebe. Continue indicando para ampliar a rede.',
    ['align' => 'center', 'size' => 15, 'color' => LRP_Email_UI::MUTED]
);

echo LRP_Email_UI::signoff();
