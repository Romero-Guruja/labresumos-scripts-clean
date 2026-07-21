/**
 * Lab Resumos Parceiros - Dashboard Scripts
 * Versão aprimorada com feedback visual
 */
(function($) {
    'use strict';
    
    // Toast notification system
    var LRP_Toast = {
        container: null,
        
        init: function() {
            if (!this.container) {
                this.container = $('<div class="lrp-toast-container"></div>');
                $('body').append(this.container);
            }
        },
        
        show: function(message, type) {
            this.init();
            
            type = type || 'success';
            var icon = type === 'success' ? '✓' : '✕';
            
            var $toast = $('<div class="lrp-toast lrp-toast-' + type + '">' +
                '<span class="lrp-toast-icon">' + icon + '</span>' +
                '<span class="lrp-toast-message">' + message + '</span>' +
            '</div>');
            
            this.container.append($toast);
            
            // Auto-remove after 3 seconds
            setTimeout(function() {
                $toast.addClass('lrp-toast-out');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            }, 3000);
        }
    };
    
    // Copy to clipboard with enhanced feedback
    $(document).on('click', '.lrp-copy-btn, .lrp-copy-btn-large', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var text = $btn.data('copy');
        
        // Try to get text from sibling input if not in data attribute
        if (!text) {
            var $input = $btn.siblings('input, .lrp-link-input-box').find('input');
            if (!$input.length) {
                $input = $btn.closest('.lrp-link-input-wrapper').find('input');
            }
            if ($input.length) {
                text = $input.val();
            }
        }
        
        if (!text) return;
        
        // Store original content
        var $icon = $btn.find('svg');
        var $text = $btn.find('span');
        var originalHtml = $btn.html();
        var isLargeBtn = $btn.hasClass('lrp-copy-btn-large');
        
        // Copy to clipboard
        copyToClipboard(text).then(function() {
            // Success feedback
            $btn.addClass('copied');
            
            if (isLargeBtn) {
                $btn.html(
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' +
                    '<span>' + lrp_dashboard.copied_text + '</span>'
                );
            } else {
                $btn.html(
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>'
                );
            }
            
            // Show toast notification
            LRP_Toast.show(lrp_dashboard.copied_text, 'success');
            
            // Restore original after 2 seconds
            setTimeout(function() {
                $btn.removeClass('copied');
                $btn.html(originalHtml);
            }, 2000);
            
        }).catch(function() {
            // Error feedback
            LRP_Toast.show(lrp_dashboard.error_text, 'error');
        });
    });
    
    // Copy to clipboard function with fallback
    function copyToClipboard(text) {
        return new Promise(function(resolve, reject) {
            // Modern API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text)
                    .then(resolve)
                    .catch(function() {
                        // Fallback on error
                        fallbackCopy(text, resolve, reject);
                    });
            } else {
                // Fallback for older browsers
                fallbackCopy(text, resolve, reject);
            }
        });
    }
    
    // Fallback copy method
    function fallbackCopy(text, resolve, reject) {
        try {
            var $temp = $('<textarea>');
            $temp.css({
                position: 'fixed',
                left: '-9999px',
                top: '0'
            });
            $('body').append($temp);
            $temp.val(text).select();
            
            var success = document.execCommand('copy');
            $temp.remove();
            
            if (success) {
                resolve();
            } else {
                reject();
            }
        } catch (err) {
            reject(err);
        }
    }
    
    // Select all text in input when clicking
    $(document).on('click', '.lrp-link-input', function() {
        $(this).select();
    });
    
    // Upload de NF (delegação para suportar múltiplos formulários)
    $(document).on('submit', '.lrp-invoice-form', function(e) {
        e.preventDefault();
        
        if (!confirm(lrp_dashboard.confirm_upload)) {
            return;
        }
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.text();
        var formData = new FormData(this);
        
        $btn.prop('disabled', true).html(
            '<svg class="lrp-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-dasharray="30 30" stroke-dashoffset="0"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></circle></svg>' +
            ' Enviando...'
        );
        
        $.ajax({
            url: lrp_dashboard.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    LRP_Toast.show(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    LRP_Toast.show(response.data.message || lrp_dashboard.upload_error, 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                LRP_Toast.show(lrp_dashboard.upload_error, 'error');
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Atualização de perfil
    $('#lrp-profile-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).html(
            '<svg class="lrp-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-dasharray="30 30" stroke-dashoffset="0"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></circle></svg>' +
            ' Salvando...'
        );
        
        $.ajax({
            url: lrp_dashboard.ajax_url,
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    LRP_Toast.show(response.data.message, 'success');
                } else {
                    LRP_Toast.show(response.data.message || 'Erro ao salvar', 'error');
                }
                $btn.prop('disabled', false).text(originalText);
            },
            error: function() {
                LRP_Toast.show('Erro ao salvar', 'error');
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Toggle campos PIX/Banco
    $('select[name="payment_method"]').on('change', function() {
        var method = $(this).val();
        
        if (method === 'pix') {
            $('.lrp-pix-fields').slideDown(200);
            $('.lrp-bank-fields').slideUp(200);
        } else {
            $('.lrp-pix-fields').slideUp(200);
            $('.lrp-bank-fields').slideDown(200);
        }
    });
    
    // Smooth scroll for help toggles
    $(document).on('click', '.lrp-help-toggle summary', function() {
        var $toggle = $(this).closest('.lrp-help-toggle');
        
        // Small delay to allow the toggle to open
        setTimeout(function() {
            if ($toggle.prop('open')) {
                $('html, body').animate({
                    scrollTop: $toggle.offset().top - 100
                }, 300);
            }
        }, 100);
    });
    
    // Initialize tooltips if available
    $(document).ready(function() {
        // Add hover effect to stat cards
        $('.lrp-card').on('mouseenter', function() {
            $(this).css('transform', 'translateY(-2px)');
        }).on('mouseleave', function() {
            $(this).css('transform', 'translateY(0)');
        });
    });
    
})(jQuery);
