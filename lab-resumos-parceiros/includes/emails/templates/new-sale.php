<h2 style="color: #28a745; margin-top: 0;">Nova venda realizada! 💰</h2>

<p>Olá, <strong><?php echo esc_html($affiliate_name); ?></strong>!</p>

<p>Parabéns! Uma nova venda foi atribuída a você.</p>

<div style="background-color: #d4edda; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0;"><strong>Pedido:</strong></td>
            <td style="padding: 8px 0; text-align: right;">#<?php echo esc_html($order_id); ?></td>
        </tr>
        <tr>
            <td style="padding: 8px 0;"><strong>Valor da Compra:</strong></td>
            <td style="padding: 8px 0; text-align: right;"><?php echo $order_total; ?></td>
        </tr>
        <tr>
            <td style="padding: 8px 0;"><strong>Atribuição:</strong></td>
            <td style="padding: 8px 0; text-align: right;"><?php echo esc_html($attribution); ?></td>
        </tr>
        <tr style="border-top: 1px solid #c3e6cb;">
            <td style="padding: 12px 0; font-size: 18px;"><strong>Sua Comissão:</strong></td>
            <td style="padding: 12px 0; text-align: right; font-size: 18px; color: #28a745;"><strong><?php echo $commission; ?></strong></td>
        </tr>
    </table>
</div>

<p style="color: #666; font-size: 14px;">
    <em>A comissão ficará pendente até a confirmação do pagamento pelo cliente. 
    Após isso, será aprovada automaticamente.</em>
</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="<?php echo esc_url($dashboard_url); ?>" 
       style="display: inline-block; background-color: #2A6B9F; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px;">
        Ver Detalhes no Painel
    </a>
</div>

<p>Continue divulgando e aumente seus ganhos! 🚀</p>

