<?php
/**
 * Plugin Name: Lab Resumos - Account
 * Description: Reset de senha, banner "Meus Materiais", mensagem de acesso na thank-you
 *              page e ação admin de ver a thank-you page de um pedido. Fase F3b do roadmap
 *              (docs/plugins-custom-analise-e-roadmap.md) — portado dos snippets WPCode
 *              #1023, #1214, #1283, #1014 e #995, sem alteração de lógica.
 * Version: 1.0.0
 * Author: Lab Resumos
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// #1023 — Personalização de Reset de Senha - Lab Resumos
// ============================================================================
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
if (!function_exists('lab_redirect_wp_login_to_woo')) {
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
}

// ============================================
// 2. ALTERAR LINK NO EMAIL DE RESET
//    (Apenas para não-admins)
// ============================================

add_filter('retrieve_password_message', 'lab_custom_reset_password_email', 10, 4);
if (!function_exists('lab_custom_reset_password_email')) {
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
}

// Assunto personalizado (apenas para não-admins é tratado acima)
add_filter('retrieve_password_title', 'lab_custom_reset_password_subject', 10, 3);
if (!function_exists('lab_custom_reset_password_subject')) {
    function lab_custom_reset_password_subject($title, $user_login, $user_data) {
        if (user_can($user_data, 'manage_options')) {
            return $title; // Mantém original para admins
        }
        return 'Redefinição de Senha - Lab Resumos';
    }
}

// Email como HTML
add_filter('wp_mail_content_type', 'lab_set_html_mail_content_type');
if (!function_exists('lab_set_html_mail_content_type')) {
    function lab_set_html_mail_content_type() {
        return 'text/html';
    }
}

// ============================================
// 3. CSS PARA PÁGINA DE RESET DO WOOCOMMERCE
// ============================================

add_action('wp_head', 'lab_style_woo_lost_password_page');
if (!function_exists('lab_style_woo_lost_password_page')) {
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
}

// ============================================================================
// #1214 — Corrige o link "Perdeu sua senha?" para usar /conta/ em vez de /minha-conta/
// ============================================================================
add_filter('lostpassword_url', 'lab_fix_lostpassword_url', 20, 2);
if (!function_exists('lab_fix_lostpassword_url')) {
    function lab_fix_lostpassword_url($url, $redirect) {
        $url = str_replace('/minha-conta/', '/conta/', $url);
        return $url;
    }
}

// ============================================================================
// #1283 — Banner de Acesso aos Materiais - HERO (v5)
// ============================================================================
/**
 * Snippet: Banner de Acesso aos Materiais - HERO (v5)
 * Com paleta de cores oficial Lab Resumos
 *
 * Inserir via WPCode
 * Local: Página "Minha Conta" do WooCommerce
 */

// Remove versões anteriores
remove_action('woocommerce_account_dashboard', 'lab_banner_acesso_materiais', 5);
remove_action('woocommerce_account_dashboard', 'lab_banner_acesso_materiais_v2', 5);
remove_action('woocommerce_account_dashboard', 'lab_banner_acesso_materiais_v3', 5);
remove_action('woocommerce_before_account_navigation', 'lab_banner_hero_materiais', 5);

