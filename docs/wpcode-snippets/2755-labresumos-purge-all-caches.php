<?php
/**
 * WPCode snippet #2755 — labresumos-purge-all-caches
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
/**
 * Lab Resumos — Botão "Limpar Cache" na barra do admin
 * Limpa Cloudflare + LiteSpeed + Redis em 1 clique.
 * 
 * Permissão: usuários com capability 'manage_options' (admins) por padrão.
 * Para liberar também para Editor: troque 'manage_options' por 'edit_pages' no register.
 */

// ============================================================
// CONFIGURAÇÃO — substitua pelos seus valores
// ============================================================
define('LAB_CF_ZONE_ID', 'REDACTED-CLOUDFLARE-ZONE-ID');
define('LAB_CF_API_TOKEN', 'REDACTED-CLOUDFLARE-API-TOKEN');
// ============================================================

/**
 * 1) Adiciona o botão na barra do admin (topo do WordPress)
 */
add_action('admin_bar_menu', function ($admin_bar) {
    if (!current_user_can('manage_options')) return;
    
    $admin_bar->add_node([
        'id'    => 'lab-purge-cache',
        'title' => '🧹 Limpar Cache',
        'href'  => '#',
        'meta'  => [
            'title' => 'Limpa Cloudflare + LiteSpeed + Redis',
            'class' => 'lab-purge-cache-btn',
        ],
    ]);
}, 999);

/**
 * 2) Estilo e script do botão (só carrega quando admin bar está visível)
 */
add_action('wp_before_admin_bar_render', function () {
    if (!current_user_can('manage_options')) return;
    
    $nonce = wp_create_nonce('lab_purge_cache');
    $ajax_url = admin_url('admin-ajax.php');
    ?>
    <style>
        #wp-admin-bar-lab-purge-cache > .ab-item {
            background: #d63638 !important;
            color: #fff !important;
            font-weight: bold !important;
        }
        #wp-admin-bar-lab-purge-cache > .ab-item:hover {
            background: #b32d2e !important;
        }
        #wp-admin-bar-lab-purge-cache.lab-purging > .ab-item {
            background: #f0b849 !important;
            color: #000 !important;
        }
        #wp-admin-bar-lab-purge-cache.lab-success > .ab-item {
            background: #00a32a !important;
        }
    </style>
    <script>
    (function() {
        document.addEventListener('DOMContentLoaded', function() {
            var btn = document.getElementById('wp-admin-bar-lab-purge-cache');
            if (!btn) return;
            
            btn.querySelector('a').addEventListener('click', function(e) {
                e.preventDefault();
                
                if (btn.classList.contains('lab-purging')) return;
                
                if (!confirm('Limpar TODOS os caches (Cloudflare + LiteSpeed + Redis)?\n\nIsso pode deixar o site momentaneamente mais lento até os caches reaquecerem.')) {
                    return;
                }
                
                btn.classList.add('lab-purging');
                btn.querySelector('.ab-item').innerHTML = '⏳ Limpando...';
                
                fetch('<?php echo esc_js($ajax_url); ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=lab_purge_all_caches&_nonce=<?php echo esc_js($nonce); ?>'
                })
                .then(r => r.json())
                .then(data => {
                    btn.classList.remove('lab-purging');
                    if (data.success) {
                        btn.classList.add('lab-success');
                        btn.querySelector('.ab-item').innerHTML = '✅ ' + data.data.message;
                        setTimeout(function() {
                            btn.classList.remove('lab-success');
                            btn.querySelector('.ab-item').innerHTML = '🧹 Limpar Cache';
                        }, 4000);
                    } else {
                        btn.querySelector('.ab-item').innerHTML = '⚠️ Erro';
                        alert('Erro ao limpar cache:\n\n' + (data.data ? data.data.message : 'Desconhecido'));
                        setTimeout(function() {
                            btn.querySelector('.ab-item').innerHTML = '🧹 Limpar Cache';
                        }, 4000);
                    }
                })
                .catch(err => {
                    btn.classList.remove('lab-purging');
                    btn.querySelector('.ab-item').innerHTML = '⚠️ Erro';
                    alert('Erro de rede: ' + err.message);
                    setTimeout(function() {
                        btn.querySelector('.ab-item').innerHTML = '🧹 Limpar Cache';
                    }, 4000);
                });
            });
        });
    })();
    </script>
    <?php
});

/**
 * 3) Endpoint AJAX que faz o trabalho pesado
 */
add_action('wp_ajax_lab_purge_all_caches', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Sem permissão']);
    }
    
    if (!wp_verify_nonce($_POST['_nonce'] ?? '', 'lab_purge_cache')) {
        wp_send_json_error(['message' => 'Nonce inválido']);
    }
    
    $resultados = [];
    
    // ---- 1. Cloudflare ----
    $cf_response = wp_remote_post(
        'https://api.cloudflare.com/client/v4/zones/' . LAB_CF_ZONE_ID . '/purge_cache',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . LAB_CF_API_TOKEN,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode(['purge_everything' => true]),
            'timeout' => 15,
        ]
    );
    
    if (is_wp_error($cf_response)) {
        $resultados[] = 'Cloudflare: ❌ ' . $cf_response->get_error_message();
    } else {
        $cf_body = json_decode(wp_remote_retrieve_body($cf_response), true);
        if (!empty($cf_body['success'])) {
            $resultados[] = 'Cloudflare ✅';
        } else {
            $erro = isset($cf_body['errors'][0]['message']) ? $cf_body['errors'][0]['message'] : 'falhou';
            $resultados[] = 'Cloudflare: ❌ ' . $erro;
        }
    }
    
    // ---- 2. LiteSpeed Cache ----
    if (defined('LSCWP_V') && class_exists('LiteSpeed\Purge')) {
        do_action('litespeed_purge_all');
        $resultados[] = 'LiteSpeed ✅';
    } else {
        $resultados[] = 'LiteSpeed: pulado (não detectado)';
    }
    
    // ---- 3. Redis Object Cache ----
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
        $resultados[] = 'Redis ✅';
    } else {
        $resultados[] = 'Redis: pulado';
    }
    
    wp_send_json_success([
        'message' => 'Tudo limpo!',
        'detalhes' => $resultados,
    ]);
});