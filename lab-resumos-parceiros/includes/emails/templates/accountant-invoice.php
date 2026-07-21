<?php
/**
 * Template de email: NF/RPA recebido para contador
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 * - $closing (object)
 * - $accountant_url (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!$affiliate || !$closing) {
    return;
}

$period = sprintf('%02d/%d', $closing->period_month, $closing->period_year);
$is_rpa = $affiliate->is_rpa();
?>
<?php if ($is_rpa): ?>
<h2>Novo RPA para emissão 📋</h2>

<p>Um fechamento está pronto para emissão de RPA (Recibo de Pagamento Autônomo):</p>

<div class="highlight">
    <p><strong>Parceiro:</strong> <?php echo esc_html($affiliate->get_display_name()); ?></p>
    <p><strong>Email:</strong> <?php echo esc_html($affiliate->get_email()); ?></p>
    <p><strong>Período:</strong> <?php echo esc_html($period); ?></p>
    <p><strong>Valor:</strong> R$ <?php echo esc_html(number_format($closing->total_commissions, 2, ',', '.')); ?></p>
    <p><strong>Tipo:</strong> <span style="background-color: #17a2b8; color: white; padding: 2px 8px; border-radius: 4px;">RPA</span></p>
</div>

<h3>Dados do Autônomo para RPA:</h3>
<?php $rpa_data = $affiliate->get_rpa_data(); ?>
<div class="highlight">
    <p><strong>Nome Completo:</strong> <?php echo esc_html($rpa_data['nome_completo']); ?></p>
    <p><strong>CPF:</strong> <?php echo esc_html($rpa_data['cpf_formatted']); ?></p>
    <p><strong>Endereço:</strong> <?php echo esc_html($rpa_data['endereco']); ?></p>
    <p><strong>Telefone:</strong> <?php echo esc_html($rpa_data['telefone']); ?></p>
    <p><strong>Email:</strong> <?php echo esc_html($rpa_data['email']); ?></p>
    <?php if (!empty($rpa_data['inss_number'])): ?>
    <p><strong>INSS/PIS:</strong> <?php echo esc_html($rpa_data['inss_number']); ?></p>
    <?php endif; ?>
    <p><strong>Descrição do Serviço:</strong> <?php echo esc_html($rpa_data['descricao_servico']); ?></p>
</div>

<?php else: ?>
<h2>Nova NF recebida 📄</h2>

<p>Uma Nota Fiscal foi enviada para validação:</p>

<div class="highlight">
    <p><strong>Parceiro:</strong> <?php echo esc_html($affiliate->get_display_name()); ?></p>
    <p><strong>Email:</strong> <?php echo esc_html($affiliate->get_email()); ?></p>
    <p><strong>Período:</strong> <?php echo esc_html($period); ?></p>
    <p><strong>NF Número:</strong> <?php echo esc_html($closing->invoice_number ?: 'Não informado'); ?></p>
    <p><strong>Valor:</strong> R$ <?php echo esc_html(number_format($closing->total_commissions, 2, ',', '.')); ?></p>
    <p><strong>Data de envio:</strong> <?php echo esc_html(date('d/m/Y H:i', strtotime($closing->invoice_uploaded_at))); ?></p>
    <p><strong>Tipo:</strong> <span style="background-color: #28a745; color: white; padding: 2px 8px; border-radius: 4px;">PJ / NF</span></p>
</div>

<h3>Dados da empresa emissora:</h3>
<div class="highlight">
    <p><strong>CNPJ:</strong> <?php echo esc_html($affiliate->get_company_cnpj_formatted()); ?></p>
    <p><strong>Razão Social:</strong> <?php echo esc_html($affiliate->get_company_name()); ?></p>
</div>

<p><strong>A NF está anexada a este email.</strong></p>
<?php endif; ?>

<h3>Dados de pagamento do parceiro:</h3>
<div class="highlight">
    <?php if ($affiliate->get_data('payment_method') === 'pix'): ?>
        <p><strong>Método:</strong> PIX</p>
        <p><strong>Tipo de chave:</strong> <?php echo esc_html(strtoupper($affiliate->get_data('pix_key_type'))); ?></p>
        <p><strong>Chave PIX:</strong> <?php echo esc_html($affiliate->get_decrypted_pix_key()); ?></p>
    <?php else: ?>
        <p><strong>Método:</strong> Transferência Bancária</p>
        <p><strong>Banco:</strong> <?php echo esc_html($affiliate->get_data('bank_name')); ?></p>
        <p><strong>Agência:</strong> <?php echo esc_html($affiliate->get_data('bank_agency')); ?></p>
        <p><strong>Conta:</strong> <?php echo esc_html($affiliate->get_data('bank_account')); ?></p>
    <?php endif; ?>
    <p><strong>Titular:</strong> <?php echo esc_html($affiliate->get_data('holder_name')); ?></p>
    <p><strong>CPF/CNPJ:</strong> <?php echo esc_html($affiliate->get_data('holder_document')); ?></p>
</div>

<p style="text-align: center;">
    <a href="<?php echo esc_url($accountant_url); ?>" class="btn">Acessar Área do Contador</a>
</p>
