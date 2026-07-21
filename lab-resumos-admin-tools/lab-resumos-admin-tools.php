<?php
/**
 * Plugin Name: Lab Resumos - Admin Tools
 * Description: Botão "Limpar Cache" na admin bar (Cloudflare+LiteSpeed+Redis), acesso ao
 *              wp-admin para o contador do programa de afiliados, remoção do Font Awesome
 *              duplicado do Edwiser Bridge e monitor de erros do Edwiser Bridge via Telegram.
 *              Fase F3d do roadmap (docs/plugins-custom-analise-e-roadmap.md) — portado dos
 *              snippets WPCode #2755, #1742, #940 e #1650, com 2 desvios deliberados de
 *              "cópia fiel": (1) as credenciais da Cloudflare NÃO ficam hardcoded no código
 *              (iam pro histórico do git) — leem de constantes definidas no wp-config.php do
 *              servidor; (2) o alerta do Edwiser Bridge passou a usar LR_Telegram::alert()
 *              do mu-plugin lab-resumos-core (F1) no lugar da função local
 *              lr_send_edwiser_telegram_alert() — mesmo webhook, mesmo rate-limit 10/h,
 *              agora não-bloqueante.
 * Version: 1.0.0
 * Author: Lab Resumos
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// #2755 — Botão "Limpar Cache" na barra do admin
// ============================================================================
/**
 * Lab Resumos — Botão "Limpar Cache" na barra do admin
 * Limpa Cloudflare + LiteSpeed + Redis em 1 clique.
 *
 * Permissão: usuários com capability 'manage_options' (admins) por padrão.
 * Para liberar também para Editor: troque 'manage_options' por 'edit_pages' no register.
 *
 * Credenciais da Cloudflare vêm do wp-config.php (LAB_CF_ZONE_ID / LAB_CF_API_TOKEN) —
 * NÃO ficam no código versionado.
 */
if (!defined('LAB_CF_ZONE_ID')) {
    define('LAB_CF_ZONE_ID', '');
}
if (!defined('LAB_CF_API_TOKEN')) {
    define('LAB_CF_API_TOKEN', '');
}

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

// ============================================================================
// #1742 — Permitir Contador acessar Admin
// ============================================================================
add_filter('woocommerce_prevent_admin_access', function ($prevent) {
    if (current_user_can('lrp_manage_invoices')) {
        return false;
    }
    return $prevent;
});

// ============================================================================
// #940 — Remove Font Awesome duplicado do Edwiser Bridge
// ============================================================================
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

// ============================================================================
// #1650 — Edwiser Bridge - Alerta de Erros via Telegram (usa LR_Telegram do core)
// ============================================================================
/**
 * Edwiser Bridge - Alerta de Erros via Telegram
 *
 * Monitora erros de conexão/enrollment do Edwiser Bridge e envia
 * notificação imediata via LR_Telegram::alert() (mu-plugin lab-resumos-core).
 */

// ============================================================================
// 1. INTERCEPTAR ERROS DE CURL DO EDWISER BRIDGE
//    O Edwiser Bridge usa wp_remote_post/get para chamar o Moodle.
//    Filtramos respostas HTTP com erro.
// ============================================================================

add_filter('http_response', 'lr_monitor_edwiser_bridge_requests', 10, 3);

