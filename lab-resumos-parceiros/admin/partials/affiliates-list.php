<?php
/**
 * Lista de Afiliados
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) exit;

$current_status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
?>
<div class="wrap lrp-admin-wrap">
    <h1>
        👥 <?php _e('Afiliados', 'lab-resumos-parceiros'); ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&action=add')); ?>" class="page-title-action">
            <?php _e('Adicionar Novo', 'lab-resumos-parceiros'); ?>
        </a>
    </h1>
    
    <ul class="subsubsub">
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates')); ?>" <?php echo !$current_status ? 'class="current"' : ''; ?>>
                <?php _e('Todos', 'lab-resumos-parceiros'); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&status=active')); ?>" <?php echo $current_status === 'active' ? 'class="current"' : ''; ?>>
                <?php _e('Ativos', 'lab-resumos-parceiros'); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&status=pending')); ?>" <?php echo $current_status === 'pending' ? 'class="current"' : ''; ?>>
                <?php _e('Pendentes', 'lab-resumos-parceiros'); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&status=inactive')); ?>" <?php echo $current_status === 'inactive' ? 'class="current"' : ''; ?>>
                <?php _e('Inativos', 'lab-resumos-parceiros'); ?>
            </a>
        </li>
    </ul>
    
    <form method="get" style="float: right; margin-top: 10px;">
        <input type="hidden" name="page" value="lrp-affiliates">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar...', 'lab-resumos-parceiros'); ?>">
        <input type="submit" class="button" value="<?php esc_attr_e('Buscar', 'lab-resumos-parceiros'); ?>">
    </form>
    
    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th width="30">#</th>
                <th><?php _e('Nome', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Email', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Cupom', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Vendas', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Receita', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Comissões', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Cadastro', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Ações', 'lab-resumos-parceiros'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($affiliates['items'])): ?>
            <tr>
                <td colspan="10"><?php _e('Nenhum afiliado encontrado.', 'lab-resumos-parceiros'); ?></td>
            </tr>
            <?php else: ?>
            <?php foreach ($affiliates['items'] as $a): ?>
            <tr>
                <td><?php echo esc_html($a->id); ?></td>
                <td>
                    <strong>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&action=view&id=' . $a->id)); ?>">
                            <?php echo esc_html($a->display_name); ?>
                        </a>
                    </strong>
                </td>
                <td><?php echo esc_html($a->user_email); ?></td>
                <td><code><?php echo esc_html($a->coupon_code); ?></code></td>
                <td><span class="lrp-badge lrp-badge-<?php echo esc_attr($a->status); ?>"><?php echo esc_html($a->status); ?></span></td>
                <td><?php echo esc_html($a->total_sales); ?></td>
                <td>R$ <?php echo esc_html(number_format($a->total_revenue, 2, ',', '.')); ?></td>
                <td>R$ <?php echo esc_html(number_format($a->total_commissions, 2, ',', '.')); ?></td>
                <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($a->created_at))); ?></td>
                <td>
                    <?php if ($a->status === 'pending'): ?>
                        <button type="button" class="button button-small button-primary lrp-approve-affiliate" data-id="<?php echo esc_attr($a->id); ?>">
                            ✓ <?php _e('Aprovar', 'lab-resumos-parceiros'); ?>
                        </button>
                        <button type="button" class="button button-small lrp-reject-affiliate" data-id="<?php echo esc_attr($a->id); ?>">
                            ✕ <?php _e('Rejeitar', 'lab-resumos-parceiros'); ?>
                        </button>
                    <?php else: ?>
                        <?php 
                        $dashboard_page_id = get_option('lrp_dashboard_page_id');
                        if ($dashboard_page_id):
                            $preview_url = add_query_arg('preview_as', $a->id, get_permalink($dashboard_page_id));
                        ?>
                        <a href="<?php echo esc_url($preview_url); ?>" class="button button-small" target="_blank" title="<?php esc_attr_e('Ver como afiliado', 'lab-resumos-parceiros'); ?>">
                            👁️
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&action=edit&id=' . $a->id)); ?>" class="button button-small">
                            <?php _e('Editar', 'lab-resumos-parceiros'); ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($affiliates['pages'] > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            echo paginate_links([
                'base'    => add_query_arg('paged', '%#%'),
                'format'  => '',
                'current' => $affiliates['current'],
                'total'   => $affiliates['pages'],
            ]);
            ?>
        </div>
    </div>
    <?php endif; ?>
    
    <p>
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=lrp_export_csv&type=affiliates'), 'lrp_admin_nonce', 'nonce')); ?>" class="button">
            📥 <?php _e('Exportar CSV', 'lab-resumos-parceiros'); ?>
        </a>
    </p>
</div>

