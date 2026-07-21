<?php
/**
 * Admin - Logs de Atividade
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

// Filtros
$filter_action = isset($_GET['filter_action']) ? sanitize_key($_GET['filter_action']) : '';
$filter_affiliate = isset($_GET['filter_affiliate']) ? (int) $_GET['filter_affiliate'] : 0;
$paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$per_page = 50;

// Exportar
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'lrp_export_logs')) {
        wp_die(__('Ação não autorizada', 'lab-resumos-parceiros'));
    }
    
    LRP_Exporter::export_logs([
        'start_date' => isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null,
        'end_date' => isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null,
    ]);
    exit;
}

// Busca logs
$args = [
    'action' => $filter_action ?: null,
    'affiliate_id' => $filter_affiliate ?: null,
    'limit' => $per_page,
    'offset' => ($paged - 1) * $per_page,
];

$logs = LRP_Logger::get_logs($args);
$total = LRP_Logger::count_logs($args);
$total_pages = ceil($total / $per_page);

$actions = LRP_Logger::get_available_actions();

// Afiliados para filtro
global $wpdb;
$affiliates = $wpdb->get_results(
    "SELECT a.id, u.display_name 
     FROM {$wpdb->prefix}lrp_affiliates a 
     JOIN {$wpdb->users} u ON a.user_id = u.ID 
     ORDER BY u.display_name"
);
?>

<div class="wrap">
    <h1><?php _e('Logs de Atividade', 'lab-resumos-parceiros'); ?></h1>
    
    <!-- Filtros -->
    <div class="lrp-admin-filters">
        <form method="get">
            <input type="hidden" name="page" value="lrp-reports">
            <input type="hidden" name="view" value="logs">
            
            <select name="filter_action">
                <option value=""><?php _e('Todas as ações', 'lab-resumos-parceiros'); ?></option>
                <?php foreach ($actions as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($filter_action, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="filter_affiliate">
                <option value=""><?php _e('Todos os afiliados', 'lab-resumos-parceiros'); ?></option>
                <?php foreach ($affiliates as $aff): ?>
                    <option value="<?php echo $aff->id; ?>" <?php selected($filter_affiliate, $aff->id); ?>>
                        <?php echo esc_html($aff->display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="button"><?php _e('Filtrar', 'lab-resumos-parceiros'); ?></button>
            
            <a href="?page=lrp-reports&view=logs&export=csv&_wpnonce=<?php echo wp_create_nonce('lrp_export_logs'); ?>" 
               class="button">
                <?php _e('Exportar CSV', 'lab-resumos-parceiros'); ?>
            </a>
        </form>
    </div>
    
    <!-- Tabela de logs -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 150px;"><?php _e('Data/Hora', 'lab-resumos-parceiros'); ?></th>
                <th style="width: 150px;"><?php _e('Ação', 'lab-resumos-parceiros'); ?></th>
                <th style="width: 150px;"><?php _e('Afiliado', 'lab-resumos-parceiros'); ?></th>
                <th style="width: 150px;"><?php _e('Usuário', 'lab-resumos-parceiros'); ?></th>
                <th><?php _e('Detalhes', 'lab-resumos-parceiros'); ?></th>
                <th style="width: 120px;"><?php _e('IP', 'lab-resumos-parceiros'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="6"><?php _e('Nenhum log encontrado.', 'lab-resumos-parceiros'); ?></td>
            </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <?php $details = LRP_Logger::format_details($log['details']); ?>
                <tr>
                    <td>
                        <?php echo date('d/m/Y', strtotime($log['created_at'])); ?>
                        <br>
                        <small><?php echo date('H:i:s', strtotime($log['created_at'])); ?></small>
                    </td>
                    <td>
                        <span class="lrp-badge lrp-badge-<?php echo strpos($log['action'], 'error') !== false ? 'danger' : 'info'; ?>">
                            <?php echo esc_html($actions[$log['action']] ?? $log['action']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($log['affiliate_id']): ?>
                            <?php 
                            $aff = new LRP_Affiliate($log['affiliate_id']);
                            echo esc_html($aff->get_display_name());
                            ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($log['user_id']): ?>
                            <?php 
                            $user = get_userdata($log['user_id']);
                            echo $user ? esc_html($user->display_name) : '—';
                            ?>
                        <?php else: ?>
                            <?php _e('Sistema', 'lab-resumos-parceiros'); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small><?php echo esc_html($details['description'] ?? ''); ?></small>
                        <?php if (!empty($details['context'])): ?>
                            <br>
                            <code style="font-size: 10px;">
                                <?php echo esc_html(wp_json_encode($details['context'], JSON_UNESCAPED_UNICODE)); ?>
                            </code>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small><?php echo esc_html($log['ip_address'] ?: '—'); ?></small>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Paginação -->
    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php printf(__('%d itens', 'lab-resumos-parceiros'), $total); ?>
            </span>
            <span class="pagination-links">
                <?php if ($paged > 1): ?>
                    <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $paged - 1)); ?>">
                        &laquo;
                    </a>
                <?php endif; ?>
                
                <span class="paging-input">
                    <?php printf(__('%d de %d', 'lab-resumos-parceiros'), $paged, $total_pages); ?>
                </span>
                
                <?php if ($paged < $total_pages): ?>
                    <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $paged + 1)); ?>">
                        &raquo;
                    </a>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="lrp-info-box" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #2A6B9F;">
        <p>
            <strong><?php _e('Nota:', 'lab-resumos-parceiros'); ?></strong>
            <?php _e('Os logs são automaticamente limpos após 90 dias para conformidade com LGPD.', 'lab-resumos-parceiros'); ?>
        </p>
    </div>
</div>

