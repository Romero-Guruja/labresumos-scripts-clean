<?php
/**
 * Classe de handlers AJAX
 * 
 * @package Lab_Resumos_Guruja
 */

defined('ABSPATH') || exit;

class LRG_Ajax {

    /**
     * Construtor
     */
    public function __construct() {
        // AJAX para verificar desconto (logado e não logado)
        add_action('wp_ajax_lrg_check_discount', [$this, 'check_discount']);
        add_action('wp_ajax_nopriv_lrg_check_discount', [$this, 'check_discount']);

        // AJAX para limpar desconto
        add_action('wp_ajax_lrg_clear_discount', [$this, 'clear_discount']);
        add_action('wp_ajax_nopriv_lrg_clear_discount', [$this, 'clear_discount']);

        // AJAX para testar conexão (admin only)
        add_action('wp_ajax_lrg_test_connection', [$this, 'test_connection']);
    }

    /**
     * Verifica e aplica desconto
     */
    public function check_discount() {
        try {
            // Verifica nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'lrg_guruja_nonce')) {
                wp_send_json_error(['message' => 'Requisição inválida']);
            }

        $email = sanitize_email($_POST['email'] ?? '');
        $cpf = sanitize_text_field($_POST['cpf'] ?? '');

        if (empty($email) || empty($cpf)) {
            wp_send_json_error(['message' => 'Email e CPF são obrigatórios']);
        }

        // Chama a integração
        $result = lrg_integration()->check_discounts($email, $cpf);

        if ($result['success'] && !empty($result['elegivel'])) {
            // Recalcula totais do carrinho
            WC()->cart->calculate_totals();

            wp_send_json_success([
                'message' => $result['message'],
                'elegivel' => true,
                'descontos' => $result['descontos'] ?? [],
            ]);
        } elseif ($result['success'] && empty($result['elegivel'])) {
            wp_send_json_success([
                'message' => $result['message'],
                'elegivel' => false,
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'],
            ]);
        }
        } catch (\Exception $e) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('[Lab Resumos Guruja] ' . $e->getMessage(), ['source' => 'lab-resumos-guruja']);
            }
            // Retorna erro silencioso - não quebra nada
            wp_send_json_error(['message' => 'Erro interno']);
        }
    }

    /**
     * Limpa desconto aplicado
     */
    public function clear_discount() {
        try {
            // Verifica nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'lrg_guruja_nonce')) {
                wp_send_json_error(['message' => 'Requisição inválida']);
            }

            lrg_integration()->clear_discounts();
            WC()->cart->calculate_totals();

            wp_send_json_success(['message' => 'Desconto removido']);
        } catch (\Exception $e) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('[Lab Resumos Guruja] ' . $e->getMessage(), ['source' => 'lab-resumos-guruja']);
            }
            // Retorna erro silencioso - não quebra nada
            wp_send_json_success(['message' => 'Desconto removido']); // Retorna sucesso mesmo em erro para não quebrar
        }
    }

    /**
     * Testa conexão com a API (admin)
     */
    public function test_connection() {
        // Verifica permissão
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }

        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'lrg_test_connection')) {
            wp_send_json_error(['message' => 'Requisição inválida']);
        }

        $result = lrg_integration()->test_connection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}

// Inicializa
new LRG_Ajax();
