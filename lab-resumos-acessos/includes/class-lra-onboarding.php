<?php
/**
 * Onboarding / SSO.
 *
 * Gera o magic link de autologin (sistema existente). Ao logar no WordPress,
 * o Edwiser SSO leva o usuario logado ao Moodle.
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Onboarding
 */
class LRA_Onboarding {

    /**
     * Gera o link de autologin para um pedido/usuario.
     *
     * @param int   $user_id
     * @param int   $order_id
     * @param array $options
     * @return string|WP_Error
     */
    public static function magic_link($user_id, $order_id, $options = []) {
        $options = wp_parse_args($options, [
            'expiry_hours' => 72,
            'max_uses'     => 0,
        ]);

        if (function_exists('lr_get_autologin_url')) {
            return lr_get_autologin_url($user_id, $order_id, $options);
        }

        if (function_exists('lr_get_payment_link_for_order')) {
            return lr_get_payment_link_for_order($order_id, $options);
        }

        return new WP_Error(
            'lra_no_autologin',
            __('Sistema de autologin indisponivel (verifique o snippet/funcao lr_get_autologin_url).', 'lab-resumos-acessos')
        );
    }
}
