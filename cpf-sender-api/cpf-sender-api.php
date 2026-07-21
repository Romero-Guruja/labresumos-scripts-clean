<?php
/**
 * Plugin Name: CPF Sender API
 * Description: Envia CPF de clientes e afiliados para endpoint externo (Edwiser Bridge + Lab Resumos Parceiros)
 * Version: 2.2.0
 * Author: Lab Resumos
 * Text Domain: cpf-sender-api
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) exit;

// =============================================================================
// SEÇÃO 1: CONSTANTES E ATIVAÇÃO
// =============================================================================

define('CPF_SENDER_VERSION', '2.2.0');
define('CPF_SENDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CPF_SENDER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Configurações de retry
define('CPF_SENDER_MAX_ATTEMPTS', 15); // Aumentado de 3 para 15 tentativas

/**
 * Calcular intervalo de backoff exponencial baseado no número de tentativas
 * 
 * - Tentativa 1-3: 1 minuto (60s)
 * - Tentativa 4-6: 2 minutos (120s)
 * - Tentativa 7-9: 4 minutos (240s)
 * - Tentativa 10-12: 8 minutos (480s)
 * - Tentativa 13+: 15 minutos (900s) máximo
 * 
 * @param int $attempts Número de tentativas já realizadas
 * @return int Intervalo em segundos até próxima tentativa
 */
function cpf_sender_get_backoff_interval($attempts) {
    if ($attempts <= 3) {
        return 60;    // 1 minuto
    } elseif ($attempts <= 6) {
        return 120;   // 2 minutos
    } elseif ($attempts <= 9) {
        return 240;   // 4 minutos
    } elseif ($attempts <= 12) {
        return 480;   // 8 minutos
    } else {
        return 900;   // 15 minutos (máximo)
    }
}

/**
 * Formatar intervalo de backoff para exibição
 * 
 * @param int $seconds Intervalo em segundos
 * @return string Intervalo formatado
 */
function cpf_sender_format_backoff($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . 'min';
    } else {
        return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'min';
    }
}

/**
 * Ativação do plugin
 */
register_activation_hook(__FILE__, 'cpf_sender_activate');

function cpf_sender_activate() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cpf_sender_logs';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        user_email VARCHAR(255) NOT NULL,
        cpf_masked VARCHAR(20) NOT NULL,
        endpoint_url TEXT NOT NULL,
        http_method VARCHAR(10) NOT NULL,
        http_status_code INT DEFAULT NULL,
        response_body TEXT DEFAULT NULL,
        error_message TEXT DEFAULT NULL,
        status ENUM('success', 'error', 'pending') DEFAULT 'pending',
        attempts INT DEFAULT 1,
        type VARCHAR(20) DEFAULT 'cliente',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        INDEX idx_type (type)
    ) {$charset_collate};";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Adicionar coluna 'type' se não existir (para upgrades)
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'type'",
        DB_NAME,
        $table_name
    ));
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN type VARCHAR(20) DEFAULT 'cliente' AFTER attempts");
        $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_type (type)");
    }
    
    // Opções padrão
    add_option('cpf_sender_http_method', 'POST');
    add_option('cpf_sender_header_name', 'X-API-Key');
    add_option('cpf_sender_delay_seconds', 30);
    add_option('cpf_sender_enable_logs', '1');
    add_option('cpf_sender_alert_on_error', '1');
    
    // Opções do Telegram
    add_option('cpf_sender_telegram_enabled', '1');
    add_option('cpf_sender_telegram_webhook_url', 'https://automation.guruja.com.br/webhook/b87b165b-6017-4156-97a6-1431cec04356');
    add_option('cpf_sender_telegram_delay_minutes', 5);

    // Opções de verificação da escrita (API própria — o Hookdeck só confirma recebimento, não a escrita real)
    add_option('cpf_sender_verify_api_url', 'https://api-laboratorio-resumos.azurewebsites.net/api/v1/hookdeck/lab-user/document/status');
    add_option('cpf_sender_verify_api_key', '');

    // Agendar limpeza de logs antigos
    if (!wp_next_scheduled('cpf_sender_cleanup_logs')) {
        wp_schedule_event(time(), 'daily', 'cpf_sender_cleanup_logs');
    }
    
    // Agendar verificação de pendências (a cada minuto)
    if (!wp_next_scheduled('cpf_sender_check_pending')) {
        wp_schedule_event(time(), 'every_minute', 'cpf_sender_check_pending');
    }
}

/**
 * Desativação do plugin
 */
register_deactivation_hook(__FILE__, 'cpf_sender_deactivate');

function cpf_sender_deactivate() {
    // Remover eventos agendados
    wp_clear_scheduled_hook('cpf_sender_scheduled_send');
    wp_clear_scheduled_hook('cpf_sender_cleanup_logs');
    wp_clear_scheduled_hook('cpf_sender_check_pending');
    wp_clear_scheduled_hook('cpf_sender_telegram_check');
    wp_clear_scheduled_hook('cpf_sender_verify_write');
}

/**
 * Registrar intervalo customizado de 1 minuto
 */
add_filter('cron_schedules', 'cpf_sender_add_cron_interval');

function cpf_sender_add_cron_interval($schedules) {
    $schedules['every_minute'] = array(
        'interval' => 60,
        'display'  => 'A cada minuto'
    );
    return $schedules;
}

/**
 * Limpeza de logs antigos
 */
add_action('cpf_sender_cleanup_logs', 'cpf_sender_do_cleanup');

function cpf_sender_do_cleanup() {
    global $wpdb;
    $table = $wpdb->prefix . 'cpf_sender_logs';
    
    // Manter logs dos últimos 30 dias
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table} WHERE created_at < %s",
        date('Y-m-d H:i:s', strtotime('-30 days'))
    ));
}

/**
 * Verificar e processar pendências antigas com backoff exponencial
 * Processa tanto clientes quanto afiliados
 */
add_action('cpf_sender_check_pending', 'cpf_sender_process_stale_pending');

function cpf_sender_process_stale_pending() {
    // Processar pendências de clientes
    cpf_sender_process_pending_by_type('cliente');
    
    // Processar pendências de afiliados
    cpf_sender_process_pending_by_type('afiliado');
}

/**
 * Processar pendências por tipo (cliente ou afiliado)
 * 
 * @param string $type Tipo: 'cliente' ou 'afiliado'
 */
function cpf_sender_process_pending_by_type($type = 'cliente') {
    global $wpdb;
    
    // Definir meta keys baseado no tipo
    $status_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_status' : '_cpf_sender_status';
    $pending_since_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_pending_since' : '_cpf_sender_pending_since';
    $attempts_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_attempts' : '_cpf_sender_attempts';
    
    // Buscar todos os usuários com status pendente deste tipo
    $pending_users = $wpdb->get_results($wpdb->prepare(
        "SELECT um1.user_id, um1.meta_value as pending_since 
         FROM {$wpdb->usermeta} um1
         INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
         WHERE um1.meta_key = %s 
         AND um2.meta_key = %s 
         AND um2.meta_value = 'pending'",
        $pending_since_key,
        $status_key
    ));
    
    foreach ($pending_users as $pending_user) {
        $user_id = absint($pending_user->user_id);
        $pending_since = absint($pending_user->pending_since);
        $attempts = absint(get_user_meta($user_id, $attempts_key, true));
        
        // Verificar se atingiu o máximo de tentativas
        if ($attempts >= CPF_SENDER_MAX_ATTEMPTS) {
            // Marcar como erro definitivo
            cpf_sender_mark_as_failed($user_id, 'Máximo de tentativas atingido (' . CPF_SENDER_MAX_ATTEMPTS . ')', $type);
            continue;
        }
        
        // Calcular o intervalo de backoff baseado nas tentativas
        $backoff_interval = cpf_sender_get_backoff_interval($attempts);
        $time_since_last_attempt = time() - $pending_since;
        
        // Verificar se já passou tempo suficiente para nova tentativa
        if ($time_since_last_attempt < $backoff_interval) {
            // Ainda não é hora de tentar novamente
            continue;
        }
        
        // Incrementar tentativas e tentar reenviar
        $new_attempts = $attempts + 1;
        update_user_meta($user_id, $attempts_key, $new_attempts);
        update_user_meta($user_id, $pending_since_key, time()); // Reset do timestamp
        
        // Calcular próximo intervalo para log
        $next_backoff = cpf_sender_get_backoff_interval($new_attempts);
        $next_backoff_formatted = cpf_sender_format_backoff($next_backoff);
        
        // Registrar tentativa de retry no log
        $user = get_userdata($user_id);
        $email = $user ? $user->user_email : 'N/A';
        $cpf = cpf_sender_get_user_cpf($user_id, $type);
        $cpf_masked = $cpf ? cpf_sender_mask_cpf($cpf) : '***';
        
        cpf_sender_save_log(array(
            'user_id'          => $user_id,
            'user_email'       => $email,
            'cpf_masked'       => $cpf_masked,
            'endpoint_url'     => get_option('cpf_sender_endpoint_url', 'N/A'),
            'http_method'      => get_option('cpf_sender_http_method', 'POST'),
            'http_status_code' => null,
            'response_body'    => null,
            'error_message'    => sprintf('[%s] Retry automático - tentativa %d/%d (próximo retry em %s se falhar)', 
                                          $type, $new_attempts, CPF_SENDER_MAX_ATTEMPTS, $next_backoff_formatted),
            'status'           => 'pending',
            'attempts'         => $new_attempts,
            'type'             => $type
        ));
        
        // Tentar enviar novamente
        $result = cpf_sender_send_to_api($user_id, $type);
        
        // Se falhou e ainda não atingiu máximo, manter como pending para próxima verificação
        if (!$result && $new_attempts < CPF_SENDER_MAX_ATTEMPTS) {
            // O status já foi atualizado em cpf_sender_process_response
            // Resetar para pending para próxima verificação com backoff
            $current_status = get_user_meta($user_id, $status_key, true);
            if ($current_status === 'error') {
                update_user_meta($user_id, $status_key, 'pending');
                update_user_meta($user_id, $pending_since_key, time());
            }
        }
    }
}

