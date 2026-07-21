<?php
/**
 * WPCode snippet #995 — Ver Thank You Page
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
/**
 * View Thank You Page @ Edit Order Admin - Versão corrigida
 */

// Adiciona a ação no dropdown de ações do pedido
add_filter( 'woocommerce_order_actions', 'lab_show_thank_you_page_order_admin_actions', 9999, 2 );

function lab_show_thank_you_page_order_admin_actions( $actions, $order ) {
    $actions['view_thankyou'] = 'Ver página de confirmação (Thank You)';
    return $actions;
}

// Redireciona para a thank you page com token especial
add_action( 'woocommerce_order_action_view_thankyou', 'lab_redirect_thank_you_page_order_admin_actions' );

function lab_redirect_thank_you_page_order_admin_actions( $order ) {
    $token = wp_create_nonce( 'view_thankyou_' . $order->get_id() );
    $url = add_query_arg( array(
        'admin_view' => $token,
        'oid' => $order->get_id()
    ), $order->get_checkout_order_received_url() );
    wp_safe_redirect( $url );
    exit;
}

// Intercepta ANTES do WooCommerce processar a página
add_action( 'template_redirect', 'lab_bypass_thankyou_login_check', 1 );

function lab_bypass_thankyou_login_check() {
    if ( ! isset( $_GET['admin_view'] ) || ! isset( $_GET['oid'] ) ) {
        return;
    }
    
    $order_id = absint( $_GET['oid'] );
    $token = sanitize_text_field( $_GET['admin_view'] );
    
    // Verifica se o nonce é válido (garante que veio do admin)
    if ( ! wp_verify_nonce( $token, 'view_thankyou_' . $order_id ) ) {
        return;
    }
    
    // Força o usuário atual a ser o dono do pedido
    $order = wc_get_order( $order_id );
    if ( $order ) {
        $customer_id = $order->get_customer_id();
        if ( $customer_id ) {
            wp_set_current_user( $customer_id );
        }
    }
}

// Desabilita verificação de email para visitantes quando admin_view está presente
add_filter( 'woocommerce_order_received_verify_known_shoppers', 'lab_disable_email_verify_for_admin', 999 );

function lab_disable_email_verify_for_admin( $verify ) {
    if ( isset( $_GET['admin_view'] ) && isset( $_GET['oid'] ) ) {
        $order_id = absint( $_GET['oid'] );
        $token = sanitize_text_field( $_GET['admin_view'] );
        if ( wp_verify_nonce( $token, 'view_thankyou_' . $order_id ) ) {
            return false;
        }
    }
    return $verify;
}