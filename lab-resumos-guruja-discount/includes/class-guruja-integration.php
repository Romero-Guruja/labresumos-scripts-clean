<?php
/**
 * Classe de integração com a API Guruja
 * 
 * @package Lab_Resumos_Guruja
 */

defined('ABSPATH') || exit;

class LRG_Integration {

    /**
     * Chave da sessão para armazenar descontos
     */
    const SESSION_KEY = 'lrg_guruja_descontos';

    /**
     * Construtor
     */
    public function __construct() {
        // Aplica descontos no carrinho
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_discounts'], 20);
        
        // Limpa descontos quando carrinho é esvaziado
        add_action('woocommerce_cart_emptied', [$this, 'clear_discounts']);
        
        // Limpa descontos após pedido finalizado
        add_action('woocommerce_thankyou', [$this, 'clear_discounts']);
        
        // Adiciona metadados ao pedido
        add_action('woocommerce_checkout_create_order', [$this, 'add_order_meta'], 10, 2);
        
        // Exibe desconto no admin do pedido
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_order_meta']);
    }

    /**
     * Verifica se a integração está ativa
     */
    public function is_enabled() {
        return get_option('lrg_enabled', 'yes') === 'yes';
    }

    /**
     * Retorna URL da API
     */
    public function get_api_url() {
        return get_option('lrg_api_url', '');
    }

    /**
     * Retorna token da API
     */
    public function get_api_token() {
        return get_option('lrg_api_token', '');
    }

    /**
     * Retorna timeout da API
     */
    public function get_api_timeout() {
        return (int) get_option('lrg_api_timeout', 10);
    }

    /**
     * Verifica se modo debug está ativo
     */
    public function is_debug_mode() {
        return get_option('lrg_debug_mode', 'no') === 'yes';
    }

    /**
     * Loga mensagens quando debug está ativo
     */
    public function log($message, $data = null) {
        if (!$this->is_debug_mode()) {
            return;
        }

        $log_message = '[Lab Resumos Guruja] ' . $message;
        if (!is_null($data)) {
            $log_message .= ' | Data: ' . wp_json_encode($data);
        }

        if (function_exists('wc_get_logger')) {
            wc_get_logger()->debug($log_message, ['source' => 'lab-resumos-guruja']);
        } else {
            error_log($log_message);
        }
    }