/**
 * Marcar usuário como falha definitiva após máximo de tentativas
 * 
 * @param int    $user_id  ID do usuário WordPress
 * @param string $reason   Motivo da falha
 * @param string $type     Tipo: 'cliente' ou 'afiliado'
 */
function cpf_sender_mark_as_failed($user_id, $reason, $type = 'cliente') {
    // Definir meta keys baseado no tipo
    $status_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_status' : '_cpf_sender_status';
    $error_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_last_error' : '_cpf_sender_last_error';
    $pending_since_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_pending_since' : '_cpf_sender_pending_since';
    $attempts_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_attempts' : '_cpf_sender_attempts';
    
    $user = get_userdata($user_id);
    $email = $user ? $user->user_email : 'N/A';
    $cpf = cpf_sender_get_user_cpf($user_id, $type);
    $cpf_masked = $cpf ? cpf_sender_mask_cpf($cpf) : '***';
    $attempts = absint(get_user_meta($user_id, $attempts_key, true));
    
    // Atualizar status para erro
    update_user_meta($user_id, $status_key, 'error');
    update_user_meta($user_id, $error_key, $reason);
    delete_user_meta($user_id, $pending_since_key);
    
    // Salvar log de falha
    cpf_sender_save_log(array(
        'user_id'          => $user_id,
        'user_email'       => $email,
        'cpf_masked'       => $cpf_masked,
        'endpoint_url'     => get_option('cpf_sender_endpoint_url', 'N/A'),
        'http_method'      => get_option('cpf_sender_http_method', 'POST'),
        'http_status_code' => null,
        'response_body'    => null,
        'error_message'    => "[{$type}] " . $reason,
        'status'           => 'error',
        'attempts'         => $attempts,
        'type'             => $type
    ));
    
    // Enviar alerta ao admin (email)
    cpf_sender_send_max_attempts_alert($user_id, $email, $attempts, $type);
    
    // Enviar alerta ao Telegram
    $type_label = ($type === 'afiliado') ? 'Afiliado' : 'Cliente';
    cpf_sender_send_telegram(
        "CRITICO - CPF Sender ({$type_label})",
        "Maximo de tentativas atingido ({$attempts}/" . CPF_SENDER_MAX_ATTEMPTS . ")\n" .
        "Email: {$email}\n" .
        "User ID: {$user_id}\n" .
        "Este {$type_label} NAO teve seu CPF enviado para a API.\n" .
        "Data/Hora: " . current_time('d/m/Y H:i:s')
    );
}

/**
 * Alerta especial para máximo de tentativas atingido
 * 
 * @param int    $user_id     ID do usuário WordPress
 * @param string $user_email  Email do usuário
 * @param int    $attempts    Número de tentativas
 * @param string $type        Tipo: 'cliente' ou 'afiliado'
 */
function cpf_sender_send_max_attempts_alert($user_id, $user_email, $attempts, $type = 'cliente') {
    // Verificar se alertas estão habilitados
    if (get_option('cpf_sender_alert_on_error') !== '1') {
        return;
    }
    
    // Email do destinatário
    $admin_email = get_option('cpf_sender_admin_email');
    if (empty($admin_email)) {
        $admin_email = get_option('admin_email');
    }
    
    // Tipo formatado
    $type_label = ($type === 'afiliado') ? 'AFILIADO' : 'CLIENTE';
    $type_emoji = ($type === 'afiliado') ? '🤝' : '🛒';
    
    // Assunto
    $subject = "[CPF Sender] {$type_emoji} ATENÇÃO: Máximo de tentativas atingido ({$type_label}) - " . $user_email;
    
    // Corpo do email
    $message = sprintf(
        "⚠️ ATENÇÃO: O envio de CPF para a API falhou após %d tentativas.\n\n" .
        "Detalhes:\n" .
        "- Tipo: %s %s\n" .
        "- Usuário ID: %d\n" .
        "- Email: %s\n" .
        "- Data/Hora: %s\n" .
        "- Tentativas realizadas: %d\n\n" .
        "Este %s NÃO teve seu CPF enviado para a API.\n" .
        "Por favor, verifique a configuração e tente enviar manualmente:\n%s\n\n" .
        "Ou acesse a página de configurações para mais detalhes:\n%s",
        $attempts,
        $type_emoji,
        $type_label,
        $user_id,
        $user_email,
        current_time('d/m/Y H:i:s'),
        $attempts,
        strtolower($type_label),
        admin_url('users.php?s=' . urlencode($user_email)),
        admin_url('options-general.php?page=cpf-sender-settings')
    );
    
    // Headers
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    // Enviar
    wp_mail($admin_email, $subject, $message, $headers);
}

// =============================================================================
// SEÇÃO 2: CONFIGURAÇÕES (Settings API)
// =============================================================================

add_action('admin_menu', 'cpf_sender_add_settings_page');
add_action('admin_init', 'cpf_sender_register_settings');
add_action('admin_enqueue_scripts', 'cpf_sender_admin_scripts');

function cpf_sender_add_settings_page() {
    add_options_page(
        'CPF Sender API',
        'CPF Sender',
        'manage_options',
        'cpf-sender-settings',
        'cpf_sender_settings_page'
    );
}

function cpf_sender_register_settings() {
    register_setting('cpf_sender_settings', 'cpf_sender_endpoint_url', array(
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => ''
    ));
    
    register_setting('cpf_sender_settings', 'cpf_sender_http_method', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'POST'
    ));
    
    register_setting('cpf_sender_settings', 'cpf_sender_header_name', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'X-API-Key'
    ));
    
    register_setting('cpf_sender_settings', 'cpf_sender_api_key', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ));
    
    register_setting('cpf_sender_settings', 'cpf_sender_delay_seconds', array(
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 30
    ));
    
    register_setting('cpf_sender_settings', 'cpf_sender_enable_logs', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '1'
    ));
    
    register_setting('cpf_sender_settings', 'cpf_sender_admin_email', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_email',
        'default' => ''
    ));
    
    register_setting('cpf_sender_settings', 'cpf_sender_alert_on_error', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '1'
    ));
    
    // Telegram settings
    register_setting('cpf_sender_settings', 'cpf_sender_telegram_enabled', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '1'
    ));
    
    register_setting('cpf_sender_settings', 'cpf_sender_telegram_webhook_url', array(
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => 'https://automation.guruja.com.br/webhook/b87b165b-6017-4156-97a6-1431cec04356'
    ));
    
    register_setting('cpf_sender_settings', 'cpf_sender_telegram_delay_minutes', array(
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 5
    ));

    // Verificação da escrita (API própria)
    register_setting('cpf_sender_settings', 'cpf_sender_verify_api_url', array(
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => 'https://api-laboratorio-resumos.azurewebsites.net/api/v1/hookdeck/lab-user/document/status'
    ));

    register_setting('cpf_sender_settings', 'cpf_sender_verify_api_key', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ));
}

function cpf_sender_admin_scripts($hook) {
    if ($hook !== 'settings_page_cpf-sender-settings') {
        return;
    }
    
    wp_enqueue_style('cpf-sender-admin', CPF_SENDER_PLUGIN_URL . 'assets/css/admin.css', array(), CPF_SENDER_VERSION);
    
    // Verificar se o arquivo JS existe antes de enfileirar
    $js_path = CPF_SENDER_PLUGIN_DIR . 'assets/js/admin.js';
    if (file_exists($js_path)) {
        wp_enqueue_script('cpf-sender-admin', CPF_SENDER_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), CPF_SENDER_VERSION, true);
        
        wp_localize_script('cpf-sender-admin', 'cpfSenderAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cpf_sender_test')
        ));
    }
}

/**
 * Handler customizado para salvar configurações
 */
add_action('admin_post_cpf_sender_save_settings', 'cpf_sender_save_settings_handler');

function cpf_sender_save_settings_handler() {
    // Verificar permissões
    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado');
    }
    
    // Verificar nonce
    if (!isset($_POST['cpf_sender_settings_nonce']) || 
        !wp_verify_nonce($_POST['cpf_sender_settings_nonce'], 'cpf_sender_save_settings')) {
        wp_die('Erro de segurança. Por favor, tente novamente.');
    }
    
    // Salvar cada opção
    if (isset($_POST['cpf_sender_endpoint_url'])) {
        update_option('cpf_sender_endpoint_url', esc_url_raw($_POST['cpf_sender_endpoint_url']));
    }
    
    if (isset($_POST['cpf_sender_http_method'])) {
        $method = sanitize_text_field($_POST['cpf_sender_http_method']);
        $allowed_methods = array('GET', 'POST', 'PUT', 'PATCH');
        if (in_array(strtoupper($method), $allowed_methods)) {
            update_option('cpf_sender_http_method', strtoupper($method));
        }
    }
    
    if (isset($_POST['cpf_sender_auth_type'])) {
        update_option('cpf_sender_auth_type', sanitize_text_field($_POST['cpf_sender_auth_type']));
    }
    
    if (isset($_POST['cpf_sender_header_name'])) {
        update_option('cpf_sender_header_name', sanitize_text_field($_POST['cpf_sender_header_name']));
    }
    
    if (isset($_POST['cpf_sender_api_key'])) {
        update_option('cpf_sender_api_key', sanitize_text_field($_POST['cpf_sender_api_key']));
    }
    
    if (isset($_POST['cpf_sender_basic_auth_username'])) {
        update_option('cpf_sender_basic_auth_username', sanitize_text_field($_POST['cpf_sender_basic_auth_username']));
    }
    
    if (isset($_POST['cpf_sender_basic_auth_password'])) {
        update_option('cpf_sender_basic_auth_password', sanitize_text_field($_POST['cpf_sender_basic_auth_password']));
    }
    
    if (isset($_POST['cpf_sender_delay_seconds'])) {
        update_option('cpf_sender_delay_seconds', absint($_POST['cpf_sender_delay_seconds']));
    }
    
    if (isset($_POST['cpf_sender_enable_logs'])) {
        update_option('cpf_sender_enable_logs', '1');
    } else {
        update_option('cpf_sender_enable_logs', '0');
    }
    
    if (isset($_POST['cpf_sender_alert_on_error'])) {
        update_option('cpf_sender_alert_on_error', '1');
    } else {
        update_option('cpf_sender_alert_on_error', '0');
    }
    
    if (isset($_POST['cpf_sender_admin_email'])) {
        $email = sanitize_email($_POST['cpf_sender_admin_email']);
        update_option('cpf_sender_admin_email', $email);
    }
    
    // Telegram settings
    if (isset($_POST['cpf_sender_telegram_enabled'])) {
        update_option('cpf_sender_telegram_enabled', '1');
    } else {
        update_option('cpf_sender_telegram_enabled', '0');
    }
    
    if (isset($_POST['cpf_sender_telegram_webhook_url'])) {
        update_option('cpf_sender_telegram_webhook_url', esc_url_raw($_POST['cpf_sender_telegram_webhook_url']));
    }
    
    if (isset($_POST['cpf_sender_telegram_delay_minutes'])) {
        $delay = absint($_POST['cpf_sender_telegram_delay_minutes']);
        if ($delay < 1) $delay = 5;
        update_option('cpf_sender_telegram_delay_minutes', $delay);
    }

    if (isset($_POST['cpf_sender_verify_api_url'])) {
        update_option('cpf_sender_verify_api_url', esc_url_raw($_POST['cpf_sender_verify_api_url']));
    }

    if (isset($_POST['cpf_sender_verify_api_key'])) {
        update_option('cpf_sender_verify_api_key', sanitize_text_field($_POST['cpf_sender_verify_api_key']));
    }

    // Redirecionar de volta com mensagem de sucesso
    $redirect_url = add_query_arg(
        array(
            'page' => 'cpf-sender-settings',
            'settings-updated' => 'true'
        ),
        admin_url('options-general.php')
    );
    
    wp_safe_redirect($redirect_url);
    exit;
}

