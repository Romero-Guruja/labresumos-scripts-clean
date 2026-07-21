<?php
/**
 * WPCode snippet #1023 — Personalização de Reset de Senha - Lab Resumos
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */

/**
 * Personalização de Reset de Senha - Lab Resumos
 * - Redireciona wp-login.php para /conta/ (apenas para clientes)
 * - Customiza email de redefinição
 * - NÃO afeta login de administradores
 */

// ============================================
// 1. REDIRECIONAR WP-LOGIN PARA WOOCOMMERCE
//    (Apenas para reset de senha de não-admins)
// ============================================

add_action('init', 'lab_redirect_wp_login_to_woo');
function lab_redirect_wp_login_to_woo() {
    // Não redireciona se já está logado como admin
    if (is_user_logged_in() && current_user_can('manage_options')) {
        return;
    }
    
    // Não redireciona no admin
    if (is_admin()) {
        return;
    }
    
    if (!isset($_SERVER['REQUEST_URI'])) {
        return;
    }
    
    $request = $_SERVER['REQUEST_URI'];
    
    // Só redireciona se estiver em wp-login.php
    if (strpos($request, 'wp-login.php') === false) {
        return;
    }
    
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    // Redireciona APENAS ações de reset de senha
    // Login normal (action vazio ou 'login') NÃO é redirecionado
    if ($action === 'lostpassword') {
        wp_redirect(wc_lostpassword_url());
        exit;
    }
    
    // Redireciona página de criar nova senha
    if ($action === 'rp' || $action === 'resetpass') {
        // Verifica se o usuário do reset é admin
        $login = isset($_GET['login']) ? sanitize_user($_GET['login']) : '';
        if ($login) {
            $user = get_user_by('login', $login);
            // Se for admin, NÃO redireciona - usa o padrão do WP
            if ($user && user_can($user, 'manage_options')) {
                return;
            }
        }
        
        $redirect_url = add_query_arg(array(
            'key' => isset($_GET['key']) ? $_GET['key'] : '',
            'id' => isset($_GET['login']) ? $_GET['login'] : '',
        ), wc_get_endpoint_url('lost-password', '', wc_get_page_permalink('myaccount')));
        
        wp_redirect($redirect_url);
        exit;
    }
}

// ============================================
// 2. ALTERAR LINK NO EMAIL DE RESET
//    (Apenas para não-admins)
// ============================================

add_filter('retrieve_password_message', 'lab_custom_reset_password_email', 10, 4);
function lab_custom_reset_password_email($message, $key, $user_login, $user_data) {
    
    // Se for admin, usa o email padrão do WordPress
    if (user_can($user_data, 'manage_options')) {
        return $message; // Retorna email original
    }
    
    // URL personalizada (WooCommerce) para clientes
    $reset_url = add_query_arg(array(
        'key' => $key,
        'id' => $user_data->ID,
    ), wc_get_endpoint_url('lost-password', '', wc_get_page_permalink('myaccount')));
    
    // Nome do usuário
    $first_name = get_user_meta($user_data->ID, 'first_name', true);
    $display_name = $first_name ? $first_name : $user_data->display_name;
    
    // Email HTML personalizado
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 40px 20px;">
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        
                        <!-- Header -->
                        <tr>
                            <td style="background-color: #333B49; padding: 30px 40px; text-align: center;">
                                <h1 style="color: #ffffff; margin: 0; font-size: 28px;">Lab Resumos</h1>
                            </td>
                        </tr>
                        
                        <!-- Content -->
                        <tr>
                            <td style="padding: 40px;">
                                <h2 style="color: #333B49; font-size: 22px; margin: 0 0 20px 0;">
                                    Redefinição de Senha
                                </h2>
                                
                                <p style="color: #555; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                    Olá' . ($display_name ? ', <strong>' . esc_html($display_name) . '</strong>' : '') . '!
                                </p>
                                
                                <p style="color: #555; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                    Recebemos uma solicitação para redefinir a senha da sua conta no Lab Resumos.
                                </p>
                                
                                <p style="color: #555; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                                    Clique no botão abaixo para criar uma nova senha:
                                </p>
                                
                                <!-- Button -->
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td align="center">
                                            <a href="' . esc_url($reset_url) . '" 
                                               style="display: inline-block; 
                                                      background-color: #2A6B9F; 
                                                      color: #ffffff; 
                                                      text-decoration: none; 
                                                      padding: 15px 40px; 
                                                      border-radius: 6px; 
                                                      font-size: 16px; 
                                                      font-weight: 600;">
                                                Redefinir Minha Senha
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                                
                                <p style="color: #888; font-size: 14px; line-height: 1.6; margin: 30px 0 0 0;">
                                    Se você não solicitou essa alteração, pode ignorar este email com segurança.
                                </p>
                                
                                <p style="color: #888; font-size: 14px; line-height: 1.6; margin: 15px 0 0 0;">
                                    Este link expira em 24 horas.
                                </p>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f8f9fa; padding: 25px 40px; border-top: 1px solid #eee;">
                                <p style="color: #888; font-size: 13px; margin: 0; text-align: center;">
                                    © ' . date('Y') . ' Lab Resumos<br>
                                    <a href="' . esc_url(get_site_url()) . '" style="color: #2A6B9F; text-decoration: none;">labresumos.com.br</a>
                                </p>
                            </td>
                        </tr>
                        
                    </table>
                    
                    <!-- Link fallback -->
                    <p style="color: #999; font-size: 12px; margin-top: 20px; text-align: center;">
                        Problemas com o botão? Copie e cole este link:<br>
                        <a href="' . esc_url($reset_url) . '" style="color: #2A6B9F; word-break: break-all; font-size: 11px;">' . esc_html($reset_url) . '</a>
                    </p>
                </td>
            </tr>
        </table>
    </body>
    </html>';
    
    return $html;
}

// Assunto personalizado (apenas para não-admins é tratado acima)
add_filter('retrieve_password_title', 'lab_custom_reset_password_subject', 10, 3);
function lab_custom_reset_password_subject($title, $user_login, $user_data) {
    if (user_can($user_data, 'manage_options')) {
        return $title; // Mantém original para admins
    }
    return 'Redefinição de Senha - Lab Resumos';
}

// Email como HTML
add_filter('wp_mail_content_type', 'lab_set_html_mail_content_type');
function lab_set_html_mail_content_type() {
    return 'text/html';
}

// ============================================
// 3. CSS PARA PÁGINA DE RESET DO WOOCOMMERCE
// ============================================

add_action('wp_head', 'lab_style_woo_lost_password_page');
function lab_style_woo_lost_password_page() {
    if (!function_exists('is_account_page') || !is_account_page()) return;
    ?>
    <style>
        .woocommerce-ResetPassword {
            max-width: 450px;
            margin: 40px auto;
            padding: 40px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
        }
        
        .woocommerce-ResetPassword label {
            color: #333B49;
            font-weight: 600;
        }
        
        .woocommerce-ResetPassword input[type="text"],
        .woocommerce-ResetPassword input[type="email"],
        .woocommerce-ResetPassword input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 15px;
        }
        
        .woocommerce-ResetPassword input:focus {
            border-color: #2A6B9F;
            outline: none;
        }
        
        .woocommerce-ResetPassword button[type="submit"] {
            width: 100%;
            background-color: #2A6B9F !important;
            color: #fff !important;
            padding: 14px 30px !important;
            border: none !important;
            border-radius: 6px !important;
            font-size: 16px !important;
            font-weight: 600 !important;
        }
        
        .woocommerce-ResetPassword button:hover {
            background-color: #1e5580 !important;
        }
    </style>
    <?php
}