// Adiciona ANTES do conteúdo padrão do WooCommerce
add_action('woocommerce_before_account_navigation', 'lab_banner_hero_materiais_v5', 5);
if (!function_exists('lab_banner_hero_materiais_v5')) {
    function lab_banner_hero_materiais_v5() {
        $user = wp_get_current_user();
        $primeiro_nome = $user->first_name ?: $user->display_name;
        ?>
        <div class="lab-hero-wrapper">
            <div class="lab-hero-card">
                <!-- Background Elements -->
                <div class="lab-hero-bg">
                    <div class="lab-hero-gradient"></div>
                    <div class="lab-hero-pattern"></div>
                    <div class="lab-hero-glow lab-hero-glow-1"></div>
                    <div class="lab-hero-glow lab-hero-glow-2"></div>
                </div>

                <!-- Content -->
                <div class="lab-hero-content">
                    <div class="lab-hero-left">
                        <div class="lab-hero-badge">
                            <span class="lab-hero-badge-dot"></span>
                            Área do Aluno
                        </div>

                        <h1 class="lab-hero-title">
                            Olá, <span class="lab-hero-name"><?php echo esc_html($primeiro_nome); ?></span>! 👋
                        </h1>

                        <p class="lab-hero-subtitle">
                            Seus materiais de estudo estão prontos e te esperando.
                            Acesse agora seus <strong>resumos</strong>, <strong>flashcards</strong> e <strong>questões comentadas</strong>.
                        </p>

                        <div class="lab-hero-actions">
                            <a href="https://aluno.labresumos.com.br/my/courses.php" target="_blank" class="lab-hero-btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/>
                                </svg>
                                Acessar Meus Materiais
                                <svg class="lab-hero-arrow" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </a>

                            <div class="lab-hero-hint">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 16v-4M12 8h.01"/>
                                </svg>
                                Acesso exclusivo para alunos
                            </div>
                        </div>
                    </div>

                    <div class="lab-hero-right">
                        <div class="lab-hero-illustration">
                            <div class="lab-hero-card-stack">
                                <div class="lab-hero-card-item lab-hero-card-1">
                                    <div class="lab-hero-card-icon">📚</div>
                                    <span>Resumos</span>
                                </div>
                                <div class="lab-hero-card-item lab-hero-card-2">
                                    <div class="lab-hero-card-icon">🎴</div>
                                    <span>Flashcards</span>
                                </div>
                                <div class="lab-hero-card-item lab-hero-card-3">
                                    <div class="lab-hero-card-icon">✍️</div>
                                    <span>Questões</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        /*
         * Paleta Lab Resumos:
         * Amarelo Queimado: #F1CC00
         * Amarelo Vibrante: #FEEF4C
         * Preto Profundo: #333B49
         * Azul Celeste: #0475CF
         * Branco Gelo: #F3F1E8
         * Azul Claro: #A0DDFC
         * Azul Turquesa: #2EBAE5
         */

        /* Wrapper */
        .lab-hero-wrapper {
            width: 100%;
            margin-bottom: 40px;
        }

        /* Card Principal - Preto Profundo como base */
        .lab-hero-card {
            position: relative;
            background: linear-gradient(145deg, #333B49 0%, #3d4556 50%, #333B49 100%);
            border-radius: 24px;
            padding: 48px 56px;
            overflow: hidden;
            box-shadow:
                0 20px 60px rgba(51, 59, 73, 0.4),
                0 8px 24px rgba(0, 0, 0, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        /* Background Elements */
        .lab-hero-bg {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .lab-hero-gradient {
            position: absolute;
            top: 0;
            right: 0;
            width: 60%;
            height: 100%;
            background: linear-gradient(135deg, transparent 0%, rgba(46, 186, 229, 0.08) 100%);
        }

        .lab-hero-pattern {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        .lab-hero-glow {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
        }

        /* Glow com Amarelo Queimado */
        .lab-hero-glow-1 {
            width: 300px;
            height: 300px;
            top: -100px;
            right: 10%;
            background: rgba(241, 204, 0, 0.2);
        }

        /* Glow com Azul Turquesa */
        .lab-hero-glow-2 {
            width: 200px;
            height: 200px;
            bottom: -80px;
            left: 20%;
            background: rgba(46, 186, 229, 0.15);
        }

        /* Content Layout */
        .lab-hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 48px;
        }

        .lab-hero-left {
            flex: 1;
            max-width: 580px;
        }

        .lab-hero-right {
            flex-shrink: 0;
        }

        /* Badge - Azul Celeste */
        .lab-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(4, 117, 207, 0.2);
            border: 1px solid rgba(4, 117, 207, 0.4);
            color: #A0DDFC;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            padding: 10px 18px;
            border-radius: 50px;
            margin-bottom: 24px;
        }

        /* Dot com Azul Turquesa */
        .lab-hero-badge-dot {
            width: 8px;
            height: 8px;
            background: #2EBAE5;
            border-radius: 50%;
            animation: lab-pulse 2s ease-in-out infinite;
            box-shadow: 0 0 12px rgba(46, 186, 229, 0.6);
        }

        @keyframes lab-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.3); }
        }

        /* Title */
        .lab-hero-title {
            color: #ffffff;
            font-size: 2.5em;
            font-weight: 700;
            line-height: 1.2;
            margin: 0 0 16px 0;
            letter-spacing: -0.03em;
        }

        /* Nome com gradiente Amarelo */
        .lab-hero-name {
            background: linear-gradient(135deg, #F1CC00 0%, #FEEF4C 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .lab-hero-wrapper .lab-hero-subtitle {
            color: rgba(255, 255, 255, 0.85);
            font-size: 1.15em;
            line-height: 1.7;
            margin: 0 0 32px 0;
        }

        .lab-hero-wrapper .lab-hero-subtitle strong {
            color: #FEEF4C !important;
            font-weight: 600;
        }

        /* Actions */
        .lab-hero-actions {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }

        /* Botão com Amarelo Queimado */
        .lab-hero-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #F1CC00 0%, #e0bd00 100%);
            color: #333B49 !important;
            font-weight: 700;
            font-size: 1.05em;
            padding: 18px 32px;
            border-radius: 14px;
            text-decoration: none !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow:
                0 8px 24px rgba(241, 204, 0, 0.35),
                0 0 0 0 rgba(241, 204, 0, 0.4);
        }

        .lab-hero-btn-primary:hover {
            transform: translateY(-3px);
            background: linear-gradient(135deg, #FEEF4C 0%, #F1CC00 100%);
            box-shadow:
                0 12px 32px rgba(241, 204, 0, 0.5),
                0 0 0 4px rgba(241, 204, 0, 0.2);
            color: #333B49 !important;
        }

        .lab-hero-arrow {
            transition: transform 0.3s ease;
            stroke: #333B49;
        }

        .lab-hero-btn-primary:hover .lab-hero-arrow {
            transform: translateX(4px);
        }

        .lab-hero-hint {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85em;
        }

        /* Illustration - Card Stack */
        .lab-hero-illustration {
            width: 280px;
            height: 220px;
            position: relative;
        }

        .lab-hero-card-stack {
            position: relative;
            width: 100%;
            height: 100%;
        }

        /* Cards com Branco Gelo */
        .lab-hero-card-item {
            position: absolute;
            background: #F3F1E8;
            border-radius: 16px;
            padding: 20px 28px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }

        /* Texto dos cards em Preto Profundo */
        .lab-hero-card-item span {
            font-weight: 600;
            color: #333B49;
            font-size: 1em;
        }

        .lab-hero-card-icon {
            font-size: 1.5em;
        }

        .lab-hero-card-1 {
            top: 0;
            left: 0;
            transform: rotate(-3deg);
            z-index: 3;
        }

        .lab-hero-card-2 {
            top: 70px;
            left: 40px;
            transform: rotate(2deg);
            z-index: 2;
        }

        .lab-hero-card-3 {
            top: 140px;
            left: 10px;
            transform: rotate(-1deg);
            z-index: 1;
        }

        .lab-hero-card:hover .lab-hero-card-1 {
            transform: rotate(-3deg) translateY(-5px);
        }

        .lab-hero-card:hover .lab-hero-card-2 {
            transform: rotate(2deg) translateX(10px);
        }

        .lab-hero-card:hover .lab-hero-card-3 {
            transform: rotate(-1deg) translateY(5px);
        }

        /* Responsivo */
        @media (max-width: 1024px) {
            .lab-hero-card {
                padding: 40px 36px;
            }

            .lab-hero-right {
                display: none;
            }

            .lab-hero-title {
                font-size: 2em;
            }
        }

        @media (max-width: 640px) {
            .lab-hero-card {
                padding: 32px 24px;
                border-radius: 20px;
            }

            .lab-hero-badge {
                font-size: 0.7em;
                padding: 8px 14px;
            }

            .lab-hero-title {
                font-size: 1.6em;
            }

            .lab-hero-subtitle {
                font-size: 1em;
            }

            .lab-hero-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 16px;
            }

            .lab-hero-btn-primary {
                justify-content: center;
                padding: 18px 24px;
                font-size: 1em;
            }

            .lab-hero-hint {
                justify-content: center;
            }
        }
        </style>
        <?php
    }
}

