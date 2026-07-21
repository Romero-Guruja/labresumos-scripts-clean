<?php
/**
 * WPCode snippet #1650 — Edwiser Bridge - Alerta de Erros via Telegram
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
/**
 * Edwiser Bridge - Alerta de Erros via Telegram
 * 
 * Monitora erros de conexão/enrollment do Edwiser Bridge e envia
 * notificação imediata via webhook Guruja → Telegram.
 * 
 * Uso: Adicionar como snippet no WPCode (ou functions.php)
 * Prioridade: Execução em todo lugar (Run Everywhere)
 */

// ============================================================
// 1. INTERCEPTAR ERROS DE CURL DO EDWISER BRIDGE
//    O Edwiser Bridge usa wp_remote_post/get para chamar o Moodle.
//    Filtramos respostas HTTP com erro.
// ============================================================

add_filter('http_response', 'lr_monitor_edwiser_bridge_requests', 10, 3);

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

        lr_send_edwiser_telegram_alert(
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

            lr_send_edwiser_telegram_alert(
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


// ============================================================
// 2. MONITORAR ERROS DE ENROLLMENT ESPECÍFICOS
//    Hook no processo de enrollment do Edwiser Bridge.
// ============================================================

add_action('eb_order_status_completed', 'lr_monitor_enrollment_on_order', 999, 2);

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

add_action('lr_verify_enrollment_after_order', 'lr_check_enrollment_status');

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

        lr_send_edwiser_telegram_alert(
            '🚨 Matrícula Não Concluída — Ação Necessária',
            $details
        );
    }
}


// ============================================================
// 3. MONITORAR ERROS NO LOG DO EDWISER BRIDGE
//    Verifica periodicamente o arquivo de log.
// ============================================================

// Registrar o cron
add_filter('cron_schedules', 'lr_add_edwiser_check_interval');

function lr_add_edwiser_check_interval($schedules) {
    $schedules['every_15_minutes'] = array(
        'interval' => 900,
        'display'  => 'A cada 15 minutos',
    );
    return $schedules;
}

// Ativar o cron na primeira execução
if (!wp_next_scheduled('lr_check_edwiser_logs_cron')) {
    wp_schedule_event(time(), 'every_15_minutes', 'lr_check_edwiser_logs_cron');
}

add_action('lr_check_edwiser_logs_cron', 'lr_scan_edwiser_log_for_errors');

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

        lr_send_edwiser_telegram_alert(
            '📋 Erros Detectados no Log Edwiser Bridge',
            $details
        );
    }
}


// ============================================================
// 4. FUNÇÃO DE ENVIO — WEBHOOK GURUJA → TELEGRAM
// ============================================================

function lr_send_edwiser_telegram_alert($evento, $descricao) {
    $webhook_url = 'https://automation.guruja.com.br/webhook/b87b165b-6017-4156-97a6-1431cec04356';

    // Rate limiting: máximo 10 alertas por hora
    $transient_key = 'lr_edwiser_alert_count_' . date('YmdH');
    $alert_count   = (int) get_transient($transient_key);

    if ($alert_count >= 10) {
        // Log local mesmo que não envie
        error_log("[Lab Resumos Alert Throttled] {$evento}: {$descricao}");
        return;
    }

    $response = wp_remote_post($webhook_url, array(
        'timeout' => 10,
        'headers' => array('Content-Type' => 'application/json'),
        'body'    => wp_json_encode(array(
            'evento'    => $evento,
            'descricao' => $descricao,
        )),
    ));

    // Incrementar contador
    set_transient($transient_key, $alert_count + 1, HOUR_IN_SECONDS);

    // Log local para referência
    if (is_wp_error($response)) {
        error_log("[Lab Resumos Alert FAILED] {$evento}: " . $response->get_error_message());
    } else {
        error_log("[Lab Resumos Alert Sent] {$evento}");
    }
}


// ============================================================
// 5. CLEANUP — Remover cron ao desativar snippet
// ============================================================

register_deactivation_hook(__FILE__, 'lr_edwiser_alert_deactivate');

function lr_edwiser_alert_deactivate() {
    wp_clear_scheduled_hook('lr_check_edwiser_logs_cron');
    wp_clear_scheduled_hook('lr_verify_enrollment_after_order');
    delete_option('lr_edwiser_log_last_position');
}