function cpf_sender_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado');
    }
    
    // Processar limpeza de logs
    if (isset($_POST['clear_old_logs']) && check_admin_referer('cpf_sender_clear_logs')) {
        global $wpdb;
        $table = $wpdb->prefix . 'cpf_sender_logs';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(
            'Logs antigos removidos: %d registro(s)',
            $deleted
        ) . '</p></div>';
    }
    
    ?>
    <div class="wrap cpf-sender-settings">
        <h1>CPF Sender API</h1>
        <p class="description">Configurações para envio de CPF para API externa</p>
        
        <?php if (isset($_GET['settings-updated'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>Configurações salvas com sucesso!</p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="cpf_sender_save_settings">
            <?php wp_nonce_field('cpf_sender_save_settings', 'cpf_sender_settings_nonce'); ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="#api-settings" class="nav-tab nav-tab-active">Configurações da API</a>
                <a href="#logs" class="nav-tab">Logs</a>
            </h2>
            
            <div id="api-settings" class="tab-content">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cpf_sender_endpoint_url">URL do Endpoint *</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="cpf_sender_endpoint_url" 
                                   name="cpf_sender_endpoint_url" 
                                   value="<?php echo esc_attr(get_option('cpf_sender_endpoint_url')); ?>" 
                                   class="regular-text" 
                                   required />
                            <p class="description">URL completa para onde o CPF será enviado</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cpf_sender_http_method">Método HTTP</label>
                        </th>
                        <td>
                            <select id="cpf_sender_http_method" name="cpf_sender_http_method">
                                <option value="POST" <?php selected(get_option('cpf_sender_http_method'), 'POST'); ?>>POST</option>
                                <option value="GET" <?php selected(get_option('cpf_sender_http_method'), 'GET'); ?>>GET</option>
                                <option value="PUT" <?php selected(get_option('cpf_sender_http_method'), 'PUT'); ?>>PUT</option>
                                <option value="PATCH" <?php selected(get_option('cpf_sender_http_method'), 'PATCH'); ?>>PATCH</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cpf_sender_auth_type">Tipo de Autenticação</label>
                        </th>
                        <td>
                            <select id="cpf_sender_auth_type" name="cpf_sender_auth_type">
                                <option value="api_key" <?php selected(get_option('cpf_sender_auth_type', 'api_key'), 'api_key'); ?>>API Key (Header Customizado)</option>
                                <option value="basic_auth" <?php selected(get_option('cpf_sender_auth_type'), 'basic_auth'); ?>>Basic Auth</option>
                            </select>
                            <p class="description">Escolha o método de autenticação do endpoint</p>
                        </td>
                    </tr>
                    
                    <?php 
                    $auth_type = get_option('cpf_sender_auth_type', 'api_key');
                    $show_api_key = ($auth_type !== 'basic_auth');
                    $show_basic_auth = ($auth_type === 'basic_auth');
                    ?>
                    <tr id="api-key-fields" <?php echo $show_basic_auth ? 'style="display:none;"' : ''; ?>>
                        <th scope="row">
                            <label for="cpf_sender_header_name">Nome do Header de Autenticação</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="cpf_sender_header_name" 
                                   name="cpf_sender_header_name" 
                                   value="<?php echo esc_attr(get_option('cpf_sender_header_name', 'X-API-Key')); ?>" 
                                   class="regular-text" />
                            <p class="description">Ex: X-API-Key, Authorization, Api-Key</p>
                        </td>
                    </tr>
                    
                    <tr id="api-key-value-field" <?php echo $show_basic_auth ? 'style="display:none;"' : ''; ?>>
                        <th scope="row">
                            <label for="cpf_sender_api_key">API Key <?php echo $show_api_key ? '*' : ''; ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="cpf_sender_api_key" 
                                   name="cpf_sender_api_key" 
                                   value="<?php echo esc_attr(get_option('cpf_sender_api_key')); ?>" 
                                   class="regular-text" 
                                   <?php echo $show_api_key ? 'required' : ''; ?> />
                            <p class="description">Chave de API para autenticação</p>
                        </td>
                    </tr>
                    
                    <tr id="basic-auth-fields" <?php echo $show_api_key ? 'style="display:none;"' : ''; ?>>
                        <th scope="row">
                            <label for="cpf_sender_basic_auth_username">Username (Basic Auth) <?php echo $show_basic_auth ? '*' : ''; ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="cpf_sender_basic_auth_username" 
                                   name="cpf_sender_basic_auth_username" 
                                   value="<?php echo esc_attr(get_option('cpf_sender_basic_auth_username')); ?>" 
                                   class="regular-text" 
                                   <?php echo $show_basic_auth ? 'required' : ''; ?> />
                            <p class="description">Username para Basic Authentication</p>
                        </td>
                    </tr>
                    
                    <tr id="basic-auth-password-field" <?php echo $show_api_key ? 'style="display:none;"' : ''; ?>>
                        <th scope="row">
                            <label for="cpf_sender_basic_auth_password">Password (Basic Auth) <?php echo $show_basic_auth ? '*' : ''; ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="cpf_sender_basic_auth_password" 
                                   name="cpf_sender_basic_auth_password" 
                                   value="<?php echo esc_attr(get_option('cpf_sender_basic_auth_password')); ?>" 
                                   class="regular-text" 
                                   <?php echo $show_basic_auth ? 'required' : ''; ?> />
                            <p class="description">Password para Basic Authentication</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cpf_sender_delay_seconds">Delay após matrícula no Moodle (segundos)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="cpf_sender_delay_seconds" 
                                   name="cpf_sender_delay_seconds" 
                                   value="<?php echo esc_attr(get_option('cpf_sender_delay_seconds', 30)); ?>" 
                                   min="0" 
                                   step="1" 
                                   class="small-text" />
                            <p class="description">Tempo de espera após o Edwiser Bridge matricular o aluno</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Logs e Alertas</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                           name="cpf_sender_enable_logs" 
                                           value="1" 
                                           <?php checked(get_option('cpf_sender_enable_logs'), '1'); ?> />
                                    Habilitar logs detalhados
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" 
                                           name="cpf_sender_alert_on_error" 
                                           value="1" 
                                           <?php checked(get_option('cpf_sender_alert_on_error'), '1'); ?> />
                                    Enviar email em caso de falha
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cpf_sender_admin_email">Email para alertas</label>
                        </th>
                        <td>
                            <input type="email" 
                                   id="cpf_sender_admin_email" 
                                   name="cpf_sender_admin_email" 
                                   value="<?php echo esc_attr(get_option('cpf_sender_admin_email')); ?>" 
                                   class="regular-text" />
                            <p class="description">Deixe vazio para usar email do admin</p>
                        </td>
                    </tr>
                </table>
                
                <hr>
                <h2>Notificações Telegram</h2>
                <p class="description">Receba alertas no Telegram quando um envio de CPF não for concluído com sucesso dentro do tempo configurado.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Ativar Telegram</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                           name="cpf_sender_telegram_enabled" 
                                           value="1" 
                                           <?php checked(get_option('cpf_sender_telegram_enabled'), '1'); ?> />
                                    Enviar alertas via Telegram quando o envio de CPF falhar
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cpf_sender_telegram_webhook_url">URL do Webhook Telegram</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="cpf_sender_telegram_webhook_url" 
                                   name="cpf_sender_telegram_webhook_url" 
                                   value="<?php echo esc_attr(get_option('cpf_sender_telegram_webhook_url', 'https://automation.guruja.com.br/webhook/b87b165b-6017-4156-97a6-1431cec04356')); ?>" 
                                   class="regular-text" />
                            <p class="description">Endpoint do webhook que envia mensagens para o Telegram (n8n/automation)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cpf_sender_telegram_delay_minutes">Tempo de espera (minutos)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="cpf_sender_telegram_delay_minutes" 
                                   name="cpf_sender_telegram_delay_minutes" 
                                   value="<?php echo esc_attr(get_option('cpf_sender_telegram_delay_minutes', 5)); ?>" 
                                   min="1" 
                                   max="60"
                                   step="1" 
                                   class="small-text" />
                            <p class="description">Se após este tempo o CPF ainda não tiver sido enviado com sucesso, envia alerta no Telegram</p>
                        </td>
                    </tr>
                </table>

                <hr>
                <h2>Verificação da Escrita (API própria)</h2>
                <p class="description">O Hookdeck confirma só o recebimento (ack assíncrono), não a escrita real do CPF. Após o envio, o plugin confirma aqui se o document foi mesmo gravado antes de marcar como sucesso.</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cpf_sender_verify_api_url">URL de verificação</label>
                        </th>
                        <td>
                            <input type="url"
                                   id="cpf_sender_verify_api_url"
                                   name="cpf_sender_verify_api_url"
                                   value="<?php echo esc_attr(get_option('cpf_sender_verify_api_url', 'https://api-laboratorio-resumos.azurewebsites.net/api/v1/hookdeck/lab-user/document/status')); ?>"
                                   class="regular-text" />
                            <p class="description">Endpoint GET da API que confirma se o document foi gravado</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="cpf_sender_verify_api_key">Chave de verificação</label>
                        </th>
                        <td>
                            <input type="password"
                                   id="cpf_sender_verify_api_key"
                                   name="cpf_sender_verify_api_key"
                                   value="<?php echo esc_attr(get_option('cpf_sender_verify_api_key', '')); ?>"
                                   class="regular-text" />
                            <p class="description">Enviada no header X-Cpf-Status-Key. Se vazia, a verificação é pulada (comportamento antigo)</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Salvar Configurações'); ?>
                
                <hr>
                
                <h3>Testes de Conexão</h3>
                <p>
                    <button type="button" id="cpf-sender-test-btn" class="button">🔄 Testar Endpoint API</button>
                    <button type="button" id="cpf-sender-test-telegram-btn" class="button" style="margin-left: 10px;">📲 Testar Telegram</button>
                </p>
                <div id="cpf-sender-test-result"></div>
                <div id="cpf-sender-test-telegram-result" style="margin-top: 10px;"></div>
            </div>
            
            <div id="logs" class="tab-content" style="display:none;">
                <?php cpf_sender_display_logs(); ?>
            </div>
        </form>
        
        <?php cpf_sender_display_stats(); ?>
    </div>
    <?php
}

function cpf_sender_display_stats() {
    global $wpdb;
    $table = $wpdb->prefix . 'cpf_sender_logs';
    
    // Verificar se coluna type existe
    $type_column_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'type'",
        DB_NAME,
        $table
    ));
    
    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $success = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'success'");
    $errors = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'error'");
    
    // Estatísticas por tipo (se coluna existir)
    $clientes_success = 0;
    $afiliados_success = 0;
    
    if ($type_column_exists) {
        $clientes_success = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'success' AND (type = 'cliente' OR type IS NULL)");
        $afiliados_success = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'success' AND type = 'afiliado'");
    }
    
    ?>
    <div class="cpf-sender-stats">
        <div class="cpf-sender-stat">
            <div class="cpf-sender-stat-number"><?php echo esc_html($total); ?></div>
            <div class="cpf-sender-stat-label">📊 Total enviados</div>
        </div>
        <div class="cpf-sender-stat">
            <div class="cpf-sender-stat-number" style="color:#46b450;"><?php echo esc_html($success); ?></div>
            <div class="cpf-sender-stat-label">✅ Sucesso</div>
        </div>
        <div class="cpf-sender-stat">
            <div class="cpf-sender-stat-number" style="color:#dc3232;"><?php echo esc_html($errors); ?></div>
            <div class="cpf-sender-stat-label">❌ Erros</div>
        </div>
        <?php if ($type_column_exists): ?>
        <div class="cpf-sender-stat">
            <div class="cpf-sender-stat-number" style="color:#3498db;"><?php echo esc_html($clientes_success); ?></div>
            <div class="cpf-sender-stat-label">🛒 Clientes OK</div>
        </div>
        <div class="cpf-sender-stat">
            <div class="cpf-sender-stat-number" style="color:#9b59b6;"><?php echo esc_html($afiliados_success); ?></div>
            <div class="cpf-sender-stat-label">🤝 Afiliados OK</div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

function cpf_sender_display_logs() {
    global $wpdb;
    $table = $wpdb->prefix . 'cpf_sender_logs';
    
    $logs = $wpdb->get_results(
        "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 30",
        ARRAY_A
    );
    
    ?>
    <h3>Logs Recentes (últimos 30)</h3>
    <table class="wp-list-table widefat fixed striped cpf-sender-logs-table">
        <thead>
            <tr>
                <th>Data/Hora</th>
                <th>Tipo</th>
                <th>Email</th>
                <th>CPF</th>
                <th>Status</th>
                <th>HTTP</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="7">Nenhum log encontrado</td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <?php 
                    $type = isset($log['type']) ? $log['type'] : 'cliente';
                    $type_label = ($type === 'afiliado') ? '🤝 Afiliado' : '🛒 Cliente';
                    $type_color = ($type === 'afiliado') ? '#9b59b6' : '#3498db';
                    ?>
                    <tr>
                        <td><?php echo esc_html(date('d/m H:i:s', strtotime($log['created_at']))); ?></td>
                        <td><span style="color: <?php echo $type_color; ?>; font-weight: 500;"><?php echo $type_label; ?></span></td>
                        <td><?php echo esc_html($log['user_email']); ?></td>
                        <td><?php echo esc_html($log['cpf_masked']); ?></td>
                        <td>
                            <?php if ($log['status'] === 'success'): ?>
                                <span class="status-success">✅ OK</span>
                            <?php elseif ($log['status'] === 'error'): ?>
                                <span class="status-error">❌ Erro</span>
                            <?php else: ?>
                                <span>⏳ Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($log['http_status_code'] ?: '—'); ?></td>
                        <td>
                            <a href="#" class="cpf-sender-view-log" data-log-id="<?php echo esc_attr($log['id']); ?>">Ver</a>
                        </td>
                    </tr>
                    <tr id="cpf-sender-log-detail-<?php echo esc_attr($log['id']); ?>" style="display:none;">
                        <td colspan="7">
                            <div style="padding: 15px; background: #f9f9f9; border-left: 4px solid <?php echo $type_color; ?>;">
                                <strong>Detalhes do Envio #<?php echo esc_html($log['id']); ?> (<?php echo ucfirst($type); ?>)</strong><br>
                                <strong>Request:</strong> <?php echo esc_html($log['http_method']); ?> <?php echo esc_html($log['endpoint_url']); ?><br>
                                <?php if ($log['error_message']): ?>
                                    <strong>Erro:</strong> <?php echo esc_html($log['error_message']); ?><br>
                                <?php endif; ?>
                                <?php if ($log['response_body']): ?>
                                    <strong>Response:</strong> <pre style="background: #fff; padding: 10px; overflow-x: auto;"><?php echo esc_html($log['response_body']); ?></pre>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <form method="post" style="margin-top: 20px;">
        <?php wp_nonce_field('cpf_sender_clear_logs'); ?>
        <button type="submit" name="clear_old_logs" id="cpf-sender-clear-logs" class="button">🗑️ Limpar Logs Antigos (>30 dias)</button>
    </form>
    <?php
}

// =============================================================================
// SEÇÃO 3: OBTENÇÃO DO CPF
// =============================================================================

/**
 * Obter CPF do usuário (ordem de prioridade)
 * 
 * @param int    $user_id  ID do usuário WordPress
 * @param string $type     Tipo: 'cliente' ou 'afiliado'
 * @return string|null     CPF limpo (apenas números) ou null
 */
function cpf_sender_get_user_cpf($user_id, $type = 'cliente') {
    $cpf = null;
    
    if ($type === 'afiliado') {
        // Para afiliados: buscar da tabela wp_lrp_affiliates ou do meta temporário
        $cpf = cpf_sender_get_affiliate_cpf($user_id);
    } else {
        // Para clientes: buscar dos campos de billing do WooCommerce
        $cpf = cpf_sender_get_customer_cpf($user_id);
    }
    
    return $cpf;
}

/**
 * Obter CPF de cliente (campos WooCommerce)
 * 
 * @param int $user_id ID do usuário WordPress
 * @return string|null CPF limpo (apenas números) ou null
 */
function cpf_sender_get_customer_cpf($user_id) {
    // Meta keys em ordem de prioridade
    $meta_keys = array(
        'billing_cpf',                      // Brazilian Market / Claudio Sanches (mais comum)
        'billing_document',                 // Alternativo
        '_wc_billing/address/document',     // Fluid Checkout (com pontuação)
    );
    
    foreach ($meta_keys as $key) {
        $cpf = get_user_meta($user_id, $key, true);
        if (!empty($cpf)) {
            // Remover tudo que não for número
            $cpf_clean = preg_replace('/\D/', '', $cpf);
            
            // Validar se tem 11 dígitos
            if (strlen($cpf_clean) === 11) {
                return $cpf_clean;
            }
        }
    }
    
    return null;
}

/**
 * Obter CPF de afiliado (tabela wp_lrp_affiliates)
 * 
 * @param int $user_id ID do usuário WordPress
 * @return string|null CPF limpo (apenas números) ou null
 */
function cpf_sender_get_affiliate_cpf($user_id) {
    // Primeiro: verificar se temos o CPF armazenado temporariamente (do hook)
    $cpf_temp = get_user_meta($user_id, '_cpf_sender_affiliate_cpf', true);
    if (!empty($cpf_temp)) {
        $cpf_clean = preg_replace('/\D/', '', $cpf_temp);
        if (strlen($cpf_clean) === 11) {
            return $cpf_clean;
        }
    }
    
    // Segundo: buscar diretamente da tabela wp_lrp_affiliates
    global $wpdb;
    $table_name = $wpdb->prefix . 'lrp_affiliates';
    
    // Verificar se a tabela existe
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
        return null;
    }
    
    $cpf = $wpdb->get_var($wpdb->prepare(
        "SELECT cpf FROM {$table_name} WHERE user_id = %d LIMIT 1",
        $user_id
    ));
    
    if (!empty($cpf)) {
        // Remover tudo que não for número (por segurança)
        $cpf_clean = preg_replace('/\D/', '', $cpf);
        
        // Validar se tem 11 dígitos
        if (strlen($cpf_clean) === 11) {
            return $cpf_clean;
        }
    }
    
    return null;
}

