<?php
/**
 * WPCode snippet #953 — Webmania - Modalidade Frete Sem Transporte
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
/**
 * Define modalidade de frete padrão como "Sem Ocorrência de Transporte" (9)
 * para todas as emissões de NF-e via Webmania
 */
add_filter('nfe_order_data', function($data, $order_id) {
    // Define modalidade_frete = 9 (Sem Ocorrência de Transporte)
    if (isset($data['pedido'])) {
        $data['pedido']['modalidade_frete'] = 9;
    } else {
        $data['pedido'] = array('modalidade_frete' => 9);
    }
    
    // Remove dados de transporte/entrega que não são necessários para produtos digitais
    if (isset($data['transporte'])) {
        unset($data['transporte']);
    }
    
    return $data;
}, 10, 2);