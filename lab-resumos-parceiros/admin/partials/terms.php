<?php
/**
 * Admin - Gerenciamento de Termos de Afiliação
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$terms_manager = LRP_Terms::instance();
$current_terms = $terms_manager->get_terms();
$stats = $terms_manager->get_acceptance_stats();
$version_history = $terms_manager->get_version_history();

// Processa ações
$message = '';
$error = '';

if (isset($_POST['lrp_save_terms']) && wp_verify_nonce($_POST['lrp_terms_nonce'], 'lrp_save_terms')) {
    $new_version = sanitize_text_field($_POST['terms_version']);
    $title = sanitize_text_field($_POST['terms_title']);
    $intro = wp_kses_post($_POST['terms_intro']);
    $changelog = wp_kses_post($_POST['terms_changelog']);
    
    // Processa seções
    $sections = [];
    if (isset($_POST['section_id']) && is_array($_POST['section_id'])) {
        foreach ($_POST['section_id'] as $i => $id) {
            $sections[] = [
                'id' => sanitize_key($id),
                'title' => sanitize_text_field($_POST['section_title'][$i]),
                'content' => wp_kses_post($_POST['section_content'][$i]),
            ];
        }
    }
    
    // Valida
    if (empty($new_version) || empty($title) || empty($sections)) {
        $error = 'Preencha todos os campos obrigatórios.';
    } else {
        // Verifica se é nova versão
        $is_new = ($new_version !== $current_terms['version']);
        
        if ($is_new) {
            $result = $terms_manager->create_version([
                'version' => $new_version,
                'title' => $title,
                'intro' => $intro,
                'sections' => $sections,
                'changelog' => $changelog,
            ]);
            
            if ($result) {
                $message = 'Nova versão dos termos criada com sucesso! Os afiliados serão notificados.';
                // Recarrega dados
                $current_terms = $terms_manager->get_terms();
                $stats = $terms_manager->get_acceptance_stats();
            } else {
                $error = 'Erro ao salvar nova versão.';
            }
        } else {
            // Atualiza versão atual (sem notificar)
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'lrp_terms_versions',
                [
                    'title' => $title,
                    'intro' => $intro,
                    'sections' => wp_json_encode($sections),
                ],
                ['version' => $new_version]
            );
            $message = 'Termos atualizados com sucesso!';
            $current_terms = $terms_manager->get_terms();
        }
    }
}

$terms_page_id = get_option('lrp_terms_page_id');
$terms_url = $terms_page_id ? get_permalink($terms_page_id) : '';
?>

<div class="wrap lrp-admin-wrap">
    <h1 class="wp-heading-inline">
        📋 <?php _e('Termos de Afiliação', 'lab-resumos-parceiros'); ?>
    </h1>
    
    <?php if ($terms_url) : ?>
    <a href="<?php echo esc_url($terms_url); ?>" class="page-title-action" target="_blank">
        Ver Página Pública
    </a>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <?php if ($message) : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if ($error) : ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo esc_html($error); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Estatísticas -->
    <div class="lrp-terms-stats">
        <div class="lrp-stats-card">
            <span class="lrp-stats-icon">📄</span>
            <div class="lrp-stats-info">
                <span class="lrp-stats-value"><?php echo esc_html($stats['version']); ?></span>
                <span class="lrp-stats-label">Versão Atual</span>
            </div>
        </div>
        <div class="lrp-stats-card">
            <span class="lrp-stats-icon">✅</span>
            <div class="lrp-stats-info">
                <span class="lrp-stats-value"><?php echo esc_html($stats['total_accepted']); ?></span>
                <span class="lrp-stats-label">Aceitaram</span>
            </div>
        </div>
        <div class="lrp-stats-card">
            <span class="lrp-stats-icon">⏳</span>
            <div class="lrp-stats-info">
                <span class="lrp-stats-value"><?php echo esc_html($stats['total_pending']); ?></span>
                <span class="lrp-stats-label">Pendentes</span>
            </div>
        </div>
        <div class="lrp-stats-card">
            <span class="lrp-stats-icon">📊</span>
            <div class="lrp-stats-info">
                <span class="lrp-stats-value"><?php echo esc_html($stats['acceptance_rate']); ?>%</span>
                <span class="lrp-stats-label">Taxa de Aceite</span>
            </div>
        </div>
    </div>
    
    <div class="lrp-terms-container">
        <div class="lrp-terms-main">
            <!-- Formulário de Edição -->
            <form method="post" class="lrp-terms-form">
                <?php wp_nonce_field('lrp_save_terms', 'lrp_terms_nonce'); ?>
                
                <div class="lrp-form-card">
                    <h2>📝 Informações Básicas</h2>
                    
                    <div class="lrp-form-row">
                        <div class="lrp-form-group" style="width: 30%;">
                            <label for="terms_version">Versão *</label>
                            <input type="text" id="terms_version" name="terms_version" 
                                   value="<?php echo esc_attr($current_terms['version']); ?>" 
                                   placeholder="Ex: 1.0, 2.0" required>
                            <p class="description">Altere para criar nova versão e notificar afiliados.</p>
                        </div>
                        <div class="lrp-form-group" style="width: 70%;">
                            <label for="terms_title">Título *</label>
                            <input type="text" id="terms_title" name="terms_title" 
                                   value="<?php echo esc_attr($current_terms['title']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="lrp-form-group">
                        <label for="terms_intro">Introdução</label>
                        <textarea id="terms_intro" name="terms_intro" rows="3"><?php echo esc_textarea($current_terms['intro']); ?></textarea>
                    </div>
                    
                    <div class="lrp-form-group">
                        <label for="terms_changelog">Changelog (para nova versão)</label>
                        <textarea id="terms_changelog" name="terms_changelog" rows="2" 
                                  placeholder="Descreva o que mudou nesta versão..."></textarea>
                        <p class="description">Será enviado no email de notificação para os afiliados.</p>
                    </div>
                </div>
                
                <div class="lrp-form-card">
                    <h2>📑 Seções dos Termos</h2>
                    <p class="description">Arraste para reordenar. Use HTML para formatação (p, ul, ol, strong, em, h4, table).</p>
                    
                    <div id="lrp-sections-container">
                        <?php foreach ($current_terms['sections'] as $index => $section) : ?>
                        <div class="lrp-section-item" data-index="<?php echo $index; ?>">
                            <div class="lrp-section-header">
                                <span class="lrp-section-drag">☰</span>
                                <span class="lrp-section-number"><?php echo $index + 1; ?></span>
                                <input type="text" name="section_id[]" value="<?php echo esc_attr($section['id']); ?>" 
                                       placeholder="ID (ex: definicoes)" class="lrp-section-id">
                                <input type="text" name="section_title[]" value="<?php echo esc_attr($section['title']); ?>" 
                                       placeholder="Título da Seção" class="lrp-section-title">
                                <button type="button" class="lrp-section-toggle">▼</button>
                                <button type="button" class="lrp-section-remove" title="Remover">✕</button>
                            </div>
                            <div class="lrp-section-content">
                                <textarea name="section_content[]" rows="8" placeholder="Conteúdo HTML..."><?php echo esc_textarea($section['content']); ?></textarea>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" id="lrp-add-section" class="button">
                        ➕ Adicionar Seção
                    </button>
                </div>
                
                <div class="lrp-form-actions">
                    <button type="submit" name="lrp_save_terms" class="button button-primary button-large">
                        💾 Salvar Termos
                    </button>
                    <span class="lrp-save-note">
                        ⚠️ Alterar a versão irá notificar todos os afiliados ativos.
                    </span>
                </div>
            </form>
        </div>
        
        <div class="lrp-terms-sidebar">
            <!-- Histórico de Versões -->
            <div class="lrp-sidebar-card">
                <h3>📜 Histórico de Versões</h3>
                <?php if (!empty($version_history)) : ?>
                <ul class="lrp-version-list">
                    <?php foreach ($version_history as $version) : ?>
                    <li class="<?php echo $version['is_active'] ? 'active' : ''; ?>">
                        <strong>v<?php echo esc_html($version['version']); ?></strong>
                        <?php if ($version['is_active']) : ?>
                        <span class="lrp-badge lrp-badge-success">Atual</span>
                        <?php endif; ?>
                        <br>
                        <small><?php echo date_i18n('d/m/Y H:i', strtotime($version['created_at'])); ?></small>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else : ?>
                <p class="lrp-empty">Nenhuma versão salva ainda.</p>
                <?php endif; ?>
            </div>
            
            <!-- Ações Rápidas -->
            <div class="lrp-sidebar-card">
                <h3>⚡ Ações Rápidas</h3>
                <ul class="lrp-quick-actions">
                    <li>
                        <a href="<?php echo esc_url($terms_url); ?>" target="_blank" class="button">
                            👁️ Ver Página Pública
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo admin_url('admin.php?page=lrp-affiliates&terms_pending=1'); ?>" class="button">
                            📋 Ver Pendentes de Aceite
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Dicas -->
            <div class="lrp-sidebar-card lrp-tips">
                <h3>💡 Dicas</h3>
                <ul>
                    <li>Use <code>{cupom}</code> para inserir o cupom do afiliado dinamicamente.</li>
                    <li>Altere a versão (ex: 1.0 → 1.1) para notificar todos os afiliados.</li>
                    <li>O aceite é registrado com IP, data/hora e User Agent.</li>
                    <li>Afiliados e admins recebem email de confirmação.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.lrp-terms-stats {
    display: flex;
    gap: 20px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.lrp-stats-card {
    display: flex;
    align-items: center;
    gap: 15px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    flex: 1;
    min-width: 180px;
}

.lrp-stats-icon {
    font-size: 32px;
}

.lrp-stats-value {
    display: block;
    font-size: 28px;
    font-weight: 700;
    color: #2271b1;
}

.lrp-stats-label {
    display: block;
    font-size: 13px;
    color: #666;
}

.lrp-terms-container {
    display: flex;
    gap: 30px;
    margin-top: 20px;
}

.lrp-terms-main {
    flex: 1;
}

.lrp-terms-sidebar {
    width: 300px;
    flex-shrink: 0;
}

.lrp-form-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
}

.lrp-form-card h2 {
    margin: 0 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    font-size: 18px;
}

.lrp-form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.lrp-form-group {
    margin-bottom: 15px;
}

.lrp-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.lrp-form-group input[type="text"],
.lrp-form-group textarea {
    width: 100%;
}

.lrp-section-item {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin-bottom: 15px;
}

.lrp-section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    background: #fff;
    border-bottom: 1px solid #ddd;
    border-radius: 6px 6px 0 0;
}

.lrp-section-drag {
    cursor: move;
    color: #999;
    font-size: 18px;
}

.lrp-section-number {
    background: #2271b1;
    color: #fff;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 12px;
    font-weight: 700;
}

.lrp-section-id {
    width: 120px !important;
    font-family: monospace;
    font-size: 12px;
}

.lrp-section-title {
    flex: 1;
}

.lrp-section-toggle,
.lrp-section-remove {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    padding: 5px;
    color: #666;
}

.lrp-section-remove:hover {
    color: #d63638;
}

.lrp-section-content {
    padding: 15px;
}

.lrp-section-content textarea {
    font-family: monospace;
    font-size: 13px;
}

.lrp-section-item.collapsed .lrp-section-content {
    display: none;
}

.lrp-section-item.collapsed .lrp-section-toggle {
    transform: rotate(-90deg);
}

.lrp-form-actions {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px 0;
}

.lrp-save-note {
    color: #d63638;
    font-size: 13px;
}

.lrp-sidebar-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.lrp-sidebar-card h3 {
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    font-size: 15px;
}

.lrp-version-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.lrp-version-list li {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

.lrp-version-list li:last-child {
    border-bottom: none;
}

.lrp-version-list li.active {
    background: #f0f6fc;
    border-radius: 4px;
}

.lrp-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}

.lrp-badge-success {
    background: #d4edda;
    color: #155724;
}

.lrp-quick-actions {
    list-style: none;
    margin: 0;
    padding: 0;
}

.lrp-quick-actions li {
    margin-bottom: 10px;
}

.lrp-quick-actions .button {
    width: 100%;
    text-align: center;
}

.lrp-tips ul {
    margin: 0;
    padding-left: 20px;
}

.lrp-tips li {
    margin-bottom: 8px;
    font-size: 13px;
    color: #666;
}

.lrp-tips code {
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
}

.lrp-empty {
    color: #999;
    font-style: italic;
    text-align: center;
    padding: 10px;
}

@media (max-width: 1200px) {
    .lrp-terms-container {
        flex-direction: column;
    }
    
    .lrp-terms-sidebar {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle seção
    $(document).on('click', '.lrp-section-toggle', function() {
        $(this).closest('.lrp-section-item').toggleClass('collapsed');
    });
    
    // Remover seção
    $(document).on('click', '.lrp-section-remove', function() {
        if (confirm('Remover esta seção?')) {
            $(this).closest('.lrp-section-item').fadeOut(300, function() {
                $(this).remove();
                updateSectionNumbers();
            });
        }
    });
    
    // Adicionar seção
    $('#lrp-add-section').on('click', function() {
        var index = $('.lrp-section-item').length;
        var html = `
            <div class="lrp-section-item" data-index="${index}">
                <div class="lrp-section-header">
                    <span class="lrp-section-drag">☰</span>
                    <span class="lrp-section-number">${index + 1}</span>
                    <input type="text" name="section_id[]" value="" placeholder="ID (ex: nova-secao)" class="lrp-section-id">
                    <input type="text" name="section_title[]" value="" placeholder="Título da Seção" class="lrp-section-title">
                    <button type="button" class="lrp-section-toggle">▼</button>
                    <button type="button" class="lrp-section-remove" title="Remover">✕</button>
                </div>
                <div class="lrp-section-content">
                    <textarea name="section_content[]" rows="8" placeholder="Conteúdo HTML..."></textarea>
                </div>
            </div>
        `;
        $('#lrp-sections-container').append(html);
    });
    
    function updateSectionNumbers() {
        $('.lrp-section-item').each(function(i) {
            $(this).attr('data-index', i);
            $(this).find('.lrp-section-number').text(i + 1);
        });
    }
    
    // Drag and drop para reordenar seções
    if (typeof $.fn.sortable !== 'undefined') {
        $('#lrp-sections-container').sortable({
            handle: '.lrp-section-drag',
            update: function() {
                updateSectionNumbers();
            }
        });
    }
});
</script>
