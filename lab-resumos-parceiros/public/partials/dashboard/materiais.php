<?php
/**
 * Dashboard do Afiliado - Tab: Materiais
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 * - $materials (array)
 * - $categories (array)
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_category = isset($_GET['category']) ? sanitize_key($_GET['category']) : 'all';

// Prepara os placeholders para substituição nos materiais
$material_placeholders = [
    '{cupom}'            => $affiliate->get_coupon_code(),
    '{link}'             => $affiliate->get_referral_url(),
    '{desconto_cliente}' => number_format($affiliate->get_customer_discount(), 0) . '%',
    '{nome_afiliado}'    => $affiliate->get_display_name(),
];

$replace_material_placeholders = function($text) use ($material_placeholders) {
    return str_replace(array_keys($material_placeholders), array_values($material_placeholders), $text);
};
?>

<div class="lrp-dashboard-materiais">
    <h2><?php _e('Materiais de Divulgação', 'lab-resumos-parceiros'); ?></h2>
    
    <p class="lrp-intro">
        <?php _e('Use estes materiais para divulgar os cursos do Lab Resumos. Clique para copiar ou baixar.', 'lab-resumos-parceiros'); ?>
    </p>
    
    <!-- Filtro por categoria -->
    <div class="lrp-material-filters">
        <a href="?tab=materiais&category=all" 
           class="lrp-filter-btn <?php echo $current_category === 'all' ? 'active' : ''; ?>">
            <?php _e('Todos', 'lab-resumos-parceiros'); ?>
        </a>
        <?php foreach ($categories as $slug => $name): ?>
        <a href="?tab=materiais&category=<?php echo esc_attr($slug); ?>" 
           class="lrp-filter-btn <?php echo $current_category === $slug ? 'active' : ''; ?>">
            <?php echo esc_html($name); ?>
        </a>
        <?php endforeach; ?>
    </div>
    
    <?php if (!empty($materials)): ?>
    
    <div class="lrp-materials-grid">
        <?php foreach ($materials as $material): ?>
        <div class="lrp-material-card lrp-material-<?php echo esc_attr($material->type); ?>">
            
            <?php if ($material->type === 'image' && $material->file_url): ?>
            <!-- Material: Imagem -->
            <div class="lrp-material-preview">
                <img src="<?php echo esc_url($material->file_url); ?>" 
                     alt="<?php echo esc_attr($material->title); ?>">
            </div>
            <div class="lrp-material-content">
                <h4><?php echo esc_html($material->title); ?></h4>
                <?php if ($material->description): ?>
                    <p><?php echo esc_html($material->description); ?></p>
                <?php endif; ?>
                <div class="lrp-material-actions">
                    <a href="<?php echo esc_url($material->file_url); ?>" 
                       class="lrp-btn lrp-btn-sm lrp-btn-primary" 
                       download>
                        📥 <?php _e('Baixar', 'lab-resumos-parceiros'); ?>
                    </a>
                </div>
            </div>
            
            <?php elseif ($material->type === 'text'): ?>
            <!-- Material: Texto/Copy -->
            <div class="lrp-material-content">
                <h4><?php echo esc_html($material->title); ?></h4>
                <?php if ($material->description): ?>
                    <p class="lrp-material-desc"><?php echo esc_html($replace_material_placeholders($material->description)); ?></p>
                <?php endif; ?>
                <div class="lrp-copy-text" id="lrp-copy-<?php echo $material->id; ?>">
                    <?php 
                    // Substitui todos os placeholders
                    $content = $replace_material_placeholders($material->content);
                    echo nl2br(esc_html($content)); 
                    ?>
                </div>
                <div class="lrp-material-actions">
                    <button type="button" 
                            class="lrp-btn lrp-btn-sm lrp-btn-secondary lrp-btn-copy-text" 
                            data-content="<?php echo esc_attr($content); ?>">
                        📋 <?php _e('Copiar Texto', 'lab-resumos-parceiros'); ?>
                    </button>
                </div>
            </div>
            
            <?php elseif ($material->type === 'video'): ?>
            <!-- Material: Vídeo -->
            <div class="lrp-material-preview lrp-video-preview">
                <?php if (strpos($material->file_url, 'youtube') !== false || strpos($material->file_url, 'youtu.be') !== false): ?>
                    <?php 
                    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/', $material->file_url, $matches);
                    $video_id = $matches[1] ?? '';
                    ?>
                    <iframe src="https://www.youtube.com/embed/<?php echo esc_attr($video_id); ?>" 
                            frameborder="0" allowfullscreen></iframe>
                <?php else: ?>
                    <video controls>
                        <source src="<?php echo esc_url($material->file_url); ?>" type="video/mp4">
                    </video>
                <?php endif; ?>
            </div>
            <div class="lrp-material-content">
                <h4><?php echo esc_html($material->title); ?></h4>
                <?php if ($material->description): ?>
                    <p><?php echo esc_html($material->description); ?></p>
                <?php endif; ?>
            </div>
            
            <?php elseif ($material->type === 'document'): ?>
            <!-- Material: Documento -->
            <div class="lrp-material-icon">
                📄
            </div>
            <div class="lrp-material-content">
                <h4><?php echo esc_html($material->title); ?></h4>
                <?php if ($material->description): ?>
                    <p><?php echo esc_html($material->description); ?></p>
                <?php endif; ?>
                <div class="lrp-material-actions">
                    <a href="<?php echo esc_url($material->file_url); ?>" 
                       class="lrp-btn lrp-btn-sm lrp-btn-primary" 
                       download>
                        📥 <?php _e('Baixar', 'lab-resumos-parceiros'); ?>
                    </a>
                </div>
            </div>
            
            <?php endif; ?>
            
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php else: ?>
    
    <div class="lrp-empty-state">
        <div class="lrp-empty-icon">📁</div>
        <h3><?php _e('Nenhum material disponível', 'lab-resumos-parceiros'); ?></h3>
        <p><?php _e('Os materiais de divulgação serão adicionados em breve.', 'lab-resumos-parceiros'); ?></p>
    </div>
    
    <?php endif; ?>
    
    <!-- Dicas -->
    <div class="lrp-tips-section">
        <h3>💡 <?php _e('Dicas de Uso', 'lab-resumos-parceiros'); ?></h3>
        <ul>
            <li><?php _e('Os textos já contêm seu cupom e link personalizados - é só copiar e colar!', 'lab-resumos-parceiros'); ?></li>
            <li><?php _e('Use os banners em suas redes sociais para atrair mais clientes.', 'lab-resumos-parceiros'); ?></li>
            <li><?php _e('Adapte os textos para sua audiência, mantendo as informações principais.', 'lab-resumos-parceiros'); ?></li>
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Copiar texto
    document.querySelectorAll('.lrp-btn-copy-text').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var content = this.getAttribute('data-content');
            navigator.clipboard.writeText(content).then(function() {
                btn.textContent = '✅ Copiado!';
                setTimeout(function() {
                    btn.textContent = '📋 Copiar Texto';
                }, 2000);
            });
        });
    });
});
</script>

