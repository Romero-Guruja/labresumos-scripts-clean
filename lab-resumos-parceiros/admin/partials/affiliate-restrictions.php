<?php
/**
 * Seção de Restrições de Produtos do Afiliado
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.3.0
 */

if (!defined('ABSPATH')) exit;

$restriction_handler = LRP_Product_Restriction::instance();
$restrictions = $restriction_handler->get_all_restrictions($affiliate->get_id(), true);
$current_mode = $restriction_handler->get_restriction_mode($affiliate->get_id());
?>

<div class="lrp-metabox lrp-metabox-restrictions" style="margin-top: 20px;">
    <div class="lrp-metabox-header">
        <?php _e('Restrições de Produtos', 'lab-resumos-parceiros'); ?>
        <span class="lrp-tooltip" data-tooltip="<?php esc_attr_e('Configure quais produtos este afiliado pode ou não promover. Blacklist = pode promover tudo EXCETO os listados. Whitelist = só pode promover os listados.', 'lab-resumos-parceiros'); ?>">?</span>
    </div>
    <div class="lrp-metabox-content">
        
        <!-- Modo atual -->
        <?php if ($current_mode): ?>
        <div class="lrp-restriction-mode-badge lrp-mode-<?php echo esc_attr($current_mode); ?>">
            <?php if ($current_mode === 'whitelist'): ?>
                🔒 <?php _e('Modo Whitelist: Afiliado só pode promover produtos listados', 'lab-resumos-parceiros'); ?>
            <?php else: ?>
                🚫 <?php _e('Modo Blacklist: Afiliado pode promover tudo EXCETO os listados', 'lab-resumos-parceiros'); ?>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="lrp-restriction-mode-badge lrp-mode-none">
            ✅ <?php _e('Sem restrições: Afiliado pode promover todos os produtos', 'lab-resumos-parceiros'); ?>
        </div>
        <?php endif; ?>
        
        <!-- Lista de restrições ativas -->
        <div class="lrp-restrictions-list" id="lrp-restrictions-list">
            <?php if (!empty($restrictions)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 80px;"><?php _e('Modo', 'lab-resumos-parceiros'); ?></th>
                            <th style="width: 100px;"><?php _e('Tipo', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Item', 'lab-resumos-parceiros'); ?></th>
                            <th style="width: 120px;"><?php _e('Início', 'lab-resumos-parceiros'); ?></th>
                            <th style="width: 120px;"><?php _e('Fim', 'lab-resumos-parceiros'); ?></th>
                            <th style="width: 150px;"><?php _e('Motivo', 'lab-resumos-parceiros'); ?></th>
                            <th style="width: 80px;"><?php _e('Ações', 'lab-resumos-parceiros'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($restrictions as $r): ?>
                        <tr data-restriction-id="<?php echo esc_attr($r->id); ?>">
                            <td>
                                <span class="lrp-mode-tag lrp-mode-<?php echo esc_attr($r->restriction_mode); ?>">
                                    <?php echo $r->restriction_mode === 'whitelist' ? '✅' : '🚫'; ?>
                                    <?php echo esc_html(ucfirst($r->restriction_mode)); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $r->item_type === 'product' ? '📦' : '📁'; ?>
                                <?php echo $r->item_type === 'product' ? __('Produto', 'lab-resumos-parceiros') : __('Categoria', 'lab-resumos-parceiros'); ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html($restriction_handler->get_item_name($r->item_type, $r->item_id)); ?></strong>
                                <small style="color: #666;">(ID: <?php echo esc_html($r->item_id); ?>)</small>
                            </td>
                            <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($r->start_date))); ?></td>
                            <td>
                                <?php if ($r->end_date): ?>
                                    <?php echo esc_html(date_i18n('d/m/Y', strtotime($r->end_date))); ?>
                                <?php else: ?>
                                    <em><?php _e('Permanente', 'lab-resumos-parceiros'); ?></em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r->reason): ?>
                                    <span title="<?php echo esc_attr($r->reason); ?>">
                                        <?php echo esc_html(wp_trim_words($r->reason, 5)); ?>
                                    </span>
                                <?php else: ?>
                                    <em>-</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small lrp-remove-restriction" 
                                        data-id="<?php echo esc_attr($r->id); ?>" 
                                        title="<?php esc_attr_e('Remover restrição', 'lab-resumos-parceiros'); ?>">
                                    🗑️
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="lrp-empty-restrictions">
                    <?php _e('Nenhuma restrição configurada para este afiliado.', 'lab-resumos-parceiros'); ?>
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Formulário para adicionar nova restrição -->
        <div class="lrp-add-restriction-form" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h4><?php _e('Adicionar Nova Restrição', 'lab-resumos-parceiros'); ?></h4>
            
            <table class="form-table">
                <tr>
                    <th>
                        <?php _e('Modo', 'lab-resumos-parceiros'); ?>
                        <span class="lrp-tooltip" data-tooltip="<?php esc_attr_e('Blacklist: excluir produtos. Whitelist: apenas permitir produtos específicos.', 'lab-resumos-parceiros'); ?>">?</span>
                    </th>
                    <td>
                        <select name="restriction_mode" id="lrp-restriction-mode" class="regular-text">
                            <option value="blacklist"><?php _e('🚫 Blacklist (Excluir)', 'lab-resumos-parceiros'); ?></option>
                            <option value="whitelist"><?php _e('✅ Whitelist (Apenas permitir)', 'lab-resumos-parceiros'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Tipo', 'lab-resumos-parceiros'); ?></th>
                    <td>
                        <select name="item_type" id="lrp-item-type" class="regular-text">
                            <option value="product"><?php _e('📦 Produto', 'lab-resumos-parceiros'); ?></option>
                            <option value="category"><?php _e('📁 Categoria', 'lab-resumos-parceiros'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>
                        <?php _e('Produto/Categoria', 'lab-resumos-parceiros'); ?>
                    </th>
                    <td>
                        <select name="item_id" id="lrp-item-select" class="regular-text lrp-select2" style="width: 100%; max-width: 400px;">
                            <option value=""><?php _e('Buscar produto ou categoria...', 'lab-resumos-parceiros'); ?></option>
                        </select>
                        <p class="description"><?php _e('Digite para buscar produtos ou categorias.', 'lab-resumos-parceiros'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Data Início', 'lab-resumos-parceiros'); ?></th>
                    <td>
                        <input type="date" name="start_date" id="lrp-start-date" class="regular-text" 
                               value="<?php echo esc_attr(date('Y-m-d')); ?>">
                    </td>
                </tr>
                <tr>
                    <th>
                        <?php _e('Data Fim', 'lab-resumos-parceiros'); ?>
                        <span class="lrp-tooltip" data-tooltip="<?php esc_attr_e('Deixe vazio para restrição permanente.', 'lab-resumos-parceiros'); ?>">?</span>
                    </th>
                    <td>
                        <input type="date" name="end_date" id="lrp-end-date" class="regular-text">
                        <p class="description"><?php _e('Deixe vazio para permanente.', 'lab-resumos-parceiros'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Motivo (opcional)', 'lab-resumos-parceiros'); ?></th>
                    <td>
                        <input type="text" name="reason" id="lrp-reason" class="large-text" 
                               placeholder="<?php esc_attr_e('Ex: Promoção exclusiva, acordo especial...', 'lab-resumos-parceiros'); ?>">
                    </td>
                </tr>
            </table>
            
            <p>
                <button type="button" class="button button-primary" id="lrp-add-restriction-btn">
                    <?php _e('Adicionar Restrição', 'lab-resumos-parceiros'); ?>
                </button>
            </p>
        </div>
    </div>
</div>

<style>
.lrp-restriction-mode-badge {
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 15px;
    font-weight: 500;
}
.lrp-restriction-mode-badge.lrp-mode-whitelist {
    background: #e8f5e9;
    color: #2e7d32;
    border-left: 4px solid #2e7d32;
}
.lrp-restriction-mode-badge.lrp-mode-blacklist {
    background: #ffebee;
    color: #c62828;
    border-left: 4px solid #c62828;
}
.lrp-restriction-mode-badge.lrp-mode-none {
    background: #f5f5f5;
    color: #666;
    border-left: 4px solid #999;
}
.lrp-mode-tag {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
}
.lrp-mode-tag.lrp-mode-whitelist {
    background: #e8f5e9;
    color: #2e7d32;
}
.lrp-mode-tag.lrp-mode-blacklist {
    background: #ffebee;
    color: #c62828;
}
.lrp-empty-restrictions {
    color: #666;
    font-style: italic;
    padding: 20px;
    text-align: center;
    background: #f9f9f9;
    border-radius: 4px;
}
.lrp-restrictions-list table {
    margin-top: 10px;
}
.lrp-remove-restriction {
    cursor: pointer;
}
.lrp-remove-restriction:hover {
    color: #c62828;
}
</style>

<script>
jQuery(document).ready(function($) {
    var affiliateId = <?php echo (int) $affiliate->get_id(); ?>;
    
    // Inicializa Select2 para busca de produtos/categorias
    function initSelect2() {
        var itemType = $('#lrp-item-type').val();
        
        $('#lrp-item-select').select2({
            placeholder: itemType === 'product' ? '<?php _e('Buscar produto...', 'lab-resumos-parceiros'); ?>' : '<?php _e('Buscar categoria...', 'lab-resumos-parceiros'); ?>',
            minimumInputLength: 2,
            ajax: {
                url: lrp_admin.ajax_url,
                dataType: 'json',
                delay: 300,
                data: function(params) {
                    return {
                        action: 'lrp_search_items',
                        nonce: lrp_admin.nonce,
                        search: params.term,
                        type: itemType
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.data || []
                    };
                }
            }
        });
    }
    
    initSelect2();
    
    // Recarrega Select2 quando muda o tipo
    $('#lrp-item-type').on('change', function() {
        $('#lrp-item-select').val(null).trigger('change');
        initSelect2();
    });
    
    // Adicionar restrição
    $('#lrp-add-restriction-btn').on('click', function() {
        var $btn = $(this);
        var itemId = $('#lrp-item-select').val();
        
        if (!itemId) {
            alert('<?php _e('Selecione um produto ou categoria.', 'lab-resumos-parceiros'); ?>');
            return;
        }
        
        $btn.prop('disabled', true).text('<?php _e('Salvando...', 'lab-resumos-parceiros'); ?>');
        
        $.post(lrp_admin.ajax_url, {
            action: 'lrp_add_product_restriction',
            nonce: lrp_admin.nonce,
            affiliate_id: affiliateId,
            restriction_mode: $('#lrp-restriction-mode').val(),
            item_type: $('#lrp-item-type').val(),
            item_id: itemId,
            start_date: $('#lrp-start-date').val(),
            end_date: $('#lrp-end-date').val(),
            reason: $('#lrp-reason').val()
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || '<?php _e('Erro ao adicionar restrição.', 'lab-resumos-parceiros'); ?>');
                $btn.prop('disabled', false).text('<?php _e('Adicionar Restrição', 'lab-resumos-parceiros'); ?>');
            }
        }).fail(function() {
            alert('<?php _e('Erro de conexão.', 'lab-resumos-parceiros'); ?>');
            $btn.prop('disabled', false).text('<?php _e('Adicionar Restrição', 'lab-resumos-parceiros'); ?>');
        });
    });
    
    // Remover restrição
    $(document).on('click', '.lrp-remove-restriction', function() {
        if (!confirm('<?php _e('Tem certeza que deseja remover esta restrição?', 'lab-resumos-parceiros'); ?>')) {
            return;
        }
        
        var $btn = $(this);
        var restrictionId = $btn.data('id');
        
        $btn.prop('disabled', true);
        
        $.post(lrp_admin.ajax_url, {
            action: 'lrp_remove_product_restriction',
            nonce: lrp_admin.nonce,
            restriction_id: restrictionId
        }, function(response) {
            if (response.success) {
                $btn.closest('tr').fadeOut(300, function() {
                    $(this).remove();
                    // Se não há mais restrições, recarrega
                    if ($('#lrp-restrictions-list tbody tr').length === 0) {
                        location.reload();
                    }
                });
            } else {
                alert(response.data.message || '<?php _e('Erro ao remover restrição.', 'lab-resumos-parceiros'); ?>');
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert('<?php _e('Erro de conexão.', 'lab-resumos-parceiros'); ?>');
            $btn.prop('disabled', false);
        });
    });
});
</script>

