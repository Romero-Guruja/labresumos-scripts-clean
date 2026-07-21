<?php
/**
 * WPCode snippet #1422 — Remover mensagem "adicionado ao carrinho"
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
add_filter('wc_add_to_cart_message_html', '__return_empty_string');