if (!function_exists('lr_monitor_edwiser_bridge_requests')) {
    function lr_monitor_edwiser_bridge_requests($response, $parsed_args, $url) {
        // Só monitorar chamadas para o Moodle (Edwiser Bridge)
        if (strpos($url, 'aluno.labresumos.com.br') === false) {
            return $response;
        }

        // Verificar se houve erro na requisição
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_code    = $response->get_error_code();

            // Extrair a função da API sendo chamada (ex: enrol_manual_enrol_users)
            $ws_function = 'desconhecida';
            if (preg_match('/wsfunction=([^&]+)/', $url, $matches)) {
                $ws_function = $matches[1];
            }

            // Limpar token da URL para não vazar no alerta
            $safe_url = preg_replace('/wstoken=[^&]+/', 'wstoken=***', $url);

            LR_Telegram::alert(
                '❌ Erro Edwiser Bridge — Conexão Moodle',
                "Função: {$ws_function}\n"
                . "Erro: {$error_message}\n"
                . "Código: {$error_code}\n"
                . "URL: {$safe_url}\n"
                . "Data: " . wp_date('d/m/Y H:i:s')
            );
        }

        // Verificar respostas HTTP com status de erro (4xx, 5xx)
        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);

            if ($status_code >= 400) {
                $ws_function = 'desconhecida';
                if (preg_match('/wsfunction=([^&]+)/', $url, $matches)) {
                    $ws_function = $matches[1];
                }

                $safe_url = preg_replace('/wstoken=[^&]+/', 'wstoken=***', $url);
                $body     = wp_remote_retrieve_body($response);
                // Limitar corpo para não sobrecarregar o alerta
                $body_preview = mb_substr($body, 0, 300);

                LR_Telegram::alert(
                    "⚠️ Erro Edwiser Bridge — HTTP {$status_code}",
                    "Função: {$ws_function}\n"
                    . "Status: {$status_code}\n"
                    . "Resposta: {$body_preview}\n"
                    . "URL: {$safe_url}\n"
                    . "Data: " . wp_date('d/m/Y H:i:s')
                );
            }
        }

        return $response;
    }
}


// ============================================================================
// 2. MONITORAR ERROS DE ENROLLMENT ESPECÍFICOS
//    Hook no processo de enrollment do Edwiser Bridge.
// ============================================================================

add_action('eb_order_status_completed', 'lr_monitor_enrollment_on_order', 999, 2);

if (!function_exists('lr_monitor_enrollment_on_order')) {
    function lr_monitor_enrollment_on_order($order_id, $course_ids = array()) {
        if (!$order_id) {
            return;
        }

        // Agendar verificação para 60 segundos depois
        // (dá tempo do Edwiser Bridge processar o enrollment)
        wp_schedule_single_event(
            time() + 60,
            'lr_verify_enrollment_after_order',
            array($order_id)
        );
    }
}

add_action('lr_verify_enrollment_after_order', 'lr_check_enrollment_status');

if (!function_exists('lr_check_enrollment_status')) {
    function lr_check_enrollment_status($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $customer_email = $order->get_billing_email();
        $customer_name  = $order->get_formatted_billing_full_name();
        $order_total    = $order->get_formatted_order_total();

        // Buscar cursos associados aos produtos do pedido
        $missing_enrollments = array();

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();

            // O Edwiser Bridge armazena o course_id como meta do produto
            $moodle_course_id = get_post_meta($product_id, '_eb_product_course', true);

            if (empty($moodle_course_id)) {
                continue; // Produto não vinculado a curso
            }

            // Verificar se o usuário WP tem vínculo de enrollment
            $wp_user = get_user_by('email', $customer_email);
            if (!$wp_user) {
                $missing_enrollments[] = array(
                    'course_id'   => $moodle_course_id,
                    'product'     => $item->get_name(),
                    'reason'      => 'Usuário WP não encontrado',
                );
                continue;
            }

            // Checar meta de enrollment do Edwiser Bridge
            $enrolled_courses = get_user_meta($wp_user->ID, 'eb_user_courses', true);

            if (empty($enrolled_courses) || !in_array($moodle_course_id, (array) $enrolled_courses)) {
                $missing_enrollments[] = array(
                    'course_id'   => $moodle_course_id,
                    'product'     => $item->get_name(),
                    'reason'      => 'Matrícula não registrada no Edwiser Bridge',
                );
            }
        }

        // Se encontrou matrículas faltando, alertar
        if (!empty($missing_enrollments)) {
            $details = "Pedido: #{$order_id}\n"
                     . "Cliente: {$customer_name}\n"
                     . "Email: {$customer_email}\n"
                     . "Valor: {$order_total}\n"
                     . "Data: " . wp_date('d/m/Y H:i:s') . "\n\n"
                     . "Matrículas não encontradas:\n";

            foreach ($missing_enrollments as $m) {
                $details .= "• {$m['product']} (Moodle #{$m['course_id']}) — {$m['reason']}\n";
            }

            LR_Telegram::alert(
                '🚨 Matrícula Não Concluída — Ação Necessária',
                $details
            );
        }
    }
}


