<?php
/**
 * Template de email: Venda de sub-afiliado (comissão de rede)
 *
 * @package Lab_Resumos_Parceiros
 *
 * Variáveis disponíveis:
 * - $sponsor       (LRP_Affiliate)
 * - $sub_affiliate (LRP_Affiliate)
 * - $commission    (LRP_Commission)
 * - $referral      (LRP_Referral)
 * - $level         (int)
 * - $sponsor_name  (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!$sponsor || !$sub_affiliate || !$commission || !$referral) {
    return;
}

echo LRP_Email_UI::hero([
    'eyebrow' => 'Comissão de rede - nível ' . (int) $level,
    'icon'    => '&#128101;', // 👥
    'title'   => 'Sua rede vendeu por você',
    'tone'    => 'blue',
]);

echo LRP_Email_UI::p(
    'Olá, ' . LRP_Email_UI::strong(esc_html($sponsor->get_display_name())) . '! Um parceiro que você indicou fez uma venda, e uma parte dela é sua.',
    ['align' => 'center']
);

echo LRP_Email_UI::amount([
    'label'   => 'Sua comissão de rede',
    'value'   => 'R$ ' . number_format($commission->commission_amount, 2, ',', '.'),
    'caption' => 'Nível ' . (int) $level . ' &middot; ' . number_format($commission->commission_rate, 1, ',', '.') . '% sobre a venda',
    'tone'    => 'blue',
]);

echo LRP_Email_UI::details([
    ['label' => 'Parceiro',       'value' => esc_html($sub_affiliate->get_display_name())],
    ['label' => 'Pedido',         'value' => '#' . esc_html($referral->order_id)],
    ['label' => 'Valor da venda', 'value' => 'R$ ' . number_format($referral->commission_base, 2, ',', '.')],
]);

echo LRP_Email_UI::p(
    'Você não precisou fazer nada nesta venda: ela veio da rede que você construiu. Quanto mais parceiros você indica, mais isso se repete.',
    ['align' => 'center', 'size' => 15, 'color' => LRP_Email_UI::MUTED]
);

echo LRP_Email_UI::signoff();
