<h2 style="color: #2A6B9F; margin-top: 0;">Bem-vindo ao Programa de Parceiros! 🎉</h2>

<p>Olá, <strong><?php echo esc_html($affiliate_name); ?></strong>!</p>

<p>Seu cadastro foi aprovado e agora você faz parte do nosso time de parceiros! Estamos muito felizes em ter você conosco.</p>

<div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <h3 style="margin-top: 0; color: #2A6B9F;">Seus dados de divulgação</h3>
    
    <p><strong>Seu cupom de desconto:</strong></p>
    <div style="background-color: #2A6B9F; color: white; padding: 15px 25px; border-radius: 8px; font-size: 24px; font-weight: bold; text-align: center; margin: 10px 0;">
        <?php echo esc_html($coupon_code); ?>
    </div>
    <p style="font-size: 12px; color: #666;">O cliente recebe 10% de desconto e você ganha 10% de comissão!</p>
    
    <p style="margin-top: 20px;"><strong>Seu link de afiliado:</strong></p>
    <div style="background-color: #e9ecef; padding: 10px; border-radius: 4px; word-break: break-all; font-family: monospace;">
        <?php echo esc_url($referral_url); ?>
    </div>
    <p style="font-size: 12px; color: #666;">Cookie válido por 60 dias. Comissão: 5%</p>
</div>

<h3 style="color: #2A6B9F;">Como funciona?</h3>

<ol style="line-height: 1.8;">
    <li><strong>Divulgue</strong> nossos cursos usando seu cupom ou link</li>
    <li><strong>Ganhe comissões</strong> por cada venda realizada</li>
    <li><strong>Acompanhe</strong> tudo pelo seu painel</li>
    <li><strong>Receba</strong> seus ganhos mensalmente via PIX</li>
</ol>

<div style="text-align: center; margin: 30px 0;">
    <a href="<?php echo esc_url($dashboard_url); ?>" 
       style="display: inline-block; background-color: #2A6B9F; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">
        Acessar Meu Painel
    </a>
</div>

<p>Qualquer dúvida, acesse a aba FAQ no seu painel ou entre em contato conosco.</p>

<p>Boas vendas! 🚀</p>

