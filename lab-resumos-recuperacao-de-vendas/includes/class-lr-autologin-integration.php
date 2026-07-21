<?php
/**
 * Classe de Integração com Autologin
 *
 * @package LR_Recuperacao_Vendas
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LR_Autologin_Integration
 * Integra com o sistema de autologin existente (snippet WPCode)
 */
class LR_Autologin_Integration {

    /**
     * Construtor
     */
    public function __construct() {
        // Hooks adicionais se necessário
    }

    /**
     * Verifica se sistema de autologin está disponível
     * @return bool
     */
    public function is_available() {
        return function_exists('lr_get_payment_link_for_order');
    }

    /**
     * Gera link de autologin para pedido
     * @param int $order_id
     * @param array $options
     * @return string|WP_Error
     */
    public function generate_autologin_url($order_id, $options = []) {
        $defaults = [
            'max_uses' => 0,        // 0 = ilimitado
            'expiry_hours' => 72,   // 3 dias
        ];

        $options = wp_parse_args($options, $defaults);

        // Verificar se função existe
        if (function_exists('lr_get_payment_link_for_order')) {
            return lr_get_payment_link_for_order($order_id, $options);
        }

        // Fallback: função alternativa
        if (function_exists('lr_get_autologin_url')) {
            $order = wc_get_order($order_id);
            if ($order) {
                return lr_get_autologin_url($order->get_user_id(), $order_id, $options);
            }
        }

        return new WP_Error(
            'autologin_not_available',
            __('Sistema de autologin não disponível. Verifique se o snippet WPCode está ativo.', 'lr-recuperacao-vendas')
        );
    }

    /**
     * Obtém template de mensagem WhatsApp
     * @return string
     */
    public function get_whatsapp_template() {
        // Sempre usa o template do código para evitar problemas de versão desatualizada no banco
        return $this->get_default_template();
    }

    /**
     * Retorna template padrão (sem link)
     * @return string
     */
    private function get_default_template() {
        $template = <<<EOT
Oi, {nome}! Tudo bem?

Aqui é da equipe do Lab Resumos.

Vi que houve uma tentativa de compra no seu nome (pedido #{pedido}), mas a operadora de pagamento recusou a transação.

Isso é bem comum e pode acontecer por vários motivos - não significa que tenha nada de errado com seu cartão ou cadastro.

Quer que a gente tente processar o pagamento novamente? Basta me confirmar aqui.

Qualquer dúvida, estou à disposição!

Abraço,
Equipe Lab Resumos
EOT;
        return $template;
    }

    /**
     * Retorna template com link
     * @return string
     */
    private function get_template_with_link() {
        $template = <<<EOT
Oi, {nome}! Tudo bem?

Aqui é da equipe do Lab Resumos.

Vi que houve uma tentativa de compra no seu nome (pedido #{pedido}), mas a operadora de pagamento recusou a transação.

Isso é bem comum e pode acontecer por vários motivos - não significa que tenha nada de errado com seu cartão ou cadastro.

Quer que a gente tente processar o pagamento novamente? Basta me confirmar aqui.

Se preferir, também pode tentar por este link:
{link}

Qualquer dúvida, estou à disposição!

Abraço,
Equipe Lab Resumos
EOT;
        return $template;
    }

    /**
     * Formata mensagem WhatsApp com dados do pedido
     * @param WC_Order $order
     * @param string $autologin_url
     * @return string
     */
    public function format_whatsapp_message($order, $autologin_url = '') {
        // Usar template com ou sem link dependendo se o link foi fornecido
        if (!empty($autologin_url)) {
            $template = $this->get_template_with_link();
        } else {
            $template = $this->get_default_template();
        }

        // Obter produtos
        $products = [];
        foreach ($order->get_items() as $item) {
            $products[] = $item->get_name();
        }

        // Formatar valor sem HTML entities
        $valor_raw = $order->get_total();
        $valor_formatted = 'R$ ' . number_format($valor_raw, 2, ',', '.');

        // Substituir variáveis
        $replacements = [
            '{nome}' => $order->get_billing_first_name(),
            '{nome_completo}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            '{pedido}' => $order->get_id(),
            '{valor}' => $valor_formatted,
            '{produtos}' => implode(', ', $products),
            '{link}' => $autologin_url,
            '{email}' => $order->get_billing_email(),
        ];

        $message = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        // Aplicar filtro
        return apply_filters('lr_recovery_whatsapp_message', $message, $order, null);
    }

    /**
     * Gera URL do WhatsApp com mensagem
     * @param WC_Order $order
     * @param string $autologin_url
     * @return string
     */
    public function generate_whatsapp_url($order, $autologin_url = '') {
        // Obter telefone
        $phone = $order->get_meta('billing_cellphone') ?: $order->get_billing_phone();
        $phone_clean = $this->format_phone_for_whatsapp($phone);

        // Verificar se já existe um link salvo para este pedido
        if (empty($autologin_url)) {
            $autologin_url = $this->get_saved_autologin_url($order->get_id());
        }

        // Formatar mensagem
        $message = $this->format_whatsapp_message($order, $autologin_url);

        // Encode para URL do WhatsApp
        // Primeiro normaliza as quebras de linha para \n
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        
        // Encode a mensagem
        $encoded = rawurlencode($message);

        return 'https://wa.me/' . $phone_clean . '?text=' . $encoded;
    }

    /**
     * Salva URL de autologin para o pedido
     * @param int $order_id
     * @param string $url
     */
    public function save_autologin_url($order_id, $url) {
        update_post_meta($order_id, '_lr_recovery_autologin_url', $url);
        update_post_meta($order_id, '_lr_recovery_autologin_generated_at', current_time('mysql'));
        
        // Também salvar no caso se existir
        $case = lr_recovery()->manager->get_case_by_order($order_id);
        if ($case) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'lr_recovery_cases',
                ['notes' => $url],
                ['id' => $case->id],
                ['%s'],
                ['%d']
            );
        }
    }

