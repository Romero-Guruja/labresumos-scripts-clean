<?php
/**
 * Template da Página de Importação de Pedidos
 *
 * @package LR_Recuperacao_Vendas
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variáveis disponíveis: $failed_orders
?>

<div class="wrap lr-recovery-import">
    <h1 class="wp-heading-inline">
        📥 <?php esc_html_e('Importar Pedidos Existentes', 'lr-recuperacao-vendas'); ?>
    </h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=lr-recuperacao-vendas')); ?>" class="page-title-action">
        ← <?php esc_html_e('Voltar ao Dashboard', 'lr-recuperacao-vendas'); ?>
    </a>
    <hr class="wp-header-end">

    <div class="lr-import-intro">
        <div class="lr-card">
            <h3>ℹ️ <?php esc_html_e('Sobre a Importação', 'lr-recuperacao-vendas'); ?></h3>
            <p><?php esc_html_e('Esta página lista os pedidos com status "Malsucedido" que ainda não foram importados para o sistema de recuperação.', 'lr-recuperacao-vendas'); ?></p>
            <p><?php esc_html_e('Selecione os pedidos que deseja importar e clique em "Importar Selecionados".', 'lr-recuperacao-vendas'); ?></p>
        </div>
    </div>

    <?php if (empty($failed_orders)): ?>
        <div class="lr-card">
            <div class="lr-empty-state">
                <div class="lr-empty-icon">✅</div>
                <p><?php esc_html_e('Nenhum pedido pendente de importação!', 'lr-recuperacao-vendas'); ?></p>
                <p class="description"><?php esc_html_e('Todos os pedidos com status "Malsucedido" já foram importados para o sistema de recuperação.', 'lr-recuperacao-vendas'); ?></p>
            </div>
        </div>
    <?php else: ?>
        <form method="post" action="">
            <?php wp_nonce_field('lr_import_orders_nonce'); ?>
            
            <div class="lr-import-actions-top">
                <div class="lr-select-actions">
                    <button type="button" class="button lr-btn-select-all"><?php esc_html_e('Selecionar Todos', 'lr-recuperacao-vendas'); ?></button>
                    <button type="button" class="button lr-btn-select-none"><?php esc_html_e('Desmarcar Todos', 'lr-recuperacao-vendas'); ?></button>
                    <span class="lr-selected-count">
                        <?php printf(
                            esc_html__('%d pedidos encontrados', 'lr-recuperacao-vendas'),
                            count($failed_orders)
                        ); ?>
                    </span>
                </div>
                <button type="submit" name="lr_import_orders" class="button button-primary button-large">
                    📥 <?php esc_html_e('Importar Selecionados', 'lr-recuperacao-vendas'); ?>
                </button>
            </div>

            <div class="lr-card">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" id="lr-select-all-checkbox">
                            </th>
                            <th><?php esc_html_e('Pedido', 'lr-recuperacao-vendas'); ?></th>
                            <th><?php esc_html_e('Cliente', 'lr-recuperacao-vendas'); ?></th>
                            <th><?php esc_html_e('Valor', 'lr-recuperacao-vendas'); ?></th>
                            <th><?php esc_html_e('Produtos', 'lr-recuperacao-vendas'); ?></th>
                            <th><?php esc_html_e('Data', 'lr-recuperacao-vendas'); ?></th>
                            <th><?php esc_html_e('Tipo Provável', 'lr-recuperacao-vendas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failed_orders as $order): 
                            $order_id = $order->get_id();
                            $failure_info = lr_recovery()->manager->extract_failure_reason($order_id);
                            
                            // Produtos
                            $products = [];
                            foreach ($order->get_items() as $item) {
                                $products[] = $item->get_name();
                            }
                        ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="order_ids[]" value="<?php echo esc_attr($order_id); ?>" class="lr-order-checkbox">
                                </th>
                                <td>
                                    <strong>#<?php echo esc_html($order_id); ?></strong>
                                    <div class="row-actions">
                                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $order_id . '&action=edit')); ?>" target="_blank">
                                            <?php esc_html_e('Ver pedido', 'lr-recuperacao-vendas'); ?>
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></strong>
                                    <br>
                                    <span class="description">
                                        <?php echo esc_html($order->get_billing_email()); ?>
                                    </span>
                                    <br>
                                    <span class="description">
                                        📱 <?php echo esc_html($order->get_meta('billing_cellphone') ?: $order->get_billing_phone()); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    $products_display = array_slice($products, 0, 2);
                                    echo esc_html(implode(', ', $products_display));
                                    if (count($products) > 2) {
                                        echo ' <em>+' . (count($products) - 2) . '</em>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $date = $order->get_date_created();
                                    if ($date) {
                                        echo esc_html($date->date_i18n('d/m/Y H:i'));
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($failure_info['type'] !== 'outro'): ?>
                                        <span class="lr-badge lr-badge-<?php echo esc_attr($failure_info['type']); ?>">
                                            <?php echo esc_html(LR_Admin_Dashboard::get_failure_type_icon($failure_info['type']) . ' ' . LR_Admin_Dashboard::get_failure_type_label($failure_info['type'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="description"><?php esc_html_e('Não identificado', 'lr-recuperacao-vendas'); ?></span>
                                    <?php endif; ?>
                                    <?php if ($failure_info['is_antifraud']): ?>
                                        <br><small class="lr-antifraud-hint">⚠️ <?php esc_html_e('Provável antifraude', 'lr-recuperacao-vendas'); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="lr-import-actions-bottom">
                <button type="submit" name="lr_import_orders" class="button button-primary button-large">
                    📥 <?php esc_html_e('Importar Selecionados', 'lr-recuperacao-vendas'); ?>
                </button>
            </div>
        </form>

        <script>
        jQuery(document).ready(function($) {
            // Selecionar/Desmarcar todos
            $('#lr-select-all-checkbox, .lr-btn-select-all').on('click', function() {
                $('.lr-order-checkbox').prop('checked', true);
                updateSelectedCount();
            });
            
            $('.lr-btn-select-none').on('click', function() {
                $('.lr-order-checkbox').prop('checked', false);
                $('#lr-select-all-checkbox').prop('checked', false);
                updateSelectedCount();
            });
            
            // Atualizar contador
            $('.lr-order-checkbox').on('change', function() {
                updateSelectedCount();
            });
            
            function updateSelectedCount() {
                var total = $('.lr-order-checkbox').length;
                var selected = $('.lr-order-checkbox:checked').length;
                var text = selected > 0 
                    ? selected + ' de ' + total + ' selecionados'
                    : total + ' pedidos encontrados';
                $('.lr-selected-count').text(text);
            }
        });
        </script>
    <?php endif; ?>
</div>

<style>
.lr-recovery-import .lr-import-intro {
    margin: 20px 0;
}

.lr-recovery-import .lr-import-actions-top,
.lr-recovery-import .lr-import-actions-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 15px 0;
    padding: 15px;
    background: #fff;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.lr-recovery-import .lr-select-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.lr-recovery-import .lr-selected-count {
    color: #666;
    font-style: italic;
}

.lr-recovery-import .check-column {
    width: 40px;
    padding: 10px !important;
}

.lr-recovery-import .row-actions a {
    color: #2271b1;
    text-decoration: none;
    font-size: 12px;
}

.lr-recovery-import .row-actions a:hover {
    text-decoration: underline;
}

.lr-recovery-import .lr-badge-antifraude {
    background: #f8d7da;
    color: #721c24;
}

.lr-recovery-import .lr-badge-banco {
    background: #fff3cd;
    color: #856404;
}

.lr-recovery-import .lr-badge-retentativas {
    background: #d1ecf1;
    color: #0c5460;
}

.lr-recovery-import .lr-antifraud-hint {
    color: #856404;
    font-size: 11px;
}

.lr-recovery-import .lr-empty-state {
    text-align: center;
    padding: 40px 20px;
}

.lr-recovery-import .lr-empty-icon {
    font-size: 48px;
    margin-bottom: 10px;
}
</style>
