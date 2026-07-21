<?php
/**
 * Template do Dashboard Principal
 *
 * @package LR_Recuperacao_Vendas
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variáveis disponíveis: $cases, $stats, $filters, $users, $per_page, $current_page
?>

<div class="wrap lr-recovery-dashboard">
    <h1 class="wp-heading-inline">
        🔄 <?php esc_html_e('Recuperação de Vendas', 'lr-recuperacao-vendas'); ?>
    </h1>
    <?php 
    // Verificar se há pedidos para importar
    $pending_import = lr_recovery()->dashboard->get_failed_orders_without_case();
    if (!empty($pending_import)):
    ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=lr-recuperacao-vendas&view=import')); ?>" class="page-title-action">
            📥 <?php printf(esc_html__('Importar %d pedidos', 'lr-recuperacao-vendas'), count($pending_import)); ?>
        </a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <!-- Estatísticas -->
    <div class="lr-stats-grid">
        <div class="lr-stat-card lr-stat-novo">
            <div class="lr-stat-number"><?php echo esc_html($stats['novo']); ?></div>
            <div class="lr-stat-label"><?php esc_html_e('Novos', 'lr-recuperacao-vendas'); ?></div>
        </div>
        <div class="lr-stat-card lr-stat-atendimento">
            <div class="lr-stat-number"><?php echo esc_html($stats['em_atendimento']); ?></div>
            <div class="lr-stat-label"><?php esc_html_e('Em Atendimento', 'lr-recuperacao-vendas'); ?></div>
        </div>
        <div class="lr-stat-card lr-stat-aguardando">
            <div class="lr-stat-number"><?php echo esc_html($stats['aguardando_cliente']); ?></div>
            <div class="lr-stat-label"><?php esc_html_e('Aguardando', 'lr-recuperacao-vendas'); ?></div>
        </div>
        <div class="lr-stat-card lr-stat-resolvido">
            <div class="lr-stat-number"><?php echo esc_html($stats['resolvido']); ?></div>
            <div class="lr-stat-label"><?php esc_html_e('Resolvidos', 'lr-recuperacao-vendas'); ?></div>
        </div>
        <div class="lr-stat-card lr-stat-valor">
            <div class="lr-stat-number"><?php echo wc_price($stats['total_recuperado']); ?></div>
            <div class="lr-stat-label"><?php esc_html_e('Valor Recuperado', 'lr-recuperacao-vendas'); ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="lr-filters-box">
        <form method="get" action="">
            <input type="hidden" name="page" value="lr-recuperacao-vendas">
            
            <div class="lr-filters-row">
                <div class="lr-filter-item">
                    <label for="filter-status"><?php esc_html_e('Status:', 'lr-recuperacao-vendas'); ?></label>
                    <select name="status" id="filter-status">
                        <option value=""><?php esc_html_e('Todos', 'lr-recuperacao-vendas'); ?></option>
                        <option value="novo" <?php selected($filters['status'], 'novo'); ?>><?php esc_html_e('Novo', 'lr-recuperacao-vendas'); ?></option>
                        <option value="em_atendimento" <?php selected($filters['status'], 'em_atendimento'); ?>><?php esc_html_e('Em atendimento', 'lr-recuperacao-vendas'); ?></option>
                        <option value="aguardando_cliente" <?php selected($filters['status'], 'aguardando_cliente'); ?>><?php esc_html_e('Aguardando cliente', 'lr-recuperacao-vendas'); ?></option>
                        <option value="resolvido" <?php selected($filters['status'], 'resolvido'); ?>><?php esc_html_e('Resolvido', 'lr-recuperacao-vendas'); ?></option>
                        <option value="abandonado" <?php selected($filters['status'], 'abandonado'); ?>><?php esc_html_e('Abandonado', 'lr-recuperacao-vendas'); ?></option>
                    </select>
                </div>

                <div class="lr-filter-item">
                    <label for="filter-assigned"><?php esc_html_e('Responsável:', 'lr-recuperacao-vendas'); ?></label>
                    <select name="assigned_to" id="filter-assigned">
                        <option value=""><?php esc_html_e('Todos', 'lr-recuperacao-vendas'); ?></option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($filters['assigned_to'], $user->ID); ?>>
                                <?php echo esc_html($user->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="lr-filter-item">
                    <label for="filter-type"><?php esc_html_e('Tipo de Falha:', 'lr-recuperacao-vendas'); ?></label>
                    <select name="failure_type" id="filter-type">
                        <option value=""><?php esc_html_e('Todos', 'lr-recuperacao-vendas'); ?></option>
                        <option value="antifraude" <?php selected($filters['failure_type'], 'antifraude'); ?>><?php esc_html_e('Antifraude', 'lr-recuperacao-vendas'); ?></option>
                        <option value="banco" <?php selected($filters['failure_type'], 'banco'); ?>><?php esc_html_e('Banco recusou', 'lr-recuperacao-vendas'); ?></option>
                        <option value="retentativas" <?php selected($filters['failure_type'], 'retentativas'); ?>><?php esc_html_e('Retentativas', 'lr-recuperacao-vendas'); ?></option>
                        <option value="outro" <?php selected($filters['failure_type'], 'outro'); ?>><?php esc_html_e('Outro', 'lr-recuperacao-vendas'); ?></option>
                    </select>
                </div>

                <div class="lr-filter-item">
                    <label for="filter-date-from"><?php esc_html_e('De:', 'lr-recuperacao-vendas'); ?></label>
                    <input type="date" name="date_from" id="filter-date-from" value="<?php echo esc_attr($filters['date_from']); ?>">
                </div>

                <div class="lr-filter-item">
                    <label for="filter-date-to"><?php esc_html_e('Até:', 'lr-recuperacao-vendas'); ?></label>
                    <input type="date" name="date_to" id="filter-date-to" value="<?php echo esc_attr($filters['date_to']); ?>">
                </div>

                <div class="lr-filter-item lr-filter-actions">
                    <button type="submit" class="button"><?php esc_html_e('Filtrar', 'lr-recuperacao-vendas'); ?></button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=lr-recuperacao-vendas')); ?>" class="button"><?php esc_html_e('Limpar', 'lr-recuperacao-vendas'); ?></a>
                </div>
            </div>
        </form>
    </div>

    <!-- Lista de Casos -->
    <div class="lr-cases-list">
        <?php if (empty($cases)): ?>
            <div class="lr-empty-state">
                <div class="lr-empty-icon">📭</div>
                <p><?php esc_html_e('Nenhum caso encontrado.', 'lr-recuperacao-vendas'); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-status" style="width: 120px;"><?php esc_html_e('Status', 'lr-recuperacao-vendas'); ?></th>
                        <th class="column-order"><?php esc_html_e('Pedido', 'lr-recuperacao-vendas'); ?></th>
                        <th class="column-customer"><?php esc_html_e('Cliente', 'lr-recuperacao-vendas'); ?></th>
                        <th class="column-value" style="width: 100px;"><?php esc_html_e('Valor', 'lr-recuperacao-vendas'); ?></th>
                        <th class="column-type" style="width: 140px;"><?php esc_html_e('Tipo', 'lr-recuperacao-vendas'); ?></th>
                        <th class="column-assigned" style="width: 120px;"><?php esc_html_e('Responsável', 'lr-recuperacao-vendas'); ?></th>
                        <th class="column-date" style="width: 140px;"><?php esc_html_e('Data', 'lr-recuperacao-vendas'); ?></th>
                        <th class="column-actions" style="width: 200px;"><?php esc_html_e('Ações', 'lr-recuperacao-vendas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cases as $case): 
                        $order = wc_get_order($case->order_id);
                        if (!$order) continue;
                        
                        $assigned_user = $case->assigned_to ? get_userdata($case->assigned_to) : null;
                    ?>
                        <tr class="lr-case-row lr-case-status-<?php echo esc_attr($case->status); ?>">
                            <td class="column-status">
                                <span class="lr-badge lr-badge-<?php echo esc_attr($case->status); ?>">
                                    <?php echo esc_html(LR_Admin_Dashboard::get_status_icon($case->status) . ' ' . LR_Admin_Dashboard::get_status_label($case->status)); ?>
                                </span>
                            </td>
                            <td class="column-order">
                                <strong>#<?php echo esc_html($case->order_id); ?></strong>
                            </td>
                            <td class="column-customer">
                                <div class="lr-customer-info">
                                    <strong><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></strong>
                                    <br>
                                    <span class="lr-customer-phone">
                                        📱 <?php echo esc_html($order->get_meta('billing_cellphone') ?: $order->get_billing_phone()); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="column-value">
                                <strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong>
                            </td>
                            <td class="column-type">
                                <?php echo esc_html(LR_Admin_Dashboard::get_failure_type_icon($case->failure_type) . ' ' . LR_Admin_Dashboard::get_failure_type_label($case->failure_type)); ?>
                            </td>
                            <td class="column-assigned">
                                <?php if ($assigned_user): ?>
                                    <?php echo esc_html($assigned_user->display_name); ?>
                                <?php else: ?>
                                    <span class="lr-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-date">
                                <?php 
                                $date = new DateTime($case->created_at);
                                echo esc_html($date->format('d/m/Y H:i')); 
                                ?>
                            </td>
                            <td class="column-actions">
                                <?php if ($case->status === 'novo' && !$case->assigned_to): ?>
                                    <button type="button" 
                                            class="button button-small lr-btn-assign" 
                                            data-case-id="<?php echo esc_attr($case->id); ?>">
                                        <?php esc_html_e('Assumir', 'lr-recuperacao-vendas'); ?>
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=lr-recuperacao-vendas&case=' . $case->order_id)); ?>" 
                                   class="button button-small button-primary">
                                    <?php esc_html_e('Ver Detalhes', 'lr-recuperacao-vendas'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            // Paginação
            $total_cases = lr_recovery()->manager->count_cases($filters['status'] ?: '');
            $total_pages = ceil($total_cases / $per_page);
            
            if ($total_pages > 1):
            ?>
                <div class="lr-pagination">
                    <?php
                    $pagination_args = [
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'current' => $current_page,
                        'total' => $total_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ];
                    echo paginate_links($pagination_args);
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
