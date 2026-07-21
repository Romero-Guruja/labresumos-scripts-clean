<?php
/**
 * Template de email: Novo sub-afiliado
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $sponsor (LRP_Affiliate)
 * - $new_affiliate (LRP_Affiliate)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!$sponsor || !$new_affiliate) {
    return;
}
?>
<h2>Você tem um novo parceiro na sua rede! 👥🎉</h2>

<p>Olá, <?php echo esc_html($sponsor->get_display_name()); ?>!</p>

<p>Parabéns! Um novo parceiro se cadastrou usando seu link de indicação:</p>

<div class="highlight">
    <p><strong>Nome:</strong> <?php echo esc_html($new_affiliate->get_display_name()); ?></p>
    <p><strong>Data:</strong> <?php echo esc_html(date('d/m/Y H:i')); ?></p>
</div>

<p>A partir de agora, você ganhará comissões sobre as vendas deste parceiro:</p>

<ul>
    <li><strong>Nível 2:</strong> 3% sobre as vendas diretas dele</li>
    <li><strong>Nível 3:</strong> 1% sobre as vendas dos indicados dele</li>
</ul>

<p>Continue convidando mais pessoas para expandir sua rede!</p>

<p>Acesse seu painel para ver sua rede completa.</p>

