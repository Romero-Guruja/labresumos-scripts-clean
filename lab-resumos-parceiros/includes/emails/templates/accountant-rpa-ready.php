<?php
/**
 * Template de email: RPA pronto para emissão (interno - financeiro)
 *
 * @package Lab_Resumos_Parceiros
 *
 * Variáveis disponíveis:
 * - $affiliate      (LRP_Affiliate)
 * - $affiliate_name (string)
 * - $rpa_data       (array)
 * - $amount         (string, já formatado por wc_price)
 * - $accountant_url (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

echo LRP_Email_UI::hero([
    'eyebrow' => 'Interno &middot; financeiro',
    'icon'    => '&#128203;', // 📋
    'title'   => 'RPA para emissão',
    'tone'    => 'blue',
]);

echo LRP_Email_UI::amount([
    'label' => 'Valor do RPA',
    'value' => $amount,
    'tone'  => 'blue',
]);

echo LRP_Email_UI::details([
    ['label' => 'Nome',       'value' => esc_html($rpa_data['nome_completo'] ?? $affiliate_name), 'strong' => true],
    ['label' => 'CPF',        'value' => esc_html($rpa_data['cpf_formatted'] ?? '')],
    ['label' => 'Nascimento', 'value' => esc_html($rpa_data['data_nascimento_fmt'] ?? '')],
    ['label' => 'Endereço',   'value' => esc_html($rpa_data['endereco'] ?? '')],
    ['label' => 'Telefone',   'value' => esc_html($rpa_data['telefone'] ?? '')],
    ['label' => 'INSS/PIS',   'value' => esc_html($rpa_data['inss_number'] ?? '')],
    ['label' => 'Serviço',    'value' => esc_html($rpa_data['descricao_servico'] ?? 'Serviços de divulgação e indicação comercial')],
], ['title' => 'Dados do parceiro', 'tone' => 'neutral']);

echo LRP_Email_UI::button($accountant_url, 'Ver RPAs pendentes', ['tone' => 'blue']);

echo LRP_Email_UI::p(
    'Depois de emitir o RPA, aprove no painel para liberar o pagamento.',
    ['align' => 'center', 'size' => 14, 'color' => LRP_Email_UI::MUTED]
);
