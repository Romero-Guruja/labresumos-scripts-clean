<h2 style="color: #2A6B9F; margin-top: 0;">Seu saque está disponível! 💵</h2>

<p>Olá, <strong><?php echo esc_html($affiliate_name); ?></strong>!</p>

<p>Ótimas notícias! Você atingiu o valor mínimo para saque e suas comissões estão prontas para pagamento.</p>

<div style="background-color: #cce5ff; border-radius: 8px; padding: 25px; margin: 20px 0; text-align: center; border-left: 4px solid #2A6B9F;">
    <p style="margin: 0; font-size: 14px; color: #004085;">Valor disponível para saque:</p>
    <p style="margin: 10px 0; font-size: 32px; font-weight: bold; color: #2A6B9F;"><?php echo $amount; ?></p>
    <?php if (!empty($period_label)): ?>
    <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">Período: <?php echo esc_html($period_label); ?></p>
    <?php endif; ?>
</div>

<?php if (isset($billing_type) && $billing_type === 'rpa'): ?>
<!-- Instruções para RPA (Pessoa Física) -->
<h3 style="color: #2A6B9F;">Próximo passo: Aguarde o RPA</h3>

<p>Como você recebe via RPA (Recibo de Pagamento Autônomo), <strong>não é necessário enviar nenhum documento</strong>. Nossa equipe irá emitir o RPA e processar o pagamento.</p>

<div style="background-color: #d1ecf1; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #17a2b8;">
    <h4 style="margin: 0 0 15px 0; color: #0c5460;">📋 Seus dados cadastrados para o RPA:</h4>
    <p style="margin: 0 0 8px 0;"><strong>Nome:</strong> <?php echo esc_html($rpa_data['nome_completo'] ?? $affiliate_name); ?></p>
    <p style="margin: 0 0 8px 0;"><strong>CPF:</strong> <?php echo esc_html($rpa_data['cpf_formatted'] ?? ''); ?></p>
    <?php if (!empty($rpa_data['data_nascimento_fmt'])): ?>
    <p style="margin: 0 0 8px 0;"><strong>Data de Nascimento:</strong> <?php echo esc_html($rpa_data['data_nascimento_fmt']); ?></p>
    <?php endif; ?>
    <p style="margin: 0 0 8px 0;"><strong>Endereço:</strong> <?php echo esc_html($rpa_data['endereco'] ?? ''); ?></p>
    <p style="margin: 0 0 8px 0;"><strong>Telefone:</strong> <?php echo esc_html($rpa_data['telefone'] ?? ''); ?></p>
    <?php if (!empty($rpa_data['inss_number'])): ?>
    <p style="margin: 0;"><strong>INSS:</strong> <?php echo esc_html($rpa_data['inss_number']); ?></p>
    <?php endif; ?>
</div>

<p style="color: #856404; background-color: #fff3cd; padding: 15px; border-radius: 8px;">
    ⚠️ <strong>Importante:</strong> Caso algum dado esteja incorreto, atualize seu perfil o mais rápido possível para evitar atrasos no pagamento.
</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="<?php echo esc_url($dashboard_url); ?>?tab=perfil" 
       style="display: inline-block; background-color: #17a2b8; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">
        Verificar Meus Dados
    </a>
</div>

<p style="color: #666; font-size: 14px;">
    Após a emissão do RPA, o pagamento será realizado via PIX em até 5 dias úteis.
</p>

<?php else: ?>
<!-- Instruções para PJ (Nota Fiscal) -->
<h3 style="color: #2A6B9F;">Próximo passo: Enviar Nota Fiscal</h3>

<p>Para receber o pagamento, você precisa emitir uma Nota Fiscal de prestação de serviços com os seguintes dados:</p>

<div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <p style="margin: 0 0 10px 0;"><strong>Razão Social:</strong><br><?php echo esc_html($company_name); ?></p>
    <?php if (!empty($company_cnpj)): ?>
    <p style="margin: 0 0 10px 0;"><strong>CNPJ:</strong><br><?php echo esc_html($company_cnpj); ?></p>
    <?php endif; ?>
    <?php if (!empty($company_address)): ?>
    <p style="margin: 0 0 10px 0;"><strong>Endereço:</strong><br><?php echo nl2br(esc_html($company_address)); ?></p>
    <?php endif; ?>
    <p style="margin: 0;"><strong>Descrição do Serviço:</strong><br>Serviços de divulgação e indicação comercial</p>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="<?php echo esc_url($dashboard_url); ?>?tab=financeiro" 
       style="display: inline-block; background-color: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">
        Enviar Nota Fiscal
    </a>
</div>

<p style="color: #666; font-size: 14px;">
    Após o envio, sua NF será validada e o pagamento será realizado via PIX em até 5 dias úteis.
</p>
<?php endif; ?>
