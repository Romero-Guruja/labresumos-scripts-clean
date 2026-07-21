<?php
/**
 * WPCode snippet #937 — Lab Resumos - Reordenar CPF Checkout
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
/**
 * Solução para reordenar CPF no Fluid Checkout
 * Cria campo correspondente no shipping para permitir ordenação correta
 */
add_filter( 'woocommerce_checkout_fields', 'labresumos_fix_cpf_order_fluid_checkout', 1100 );
function labresumos_fix_cpf_order_fluid_checkout( $fields ) {
    
    // Verificar se o campo billing_cpf existe
    if ( ! isset( $fields['billing']['billing_cpf'] ) ) {
        return $fields;
    }
    
    // Definir prioridade baixa para o CPF (aparecerá no início)
    $fields['billing']['billing_cpf']['priority'] = 5;
    $fields['billing']['billing_cpf']['class'] = array('form-row-wide');
    
    // Criar campo correspondente no shipping (oculto) para Fluid Checkout
    // respeitar a ordenação
    $fields['shipping']['shipping_cpf'] = array(
        'type'     => 'hidden',
        'priority' => 5,
        'required' => false,
        'class'    => array('hidden'),
    );
    
    return $fields;
}

// CSS para garantir que o campo shipping_cpf fique oculto
add_action( 'wp_head', 'labresumos_hide_shipping_cpf_css' );
function labresumos_hide_shipping_cpf_css() {
    if ( is_checkout() ) {
        echo '<style>#shipping_cpf_field { display: none !important; }</style>';
    }
}