    /**
     * Loga erros sempre (independente do debug mode)
     */
    public function log_error($message, $data = null) {
        $log_message = '[Lab Resumos Guruja] ERRO: ' . $message;
        if (!is_null($data)) {
            $log_message .= ' | Data: ' . wp_json_encode($data);
        }

        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error($log_message, ['source' => 'lab-resumos-guruja']);
        } else {
            error_log($log_message);
        }
    }

    /**
     * Prepara dados dos produtos do carrinho
     */
    public function get_cart_products() {
        $products = [];

        if (!WC()->cart) {
            return $products;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $product_id = $cart_item['product_id'];
            
            // Usa variation_id se for variação
            if (!empty($cart_item['variation_id'])) {
                $product_id = $cart_item['variation_id'];
            }

            $products[] = [
                'product_id' => $product_id,
                'sku' => $product->get_sku() ?: '',
                'valor' => (float) $product->get_price(),
                'quantidade' => $cart_item['quantity'],
            ];
        }

        return $products;
    }

    /**
     * Chama a API Guruja para verificar descontos
     */
    public function check_discounts($email, $cpf) {
        try {
            $this->log('Iniciando verificação de desconto', ['email' => $email, 'cpf' => $cpf]);

        if (!$this->is_enabled()) {
            $this->log('Integração desativada');
            return ['success' => false, 'message' => 'Integração desativada'];
        }

        $api_url = $this->get_api_url();
        $api_token = $this->get_api_token();

        if (empty($api_url) || empty($api_token)) {
            $this->log('API não configurada');
            return ['success' => false, 'message' => 'API não configurada'];
        }

        // Limpa CPF (remove pontos e traços)
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // Valida CPF básico (11 dígitos)
        if (strlen($cpf) !== 11) {
            $this->log('CPF inválido', ['cpf_length' => strlen($cpf)]);
            return ['success' => false, 'message' => 'CPF inválido'];
        }

        // Valida email
        if (!is_email($email)) {
            $this->log('Email inválido');
            return ['success' => false, 'message' => 'Email inválido'];
        }

        // Monta payload
        $payload = [
            'email' => sanitize_email($email),
            'cpf' => $cpf,
            'produtos' => $this->get_cart_products(),
        ];

        $this->log('Enviando request para API', $payload);

        // Faz requisição
        $response = wp_remote_post($api_url, [
            'timeout' => $this->get_api_timeout(),
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        // Verifica erro de conexão
        if (is_wp_error($response)) {
            $this->log('Erro de conexão', ['error' => $response->get_error_message()]);
            return [
                'success' => false,
                'message' => 'Erro de conexão: ' . $response->get_error_message(),
            ];
        }

        // Verifica código HTTP
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $this->log('Código HTTP inesperado', ['code' => $http_code]);
            return [
                'success' => false,
                'message' => 'Erro na API (código ' . $http_code . ')',
            ];
        }

        // Decodifica resposta
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('Erro ao decodificar JSON', ['body' => $body]);
            return [
                'success' => false,
                'message' => 'Resposta inválida da API',
            ];
        }

        $this->log('Resposta da API', $data);

        // Verifica se é elegível
        if (empty($data['elegivel']) || $data['elegivel'] !== true) {
            $this->clear_discounts();
            return [
                'success' => true,
                'elegivel' => false,
                'message' => 'Não elegível para desconto',
            ];
        }

        // Processa descontos
        $descontos = $data['descontos'] ?? [];
        if (empty($descontos)) {
            $this->clear_discounts();
            return [
                'success' => true,
                'elegivel' => false,
                'message' => 'Nenhum desconto disponível',
            ];
        }

        // Salva descontos na sessão
        $this->save_discounts($descontos, $email, $cpf);

        return [
            'success' => true,
            'elegivel' => true,
            'descontos' => $descontos,
            'message' => 'Desconto aplicado!',
        ];
        } catch (\Exception $e) {
            $this->log_error('Erro na verificação: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno'];
        }
    }

    /**
     * Salva descontos na sessão do WooCommerce
     */
    public function save_discounts($descontos, $email, $cpf) {
        if (!WC()->session) {
            return;
        }

        WC()->session->set(self::SESSION_KEY, [
            'descontos' => $descontos,
            'email' => $email,
            'cpf' => $cpf,
            'timestamp' => time(),
        ]);

        // Nova verificação elegível ⇒ limpa qualquer flag de rejeição anterior
        // (evita que a flag stale da sessão bloqueie o desconto quando o
        // cupom de afiliado não está mais no carrinho).
        if (class_exists('LRP_Guruja') && method_exists('LRP_Guruja', 'instance')) {
            $lrp_guruja = LRP_Guruja::instance();
            if (method_exists($lrp_guruja, 'clear_guruja_rejection')) {
                $lrp_guruja->clear_guruja_rejection();
                $this->log('Flag de rejeição Guruja limpa após nova verificação elegível');
            }
        }

        $this->log('Descontos salvos na sessão', $descontos);
    }

    /**
     * Recupera descontos da sessão
     */
    public function get_saved_discounts() {
        if (!WC()->session) {
            return null;
        }

        return WC()->session->get(self::SESSION_KEY);
    }

    /**
     * Limpa descontos da sessão
     */
    public function clear_discounts() {
        if (WC()->session) {
            WC()->session->set(self::SESSION_KEY, null);
            $this->log('Descontos limpos da sessão');
        }
    }

    /**
     * Aplica descontos no carrinho
     */
    public function apply_discounts($cart) {
        try {
            if (is_admin() && !defined('DOING_AJAX')) {
                return;
            }

            // Verifica se plugin de afiliados rejeitou Guruja em favor de cupom
            if (class_exists('LRP_Guruja') && LRP_Guruja::instance()->was_guruja_rejected_for_coupon()) {
                $this->log('Desconto Guruja ignorado - rejeitado em favor de cupom de afiliado');
                return;
            }

        $session_data = $this->get_saved_discounts();
        if (empty($session_data) || empty($session_data['descontos'])) {
            return;
        }

        // REMOVIDO: validação de email/CPF que estava limpando o desconto
        
        $descontos = $session_data['descontos'];
        $total_desconto = 0;
        $desconto_detalhes = [];

        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            
            // Verifica também variation_id
            $variation_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;

            // Procura desconto para este produto
            foreach ($descontos as $desconto) {
                $desconto_product_id = (int) $desconto['product_id'];
                
                // Verifica se o desconto é para este produto ou variação
                if ($desconto_product_id !== $product_id && $desconto_product_id !== $variation_id) {
                    continue;
                }

                $tipo = $desconto['tipo'] ?? 'percentual';
                $valor = (float) ($desconto['valor'] ?? 0);
                $preco_produto = (float) $cart_item['data']->get_price();
                $quantidade = $cart_item['quantity'];

                if ($tipo === 'percentual') {
                    $desconto_item = ($preco_produto * $valor / 100) * $quantidade;
                } else {
                    // Desconto fixo por unidade
                    $desconto_item = $valor * $quantidade;
                }

                // Limita desconto ao valor do produto
                $valor_max = $preco_produto * $quantidade;
                $desconto_item = min($desconto_item, $valor_max);

                $total_desconto += $desconto_item;

                $desconto_detalhes[] = [
                    'produto' => $cart_item['data']->get_name(),
                    'desconto' => $desconto_item,
                ];

                $this->log('Desconto aplicado ao produto', [
                    'produto' => $cart_item['data']->get_name(),
                    'tipo' => $tipo,
                    'valor_config' => $valor,
                    'desconto_calculado' => $desconto_item,
                ]);

                break; // Só aplica um desconto por produto
            }
        }

        if ($total_desconto > 0) {
            $cart->add_fee(
                __('Desconto Aluno Guruja', 'lab-resumos-guruja'),
                -$total_desconto,
                false // Não aplicar taxa sobre desconto
            );

            $this->log('Desconto total aplicado', ['total' => $total_desconto]);
        }
        } catch (\Exception $e) {
            $this->log_error('Erro ao aplicar desconto: ' . $e->getMessage());
            // Falha silenciosa - checkout continua normal
            return;
        }
    }

    /**
     * Adiciona metadados ao pedido
     */
    public function add_order_meta($order, $data) {
        $session_data = $this->get_saved_discounts();
        if (empty($session_data)) {
            return;
        }

        $order->update_meta_data('_lrg_guruja_email', $session_data['email'] ?? '');
        $order->update_meta_data('_lrg_guruja_cpf', $session_data['cpf'] ?? '');
        $order->update_meta_data('_lrg_guruja_descontos', $session_data['descontos'] ?? []);

        $this->log('Metadados adicionados ao pedido', ['order_id' => $order->get_id()]);
    }

    /**
     * Exibe informações do desconto no admin
     */
    public function display_order_meta($order) {
        $email = $order->get_meta('_lrg_guruja_email');
        $cpf = $order->get_meta('_lrg_guruja_cpf');
        $descontos = $order->get_meta('_lrg_guruja_descontos');

        if (empty($email) && empty($cpf)) {
            return;
        }

        echo '<div class="order_data_column" style="margin-top: 20px; padding: 10px; background: #e7f3e7; border-left: 4px solid #46b450;">';
        echo '<h4 style="margin: 0 0 10px;">' . esc_html__('Desconto Guruja Aplicado', 'lab-resumos-guruja') . '</h4>';
        
        if ($email) {
            echo '<p><strong>Email:</strong> ' . esc_html($email) . '</p>';
        }
        if ($cpf) {
            echo '<p><strong>CPF:</strong> ' . esc_html($cpf) . '</p>';
        }
        if (!empty($descontos)) {
            echo '<p><strong>Descontos:</strong></p><ul>';
            foreach ($descontos as $d) {
                $tipo_label = $d['tipo'] === 'percentual' ? '%' : ' (fixo)';
                echo '<li>Produto #' . esc_html($d['product_id']) . ': ' . esc_html($d['valor']) . $tipo_label . '</li>';
            }
            echo '</ul>';
        }
        
        echo '</div>';
    }

    /**
     * Testa conexão com a API (para admin)
     */
    public function test_connection() {
        $api_url = $this->get_api_url();
        $api_token = $this->get_api_token();

        if (empty($api_url)) {
            return ['success' => false, 'message' => 'URL da API não configurada'];
        }

        if (empty($api_token)) {
            return ['success' => false, 'message' => 'Token não configurado'];
        }

        // Faz uma requisição de teste (pode ser adaptado conforme endpoint de health check)
        $response = wp_remote_post($api_url, [
            'timeout' => $this->get_api_timeout(),
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode([
                'email' => 'teste@labresumos.com.br',
                'cpf' => '00000000000',
                'produtos' => [],
            ]),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Erro: ' . $response->get_error_message(),
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        
        // Considera sucesso se receber qualquer resposta válida (200, 401, 422, etc.)
        // 401 = token inválido, 422 = dados inválidos, mas a API respondeu
        if ($http_code >= 200 && $http_code < 500) {
            return [
                'success' => true,
                'message' => 'Conexão OK (HTTP ' . $http_code . ')',
            ];
        }

        return [
            'success' => false,
            'message' => 'Erro HTTP ' . $http_code,
        ];
    }
}

// Instância global
function lrg_integration() {
    static $instance = null;
    if (is_null($instance)) {
        $instance = new LRG_Integration();
    }
    return $instance;
}

// Inicializa
lrg_integration();
