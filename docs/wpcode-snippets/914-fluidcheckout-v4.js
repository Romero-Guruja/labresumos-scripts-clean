/* WPCode snippet #914 — FluidCheckout v4
 * location: site_wide_header | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 */
jQuery(function($) {
    $('form.checkout, form.woocommerce-checkout').on('submit checkout_place_order', function(e) {
        var $form = $(this);
        
        // Remove máscara do CPF
        var $cpf = $('[name="billing_cpf"]');
        if ($cpf.length && $cpf.val()) {
            $cpf.val($cpf.val().replace(/\D/g, ''));
        }
        
        // Remove máscara do CNPJ
        var $cnpj = $('[name="billing_cnpj"]');
        if ($cnpj.length && $cnpj.val()) {
            $cnpj.val($cnpj.val().replace(/\D/g, ''));
        }
        
        // Garante billing_phone (copia de qualquer campo de telefone)
        if (!$('[name="billing_phone"]').val()) {
            var phone = $('[name="billing_cellphone"]').val() || 
                        $('[name="shipping_phone"]').val() || 
                        $('input[type="tel"]').first().val() || '';
            
            if (!$('[name="billing_phone"]').length) {
                $form.append('<input type="hidden" name="billing_phone" value="' + phone.replace(/\D/g, '') + '">');
            } else {
                $('[name="billing_phone"]').val(phone);
            }
        }
    });
});