/**
 * Lab Resumos - Desconto Guruja
 * Script do checkout
 */

(function($) {
    'use strict';

    var LRG_Checkout = {
        
        // Estado
        isChecking: false,
        lastEmail: '',
        lastCpf: '',
        debounceTimer: null,
        hasAppliedDiscount: false,

        /**
         * Inicializa
         */
        init: function() {
            this.bindEvents();
            this.createNoticeContainer();
        },

        /**
         * Cria container para notificações
         */
        createNoticeContainer: function() {
            if ($('#lrg-guruja-notice').length === 0) {
                var container = '<div id="lrg-guruja-notice" class="lrg-guruja-notice" style="display: none;"></div>';
                $('.woocommerce-billing-fields').after(container);
            }
        },

        /**
         * Vincula eventos
         */
        bindEvents: function() {
            var self = this;

            // Monitora mudanças nos campos de email e CPF
            $(document).on('change blur', '#billing_email, input[name="billing_email"], .woocommerce-billing-fields input[type="email"]', function() {
                self.scheduleCheck();
            });

            // Campo de CPF pode ter diferentes IDs dependendo do plugin
            $(document).on('change blur', '#billing_document, input[name="billing_document"], #billing_cpf, input[name="billing_cpf"]', function() {
                self.scheduleCheck();
            });

            // Também verifica quando o checkout é atualizado
            $(document.body).on('updated_checkout', function() {
                // Verifica se já temos desconto na sessão
                self.checkCurrentDiscount();
            });

            // Fluid Checkout compatibility
            $(document).on('change blur', '.fc-step--billing input', function() {
                self.scheduleCheck();
            });

            // Fallback: monitora qualquer input de email ou cpf
            $(document).on('change blur', 'input[type="email"], input[id*="cpf"], input[name*="cpf"]', function() {
                self.scheduleCheck();
            });
        },

        /**
         * Agenda verificação com debounce
         */
        scheduleCheck: function() {
            var self = this;

            // Limpa timer anterior
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }

            // Agenda nova verificação em 500ms
            this.debounceTimer = setTimeout(function() {
                self.checkDiscount();
            }, 500);
        },

        /**
         * Obtém valor do campo CPF
         */
        getCpfValue: function() {
            // billing_document é o campo usado pelo Fluid Checkout / plugins brasileiros
            var cpfField = $('#billing_document').val() || 
                           $('input[name="billing_document"]').val() ||
                           $('#billing_cpf').val() || 
                           $('input[name="billing_cpf"]').val() ||
                           $('#billing_cpf_field input').val();
            
            return cpfField || '';
        },

        /**
         * Obtém valor do campo email
         */
        getEmailValue: function() {
            var emailField = $('#billing_email').val() || 
                             $('input[name="billing_email"]').val() ||
                             $('input[type="email"]').first().val() ||
                             $('[data-input-name="billing_email"]').val();
            
            return emailField || '';
        },

        /**
         * Verifica desconto
         */
        checkDiscount: function() {
            var self = this;

            // DEBUG - remover depois
            console.log('[LRG Debug] checkDiscount chamado');
            console.log('[LRG Debug] Email encontrado:', this.getEmailValue());
            console.log('[LRG Debug] CPF encontrado:', this.getCpfValue());

            // Previne múltiplas requisições simultâneas
            if (this.isChecking) {
                return;
            }

            var email = this.getEmailValue();
            var cpf = this.getCpfValue();

            // Limpa formatação do CPF
            cpf = cpf.replace(/[^0-9]/g, '');

            // Validação básica
            if (!this.isValidEmail(email)) {
                return;
            }

            if (cpf.length !== 11) {
                return;
            }

            // Evita requisição duplicada
            if (email === this.lastEmail && cpf === this.lastCpf) {
                return;
            }

            // Se já tinha desconto e mudou os dados, limpa primeiro
            if (this.hasAppliedDiscount) {
                this.clearDiscount();
            }

            this.lastEmail = email;
            this.lastCpf = cpf;
            this.isChecking = true;

            // Mostra loading
            this.showNotice(lrgGuruja.i18n.checking, 'loading');

            // Faz requisição AJAX
            $.ajax({
                url: lrgGuruja.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lrg_check_discount',
                    nonce: lrgGuruja.nonce,
                    email: email,
                    cpf: cpf
                },
                success: function(response) {
                    try {
                        if (response.success && response.data.elegivel) {
                            self.showNotice(lrgGuruja.i18n.applied, 'success');
                            self.hasAppliedDiscount = true;
                            // Atualiza checkout para mostrar novo total
                            $(document.body).trigger('update_checkout');
                        } else if (response.success && !response.data.elegivel) {
                            // Silencioso - não mostra nada para não-alunos
                            self.hasAppliedDiscount = false;
                            self.hideNotice();
                        } else {
                            self.showNotice(response.data.message || lrgGuruja.i18n.error, 'error');
                        }
                    } catch (e) {
                        console.error('[Lab Resumos Guruja] Erro JS:', e);
                        self.hideNotice();
                    }
                },
                error: function(xhr, status, error) {
                    // Silencioso para o cliente - apenas loga
                    console.error('[Lab Resumos Guruja] Erro AJAX:', status, error);
                    self.hideNotice();
                },
                complete: function() {
                    self.isChecking = false;
                }
            });
        },

        /**
         * Verifica se já tem desconto atual
         */
        checkCurrentDiscount: function() {
            // Verifica se há fee de desconto Guruja na página
            var hasDiscount = $('.fee .amount:contains("-")').length > 0 &&
                              $('.fee th:contains("Guruja")').length > 0;
            
            if (hasDiscount && !$('#lrg-guruja-notice').is(':visible')) {
                this.showNotice(lrgGuruja.i18n.applied, 'success');
            }
        },

        /**
         * Valida email
         */
        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        /**
         * Mostra notificação
         */
        showNotice: function(message, type) {
            var $notice = $('#lrg-guruja-notice');
            
            // Remove classes anteriores
            $notice.removeClass('lrg-notice-loading lrg-notice-success lrg-notice-error lrg-notice-info');
            
            // Define conteúdo
            var icon = '';
            switch(type) {
                case 'loading':
                    icon = '<span class="lrg-spinner"></span>';
                    break;
                case 'success':
                    icon = '<span class="lrg-icon">✓</span>';
                    break;
                case 'error':
                    icon = '<span class="lrg-icon">✗</span>';
                    break;
                case 'info':
                    icon = '<span class="lrg-icon">ℹ</span>';
                    break;
            }

            $notice.html(icon + ' ' + message);
            $notice.addClass('lrg-notice-' + type);
            $notice.slideDown(200);

            // Auto-hide após 10 segundos (exceto loading)
            if (type !== 'loading') {
                setTimeout(function() {
                    if (!$notice.hasClass('lrg-notice-loading')) {
                        // Mantém visível se for sucesso
                        if (type !== 'success') {
                            $notice.slideUp(200);
                        }
                    }
                }, 10000);
            }
        },

        /**
         * Esconde notificação
         */
        hideNotice: function() {
            $('#lrg-guruja-notice').slideUp(200);
        },

        /**
         * Limpa desconto via AJAX
         */
        clearDiscount: function() {
            var self = this;
            
            $.ajax({
                url: lrgGuruja.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lrg_clear_discount',
                    nonce: lrgGuruja.nonce
                },
                success: function() {
                    try {
                        self.hasAppliedDiscount = false;
                        self.hideNotice();
                        $(document.body).trigger('update_checkout');
                    } catch (e) {
                        console.error('[Lab Resumos Guruja] Erro ao limpar desconto:', e);
                    }
                },
                error: function(xhr, status, error) {
                    // Silencioso - apenas loga
                    console.error('[Lab Resumos Guruja] Erro AJAX ao limpar desconto:', status, error);
                }
            });
        }
    };

    // Inicializa quando DOM estiver pronto
    $(document).ready(function() {
        LRG_Checkout.init();
    });

})(jQuery);
