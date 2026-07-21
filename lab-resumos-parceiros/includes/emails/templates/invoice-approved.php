<?php
/**
 * Template de email: NF aprovada
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
<h2>Ótimas notícias, <?php echo esc_html($affiliate->get_display_name()); ?>! ✅</h2>

<p>Sua Nota Fiscal referente ao período <strong><?php echo esc_html($period); ?></strong> foi aprovada!</p>

<div class="highlight">
    <p><strong>NF Número:</strong> <?php echo esc_html($closing->invoice_number ?: 'N/A'); ?></p>
    <p><strong>Valor:</strong> R$ <?php echo esc_html(number_format($closing->total_commissions, 2, ',', '.')); ?></p>
</div>

<p>O pagamento será realizado via PIX em até <strong>5 dias úteis</strong>.</p>

<p>Você receberá uma confirmação assim que o pagamento for processado.</p>

<p>Obrigado por fazer parte do nosso programa de parceiros! 🙏</p>

