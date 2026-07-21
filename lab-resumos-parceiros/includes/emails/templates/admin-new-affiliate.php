<?php
/**
 * Template de email: Novo afiliado para admin
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 * - $admin_url (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!$affiliate) {
    return;
}
?>
<h2>Novo parceiro aguardando aprovação 📋</h2>

<p>Um novo candidato se cadastrou no Programa de Parceiros:</p>

<div class="highlight">
    <p><strong>Nome:</strong> <?php echo esc_html($affiliate->get_display_name()); ?></p>
    <p><strong>Email:</strong> <?php echo esc_html($affiliate->get_email()); ?></p>
    <p><strong>Data:</strong> <?php echo esc_html(date('d/m/Y H:i')); ?></p>
    <?php if ($affiliate->get_sponsor_id()): ?>
        <?php $sponsor = new LRP_Affiliate($affiliate->get_sponsor_id()); ?>
        <?php if ($sponsor->get_display_name()): ?>
            <p><strong>Indicado por:</strong> <?php echo esc_html($sponsor->get_display_name()); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($affiliate->get_application_notes()): ?>
<p><strong>Notas do candidato:</strong></p>
<blockquote style="background: #f8f9fa; padding: 15px; border-left: 4px solid #6c757d; margin: 15px 0;">
    <?php echo esc_html($affiliate->get_application_notes()); ?>
</blockquote>
<?php endif; ?>

<p style="text-align: center;">
    <a href="<?php echo esc_url($admin_url); ?>" class="btn">Revisar Cadastro</a>
</p>

