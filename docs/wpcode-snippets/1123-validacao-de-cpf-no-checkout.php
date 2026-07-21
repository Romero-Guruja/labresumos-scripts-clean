<?php
/**
 * WPCode snippet #1123 — Validação de CPF no Checkout
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
/**
 * Validação de CPF no Checkout - Lab Resumos
 * Validação server-side (PHP) + feedback visual (JS)
 */

// Função de validação do CPF
function lab_validar_cpf($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) return false;
    
    // CPFs com todos dígitos iguais são inválidos
    if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    
    // Validação dos dígitos verificadores
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    
    return true;
}

// Validação server-side no checkout
add_action('woocommerce_checkout_process', 'lab_validar_cpf_checkout');
function lab_validar_cpf_checkout() {
    $cpf = isset($_POST['billing_cpf']) ? preg_replace('/[^0-9]/', '', $_POST['billing_cpf']) : '';
    
    if (empty($cpf)) {
        wc_add_notice('Por favor, informe seu CPF.', 'error');
        return;
    }
    
    if (!lab_validar_cpf($cpf)) {
        wc_add_notice('O CPF informado é inválido. Verifique e tente novamente.', 'error');
    }
}

// Validação client-side com feedback visual
add_action('wp_footer', 'lab_validar_cpf_js_checkout');
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