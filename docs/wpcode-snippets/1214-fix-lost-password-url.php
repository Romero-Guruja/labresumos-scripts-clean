<?php
/**
 * WPCode snippet #1214 — Fix Lost Password URL
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
/**
 * Corrige o link "Perdeu sua senha?" para usar /conta/ em vez de /minha-conta/
 */
add_filter('lostpassword_url', 'lab_fix_lostpassword_url', 20, 2);
function lab_fix_lostpassword_url($url, $redirect) {
    $url = str_replace('/minha-conta/', '/conta/', $url);
    return $url;
}