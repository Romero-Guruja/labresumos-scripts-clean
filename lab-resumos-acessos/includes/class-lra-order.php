<?php
/**
 * Criacao do pedido de cortesia.
 *
 * Cria um pedido WooCommerce de valor zero, com o CPF no billing (para o DRM),
 * e o marca como completed -> dispara Edwiser (matricula no Moodle) e cpf-sender.
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Order
 */
class LRA_Order {

    /**
     * Cria um pedido de cortesia concluido.
     *
     * @param int    $user_id
     * @param int[]  $product_ids
     * @param string $context
     * @param int    $granted_by
     * @return int|WP_Error ID do pedido ou WP_Error.
     */
    public static function create_courtesy($user_id, $product_ids, $context = 'manual', $granted_by = 0) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new WP_Error('lra_no_user', __('Usuario nao encontrado.', 'lab-resumos-acessos'));
        }

        $product_ids = array_filter(array_map('absint', (array) $product_ids));
        if (empty($product_ids)) {
            return new WP_Error('lra_no_products', __('Nenhum produto informado.', 'lab-resumos-acessos'));
        }

        $cpf = preg_replace('/\D/', '', (string) get_user_meta($user_id, 'billing_cpf', true));

        $order = wc_create_order(['customer_id' => $user_id]);
        if (is_wp_error($order)) {
            return $order;
        }

        // Adiciona produtos ja zerados (validado: zerar via args evita total residual).
        $added = 0;
        foreach ($product_ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product) {
                continue;
            }
            $order->add_product($product, 1, ['subtotal' => 0, 'total' => 0]);
            $added++;
        }

        if (!$added) {
            $order->delete(true);
            return new WP_Error('lra_no_valid_products', __('Nenhum produto valido encontrado.', 'lab-resumos-acessos'));
        }

        // Dados de billing (CPF e o que o cpf-sender/DRM consome).
        $order->set_billing_email($user->user_email);

        $first = get_user_meta($user_id, 'first_name', true);
        $last  = get_user_meta($user_id, 'last_name', true);
        $order->set_billing_first_name($first ?: $user->display_name);
        if ($last) {
            $order->set_billing_last_name($last);
        }

        $order->update_meta_data('billing_cpf', $cpf);
        $order->update_meta_data('_billing_cpf', $cpf);

        // Flags de cortesia.
        $order->update_meta_data('_lra_courtesy', '1');
        $order->update_meta_data('_lra_context', sanitize_key($context));
        $order->update_meta_data('_lra_granted_by', absint($granted_by));

        // Seguranca: marca como sem comissao (regra reaproveitada do plugin de parceiros).
        $order->update_meta_data('_lrp_no_commission_reason', 'courtesy');

        $order->calculate_totals();
        $order->set_total(0);
        $order->add_order_note(sprintf(
            /* translators: %s: contexto */
            __('Acesso de cortesia (%s) gerado pelo Lab Resumos - Acessos.', 'lab-resumos-acessos'),
            $context
        ));
        $order->save();

        // Conclui -> dispara Edwiser (matricula) + eb_created_user -> cpf-sender -> DRM.
        $order->update_status('completed', __('Acesso de cortesia (LRA)', 'lab-resumos-acessos'));

        lra_log('Pedido de cortesia criado', [
            'order_id'    => $order->get_id(),
            'user_id'     => $user_id,
            'products'    => $product_ids,
            'context'     => $context,
        ]);

        return $order->get_id();
    }
}
