<?php
/**
 * Template de email: Boas-vindas ao Programa de Parceiros
 *
 * @package Lab_Resumos_Parceiros
 *
 * Variáveis disponíveis:
 * - $affiliate_name (string)
 * - $coupon_code    (string)
 * - $referral_url   (string)
 * - $dashboard_url  (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

echo LRP_Email_UI::hero([
    'eyebrow' => 'Cadastro aprovado',
    'icon'    => '&#127881;', // 🎉
    'title'   => 'Bem-vindo ao time de parceiros',
    'tone'    => 'gold',
]);

echo LRP_Email_UI::p(
    'Olá, ' . LRP_Email_UI::strong(esc_html($affiliate_name)) . '! Seu cadastro foi aprovado. A partir de agora, toda venda que vier da sua indicação vira comissão para você.',
    ['align' => 'center']
);

echo LRP_Email_UI::h3('Seu cupom exclusivo');

echo LRP_Email_UI::code_block($coupon_code, ['big' => true]);

echo LRP_Email_UI::p(
    'Quem usa esse cupom ganha ' . LRP_Email_UI::strong('10% de desconto') . '. Você ganha ' . LRP_Email_UI::strong('10% de comissão') . ' sobre a compra.',
    ['size' => 15]
);

echo LRP_Email_UI::code_block($referral_url, ['label' => 'Seu link de indicação']);

echo LRP_Email_UI::p(
    'Quem entra pelo link fica marcado como seu por ' . LRP_Email_UI::strong('60 dias') . ', mesmo que compre depois. Comissão de ' . LRP_Email_UI::strong('5%') . '.',
    ['size' => 15]
);

echo LRP_Email_UI::divider();

echo LRP_Email_UI::h3('Como funciona');

echo LRP_Email_UI::steps([
    ['title' => 'Divulgue', 'text' => 'use seu cupom ou seu link onde quiser'],
    ['title' => 'Venda',    'text' => 'a comissão é registrada automaticamente'],
    ['title' => 'Acompanhe', 'text' => 'tudo aparece em tempo real no seu painel'],
    ['title' => 'Receba',   'text' => 'pagamento mensal via PIX'],
]);

echo LRP_Email_UI::button($dashboard_url, 'Acessar meu painel', ['tone' => 'gold']);

echo LRP_Email_UI::p(
    'Dúvidas sobre regras, prazos ou pagamento? A aba FAQ do painel responde quase tudo - e o resto é só responder este e-mail.',
    ['align' => 'center', 'size' => 15, 'color' => LRP_Email_UI::MUTED]
);

echo LRP_Email_UI::signoff('Boas vendas,');