/**
 * Mascarar CPF para logs
 */
function cpf_sender_mask_cpf($cpf) {
    // Entrada: 12345678900
    // Saída: ***.456.***-00
    if (strlen($cpf) !== 11) return '***';
    
    return '***.' . substr($cpf, 3, 3) . '.***-' . substr($cpf, -2);
}

// =============================================================================
// SEÇÃO 4: ENVIO PARA API
// =============================================================================

/**
 * Função principal de envio
 * 
 * @param int    $user_id  ID do usuário WordPress
 * @param string $type     Tipo: 'cliente' ou 'afiliado'
 * @return bool            True se enviado com sucesso, false caso contrário
 */
function cpf_sender_send_to_api($user_id, $type = 'cliente') {
    // Obter configurações
    $endpoint = get_option('cpf_sender_endpoint_url');
    $method = get_option('cpf_sender_http_method', 'POST');
    $auth_type = get_option('cpf_sender_auth_type', 'api_key');
    $header_name = get_option('cpf_sender_header_name', 'X-API-Key');
    $api_key = get_option('cpf_sender_api_key');
    $basic_auth_username = get_option('cpf_sender_basic_auth_username');
    $basic_auth_password = get_option('cpf_sender_basic_auth_password');
    
    // Validar configuração
    if (empty($endpoint)) {
        cpf_sender_log_error($user_id, 'Endpoint não configurado', $type);
        return false;
    }
    
    // Validar autenticação
    if ($auth_type === 'basic_auth') {
        if (empty($basic_auth_username) || empty($basic_auth_password)) {
            cpf_sender_log_error($user_id, 'Credenciais Basic Auth não configuradas', $type);
            return false;
        }
    } elseif (empty($api_key)) {
        cpf_sender_log_error($user_id, 'API Key não configurada', $type);
        return false;
    }
    
    // Obter dados do usuário
    $user = get_userdata($user_id);
    if (!$user) {
        cpf_sender_log_error($user_id, 'Usuário não encontrado', $type);
        return false;
    }
    
    // Obter CPF baseado no tipo
    $cpf = cpf_sender_get_user_cpf($user_id, $type);
    if (!$cpf) {
        cpf_sender_log_error($user_id, "CPF não encontrado ({$type})", $type);
        return false;
    }
    
    // Montar payload
    $payload = array(
        'email' => $user->user_email,
        'cpf'   => $cpf
    );
    
    // Montar headers
    $headers = array(
        'Content-Type' => 'application/json',
    );
    
    // Adicionar autenticação
    if ($auth_type === 'basic_auth') {
        // Basic Auth: base64(username:password)
        $auth_string = base64_encode($basic_auth_username . ':' . $basic_auth_password);
        $headers['Authorization'] = 'Basic ' . $auth_string;
    } else {
        // API Key no header customizado
        if (!empty($api_key)) {
            $headers[$header_name] = $api_key;
        }
    }
    
    // Montar argumentos da requisição
    $args = array(
        'method'      => strtoupper($method),
        'headers'     => $headers,
        'body'        => json_encode($payload),
        'timeout'     => 30,
        'sslverify'   => true,
    );
    
    // Enviar requisição
    $response = wp_remote_request($endpoint, $args);
    
    // Processar resposta
    return cpf_sender_process_response($user_id, $user->user_email, $cpf, $response, $endpoint, $method, $type);
}

