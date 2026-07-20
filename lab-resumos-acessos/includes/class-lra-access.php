<?php
/**
 * API publica de concessao de acesso.
 *
 * Ponto de entrada unico para qualquer consumidor (admin, plugin de parceiros,
 * recuperacao de vendas, brindes, suporte, etc.).
 *
 * Exemplo:
 *   $result = LRA_Access::grant([
 *       'email'       => 'fulano@exemplo.com',
 *       'cpf'         => '111.444.777-35',
 *       'nome'        => 'Fulano de Tal',
 *       'product_ids' => [1024],
 *       'context'     => 'affiliate_courtesy',
 *   ]);
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Access
 */
class LRA_Access {

    /**
     * Concede acesso de cortesia.
     *
     * @param array $args {
     *     @type string $email
     *     @type string $cpf
     *     @type string $nome
     *     @type int[]  $product_ids
     *     @type string $context
     *     @type bool   $send_magic   Gera magic link (default true).
     *     @type int    $granted_by   ID do admin que concedeu.
     * }
     * @return array|WP_Error {
     *     @type string   $status          'granted'.
     *     @type int      $user_id
     *     @type int      $order_id
     *     @type bool     $created_user
     *     @type string|null $magic_login_url
     * }
     */
    public static function grant($args) {
        $args = wp_parse_args($args, [
            'email'       => '',
            'cpf'         => '',
            'nome'        => '',
            'product_ids' => [],
            'context'     => 'manual',
            'send_magic'  => true,
            'granted_by'  => get_current_user_id(),
        ]);

        $email       = sanitize_email($args['email']);
        $cpf         = preg_replace('/\D/', '', (string) $args['cpf']);
        $product_ids = array_filter(array_map('absint', (array) $args['product_ids']));
        $context     = sanitize_key($args['context']);

        if (!is_email($email)) {
            return new WP_Error('lra_invalid_email', __('Email invalido.', 'lab-resumos-acessos'));
        }

        if (strlen($cpf) !== 11 || !LRA_Identity::validate_cpf($cpf)) {
            return new WP_Error('lra_invalid_cpf', __('CPF invalido.', 'lab-resumos-acessos'));
        }

        if (empty($product_ids)) {
            return new WP_Error('lra_no_products', __('Selecione ao menos um curso/produto.', 'lab-resumos-acessos'));
        }

        // 1. Identidade.
        $resolved = LRA_Identity::resolve($email, $cpf, $args['nome'], $context);
        if (is_wp_error($resolved)) {
            return $resolved; // inclui conflito (lra_cpf_email_conflict).
        }
        $user_id = $resolved['user_id'];

        // 2. Pedido de cortesia (dispara matricula + cpf-sender + DRM).
        $order_id = LRA_Order::create_courtesy($user_id, $product_ids, $context, $args['granted_by']);
        if (is_wp_error($order_id)) {
            return $order_id;
        }

        // 3. Onboarding (magic link).
        $magic = null;
        if ($args['send_magic'] || $resolved['created']) {
            $link = LRA_Onboarding::magic_link($user_id, $order_id);
            $magic = is_wp_error($link) ? null : $link;
        }

        do_action('lra_access_granted', $user_id, $order_id, $context, $product_ids);

        return [
            'status'          => 'granted',
            'user_id'         => $user_id,
            'order_id'        => $order_id,
            'created_user'    => $resolved['created'],
            'magic_login_url' => $magic,
        ];
    }

    /**
     * Revoga um acesso de cortesia cancelando o pedido
     * (o Edwiser desmatricula ao cancelar/reembolsar).
     *
     * @param int $order_id
     * @return true|WP_Error
     */
    public static function revoke($order_id) {
        $order = wc_get_order(absint($order_id));
        if (!$order) {
            return new WP_Error('lra_no_order', __('Pedido nao encontrado.', 'lab-resumos-acessos'));
        }

        if (!$order->get_meta('_lra_courtesy')) {
            return new WP_Error('lra_not_courtesy', __('Este pedido nao e um acesso de cortesia.', 'lab-resumos-acessos'));
        }

        $order->update_status('cancelled', __('Acesso de cortesia revogado (LRA).', 'lab-resumos-acessos'));

        do_action('lra_access_revoked', $order->get_customer_id(), $order_id);

        return true;
    }

    /**
     * Lista pedidos de cortesia (compativel com HPOS e modelo legado).
     *
     * @param int $limit
     * @return int[] IDs de pedidos.
     */
    public static function list_courtesy_order_ids($limit = 50) {
        global $wpdb;

        $limit = absint($limit);

        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
            && method_exists('\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            $table = $wpdb->prefix . 'wc_orders_meta';
            $ids   = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT order_id FROM {$table} WHERE meta_key = '_lra_courtesy' AND meta_value = '1' ORDER BY order_id DESC LIMIT %d",
                    $limit
                )
            );
        } else {
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_lra_courtesy' AND meta_value = '1' ORDER BY post_id DESC LIMIT %d",
                    $limit
                )
            );
        }

        return array_map('intval', (array) $ids);
    }
}
