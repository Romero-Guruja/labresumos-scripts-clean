<?php
/**
 * Dashboard do Afiliado - Tab: Minha Rede
 *
 * @package Lab_Resumos_Parceiros
 * 
 * Variáveis disponíveis:
 * - $affiliate (LRP_Affiliate)
 * - $network (array)
 * - $network_stats (array)
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = LRP_Settings::instance();
$l2_rate = $affiliate->get_commission_rate('l2');
$l3_rate = $affiliate->get_commission_rate('l3');
$registration_url = get_permalink(get_option('lrp_registration_page_id'));
$invite_url = add_query_arg('sponsor', $affiliate->get_referral_code(), $registration_url);
?>

<div class="lrp-dashboard-rede">
    <h2><?php _e('Minha Rede de Parceiros', 'lab-resumos-parceiros'); ?></h2>
    
    <p class="lrp-intro">
        <?php _e('Convide outros parceiros e ganhe comissões sobre as vendas deles!', 'lab-resumos-parceiros'); ?>
    </p>
    
    <!-- Link de convite -->
    <div class="lrp-invite-section">
        <h3>🎯 <?php _e('Seu Link de Convite', 'lab-resumos-parceiros'); ?></h3>
        
        <div class="lrp-copy-field lrp-copy-large">
            <input type="text" readonly value="<?php echo esc_url($invite_url); ?>" id="lrp-invite-url">
            <button type="button" class="lrp-btn lrp-btn-primary lrp-btn-copy" data-target="lrp-invite-url">
                📋 <?php _e('Copiar Link', 'lab-resumos-parceiros'); ?>
            </button>
        </div>
        
        <div class="lrp-commission-info">
            <div class="lrp-commission-item">
                <span class="lrp-commission-level"><?php _e('Nível 2', 'lab-resumos-parceiros'); ?></span>
                <span class="lrp-commission-rate"><?php echo number_format($l2_rate, 0); ?>%</span>
                <span class="lrp-commission-desc"><?php _e('sobre vendas dos seus indicados', 'lab-resumos-parceiros'); ?></span>
            </div>
            <div class="lrp-commission-item">
                <span class="lrp-commission-level"><?php _e('Nível 3', 'lab-resumos-parceiros'); ?></span>
                <span class="lrp-commission-rate"><?php echo number_format($l3_rate, 0); ?>%</span>
                <span class="lrp-commission-desc"><?php _e('sobre vendas dos indicados deles', 'lab-resumos-parceiros'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Estatísticas da rede -->
    <div class="lrp-network-stats">
        <h3>📊 <?php _e('Estatísticas da Rede', 'lab-resumos-parceiros'); ?></h3>
        
        <div class="lrp-stats-grid lrp-stats-3">
            <div class="lrp-stat-card">
                <div class="lrp-stat-icon">👥</div>
                <div class="lrp-stat-content">
                    <span class="lrp-stat-value"><?php echo (int) ($network_stats['total_l2'] ?? 0); ?></span>
                    <span class="lrp-stat-label"><?php _e('Parceiros Diretos (Nível 2)', 'lab-resumos-parceiros'); ?></span>
                </div>
            </div>
            
            <div class="lrp-stat-card">
                <div class="lrp-stat-icon">👥</div>
                <div class="lrp-stat-content">
                    <span class="lrp-stat-value"><?php echo (int) ($network_stats['total_l3'] ?? 0); ?></span>
                    <span class="lrp-stat-label"><?php _e('Parceiros Indiretos (Nível 3)', 'lab-resumos-parceiros'); ?></span>
                </div>
            </div>
            
            <div class="lrp-stat-card">
                <div class="lrp-stat-icon">💰</div>
                <div class="lrp-stat-content">
                    <span class="lrp-stat-value">R$ <?php echo number_format($network_stats['network_commissions'] ?? 0, 2, ',', '.'); ?></span>
                    <span class="lrp-stat-label"><?php _e('Comissões de Rede', 'lab-resumos-parceiros'); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lista da rede -->
    <?php if (!empty($network['l2'])): ?>
    <div class="lrp-network-list">
        <h3>👥 <?php _e('Parceiros da Minha Rede', 'lab-resumos-parceiros'); ?></h3>
        
        <div class="lrp-network-tree">
            <?php foreach ($network['l2'] as $l2_affiliate): 
                // Obtém informações de atividade do sub-afiliado
                $l2_activity = LRP_Activity_Calculator::get_activity_info($l2_affiliate->id);
            ?>
            <div class="lrp-network-node lrp-level-2">
                <div class="lrp-node-header">
                    <span class="lrp-node-level">L2</span>
                    <span class="lrp-node-name"><?php echo esc_html($l2_affiliate->display_name); ?></span>
                    <span class="lrp-node-status lrp-status-<?php echo esc_attr($l2_affiliate->status); ?>">
                        <?php echo LRP_Dashboard::get_status_label($l2_affiliate->status); ?>
                    </span>
                    <?php // Badge de atividade de rede ?>
                    <?php if ($l2_activity['is_new_affiliate']): ?>
                        <span class="lrp-network-badge lrp-badge-new" title="<?php esc_attr_e('Novo parceiro - período de proteção ativo', 'lab-resumos-parceiros'); ?>">🌟</span>
                    <?php elseif ($l2_activity['is_active']): ?>
                        <span class="lrp-network-badge lrp-badge-active" title="<?php esc_attr_e('Ativo para receber comissões de rede', 'lab-resumos-parceiros'); ?>">✅</span>
                    <?php else: ?>
                        <span class="lrp-network-badge lrp-badge-inactive" title="<?php printf(esc_attr__('Inativo - faltam %d vendas para ativar', 'lab-resumos-parceiros'), $l2_activity['sales_missing']); ?>">⏸️</span>
                    <?php endif; ?>
                </div>
                <div class="lrp-node-details">
                    <span><?php printf(__('%d vendas', 'lab-resumos-parceiros'), (int) $l2_affiliate->total_sales); ?></span>
                    <span><?php printf(__('R$ %s gerado', 'lab-resumos-parceiros'), number_format($l2_affiliate->total_revenue, 2, ',', '.')); ?></span>
                    <span><?php printf(__('Desde: %s', 'lab-resumos-parceiros'), date('d/m/Y', strtotime($l2_affiliate->created_at))); ?></span>
                    <?php if (!$l2_activity['is_new_affiliate']): ?>
                        <span class="lrp-node-activity">
                            <?php printf(__('Rede: %d/%d vendas', 'lab-resumos-parceiros'), $l2_activity['sales_count'], $l2_activity['sales_required']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php 
                // Busca sub-afiliados do L2 (L3)
                $l3_affiliates = isset($network['l3'][$l2_affiliate->id]) ? $network['l3'][$l2_affiliate->id] : [];
                if (!empty($l3_affiliates)): 
                ?>
                <div class="lrp-network-children">
                    <?php foreach ($l3_affiliates as $l3_affiliate): 
                        // Obtém informações de atividade do sub-afiliado L3
                        $l3_activity = LRP_Activity_Calculator::get_activity_info($l3_affiliate->id);
                    ?>
                    <div class="lrp-network-node lrp-level-3">
                        <div class="lrp-node-header">
                            <span class="lrp-node-level">L3</span>
                            <span class="lrp-node-name"><?php echo esc_html($l3_affiliate->display_name); ?></span>
                            <span class="lrp-node-status lrp-status-<?php echo esc_attr($l3_affiliate->status); ?>">
                                <?php echo LRP_Dashboard::get_status_label($l3_affiliate->status); ?>
                            </span>
                            <?php // Badge de atividade de rede ?>
                            <?php if ($l3_activity['is_new_affiliate']): ?>
                                <span class="lrp-network-badge lrp-badge-new" title="<?php esc_attr_e('Novo parceiro - período de proteção ativo', 'lab-resumos-parceiros'); ?>">🌟</span>
                            <?php elseif ($l3_activity['is_active']): ?>
                                <span class="lrp-network-badge lrp-badge-active" title="<?php esc_attr_e('Ativo para receber comissões de rede', 'lab-resumos-parceiros'); ?>">✅</span>
                            <?php else: ?>
                                <span class="lrp-network-badge lrp-badge-inactive" title="<?php printf(esc_attr__('Inativo - faltam %d vendas para ativar', 'lab-resumos-parceiros'), $l3_activity['sales_missing']); ?>">⏸️</span>
                            <?php endif; ?>
                        </div>
                        <div class="lrp-node-details">
                            <span><?php printf(__('%d vendas', 'lab-resumos-parceiros'), (int) $l3_affiliate->total_sales); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php else: ?>
    
    <div class="lrp-empty-state">
        <div class="lrp-empty-icon">👥</div>
        <h3><?php _e('Sua rede está vazia', 'lab-resumos-parceiros'); ?></h3>
        <p><?php _e('Convide outros parceiros usando seu link de convite acima e comece a ganhar comissões sobre as vendas deles!', 'lab-resumos-parceiros'); ?></p>
    </div>
    
    <?php endif; ?>
    
    <!-- Como funciona -->
    <div class="lrp-info-section">
        <h3>❓ <?php _e('Como Funciona o Sistema de Rede?', 'lab-resumos-parceiros'); ?></h3>
        
        <div class="lrp-how-it-works">
            <div class="lrp-step">
                <div class="lrp-step-number">1</div>
                <div class="lrp-step-content">
                    <h4><?php _e('Convide', 'lab-resumos-parceiros'); ?></h4>
                    <p><?php _e('Compartilhe seu link de convite com pessoas que querem ser parceiros.', 'lab-resumos-parceiros'); ?></p>
                </div>
            </div>
            
            <div class="lrp-step">
                <div class="lrp-step-number">2</div>
                <div class="lrp-step-content">
                    <h4><?php _e('Eles vendem', 'lab-resumos-parceiros'); ?></h4>
                    <p><?php _e('Seus indicados divulgam e fazem vendas normalmente.', 'lab-resumos-parceiros'); ?></p>
                </div>
            </div>
            
            <div class="lrp-step">
                <div class="lrp-step-number">3</div>
                <div class="lrp-step-content">
                    <h4><?php _e('Você ganha', 'lab-resumos-parceiros'); ?></h4>
                    <p><?php printf(__('Você recebe %s%% sobre cada venda deles (e %s%% das vendas dos indicados deles).', 'lab-resumos-parceiros'), number_format($l2_rate, 0), number_format($l3_rate, 0)); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