/**
 * Processar resposta da API
 * 
 * @param int    $user_id   ID do usuário WordPress
 * @param string $email     Email do usuário
 * @param string $cpf       CPF (apenas números)
 * @param mixed  $response  Resposta do wp_remote_request
 * @param string $endpoint  URL do endpoint
 * @param string $method    Método HTTP
 * @param string $type      Tipo: 'cliente' ou 'afiliado'
 * @return bool             True se sucesso, false caso contrário
 */
function cpf_sender_process_response($user_id, $email, $cpf, $response, $endpoint, $method, $type = 'cliente') {
    $cpf_masked = cpf_sender_mask_cpf($cpf);
    
    // Definir meta keys baseado no tipo
    $status_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_status' : '_cpf_sender_status';
    $attempts_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_attempts' : '_cpf_sender_attempts';
    $sent_at_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_sent_at' : '_cpf_sender_sent_at';
    $error_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_last_error' : '_cpf_sender_last_error';
    $pending_since_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_pending_since' : '_cpf_sender_pending_since';
    
    $attempts = absint(get_user_meta($user_id, $attempts_key, true));
    
    // Erro de conexão
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        
        cpf_sender_save_log(array(
            'user_id'          => $user_id,
            'user_email'       => $email,
            'cpf_masked'       => $cpf_masked,
            'endpoint_url'     => $endpoint,
            'http_method'      => $method,
            'http_status_code' => null,
            'response_body'    => null,
            'error_message'    => "[{$type}] " . $error_message,
            'status'           => 'error',
            'attempts'         => $attempts,
            'type'             => $type
        ));
        
        update_user_meta($user_id, $status_key, 'error');
        update_user_meta($user_id, $error_key, $error_message);
        
        // Enviar alerta apenas se atingiu máximo de tentativas
        if ($attempts >= CPF_SENDER_MAX_ATTEMPTS) {
            cpf_sender_send_alert($user_id, $email, "[{$type}] " . $error_message);
        }
        
        return false;
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    // "Sucesso" (2xx) do Hookdeck — é só o ack de recebimento na fila dele, NÃO confirma
    // que a nossa API gravou o document. A entrega ao destino é assíncrona. Por isso não
    // marcamos sucesso aqui: agendamos uma verificação real contra a API de status.
    if ($http_code >= 200 && $http_code < 300) {
        cpf_sender_save_log(array(
            'user_id'          => $user_id,
            'user_email'       => $email,
            'cpf_masked'       => $cpf_masked,
            'endpoint_url'     => $endpoint,
            'http_method'      => $method,
            'http_status_code' => $http_code,
            'response_body'    => $body,
            'error_message'    => "[{$type}] Hookdeck aceitou (ack) — aguardando confirmação da escrita",
            'status'           => 'pending',
            'attempts'         => $attempts,
            'type'             => $type
        ));

        cpf_sender_schedule_write_verification($user_id, $email, $type, 1);

        return true;
    }
    
    // Erro HTTP
    $error_message = "HTTP {$http_code}: {$body}";
    
    cpf_sender_save_log(array(
        'user_id'          => $user_id,
        'user_email'       => $email,
        'cpf_masked'       => $cpf_masked,
        'endpoint_url'     => $endpoint,
        'http_method'      => $method,
        'http_status_code' => $http_code,
        'response_body'    => $body,
        'error_message'    => "[{$type}] " . $error_message,
        'status'           => 'error',
        'attempts'         => $attempts,
        'type'             => $type
    ));
    
    update_user_meta($user_id, $status_key, 'error');
    update_user_meta($user_id, $error_key, $error_message);
    
    // Enviar alerta apenas se atingiu máximo de tentativas
    if ($attempts >= CPF_SENDER_MAX_ATTEMPTS) {
        cpf_sender_send_alert($user_id, $email, "[{$type}] " . $error_message);
    }

    return false;
}

/**
 * Verificação da escrita real do document — o Hookdeck só confirma recebimento
 * (ack assíncrono), então depois de um envio "aceito" agendamos esta checagem
 * contra a nossa própria API antes de marcar como sucesso de verdade.
 */
const CPF_SENDER_VERIFY_DELAY_SECONDS = 15;
const CPF_SENDER_VERIFY_MAX_ATTEMPTS = 3;

function cpf_sender_schedule_write_verification($user_id, $email, $type, $attempt) {
    wp_schedule_single_event(
        time() + CPF_SENDER_VERIFY_DELAY_SECONDS,
        'cpf_sender_verify_write',
        array($user_id, $email, $type, $attempt)
    );
}

/**
 * Consulta a API própria para saber se o document já foi gravado.
 *
 * @return bool|null true = gravado, false = não gravado, null = não foi possível checar
 *                    (API não configurada ou erro de rede — não deve penalizar o envio)
 */
function cpf_sender_check_document_written($email) {
    $api_url = get_option('cpf_sender_verify_api_url', '');
    $api_key = get_option('cpf_sender_verify_api_key', '');

    if (empty($api_url) || empty($api_key)) {
        return null;
    }

    $response = wp_remote_get(
        add_query_arg('email', rawurlencode($email), $api_url),
        array(
            'headers'   => array('X-Cpf-Status-Key' => $api_key),
            'timeout'   => 15,
            'sslverify' => true,
        )
    );

    if (is_wp_error($response)) {
        error_log('[CPF Sender Verify] Erro ao consultar API de status: ' . $response->get_error_message());
        return null;
    }

    if (wp_remote_retrieve_response_code($response) !== 200) {
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data)) {
        return null;
    }

    return !empty($data['document_filled']);
}

/**
 * Handler da verificação agendada.
 */
add_action('cpf_sender_verify_write', 'cpf_sender_execute_verify_write', 10, 4);