// ============================================================================
// #1014 — Adiciona mensagem de acesso na Thank You page
// ============================================================================
add_action('woocommerce_thankyou', 'lab_mensagem_acesso_moodle', 5);
if (!function_exists('lab_mensagem_acesso_moodle')) {
    function lab_mensagem_acesso_moodle($order_id) {
        ?>
        <div class="lab-acesso-conteudo">
            <h3>📚 Como acessar seu conteúdo</h3>
            <p>Todo o material adquirido estará disponível na nossa <strong>Área do Aluno</strong>.</p>
            <p>Acesse agora mesmo em:</p>
            <a href="https://labresumos.com.br/area-aluno/" target="_blank" class="lab-btn-acesso">
                Acessar Área do Aluno →
            </a>
        </div>
        <?php
    }
}

// ============================================================================
// #995 — Ver Thank You Page (admin)
// ============================================================================
/**
 * View Thank You Page @ Edit Order Admin - Versão corrigida
 */

// Adiciona a ação no dropdown de ações do pedido
add_filter('woocommerce_order_actions', 'lab_show_thank_you_page_order_admin_actions', 9999, 2);

if (!function_exists('lab_show_thank_you_page_order_admin_actions')) {
    function lab_show_thank_you_page_order_admin_actions($actions, $order) {
        $actions['view_thankyou'] = 'Ver página de confirmação (Thank You)';
        return $actions;
    }
}

