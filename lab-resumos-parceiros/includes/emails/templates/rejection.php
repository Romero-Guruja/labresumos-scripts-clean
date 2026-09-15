<?php
/**
 * Template de email: Cadastro não aprovado
 *
 * @package Lab_Resumos_Parceiros
 *
 * Variáveis disponíveis:
 * - $affiliate_name (string)
 * - $reason         (string, opcional)
 */

if (!defined('ABSPATH')) {
    exit;
}

echo LRP_Email_UI::hero([
    'eyebrow' => 'Sobre o seu cadastro',
    'title'   => 'Não foi desta vez',
    'tone'    => 'neutral',
]);

echo LRP_Email_UI::p(
    'Olá, ' . LRP_Email_UI::strong(esc_html($affiliate_name)) . '! Obrigado pelo interesse no Programa de Parceiros Lab Resumos. Depois de analisar seu cadastro, não conseguimos aprová-lo neste momento.'
);

if (!empty($reason)) {
    echo LRP_Email_UI::note(
        LRP_Email_UI::strong('Motivo:', '#F6A6A0') . ' ' . esc_html($reason),
        ['tone' => 'red']
    );
}

echo LRP_Email_UI::p(
    'Se você acha que houve um engano ou quer entender melhor o critério, é só responder este e-mail - a gente revisa com atenção.'
);

echo LRP_Email_UI::signoff('Atenciosamente,');
