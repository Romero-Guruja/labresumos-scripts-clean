<?php
/**
 * Admin - Materiais de Divulgação
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Processa ações
if (isset($_POST['action']) && $_POST['action'] === 'save_material') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'lrp_save_material')) {
        wp_die(__('Ação não autorizada', 'lab-resumos-parceiros'));
    }
    
    $result = LRP_Admin_Materials::save($_POST);
    if ($result) {
        echo '<div class="notice notice-success"><p>' . __('Material salvo com sucesso!', 'lab-resumos-parceiros') . '</p></div>';
    }
}

if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'lrp_delete_material_' . $_GET['delete'])) {
        LRP_Admin_Materials::delete((int) $_GET['delete']);
        echo '<div class="notice notice-success"><p>' . __('Material excluído com sucesso!', 'lab-resumos-parceiros') . '</p></div>';
    }
}

// Busca materiais
$materials = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}lrp_materials ORDER BY display_order, created_at DESC"
);

$categories = LRP_Admin_Materials::get_categories();
$types = LRP_Admin_Materials::get_types();

// Edição
$editing = null;
if (isset($_GET['edit'])) {
    $editing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}lrp_materials WHERE id = %d",
        (int) $_GET['edit']
    ));
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Materiais de Divulgação', 'lab-resumos-parceiros'); ?></h1>
    <a href="?page=lrp-materials&add=1" class="page-title-action"><?php _e('Adicionar Novo', 'lab-resumos-parceiros'); ?></a>
    <hr class="wp-header-end">
    
    <?php if (isset($_GET['add']) || $editing): ?>
    <!-- Formulário -->
    <div class="lrp-admin-form">
        <h2><?php echo $editing ? __('Editar Material', 'lab-resumos-parceiros') : __('Novo Material', 'lab-resumos-parceiros'); ?></h2>
        
        <form method="post">
            <?php wp_nonce_field('lrp_save_material'); ?>
            <input type="hidden" name="action" value="save_material">
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?php echo $editing->id; ?>">
            <?php endif; ?>
            
            <!-- Caixa de Placeholders -->
            <div class="lrp-placeholders-box" style="background: #f0f6fc; border: 1px solid #c3d9ed; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
                <h3 style="margin-top: 0; color: #1d4ed8;">
                    <span class="dashicons dashicons-shortcode" style="margin-right: 5px;"></span>
                    <?php _e('Placeholders Dinâmicos para Textos', 'lab-resumos-parceiros'); ?>
                </h3>
                <p style="margin-bottom: 10px; color: #555;">
                    <?php _e('Use os placeholders abaixo em textos de divulgação. Eles serão substituídos pelos valores específicos de cada afiliado.', 'lab-resumos-parceiros'); ?>
                </p>
                <div class="lrp-placeholders-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px;">
                    <div class="lrp-placeholder-item" style="background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <code style="background: #e8f0fe; padding: 2px 6px; border-radius: 3px; font-size: 13px;">{cupom}</code>
                            <small style="display: block; color: #666; margin-top: 3px;"><?php _e('Código do cupom do afiliado', 'lab-resumos-parceiros'); ?></small>
                        </div>
                        <button type="button" class="button button-small lrp-copy-placeholder" data-clipboard="{cupom}" title="<?php esc_attr_e('Copiar', 'lab-resumos-parceiros'); ?>">
                            <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
                        </button>
                    </div>
                    <div class="lrp-placeholder-item" style="background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <code style="background: #e8f0fe; padding: 2px 6px; border-radius: 3px; font-size: 13px;">{link}</code>
                            <small style="display: block; color: #666; margin-top: 3px;"><?php _e('Link de afiliado personalizado', 'lab-resumos-parceiros'); ?></small>
                        </div>
                        <button type="button" class="button button-small lrp-copy-placeholder" data-clipboard="{link}" title="<?php esc_attr_e('Copiar', 'lab-resumos-parceiros'); ?>">
                            <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
                        </button>
                    </div>
                    <div class="lrp-placeholder-item" style="background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <code style="background: #e8f0fe; padding: 2px 6px; border-radius: 3px; font-size: 13px;">{desconto_cliente}</code>
                            <small style="display: block; color: #666; margin-top: 3px;"><?php _e('Desconto que o cliente recebe (ex: 10%)', 'lab-resumos-parceiros'); ?></small>
                        </div>
                        <button type="button" class="button button-small lrp-copy-placeholder" data-clipboard="{desconto_cliente}" title="<?php esc_attr_e('Copiar', 'lab-resumos-parceiros'); ?>">
                            <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
                        </button>
                    </div>
                    <div class="lrp-placeholder-item" style="background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <code style="background: #e8f0fe; padding: 2px 6px; border-radius: 3px; font-size: 13px;">{nome_afiliado}</code>
                            <small style="display: block; color: #666; margin-top: 3px;"><?php _e('Nome de exibição do afiliado', 'lab-resumos-parceiros'); ?></small>
                        </div>
                        <button type="button" class="button button-small lrp-copy-placeholder" data-clipboard="{nome_afiliado}" title="<?php esc_attr_e('Copiar', 'lab-resumos-parceiros'); ?>">
                            <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
                        </button>
                    </div>
                </div>
                <p style="margin-top: 12px; margin-bottom: 0; font-size: 12px; color: #666;">
                    <span class="dashicons dashicons-info" style="font-size: 14px; vertical-align: middle;"></span>
                    <?php _e('Exemplo: "Use o cupom {cupom} e ganhe {desconto_cliente} de desconto!"', 'lab-resumos-parceiros'); ?>
                </p>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
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
            });
            </script>

            <table class="form-table">
                <tr>
                    <th><label for="title"><?php _e('Título', 'lab-resumos-parceiros'); ?></label></th>
                    <td>
                        <input type="text" name="title" id="title" class="regular-text" required
                               value="<?php echo esc_attr($editing->title ?? ''); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="description"><?php _e('Descrição', 'lab-resumos-parceiros'); ?></label></th>
                    <td>
                        <textarea name="description" id="description" rows="3" class="large-text"><?php 
                            echo esc_textarea($editing->description ?? ''); 
                        ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label for="type"><?php _e('Tipo', 'lab-resumos-parceiros'); ?></label></th>
                    <td>
                        <select name="type" id="type" required>
                            <?php foreach ($types as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($editing->type ?? '', $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="category"><?php _e('Categoria', 'lab-resumos-parceiros'); ?></label></th>
                    <td>
                        <select name="category" id="category">
                            <?php foreach ($categories as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($editing->category ?? '', $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr class="lrp-field-file">
                    <th><label for="file_url"><?php _e('URL do Arquivo', 'lab-resumos-parceiros'); ?></label></th>
                    <td>
                        <input type="url" name="file_url" id="file_url" class="regular-text"
                               value="<?php echo esc_url($editing->file_url ?? ''); ?>">
                        <button type="button" class="button" id="lrp-upload-btn"><?php _e('Selecionar Arquivo', 'lab-resumos-parceiros'); ?></button>
                        <p class="description"><?php _e('Para imagens, vídeos ou documentos.', 'lab-resumos-parceiros'); ?></p>
                    </td>
                </tr>
                <tr class="lrp-field-content" style="display: none;">
                    <th><label for="content"><?php _e('Conteúdo (Texto)', 'lab-resumos-parceiros'); ?></label></th>
                    <td>
                        <textarea name="content" id="content" rows="6" class="large-text"><?php 
                            echo esc_textarea($editing->content ?? ''); 
                        ?></textarea>
                        <p class="description">
                            <?php _e('Use {cupom} e {link} como placeholders para o cupom e link do afiliado.', 'lab-resumos-parceiros'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="display_order"><?php _e('Ordem', 'lab-resumos-parceiros'); ?></label></th>
                    <td>
                        <input type="number" name="display_order" id="display_order" class="small-text" min="0"
                               value="<?php echo (int) ($editing->display_order ?? 0); ?>">
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
            
            <?php submit_button($editing ? __('Atualizar Material', 'lab-resumos-parceiros') : __('Criar Material', 'lab-resumos-parceiros')); ?>
            <a href="?page=lrp-materials" class="button"><?php _e('Cancelar', 'lab-resumos-parceiros'); ?></a>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Toggle campos baseado no tipo
        $('#type').on('change', function() {
            var type = $(this).val();
            if (type === 'text') {
                $('.lrp-field-file').hide();
                $('.lrp-field-content').show();
            } else {
                $('.lrp-field-file').show();
                $('.lrp-field-content').hide();
            }
        }).trigger('change');
        
        // Media uploader
        $('#lrp-upload-btn').on('click', function(e) {
            e.preventDefault();
            var frame = wp.media({
                title: '<?php _e('Selecionar Arquivo', 'lab-resumos-parceiros'); ?>',
                button: { text: '<?php _e('Usar este arquivo', 'lab-resumos-parceiros'); ?>' },
                multiple: false
            });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#file_url').val(attachment.url);
            });
            frame.open();
        });
    });
    </script>
    
    <?php else: ?>
    <!-- Lista de materiais -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Título', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Tipo', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Categoria', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Ordem', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Ações', 'lab-resumos-parceiros'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($materials)): ?>
            <tr>
                <td colspan="6"><?php _e('Nenhum material cadastrado.', 'lab-resumos-parceiros'); ?></td>
            </tr>
            <?php else: ?>
                <?php foreach ($materials as $material): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($material->title); ?></strong>
                        <?php if ($material->description): ?>
                            <br><small><?php echo esc_html(wp_trim_words($material->description, 10)); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($types[$material->type] ?? $material->type); ?></td>
                    <td><?php echo esc_html($categories[$material->category] ?? $material->category); ?></td>
                    <td><?php echo (int) $material->display_order; ?></td>
                    <td>
                        <?php if ($material->is_active): ?>
                            <span class="lrp-badge lrp-badge-success"><?php _e('Ativo', 'lab-resumos-parceiros'); ?></span>
                        <?php else: ?>
                            <span class="lrp-badge lrp-badge-secondary"><?php _e('Inativo', 'lab-resumos-parceiros'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=lrp-materials&edit=<?php echo $material->id; ?>" class="button button-small">
                            <?php _e('Editar', 'lab-resumos-parceiros'); ?>
                        </a>
                        <a href="?page=lrp-materials&delete=<?php echo $material->id; ?>&_wpnonce=<?php echo wp_create_nonce('lrp_delete_material_' . $material->id); ?>" 
                           class="button button-small button-link-delete"
                           onclick="return confirm('<?php _e('Tem certeza?', 'lab-resumos-parceiros'); ?>')">
                            <?php _e('Excluir', 'lab-resumos-parceiros'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

