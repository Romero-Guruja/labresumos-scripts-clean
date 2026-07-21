<?php
/**
 * Dashboard do Afiliado - Tab: Links e Cupons
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = LRP_Settings::instance();
$coupon_rate = $affiliate->get_commission_rate('coupon');
$link_rate = $affiliate->get_commission_rate('link');
$customer_discount = $affiliate->get_customer_discount();
$cookie_days = $affiliate->get_cookie_days();
?>

<div class="lrp-dashboard-links">
    <h2><?php _e('Suas Ferramentas de Divulgação', 'lab-resumos-parceiros'); ?></h2>
    
    <p class="lrp-intro"><?php _e('Use estas ferramentas para divulgar os cursos do Lab Resumos e ganhar comissões em cada venda.', 'lab-resumos-parceiros'); ?></p>
    
    <!-- Cupom de desconto -->
    <div class="lrp-tool-section">
        <div class="lrp-tool-header">
            <h3>🎫 <?php _e('Seu Cupom Exclusivo', 'lab-resumos-parceiros'); ?></h3>
            <span class="lrp-badge lrp-badge-success"><?php _e('Recomendado', 'lab-resumos-parceiros'); ?></span>
        </div>
        
        <div class="lrp-tool-content">
            <div class="lrp-highlight-box">
                <div class="lrp-copy-field lrp-copy-large">
                    <input type="text" readonly value="<?php echo esc_attr($affiliate->get_coupon_code()); ?>" id="lrp-coupon-main">
                    <button type="button" class="lrp-btn lrp-btn-primary lrp-btn-copy" data-target="lrp-coupon-main">
                        📋 <?php _e('Copiar Cupom', 'lab-resumos-parceiros'); ?>
                    </button>
                </div>
            </div>
            
            <div class="lrp-tool-info">
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Sua comissão:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-info-value"><?php echo number_format($coupon_rate, 0); ?>%</span>
                </div>
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Desconto para cliente:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-info-value"><?php echo number_format($customer_discount, 0); ?>%</span>
                </div>
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Atribuição:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-info-value">100% <?php _e('garantida', 'lab-resumos-parceiros'); ?></span>
                </div>
            </div>
            
            <div class="lrp-tip">
                <strong>💡 <?php _e('Dica:', 'lab-resumos-parceiros'); ?></strong>
                <?php _e('Sempre incentive seus indicados a usar o cupom no checkout. A atribuição é 100% garantida e você ganha uma comissão maior!', 'lab-resumos-parceiros'); ?>
            </div>
        </div>
    </div>
    
    <!-- Link de afiliado -->
    <div class="lrp-tool-section">
        <div class="lrp-tool-header">
            <h3>🔗 <?php _e('Seu Link de Afiliado', 'lab-resumos-parceiros'); ?></h3>
        </div>
        
        <div class="lrp-tool-content">
            <div class="lrp-highlight-box">
                <div class="lrp-copy-field">
                    <input type="text" readonly value="<?php echo esc_url($affiliate->get_referral_url()); ?>" id="lrp-link-main">
                    <button type="button" class="lrp-btn lrp-btn-secondary lrp-btn-copy" data-target="lrp-link-main">
                        📋 <?php _e('Copiar Link', 'lab-resumos-parceiros'); ?>
                    </button>
                </div>
            </div>
            
            <div class="lrp-tool-info">
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Sua comissão:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-info-value"><?php echo number_format($link_rate, 0); ?>%</span>
                </div>
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Duração do cookie:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-info-value"><?php echo $cookie_days; ?> <?php _e('dias', 'lab-resumos-parceiros'); ?></span>
                </div>
                <div class="lrp-info-item">
                    <span class="lrp-info-label"><?php _e('Atribuição:', 'lab-resumos-parceiros'); ?></span>
                    <span class="lrp-info-value"><?php _e('Via cookie', 'lab-resumos-parceiros'); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gerador de links -->
    <div class="lrp-tool-section">
        <div class="lrp-tool-header">
            <h3>🔧 <?php _e('Gerador de Links', 'lab-resumos-parceiros'); ?></h3>
        </div>
        
        <div class="lrp-tool-content">
            <p><?php _e('Crie links personalizados para páginas específicas:', 'lab-resumos-parceiros'); ?></p>
            
            <div class="lrp-link-generator">
                <div class="lrp-form-group">
                    <label for="lrp-custom-url"><?php _e('URL da página:', 'lab-resumos-parceiros'); ?></label>
                    <input type="url" id="lrp-custom-url" placeholder="<?php echo esc_attr(home_url('/curso-exemplo/')); ?>">
                </div>
                
                <button type="button" class="lrp-btn lrp-btn-secondary" id="lrp-generate-link">
                    <?php _e('Gerar Link', 'lab-resumos-parceiros'); ?>
                </button>
                
                <div class="lrp-copy-field" id="lrp-generated-link-wrapper" style="display: none;">
                    <input type="text" readonly id="lrp-generated-link">
                    <button type="button" class="lrp-btn lrp-btn-secondary lrp-btn-copy" data-target="lrp-generated-link">
                        📋 <?php _e('Copiar', 'lab-resumos-parceiros'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Comparativo -->
    <div class="lrp-comparison-table">
        <h3><?php _e('Comparativo: Cupom vs Link', 'lab-resumos-parceiros'); ?></h3>
        <table class="lrp-table">
            <thead>
                <tr>
                    <th><?php _e('Característica', 'lab-resumos-parceiros'); ?></th>
                    <th>🎫 <?php _e('Cupom', 'lab-resumos-parceiros'); ?></th>
                    <th>🔗 <?php _e('Link', 'lab-resumos-parceiros'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php _e('Sua comissão', 'lab-resumos-parceiros'); ?></td>
                    <td><strong><?php echo number_format($coupon_rate, 0); ?>%</strong></td>
                    <td><?php echo number_format($link_rate, 0); ?>%</td>
                </tr>
                <tr>
                    <td><?php _e('Desconto para cliente', 'lab-resumos-parceiros'); ?></td>
                    <td><?php echo number_format($customer_discount, 0); ?>%</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td><?php _e('Certeza de atribuição', 'lab-resumos-parceiros'); ?></td>
                    <td><strong>100%</strong></td>
                    <td><?php _e('Depende do cookie', 'lab-resumos-parceiros'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Ideal para', 'lab-resumos-parceiros'); ?></td>
                    <td><?php _e('Divulgação direta', 'lab-resumos-parceiros'); ?></td>
                    <td><?php _e('Redes sociais, blogs', 'lab-resumos-parceiros'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var generateBtn = document.getElementById('lrp-generate-link');
    var customUrl = document.getElementById('lrp-custom-url');
    var generatedLink = document.getElementById('lrp-generated-link');
    var wrapper = document.getElementById('lrp-generated-link-wrapper');
    var refCode = '<?php echo esc_js($affiliate->get_referral_code()); ?>';
    
    if (generateBtn) {
        generateBtn.addEventListener('click', function() {
            var url = customUrl.value.trim();
            if (!url) {
                alert('<?php _e('Por favor, insira uma URL válida.', 'lab-resumos-parceiros'); ?>');
                return;
            }
            
            // Adiciona parâmetro ref
            var separator = url.indexOf('?') > -1 ? '&' : '?';
            generatedLink.value = url + separator + 'ref=' + refCode;
            wrapper.style.display = 'flex';
        });
    }
});
</script>

