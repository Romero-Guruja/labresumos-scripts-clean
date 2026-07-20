<?php
/**
 * Desativa o snippet WPCode #2975 "Impede direcionamento para PDF nos anuncios"
 * (paliativo antigo cujo MutationObserver destruia o embed injetado no desktop).
 *
 * PRECISA dos 2 passos: deactivate() NAO regenera o cache de execucao do WPCode
 * (option wpcode_snippets), entao removemos o item de la manualmente.
 *
 * Rodar do $HOME: wp --path=~/domains/labresumos.com.br/public_html eval-file $HOME/desativar_paliativo_wpcode_2975.php
 * Depois: wp litespeed-purge all && wp cache flush
 */

$snippet_id = 2975;

// 1) Marca o snippet como inativo (fonte da verdade).
if (class_exists('WPCode_Snippet')) {
    $s = new WPCode_Snippet($snippet_id);
    if (method_exists($s, 'deactivate')) {
        $s->deactivate();
    } else {
        $s->active = false;
        $s->save();
    }
}
wp_update_post(['ID' => $snippet_id, 'post_status' => 'draft']);

// 2) Remove do cache de execucao (option wpcode_snippets), por localizacao.
$o = get_option('wpcode_snippets');
$removed = 0;
if (is_array($o)) {
    foreach ($o as $loc => $arr) {
        if (!is_array($arr)) {
            continue;
        }
        $new = [];
        foreach ($arr as $item) {
            $id = is_array($item) ? ($item['id'] ?? 0) : (is_object($item) ? ($item->id ?? 0) : 0);
            if ((int) $id === $snippet_id) {
                $removed++;
                continue;
            }
            $new[] = $item;
        }
        $o[$loc] = array_values($new);
    }
    update_option('wpcode_snippets', $o);
}

if (function_exists('WP_CLI')) {
    WP_CLI::success("Snippet #{$snippet_id} desativado. Removido do indice: {$removed}.");
}
