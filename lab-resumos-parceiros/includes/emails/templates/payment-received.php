<?php
/**
 * Template de email: Pagamento realizado
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 * - $closing (object)
 */

if (!defined('ABSPATH')) {
    exit;
}

$period = sprintf('%02d/%d', $closing->period_month, $closing->period_year);
?>
<h2>Pagamento realizado! 🎉💰</h2>

<p>Olá, <?php echo esc_html($affiliate->get_display_name()); ?>!</p>

<p>Seu pagamento referente ao período <strong><?php echo esc_html($period); ?></strong> foi processado com sucesso!</p>

<div class="highlight" style="background: #d4edda; border-left: 4px solid #28a745;">
    <p><strong>Valor:</strong> R$ <?php echo esc_html(number_format($closing->total_commissions, 2, ',', '.')); ?></p>
    <p><strong>Data:</strong> <?php echo esc_html(date('d/m/Y', strtotime($closing->paid_at))); ?></p>
    <p><strong>Método:</strong> PIX</p>
</div>

<p>O valor foi enviado para a chave PIX cadastrada em seu perfil.</p>

<p>Continue divulgando e aumentando suas comissões!</p>

<p>Obrigado por fazer parte do Programa de Parceiros Lab Resumos! 🚀</p>

