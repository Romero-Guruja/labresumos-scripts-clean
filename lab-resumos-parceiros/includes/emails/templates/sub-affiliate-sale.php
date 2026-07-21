<?php
/**
 * Template de email: Venda de sub-afiliado
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $sponsor (LRP_Affiliate)
 * - $sub_affiliate (LRP_Affiliate)
 * - $commission (LRP_Commission)
 * - $referral (LRP_Referral)
 * - $level (int)
 * - $sponsor_name (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!$sponsor || !$sub_affiliate || !$commission || !$referral) {
    return;
}
?>
<h2>Comissão de rede! 🎉</h2>

<p>Olá, <?php echo esc_html($sponsor->get_display_name()); ?>!</p>

<p>Um parceiro da sua rede fez uma venda e você ganhou uma comissão:</p>

<div class="highlight">
    <p><strong>Parceiro:</strong> <?php echo esc_html($sub_affiliate->get_display_name()); ?> (Nível <?php echo (int) $level; ?>)</p>
    <p><strong>Pedido:</strong> #<?php echo esc_html($referral->order_id); ?></p>
    <p><strong>Valor da venda:</strong> R$ <?php echo esc_html(number_format($referral->commission_base, 2, ',', '.')); ?></p>
    <p><strong>Taxa:</strong> <?php echo esc_html(number_format($commission->commission_rate, 1, ',', '.')); ?>%</p>
    <p><strong>Sua comissão:</strong> R$ <?php echo esc_html(number_format($commission->commission_amount, 2, ',', '.')); ?></p>
</div>

<p>Sua rede está gerando resultados! Continue crescendo! 📈</p>

