<?php
/**
 * WPCode snippet #1742 — Permitir Contador acessar Admin
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
add_filter('woocommerce_prevent_admin_access', function($prevent) {
    if (current_user_can('lrp_manage_invoices')) {
        return false;
    }
    return $prevent;
});