jQuery(document).ready(function($) {
    
    // Mostrar/esconder campos conforme tipo de autenticação
    function toggleAuthFields() {
        var authType = $('#cpf_sender_auth_type').val();
        
        if (authType === 'basic_auth') {
            $('#api-key-fields').hide();
            $('#api-key-value-field').hide();
            $('#basic-auth-fields').show();
            $('#basic-auth-password-field').show();
            $('#cpf_sender_api_key').removeAttr('required');
            $('#cpf_sender_header_name').removeAttr('required');
            $('#cpf_sender_basic_auth_username').attr('required', 'required');
            $('#cpf_sender_basic_auth_password').attr('required', 'required');
        } else {
            $('#api-key-fields').show();
            $('#api-key-value-field').show();
            $('#basic-auth-fields').hide();
            $('#basic-auth-password-field').hide();
            $('#cpf_sender_api_key').attr('required', 'required');
            $('#cpf_sender_basic_auth_username').removeAttr('required');
            $('#cpf_sender_basic_auth_password').removeAttr('required');
        }
    }
    
    // Inicializar campos ao carregar (com pequeno delay para garantir que o DOM está pronto)
    setTimeout(function() {
        toggleAuthFields();
    }, 50);
    
    // Atualizar campos ao mudar tipo de autenticação
    $('#cpf_sender_auth_type').on('change', toggleAuthFields);
    
    // Teste de conexão
    $('#cpf-sender-test-btn').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $result = $('#cpf-sender-test-result');
        
        $btn.prop('disabled', true).text('Testando...');
        $result.html('');
        
        $.ajax({
            url: cpfSenderAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'cpf_sender_test_connection',
                nonce: cpfSenderAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var statusClass = data.success ? 'success' : 'error';
                    var statusIcon = data.success ? '✅' : '❌';
                    
                    $result.html(
                        '<div class="cpf-sender-result ' + statusClass + '">' +
                        statusIcon + ' Conexão ' + (data.success ? 'OK' : 'Falhou') + ' - Status: ' + data.status_code +
                        (data.body ? '<br><small>Response: ' + data.body.substring(0, 200) + '</small>' : '') +
                        '</div>'
                    );
                } else {
                    $result.html(
                        '<div class="cpf-sender-result error">❌ ' + response.data + '</div>'
                    );
                }
            },
            error: function() {
                $result.html(
                    '<div class="cpf-sender-result error">❌ Erro na requisição</div>'
                );
            },
            complete: function() {
                $btn.prop('disabled', false).text('🔄 Testar Endpoint');
            }
        });
    });
    
    // Teste de conectividade com Telegram
    $('#cpf-sender-test-telegram-btn').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $result = $('#cpf-sender-test-telegram-result');
        
        $btn.prop('disabled', true).text('Enviando...');
        $result.html('');
        
        $.ajax({
            url: cpfSenderAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'cpf_sender_test_telegram',
                nonce: cpfSenderAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html(
                        '<div class="cpf-sender-result success">📲 ' + response.data.message + '</div>'
                    );
                } else {
                    $result.html(
                        '<div class="cpf-sender-result error">❌ ' + response.data + '</div>'
                    );
                }
            },
            error: function() {
                $result.html(
                    '<div class="cpf-sender-result error">❌ Erro na requisição</div>'
                );
            },
            complete: function() {
                $btn.prop('disabled', false).text('📲 Testar Telegram');
            }
        });
    });
    
    // Confirmação para limpar logs
    $('#cpf-sender-clear-logs').on('click', function(e) {
        if (!confirm('Tem certeza que deseja limpar os logs antigos (>30 dias)?')) {
            e.preventDefault();
        }
    });
    
    // Modal de detalhes do log
    $('.cpf-sender-view-log').on('click', function(e) {
        e.preventDefault();
        var logId = $(this).data('log-id');
        
        // Expandir/colapsar linha de detalhes
        $('#cpf-sender-log-detail-' + logId).toggle();
    });
    
    // Tabs
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-content').hide();
        $(target).show();
    });
});