function cpf_sender_execute_verify_write($user_id, $email, $type = 'cliente', $attempt = 1) {
    $status_key        = ($type === 'afiliado') ? '_cpf_sender_affiliate_status' : '_cpf_sender_status';
    $sent_at_key        = ($type === 'afiliado') ? '_cpf_sender_affiliate_sent_at' : '_cpf_sender_sent_at';
    $error_key          = ($type === 'afiliado') ? '_cpf_sender_affiliate_last_error' : '_cpf_sender_last_error';
    $pending_since_key  = ($type === 'afiliado') ? '_cpf_sender_affiliate_pending_since' : '_cpf_sender_pending_since';
    $attempts_key       = ($type === 'afiliado') ? '_cpf_sender_affiliate_attempts' : '_cpf_sender_attempts';

    $confirmed = cpf_sender_check_document_written($email);

    // Não foi possível checar (API de verificação não configurada ou fora do ar):
    // não penaliza o envio — mantém como estava e deixa o ciclo normal de backoff cuidar.
    if ($confirmed === null) {
        return;
    }

    if ($confirmed === true) {
        update_user_meta($user_id, $status_key, 'success');
        update_user_meta($user_id, $sent_at_key, current_time('mysql'));
        delete_user_meta($user_id, $error_key);
        delete_user_meta($user_id, $pending_since_key);
        delete_user_meta($user_id, $attempts_key);

        if ($type === 'afiliado') {
            delete_user_meta($user_id, '_cpf_sender_affiliate_cpf');
        }

        cpf_sender_save_log(array(
            'user_id'          => $user_id,
            'user_email'       => $email,
            'cpf_masked'       => '***',
            'endpoint_url'     => 'Verificação (API de status)',
            'http_method'      => 'GET',
            'http_status_code' => 200,
            'response_body'    => null,
            'error_message'    => "[{$type}] Escrita do document confirmada",
            'status'           => 'success',
            'attempts'         => 0,
            'type'             => $type,
        ));
        return;
    }

    // $confirmed === false: Hookdeck recebeu, mas a escrita ainda não apareceu no banco.
    // A entrega assíncrona pode só estar demorando — tenta checar de novo algumas vezes
    // antes de tratar como falha de verdade.
    if ($attempt < CPF_SENDER_VERIFY_MAX_ATTEMPTS) {
        cpf_sender_schedule_write_verification($user_id, $email, $type, $attempt + 1);
        return;
    }

    update_user_meta($user_id, $status_key, 'error');
    update_user_meta($user_id, $error_key, 'Hookdeck aceitou o envio, mas a escrita do document não foi confirmada');

    cpf_sender_save_log(array(
        'user_id'          => $user_id,
        'user_email'       => $email,
        'cpf_masked'       => '***',
        'endpoint_url'     => 'Verificação (API de status)',
        'http_method'      => 'GET',
        'http_status_code' => null,
        'response_body'    => null,
        'error_message'    => "[{$type}] Hookdeck aceitou mas escrita não confirmada após " . CPF_SENDER_VERIFY_MAX_ATTEMPTS . " checagens",
        'status'           => 'error',
        'attempts'         => absint(get_user_meta($user_id, $attempts_key, true)),
        'type'             => $type,
    ));
}

// =============================================================================
// SEÇÃO 5: HOOKS - EDWISER BRIDGE / AFILIADOS / WOOCOMMERCE
// =============================================================================

/**
 * Hook principal - dispara após criação de usuário no Edwiser Bridge
 * Prioridade 100 = executa depois dos outros hooks
 */
add_action('eb_created_user', 'cpf_sender_after_user_created', 100, 2);

function cpf_sender_after_user_created($user_id, $user_data = array()) {
    // Verificar se já foi enviado
    $status = get_user_meta($user_id, '_cpf_sender_status', true);
    if ($status === 'success') {
        return; // Já enviado com sucesso, não reenviar
    }
    
    // Agendar envio do CPF (cliente)
    cpf_sender_schedule_send($user_id, 'cliente', 'Criação de usuário Edwiser Bridge');
}

/**
 * Hook para afiliados - dispara após criação de afiliado no Lab Resumos Parceiros
 */
add_action('lrp_affiliate_created', 'cpf_sender_after_affiliate_created', 100, 1);

function cpf_sender_after_affiliate_created($affiliate) {
    // O $affiliate é uma instância de LRP_Affiliate
    $user_id = $affiliate->get_user_id();
    
    if (!$user_id) {
        return;
    }
    
    // Verificar se já foi enviado (como afiliado)
    $status = get_user_meta($user_id, '_cpf_sender_affiliate_status', true);
    if ($status === 'success') {
        return; // Já enviado com sucesso, não reenviar
    }
    
    // Obter CPF diretamente do objeto afiliado
    $cpf = $affiliate->get_cpf();
    
    if (empty($cpf)) {
        cpf_sender_log_error($user_id, 'CPF do afiliado não encontrado');
        return;
    }
    
    // Armazenar CPF temporariamente para uso no envio
    update_user_meta($user_id, '_cpf_sender_affiliate_cpf', $cpf);
    
    // Agendar envio do CPF (afiliado)
    cpf_sender_schedule_send($user_id, 'afiliado', 'Criação de afiliado');
}

/**
 * Função centralizada para agendar envio
 * 
 * @param int    $user_id   ID do usuário WordPress
 * @param string $type      Tipo: 'cliente' ou 'afiliado'
 * @param string $reason    Motivo do agendamento (para logs)
 */
function cpf_sender_schedule_send($user_id, $type = 'cliente', $reason = 'Agendado para envio') {
    // Obter delay configurado
    $delay = absint(get_option('cpf_sender_delay_seconds', 30));
    
    // Definir meta key de status baseado no tipo
    $status_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_status' : '_cpf_sender_status';
    
    if ($delay > 0) {
        // Agendar envio com delay
        wp_schedule_single_event(
            time() + $delay,
            'cpf_sender_scheduled_send',
            array($user_id, $type)
        );
        
        // Marcar como pendente com timestamp e contador
        cpf_sender_set_pending_status($user_id, $reason . " ({$type})", $type);
    } else {
        // Enviar imediatamente
        cpf_sender_send_to_api($user_id, $type);
    }
    
    // Agendar verificação do Telegram (alerta se não tiver sucesso após X minutos)
    cpf_sender_schedule_telegram_check($user_id, $type);
}

/**
 * Definir status como pendente com metadados
 * 
 * @param int    $user_id  ID do usuário WordPress
 * @param string $reason   Motivo (para logs)
 * @param string $type     Tipo: 'cliente' ou 'afiliado'
 */
function cpf_sender_set_pending_status($user_id, $reason = 'Aguardando envio', $type = 'cliente') {
    // Definir meta keys baseado no tipo
    $status_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_status' : '_cpf_sender_status';
    $attempts_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_attempts' : '_cpf_sender_attempts';
    $pending_since_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_pending_since' : '_cpf_sender_pending_since';
    
    $current_attempts = absint(get_user_meta($user_id, $attempts_key, true));
    
    update_user_meta($user_id, $status_key, 'pending');
    update_user_meta($user_id, $pending_since_key, time());
    update_user_meta($user_id, '_cpf_sender_type', $type); // Armazenar tipo para referência
    
    // Só incrementa tentativas se já houve alguma
    if ($current_attempts > 0) {
        update_user_meta($user_id, $attempts_key, $current_attempts + 1);
    } else {
        update_user_meta($user_id, $attempts_key, 1);
    }
    
    // Registrar log de pending
    $user = get_userdata($user_id);
    $email = $user ? $user->user_email : 'N/A';
    $cpf = cpf_sender_get_user_cpf($user_id, $type);
    $cpf_masked = $cpf ? cpf_sender_mask_cpf($cpf) : '***';
    
    cpf_sender_save_log(array(
        'user_id'          => $user_id,
        'user_email'       => $email,
        'cpf_masked'       => $cpf_masked,
        'endpoint_url'     => get_option('cpf_sender_endpoint_url', 'N/A'),
        'http_method'      => get_option('cpf_sender_http_method', 'POST'),
        'http_status_code' => null,
        'response_body'    => null,
        'error_message'    => $reason,
        'status'           => 'pending',
        'attempts'         => $current_attempts + 1,
        'type'             => $type
    ));
}

/**
 * Executar envio agendado
 */
add_action('cpf_sender_scheduled_send', 'cpf_sender_execute_scheduled', 10, 2);

function cpf_sender_execute_scheduled($user_id, $type = 'cliente') {
    cpf_sender_send_to_api($user_id, $type);
}

/**
 * Fallback caso Edwiser Bridge não dispare o hook
 * Verifica se o plugin está ativo antes de usar
 */
add_action('woocommerce_order_status_completed', 'cpf_sender_woo_fallback', 100, 1);

