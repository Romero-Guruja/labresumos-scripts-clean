<?php
/**
 * WPCode snippet #2382 — labresumos-force-menu-order
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
/**
 * Lab Resumos — Força menu_order em /materiais/ quando há filtros WBW.
 * Diagnóstico: com filtros WBW, o orderby do widget Essential Addons
 * é zerado e cai em post_date DESC. Este filtro reinjeta menu_order
 * na query de produtos (posts_per_page=8, que é o widget principal).
 *
 * menu_order ASC = respeita a sequência arrastada no admin (1, 2, 3...).
 * ID ASC = desempate neutro (não agrupa por nome como o post_title fazia).
 */
add_filter('posts_orderby', function ($orderby, $query) {
    if (is_admin() || !is_page('materiais')) {
        return $orderby;
    }

    // Só age em queries de produto
    $post_type = $query->get('post_type');
    if ($post_type !== 'product' && (!is_array($post_type) || !in_array('product', $post_type))) {
        return $orderby;
    }

    // Só age na query do widget (posts_per_page=8)
    // As outras queries (1, 10 items) são do WBW indexer — não mexer
    if ((int) $query->get('posts_per_page') !== 8) {
        return $orderby;
    }

    // Só reescreve se o orderby atual for post_date (o sintoma do bug)
    if (strpos($orderby, 'post_date') === false) {
        return $orderby;
    }

    global $wpdb;
    return "{$wpdb->posts}.menu_order DESC, {$wpdb->posts}.ID DESC";
}, 999999, 2);