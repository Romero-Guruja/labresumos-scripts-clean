/**
 * Lab Resumos Parceiros - Admin Scripts
 */
(function($) {
    'use strict';
    
    // Aprovar afiliado
    $(document).on('click', '.lrp-approve-affiliate', function(e) {
        e.preventDefault();
        
        if (!confirm(lrp_admin.confirm_approve)) {
            return;
        }
        
        var $btn = $(this);
        var affiliateId = $btn.data('id');
        
        $btn.prop('disabled', true);
        
        $.post(lrp_admin.ajax_url, {
            action: 'lrp_approve_affiliate',
            nonce: lrp_admin.nonce,
            affiliate_id: affiliateId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Rejeitar afiliado
    $(document).on('click', '.lrp-reject-affiliate', function(e) {
        e.preventDefault();
        
        var reason = prompt('Motivo da rejeição (opcional):');
        
        if (reason === null) {
            return;
        }
        
        var $btn = $(this);
        var affiliateId = $btn.data('id');
        
        $btn.prop('disabled', true);
        
        $.post(lrp_admin.ajax_url, {
            action: 'lrp_reject_affiliate',
            nonce: lrp_admin.nonce,
            affiliate_id: affiliateId,
            reason: reason
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Aprovar NF
    $(document).on('click', '.lrp-approve-invoice', function(e) {
        e.preventDefault();
        
        if (!confirm('Aprovar esta Nota Fiscal?')) {
            return;
        }
        
        var $btn = $(this);
        var closingId = $btn.data('id');
        
        $btn.prop('disabled', true);
        
        $.post(lrp_admin.ajax_url, {
            action: 'lrp_approve_invoice',
            nonce: lrp_admin.nonce,
            closing_id: closingId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
                $btn.prop('disabled', false);
            }
        });
    });

    // Aprovar RPA (v1.7.1)
    $(document).on('click', '.lrp-approve-rpa', function(e) {
        e.preventDefault();

        if (!confirm('Confirma que o RPA foi emitido e deseja aprovar para pagamento?')) {
            return;
        }

        var $btn = $(this);
        var closingId = $btn.data('id');

        $btn.prop('disabled', true);

        $.post(lrp_admin.ajax_url, {
            action: 'lrp_approve_rpa',
            nonce: lrp_admin.nonce,
            closing_id: closingId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Rejeitar NF
    $(document).on('click', '.lrp-reject-invoice', function(e) {
        e.preventDefault();
        
        var reason = prompt('Motivo da rejeição (obrigatório):');
        
        if (!reason) {
            alert('Informe o motivo da rejeição.');
            return;
        }
        
        var $btn = $(this);
        var closingId = $btn.data('id');
        
        $btn.prop('disabled', true);
        
        $.post(lrp_admin.ajax_url, {
            action: 'lrp_reject_invoice',
            nonce: lrp_admin.nonce,
            closing_id: closingId,
            reason: reason
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Excluir item (material/FAQ)
    $(document).on('click', '.lrp-delete-item', function(e) {
        e.preventDefault();
        
        if (!confirm(lrp_admin.confirm_delete)) {
            return;
        }
        
        var $btn = $(this);
        var action = $btn.data('action');
        var id = $btn.data('id');
        
        $.post(lrp_admin.ajax_url, {
            action: action,
            nonce: lrp_admin.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                $btn.closest('tr').fadeOut(function() {
                    $(this).remove();
                });
            } else {
                alert(response.data.message);
            }
        });
    });
    
    // Carregar dados do gráfico
    function loadChartData(type, startDate, endDate) {
        $.get(lrp_admin.ajax_url, {
            action: 'lrp_get_chart_data',
            nonce: lrp_admin.nonce,
            type: type,
            start_date: startDate,
            end_date: endDate
        }, function(response) {
            if (response.success && window.lrpChart) {
                updateChart(response.data.data);
            }
        });
    }
    
    // Inicializa gráficos se Chart.js disponível
    if (typeof Chart !== 'undefined' && $('#lrp-sales-chart').length) {
        var ctx = document.getElementById('lrp-sales-chart').getContext('2d');
        
        window.lrpChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Vendas',
                    data: [],
                    borderColor: '#2A6B9F',
                    tension: 0.1,
                    fill: false
                }, {
                    label: 'Receita (R$)',
                    data: [],
                    borderColor: '#28a745',
                    tension: 0.1,
                    fill: false,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
        
        // Carrega dados iniciais
        loadChartData('sales', $('#lrp-start-date').val(), $('#lrp-end-date').val());
    }
    
    // Filtro de datas - permite submit normal para atualizar cards/tabelas
    // O gráfico será carregado automaticamente após o reload da página
    
    function updateChart(data) {
        if (!window.lrpChart) return;
        
        window.lrpChart.data.labels = data.map(function(item) {
            return item.date;
        });
        
        window.lrpChart.data.datasets[0].data = data.map(function(item) {
            return item.sales;
        });
        
        window.lrpChart.data.datasets[1].data = data.map(function(item) {
            return item.revenue;
        });
        
        window.lrpChart.update();
    }
    
})(jQuery);