function cpf_sender_woo_fallback($order_id) {
    // Se Edwiser Bridge está ativo, não usar fallback
    // O hook eb_created_user já vai disparar
    if (class_exists('Eb_Course') || function_exists('edwiser_bridge_instance')) {
        return;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $user_id = $order->get_user_id();
    if (!$user_id) return;
    
    // Verificar se já foi enviado
    $status = get_user_meta($user_id, '_cpf_sender_status', true);
    if ($status === 'success') return;
    
    // Agendar envio do CPF (cliente via WooCommerce fallback)
    cpf_sender_schedule_send($user_id, 'cliente', 'Agendado via WooCommerce fallback');
}

// =============================================================================
// SEÇÃO 6: INTERFACE ADMIN - LISTA DE USUÁRIOS
// =============================================================================

/**
 * Adicionar coluna na lista de usuários
 */
add_filter('manage_users_columns', 'cpf_sender_add_user_column');

function cpf_sender_add_user_column($columns) {
    $columns['cpf_sender'] = 'CPF API';
    return $columns;
}

/**
 * Conteúdo da coluna
 */
add_filter('manage_users_custom_column', 'cpf_sender_user_column_content', 10, 3);

function cpf_sender_user_column_content($value, $column_name, $user_id) {
    if ($column_name !== 'cpf_sender') return $value;
    
    $cpf = cpf_sender_get_user_cpf($user_id);
    $status = get_user_meta($user_id, '_cpf_sender_status', true);
    $sent_at = get_user_meta($user_id, '_cpf_sender_sent_at', true);
    $pending_since = get_user_meta($user_id, '_cpf_sender_pending_since', true);
    $attempts = absint(get_user_meta($user_id, '_cpf_sender_attempts', true));
    
    // Sem CPF cadastrado
    if (!$cpf) {
        return '<span style="color:#999;">—</span>';
    }
    
    // Gerar URL para ação manual
    $nonce = wp_create_nonce('cpf_sender_manual_' . $user_id);
    $send_url = admin_url('admin-post.php?action=cpf_sender_manual&user_id=' . $user_id . '&_wpnonce=' . $nonce);
    
    switch ($status) {
        case 'success':
            $date = $sent_at ? date('d/m H:i', strtotime($sent_at)) : '';
            return sprintf(
                '<span style="color:#46b450;">✓ Enviado</span><br>
                <small style="color:#666;">%s</small><br>
                <a href="%s" class="cpf-sender-resend">Reenviar</a>',
                $date,
                esc_url($send_url)
            );
            
        case 'error':
            $error = get_user_meta($user_id, '_cpf_sender_last_error', true);
            $attempts_text = $attempts > 0 ? sprintf(' (Tentativa %d/%d)', $attempts, CPF_SENDER_MAX_ATTEMPTS) : '';
            return sprintf(
                '<span style="color:#dc3232;" title="%s">✗ Erro%s</span><br>
                <a href="%s" class="cpf-sender-retry">Tentar novamente</a>',
                esc_attr($error),
                $attempts_text,
                esc_url($send_url)
            );
            
        case 'pending':
            $elapsed = '';
            $is_delayed = false;
            $attempts_text = '';
            $next_retry_info = '';
            
            if ($pending_since) {
                $seconds_elapsed = time() - intval($pending_since);
                $elapsed = cpf_sender_format_elapsed_time($seconds_elapsed);
                
                // Verificar se está atrasado baseado no backoff exponencial
                $current_backoff = cpf_sender_get_backoff_interval($attempts);
                $is_delayed = $seconds_elapsed > $current_backoff;
                
                // Calcular tempo até próximo retry
                if (!$is_delayed) {
                    $time_remaining = $current_backoff - $seconds_elapsed;
                    $next_retry_info = sprintf('<br><small style="color:#0073aa;">Próximo retry em %s</small>', 
                                               cpf_sender_format_backoff($time_remaining));
                }
            }
            
            if ($attempts > 0) {
                $attempts_text = sprintf('<br><small>Tentativa %d/%d</small>', $attempts, CPF_SENDER_MAX_ATTEMPTS);
            }
            
            $status_style = $is_delayed ? 'color:#dc3232;' : 'color:#f0b849;';
            $status_label = $is_delayed ? '🔄 Reenviando...' : '⏳ Pendente';
            
            return sprintf(
                '<span style="%s">%s</span><br>
                <small style="color:#666;">%s</small>%s%s<br>
                <a href="%s" class="cpf-sender-retry">Reenviar agora</a>',
                $status_style,
                $status_label,
                $elapsed ? "({$elapsed})" : '',
                $attempts_text,
                $next_retry_info,
                esc_url($send_url)
            );
            
        default: // not_sent ou vazio
            return sprintf(
                '<a href="%s" class="button button-small">Enviar</a>',
                esc_url($send_url)
            );
    }
}

/**
 * Formatar tempo decorrido
 */
function cpf_sender_format_elapsed_time($seconds) {
    if ($seconds < 60) {
        return sprintf('%ds', $seconds);
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return sprintf('%dm %ds', $minutes, $secs);
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return sprintf('%dh %dm', $hours, $minutes);
    }
}

/**
 * Ação manual de envio
 */
add_action('admin_post_cpf_sender_manual', 'cpf_sender_handle_manual');

function cpf_sender_handle_manual() {
    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado');
    }
    
    $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
    
    if (!$user_id) {
        wp_die('ID de usuário inválido');
    }
    
    check_admin_referer('cpf_sender_manual_' . $user_id);
    
    // Resetar tentativas para envio manual (novo ciclo)
    delete_user_meta($user_id, '_cpf_sender_attempts');
    delete_user_meta($user_id, '_cpf_sender_pending_since');
    delete_user_meta($user_id, '_cpf_sender_last_error');
    update_user_meta($user_id, '_cpf_sender_attempts', 1);
    
    $result = cpf_sender_send_to_api($user_id);
    
    $redirect = admin_url('users.php');
    
    if ($result) {
        $redirect = add_query_arg('cpf_sender_success', '1', $redirect);
    } else {
        $redirect = add_query_arg('cpf_sender_error', '1', $redirect);
    }
    
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Bulk actions
 */
add_filter('bulk_actions-users', 'cpf_sender_bulk_actions');

function cpf_sender_bulk_actions($actions) {
    $actions['cpf_sender_bulk'] = 'Enviar CPF para API';
    return $actions;
}

/**
 * Processar ação em lote
 */
add_filter('handle_bulk_actions-users', 'cpf_sender_handle_bulk', 10, 3);

function cpf_sender_handle_bulk($redirect_to, $action, $user_ids) {
    if ($action !== 'cpf_sender_bulk') return $redirect_to;
    
    $success = 0;
    $errors = 0;
    $no_cpf = 0;
    
    foreach ($user_ids as $user_id) {
        $cpf = cpf_sender_get_user_cpf($user_id);
        
        if (!$cpf) {
            $no_cpf++;
            continue;
        }
        
        $result = cpf_sender_send_to_api($user_id);
        
        if ($result === true) {
            $success++;
        } else {
            $errors++;
        }
    }
    
    return add_query_arg(array(
        'cpf_sender_bulk_success' => $success,
        'cpf_sender_bulk_errors' => $errors,
        'cpf_sender_bulk_no_cpf' => $no_cpf,
    ), $redirect_to);
}

/**
 * Admin notices para bulk actions
 */
add_action('admin_notices', 'cpf_sender_bulk_admin_notices');

function cpf_sender_bulk_admin_notices() {
    if (!isset($_GET['cpf_sender_bulk_success'])) return;
    
    $success = intval($_GET['cpf_sender_bulk_success']);
    $errors = intval($_GET['cpf_sender_bulk_errors']);
    $no_cpf = intval($_GET['cpf_sender_bulk_no_cpf']);
    
    if ($success > 0) {
        printf(
            '<div class="notice notice-success is-dismissible"><p>CPF Sender: %d enviado(s) com sucesso.</p></div>',
            $success
        );
    }
    
    if ($errors > 0) {
        printf(
            '<div class="notice notice-error is-dismissible"><p>CPF Sender: %d erro(s) no envio. Verifique os logs.</p></div>',
            $errors
        );
    }
    
    if ($no_cpf > 0) {
        printf(
            '<div class="notice notice-warning is-dismissible"><p>CPF Sender: %d usuário(s) sem CPF cadastrado.</p></div>',
            $no_cpf
        );
    }
    
    // Notices para envio manual
    if (isset($_GET['cpf_sender_success'])) {
        echo '<div class="notice notice-success is-dismissible"><p>CPF Sender: Enviado com sucesso.</p></div>';
    }
    
    if (isset($_GET['cpf_sender_error'])) {
        echo '<div class="notice notice-error is-dismissible"><p>CPF Sender: Erro no envio. Verifique os logs.</p></div>';
    }
}

// =============================================================================
// SEÇÃO 7: SISTEMA DE LOGS
// =============================================================================

/**
 * Salvar log no banco de dados
 */
function cpf_sender_save_log($data) {
    if (get_option('cpf_sender_enable_logs') !== '1') {
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'cpf_sender_logs';
    
    $wpdb->insert(
        $table,
        array(
            'user_id'          => $data['user_id'],
            'user_email'       => $data['user_email'],
            'cpf_masked'       => $data['cpf_masked'],
            'endpoint_url'     => $data['endpoint_url'],
            'http_method'      => $data['http_method'],
            'http_status_code' => $data['http_status_code'],
            'response_body'    => $data['response_body'],
            'error_message'    => $data['error_message'],
            'status'           => $data['status'],
            'attempts'         => isset($data['attempts']) ? $data['attempts'] : 1,
            'type'             => isset($data['type']) ? $data['type'] : 'cliente',
        ),
        array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s')
    );
}

/**
 * Log de erro simples
 * 
 * @param int    $user_id  ID do usuário WordPress
 * @param string $message  Mensagem de erro
 * @param string $type     Tipo: 'cliente' ou 'afiliado'
 */
function cpf_sender_log_error($user_id, $message, $type = 'cliente') {
    $user = get_userdata($user_id);
    $email = $user ? $user->user_email : 'N/A';
    
    cpf_sender_save_log(array(
        'user_id'          => $user_id,
        'user_email'       => $email,
        'cpf_masked'       => '***',
        'endpoint_url'     => get_option('cpf_sender_endpoint_url', 'N/A'),
        'http_method'      => get_option('cpf_sender_http_method', 'POST'),
        'http_status_code' => null,
        'response_body'    => null,
        'error_message'    => "[{$type}] " . $message,
        'status'           => 'error',
        'type'             => $type
    ));
}

// =============================================================================
// SEÇÃO 8: NOTIFICAÇÕES TELEGRAM
// =============================================================================

/**
 * Enviar notificação via Telegram (webhook n8n/automation)
 * 
 * @param string $evento    Título curto do alerta
 * @param string $descricao Texto complementar com detalhes
 * @return bool             True se enviado com sucesso, false caso contrário
 */
function cpf_sender_send_telegram($evento, $descricao = '') {
    // Verificar se Telegram está habilitado
    if (get_option('cpf_sender_telegram_enabled') !== '1') {
        return false;
    }
    
    $webhook_url = get_option('cpf_sender_telegram_webhook_url');
    if (empty($webhook_url)) {
        return false;
    }
    
    $payload = array(
        'evento'    => $evento,
        'descricao' => $descricao,
    );
    
    $args = array(
        'method'    => 'POST',
        'headers'   => array('Content-Type' => 'application/json'),
        'body'      => json_encode($payload),
        'timeout'   => 15,
        'sslverify' => true,
    );
    
    $response = wp_remote_request($webhook_url, $args);
    
    if (is_wp_error($response)) {
        error_log('[CPF Sender Telegram] Erro ao enviar: ' . $response->get_error_message());
        return false;
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    return ($http_code >= 200 && $http_code < 300);
}

/**
 * Agendar verificação do Telegram após venda/criação de usuário
 * Será chamado junto com o agendamento normal de envio de CPF.
 * 
 * @param int    $user_id  ID do usuário WordPress
 * @param string $type     Tipo: 'cliente' ou 'afiliado'
 */
function cpf_sender_schedule_telegram_check($user_id, $type = 'cliente') {
    // Verificar se Telegram está habilitado
    if (get_option('cpf_sender_telegram_enabled') !== '1') {
        return;
    }
    
    $delay_minutes = absint(get_option('cpf_sender_telegram_delay_minutes', 5));
    if ($delay_minutes < 1) $delay_minutes = 5;
    
    $delay_seconds = $delay_minutes * 60;
    
    // Agendar verificação para daqui a X minutos
    wp_schedule_single_event(
        time() + $delay_seconds,
        'cpf_sender_telegram_check',
        array($user_id, $type, time())
    );
}

/**
 * Hook para executar a verificação do Telegram
 */
add_action('cpf_sender_telegram_check', 'cpf_sender_execute_telegram_check', 10, 3);

/**
 * Executar verificação: se CPF ainda não foi enviado com sucesso, alertar via Telegram
 * 
 * @param int    $user_id       ID do usuário WordPress
 * @param string $type          Tipo: 'cliente' ou 'afiliado'
 * @param int    $scheduled_at  Timestamp de quando foi agendado (para referência)
 */
function cpf_sender_execute_telegram_check($user_id, $type = 'cliente', $scheduled_at = 0) {
    // Definir meta key de status baseado no tipo
    $status_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_status' : '_cpf_sender_status';
    $attempts_key = ($type === 'afiliado') ? '_cpf_sender_affiliate_attempts' : '_cpf_sender_attempts';
    
    $status = get_user_meta($user_id, $status_key, true);
    
    // Se já deu sucesso, não precisa alertar
    if ($status === 'success') {
        return;
    }
    
    // Obter dados do usuário para a mensagem
    $user = get_userdata($user_id);
    $email = $user ? $user->user_email : 'ID #' . $user_id;
    $attempts = absint(get_user_meta($user_id, $attempts_key, true));
    $delay_minutes = absint(get_option('cpf_sender_telegram_delay_minutes', 5));
    
    // Tipo formatado
    $type_label = ($type === 'afiliado') ? 'Afiliado' : 'Cliente';
    
    // Montar evento e descrição
    $evento = "CPF Sender - Falha no envio ({$type_label})";
    
    $descricao_parts = array();
    $descricao_parts[] = "Email: {$email}";
    $descricao_parts[] = "Tipo: {$type_label}";
    $descricao_parts[] = "Status atual: " . ($status ?: 'sem status');
    $descricao_parts[] = "Tentativas: {$attempts}/" . CPF_SENDER_MAX_ATTEMPTS;
    $descricao_parts[] = "O CPF nao foi enviado com sucesso apos {$delay_minutes} minutos.";
    $descricao_parts[] = "Data/Hora: " . current_time('d/m/Y H:i:s');
    
    $descricao = implode("\n", $descricao_parts);
    
    // Enviar alerta no Telegram
    $sent = cpf_sender_send_telegram($evento, $descricao);
    
    // Registrar no log
    if ($sent) {
        cpf_sender_save_log(array(
            'user_id'          => $user_id,
            'user_email'       => $email,
            'cpf_masked'       => '***',
            'endpoint_url'     => 'Telegram Webhook',
            'http_method'      => 'POST',
            'http_status_code' => 200,
            'response_body'    => null,
            'error_message'    => "[Telegram] Alerta enviado - {$type_label} sem sucesso apos {$delay_minutes}min",
            'status'           => 'pending',
            'attempts'         => $attempts,
            'type'             => $type
        ));
    }
}

/**
 * AJAX para teste de conectividade com Telegram
 */
add_action('wp_ajax_cpf_sender_test_telegram', 'cpf_sender_test_telegram');

function cpf_sender_test_telegram() {
    check_ajax_referer('cpf_sender_test', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permissao negada');
    }
    
    $webhook_url = get_option('cpf_sender_telegram_webhook_url');
    if (empty($webhook_url)) {
        wp_send_json_error('URL do webhook Telegram nao configurada');
    }
    
    $payload = array(
        'evento'    => 'Teste CPF Sender',
        'descricao' => 'Este e um teste de conectividade do plugin CPF Sender API. Se voce recebeu esta mensagem, o Telegram esta funcionando! - ' . current_time('d/m/Y H:i:s'),
    );
    
    $args = array(
        'method'    => 'POST',
        'headers'   => array('Content-Type' => 'application/json'),
        'body'      => json_encode($payload),
        'timeout'   => 15,
        'sslverify' => true,
    );
    
    $response = wp_remote_request($webhook_url, $args);
    
    if (is_wp_error($response)) {
        wp_send_json_error('Erro de conexao: ' . $response->get_error_message());
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($http_code >= 200 && $http_code < 300) {
        wp_send_json_success(array(
            'status_code' => $http_code,
            'body'        => $body,
            'message'     => 'Mensagem de teste enviada com sucesso! Verifique seu Telegram.'
        ));
    } else {
        wp_send_json_error('Erro HTTP ' . $http_code . ': ' . $body);
    }
}

// =============================================================================
// SEÇÃO 9: ALERTAS E NOTIFICAÇÕES (EMAIL)
// =============================================================================

/**
 * Alerta por email
 */
function cpf_sender_send_alert($user_id, $user_email, $error_message) {
    // Verificar se alertas estão habilitados
    if (get_option('cpf_sender_alert_on_error') !== '1') {
        return;
    }
    
    // Email do destinatário
    $admin_email = get_option('cpf_sender_admin_email');
    if (empty($admin_email)) {
        $admin_email = get_option('admin_email');
    }
    
    // Assunto
    $subject = '[CPF Sender] Falha no envio - ' . $user_email;
    
    // Corpo do email
    $message = sprintf(
        "Houve uma falha no envio de CPF para a API.\n\n" .
        "Detalhes:\n" .
        "- Usuário ID: %d\n" .
        "- Email: %s\n" .
        "- Data/Hora: %s\n" .
        "- Erro: %s\n\n" .
        "Acesse a página de configurações para mais detalhes:\n%s",
        $user_id,
        $user_email,
        current_time('d/m/Y H:i:s'),
        $error_message,
        admin_url('options-general.php?page=cpf-sender-settings')
    );
    
    // Headers
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    // Enviar
    wp_mail($admin_email, $subject, $message, $headers);
}

/**
 * Admin notice para erros recentes e pendências antigas
 */
add_action('admin_notices', 'cpf_sender_error_notice');

function cpf_sender_error_notice() {
    // Só mostrar na dashboard e na página de configurações
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->id, array('dashboard', 'settings_page_cpf-sender-settings', 'users'))) {
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'cpf_sender_logs';
    
    // Verificar se tem erros nas últimas 24h
    $error_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} 
         WHERE status = 'error' 
         AND created_at > %s",
        date('Y-m-d H:i:s', strtotime('-24 hours'))
    ));
    
    if ($error_count > 0) {
        printf(
            '<div class="notice notice-error"><p>' .
            '<strong>CPF Sender:</strong> %d erro(s) nas últimas 24 horas. ' .
            '<a href="%s">Ver logs</a></p></div>',
            $error_count,
            admin_url('options-general.php?page=cpf-sender-settings#logs')
        );
    }
    
    // Contar usuários com envio pendente (serão processados automaticamente)
    $pending_count = $wpdb->get_var(
        "SELECT COUNT(DISTINCT user_id) 
         FROM {$wpdb->usermeta} 
         WHERE meta_key = '_cpf_sender_status' 
         AND meta_value = 'pending'"
    );
    
    if ($pending_count > 0) {
        printf(
            '<div class="notice notice-info"><p>' .
            '<strong>CPF Sender:</strong> %d usuário(s) com envio pendente. ' .
            'O sistema tentará reenviar automaticamente (até %d tentativas com backoff exponencial). ' .
            '<a href="%s">Ver usuários</a></p></div>',
            $pending_count,
            CPF_SENDER_MAX_ATTEMPTS,
            admin_url('users.php')
        );
    }
}

