<?php
/**
 * Classe pública principal
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Public
 * 
 * Gerencia assets e shortcodes públicos.
 */
class LRP_Public {

    /**
     * Construtor
     */
    public function __construct() {
        // Shortcodes são registrados via hook
    }

    /**
     * Enfileira estilos públicos
     */
    public function enqueue_styles() {
        // Sempre carrega o CSS básico
        wp_enqueue_style(
            'lrp-public',
            LRP_PLUGIN_URL . 'public/css/lrp-public.css',
            [],
            LRP_VERSION
        );
        
        // CSS do dashboard apenas na página do dashboard
        if ($this->is_dashboard_page()) {
            wp_enqueue_style(
                'lrp-dashboard',
                LRP_PLUGIN_URL . 'public/css/lrp-dashboard.css',
                ['lrp-public'],
                LRP_VERSION
            );
        }
        
        // CSS dos termos apenas na página de termos
        if ($this->is_terms_page()) {
            wp_enqueue_style(
                'lrp-terms',
                LRP_PLUGIN_URL . 'public/css/lrp-terms.css',
                ['lrp-public'],
                LRP_VERSION
            );
            
            // Google Font - Inter
            wp_enqueue_style(
                'lrp-google-fonts-inter',
                'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
                [],
                null
            );
        }
    }

    /**
     * Enfileira scripts públicos
     */
    public function enqueue_scripts() {
        // Script de tracking (sempre)
        wp_enqueue_script(
            'lrp-tracking',
            LRP_PLUGIN_URL . 'public/js/lrp-tracking.js',
            [],
            LRP_VERSION,
            true
        );
        
        $settings = LRP_Settings::instance();
        wp_localize_script('lrp-tracking', 'lrp_params', [
            'cookie_name' => LRP_Cookie_Tracker::COOKIE_NAME,
            'cookie_days' => $settings->get('default_cookie_days', 60),
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('lrp_tracking_nonce'),
        ]);
        
        // Scripts do dashboard apenas na página
        if ($this->is_dashboard_page()) {
            wp_enqueue_script(
                'lrp-dashboard',
                LRP_PLUGIN_URL . 'public/js/lrp-dashboard.js',
                ['jquery'],
                LRP_VERSION,
                true
            );
            
            // QRCode lib
            wp_enqueue_script(
                'qrcode',
                'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js',
                [],
                '1.4.4',
                true
            );
            
            wp_localize_script('lrp-dashboard', 'lrp_dashboard', [
                'ajax_url'      => admin_url('admin-ajax.php'),
                'nonce'         => wp_create_nonce('lrp_dashboard_nonce'),
                'copied_text'   => __('Copiado!', 'lab-resumos-parceiros'),
                'error_text'    => __('Erro ao copiar', 'lab-resumos-parceiros'),
                'upload_error'  => __('Erro no upload', 'lab-resumos-parceiros'),
                'confirm_upload'=> __('Deseja enviar esta NF?', 'lab-resumos-parceiros'),
            ]);
        }
    }

    /**
     * Registra shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('lrp_affiliate_dashboard', [$this, 'shortcode_dashboard']);
        add_shortcode('lrp_affiliate_registration', [$this, 'shortcode_registration']);
        add_shortcode('lrp_affiliate_link', [$this, 'shortcode_affiliate_link']);
        add_shortcode('lrp_affiliate_terms', [$this, 'shortcode_terms']);
    }

    /**
     * Shortcode do dashboard
     *
     * @param array $atts
     * @return string
     */
    public function shortcode_dashboard($atts) {
        // Verifica se está logado
        if (!is_user_logged_in()) {
            return $this->get_login_message();
        }
        
        // Admin preview mode - permite admin visualizar dashboard de qualquer afiliado
        $preview_affiliate_id = isset($_GET['preview_as']) ? (int) $_GET['preview_as'] : 0;
        
        if ($preview_affiliate_id && current_user_can('manage_options')) {
            $affiliate = new LRP_Affiliate($preview_affiliate_id);
            
            if (!$affiliate->exists()) {
                return '<div class="lrp-admin-preview-error">' .
                       '<p>❌ ' . __('Afiliado não encontrado.', 'lab-resumos-parceiros') . '</p>' .
                       '<a href="' . esc_url(admin_url('admin.php?page=lrp-affiliates')) . '">' . 
                       __('← Voltar para lista de afiliados', 'lab-resumos-parceiros') . '</a></div>';
            }
            
            // Renderiza banner de preview + dashboard
            $preview_banner = $this->get_preview_banner($affiliate);
            $dashboard = new LRP_Dashboard($affiliate);
            return $preview_banner . $dashboard->render();
        }
        
        // Fluxo normal para afiliados
        $user_id = get_current_user_id();
        $affiliate = LRP_Affiliate::get_by_user_id($user_id);
        
        if (!$affiliate) {
            return $this->get_not_affiliate_message();
        }
        
        // Verifica status
        if (!$affiliate->is_active()) {
            return $this->get_pending_message($affiliate);
        }
        
        // Renderiza dashboard
        $dashboard = new LRP_Dashboard($affiliate);
        return $dashboard->render();
    }
    
