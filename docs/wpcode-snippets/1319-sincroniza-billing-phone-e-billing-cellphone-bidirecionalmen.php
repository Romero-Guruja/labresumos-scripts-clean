<?php
/**
 * WPCode snippet #1319 — Sincroniza billing_phone e billing_cellphone bidirecionalmente
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
/**
 * Sincroniza billing_phone e billing_cellphone bidirecionalmente
 * Resolve incompatibilidade entre Brazilian Market e Pagar.me
 */

// 1. BACKEND: Garantir sincronização ao salvar o pedido
add_action('woocommerce_checkout_update_order_meta', function($order_id) {
    $order = wc_get_order($order_id);
    
    $phone = $order->get_billing_phone();
    $cellphone = $order->get_meta('_billing_cellphone');
    
    $updated = false;
    
    // Se phone está vazio mas cellphone tem valor
    if (empty($phone) && !empty($cellphone)) {
        $order->set_billing_phone($cellphone);
        $updated = true;
    }
    // Se cellphone está vazio mas phone tem valor
    elseif (empty($cellphone) && !empty($phone)) {
        $order->update_meta_data('_billing_cellphone', $phone);
        $updated = true;
    }
    
    if ($updated) {
        $order->save();
    }
}, 5);

// 2. FRONTEND: Sincronizar campos em tempo real no checkout
add_action('wp_footer', function() {
    if (!is_checkout()) return;
    ?>
    <script>
    jQuery(function($) {
        var $phone = $('#billing_phone');
        var $cellphone = $('#billing_cellphone');
        
        // Se um dos campos não existir, não faz nada
        if (!$phone.length || !$cellphone.length) return;
        
        var syncing = false;
        
        // Função para formatar/limpar o telefone (opcional)
        function syncPhone(source, target) {
            if (syncing) return;
            syncing = true;
            
            var value = source.val();
            if (value && !target.val()) {
                target.val(value).trigger('change');
            }
            
            syncing = false;
        }
        
        // Sincronizar quando sair do campo (blur)
        $phone.on('blur', function() {
            syncPhone($phone, $cellphone);
        });
        
        $cellphone.on('blur', function() {
            syncPhone($cellphone, $phone);
        });
        
        // Também sincronizar no submit do checkout
        $('form.checkout').on('checkout_place_order', function() {
            if ($phone.val() && !$cellphone.val()) {
                $cellphone.val($phone.val());
            } else if ($cellphone.val() && !$phone.val()) {
                $phone.val($cellphone.val());
            }
        });
    });
    </script>
    <?php
});

// 3. ADMIN: Sincronizar também ao editar pedido manualmente
add_action('woocommerce_process_shop_order_meta', function($order_id) {
    $order = wc_get_order($order_id);
    
    $phone = $order->get_billing_phone();
    $cellphone = $order->get_meta('_billing_cellphone');
    
    if (empty($phone) && !empty($cellphone)) {
        $order->set_billing_phone($cellphone);
        $order->save();
    } elseif (empty($cellphone) && !empty($phone)) {
        $order->update_meta_data('_billing_cellphone', $phone);
        $order->save();
    }
}, 50);