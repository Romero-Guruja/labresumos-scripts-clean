<?php
/**
 * Template de email: Fechamento disponível
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 * - $closing (object)
 * - $total (float)
 * - $dashboard_url (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

$period = sprintf('%02d/%d', $closing->period_month, $closing->period_year);
$is_rpa = $affiliate->is_rpa();
$settings = LRP_Settings::instance();
?>
<h2>Olá, <?php echo esc_html($affiliate->get_display_name()); ?>! 💰</h2>

<p>O fechamento do período <strong><?php echo esc_html($period); ?></strong> está disponível!</p>

<div class="highlight">
    <p><strong>Total de vendas:</strong> <?php echo (int) $closing->total_sales; ?></p>
    <p><strong>Receita gerada:</strong> R$ <?php echo esc_html(number_format($closing->total_revenue, 2, ',', '.')); ?></p>
    <p><strong>Suas comissões:</strong> R$ <?php echo esc_html(number_format($total, 2, ',', '.')); ?></p>
</div>

<?php if (!empty($closing->deferred) && !empty($closing->original_period_month)): ?>
<p style="background-color: #fff3cd; padding: 10px; border-radius: 5px;">
    ℹ️ Este fechamento inclui valores acumulados de período(s) anterior(es).
</p>
<?php endif; ?>

<?php if ($is_rpa): ?>
<!-- Instruções para RPA (Pessoa Física) -->
<h3>Próximo passo: Aguarde o RPA</h3>

<p>Você atingiu o valor mínimo para saque! Como você recebe via <strong>RPA (Recibo de Pagamento Autônomo)</strong>, não é necessário enviar nenhum documento.</p>

<p>Nossa equipe irá emitir o RPA com seus dados cadastrados e processar o pagamento via PIX em até 5 dias úteis.</p>

<div class="highlight">
    <h4>Seus dados cadastrados:</h4>
    <?php $rpa_data = $affiliate->get_rpa_data(); ?>
    <p><strong>Nome:</strong> <?php echo esc_html($rpa_data['nome_completo']); ?></p>
    <p><strong>CPF:</strong> <?php echo esc_html($rpa_data['cpf_formatted']); ?></p>
    <p><strong>Endereço:</strong> <?php echo esc_html($rpa_data['endereco']); ?></p>
    <p><strong>Telefone:</strong> <?php echo esc_html($rpa_data['telefone']); ?></p>
</div>

<p>⚠️ <strong>Importante:</strong> Caso algum dado esteja incorreto, atualize seu perfil o mais rápido possível.</p>

<p style="text-align: center;">
    <a href="<?php echo esc_url($dashboard_url); ?>?tab=perfil" class="btn">Verificar Meus Dados</a>
</p>

<?php else: ?>
<!-- Instruções para PJ (Nota Fiscal) -->
<p>Você atingiu o valor mínimo para saque! Para receber seu pagamento, envie sua Nota Fiscal de prestação de serviços.</p>

<h3>Dados para emissão da NF:</h3>

<div class="highlight">
    <p><strong>Tomador:</strong> <?php echo esc_html($settings->get('company_name')); ?></p>
    <p><strong>CNPJ:</strong> <?php echo esc_html($settings->get('company_cnpj')); ?></p>
    <p><strong>Endereço:</strong> <?php echo esc_html($settings->get('company_address')); ?></p>
    <p><strong>Valor:</strong> R$ <?php echo esc_html(number_format($total, 2, ',', '.')); ?></p>
    <p><strong>Descrição:</strong> Serviços de divulgação e indicação comercial - Período <?php echo esc_html($period); ?></p>
</div>

<p style="text-align: center;">
    <a href="<?php echo esc_url($dashboard_url); ?>?tab=financeiro" class="btn">Enviar Nota Fiscal</a>
</p>

<p><small>Após o envio, sua NF será validada e o pagamento será realizado via PIX em até 5 dias úteis.</small></p>
<?php endif; ?>
