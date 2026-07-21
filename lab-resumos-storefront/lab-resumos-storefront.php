<?php
/**
 * Plugin Name: Lab Resumos - Storefront
 * Description: Filtros de listagem/loja/carrinho e um ajuste de NF-e do labresumos.com.br.
 *              Fase F3a do roadmap (docs/plugins-custom-analise-e-roadmap.md) — portado dos
 *              snippets WPCode #2774, #2382, #2831, #3039, #1422 e #953, sem alteração de
 *              lógica. Nenhum destes define função nomeada (só add_action/add_filter com
 *              closures), então não há guard function_exists a fazer.
 * Version: 1.0.0
 * Author: Lab Resumos
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// #2774 — Corrige filtro perdido no botão "Carregar mais" do Essential Addons
// ============================================================================
/**
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

// ============================================================================
// #2382 — Força menu_order em /materiais/ quando há filtros WBW
// ============================================================================
/**
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

// ============================================================================
// #2831 — Ocultar loja Edwiser (redireciona arquivo de produto pra /materiais/)
// ============================================================================
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

// ============================================================================
// #3039 — Diferenciador de Cursos no Seletor Woo↔Moodle (ID + Área)
// ============================================================================
/**
 * Adiciona [#ID • Área] ao texto de cada opção do dropdown de cursos,
 * para distinguir cursos de mesmo nome (ex.: Fiscal x Tribunais).
 * Cosmético: altera apenas o texto exibido, nunca o value salvo.
 */
add_action('admin_footer', function () {

    if (!current_user_can('manage_options')) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'product') {
        return;
    }

    // ⇩ MAPA: course_id => área. Preencha com os IDs reais.
    //   (só os que você quer diferenciar; o resto mostra só o ID)
    $areas = array(
        1935 => 'Tribunais',   // Direito Administrativo - Resumo
        1937 => 'Tribunais',   // Direito Administrativo - Flashcards
        3025 => 'Fiscal',      // Direito Administrativo
        3026 => 'Fiscal',      // Direito Administrativo - Resumo
        // ... adicione os outros pares conforme precisar
    );
    ?>
    <script>
    (function () {
        var areas = <?php echo wp_json_encode($areas); ?>;
        var sel = document.querySelector('select[name="product_options[moodle_post_course_id][]"]');
        if (!sel) return;

        sel.querySelectorAll('option').forEach(function (op) {
            var id = op.value;
            var base = op.textContent.replace(/\s+/g, ' ').trim(); // limpa tabs/quebras
            var area = areas[id] ? ' • ' + areas[id] : '';
            op.textContent = base + '  [#' + id + area + ']';
        });
    })();
    </script>
    <?php
}, 9999);

// ============================================================================
// #1422 — Remover mensagem "adicionado ao carrinho"
// ============================================================================
add_filter('wc_add_to_cart_message_html', '__return_empty_string');

// ============================================================================
// #953 — Webmania: Modalidade Frete Sem Transporte
// ============================================================================
/**
 * Define modalidade de frete padrão como "Sem Ocorrência de Transporte" (9)
 * para todas as emissões de NF-e via Webmania
 */
add_filter('nfe_order_data', function ($data, $order_id) {
    // Define modalidade_frete = 9 (Sem Ocorrência de Transporte)
    if (isset($data['pedido'])) {
        $data['pedido']['modalidade_frete'] = 9;
    } else {
        $data['pedido'] = array('modalidade_frete' => 9);
    }

    // Remove dados de transporte/entrega que não são necessários para produtos digitais
    if (isset($data['transporte'])) {
        unset($data['transporte']);
    }

    return $data;
}, 10, 2);