    /**
     * Obtém URL de autologin salva para o pedido
     * @param int $order_id
     * @return string
     */
    public function get_saved_autologin_url($order_id) {
        return get_post_meta($order_id, '_lr_recovery_autologin_url', true) ?: '';
    }

    /**
     * Verifica se já existe um link de autologin para o pedido
     * @param int $order_id
     * @return bool
     */
    public function has_autologin_url($order_id) {
        return !empty($this->get_saved_autologin_url($order_id));
    }

    /**
     * Formata número de telefone para WhatsApp
     * @param string $phone
     * @return string
     */
    public function format_phone_for_whatsapp($phone) {
        // Remover tudo que não é número
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);

        // Se tem 11 dígitos (DDD + número) e não começa com 55, adicionar
        if (strlen($phone_clean) === 11 && substr($phone_clean, 0, 2) !== '55') {
            $phone_clean = '55' . $phone_clean;
        }

        // Se tem 10 dígitos (DDD + número antigo), adicionar 55
        if (strlen($phone_clean) === 10) {
            $phone_clean = '55' . $phone_clean;
        }

        return $phone_clean;
    }

    /**
     * AJAX: Gerar link de autologin
     */
    public function ajax_generate_autologin() {
        check_ajax_referer('lr_recovery_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permissão negada', 'lr-recuperacao-vendas')]);
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(['message' => __('ID do pedido inválido', 'lr-recuperacao-vendas')]);
        }

        $url = $this->generate_autologin_url($order_id, [
            'max_uses' => 0,
            'expiry_hours' => 72,
        ]);

        if (is_wp_error($url)) {
            wp_send_json_error(['message' => $url->get_error_message()]);
        }

        // Salvar o link gerado
        $this->save_autologin_url($order_id, $url);

        // Gerar também URL do WhatsApp (com o link)
        $order = wc_get_order($order_id);
        $whatsapp_url = '';
        
        if ($order) {
            $whatsapp_url = $this->generate_whatsapp_url($order, $url);
        }

        // Registrar log se houver case_id
        $case_id = isset($_POST['case_id']) ? absint($_POST['case_id']) : 0;
        if ($case_id) {
            lr_recovery()->manager->add_log(
                $case_id,
                get_current_user_id(),
                'autologin_generated',
                __('Link de autologin gerado', 'lr-recuperacao-vendas')
            );
        }

        wp_send_json_success([
            'url' => $url,
            'whatsapp_url' => $whatsapp_url,
        ]);
    }
}
