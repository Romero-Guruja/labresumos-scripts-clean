<?php
/**
 * WPCode snippet #1241 — Lab Resumos - AutoLogin DEBUG VERSION
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */

/**
 * Lab Resumos - AutoLogin v7
 * 
 * Novidades:
 * - Sugestão de pedidos pendentes/falhos
 * - Mensagem WhatsApp editável
 * - Quick actions para pedidos sugeridos
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// INTERCEPTAR NO INIT - PRIORIDADE 1
// ============================================================================
add_action('init', function() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    
    if (strpos($request_uri, '/autologin') === false || !isset($_GET['token'])) {
        return;
    }
    
    $token = sanitize_text_field($_GET['token']);
    
    if (empty($token) || strlen($token) !== 32) {
        wp_die('Link inválido.', 'Erro', array('response' => 400));
    }
    
    $token_data = get_option('lr_autologin_' . $token);
    
    if (!$token_data) {
        wp_die('Link não encontrado ou expirado.', 'Erro', array('response' => 404));
    }
    
    if (time() > $token_data['expiry']) {
        delete_option('lr_autologin_' . $token);
        wp_die('Link expirado.', 'Erro', array('response' => 410));
    }
    
    $max_uses = isset($token_data['max_uses']) ? $token_data['max_uses'] : 1;
    $use_count = isset($token_data['use_count']) ? $token_data['use_count'] : 0;
    
    if ($max_uses > 0 && $use_count >= $max_uses) {
        wp_die('Link já atingiu o limite de usos.', 'Erro', array('response' => 410));
    }
    
    $user = get_user_by('ID', $token_data['user_id']);
    if (!$user) {
        wp_die('Usuário não encontrado.', 'Erro', array('response' => 404));
    }
    
    // Atualizar uso
    $token_data['use_count'] = $use_count + 1;
    $token_data['last_used'] = current_time('mysql');
    $token_data['last_ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    update_option('lr_autologin_' . $token, $token_data, false);
    
    $checkout_url = wc_get_checkout_url();
    
    wp_clear_auth_cookie();
    wp_set_current_user($token_data['user_id']);
    wp_set_auth_cookie($token_data['user_id'], true);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (headers_sent()) {
        echo '<meta http-equiv="refresh" content="0;url=' . esc_url($checkout_url) . '">';
        exit;
    }
    
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Location: ' . $checkout_url, true, 302);
    exit;
    
}, 1);

// ============================================================================
// FUNÇÕES AUXILIARES
// ============================================================================
function lr_generate_autologin_token($user_id, $order_id = null, $options = array()) {
    $token = bin2hex(random_bytes(16));
    
    $defaults = array(
        'max_uses' => 0,
        'expiry_hours' => 72
    );
    $options = wp_parse_args($options, $defaults);
    
    $token_data = array(
        'user_id'    => absint($user_id),
        'order_id'   => absint($order_id),
        'expiry'     => time() + ($options['expiry_hours'] * 60 * 60),
        'max_uses'   => absint($options['max_uses']),
        'use_count'  => 0,
        'created_at' => current_time('mysql'),
        'ip_created' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
    );
    
    update_option('lr_autologin_' . $token, $token_data, false);
    
    return $token;
}

function lr_get_autologin_url($user_id, $order_id = null, $options = array()) {
    $token = lr_generate_autologin_token($user_id, $order_id, $options);
    $url = home_url('/autologin/') . '?token=' . $token;
    if ($order_id) {
        $url .= '&order=' . $order_id;
    }
    return $url;
}

function lr_get_payment_link_for_order($order_id, $options = array()) {
    $order = wc_get_order($order_id);
    if (!$order) return new WP_Error('invalid_order', 'Pedido não encontrado');
    
    $user_id = $order->get_user_id();
    if (!$user_id) return new WP_Error('no_user', 'Pedido sem usuário vinculado');
    
    if (!$order->needs_payment()) return new WP_Error('already_paid', 'Pedido já foi pago');
    
    return lr_get_autologin_url($user_id, $order_id, $options);
}

function lr_get_all_tokens() {
    global $wpdb;
    $tokens = array();
    $option_names = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'lr_autologin_%' ORDER BY option_id DESC");
    
    foreach ($option_names as $name) {
        $token = str_replace('lr_autologin_', '', $name);
        $data = get_option($name);
        if ($data) {
            $data['token'] = $token;
            $tokens[] = $data;
        }
    }
    
    return $tokens;
}

function lr_delete_token($token) {
    return delete_option('lr_autologin_' . $token);
}

function lr_delete_expired_tokens() {
    $tokens = lr_get_all_tokens();
    $deleted = 0;
    foreach ($tokens as $t) {
        if (time() > $t['expiry']) {
            lr_delete_token($t['token']);
            $deleted++;
        }
    }
    return $deleted;
}

function lr_get_pending_orders($limit = 20) {
    $orders = wc_get_orders(array(
        'status' => array('pending', 'failed', 'on-hold'),
        'limit' => $limit,
        'orderby' => 'date',
        'order' => 'DESC',
    ));
    
    // Filtrar apenas pedidos que têm usuário vinculado
    $filtered = array();
    foreach ($orders as $order) {
        if ($order->get_user_id() > 0) {
            $filtered[] = $order;
        }
    }
    
    return $filtered;
}

function lr_get_whatsapp_message_template() {
    $default = "Olá {nome}! 👋

Vi que seu pedido #{pedido} ficou pendente no Lab Resumos.

💰 Valor: {valor}
📦 Produto: {produtos}

Para facilitar, preparei um link especial que já faz login automático e leva direto para o pagamento:

🔗 {link}

✅ Sem precisar de senha
⏱️ Link válido por 72h

Qualquer dúvida, estou à disposição!

Abraços,
Equipe Lab Resumos";
    
    return get_option('lr_whatsapp_template', $default);
}

function lr_save_whatsapp_template($template) {
    update_option('lr_whatsapp_template', $template);
}

// ============================================================================
// PÁGINA DE ADMIN
// ============================================================================
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Links de Pagamento',
        '🔗 Links Pagamento',
        'manage_woocommerce',
        'lr-payment-links',
        'lr_payment_links_admin_page'
    );
});

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'woocommerce_page_lr-payment-links') return;
    
    wp_add_inline_style('wp-admin', '
        .lr-wrap { max-width: 1400px; }
        .lr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 1200px) { .lr-grid { grid-template-columns: 1fr; } }
        .lr-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 20px; }
        .lr-card h2 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 8px; font-size: 15px; }
        .lr-card h2 .dashicons { color: #F1CC00; }
        .lr-success { background: #d4edda; border-color: #c3e6cb; }
        .lr-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .lr-table th, .lr-table td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #eee; }
        .lr-table th { background: #f8f9fa; font-weight: 600; font-size: 12px; }
        .lr-table tr:hover { background: #f8f9fa; }
        .lr-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
        .lr-badge-success { background: #d4edda; color: #155724; }
        .lr-badge-warning { background: #fff3cd; color: #856404; }
        .lr-badge-danger { background: #f8d7da; color: #721c24; }
        .lr-badge-info { background: #d1ecf1; color: #0c5460; }
        .lr-badge-pending { background: #fff3cd; color: #856404; }
        .lr-badge-failed { background: #f8d7da; color: #721c24; }
        .lr-badge-on-hold { background: #d1ecf1; color: #0c5460; }
        .lr-btn { display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; cursor: pointer; border: none; transition: all 0.2s; }
        .lr-btn-primary { background: #F1CC00; color: #333; }
        .lr-btn-primary:hover { background: #d4b400; color: #333; }
        .lr-btn-secondary { background: #6c757d; color: #fff; }
        .lr-btn-secondary:hover { background: #5a6268; color: #fff; }
        .lr-btn-success { background: #25D366; color: #fff; }
        .lr-btn-success:hover { background: #1da851; color: #fff; }
        .lr-btn-danger { background: #dc3545; color: #fff; }
        .lr-btn-danger:hover { background: #c82333; color: #fff; }
        .lr-btn-sm { padding: 4px 8px; font-size: 12px; }
        .lr-btn-xs { padding: 2px 6px; font-size: 11px; }
        .lr-stats { display: flex; gap: 15px; margin-bottom: 20px; }
        .lr-stat { flex: 1; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; text-align: center; }
        .lr-stat-value { font-size: 28px; font-weight: 700; color: #2271b1; }
        .lr-stat-label { color: #666; font-size: 12px; margin-top: 3px; }
        .lr-form-row { margin-bottom: 12px; }
        .lr-form-row label { display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; }
        .lr-form-row input, .lr-form-row select, .lr-form-row textarea { padding: 8px 12px; border: 1px solid #8c8f94; border-radius: 4px; width: 100%; box-sizing: border-box; }
        .lr-form-row input[type="number"] { width: 100px; }
        .lr-form-row textarea { min-height: 200px; font-family: inherit; font-size: 13px; line-height: 1.5; }
        .lr-form-inline { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .lr-form-inline .lr-form-row { margin-bottom: 0; }
        .lr-link-box { background: #f0f0f1; padding: 15px; border-radius: 4px; margin-top: 15px; }
        .lr-link-box input { width: 100%; padding: 10px; font-family: monospace; font-size: 12px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; }
        .lr-link-actions { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .lr-empty { text-align: center; padding: 30px; color: #666; }
        .lr-order-info { background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 4px; padding: 12px; margin-top: 12px; font-size: 13px; }
        .lr-order-info p { margin: 4px 0; }
        .lr-pending-list { max-height: 400px; overflow-y: auto; }
        .lr-pending-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #eee; gap: 10px; }
        .lr-pending-item:last-child { border-bottom: none; }
        .lr-pending-item:hover { background: #f8f9fa; }
        .lr-pending-info { flex: 1; min-width: 0; }
        .lr-pending-info strong { display: block; font-size: 13px; }
        .lr-pending-info small { color: #666; font-size: 12px; }
        .lr-pending-actions { display: flex; gap: 5px; flex-shrink: 0; }
        .lr-whatsapp-preview { background: #DCF8C6; border-radius: 8px; padding: 15px; margin-top: 15px; font-size: 13px; white-space: pre-wrap; font-family: inherit; max-height: 300px; overflow-y: auto; }
        .lr-tabs { display: flex; gap: 5px; margin-bottom: 15px; border-bottom: 1px solid #c3c4c7; padding-bottom: 10px; }
        .lr-tab { padding: 8px 16px; border: none; background: #f0f0f1; border-radius: 4px 4px 0 0; cursor: pointer; font-size: 13px; }
        .lr-tab.active { background: #F1CC00; font-weight: 600; }
        .lr-tab-content { display: none; }
        .lr-tab-content.active { display: block; }
        .lr-help { font-size: 11px; color: #666; margin-top: 5px; }
        .lr-divider { border-top: 1px solid #eee; margin: 15px 0; }
    ');
});

function lr_payment_links_admin_page() {
    // Processar ações
    $message = '';
    $generated_link = '';
    $order_info = null;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'generate';
    
    // Salvar template WhatsApp
    if (isset($_POST['save_template']) && check_admin_referer('lr_save_template')) {
        lr_save_whatsapp_template(sanitize_textarea_field($_POST['whatsapp_template']));
        $message = '✅ Template salvo com sucesso!';
        $active_tab = 'settings';
    }
    
    // Deletar token
    if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'lr_delete_token')) {
            lr_delete_token(sanitize_text_field($_GET['delete']));
            $message = '✅ Link removido.';
        }
    }
    
    // Limpar expirados
    if (isset($_GET['cleanup']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'lr_cleanup')) {
            $deleted = lr_delete_expired_tokens();
            $message = "✅ Removidos {$deleted} links expirados.";
        }
    }
    
    // Gerar via quick action
    if (isset($_GET['quick_generate']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'lr_quick_generate')) {
            $order_id = absint($_GET['quick_generate']);
            $result = lr_get_payment_link_for_order($order_id, array('max_uses' => 0, 'expiry_hours' => 72));
            if (!is_wp_error($result)) {
                $generated_link = $result;
                $order = wc_get_order($order_id);
                if ($order) {
                    $items = array();
                    foreach ($order->get_items() as $item) {
                        $items[] = $item->get_name();
                    }
                    $order_info = array(
                        'id' => $order_id,
                        'status' => $order->get_status(),
                        'status_name' => wc_get_order_status_name($order->get_status()),
                        'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        'first_name' => $order->get_billing_first_name(),
                        'email' => $order->get_billing_email(),
                        'phone' => $order->get_billing_phone(),
                        'total' => $order->get_formatted_order_total(),
                        'total_raw' => $order->get_total(),
                        'products' => implode(', ', $items),
                        'date' => $order->get_date_created()->date_i18n('d/m/Y H:i')
                    );
                }
                $message = '✅ Link gerado!';
            } else {
                $message = '❌ ' . $result->get_error_message();
            }
        }
    }
    
    // Gerar novo link via form
    if (isset($_POST['generate']) && check_admin_referer('lr_generate')) {
        $order_id = absint($_POST['order_id']);
        $options = array(
            'max_uses' => isset($_POST['max_uses']) ? absint($_POST['max_uses']) : 0,
            'expiry_hours' => isset($_POST['expiry_hours']) ? absint($_POST['expiry_hours']) : 72
        );
        
        $result = lr_get_payment_link_for_order($order_id, $options);
        
        if (is_wp_error($result)) {
            $message = '❌ ' . $result->get_error_message();
        } else {
            $generated_link = $result;
            $order = wc_get_order($order_id);
            if ($order) {
                $items = array();
                foreach ($order->get_items() as $item) {
                    $items[] = $item->get_name();
                }
                $order_info = array(
                    'id' => $order_id,
                    'status' => $order->get_status(),
                    'status_name' => wc_get_order_status_name($order->get_status()),
                    'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'first_name' => $order->get_billing_first_name(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                    'total' => $order->get_formatted_order_total(),
                    'total_raw' => $order->get_total(),
                    'products' => implode(', ', $items),
                    'date' => $order->get_date_created()->date_i18n('d/m/Y H:i')
                );
            }
            $message = '✅ Link gerado com sucesso!';
        }
    }
    
    // Obter dados
    $tokens = lr_get_all_tokens();
    $pending_orders = lr_get_pending_orders(20);
    $whatsapp_template = lr_get_whatsapp_message_template();
    
    // Estatísticas
    $stats = array('total' => count($tokens), 'active' => 0, 'expired' => 0, 'used' => 0);
    foreach ($tokens as $t) {
        if (time() > $t['expiry']) $stats['expired']++;
        else $stats['active']++;
        if (isset($t['use_count']) && $t['use_count'] > 0) $stats['used']++;
    }
    
    ?>
    <div class="wrap lr-wrap">
        <h1 style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
            <span class="dashicons dashicons-admin-links" style="font-size: 30px; color: #F1CC00;"></span>
            Links de Pagamento
        </h1>
        
        <?php if ($message): ?>
            <div class="notice notice-<?php echo strpos($message, '❌') !== false ? 'error' : 'success'; ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Estatísticas -->
        <div class="lr-stats">
            <div class="lr-stat">
                <div class="lr-stat-value"><?php echo $stats['total']; ?></div>
                <div class="lr-stat-label">Total</div>
            </div>
            <div class="lr-stat">
                <div class="lr-stat-value" style="color: #28a745;"><?php echo $stats['active']; ?></div>
                <div class="lr-stat-label">Ativos</div>
            </div>
            <div class="lr-stat">
                <div class="lr-stat-value" style="color: #007bff;"><?php echo $stats['used']; ?></div>
                <div class="lr-stat-label">Utilizados</div>
            </div>
            <div class="lr-stat">
                <div class="lr-stat-value" style="color: #dc3545;"><?php echo count($pending_orders); ?></div>
                <div class="lr-stat-label">Pedidos Pendentes</div>
            </div>
        </div>
        
        <div class="lr-grid">
            <!-- Coluna Esquerda -->
            <div>
                <!-- Pedidos Pendentes -->
                <div class="lr-card">
                    <h2><span class="dashicons dashicons-warning"></span> Pedidos Aguardando Pagamento</h2>
                    
                    <?php if (empty($pending_orders)): ?>
                        <div class="lr-empty">
                            <p>🎉 Nenhum pedido pendente!</p>
                        </div>
                    <?php else: ?>
                        <div class="lr-pending-list">
                            <?php foreach ($pending_orders as $order): 
                                $order_id = $order->get_id();
                                $quick_url = wp_nonce_url(
                                    admin_url('admin.php?page=lr-payment-links&quick_generate=' . $order_id),
                                    'lr_quick_generate'
                                );
                                $phone = $order->get_billing_phone();
                                $phone_clean = preg_replace('/[^0-9]/', '', $phone);
                                if (strlen($phone_clean) == 11) {
                                    $phone_clean = '55' . $phone_clean;
                                }
                            ?>
                                <div class="lr-pending-item">
                                    <div class="lr-pending-info">
                                        <strong>
                                            #<?php echo $order_id; ?> — <?php echo esc_html($order->get_billing_first_name()); ?>
                                            <span class="lr-badge lr-badge-<?php echo $order->get_status(); ?>">
                                                <?php echo wc_get_order_status_name($order->get_status()); ?>
                                            </span>
                                        </strong>
                                        <small>
                                            <?php echo $order->get_formatted_order_total(); ?> • 
                                            <?php echo $order->get_date_created()->date_i18n('d/m H:i'); ?>
                                            <?php if ($phone): ?> • 📱 <?php echo esc_html($phone); ?><?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="lr-pending-actions">
                                        <a href="<?php echo esc_url($quick_url); ?>" class="lr-btn lr-btn-primary lr-btn-sm" title="Gerar Link">
                                            🔗 Gerar
                                        </a>
                                        <a href="<?php echo admin_url('post.php?post=' . $order_id . '&action=edit'); ?>" 
                                           class="lr-btn lr-btn-secondary lr-btn-sm" title="Ver Pedido" target="_blank">
                                            👁️
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Gerar Manualmente -->
                <div class="lr-card">
                    <h2><span class="dashicons dashicons-plus-alt"></span> Gerar Link Manual</h2>
                    
                    <form method="post">
                        <?php wp_nonce_field('lr_generate'); ?>
                        
                        <div class="lr-form-inline">
                            <div class="lr-form-row">
                                <label>Nº Pedido</label>
                                <input type="number" name="order_id" required placeholder="1238"
                                       value="<?php echo isset($_POST['order_id']) ? esc_attr($_POST['order_id']) : ''; ?>">
                            </div>
                            
                            <div class="lr-form-row">
                                <label>Usos</label>
                                <select name="max_uses">
                                    <option value="0">♾️ Ilimitado</option>
                                    <option value="1">1</option>
                                    <option value="3">3</option>
                                    <option value="5">5</option>
                                </select>
                            </div>
                            
                            <div class="lr-form-row">
                                <label>Validade</label>
                                <select name="expiry_hours">
                                    <option value="24">24h</option>
                                    <option value="72" selected>72h</option>
                                    <option value="168">7 dias</option>
                                    <option value="720">30 dias</option>
                                </select>
                            </div>
                            
                            <div class="lr-form-row">
                                <label>&nbsp;</label>
                                <button type="submit" name="generate" class="lr-btn lr-btn-primary">
                                    🔗 Gerar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Link Gerado -->
                <?php if ($generated_link && $order_info): ?>
                    <div class="lr-card lr-success">
                        <h2><span class="dashicons dashicons-yes-alt"></span> Link Gerado!</h2>
                        
                        <div class="lr-order-info">
                            <p><strong>📦 Pedido #<?php echo $order_info['id']; ?></strong> — <?php echo $order_info['status_name']; ?></p>
                            <p>👤 <?php echo esc_html($order_info['customer']); ?></p>
                            <p>📧 <?php echo esc_html($order_info['email']); ?></p>
                            <?php if ($order_info['phone']): ?>
                                <p>📱 <?php echo esc_html($order_info['phone']); ?></p>
                            <?php endif; ?>
                            <p>💰 <?php echo $order_info['total']; ?></p>
                            <p>📝 <?php echo esc_html($order_info['products']); ?></p>
                        </div>
                        
                        <div class="lr-link-box">
                            <input type="text" value="<?php echo esc_url($generated_link); ?>" 
                                   readonly onclick="this.select();" id="generated-link">
                            
                            <div class="lr-link-actions">
                                <button type="button" class="lr-btn lr-btn-primary" onclick="copyToClipboard('generated-link')">
                                    📋 Copiar Link
                                </button>
                                <a href="<?php echo esc_url($generated_link); ?>" target="_blank" class="lr-btn lr-btn-secondary">
                                    🔗 Testar
                                </a>
                                <?php 
                                $phone_clean = preg_replace('/[^0-9]/', '', $order_info['phone']);
                                if (strlen($phone_clean) == 11) $phone_clean = '55' . $phone_clean;
                                
                                $wa_message = str_replace(
                                    array('{nome}', '{pedido}', '{valor}', '{produtos}', '{link}'),
                                    array($order_info['first_name'], $order_info['id'], $order_info['total'], $order_info['products'], $generated_link),
                                    $whatsapp_template
                                );
                                ?>
                                <a href="https://wa.me/<?php echo $phone_clean; ?>?text=<?php echo rawurlencode($wa_message); ?>" 
                                   target="_blank" class="lr-btn lr-btn-success">
                                    💬 WhatsApp
                                </a>
                            </div>
                        </div>
                        
                        <div class="lr-divider"></div>
                        
                        <h4 style="margin: 0 0 10px;">📱 Prévia da Mensagem WhatsApp:</h4>
                        <div class="lr-whatsapp-preview" id="wa-preview"><?php echo esc_html($wa_message); ?></div>
                        
                        <div style="margin-top: 10px;">
                            <button type="button" class="lr-btn lr-btn-secondary lr-btn-sm" onclick="copyToClipboard('wa-preview', true)">
                                📋 Copiar Mensagem
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Coluna Direita -->
            <div>
                <!-- Lista de Links -->
                <div class="lr-card">
                    <h2 style="justify-content: space-between;">
                        <span><span class="dashicons dashicons-list-view"></span> Links Gerados</span>
                        <?php if ($stats['expired'] > 0): ?>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=lr-payment-links&cleanup=1'), 'lr_cleanup'); ?>" 
                               class="lr-btn lr-btn-secondary lr-btn-xs">
                                🧹 Limpar <?php echo $stats['expired']; ?> expirados
                            </a>
                        <?php endif; ?>
                    </h2>
                    
                    <?php if (empty($tokens)): ?>
                        <div class="lr-empty">
                            <p>Nenhum link gerado ainda.</p>
                        </div>
                    <?php else: ?>
                        <div style="max-height: 500px; overflow-y: auto;">
                            <table class="lr-table">
                                <thead>
                                    <tr>
                                        <th>Pedido</th>
                                        <th>Expira</th>
                                        <th>Usos</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tokens as $t): 
                                        $order = isset($t['order_id']) ? wc_get_order($t['order_id']) : null;
                                        $is_expired = time() > $t['expiry'];
                                        $max_uses = isset($t['max_uses']) ? $t['max_uses'] : 1;
                                        $use_count = isset($t['use_count']) ? $t['use_count'] : 0;
                                        $is_exhausted = $max_uses > 0 && $use_count >= $max_uses;
                                        $link_url = home_url('/autologin/?token=' . $t['token'] . ($t['order_id'] ? '&order=' . $t['order_id'] : ''));
                                    ?>
                                        <tr style="<?php echo $is_expired ? 'opacity: 0.5;' : ''; ?>">
                                            <td>
                                                <?php if ($order): ?>
                                                    <a href="<?php echo admin_url('post.php?post=' . $t['order_id'] . '&action=edit'); ?>" target="_blank">
                                                        #<?php echo $t['order_id']; ?>
                                                    </a>
                                                    <br><small><?php echo $order->get_formatted_order_total(); ?></small>
                                                <?php else: ?>
                                                    <span style="color: #999;">#<?php echo $t['order_id'] ?: '—'; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($is_expired): ?>
                                                    <span style="color: #dc3545;">Expirado</span>
                                                <?php else: 
                                                    $hours_left = round(($t['expiry'] - time()) / 3600);
                                                ?>
                                                    <?php echo $hours_left; ?>h
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($max_uses == 0): ?>
                                                    <?php echo $use_count; ?>/♾️
                                                <?php else: ?>
                                                    <?php echo $use_count; ?>/<?php echo $max_uses; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($is_expired): ?>
                                                    <span class="lr-badge lr-badge-danger">Exp</span>
                                                <?php elseif ($is_exhausted): ?>
                                                    <span class="lr-badge lr-badge-warning">Esgot</span>
                                                <?php else: ?>
                                                    <span class="lr-badge lr-badge-success">Ativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$is_expired && !$is_exhausted): ?>
                                                    <button type="button" class="lr-btn lr-btn-primary lr-btn-xs" 
                                                            onclick="navigator.clipboard.writeText('<?php echo esc_js($link_url); ?>'); alert('Copiado!');">
                                                        📋
                                                    </button>
                                                <?php endif; ?>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=lr-payment-links&delete=' . $t['token']), 'lr_delete_token'); ?>" 
                                                   class="lr-btn lr-btn-danger lr-btn-xs"
                                                   onclick="return confirm('Remover?');">
                                                    🗑️
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Template WhatsApp -->
                <div class="lr-card">
                    <h2><span class="dashicons dashicons-whatsapp"></span> Template WhatsApp</h2>
                    
                    <form method="post">
                        <?php wp_nonce_field('lr_save_template'); ?>
                        
                        <div class="lr-form-row">
                            <label>Mensagem Padrão</label>
                            <textarea name="whatsapp_template"><?php echo esc_textarea($whatsapp_template); ?></textarea>
                            <p class="lr-help">
                                Variáveis: <code>{nome}</code> <code>{pedido}</code> <code>{valor}</code> <code>{produtos}</code> <code>{link}</code>
                            </p>
                        </div>
                        
                        <button type="submit" name="save_template" class="lr-btn lr-btn-primary">
                            💾 Salvar Template
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        function copyToClipboard(elementId, isText) {
            var element = document.getElementById(elementId);
            var text = isText ? element.innerText : element.value;
            navigator.clipboard.writeText(text).then(function() {
                alert('✅ Copiado!');
            });
        }
        </script>
    </div>
    <?php
}

// ============================================================================
// ADICIONAR LINK NOS EMAILS
// ============================================================================
add_action('woocommerce_email_before_order_table', function($order, $sent_to_admin, $plain_text, $email) {
    if ($sent_to_admin || !$order->needs_payment()) return;
    
    $applicable = array('customer_on_hold_order', 'customer_pending_order', 'customer_failed_order', 'customer_invoice');
    if (!isset($email->id) || !in_array($email->id, $applicable)) return;
    
    $user_id = $order->get_user_id();
    if (!$user_id) return;
    
    $url = lr_get_autologin_url($user_id, $order->get_id(), array('max_uses' => 0, 'expiry_hours' => 72));
    
    if (!$plain_text): ?>
        <div style="background: #fffde7; border-left: 4px solid #F1CC00; padding: 20px; margin: 20px 0; text-align: center;">
            <p style="margin: 0 0 15px; font-size: 16px; font-weight: bold; color: #333;">
                💡 Finalize com 1 clique!
            </p>
            <a href="<?php echo esc_url($url); ?>" 
               style="display: inline-block; padding: 14px 28px; background: #F1CC00; color: #333; text-decoration: none; border-radius: 6px; font-weight: bold;">
                🔓 Finalizar Pagamento
            </a>
            <p style="margin: 12px 0 0; font-size: 12px; color: #666;">
                Login automático • Válido por 72h
            </p>
        </div>
    <?php endif;
}, 10, 4);

// ============================================================================
// LIMPEZA AUTOMÁTICA
// ============================================================================
add_action('woocommerce_cleanup_sessions', function() {
    lr_delete_expired_tokens();
});