    /**
     * Retorna banner de preview para admins
     *
     * @param LRP_Affiliate $affiliate
     * @return string
     */
    private function get_preview_banner($affiliate) {
        $admin_url = admin_url('admin.php?page=lrp-affiliates&action=edit&id=' . $affiliate->get_id());
        $exit_url = admin_url('admin.php?page=lrp-affiliates');
        
        return '<div class="lrp-preview-banner" style="
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(238, 90, 36, 0.3);
        ">
            <div>
                <strong style="font-size: 16px;">👁️ ' . __('Modo Preview', 'lab-resumos-parceiros') . '</strong>
                <span style="opacity: 0.9; margin-left: 10px;">' . 
                    sprintf(__('Visualizando como: %s (%s)', 'lab-resumos-parceiros'), 
                        '<strong>' . esc_html($affiliate->get_display_name()) . '</strong>',
                        esc_html($affiliate->get_coupon_code())
                    ) . 
                '</span>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="' . esc_url($admin_url) . '" style="
                    background: rgba(255,255,255,0.2);
                    color: white;
                    padding: 8px 15px;
                    border-radius: 5px;
                    text-decoration: none;
                    font-size: 13px;
                ">' . __('✏️ Editar Afiliado', 'lab-resumos-parceiros') . '</a>
                <a href="' . esc_url($exit_url) . '" style="
                    background: white;
                    color: #ee5a24;
                    padding: 8px 15px;
                    border-radius: 5px;
                    text-decoration: none;
                    font-size: 13px;
                    font-weight: 600;
                ">' . __('✕ Sair do Preview', 'lab-resumos-parceiros') . '</a>
            </div>
        </div>';
    }

    /**
     * Shortcode de cadastro
     *
     * @param array $atts
     * @return string
     */
    public function shortcode_registration($atts) {
        // Se já é afiliado, redireciona
        if (is_user_logged_in()) {
            $affiliate = LRP_Affiliate::get_by_user_id(get_current_user_id());
            
            if ($affiliate) {
                $dashboard_page = get_option('lrp_dashboard_page_id');
                if ($dashboard_page) {
                    $url = get_permalink($dashboard_page);
                    return '<div class="lrp-notice lrp-notice-info">' .
                           '<p>' . __('Você já é um parceiro!', 'lab-resumos-parceiros') . '</p>' .
                           '<a href="' . esc_url($url) . '" class="lrp-button">' . 
                           __('Acessar Painel', 'lab-resumos-parceiros') . '</a></div>';
                }
            }
        }
        
        $registration = new LRP_Registration();
        return $registration->render();
    }

    /**
     * Shortcode de link de afiliado
     *
     * @param array $atts
     * @param string $content
     * @return string
     */
    public function shortcode_affiliate_link($atts, $content = null) {
        $atts = shortcode_atts([
            'product_id' => 0,
            'class'      => '',
        ], $atts);
        
        if (!is_user_logged_in()) {
            return '';
        }
        
        $affiliate = LRP_Affiliate::get_by_user_id(get_current_user_id());
        
        if (!$affiliate || !$affiliate->is_active()) {
            return '';
        }
        
        $url = $affiliate->get_referral_url($atts['product_id']);
        $text = $content ?: $url;
        $class = $atts['class'] ? esc_attr($atts['class']) : 'lrp-affiliate-link';
        
        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($url),
            $class,
            esc_html($text)
        );
    }

    /**
     * Verifica se está na página do dashboard
     *
     * @return bool
     */
    private function is_dashboard_page() {
        $dashboard_page_id = get_option('lrp_dashboard_page_id');
        
        if (!$dashboard_page_id) {
            return false;
        }
        
        return is_page($dashboard_page_id);
    }

    /**
     * Verifica se está na página de termos
     *
     * @return bool
     */
    private function is_terms_page() {
        $terms_page_id = get_option('lrp_terms_page_id');
        
        if (!$terms_page_id) {
            return false;
        }
        
        return is_page($terms_page_id);
    }

    /**
     * Shortcode de termos
     *
     * @param array $atts
     * @return string
     */
    public function shortcode_terms($atts) {
        ob_start();
        include LRP_PLUGIN_DIR . 'public/partials/terms.php';
        return ob_get_clean();
    }

    /**
     * Mensagem de login necessário
     *
     * @return string
     */
    private function get_login_message() {
        $login_url = wp_login_url(get_permalink());
        
        return '<div class="lrp-login-required">' .
               '<p>' . __('Você precisa estar logado para acessar o painel de parceiro.', 'lab-resumos-parceiros') . '</p>' .
               '<a href="' . esc_url($login_url) . '" class="lrp-button">' . 
               __('Fazer Login', 'lab-resumos-parceiros') . '</a></div>';
    }

    /**
     * Mensagem de não é afiliado
     *
     * @return string
     */
    private function get_not_affiliate_message() {
        $registration_page = get_option('lrp_registration_page_id');
        $registration_url = $registration_page ? get_permalink($registration_page) : home_url('/seja-parceiro/');
        
        return '<div class="lrp-not-affiliate">' .
               '<p>' . __('Você ainda não é um parceiro Lab Resumos.', 'lab-resumos-parceiros') . '</p>' .
               '<a href="' . esc_url($registration_url) . '" class="lrp-button">' . 
               __('Quero ser Parceiro', 'lab-resumos-parceiros') . '</a></div>';
    }

    /**
     * Mensagem de status pendente
     *
     * @param LRP_Affiliate $affiliate
     * @return string
     */
    private function get_pending_message($affiliate) {
        $status = $affiliate->get_status();
        
        $messages = [
            'pending'  => __('Seu cadastro está em análise. Você será notificado por email quando for aprovado.', 'lab-resumos-parceiros'),
            'inactive' => __('Sua conta de parceiro está inativa. Entre em contato conosco.', 'lab-resumos-parceiros'),
            'rejected' => __('Infelizmente seu cadastro não foi aprovado.', 'lab-resumos-parceiros'),
        ];
        
        $message = $messages[$status] ?? $messages['pending'];
        $icon = $status === 'rejected' ? '❌' : '⏳';
        
        return '<div class="lrp-status-message lrp-status-' . esc_attr($status) . '">' .
               '<span class="lrp-icon">' . $icon . '</span>' .
               '<p>' . esc_html($message) . '</p></div>';
    }
}

