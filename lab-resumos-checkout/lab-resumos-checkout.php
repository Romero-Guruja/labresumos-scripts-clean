<?php
/**
 * Plugin Name: Lab Resumos - Checkout
 * Description: Validação de CPF, reordenação do campo CPF no Fluid Checkout e sincronização
 *              billing_phone/billing_cellphone. Fase F3c do roadmap
 *              (docs/plugins-custom-analise-e-roadmap.md) — portado dos snippets WPCode
 *              #1123, #937 e #1319. A validação de CPF passou a usar LR_CPF::validate()
 *              (mu-plugin lab-resumos-core, F1) no lugar da cópia local do algoritmo —
 *              equivalência confirmada numa bateria de 14 CPFs válidos/inválidos, 0
 *              divergências. #937 e #1319 são cópia fiel, sem alteração de lógica.
 * Version: 1.0.0
 * Author: Lab Resumos
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// #1123 — Validação de CPF no Checkout (usa LR_CPF::validate() do core)
// ============================================================================
/**
 * Validação de CPF no Checkout - Lab Resumos
 * Validação server-side (PHP) + feedback visual (JS)
 */

// Validação server-side no checkout
add_action('woocommerce_checkout_process', 'lab_validar_cpf_checkout');
if (!function_exists('lab_validar_cpf_checkout')) {
    function lab_validar_cpf_checkout() {
        $cpf = isset($_POST['billing_cpf']) ? preg_replace('/[^0-9]/', '', $_POST['billing_cpf']) : '';

        if (empty($cpf)) {
            wc_add_notice('Por favor, informe seu CPF.', 'error');
            return;
        }

        if (!LR_CPF::validate($cpf)) {
            wc_add_notice('O CPF informado é inválido. Verifique e tente novamente.', 'error');
        }
    }
}

// Validação client-side com feedback visual
add_action('wp_footer', 'lab_validar_cpf_js_checkout');
if (!function_exists('lab_validar_cpf_js_checkout')) {
    function lab_validar_cpf_js_checkout() {
        if (!is_checkout()) return;
        ?>
        <script>
        jQuery(document).ready(function($) {
            function validarCPF(cpf) {
                cpf = cpf.replace(/[^\d]/g, '');
                if (cpf.length !== 11) return false;
                if (/^(\d)\1{10}$/.test(cpf)) return false;

                let soma = 0, resto;
                for (let i = 1; i <= 9; i++) soma += parseInt(cpf[i-1]) * (11 - i);
                resto = (soma * 10) % 11;
                if (resto === 10 || resto === 11) resto = 0;
                if (resto !== parseInt(cpf[9])) return false;

                soma = 0;
                for (let i = 1; i <= 10; i++) soma += parseInt(cpf[i-1]) * (12 - i);
                resto = (soma * 10) % 11;
                if (resto === 10 || resto === 11) resto = 0;
                return resto === parseInt(cpf[10]);
            }

            $(document).on('blur change', '#billing_cpf', function() {
                var cpf = $(this).val();
                var $field = $(this).closest('.form-row');

                $field.find('.cpf-error-msg').remove();
                $field.removeClass('woocommerce-invalid woocommerce-validated');

                if (cpf && cpf.replace(/[^\d]/g, '').length > 0) {
                    if (!validarCPF(cpf)) {
                        $field.addClass('woocommerce-invalid');
                        $(this).after('<span class="cpf-error-msg" style="color:#e2401c;font-size:13px;display:block;margin-top:5px;">CPF inválido. Verifique o número digitado.</span>');
                    } else {
                        $field.addClass('woocommerce-validated');
                    }
                }
            });
        });
        </script>
        <?php
    }
}

// ============================================================================
// #937 — Reordenar CPF no Fluid Checkout
// ============================================================================
/**
 * Solução para reordenar CPF no Fluid Checkout
 * Cria campo correspondente no shipping para permitir ordenação correta
 */
add_filter('woocommerce_checkout_fields', 'labresumos_fix_cpf_order_fluid_checkout', 1100);
if (!function_exists('labresumos_fix_cpf_order_fluid_checkout')) {
    function labresumos_fix_cpf_order_fluid_checkout($fields) {

        // Verificar se o campo billing_cpf existe
        if (!isset($fields['billing']['billing_cpf'])) {
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
}

// CSS para garantir que o campo shipping_cpf fique oculto
add_action('wp_head', 'labresumos_hide_shipping_cpf_css');
if (!function_exists('labresumos_hide_shipping_cpf_css')) {
    function labresumos_hide_shipping_cpf_css() {
        if (is_checkout()) {
            echo '<style>#shipping_cpf_field { display: none !important; }</style>';
        }
    }
}

// ============================================================================
// #1319 — Sincroniza billing_phone e billing_cellphone bidirecionalmente
// ============================================================================
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