// Redireciona para a thank you page com token especial
add_action('woocommerce_order_action_view_thankyou', 'lab_redirect_thank_you_page_order_admin_actions');

if (!function_exists('lab_redirect_thank_you_page_order_admin_actions')) {
    function lab_redirect_thank_you_page_order_admin_actions($order) {
        $token = wp_create_nonce('view_thankyou_' . $order->get_id());
        $url = add_query_arg(array(
            'admin_view' => $token,
            'oid' => $order->get_id()
        ), $order->get_checkout_order_received_url());
        wp_safe_redirect($url);
        exit;
    }
}

// Intercepta ANTES do WooCommerce processar a página
add_action('template_redirect', 'lab_bypass_thankyou_login_check', 1);

if (!function_exists('lab_bypass_thankyou_login_check')) {
    function lab_bypass_thankyou_login_check() {
        if (!isset($_GET['admin_view']) || !isset($_GET['oid'])) {
            return;
        }

        $order_id = absint($_GET['oid']);
        $token = sanitize_text_field($_GET['admin_view']);

        // Verifica se o nonce é válido (garante que veio do admin)
        if (!wp_verify_nonce($token, 'view_thankyou_' . $order_id)) {
            return;
        }

        // Força o usuário atual a ser o dono do pedido
        $order = wc_get_order($order_id);
        if ($order) {
            $customer_id = $order->get_customer_id();
            if ($customer_id) {
                wp_set_current_user($customer_id);
            }
        }
    }
}

// Desabilita verificação de email para visitantes quando admin_view está presente
add_filter('woocommerce_order_received_verify_known_shoppers', 'lab_disable_email_verify_for_admin', 999);

if (!function_exists('lab_disable_email_verify_for_admin')) {
    function lab_disable_email_verify_for_admin($verify) {
        if (isset($_GET['admin_view']) && isset($_GET['oid'])) {
            $order_id = absint($_GET['oid']);
            $token = sanitize_text_field($_GET['admin_view']);
            if (wp_verify_nonce($token, 'view_thankyou_' . $order_id)) {
                return false;
            }
        }
        return $verify;
    }
}
