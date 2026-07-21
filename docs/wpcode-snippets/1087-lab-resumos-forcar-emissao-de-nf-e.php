<?php
/**
 * WPCode snippet #1087 — Lab Resumos - Forçar Emissão de NF-e
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */

/**
 * Plugin Name: Lab Resumos - NF-e Auto em Pedido Concluído
 * Description: Emite NF-e automaticamente quando pedido muda para Concluído
 * Version: 1.0.0
 * Author: Lab Resumos
 */

if (!defined('ABSPATH')) exit;

/**
 * Emite NF-e automaticamente quando pedido muda para "Concluído"
 * Usa a mesma função que o botão "Emitir Nota Fiscal" do Webmania
 */
add_action('woocommerce_order_status_completed', 'labresumos_emitir_nfe_auto', 20, 1);

function labresumos_emitir_nfe_auto($order_id) {
    
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    // Verifica se já tem NF-e emitida (evita duplicação)
    $nfe_status = $order->get_meta('_nfe_status');
    if (!empty($nfe_status) && $nfe_status === 'aprovado') {
        $order->add_order_note('[Lab Resumos NF-e] NF-e já emitida anteriormente. Ignorando.');
        return;
    }
    
    // Verifica se a classe do Webmania existe
    if (!class_exists('WooCommerceNFeIssue')) {
        $order->add_order_note('[Lab Resumos NF-e] Erro: Plugin Webmania não encontrado.');
        return;
    }
    
    // Log início
    $order->add_order_note('[Lab Resumos NF-e] Iniciando emissão automática de NF-e...');
    
    // Chama a mesma função que o botão "Emitir Nota Fiscal" usa
    $nf = new WooCommerceNFeIssue;
    $nf->send(array($order_id), true);
    
    // Log conclusão
    $order->add_order_note('[Lab Resumos NF-e] Solicitação de emissão enviada ao Webmania.');
}