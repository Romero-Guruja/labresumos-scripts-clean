<h2 style="color: #2A6B9F; margin-top: 0;">Sobre seu cadastro no Programa de Parceiros</h2>

<p>Olá, <strong><?php echo esc_html($affiliate_name); ?></strong>!</p>

<p>Agradecemos seu interesse em fazer parte do Programa de Parceiros Lab Resumos.</p>

<p>Após análise do seu cadastro, infelizmente não foi possível aprová-lo neste momento.</p>

<?php if (!empty($reason)): ?>
<div style="background-color: #f8d7da; border-left: 4px solid #dc3545; border-radius: 4px; padding: 15px; margin: 20px 0;">
    <p style="margin: 0;"><strong>Motivo:</strong></p>
    <p style="margin: 10px 0 0 0; color: #721c24;"><?php echo esc_html($reason); ?></p>
</div>
<?php endif; ?>

<p>Se você acredita que houve um engano ou deseja mais informações, entre em contato conosco respondendo este email.</p>

<p>Agradecemos sua compreensão.</p>

<p>Atenciosamente,<br>
<strong>Equipe Lab Resumos</strong></p>