/**
 * AJAX para teste de conexão
 */
add_action('wp_ajax_cpf_sender_test_connection', 'cpf_sender_test_connection');

function cpf_sender_test_connection() {
    check_ajax_referer('cpf_sender_test', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permissão negada');
    }
    
    $endpoint = get_option('cpf_sender_endpoint_url');
    $method = get_option('cpf_sender_http_method', 'POST');
    $auth_type = get_option('cpf_sender_auth_type', 'api_key');
    $header_name = get_option('cpf_sender_header_name', 'X-API-Key');
    $api_key = get_option('cpf_sender_api_key');
    $basic_auth_username = get_option('cpf_sender_basic_auth_username');
    $basic_auth_password = get_option('cpf_sender_basic_auth_password');
    
    if (empty($endpoint)) {
        wp_send_json_error('Endpoint não configurado');
    }
    
    // Payload de teste
    $payload = array(
        'email' => 'teste@teste.com',
        'cpf'   => '00000000000'
    );
    
    $headers = array(
        'Content-Type' => 'application/json',
    );
    
    // Adicionar autenticação
    if ($auth_type === 'basic_auth') {
        if (empty($basic_auth_username) || empty($basic_auth_password)) {
            wp_send_json_error('Credenciais Basic Auth não configuradas');
        }
        $auth_string = base64_encode($basic_auth_username . ':' . $basic_auth_password);
        $headers['Authorization'] = 'Basic ' . $auth_string;
    } else {
        if (!empty($api_key)) {
            $headers[$header_name] = $api_key;
        }
    }
    
    $args = array(
        'method'    => strtoupper($method),
        'headers'   => $headers,
        'body'      => json_encode($payload),
        'timeout'   => 15,
        'sslverify' => true,
    );
    
    $response = wp_remote_request($endpoint, $args);
    
    if (is_wp_error($response)) {
        wp_send_json_error('Erro de conexão: ' . $response->get_error_message());
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    wp_send_json_success(array(
        'status_code' => $http_code,
        'body'        => $body,
        'success'     => ($http_code >= 200 && $http_code < 300)
    ));
}