// ============================================================================
// 3. MONITORAR ERROS NO LOG DO EDWISER BRIDGE
//    Verifica periodicamente o arquivo de log.
// ============================================================================

// Registrar o cron
add_filter('cron_schedules', 'lr_add_edwiser_check_interval');

if (!function_exists('lr_add_edwiser_check_interval')) {
    function lr_add_edwiser_check_interval($schedules) {
        $schedules['every_15_minutes'] = array(
            'interval' => 900,
            'display'  => 'A cada 15 minutos',
        );
        return $schedules;
    }
}

// Ativar o cron na primeira execução
if (!wp_next_scheduled('lr_check_edwiser_logs_cron')) {
    wp_schedule_event(time(), 'every_15_minutes', 'lr_check_edwiser_logs_cron');
}

add_action('lr_check_edwiser_logs_cron', 'lr_scan_edwiser_log_for_errors');

if (!function_exists('lr_scan_edwiser_log_for_errors')) {
    function lr_scan_edwiser_log_for_errors() {
        // Caminhos possíveis do log do Edwiser Bridge
        $log_paths = array(
            WP_CONTENT_DIR . '/uploads/starter-starter-log/starter-starter-log.log',
            WP_CONTENT_DIR . '/uploads/starter-starter-log.log',
            WP_CONTENT_DIR . '/uploads/starter-starter-log/starter-starter-debug.log',
        );

        $log_file = null;
        foreach ($log_paths as $path) {
            if (file_exists($path)) {
                $log_file = $path;
                break;
            }
        }

        if (!$log_file) {
            return;
        }

        // Posição da última verificação
        $last_position = get_option('lr_edwiser_log_last_position', 0);
        $current_size  = filesize($log_file);

        // Se o arquivo foi rotacionado (ficou menor), começar do zero
        if ($current_size < $last_position) {
            $last_position = 0;
        }

        // Nada novo para ler
        if ($current_size <= $last_position) {
            return;
        }

        // Ler apenas conteúdo novo
        $handle = fopen($log_file, 'r');
        fseek($handle, $last_position);
        $new_content = fread($handle, $current_size - $last_position);
        fclose($handle);

        // Salvar posição atual
        update_option('lr_edwiser_log_last_position', $current_size);

        // Padrões de erro para detectar
        $error_patterns = array(
            'cURL error',
            'Could not resolve host',
            'Connection timed out',
            'Connection refused',
            'enrollment failed',
            'enrol_manual_enrol_users',
            'ERROR',
            'Exception',
            'HTTP\/\d\.\d\s[45]\d{2}',
        );

        $pattern = '/' . implode('|', $error_patterns) . '/i';

        // Analisar linha por linha
        $lines       = explode("\n", $new_content);
        $error_lines = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && preg_match($pattern, $line)) {
                $error_lines[] = mb_substr($line, 0, 200);
            }
        }

        if (!empty($error_lines)) {
            // Limitar a 5 erros por alerta para não sobrecarregar
            $total  = count($error_lines);
            $sample = array_slice($error_lines, 0, 5);

            $details = "Encontrados {$total} erro(s) no log do Edwiser Bridge:\n\n"
                     . implode("\n\n", $sample);

            if ($total > 5) {
                $details .= "\n\n... e mais " . ($total - 5) . " erro(s). Verifique o log completo.";
            }

            LR_Telegram::alert(
                '📋 Erros Detectados no Log Edwiser Bridge',
                $details
            );
        }
    }
}


// ============================================================================
// 4. CLEANUP — Remover cron ao desativar o plugin
// ============================================================================

register_deactivation_hook(__FILE__, 'lr_edwiser_alert_deactivate');

if (!function_exists('lr_edwiser_alert_deactivate')) {
    function lr_edwiser_alert_deactivate() {
        wp_clear_scheduled_hook('lr_check_edwiser_logs_cron');
        wp_clear_scheduled_hook('lr_verify_enrollment_after_order');
        delete_option('lr_edwiser_log_last_position');
    }
}
