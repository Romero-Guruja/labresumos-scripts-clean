/**
 * Lab Resumos - Recuperação de Vendas
 * Admin JavaScript
 */

(function($) {
    'use strict';

    // Objeto principal do plugin
    var LRRecovery = {
        
        /**
         * Inicialização
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind de eventos
         */
        bindEvents: function() {
            // Dashboard - Assumir caso
            $(document).on('click', '.lr-btn-assign', this.assignCase);

            // Case Detail - Checklist
            $(document).on('change', '.lr-checklist-checkbox', this.updateChecklist);

            // Case Detail - Gerar autologin
            $(document).on('click', '.lr-btn-generate-autologin', this.generateAutologin);

            // Case Detail - Copiar autologin
            $(document).on('click', '.lr-btn-copy-autologin', this.copyAutologinUrl);

            // Case Detail - Adicionar nota
            $(document).on('click', '.lr-btn-add-note', this.addNote);

            // Case Detail - Atualizar status
            $(document).on('change', '.lr-select-status', this.updateStatus);

            // Case Detail - Atribuir responsável
            $(document).on('change', '.lr-select-assigned', this.updateAssigned);

            // Case Detail - Marcar como resolvido
            $(document).on('click', '.lr-btn-resolve', this.resolveCase);

            // Case Detail - Marcar como abandonado
            $(document).on('click', '.lr-btn-abandon', this.abandonCase);

            // Case Detail - Completar pedido
            $(document).on('click', '.lr-btn-complete-order', this.completeOrder);

            // Copiar texto genérico
            $(document).on('click', '.lr-btn-copy', this.copyText);
        },

        /**
         * Assumir caso
         */
        assignCase: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var caseId = $btn.data('case-id');

            $btn.prop('disabled', true).text(lrRecovery.i18n.loading);

            $.ajax({
                url: lrRecovery.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lr_assign_case',
                    nonce: lrRecovery.nonce,
                    case_id: caseId
                },
                success: function(response) {
                    if (response.success) {
                        LRRecovery.showToast(response.data.message, 'success');
                        // Recarregar página para atualizar lista
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        LRRecovery.showToast(response.data.message, 'error');
                        $btn.prop('disabled', false).text('Assumir');
                    }
                },
                error: function() {
                    LRRecovery.showToast(lrRecovery.i18n.error, 'error');
                    $btn.prop('disabled', false).text('Assumir');
                }
            });
        },

        /**
         * Atualizar item do checklist
         */
        updateChecklist: function() {
            var $checkbox = $(this);
            var $item = $checkbox.closest('.lr-checklist-item');
            var caseId = $checkbox.data('case-id');
            var itemKey = $checkbox.data('item');
            var completed = $checkbox.is(':checked');

            $item.addClass('lr-loading');

            $.ajax({
                url: lrRecovery.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lr_update_checklist',
                    nonce: lrRecovery.nonce,
                    case_id: caseId,
                    item: itemKey,
                    completed: completed
                },
                success: function(response) {
                    $item.removeClass('lr-loading');
                    
                    if (response.success) {
                        if (completed) {
                            $item.addClass('completed');
                        } else {
                            $item.removeClass('completed');
                        }
                        LRRecovery.showToast(response.data.message, 'success');
                    } else {
                        // Reverter checkbox
                        $checkbox.prop('checked', !completed);
                        LRRecovery.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    $item.removeClass('lr-loading');
                    $checkbox.prop('checked', !completed);
                    LRRecovery.showToast(lrRecovery.i18n.error, 'error');
                }
            });
        },

        /**
         * Gerar link de autologin
         */
        generateAutologin: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var orderId = $btn.data('order-id');
            var caseId = $btn.data('case-id');
            var $section = $btn.closest('.lr-autologin-section');

            $btn.prop('disabled', true).text(lrRecovery.i18n.loading);

            $.ajax({
                url: lrRecovery.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lr_generate_autologin',
                    nonce: lrRecovery.nonce,
                    order_id: orderId,
                    case_id: caseId
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Gerar Novo Link');
                    
                    if (response.success) {
                        var $result = $section.find('.lr-autologin-result');
                        $result.find('.lr-autologin-url').val(response.data.url);
                        $result.find('.lr-btn-whatsapp-autologin').attr('href', response.data.whatsapp_url);
                        $result.slideDown();
                        
                        // Atualizar status
                        if (!$section.find('.lr-autologin-exists').length) {
                            $btn.before('<div class="lr-autologin-exists"><span class="lr-autologin-status">✅ Link ja gerado</span></div>');
                        }
                        
                        // Atualizar botão principal de WhatsApp
                        var $mainWhatsappBtn = $('.lr-btn-whatsapp-main');
                        if ($mainWhatsappBtn.length) {
                            $mainWhatsappBtn.attr('href', response.data.whatsapp_url);
                            if ($mainWhatsappBtn.text().indexOf('(com link)') === -1) {
                                $mainWhatsappBtn.text('WhatsApp (com link)');
                            }
                        }
                        
                        LRRecovery.showToast('Link gerado com sucesso!', 'success');
                    } else {
                        LRRecovery.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Gerar Link de Autologin');
                    LRRecovery.showToast(lrRecovery.i18n.error, 'error');
                }
            });
        },

        /**
         * Copiar URL de autologin
         */
        copyAutologinUrl: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $input = $btn.siblings('.lr-autologin-url');
            
            LRRecovery.copyToClipboard($input.val());
            LRRecovery.showToast(lrRecovery.i18n.copied, 'success');
        },

        /**
         * Adicionar nota
         */
        addNote: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var caseId = $btn.data('case-id');
            var $textarea = $('#lr-new-note');
            var note = $textarea.val().trim();

            if (!note) {
                LRRecovery.showToast('Digite uma observação', 'error');
                return;
            }

            $btn.prop('disabled', true).text(lrRecovery.i18n.loading);

            $.ajax({
                url: lrRecovery.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lr_add_note',
                    nonce: lrRecovery.nonce,
                    case_id: caseId,
                    note: note
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Adicionar Observação');
                    
                    if (response.success) {
                        $textarea.val('');
                        LRRecovery.showToast(response.data.message, 'success');
                        // Recarregar para mostrar nova nota
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        LRRecovery.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Adicionar Observação');
                    LRRecovery.showToast(lrRecovery.i18n.error, 'error');
                }
            });
        },

        /**
         * Atualizar status
         */
        updateStatus: function() {
            var $select = $(this);
            var caseId = $select.data('case-id');
            var status = $select.val();

            $select.prop('disabled', true);

            $.ajax({
                url: lrRecovery.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lr_update_case_status',
                    nonce: lrRecovery.nonce,
                    case_id: caseId,
                    status: status
                },
                success: function(response) {
                    $select.prop('disabled', false);
                    
                    if (response.success) {
                        LRRecovery.showToast(response.data.message, 'success');
                        // Atualizar badge no header
                        LRRecovery.updateStatusBadge(status);
                    } else {
                        LRRecovery.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    $select.prop('disabled', false);
                    LRRecovery.showToast(lrRecovery.i18n.error, 'error');
                }
            });
        },

        /**
         * Atualizar responsável
         */
        updateAssigned: function() {
            var $select = $(this);
            var caseId = $select.data('case-id');
            var userId = $select.val();

            if (!userId) return;

            $select.prop('disabled', true);

            $.ajax({
                url: lrRecovery.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lr_assign_case',
                    nonce: lrRecovery.nonce,
                    case_id: caseId
                },
                success: function(response) {
                    $select.prop('disabled', false);
                    
                    if (response.success) {
                        LRRecovery.showToast(response.data.message, 'success');
                    } else {
                        LRRecovery.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    $select.prop('disabled', false);
                    LRRecovery.showToast(lrRecovery.i18n.error, 'error');
                }
            });
        },

        /**
         * Resolver caso
         */
        resolveCase: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var caseId = $btn.data('case-id');

            $btn.prop('disabled', true).text(lrRecovery.i18n.loading);

            $.ajax({
                url: lrRecovery.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lr_update_case_status',
                    nonce: lrRecovery.nonce,
                    case_id: caseId,
                    status: 'resolvido'
                },
                success: function(response) {
                    if (response.success) {
                        LRRecovery.showToast('Caso marcado como resolvido!', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        $btn.prop('disabled', false).text('✓ Marcar como Resolvido');
                        LRRecovery.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('✓ Marcar como Resolvido');
                    LRRecovery.showToast(lrRecovery.i18n.error, 'error');
                }
            });
        },

        /**
         * Abandonar caso
         */
        abandonCase: function(e) {
            e.preventDefault();
            
            if (!confirm(lrRecovery.i18n.confirm_abandon)) {
                return;
            }

            var $btn = $(this);
            var caseId = $btn.data('case-id');

            $btn.prop('disabled', true).text(lrRecovery.i18n.loading);

            $.ajax({
                url: lrRecovery.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lr_update_case_status',
                    nonce: lrRecovery.nonce,
                    case_id: caseId,
                    status: 'abandonado'
                },
                success: function(response) {
                    if (response.success) {
                        LRRecovery.showToast('Caso marcado como abandonado', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        $btn.prop('disabled', false).text('❌ Marcar como Abandonado');
                        LRRecovery.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('❌ Marcar como Abandonado');
                    LRRecovery.showToast(lrRecovery.i18n.error, 'error');
                }
            });
        },

        /**
         * Completar pedido
         */
        completeOrder: function(e) {
            e.preventDefault();

            if (!confirm(lrRecovery.i18n.confirm_complete)) {
                return;
            }

            var $btn = $(this);
            var orderId = $btn.data('order-id');
            var caseId = $btn.data('case-id');

            $btn.prop('disabled', true).text(lrRecovery.i18n.loading);

            $.ajax({
                url: lrRecovery.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lr_complete_order',
                    nonce: lrRecovery.nonce,
                    order_id: orderId,
                    case_id: caseId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('✓ Já Concluído');
                        // Marcar checkbox correspondente
                        var $checkbox = $('.lr-checklist-checkbox[data-item="complete_order"]');
                        if (!$checkbox.is(':checked')) {
                            $checkbox.prop('checked', true);
                            $checkbox.closest('.lr-checklist-item').addClass('completed');
                        }
                        LRRecovery.showToast(response.data.message, 'success');
                    } else {
                        $btn.prop('disabled', false).text('Marcar como Concluído');
                        LRRecovery.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Marcar como Concluído');
                    LRRecovery.showToast(lrRecovery.i18n.error, 'error');
                }
            });
        },

        /**
         * Copiar texto genérico
         */
        copyText: function(e) {
            e.preventDefault();
            var text = $(this).data('copy');
            
            if (text) {
                LRRecovery.copyToClipboard(text);
                $(this).addClass('copied');
                LRRecovery.showToast(lrRecovery.i18n.copied, 'success');
                
                var $btn = $(this);
                setTimeout(function() {
                    $btn.removeClass('copied');
                }, 1500);
            }
        },

        /**
         * Copiar para clipboard
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text);
            } else {
                // Fallback para browsers antigos
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
            }
        },

        /**
         * Atualizar badge de status no header
         */
        updateStatusBadge: function(status) {
            var statusLabels = {
                'novo': 'Novo',
                'em_atendimento': 'Em atendimento',
                'aguardando_cliente': 'Aguardando cliente',
                'resolvido': 'Resolvido',
                'abandonado': 'Abandonado'
            };

            var statusIcons = {
                'novo': '🔴',
                'em_atendimento': '🟡',
                'aguardando_cliente': '🔵',
                'resolvido': '🟢',
                'abandonado': '⚫'
            };

            var $badge = $('.lr-case-header-right .lr-badge');
            $badge
                .removeClass('lr-badge-novo lr-badge-em_atendimento lr-badge-aguardando_cliente lr-badge-resolvido lr-badge-abandonado')
                .addClass('lr-badge-' + status)
                .text(statusIcons[status] + ' ' + statusLabels[status]);
        },

        /**
         * Mostrar toast notification
         */
        showToast: function(message, type) {
            type = type || 'info';
            
            // Remover toasts existentes
            $('.lr-toast').remove();

            var $toast = $('<div class="lr-toast ' + type + '">' + message + '</div>');
            $('body').append($toast);

            // Auto-remover após 3 segundos
            setTimeout(function() {
                $toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // Inicializar quando DOM estiver pronto
    $(document).ready(function() {
        LRRecovery.init();
    });

})(jQuery);
