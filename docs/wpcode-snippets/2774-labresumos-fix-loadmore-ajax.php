<?php
/**
 * WPCode snippet #2774 — labresumos-fix-loadmore-ajax
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
/**
 * Lab Resumos — Corrige filtro perdido no botão "Carregar mais" do Essential Addons
 * 
 * Bug: ao clicar "Carregar mais" com filtro WBW ativo, o EA faz uma chamada AJAX 
 * (action=load_more) que NÃO inclui os filtros WBW no tax_query. Resultado: a 
 * segunda página vem com produtos de outros tipos misturados.
 * 
 * Solução: durante a AJAX action=load_more, lê o Referer (URL da página que 
 * disparou a chamada), extrai os parâmetros wpf_filter_* e product_tag_N / 
 * product_cat_N, e re-injeta no tax_query antes do WP_Query rodar.
 */
add_action('pre_get_posts', function ($query) {
    // Só atua durante a AJAX específica do Essential Addons "Load More"
    if (!wp_doing_ajax()) return;
    if (($_POST['action'] ?? '') !== 'load_more') return;
    
    // Confirma que é do widget Product_Grid do Essential Addons
    if (strpos($_POST['class'] ?? '', 'Product_Grid') === false) return;
    
    // Extrai a URL da página que disparou a chamada
    $referer = wp_get_referer();
    if (!$referer) return;
    
    $parsed = wp_parse_url($referer);
    if (empty($parsed['query'])) return;
    
    parse_str($parsed['query'], $params);
    if (empty($params)) return;
    
    // Constrói tax_query adicional baseado nos parâmetros conhecidos
    $extra_tax_query = [];
    
    foreach ($params as $key => $value) {
        if (empty($value)) continue;
        
        // Padrão 1: ?product_tag_N=slug (do Carregar Mais)
        // Padrão 2: ?product_cat_N=slug
        if (preg_match('/^(product_tag|product_cat|pwb-brand)_\d+$/', $key, $m)) {
            $taxonomy = $m[1];
            $slugs = is_array($value) ? $value : explode(',', $value);
            $slugs = array_map('sanitize_title', array_filter($slugs));
            
            if (!empty($slugs)) {
                $extra_tax_query[] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $slugs,
                    'operator' => 'IN',
                ];
            }
        }
        
        // Padrão 3: ?wpf_filter_pwb_list_N=ID (filtro Perfect Brands do WBW)
        if (preg_match('/^wpf_filter_pwb_list_\d+$/', $key)) {
            $ids = is_array($value) ? $value : explode(',', $value);
            $ids = array_map('intval', array_filter($ids));
            
            if (!empty($ids)) {
                $extra_tax_query[] = [
                    'taxonomy' => 'pwb-brand',
                    'field'    => 'term_id',
                    'terms'    => $ids,
                    'operator' => 'IN',
                ];
            }
        }
        
        // Padrão 4: ?wpf_filter_cat_N=ID (filtro Categoria do WBW)
        if (preg_match('/^wpf_filter_cat_\d+$/', $key)) {
            $ids = is_array($value) ? $value : explode(',', $value);
            $ids = array_map('intval', array_filter($ids));
            
            if (!empty($ids)) {
                $extra_tax_query[] = [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $ids,
                    'operator' => 'IN',
                ];
            }
        }
        
        // Padrão 5: ?wpf_filter_tag_N=ID (filtro Tag do WBW, se existir)
        if (preg_match('/^wpf_filter_tag_\d+$/', $key)) {
            $ids = is_array($value) ? $value : explode(',', $value);
            $ids = array_map('intval', array_filter($ids));
            
            if (!empty($ids)) {
                $extra_tax_query[] = [
                    'taxonomy' => 'product_tag',
                    'field'    => 'term_id',
                    'terms'    => $ids,
                    'operator' => 'IN',
                ];
            }
        }
    }
    
    if (empty($extra_tax_query)) return;
    
    // Combina com tax_query existente
    $existing = $query->get('tax_query') ?: [];
    if (!empty($existing) && is_array($existing)) {
        foreach ($existing as $k => $v) {
            if (is_array($v)) {
                $extra_tax_query[] = $v;
            }
        }
    }
    
    if (count($extra_tax_query) > 1) {
        $extra_tax_query['relation'] = 'AND';
    }
    
    $query->set('tax_query', $extra_tax_query);
    
    // Força menu_order DESC também na AJAX (consistência com a página)
    $query->set('orderby', 'menu_order');
    $query->set('order', 'DESC');
}, 5);