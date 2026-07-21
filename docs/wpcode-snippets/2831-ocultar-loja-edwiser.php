<?php
/**
 * WPCode snippet #2831 — Ocultar loja Edwiser
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
add_action('template_redirect', function () {
    if (is_post_type_archive('product')) {
        // não redireciona se já estiver na /materiais/
        if (strpos($_SERVER['REQUEST_URI'], '/materiais') !== false) {
            return;
        }
        wp_safe_redirect(home_url('/materiais/'), 301);
        exit;
    }
});