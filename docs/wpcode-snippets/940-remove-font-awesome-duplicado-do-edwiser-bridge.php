<?php
/**
 * WPCode snippet #940 — Remove Font Awesome duplicado do Edwiser Bridge
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
// Remove @import Font Awesome do CSS inline do Edwiser Bridge
add_action('wp_head', function() {
    ob_start(function($html) {
        // Remove o @import do Font Awesome CDN do Edwiser Bridge
        return preg_replace(
            '/@import\s+url\([^)]*cdnjs\.cloudflare\.com[^)]*font-awesome[^)]*\);?/i',
            '/* FA CDN removido */',
            $html
        );
    });
}, 1);

add_action('wp_footer', function() {
    if (ob_get_level()) {
        ob_end_flush();
    }
}, 999);