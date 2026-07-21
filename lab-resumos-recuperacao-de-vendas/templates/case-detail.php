<?php
/**
 * Template de Detalhe do Caso
 *
 * @package LR_Recuperacao_Vendas
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variáveis disponíveis: $case, $order, $checklist, $logs, $failure_info, $external_urls, 
// $customer, $order_data, $assigned_user, $users, $whatsapp_url

// Verificar se já existe um link de autologin salvo
$saved_autologin_url = lr_recovery()->autologin->get_saved_autologin_url($order_data['id']);
$has_autologin = !empty($saved_autologin_url);

// Se tem link salvo, atualizar a URL do WhatsApp para incluir o link
if ($has_autologin) {
    $whatsapp_url = lr_recovery()->autologin->generate_whatsapp_url($order, $saved_autologin_url);
}
?>

<div class="wrap lr-recovery-case-detail">
    <div class="lr-case-header">
        <div class="lr-case-header-left">
            <a href="<?php echo esc_url(admin_url('admin.php?page=lr-recuperacao-vendas')); ?>" class="lr-back-link">
                ← <?php esc_html_e('Voltar', 'lr-recuperacao-vendas'); ?>
            </a>
            <h1>
                <?php 
                printf(
                    /* translators: %1$d: order ID, %2$s: customer name */
                    esc_html__('Caso #%1$d - %2$s', 'lr-recuperacao-vendas'),
                    $order_data['id'],
                    $customer['name']
                );
                ?>
            </h1>
        </div>
        <div class="lr-case-header-right">
            <span class="lr-badge lr-badge-<?php echo esc_attr($case->status); ?> lr-badge-large">
                <?php echo esc_html(LR_Admin_Dashboard::get_status_icon($case->status) . ' ' . LR_Admin_Dashboard::get_status_label($case->status)); ?>
            </span>
        </div>
    </div>

    <div class="lr-case-grid">
        <!-- Coluna Principal -->
        <div class="lr-case-main">
            <!-- Informações do Cliente e Pedido -->
            <div class="lr-card-row">
                <div class="lr-card lr-card-half">
                    <h3>👤 <?php esc_html_e('Informações do Cliente', 'lr-recuperacao-vendas'); ?></h3>
                    <div class="lr-info-list">
                        <div class="lr-info-item">
                            <strong><?php echo esc_html($customer['name']); ?></strong>
                        </div>
                        <div class="lr-info-item lr-info-copyable">
                            <span>📱 <?php echo esc_html($customer['cellphone']); ?></span>
                            <button type="button" class="lr-btn-copy" data-copy="<?php echo esc_attr($customer['cellphone']); ?>" title="<?php esc_attr_e('Copiar', 'lr-recuperacao-vendas'); ?>">📋</button>
                            <a href="<?php echo esc_attr($whatsapp_url); ?>" class="button button-small lr-btn-whatsapp-main" target="_blank">
                                WhatsApp <?php echo $has_autologin ? '(com link)' : ''; ?>
                            </a>
                        </div>
                        <div class="lr-info-item lr-info-copyable">
                            <span>📧 <?php echo esc_html($customer['email']); ?></span>
                            <button type="button" class="lr-btn-copy" data-copy="<?php echo esc_attr($customer['email']); ?>" title="<?php esc_attr_e('Copiar', 'lr-recuperacao-vendas'); ?>">📋</button>
                        </div>
                        <?php if (!empty($customer['cpf'])): ?>
                            <div class="lr-info-item">
                                <span>📄 CPF: <?php echo esc_html($customer['cpf']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="lr-card lr-card-half">
                    <h3>📦 <?php esc_html_e('Detalhes do Pedido', 'lr-recuperacao-vendas'); ?></h3>
                    <div class="lr-info-list">
                        <?php foreach ($order_data['items'] as $item): ?>
                            <div class="lr-info-item">
                                <strong><?php echo esc_html($item['name']); ?></strong>
                                <?php if ($item['quantity'] > 1): ?>
                                    <span class="lr-qty">(x<?php echo esc_html($item['quantity']); ?>)</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="lr-info-item lr-info-highlight">
                            <span>💰 <?php echo wp_kses_post($order_data['total']); ?></span>
                        </div>
                        <?php if (!empty($order_data['coupon'])): ?>
                            <div class="lr-info-item">
                                <span>🎫 Cupom: <?php echo esc_html($order_data['coupon']); ?> (-<?php echo wc_price($order_data['coupon_discount']); ?>)</span>
                            </div>
                        <?php endif; ?>
                        <div class="lr-info-item">
                            <span>📅 <?php echo esc_html($order_data['date']); ?></span>
                        </div>
                        <div class="lr-info-item">
                            <span>💳 <?php echo esc_html($order_data['payment_method']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Motivo da Falha -->
            <div class="lr-card lr-card-warning">
                <h3>⚠️ <?php esc_html_e('Motivo da Falha', 'lr-recuperacao-vendas'); ?></h3>
                <div class="lr-failure-info">
                    <p>
                        <strong><?php esc_html_e('Tipo:', 'lr-recuperacao-vendas'); ?></strong>
                        <?php echo esc_html(LR_Admin_Dashboard::get_failure_type_icon($failure_info['type']) . ' ' . LR_Admin_Dashboard::get_failure_type_label($failure_info['type'])); ?>
                    </p>
                    <?php if (!empty($failure_info['message'])): ?>
                        <p>
                            <strong><?php esc_html_e('Mensagem:', 'lr-recuperacao-vendas'); ?></strong>
                            <?php echo esc_html($failure_info['message']); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($failure_info['is_antifraud']): ?>
                        <div class="lr-tip">
                            💡 <?php esc_html_e('A mensagem "aprovada com sucesso" seguida de falha indica bloqueio por antifraude. Reprocessar sem antifraude na Pagar.me.', 'lr-recuperacao-vendas'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($failure_info['charge_id'])): ?>
                        <p>
                            <a href="<?php echo esc_url($external_urls['pagarme_charge'] . $failure_info['charge_id']); ?>" class="button" target="_blank">
                                🔗 <?php esc_html_e('Ver Cobrança na Pagar.me', 'lr-recuperacao-vendas'); ?>
                            </a>
                            <code><?php echo esc_html($failure_info['charge_id']); ?></code>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Checklist de Recuperação -->
            <div class="lr-card">
                <h3>✅ <?php esc_html_e('Checklist de Recuperação', 'lr-recuperacao-vendas'); ?></h3>
                <div class="lr-checklist">
                    <?php 
                    $index = 1;
                    foreach ($checklist as $key => $item): 
                    ?>
                        <div class="lr-checklist-item <?php echo $item['completed'] ? 'completed' : ''; ?>" data-item="<?php echo esc_attr($key); ?>">
                            <label class="lr-checkbox-label">
                                <input type="checkbox" 
                                       class="lr-checklist-checkbox" 
                                       data-case-id="<?php echo esc_attr($case->id); ?>"
                                       data-item="<?php echo esc_attr($key); ?>"
                                       <?php checked($item['completed']); ?>>
                                <span class="lr-checkbox-number"><?php echo $index; ?></span>
                                <span class="lr-checkbox-text">
                                    <?php echo esc_html($item['label']); ?>
                                    <?php if (!empty($item['description'])): ?>
                                        <small class="lr-checkbox-desc"><?php echo esc_html($item['description']); ?></small>
                                    <?php endif; ?>
                                </span>
                            </label>

                            <!-- Ações específicas por item -->
                            <div class="lr-checklist-actions">
                                <?php if ($key === 'contact_customer'): ?>
                                    <a href="<?php echo esc_attr($whatsapp_url); ?>" class="button button-small" target="_blank">
                                        📱 <?php esc_html_e('Abrir WhatsApp', 'lr-recuperacao-vendas'); ?>
                                    </a>
                                <?php elseif ($key === 'reprocess_payment'): ?>
                                    <a href="<?php echo esc_url($external_urls['pagarme_base']); ?>" class="button button-small" target="_blank">
                                        🔗 <?php esc_html_e('Abrir Pagar.me', 'lr-recuperacao-vendas'); ?>
                                    </a>
                                <?php elseif ($key === 'enroll_student'): ?>
                                    <a href="<?php echo esc_url($external_urls['edwiser_enrollment']); ?>" class="button button-small" target="_blank">
                                        🎓 <?php esc_html_e('Matrícula Manual', 'lr-recuperacao-vendas'); ?>
                                    </a>
                                    <?php foreach ($order_data['items'] as $item_data): ?>
                                        <small class="lr-course-id"><?php printf(esc_html__('Curso ID: %d', 'lr-recuperacao-vendas'), $item_data['product_id']); ?></small>
                                    <?php endforeach; ?>
                                <?php elseif ($key === 'complete_order'): ?>
                                    <button type="button" 
                                            class="button button-small lr-btn-complete-order" 
                                            data-order-id="<?php echo esc_attr($order_data['id']); ?>"
                                            data-case-id="<?php echo esc_attr($case->id); ?>"
                                            <?php echo $order->get_status() === 'completed' ? 'disabled' : ''; ?>>
                                        <?php echo $order->get_status() === 'completed' 
                                            ? esc_html__('✓ Já Concluído', 'lr-recuperacao-vendas') 
                                            : esc_html__('Marcar como Concluído', 'lr-recuperacao-vendas'); ?>
                                    </button>
                                <?php elseif ($key === 'issue_invoice'): ?>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $order_data['id'] . '&action=edit')); ?>" class="button button-small" target="_blank">
                                        📄 <?php esc_html_e('Abrir Pedido', 'lr-recuperacao-vendas'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php 
                    $index++;
                    endforeach; 
                    ?>
                </div>
            </div>

            <!-- Link de Autologin -->
            <div class="lr-card">
                <h3>🔗 <?php esc_html_e('Link de Recuperação (Autologin)', 'lr-recuperacao-vendas'); ?></h3>
                <p class="description"><?php esc_html_e('Caso o cliente prefira tentar pagar novamente:', 'lr-recuperacao-vendas'); ?></p>
                
                <div class="lr-autologin-section">
                    <?php if ($has_autologin): ?>
                        <div class="lr-autologin-exists">
                            <span class="lr-autologin-status">✅ <?php esc_html_e('Link ja gerado', 'lr-recuperacao-vendas'); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <button type="button" class="button lr-btn-generate-autologin" data-order-id="<?php echo esc_attr($order_data['id']); ?>" data-case-id="<?php echo esc_attr($case->id); ?>">
                        <?php echo $has_autologin ? esc_html__('Gerar Novo Link', 'lr-recuperacao-vendas') : esc_html__('Gerar Link de Autologin', 'lr-recuperacao-vendas'); ?>
                    </button>
                    
                    <div class="lr-autologin-result" <?php echo $has_autologin ? '' : 'style="display: none;"'; ?>>
                        <div class="lr-autologin-url-box">
                            <input type="text" class="lr-autologin-url" value="<?php echo esc_attr($saved_autologin_url); ?>" readonly>
                            <button type="button" class="button lr-btn-copy-autologin">📋 <?php esc_html_e('Copiar', 'lr-recuperacao-vendas'); ?></button>
                        </div>
                        <a href="<?php echo esc_attr($whatsapp_url); ?>" class="button button-primary lr-btn-whatsapp-autologin" target="_blank">
                            📱 <?php esc_html_e('Enviar via WhatsApp', 'lr-recuperacao-vendas'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Observações -->
            <div class="lr-card">
                <h3>📝 <?php esc_html_e('Observações do Atendimento', 'lr-recuperacao-vendas'); ?></h3>
                
                <div class="lr-notes-form">
                    <textarea id="lr-new-note" class="large-text" rows="3" placeholder="<?php esc_attr_e('Adicione uma observação...', 'lr-recuperacao-vendas'); ?>"></textarea>
                    <button type="button" class="button lr-btn-add-note" data-case-id="<?php echo esc_attr($case->id); ?>">
                        <?php esc_html_e('Adicionar Observação', 'lr-recuperacao-vendas'); ?>
                    </button>
                </div>

                <div class="lr-notes-history">
                    <h4><?php esc_html_e('Histórico:', 'lr-recuperacao-vendas'); ?></h4>
                    <?php if (empty($logs)): ?>
                        <p class="description"><?php esc_html_e('Nenhum registro ainda.', 'lr-recuperacao-vendas'); ?></p>
                    <?php else: ?>
                        <ul class="lr-logs-list">
                            <?php foreach ($logs as $log): 
                                $log_user = $log->user_id ? get_userdata($log->user_id) : null;
                                $log_date = new DateTime($log->created_at);
                            ?>
                                <li class="lr-log-item lr-log-<?php echo esc_attr($log->action); ?>">
                                    <span class="lr-log-date"><?php echo esc_html($log_date->format('d/m H:i')); ?></span>
                                    <span class="lr-log-user"><?php echo $log_user ? esc_html($log_user->display_name) : esc_html__('Sistema', 'lr-recuperacao-vendas'); ?>:</span>
                                    <span class="lr-log-details"><?php echo esc_html($log->details); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lr-case-sidebar">
            <!-- Ações do Caso -->
            <div class="lr-card lr-card-actions">
                <h3><?php esc_html_e('Ações do Caso', 'lr-recuperacao-vendas'); ?></h3>
                
                <div class="lr-action-group">
                    <label for="lr-assigned-to"><?php esc_html_e('Responsável:', 'lr-recuperacao-vendas'); ?></label>
                    <select id="lr-assigned-to" class="lr-select-assigned" data-case-id="<?php echo esc_attr($case->id); ?>">
                        <option value=""><?php esc_html_e('Não atribuído', 'lr-recuperacao-vendas'); ?></option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($case->assigned_to, $user->ID); ?>>
                                <?php echo esc_html($user->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="lr-action-group">
                    <label for="lr-case-status"><?php esc_html_e('Status:', 'lr-recuperacao-vendas'); ?></label>
                    <select id="lr-case-status" class="lr-select-status" data-case-id="<?php echo esc_attr($case->id); ?>">
                        <option value="novo" <?php selected($case->status, 'novo'); ?>><?php esc_html_e('Novo', 'lr-recuperacao-vendas'); ?></option>
                        <option value="em_atendimento" <?php selected($case->status, 'em_atendimento'); ?>><?php esc_html_e('Em atendimento', 'lr-recuperacao-vendas'); ?></option>
                        <option value="aguardando_cliente" <?php selected($case->status, 'aguardando_cliente'); ?>><?php esc_html_e('Aguardando cliente', 'lr-recuperacao-vendas'); ?></option>
                        <option value="resolvido" <?php selected($case->status, 'resolvido'); ?>><?php esc_html_e('Resolvido', 'lr-recuperacao-vendas'); ?></option>
                        <option value="abandonado" <?php selected($case->status, 'abandonado'); ?>><?php esc_html_e('Abandonado', 'lr-recuperacao-vendas'); ?></option>
                    </select>
                </div>

                <div class="lr-action-buttons">
                    <?php if ($case->status !== 'resolvido'): ?>
                        <button type="button" class="button button-primary lr-btn-resolve" data-case-id="<?php echo esc_attr($case->id); ?>">
                            ✓ <?php esc_html_e('Marcar como Resolvido', 'lr-recuperacao-vendas'); ?>
                        </button>
                    <?php endif; ?>
                    
                    <?php if (!in_array($case->status, ['resolvido', 'abandonado'])): ?>
                        <button type="button" class="button lr-btn-abandon" data-case-id="<?php echo esc_attr($case->id); ?>">
                            ❌ <?php esc_html_e('Marcar como Abandonado', 'lr-recuperacao-vendas'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Links Rápidos -->
            <div class="lr-card">
                <h3><?php esc_html_e('Links Rápidos', 'lr-recuperacao-vendas'); ?></h3>
                <div class="lr-quick-links">
                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $order_data['id'] . '&action=edit')); ?>" class="button" target="_blank">
                        📄 <?php esc_html_e('Ver Pedido WC', 'lr-recuperacao-vendas'); ?>
                    </a>
                    <a href="<?php echo esc_url($external_urls['pagarme_base']); ?>" class="button" target="_blank">
                        🔗 <?php esc_html_e('Pagar.me Dashboard', 'lr-recuperacao-vendas'); ?>
                    </a>
                    <a href="<?php echo esc_url($external_urls['edwiser_enrollment']); ?>" class="button" target="_blank">
                        🎓 <?php esc_html_e('Edwiser Bridge', 'lr-recuperacao-vendas'); ?>
                    </a>
                </div>
            </div>

            <!-- Info do Caso -->
            <div class="lr-card lr-card-info">
                <h3><?php esc_html_e('Informações', 'lr-recuperacao-vendas'); ?></h3>
                <ul class="lr-info-meta">
                    <li>
                        <strong><?php esc_html_e('Criado em:', 'lr-recuperacao-vendas'); ?></strong>
                        <?php 
                        $created = new DateTime($case->created_at);
                        echo esc_html($created->format('d/m/Y H:i')); 
                        ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Atualizado em:', 'lr-recuperacao-vendas'); ?></strong>
                        <?php 
                        $updated = new DateTime($case->updated_at);
                        echo esc_html($updated->format('d/m/Y H:i')); 
                        ?>
                    </li>
                    <?php if ($case->resolved_at): ?>
                        <li>
                            <strong><?php esc_html_e('Resolvido em:', 'lr-recuperacao-vendas'); ?></strong>
                            <?php 
                            $resolved = new DateTime($case->resolved_at);
                            echo esc_html($resolved->format('d/m/Y H:i')); 
                            ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
