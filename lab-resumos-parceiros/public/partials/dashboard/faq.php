<?php
/**
 * Dashboard do Afiliado - Tab: FAQ
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 * - $faqs (array)
 * - $categories (array)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obtém valores específicos do afiliado para substituir nos textos
$affiliate_values = [
    '{comissao_cupom}'    => number_format($affiliate->get_commission_rate('coupon'), 0) . '%',
    '{comissao_link}'     => number_format($affiliate->get_commission_rate('link'), 0) . '%',
    '{comissao_l2}'       => number_format($affiliate->get_commission_rate('l2'), 0) . '%',
    '{comissao_l3}'       => number_format($affiliate->get_commission_rate('l3'), 0) . '%',
    '{desconto_cliente}'  => number_format($affiliate->get_customer_discount(), 0) . '%',
    '{cookie_dias}'       => $affiliate->get_cookie_days(),
    '{cupom}'             => $affiliate->get_coupon_code(),
];

// Função para substituir placeholders
$replace_placeholders = function($text) use ($affiliate_values) {
    return str_replace(array_keys($affiliate_values), array_values($affiliate_values), $text);
};

// Agrupa FAQs por categoria
$grouped_faqs = [];
foreach ($faqs as $faq) {
    $cat = $faq->category ?: 'geral';
    if (!isset($grouped_faqs[$cat])) {
        $grouped_faqs[$cat] = [];
    }
    $grouped_faqs[$cat][] = $faq;
}
?>

<div class="lrp-dashboard-faq">
    <h2><?php _e('Perguntas Frequentes', 'lab-resumos-parceiros'); ?></h2>
    
    <p class="lrp-intro">
        <?php _e('Tire suas dúvidas sobre o Programa de Parceiros.', 'lab-resumos-parceiros'); ?>
    </p>
    
    <?php if (!empty($grouped_faqs)): ?>
    
    <div class="lrp-faq-container">
        <?php foreach ($grouped_faqs as $category => $category_faqs): ?>
        <div class="lrp-faq-category">
            <h3 class="lrp-faq-category-title">
                <?php echo esc_html($categories[$category] ?? ucfirst($category)); ?>
            </h3>
            
            <div class="lrp-faq-list">
                <?php foreach ($category_faqs as $index => $faq): ?>
                <div class="lrp-faq-item">
                    <button type="button" class="lrp-faq-question" 
                            aria-expanded="false" 
                            aria-controls="faq-answer-<?php echo $faq->id; ?>">
                        <span><?php echo esc_html($replace_placeholders($faq->question)); ?></span>
                        <span class="lrp-faq-icon">+</span>
                    </button>
                    <div class="lrp-faq-answer" id="faq-answer-<?php echo $faq->id; ?>">
                        <?php echo wp_kses_post($replace_placeholders($faq->answer)); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php else: ?>
    
    <div class="lrp-empty-state">
        <div class="lrp-empty-icon">❓</div>
        <h3><?php _e('Nenhuma pergunta cadastrada', 'lab-resumos-parceiros'); ?></h3>
        <p><?php _e('As perguntas frequentes serão adicionadas em breve.', 'lab-resumos-parceiros'); ?></p>
    </div>
    
    <?php endif; ?>
    
    <!-- Contato -->
    <div class="lrp-support-section">
        <h3><?php _e('Ainda tem dúvidas?', 'lab-resumos-parceiros'); ?></h3>
        <p><?php _e('Entre em contato conosco:', 'lab-resumos-parceiros'); ?></p>
        <p>
            <a href="mailto:parceiros@labresumos.com.br" class="lrp-btn lrp-btn-secondary">
                ✉️ parceiros@labresumos.com.br
            </a>
        </p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Accordion para FAQ
    document.querySelectorAll('.lrp-faq-question').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var expanded = this.getAttribute('aria-expanded') === 'true';
            var answerId = this.getAttribute('aria-controls');
            var answer = document.getElementById(answerId);
            
            // Fecha outros
            document.querySelectorAll('.lrp-faq-question[aria-expanded="true"]').forEach(function(openBtn) {
                if (openBtn !== btn) {
                    openBtn.setAttribute('aria-expanded', 'false');
                    openBtn.querySelector('.lrp-faq-icon').textContent = '+';
                    var openAnswer = document.getElementById(openBtn.getAttribute('aria-controls'));
                    openAnswer.style.maxHeight = null;
                }
            });
            
            // Toggle atual
            this.setAttribute('aria-expanded', !expanded);
            this.querySelector('.lrp-faq-icon').textContent = expanded ? '+' : '−';
            
            if (!expanded) {
                answer.style.maxHeight = answer.scrollHeight + 'px';
            } else {
                answer.style.maxHeight = null;
            }
        });
    });
});
</script>

<style>
.lrp-faq-answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
}
.lrp-faq-question[aria-expanded="true"] + .lrp-faq-answer {
    max-height: 500px;
}
</style>

