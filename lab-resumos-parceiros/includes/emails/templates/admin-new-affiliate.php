<?php
/**
 * Template de email: Novo cadastro aguardando aprovação (interno - admin)
 *
 * @package Lab_Resumos_Parceiros
 *
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 * - $admin_url (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!$affiliate) {
    return;
}

echo LRP_Email_UI::hero([
    'eyebrow' => 'Interno &middot; aprovação pendente',
    'icon'    => '&#128203;', // 📋
    'title'   => 'Novo parceiro aguardando análise',
    'tone'    => 'blue',
]);

$rows = [
    ['label' => 'Nome',  'value' => esc_html($affiliate->get_display_name()), 'strong' => true],
    ['label' => 'Email', 'value' => esc_html($affiliate->get_email())],
    ['label' => 'Data',  'value' => esc_html(date('d/m/Y H:i'))],
];

if ($affiliate->get_sponsor_id()) {
    $sponsor = new LRP_Affiliate($affiliate->get_sponsor_id());
    if ($sponsor->get_display_name()) {
        $rows[] = ['label' => 'Indicado por', 'value' => esc_html($sponsor->get_display_name())];
    }
}

echo LRP_Email_UI::details($rows, ['tone' => 'blue']);

if ($affiliate->get_application_notes()) {
    echo LRP_Email_UI::note(
        LRP_Email_UI::strong('Notas do candidato:', '#9CCBF5') . '<br>' . esc_html($affiliate->get_application_notes()),
        ['tone' => 'neutral']
    );
}

echo LRP_Email_UI::button($admin_url, 'Revisar cadastro', ['tone' => 'blue']);
