<?php
/**
 * Admin - FAQ
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Processa ações
if (isset($_POST['action']) && $_POST['action'] === 'save_faq') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'lrp_save_faq')) {
        wp_die(__('Ação não autorizada', 'lab-resumos-parceiros'));
    }
    
    $result = LRP_Admin_FAQ::save($_POST);
    if ($result) {
        echo '<div class="notice notice-success"><p>' . __('FAQ salvo com sucesso!', 'lab-resumos-parceiros') . '</p></div>';
    }
}

if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'lrp_delete_faq_' . $_GET['delete'])) {
        LRP_Admin_FAQ::delete((int) $_GET['delete']);
        echo '<div class="notice notice-success"><p>' . __('FAQ excluído com sucesso!', 'lab-resumos-parceiros') . '</p></div>';
    }
}

// Busca FAQs
$faqs = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}lrp_faq ORDER BY category, display_order, created_at DESC"
);

$categories = LRP_Admin_FAQ::get_categories();

// Edição
$editing = null;
if (isset($_GET['edit'])) {
    $editing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}lrp_faq WHERE id = %d",
        (int) $_GET['edit']
    ));
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Perguntas Frequentes (FAQ)', 'lab-resumos-parceiros'); ?></h1>
    <a href="?page=lrp-faq&add=1" class="page-title-action"><?php _e('Adicionar Novo', 'lab-resumos-parceiros'); ?></a>
    <hr class="wp-header-end">
    
    <?php if (isset($_GET['add']) || $editing): ?>
    <!-- Formulário -->
    <div class="lrp-admin-form">
        <h2><?php echo $editing ? __('Editar FAQ', 'lab-resumos-parceiros') : __('Novo FAQ', 'lab-resumos-parceiros'); ?></h2>
        
        <form method="post">
            <?php wp_nonce_field('lrp_save_faq'); ?>
            <input type="hidden" name="action" value="save_faq">
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?php echo $editing->id; ?>">
            <?php endif; ?>
            
            <!-- Caixa de Placeholders -->
            <div class="lrp-placeholders-box" style="background: #f0f6fc; border: 1px solid #c3d9ed; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
                <h3 style="margin-top: 0; color: #1d4ed8;">
                    <span class="dashicons dashicons-shortcode" style="margin-right: 5px;"></span>
                    <?php _e('Placeholders Dinâmicos', 'lab-resumos-parceiros'); ?>
                </h3>
                <p style="margin-bottom: 10px; color: #555;">
                    <?php _e('Use os placeholders abaixo para exibir valores personalizados de cada afiliado. Os valores serão substituídos automaticamente quando o afiliado visualizar o FAQ.', 'lab-resumos-parceiros'); ?>
                </p>
                <div class="lrp-placeholders-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px;">
                    <div class="lrp-placeholder-item" style="background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <code style="background: #e8f0fe; padding: 2px 6px; border-radius: 3px; font-size: 13px;">{comissao_cupom}</code>
                            <small style="display: block; color: #666; margin-top: 3px;"><?php _e('Comissão por cupom (ex: 10%)', 'lab-resumos-parceiros'); ?></small>
                        </div>
                        <button type="button" class="button button-small lrp-copy-placeholder" data-clipboard="{comissao_cupom}" title="<?php esc_attr_e('Copiar', 'lab-resumos-parceiros'); ?>">
                            <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
                        </button>
                    </div>
                    <div class="lrp-placeholder-item" style="background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <code style="background: #e8f0fe; padding: 2px 6px; border-radius: 3px; font-size: 13px;">{comissao_link}</code>
                            <small style="display: block; color: #666; margin-top: 3px;"><?php _e('Comissão por link (ex: 5%)', 'lab-resumos-parceiros'); ?></small>
                        </div>
                        <button type="button" class="button button-small lrp-copy-placeholder" data-clipboard="{comissao_link}" title="<?php esc_attr_e('Copiar', 'lab-resumos-parceiros'); ?>">
                            <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
                        </button>
                    </div>
                    <div class="lrp-placeholder-item" style="background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <code style="background: #e8f0fe; padding: 2px 6px; border-radius: 3px; font-size: 13px;">{comissao_l2}</code>
                            <small style="display: block; color: #666; margin-top: 3px;"><?php _e('Comissão nível 2 / rede (ex: 3%)', 'lab-resumos-parceiros'); ?></small>
                        </div>
                        <button type="button" class="button button-small lrp-copy-placeholder" data-clipboard="{comissao_l2}" title="<?php esc_attr_e('Copiar', 'lab-resumos-parceiros'); ?>">
                            <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
                        </button>
                    </div>
                    <div class="lrp-placeholder-item" style="background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <code style="background: #e8f0fe; padding: 2px 6px; border-radius: 3px; font-size: 13px;">{comissao_l3}</code>
                            <small style="display: block; color: #666; margin-top: 3px;"><?php _e('Comissão nível 3 (ex: 1%)', 'lab-resumos-parceiros'); ?></small>
                        </div>
                        <button type="button" class="button button-small lrp-copy-placeholder" data-clipboard="{comissao_l3}" title="<?php esc_attr_e('Copiar', 'lab-resumos-parceiros'); ?>">
                            <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
                        </button>
                    </div>
                    <div class="lrp-placeholder-item" style="background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <code style="background: #e8f0fe; padding: 2px 6px; border-radius: 3px; font-size: 13px;">{desconto_cliente}</code>
                            <small style="display: block; color: #666; margin-top: 3px;"><?php _e('Desconto para o cliente (ex: 10%)', 'lab-resumos-parceiros'); ?></small>
                        </div>
                        <button type="button" class="button button-small lrp-copy-placeholder" data-clipboard="{desconto_cliente}" title="<?php esc_attr_e('Copiar', 'lab-resumos-parceiros'); ?>">
                            <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
                        </button>
                    </div>
                    <div class="lrp-placeholder-item" style="background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <code style="background: #e8f0fe; padding: 2px 6px; border-radius: 3px; font-size: 13px;">{cookie_dias}</code>
                            <small style="display: block; color: #666; margin-top: 3px;"><?php _e('Dias de duração do cookie (ex: 60)', 'lab-resumos-parceiros'); ?></small>
                        </div>
                        <button type="button" class="button button-small lrp-copy-placeholder" data-clipboard="{cookie_dias}" title="<?php esc_attr_e('Copiar', 'lab-resumos-parceiros'); ?>">
                            <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
                        </button>
                    </div>
                    <div class="lrp-placeholder-item" style="background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <code style="background: #e8f0fe; padding: 2px 6px; border-radius: 3px; font-size: 13px;">{cupom}</code>
                            <small style="display: block; color: #666; margin-top: 3px;"><?php _e('Código do cupom do afiliado', 'lab-resumos-parceiros'); ?></small>
                        </div>
                        <button type="button" class="button button-small lrp-copy-placeholder" data-clipboard="{cupom}" title="<?php esc_attr_e('Copiar', 'lab-resumos-parceiros'); ?>">
                            <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
                        </button>
                    </div>
                </div>
                <p style="margin-top: 12px; margin-bottom: 0; font-size: 12px; color: #666;">
                    <span class="dashicons dashicons-info" style="font-size: 14px; vertical-align: middle;"></span>
                    <?php _e('Exemplo: "Você ganha {comissao_cupom} de comissão usando seu cupom {cupom}"', 'lab-resumos-parceiros'); ?>
                </p>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Copiar placeholders
                document.querySelectorAll('.lrp-copy-placeholder').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var text = this.getAttribute('data-clipboard');
                        navigator.clipboard.writeText(text).then(function() {
                            var icon = btn.querySelector('.dashicons');
                            icon.classList.remove('dashicons-clipboard');
                            icon.classList.add('dashicons-yes');
                            setTimeout(function() {
                                icon.classList.remove('dashicons-yes');
                                icon.classList.add('dashicons-clipboard');
                            }, 1500);
                        });
                    });
                });
                
                // Valores de exemplo para preview
                var exampleValues = {
                    '{comissao_cupom}': '10%',
                    '{comissao_link}': '5%',
                    '{comissao_l2}': '3%',
                    '{comissao_l3}': '1%',
                    '{desconto_cliente}': '10%',
                    '{cookie_dias}': '60',
                    '{cupom}': 'PARCEIRO123'
                };
                
                // Função para substituir placeholders
                function replacePlaceholders(text) {
                    for (var placeholder in exampleValues) {
                        text = text.split(placeholder).join('<mark style="background: #fff3cd; padding: 1px 4px; border-radius: 3px;">' + exampleValues[placeholder] + '</mark>');
                    }
                    return text;
                }
                
                // Função para atualizar preview
                function updatePreview() {
                    var questionInput = document.getElementById('question');
                    var previewQuestion = document.getElementById('lrp-preview-question');
                    var previewAnswer = document.getElementById('lrp-preview-answer');
                    
                    // Atualiza pergunta
                    var questionText = questionInput.value.trim();
                    if (questionText) {
                        previewQuestion.innerHTML = '<span>' + replacePlaceholders(questionText) + '</span><span style="color: #667eea;">+</span>';
                    } else {
                        previewQuestion.innerHTML = '<span style="color: #999;"><?php echo esc_js(__('(Digite a pergunta acima)', 'lab-resumos-parceiros')); ?></span><span style="color: #667eea;">+</span>';
                    }
                    
                    // Atualiza resposta (do editor TinyMCE ou textarea)
                    var answerContent = '';
                    if (typeof tinymce !== 'undefined' && tinymce.get('answer')) {
                        answerContent = tinymce.get('answer').getContent();
                    } else {
                        var answerTextarea = document.getElementById('answer');
                        if (answerTextarea) {
                            answerContent = answerTextarea.value;
                        }
                    }
                    
                    if (answerContent.trim()) {
                        previewAnswer.innerHTML = replacePlaceholders(answerContent);
                    } else {
                        previewAnswer.innerHTML = '<span style="color: #999;"><?php echo esc_js(__('(A resposta aparecerá aqui)', 'lab-resumos-parceiros')); ?></span>';
                    }
                }
                
                // Eventos para pergunta
                var questionInput = document.getElementById('question');
                if (questionInput) {
                    questionInput.addEventListener('input', updatePreview);
                    questionInput.addEventListener('change', updatePreview);
                }
                
                // Eventos para TinyMCE (resposta)
                function setupTinyMCEEvents() {
                    if (typeof tinymce !== 'undefined' && tinymce.get('answer')) {
                        tinymce.get('answer').on('keyup change input NodeChange', updatePreview);
                    }
                }
                
                // Aguarda TinyMCE carregar
                if (typeof tinymce !== 'undefined') {
                    tinymce.on('AddEditor', function(e) {
                        if (e.editor.id === 'answer') {
                            e.editor.on('init', function() {
                                setupTinyMCEEvents();
                                updatePreview();
                            });
                        }
                    });
                }
                
                // Fallback para textarea
                var answerTextarea = document.getElementById('answer');
                if (answerTextarea) {
                    answerTextarea.addEventListener('input', updatePreview);
                    answerTextarea.addEventListener('change', updatePreview);
                }
                
                // Atualização periódica como fallback
                setInterval(updatePreview, 1000);
                
                // Atualiza preview inicial
                setTimeout(updatePreview, 500);
            });
            </script>

            <table class="form-table">
                <tr>
                    <th><label for="question"><?php _e('Pergunta', 'lab-resumos-parceiros'); ?></label></th>
                    <td>
                        <input type="text" name="question" id="question" class="large-text" required
                               value="<?php echo esc_attr($editing->question ?? ''); ?>">
                        <p class="description"><?php _e('Você pode usar placeholders na pergunta também.', 'lab-resumos-parceiros'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="answer"><?php _e('Resposta', 'lab-resumos-parceiros'); ?></label></th>
                    <td>
                        <?php 
                        wp_editor(
                            $editing->answer ?? '',
                            'answer',
                            [
                                'textarea_name' => 'answer',
                                'textarea_rows' => 8,
                                'media_buttons' => false,
                                'teeny' => true,
                            ]
                        );
                        ?>
                        <p class="description"><?php _e('Use os placeholders acima para valores dinâmicos.', 'lab-resumos-parceiros'); ?></p>
                    </td>
                </tr>
                
                <!-- Pré-visualização -->
                <tr>
                    <th>
                        <label><?php _e('Pré-visualização', 'lab-resumos-parceiros'); ?></label>
                        <p class="description" style="font-weight: normal; margin-top: 5px;">
                            <?php _e('Como o afiliado verá', 'lab-resumos-parceiros'); ?>
                        </p>
                    </th>
                    <td>
                        <div id="lrp-faq-preview" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; padding: 0; overflow: hidden;">
                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 10px 15px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                                <span class="dashicons dashicons-visibility" style="font-size: 14px; margin-right: 5px;"></span>
                                <?php _e('Preview - Visão do Afiliado', 'lab-resumos-parceiros'); ?>
                            </div>
                            <div style="padding: 20px;">
                                <div class="lrp-preview-faq-item" style="border: 1px solid #e0e0e0; border-radius: 4px; background: #fff;">
                                    <div id="lrp-preview-question" style="padding: 15px; font-weight: 600; color: #333; cursor: pointer; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee;">
                                        <span><?php _e('(Digite a pergunta acima)', 'lab-resumos-parceiros'); ?></span>
                                        <span style="color: #667eea;">+</span>
                                    </div>
                                    <div id="lrp-preview-answer" style="padding: 15px; color: #555; line-height: 1.6; background: #fafafa;">
                                        <?php _e('(A resposta aparecerá aqui)', 'lab-resumos-parceiros'); ?>
                                    </div>
                                </div>
                                <p style="margin-top: 15px; margin-bottom: 0; font-size: 11px; color: #888; text-align: center;">
                                    <span class="dashicons dashicons-info" style="font-size: 12px;"></span>
                                    <?php _e('Valores de exemplo: Cupom=PARCEIRO123, Comissão Cupom=10%, Link=5%, L2=3%, L3=1%, Desconto=10%, Cookie=60 dias', 'lab-resumos-parceiros'); ?>
                                </p>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="category"><?php _e('Categoria', 'lab-resumos-parceiros'); ?></label></th>
                    <td>
                        <select name="category" id="category">
                            <?php foreach ($categories as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($editing->category ?? 'geral', $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="display_order"><?php _e('Ordem', 'lab-resumos-parceiros'); ?></label></th>
                    <td>
                        <input type="number" name="display_order" id="display_order" class="small-text" min="0"
                               value="<?php echo (int) ($editing->display_order ?? 0); ?>">
                        <p class="description"><?php _e('Ordem de exibição dentro da categoria (menor = primeiro).', 'lab-resumos-parceiros'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php checked($editing->is_active ?? true); ?>>
                            <?php _e('Ativo', 'lab-resumos-parceiros'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <?php submit_button($editing ? __('Atualizar FAQ', 'lab-resumos-parceiros') : __('Criar FAQ', 'lab-resumos-parceiros')); ?>
            <a href="?page=lrp-faq" class="button"><?php _e('Cancelar', 'lab-resumos-parceiros'); ?></a>
        </form>
    </div>
    
    <?php else: ?>
    <!-- Lista de FAQs agrupados por categoria -->
    <?php
    $grouped = [];
    foreach ($faqs as $faq) {
        $cat = $faq->category ?: 'geral';
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }
        $grouped[$cat][] = $faq;
    }
    ?>
    
    <?php if (empty($grouped)): ?>
        <p><?php _e('Nenhum FAQ cadastrado.', 'lab-resumos-parceiros'); ?></p>
    <?php else: ?>
        <?php foreach ($grouped as $category => $cat_faqs): ?>
        <h2><?php echo esc_html($categories[$category] ?? ucfirst($category)); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 40%;"><?php _e('Pergunta', 'lab-resumos-parceiros'); ?></th>
                    <th><?php _e('Resposta (prévia)', 'lab-resumos-parceiros'); ?></th>
                    <th style="width: 60px;"><?php _e('Ordem', 'lab-resumos-parceiros'); ?></th>
                    <th style="width: 80px;"><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                    <th style="width: 150px;"><?php _e('Ações', 'lab-resumos-parceiros'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cat_faqs as $faq): ?>
                <tr>
                    <td><strong><?php echo esc_html($faq->question); ?></strong></td>
                    <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags($faq->answer), 15)); ?></td>
                    <td><?php echo (int) $faq->display_order; ?></td>
                    <td>
                        <?php if ($faq->is_active): ?>
                            <span class="lrp-badge lrp-badge-success"><?php _e('Ativo', 'lab-resumos-parceiros'); ?></span>
                        <?php else: ?>
                            <span class="lrp-badge lrp-badge-secondary"><?php _e('Inativo', 'lab-resumos-parceiros'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=lrp-faq&edit=<?php echo $faq->id; ?>" class="button button-small">
                            <?php _e('Editar', 'lab-resumos-parceiros'); ?>
                        </a>
                        <a href="?page=lrp-faq&delete=<?php echo $faq->id; ?>&_wpnonce=<?php echo wp_create_nonce('lrp_delete_faq_' . $faq->id); ?>" 
                           class="button button-small button-link-delete"
                           onclick="return confirm('<?php _e('Tem certeza?', 'lab-resumos-parceiros'); ?>')">
                            <?php _e('Excluir', 'lab-resumos-parceiros'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <br>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>

