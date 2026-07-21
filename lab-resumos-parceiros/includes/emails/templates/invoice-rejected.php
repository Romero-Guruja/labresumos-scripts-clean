<?php
/**
 * Template de email: NF rejeitada
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 * - $closing (object)
 * - $reason (string)
 * - $dashboard_url (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

$period = sprintf('%02d/%d', $closing->period_month, $closing->period_year);
?>
<h2>Atenção, <?php echo esc_html($affiliate->get_display_name()); ?>!</h2>

<p>Sua Nota Fiscal referente ao período <strong><?php echo esc_html($period); ?></strong> precisa de correção.</p>

<div class="highlight" style="background: #fff3cd; border-left: 4px solid #ffc107;">
    <p><strong>Motivo da rejeição:</strong></p>
    <p><?php echo esc_html($reason); ?></p>
</div>

<p>Por favor, emita uma nova NF corrigida e envie novamente pelo seu painel.</p>

<h3>Lembrete - Dados para emissão da NF:</h3>

<?php 
$settings = LRP_Settings::instance();
?>
<div class="highlight">
    <p><strong>Tomador:</strong> <?php echo esc_html($settings->get('company_name')); ?></p>
    <p><strong>CNPJ:</strong> <?php echo esc_html($settings->get('company_cnpj')); ?></p>
    <p><strong>Endereço:</strong> <?php echo esc_html($settings->get('company_address')); ?></p>
    <p><strong>Valor:</strong> R$ <?php echo esc_html(number_format($closing->total_commissions, 2, ',', '.')); ?></p>
    <p><strong>Descrição:</strong> Serviços de divulgação e indicação comercial - Período <?php echo esc_html($period); ?></p>
</div>

<p style="text-align: center;">
    <a href="<?php echo esc_url($dashboard_url); ?>" class="btn">Enviar Nova NF</a>
</p>

