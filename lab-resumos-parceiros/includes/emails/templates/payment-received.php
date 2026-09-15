<?php
/**
 * Template de email: Pagamento realizado
 *
 * @package Lab_Resumos_Parceiros
 *
 * Variáveis disponíveis:
 * - $affiliate      (LRP_Affiliate)
 * - $closing        (object)
 * - $affiliate_name (string)
 * - $amount         (string, já formatado)
 * - $period         (string, MM/AAAA)
 */

if (!defined('ABSPATH')) {
    exit;
}

$period = sprintf('%02d/%d', $closing->period_month, $closing->period_year);

echo LRP_Email_UI::hero([
    'eyebrow' => 'Pagamento concluído',
    'icon'    => '&#128184;', // 💸
    'title'   => 'O dinheiro já saiu daqui',
    'tone'    => 'gold',
]);

echo LRP_Email_UI::p(
    'Olá, ' . LRP_Email_UI::strong(esc_html($affiliate->get_display_name())) . '! Suas comissões do período ' . LRP_Email_UI::strong(esc_html($period)) . ' foram pagas.',
    ['align' => 'center']
);

echo LRP_Email_UI::amount([
    'label'   => 'Valor pago',
    'value'   => 'R$ ' . number_format($closing->total_commissions, 2, ',', '.'),
    'caption' => 'Via PIX, na chave cadastrada no seu perfil',
    'tone'    => 'gold',
]);

echo LRP_Email_UI::details([
    ['label' => 'Período',  'value' => esc_html($period)],
    ['label' => 'Data',     'value' => esc_html(date('d/m/Y', strtotime($closing->paid_at)))],
    ['label' => 'Método',   'value' => 'PIX'],
]);

echo LRP_Email_UI::note(
    'Se o valor não aparecer na sua conta em algumas horas, confira a chave PIX no seu perfil e responda este e-mail.',
    ['tone' => 'blue', 'icon' => '&#128172;'] // 💬
);

echo LRP_Email_UI::p(
    'Obrigado por divulgar a Lab. O próximo ciclo já começou a contar.',
    ['align' => 'center', 'size' => 15, 'color' => LRP_Email_UI::MUTED]
);

echo LRP_Email_UI::signoff();
