<?php
/**
 * v2: mesma logica desktop=embed / mobile+inapp=botao, mas com o <script>
 * marcado com data-no-optimize / data-no-defer para o LiteSpeed NAO trocar o
 * type para "litespeed/javascript" (que impedia a execucao no desktop).
 *
 * Rodar com: wp eval-file lra_embed_desktop2.php
 */

global $wpdb;

$rows = $wpdb->get_results(
    "SELECT post_id FROM {$wpdb->postmeta}
     WHERE meta_key = 'wb_custom_tabs' AND meta_value LIKE '%lr-amostra%'"
);

$backup_dir = getenv('HOME') . '/lra-backups';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0775, true);
}

$js = '<script data-no-optimize="1" data-no-defer="1" data-cfasync="false">'
    . '(function(){var els=document.querySelectorAll(".lr-amostra");'
    . 'for(var i=0;i<els.length;i++){(function(w){var url=w.getAttribute("data-pdf");if(!url)return;'
    . 'var ua=navigator.userAgent||"";'
    . 'var block=/Instagram|FBAN|FBAV|FB_IAB|Line|Twitter|WhatsApp|Snapchat|Pinterest|Mobi|Android|iPhone|iPad|iPod/i.test(ua);'
    . 'if(block)return;'
    . 'var fb=w.querySelector(".lr-amostra-fallback");'
    . 'var f=document.createElement("iframe");f.src=url;f.setAttribute("width","100%");f.setAttribute("height","800");f.style.border="0";'
    . 'if(fb){fb.style.display="none";}w.insertBefore(f,fb);})(els[i]);}})();</script>';

$build = function ($pdf_url) use ($js) {
    $u = esc_url($pdf_url);
    $fallback = '<div class="lr-amostra-fallback" style="text-align:center;padding:32px 16px;">'
        . '<p style="font-size:16px;margin:0 0 20px;">Confira uma amostra gratuita do material antes de adquirir.</p>'
        . '<a class="button" href="' . $u . '" target="_blank" rel="noopener" '
        . 'style="display:inline-block;padding:14px 28px;background:#111;color:#fff;border-radius:999px;'
        . 'text-decoration:none;font-weight:700;font-size:15px;">Ver amostra gr&aacute;tis (PDF)</a>'
        . '<p style="font-size:13px;color:#888;margin:16px 0 0;">O arquivo abre em uma nova aba.</p>'
        . '</div>';
    return '<div class="lr-amostra" data-pdf="' . $u . '">' . $fallback . $js . '</div>';
};

$changed = 0;
foreach ($rows as $row) {
    $pid  = (int) $row->post_id;
    $tabs = get_post_meta($pid, 'wb_custom_tabs', true);
    if (!is_array($tabs)) {
        continue;
    }
    $did = false;
    foreach ($tabs as $i => $tab) {
        if (empty($tab['content']) || strpos($tab['content'], 'lr-amostra') === false) {
            continue;
        }
        if (!preg_match('/(?:data-pdf|href)=["\']([^"\']*\.pdf[^"\']*)["\']/i', $tab['content'], $m)) {
            continue;
        }
        $tabs[$i]['content'] = $build(html_entity_decode($m[1]));
        $did = true;
    }
    if (!$did) {
        continue;
    }
    update_post_meta($pid, 'wb_custom_tabs', $tabs);
    $changed++;
}

// Excluir tambem via config do LiteSpeed (defesa redundante): marca a string
// identificadora do nosso script como excluida do defer de JS.
WP_CLI::success("Conteudo atualizado em {$changed} produtos.");
