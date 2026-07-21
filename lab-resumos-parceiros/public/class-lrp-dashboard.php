<?php
/**
 * Dashboard do Afiliado
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Dashboard
 * 
 * Renderiza o dashboard completo do afiliado.
 */
class LRP_Dashboard {

    /**
     * Afiliado atual
     *
     * @var LRP_Affiliate
     */
    private $affiliate;

    /**
     * Aba atual
     *
     * @var string
     */
    private $current_tab;

    /**
     * Abas disponíveis
     *
     * @var array
     */
    private $tabs = [];

    /**
     * Construtor
     *
     * @param LRP_Affiliate $affiliate
     */
    public function __construct($affiliate) {
        $this->affiliate = $affiliate;
        $this->current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'home';
        
        $this->tabs = [
            'home'        => ['label' => __('Início', 'lab-resumos-parceiros'), 'icon' => '🏠'],
            'links'       => ['label' => __('Links e Cupons', 'lab-resumos-parceiros'), 'icon' => '🔗'],
            'products'    => ['label' => __('Produtos', 'lab-resumos-parceiros'), 'icon' => '🛍️'],
            'sales'       => ['label' => __('Vendas', 'lab-resumos-parceiros'), 'icon' => '💰'],
            'traffic'     => ['label' => __('Tráfego', 'lab-resumos-parceiros'), 'icon' => '📊'],
            'performance' => ['label' => __('Desempenho', 'lab-resumos-parceiros'), 'icon' => '🏆'],
            'network'     => ['label' => __('Minha Rede', 'lab-resumos-parceiros'), 'icon' => '👥'],
            'financial'   => ['label' => __('Financeiro', 'lab-resumos-parceiros'), 'icon' => '💵'],
            'adjustments' => ['label' => __('Ajustes e Bônus', 'lab-resumos-parceiros'), 'icon' => '🎁'],
            'materials'   => ['label' => __('Materiais', 'lab-resumos-parceiros'), 'icon' => '📦'],
            'area_aluno'  => ['label' => __('Área do Aluno', 'lab-resumos-parceiros'), 'icon' => '📚'],
            'faq'         => ['label' => __('FAQ', 'lab-resumos-parceiros'), 'icon' => '❓'],
            'profile'     => ['label' => __('Meu Perfil', 'lab-resumos-parceiros'), 'icon' => '👤'],
        ];
    }

    /**
     * Renderiza dashboard
     *
     * @return string
     */
    public function render() {
        ob_start();
        ?>
        <div class="lrp-dashboard">
            <?php $this->render_header(); ?>
            <?php $this->render_tabs(); ?>
            
            <div class="lrp-dashboard-content">
                <?php $this->render_tab_content(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza header
     */
    private function render_header() {
        ?>
        <div class="lrp-dashboard-header">
            <div class="lrp-welcome">
                <h2><?php printf(__('Olá, %s!', 'lab-resumos-parceiros'), esc_html($this->affiliate->get_display_name())); ?></h2>
                <p class="lrp-coupon-badge">
                    <?php _e('Seu cupom:', 'lab-resumos-parceiros'); ?>
                    <strong><?php echo esc_html($this->affiliate->get_coupon_code()); ?></strong>
                    <button class="lrp-copy-btn" data-copy="<?php echo esc_attr($this->affiliate->get_coupon_code()); ?>" title="<?php esc_attr_e('Copiar', 'lab-resumos-parceiros'); ?>">
                        <?php echo $this->get_copy_icon(); ?>
                    </button>
                </p>
            </div>
            
            <div class="lrp-quick-stats">
                <div class="lrp-stat">
                    <span class="lrp-stat-value">R$ <?php echo esc_html(number_format($this->affiliate->get_current_balance(), 2, ',', '.')); ?></span>
                    <span class="lrp-stat-label"><?php _e('Saldo Atual', 'lab-resumos-parceiros'); ?></span>
                </div>
                <div class="lrp-stat">
                    <span class="lrp-stat-value"><?php echo esc_html($this->affiliate->get_total_sales()); ?></span>
                    <span class="lrp-stat-label"><?php _e('Vendas', 'lab-resumos-parceiros'); ?></span>
                </div>
                <div class="lrp-stat">
                    <span class="lrp-stat-value">R$ <?php echo esc_html(number_format($this->affiliate->get_total_commissions(), 2, ',', '.')); ?></span>
                    <span class="lrp-stat-label"><?php _e('Total Ganho', 'lab-resumos-parceiros'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza navegação por abas
     */
    private function render_tabs() {
        $base_url = get_permalink();
        
        // Preserva parâmetro preview_as se estiver em modo admin preview
        $preview_affiliate_id = isset($_GET['preview_as']) ? (int) $_GET['preview_as'] : 0;
        ?>
        <nav class="lrp-tabs">
            <?php foreach ($this->tabs as $tab_id => $tab): 
                $tab_url = add_query_arg('tab', $tab_id, $base_url);
                if ($preview_affiliate_id) {
                    $tab_url = add_query_arg('preview_as', $preview_affiliate_id, $tab_url);
                }
            ?>
                <a href="<?php echo esc_url($tab_url); ?>" 
                   class="lrp-tab <?php echo $tab_id === $this->current_tab ? 'active' : ''; ?>">
                    <span class="lrp-tab-icon"><?php echo $tab['icon']; ?></span>
                    <span class="lrp-tab-label"><?php echo esc_html($tab['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    /**
     * Renderiza conteúdo da aba atual
     */
    private function render_tab_content() {
        $method = 'render_tab_' . $this->current_tab;
        
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            $this->render_tab_home();
        }
    }

    /**
     * Aba: Início
     */
    private function render_tab_home() {
        $stats = $this->get_cached_stats();
        $recent_sales = LRP_Referral::get_recent_by_affiliate($this->affiliate->get_id(), 5);
        ?>
        <div class="lrp-tab-content lrp-tab-home">
            <div class="lrp-stats-grid">
                <div class="lrp-card">
                    <h4><?php _e('Este Mês', 'lab-resumos-parceiros'); ?></h4>
                    <div class="lrp-card-value"><?php echo esc_html($stats['month_sales']); ?></div>
                    <div class="lrp-card-label"><?php _e('Vendas', 'lab-resumos-parceiros'); ?></div>
                </div>
                <div class="lrp-card">
                    <h4><?php _e('Este Mês', 'lab-resumos-parceiros'); ?></h4>
                    <div class="lrp-card-value">R$ <?php echo esc_html(number_format($stats['month_commission'], 2, ',', '.')); ?></div>
                    <div class="lrp-card-label"><?php _e('Comissões', 'lab-resumos-parceiros'); ?></div>
                </div>
                <div class="lrp-card">
                    <h4><?php _e('Taxa de Conversão', 'lab-resumos-parceiros'); ?></h4>
                    <div class="lrp-card-value"><?php echo esc_html($stats['conversion_rate']); ?>%</div>
                    <div class="lrp-card-label"><?php _e('Visitas → Vendas', 'lab-resumos-parceiros'); ?></div>
                </div>
                <div class="lrp-card lrp-card-highlight">
                    <h4><?php _e('A Receber', 'lab-resumos-parceiros'); ?></h4>
                    <div class="lrp-card-value">R$ <?php echo esc_html(number_format($stats['pending_payment'], 2, ',', '.')); ?></div>
                    <div class="lrp-card-label"><?php _e('Comissões Aprovadas', 'lab-resumos-parceiros'); ?></div>
                </div>
            </div>
            
            <?php if (!empty($recent_sales)): ?>
            <div class="lrp-recent-sales">
                <h3><?php _e('Vendas Recentes', 'lab-resumos-parceiros'); ?></h3>
                <table class="lrp-table">
                    <thead>
                        <tr>
                            <th><?php _e('Data', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Pedido', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Valor', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Comissão', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sales as $sale): ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($sale->get_created_at()))); ?></td>
                            <td>#<?php echo esc_html($sale->get_order_id()); ?></td>
                            <td>R$ <?php echo esc_html(number_format($sale->get_commission_base(), 2, ',', '.')); ?></td>
                            <td>R$ <?php echo esc_html(number_format($sale->get_direct_commission(), 2, ',', '.')); ?></td>
                            <td><span class="lrp-status lrp-status-<?php echo esc_attr($sale->get_status()); ?>">
                                <?php echo esc_html($this->get_status_label($sale->get_status())); ?>
                            </span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Aba: Links e Cupons
     */
    private function render_tab_links() {
        $referral_url = $this->affiliate->get_referral_url();
        $coupon_code = $this->affiliate->get_coupon_code();
        $settings = LRP_Settings::instance();
        $visit_stats = LRP_Cookie_Tracker::instance()->get_visit_stats($this->affiliate->get_id(), 'all');
        
        // Verifica restrições de produtos
        $restriction_handler = LRP_Product_Restriction::instance();
        $restrictions_summary = $restriction_handler->get_restrictions_summary($this->affiliate->get_id());
        ?>
        <div class="lrp-tab-content lrp-tab-links">
            
            <?php if ($restrictions_summary['has_restrictions']): ?>
            <!-- Aviso de Restrições de Produtos -->
            <div class="lrp-restrictions-notice <?php echo esc_attr($restrictions_summary['mode']); ?>">
                <div class="lrp-restrictions-header">
                    <?php if ($restrictions_summary['mode'] === 'whitelist'): ?>
                        <span class="lrp-restrictions-icon">🔒</span>
                        <h4><?php _e('Produtos que você pode promover', 'lab-resumos-parceiros'); ?></h4>
                    <?php else: ?>
                        <span class="lrp-restrictions-icon">⚠️</span>
                        <h4><?php _e('Restrições de Produtos Ativas', 'lab-resumos-parceiros'); ?></h4>
                    <?php endif; ?>
                </div>
                
                <div class="lrp-restrictions-content">
                    <?php if ($restrictions_summary['mode'] === 'whitelist'): ?>
                        <p><?php _e('Você só pode promover os seguintes produtos/categorias:', 'lab-resumos-parceiros'); ?></p>
                    <?php else: ?>
                        <p><?php _e('Você NÃO pode promover os seguintes produtos/categorias:', 'lab-resumos-parceiros'); ?></p>
                    <?php endif; ?>
                    
                    <ul class="lrp-restrictions-list">
                        <?php foreach ($restrictions_summary['items'] as $item): ?>
                        <li class="lrp-restriction-item">
                            <span class="lrp-restriction-type">
                                <?php echo $item['type'] === 'product' ? '📦' : '📁'; ?>
                            </span>
                            <span class="lrp-restriction-name"><?php echo esc_html($item['name']); ?></span>
                            <span class="lrp-restriction-period">
                                <?php 
                                if ($item['end_date']) {
                                    printf(
                                        __('%s até %s', 'lab-resumos-parceiros'),
                                        date_i18n('d/m/Y', strtotime($item['start_date'])),
                                        date_i18n('d/m/Y', strtotime($item['end_date']))
                                    );
                                } else {
                                    _e('Permanente', 'lab-resumos-parceiros');
                                }
                                ?>
                            </span>
                            <?php if ($item['reason']): ?>
                            <span class="lrp-restriction-reason" title="<?php echo esc_attr($item['reason']); ?>">
                                ℹ️
                            </span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <div class="lrp-restrictions-note">
                        <?php echo $this->get_info_icon(); ?>
                        <?php if ($restrictions_summary['mode'] === 'whitelist'): ?>
                            <?php _e('Vendas de outros produtos não gerarão comissão para você.', 'lab-resumos-parceiros'); ?>
                        <?php else: ?>
                            <?php _e('Vendas com esses produtos não gerarão comissão para você.', 'lab-resumos-parceiros'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Card: Cupom de Desconto -->
            <div class="lrp-link-card">
                <div class="lrp-link-card-header">
                    <div class="lrp-link-card-title">
                        <div class="lrp-link-card-icon lrp-icon-coupon">🎫</div>
                        <div>
                            <h4><?php _e('Cupom de Desconto', 'lab-resumos-parceiros'); ?></h4>
                            <p><?php _e('Compartilhe este cupom para seus seguidores usarem no checkout', 'lab-resumos-parceiros'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="lrp-coupon-display">
                    <span class="lrp-coupon-code-display"><?php echo esc_html($coupon_code); ?></span>
                    <button class="lrp-copy-btn-large" data-copy="<?php echo esc_attr($coupon_code); ?>">
                        <?php echo $this->get_copy_icon(); ?>
                        <span><?php _e('Copiar Cupom', 'lab-resumos-parceiros'); ?></span>
                    </button>
                </div>
                
                <div class="lrp-link-card-info">
                    <?php echo $this->get_info_icon(); ?>
                    <div>
                        <?php printf(
                            __('<strong>Desconto de %s%%</strong> para o cliente que usar este cupom. Você recebe <strong>%s%% de comissão</strong> sobre o valor pago.', 'lab-resumos-parceiros'), 
                            esc_html($this->affiliate->get_customer_discount()),
                            esc_html($this->affiliate->get_commission_rate('coupon'))
                        ); ?>
                    </div>
                </div>
            </div>
            
            <!-- Card: Link de Afiliado -->
            <div class="lrp-link-card">
                <div class="lrp-link-card-header">
                    <div class="lrp-link-card-title">
                        <div class="lrp-link-card-icon lrp-icon-link">🔗</div>
                        <div>
                            <h4><?php _e('Link de Afiliado', 'lab-resumos-parceiros'); ?></h4>
                            <p><?php _e('Link exclusivo que rastreia todas as visitas e vendas', 'lab-resumos-parceiros'); ?></p>
                        </div>
                    </div>
                    <div class="lrp-link-card-stats" title="<?php esc_attr_e('Total de cliques no seu link', 'lab-resumos-parceiros'); ?>">
                        <?php echo $this->get_click_icon(); ?>
                        <span><?php echo esc_html(number_format($visit_stats['total_visits'])); ?> <?php _e('cliques', 'lab-resumos-parceiros'); ?></span>
                    </div>
                </div>
                
                <div class="lrp-link-input-wrapper">
                    <div class="lrp-link-input-box">
                        <input type="text" readonly value="<?php echo esc_url($referral_url); ?>" class="lrp-link-input" id="lrp-referral-url">
                    </div>
                    <button class="lrp-copy-btn-large" data-copy="<?php echo esc_attr($referral_url); ?>">
                        <?php echo $this->get_copy_icon(); ?>
                        <span><?php _e('Copiar Link', 'lab-resumos-parceiros'); ?></span>
                    </button>
                </div>
                
                <div class="lrp-link-card-info">
                    <?php echo $this->get_info_icon(); ?>
                    <div>
                        <?php printf(
                            __('Cookie válido por <strong>%d dias</strong>. Mesmo que a pessoa não compre na hora, você ganha <strong>%s%% de comissão</strong> se ela voltar e comprar dentro desse período.', 'lab-resumos-parceiros'),
                            esc_html($this->affiliate->get_cookie_days()),
                            esc_html($this->affiliate->get_commission_rate('link'))
                        ); ?>
                    </div>
                </div>
                
                <!-- QR Code -->
                <div class="lrp-qrcode">
                    <div class="lrp-qrcode-wrapper">
                        <div id="lrp-qrcode"></div>
                    </div>
                    <p class="lrp-qrcode-label"><?php _e('QR Code do seu link', 'lab-resumos-parceiros'); ?></p>
                </div>
                
                <!-- Compartilhar -->
                <div class="lrp-share-section">
                    <h5><?php _e('Compartilhar rapidamente', 'lab-resumos-parceiros'); ?></h5>
                    <div class="lrp-share-buttons">
                        <a href="https://wa.me/?text=<?php echo urlencode(__('Confira os cursos do Lab Resumos! Use meu cupom ', 'lab-resumos-parceiros') . $coupon_code . ' para desconto: ' . $referral_url); ?>" 
                           target="_blank" class="lrp-share-btn lrp-share-whatsapp">
                            📱 WhatsApp
                        </a>
                        <a href="https://t.me/share/url?url=<?php echo urlencode($referral_url); ?>&text=<?php echo urlencode(__('Use meu cupom ', 'lab-resumos-parceiros') . $coupon_code); ?>" 
                           target="_blank" class="lrp-share-btn lrp-share-telegram">
                            ✈️ Telegram
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Seção de Ajuda -->
            <div class="lrp-help-section">
                <details class="lrp-help-toggle">
                    <summary>
                        <span class="lrp-help-icon">💡</span>
                        <?php _e('Qual a diferença entre cupom e link?', 'lab-resumos-parceiros'); ?>
                        <?php echo $this->get_chevron_icon(); ?>
                    </summary>
                    <div class="lrp-help-content">
                        <p><?php _e('Ambos servem para rastrear suas vendas, mas funcionam de formas diferentes:', 'lab-resumos-parceiros'); ?></p>
                        
                        <table class="lrp-commission-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Característica', 'lab-resumos-parceiros'); ?></th>
                                    <th>🎫 <?php _e('Cupom', 'lab-resumos-parceiros'); ?></th>
                                    <th>🔗 <?php _e('Link', 'lab-resumos-parceiros'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php _e('Desconto para cliente', 'lab-resumos-parceiros'); ?></td>
                                    <td><strong><?php echo esc_html($this->affiliate->get_customer_discount()); ?>%</strong></td>
                                    <td><?php _e('Não tem', 'lab-resumos-parceiros'); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Sua comissão', 'lab-resumos-parceiros'); ?></td>
                                    <td><strong><?php echo esc_html($this->affiliate->get_commission_rate('coupon')); ?>%</strong></td>
                                    <td><strong><?php echo esc_html($this->affiliate->get_commission_rate('link')); ?>%</strong></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Validade', 'lab-resumos-parceiros'); ?></td>
                                    <td><?php _e('No momento da compra', 'lab-resumos-parceiros'); ?></td>
                                    <td><?php printf(__('%d dias de cookie', 'lab-resumos-parceiros'), $this->affiliate->get_cookie_days()); ?></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="lrp-example-box">
                            <div class="lrp-example-box-title">📌 <?php _e('Dica', 'lab-resumos-parceiros'); ?></div>
                            <div class="lrp-example-content">
                                <?php _e('Use o <strong>cupom</strong> em posts e stories para atrair clientes com desconto. Use o <strong>link</strong> em bio de redes sociais, blogs ou emails para rastrear visitas.', 'lab-resumos-parceiros'); ?>
                            </div>
                        </div>
                    </div>
                </details>
                
                <details class="lrp-help-toggle">
                    <summary>
                        <span class="lrp-help-icon">💰</span>
                        <?php _e('Como é calculada minha comissão?', 'lab-resumos-parceiros'); ?>
                        <?php echo $this->get_chevron_icon(); ?>
                    </summary>
                    <div class="lrp-help-content">
                        <p><?php _e('Sua comissão é calculada sobre o valor pago pelo cliente (após descontos).', 'lab-resumos-parceiros'); ?></p>
                        
                        <div class="lrp-example-box">
                            <div class="lrp-example-box-title">📊 <?php _e('Exemplo prático', 'lab-resumos-parceiros'); ?></div>
                            <div class="lrp-example-content">
                                <?php 
                                $price = 97;
                                $discount = $this->affiliate->get_customer_discount();
                                $commission_rate = $this->affiliate->get_commission_rate('coupon');
                                $price_with_discount = $price * (1 - $discount/100);
                                $commission = $price_with_discount * ($commission_rate/100);
                                
                                printf(
                                    __('Maria compra um curso de R$%s usando seu cupom.<br>
                                    → Com %s%% de desconto, ela paga <span class="lrp-example-highlight">R$%s</span><br>
                                    → Sua comissão de %s%% = <span class="lrp-example-highlight">R$%s</span>', 'lab-resumos-parceiros'),
                                    number_format($price, 2, ',', '.'),
                                    $discount,
                                    number_format($price_with_discount, 2, ',', '.'),
                                    $commission_rate,
                                    number_format($commission, 2, ',', '.')
                                );
                                ?>
                            </div>
                        </div>
                    </div>
                </details>
                
                <details class="lrp-help-toggle">
                    <summary>
                        <span class="lrp-help-icon">📅</span>
                        <?php _e('Quando recebo minha comissão?', 'lab-resumos-parceiros'); ?>
                        <?php echo $this->get_chevron_icon(); ?>
                    </summary>
                    <div class="lrp-help-content">
                        <p><?php _e('O processo de pagamento funciona assim:', 'lab-resumos-parceiros'); ?></p>
                        <ul>
                            <li><?php _e('<strong>Venda registrada</strong> → Comissão fica "Pendente" (aguardando confirmação de pagamento)', 'lab-resumos-parceiros'); ?></li>
                            <li><?php _e('<strong>Pagamento confirmado</strong> → Comissão fica "Aprovada" e entra no seu saldo', 'lab-resumos-parceiros'); ?></li>
                            <li><?php _e('<strong>Fechamento mensal</strong> → No início de cada mês, somamos suas comissões aprovadas', 'lab-resumos-parceiros'); ?></li>
                            <li><?php _e('<strong>Envio de NF</strong> → Você envia a nota fiscal (se necessário)', 'lab-resumos-parceiros'); ?></li>
                            <li><?php _e('<strong>Pagamento</strong> → Transferência via PIX em até 5 dias úteis', 'lab-resumos-parceiros'); ?></li>
                        </ul>
                        
                        <div class="lrp-example-box">
                            <div class="lrp-example-box-title">⚠️ <?php _e('Importante', 'lab-resumos-parceiros'); ?></div>
                            <div class="lrp-example-content">
                                <?php _e('Se um cliente pedir reembolso, a comissão referente a essa venda será estornada do seu saldo.', 'lab-resumos-parceiros'); ?>
                            </div>
                        </div>
                    </div>
                </details>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof qrcode !== 'undefined') {
                var qr = qrcode(0, 'M');
                qr.addData('<?php echo esc_js($referral_url); ?>');
                qr.make();
                document.getElementById('lrp-qrcode').innerHTML = qr.createImgTag(5, 10);
            }
        });
        </script>
        <?php
    }

    /**
     * Aba: Produtos (catálogo com links)
     * 
     * @since 1.5.0
     */
    private function render_tab_products() {
        // Busca produtos WooCommerce
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 12;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $category = isset($_GET['cat']) ? (int) $_GET['cat'] : 0;
        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'title';
        $order = isset($_GET['order']) ? strtoupper(sanitize_key($_GET['order'])) : 'ASC';
        
        // Monta query
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => $orderby === 'price' ? 'meta_value_num' : $orderby,
            'order'          => in_array($order, ['ASC', 'DESC']) ? $order : 'ASC',
        ];
        
        if ($orderby === 'price') {
            $args['meta_key'] = '_price';
        }
        
        if ($search) {
            $args['s'] = $search;
        }
        
        if ($category) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $category,
                ],
            ];
        }
        
        $products_query = new WP_Query($args);
        $products = $products_query->posts;
        $total_pages = $products_query->max_num_pages;
        $total_products = $products_query->found_posts;
        
        // Busca categorias
        $categories = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
        ]);
        
        // Código de referência
        $ref_code = $this->affiliate->get_referral_code();
        $link_rate = $this->affiliate->get_commission_rate('link');
        
        // URL base para filtros (preserva preview_as se em modo admin)
        $preview_affiliate_id = isset($_GET['preview_as']) ? (int) $_GET['preview_as'] : 0;
        $base_url = add_query_arg('tab', 'products', get_permalink());
        if ($preview_affiliate_id) {
            $base_url = add_query_arg('preview_as', $preview_affiliate_id, $base_url);
        }
        ?>
        <div class="lrp-tab-content lrp-tab-products">
            <div class="lrp-section">
                <h3>🛍️ <?php _e('Catálogo de Produtos', 'lab-resumos-parceiros'); ?></h3>
                
                <p class="lrp-section-description">
                    <?php printf(
                        __('Encontre o produto ideal para divulgar. Clique em "Copiar Link" para obter seu link de afiliado. Sua comissão por link: <strong>%s%%</strong>', 'lab-resumos-parceiros'),
                        number_format($link_rate, 0)
                    ); ?>
                </p>
                
                <!-- Filtros -->
                <div class="lrp-products-filters">
                    <form method="get" action="<?php echo esc_url($base_url); ?>" class="lrp-filters-form">
                        <input type="hidden" name="tab" value="products">
                        <?php if ($preview_affiliate_id): ?>
                        <input type="hidden" name="preview_as" value="<?php echo esc_attr($preview_affiliate_id); ?>">
                        <?php endif; ?>
                        
                        <div class="lrp-filter-group">
                            <label for="lrp-search"><?php _e('Buscar:', 'lab-resumos-parceiros'); ?></label>
                            <input type="text" name="s" id="lrp-search" value="<?php echo esc_attr($search); ?>" 
                                   placeholder="<?php esc_attr_e('Nome do produto...', 'lab-resumos-parceiros'); ?>">
                        </div>
                        
                        <?php if (!empty($categories) && !is_wp_error($categories)): ?>
                        <div class="lrp-filter-group">
                            <label for="lrp-category"><?php _e('Categoria:', 'lab-resumos-parceiros'); ?></label>
                            <select name="cat" id="lrp-category">
                                <option value=""><?php _e('Todas', 'lab-resumos-parceiros'); ?></option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($category, $cat->term_id); ?>>
                                    <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="lrp-filter-group">
                            <label for="lrp-orderby"><?php _e('Ordenar por:', 'lab-resumos-parceiros'); ?></label>
                            <select name="orderby" id="lrp-orderby">
                                <option value="title" <?php selected($orderby, 'title'); ?>><?php _e('Nome', 'lab-resumos-parceiros'); ?></option>
                                <option value="price" <?php selected($orderby, 'price'); ?>><?php _e('Preço', 'lab-resumos-parceiros'); ?></option>
                                <option value="date" <?php selected($orderby, 'date'); ?>><?php _e('Data', 'lab-resumos-parceiros'); ?></option>
                                <option value="popularity" <?php selected($orderby, 'popularity'); ?>><?php _e('Popularidade', 'lab-resumos-parceiros'); ?></option>
                            </select>
                        </div>
                        
                        <div class="lrp-filter-group">
                            <label for="lrp-order"><?php _e('Ordem:', 'lab-resumos-parceiros'); ?></label>
                            <select name="order" id="lrp-order">
                                <option value="ASC" <?php selected($order, 'ASC'); ?>><?php _e('Crescente', 'lab-resumos-parceiros'); ?></option>
                                <option value="DESC" <?php selected($order, 'DESC'); ?>><?php _e('Decrescente', 'lab-resumos-parceiros'); ?></option>
                            </select>
                        </div>
                        
                        <div class="lrp-filter-actions">
                            <button type="submit" class="lrp-btn lrp-btn-primary">
                                🔍 <?php _e('Filtrar', 'lab-resumos-parceiros'); ?>
                            </button>
                            <?php if ($search || $category || $orderby !== 'title'): ?>
                            <a href="<?php echo esc_url($base_url); ?>" class="lrp-btn lrp-btn-secondary">
                                <?php _e('Limpar', 'lab-resumos-parceiros'); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Resultados info -->
                <div class="lrp-products-info">
                    <?php printf(
                        _n('%s produto encontrado', '%s produtos encontrados', $total_products, 'lab-resumos-parceiros'),
                        '<strong>' . number_format_i18n($total_products) . '</strong>'
                    ); ?>
                </div>
                
                <?php if (empty($products)): ?>
                    <div class="lrp-empty">
                        <div class="lrp-empty-icon">🔍</div>
                        <div class="lrp-empty-title"><?php _e('Nenhum produto encontrado', 'lab-resumos-parceiros'); ?></div>
                        <div class="lrp-empty-text"><?php _e('Tente ajustar os filtros de busca.', 'lab-resumos-parceiros'); ?></div>
                    </div>
                <?php else: ?>
                    <!-- Grid de produtos -->
                    <div class="lrp-products-grid">
                        <?php foreach ($products as $post): 
                            $product = wc_get_product($post->ID);
                            if (!$product) continue;
                            
                            $product_url = $product->get_permalink();
                            $ref_url = add_query_arg('ref', $ref_code, $product_url);
                            $image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'woocommerce_thumbnail');
                            $image_url = $image ? $image[0] : wc_placeholder_img_src('woocommerce_thumbnail');
                            $price = $product->get_price();
                            $commission = $price * ($link_rate / 100);
                        ?>
                        <div class="lrp-product-card">
                            <div class="lrp-product-image">
                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product->get_name()); ?>">
                            </div>
                            
                            <div class="lrp-product-info">
                                <h4 class="lrp-product-title"><?php echo esc_html($product->get_name()); ?></h4>
                                
                                <div class="lrp-product-prices">
                                    <span class="lrp-product-price">
                                        <?php echo $product->get_price_html(); ?>
                                    </span>
                                    <span class="lrp-product-commission" title="<?php esc_attr_e('Sua comissão estimada', 'lab-resumos-parceiros'); ?>">
                                        💰 R$ <?php echo esc_html(number_format($commission, 2, ',', '.')); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="lrp-product-actions">
                                <button type="button" class="lrp-btn lrp-btn-primary lrp-btn-copy-link" 
                                        data-link="<?php echo esc_url($ref_url); ?>"
                                        title="<?php esc_attr_e('Copiar link de afiliado', 'lab-resumos-parceiros'); ?>">
                                    📋 <?php _e('Copiar Link', 'lab-resumos-parceiros'); ?>
                                </button>
                                <a href="<?php echo esc_url($product_url); ?>" target="_blank" class="lrp-btn lrp-btn-secondary" 
                                   title="<?php esc_attr_e('Ver página do produto', 'lab-resumos-parceiros'); ?>">
                                    👁️ <?php _e('Ver', 'lab-resumos-parceiros'); ?>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Paginação -->
                    <?php if ($total_pages > 1): ?>
                    <div class="lrp-pagination">
                        <?php
                        $pagination_args = [
                            'tab'     => 'products',
                            's'       => $search,
                            'cat'     => $category,
                            'orderby' => $orderby,
                            'order'   => $order,
                        ];
                        if ($preview_affiliate_id) {
                            $pagination_args['preview_as'] = $preview_affiliate_id;
                        }
                        $pagination_base = add_query_arg($pagination_args, get_permalink());
                        
                        echo paginate_links([
                            'base'      => $pagination_base . '&paged=%#%',
                            'format'    => '',
                            'current'   => $paged,
                            'total'     => $total_pages,
                            'prev_text' => '&laquo; ' . __('Anterior', 'lab-resumos-parceiros'),
                            'next_text' => __('Próxima', 'lab-resumos-parceiros') . ' &raquo;',
                        ]);
                        ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Copy link buttons
            document.querySelectorAll('.lrp-btn-copy-link').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var link = this.getAttribute('data-link');
                    var originalText = this.innerHTML;
                    var button = this;
                    
                    navigator.clipboard.writeText(link).then(function() {
                        button.innerHTML = '✅ <?php _e('Copiado!', 'lab-resumos-parceiros'); ?>';
                        button.classList.add('lrp-copied');
                        
                        setTimeout(function() {
                            button.innerHTML = originalText;
                            button.classList.remove('lrp-copied');
                        }, 2000);
                    }).catch(function() {
                        // Fallback para navegadores antigos
                        var textarea = document.createElement('textarea');
                        textarea.value = link;
                        textarea.style.position = 'fixed';
                        textarea.style.opacity = '0';
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                        
                        button.innerHTML = '✅ <?php _e('Copiado!', 'lab-resumos-parceiros'); ?>';
                        button.classList.add('lrp-copied');
                        
                        setTimeout(function() {
                            button.innerHTML = originalText;
                            button.classList.remove('lrp-copied');
                        }, 2000);
                    });
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Aba: Vendas
     */
    private function render_tab_sales() {
        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 20;
        
        $sales = LRP_Referral::get_by_affiliate($this->affiliate->get_id(), [
            'limit'  => $per_page,
            'offset' => ($page - 1) * $per_page,
        ]);
        ?>
        <div class="lrp-tab-content lrp-tab-sales">
            <div class="lrp-section">
                <h3>💰 <?php _e('Histórico de Vendas', 'lab-resumos-parceiros'); ?></h3>
                
                <?php if (empty($sales)): ?>
                    <div class="lrp-empty">
                        <div class="lrp-empty-icon">📊</div>
                        <div class="lrp-empty-title"><?php _e('Nenhuma venda ainda', 'lab-resumos-parceiros'); ?></div>
                        <div class="lrp-empty-text"><?php _e('Compartilhe seu link e cupom para começar a ganhar comissões!', 'lab-resumos-parceiros'); ?></div>
                    </div>
                <?php else: ?>
                    <table class="lrp-table">
                        <thead>
                            <tr>
                                <th><?php _e('Data', 'lab-resumos-parceiros'); ?></th>
                                <th><?php _e('Pedido', 'lab-resumos-parceiros'); ?></th>
                                <th><?php _e('Tipo', 'lab-resumos-parceiros'); ?></th>
                                <th><?php _e('Valor Pago', 'lab-resumos-parceiros'); ?></th>
                                <th><?php _e('Comissão', 'lab-resumos-parceiros'); ?></th>
                                <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($sale->get_created_at()))); ?></td>
                                <td>#<?php echo esc_html($sale->get_order_id()); ?></td>
                                <td><?php echo $this->get_attribution_type_label($sale->get_attribution_type()); ?></td>
                                <td>R$ <?php echo esc_html(number_format($sale->get_commission_base(), 2, ',', '.')); ?></td>
                                <td>R$ <?php echo esc_html(number_format($sale->get_direct_commission(), 2, ',', '.')); ?></td>
                                <td><span class="lrp-status lrp-status-<?php echo esc_attr($sale->get_status()); ?>">
                                    <?php echo esc_html($this->get_status_label($sale->get_status())); ?>
                                </span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Aba: Análise de Tráfego
     * 
     * @since 1.2.0
     */
    private function render_tab_traffic() {
        $stats = LRP_Stats_Calculator::get_cached_stats($this->affiliate->get_id(), 'month');
        $visit_stats = LRP_Cookie_Tracker::instance()->get_visit_stats($this->affiliate->get_id(), 'month');
        
        // Decodifica distribuições
        $source_dist = $stats ? json_decode($stats->source_distribution, true) : [];
        $device_dist = $stats ? json_decode($stats->device_distribution, true) : [];
        
        // Estatísticas de horário/dia da semana
        $hourly_stats = $this->get_hourly_stats();
        $weekday_stats = $this->get_weekday_stats();
        ?>
        <div class="lrp-tab-content lrp-tab-traffic">
            
            <!-- Métricas de Funil -->
            <div class="lrp-stats-grid">
                <div class="lrp-card">
                    <h4><?php _e('Cliques', 'lab-resumos-parceiros'); ?></h4>
                    <div class="lrp-card-value"><?php echo esc_html(number_format($visit_stats['total_visits'])); ?></div>
                    <div class="lrp-card-label"><?php _e('Últimos 30 dias', 'lab-resumos-parceiros'); ?></div>
                </div>
                <div class="lrp-card">
                    <h4><?php _e('Visitantes Únicos', 'lab-resumos-parceiros'); ?></h4>
                    <div class="lrp-card-value"><?php echo esc_html(number_format($visit_stats['unique_visitors'])); ?></div>
                    <div class="lrp-card-label"><?php _e('Últimos 30 dias', 'lab-resumos-parceiros'); ?></div>
                </div>
                <div class="lrp-card">
                    <h4><?php _e('Conversões', 'lab-resumos-parceiros'); ?></h4>
                    <div class="lrp-card-value"><?php echo esc_html($visit_stats['conversions']); ?></div>
                    <div class="lrp-card-label"><?php _e('Vendas via link', 'lab-resumos-parceiros'); ?></div>
                </div>
                <div class="lrp-card lrp-card-highlight">
                    <h4><?php _e('Taxa de Conversão', 'lab-resumos-parceiros'); ?></h4>
                    <div class="lrp-card-value"><?php echo esc_html($visit_stats['conversion_rate']); ?>%</div>
                    <div class="lrp-card-label"><?php _e('Cliques → Vendas', 'lab-resumos-parceiros'); ?></div>
                </div>
            </div>
            
            <div class="lrp-traffic-grid">
                <!-- Fontes de Tráfego -->
                <div class="lrp-section">
                    <h3>📊 <?php _e('Fontes de Tráfego', 'lab-resumos-parceiros'); ?></h3>
                    <?php if (!empty($source_dist)): ?>
                        <div class="lrp-bar-chart">
                            <?php 
                            $max_clicks = max(array_column($source_dist, 'clicks'));
                            foreach ($source_dist as $source): 
                                $percentage = $max_clicks > 0 ? ($source['clicks'] / $max_clicks) * 100 : 0;
                                $total_clicks = array_sum(array_column($source_dist, 'clicks'));
                                $share = $total_clicks > 0 ? round(($source['clicks'] / $total_clicks) * 100) : 0;
                            ?>
                            <div class="lrp-bar-row">
                                <span class="lrp-bar-label"><?php echo esc_html($source['source']); ?></span>
                                <div class="lrp-bar-container">
                                    <div class="lrp-bar" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                </div>
                                <span class="lrp-bar-value"><?php echo esc_html($share); ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="lrp-empty-text"><?php _e('Dados de fonte ainda não disponíveis.', 'lab-resumos-parceiros'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Dispositivos -->
                <div class="lrp-section">
                    <h3>📱 <?php _e('Dispositivos', 'lab-resumos-parceiros'); ?></h3>
                    <?php if (!empty($device_dist)): ?>
                        <div class="lrp-device-chart">
                            <?php 
                            $total_devices = array_sum(array_column($device_dist, isset($device_dist[0]['clicks']) ? 'clicks' : 'sales'));
                            foreach ($device_dist as $device): 
                                $value = $device['clicks'] ?? $device['sales'] ?? 0;
                                $percentage = $total_devices > 0 ? round(($value / $total_devices) * 100) : 0;
                                $icon = $this->get_device_icon($device['device']);
                            ?>
                            <div class="lrp-device-item">
                                <span class="lrp-device-icon"><?php echo $icon; ?></span>
                                <span class="lrp-device-name"><?php echo esc_html(ucfirst($device['device'])); ?></span>
                                <div class="lrp-device-bar-container">
                                    <div class="lrp-device-bar" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                </div>
                                <span class="lrp-device-percentage"><?php echo esc_html($percentage); ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="lrp-empty-text"><?php _e('Dados de dispositivo ainda não disponíveis.', 'lab-resumos-parceiros'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="lrp-traffic-grid">
                <!-- Cliques por Dia da Semana -->
                <div class="lrp-section">
                    <h3>📅 <?php _e('Cliques por Dia da Semana', 'lab-resumos-parceiros'); ?></h3>
                    <?php if (!empty($weekday_stats)): ?>
                        <div class="lrp-weekday-chart">
                            <?php 
                            $max_day = max($weekday_stats);
                            $days = [
                                __('Dom', 'lab-resumos-parceiros'),
                                __('Seg', 'lab-resumos-parceiros'),
                                __('Ter', 'lab-resumos-parceiros'),
                                __('Qua', 'lab-resumos-parceiros'),
                                __('Qui', 'lab-resumos-parceiros'),
                                __('Sex', 'lab-resumos-parceiros'),
                                __('Sáb', 'lab-resumos-parceiros'),
                            ];
                            foreach ($weekday_stats as $day => $count): 
                                $height = $max_day > 0 ? ($count / $max_day) * 100 : 0;
                            ?>
                            <div class="lrp-weekday-bar">
                                <div class="lrp-weekday-fill" style="height: <?php echo esc_attr($height); ?>%"></div>
                                <span class="lrp-weekday-label"><?php echo esc_html($days[$day] ?? $day); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php 
                        $best_day_index = array_search(max($weekday_stats), $weekday_stats);
                        $best_day = $days[$best_day_index] ?? '';
                        if ($best_day): ?>
                        <p class="lrp-insight">
                            📌 <?php printf(__('Melhor dia: %s', 'lab-resumos-parceiros'), '<strong>' . esc_html($best_day) . '</strong>'); ?>
                        </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="lrp-empty-text"><?php _e('Dados de dia da semana ainda não disponíveis.', 'lab-resumos-parceiros'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Cliques por Horário -->
                <div class="lrp-section">
                    <h3>🕐 <?php _e('Cliques por Período', 'lab-resumos-parceiros'); ?></h3>
                    <?php if (!empty($hourly_stats)): ?>
                        <div class="lrp-hourly-chart">
                            <?php 
                            $periods = [
                                '00-06' => __('Madrugada (00-06h)', 'lab-resumos-parceiros'),
                                '06-12' => __('Manhã (06-12h)', 'lab-resumos-parceiros'),
                                '12-18' => __('Tarde (12-18h)', 'lab-resumos-parceiros'),
                                '18-24' => __('Noite (18-24h)', 'lab-resumos-parceiros'),
                            ];
                            $max_period = max($hourly_stats);
                            $total_period = array_sum($hourly_stats);
                            foreach ($hourly_stats as $period => $count): 
                                $percentage = $max_period > 0 ? ($count / $max_period) * 100 : 0;
                                $share = $total_period > 0 ? round(($count / $total_period) * 100) : 0;
                            ?>
                            <div class="lrp-bar-row">
                                <span class="lrp-bar-label"><?php echo esc_html($periods[$period] ?? $period); ?></span>
                                <div class="lrp-bar-container">
                                    <div class="lrp-bar" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                </div>
                                <span class="lrp-bar-value"><?php echo esc_html($share); ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php 
                        $best_period = array_search(max($hourly_stats), $hourly_stats);
                        if ($best_period && isset($periods[$best_period])): ?>
                        <p class="lrp-insight">
                            📌 <?php printf(__('Melhor período: %s', 'lab-resumos-parceiros'), '<strong>' . esc_html($periods[$best_period]) . '</strong>'); ?>
                        </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="lrp-empty-text"><?php _e('Dados de horário ainda não disponíveis.', 'lab-resumos-parceiros'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Dicas -->
            <div class="lrp-help-section">
                <details class="lrp-help-toggle">
                    <summary>
                        <span class="lrp-help-icon">💡</span>
                        <?php _e('Como usar esses dados?', 'lab-resumos-parceiros'); ?>
                        <?php echo $this->get_chevron_icon(); ?>
                    </summary>
                    <div class="lrp-help-content">
                        <p><?php _e('Entender de onde vem seu tráfego ajuda a otimizar suas estratégias:', 'lab-resumos-parceiros'); ?></p>
                        <ul>
                            <li><strong><?php _e('Fontes com mais cliques:', 'lab-resumos-parceiros'); ?></strong> <?php _e('Invista mais tempo e conteúdo nesses canais.', 'lab-resumos-parceiros'); ?></li>
                            <li><strong><?php _e('Dispositivos:', 'lab-resumos-parceiros'); ?></strong> <?php _e('Se a maioria usa mobile, priorize stories e conteúdo vertical.', 'lab-resumos-parceiros'); ?></li>
                            <li><strong><?php _e('Melhores dias/horários:', 'lab-resumos-parceiros'); ?></strong> <?php _e('Publique conteúdo promocional nesses momentos.', 'lab-resumos-parceiros'); ?></li>
                        </ul>
                    </div>
                </details>
            </div>
        </div>
        <?php
    }

    /**
     * Aba: Desempenho
     * 
     * @since 1.2.0
     */
    private function render_tab_performance() {
        $stats = LRP_Stats_Calculator::get_cached_stats($this->affiliate->get_id(), 'month');
        $ranking = LRP_Ranking::instance();
        $rank_data = $ranking->get_affiliate_ranking($this->affiliate->get_id(), 'sales', 'month');
        $comparison = $ranking->get_comparison_summary($this->affiliate->get_id(), 'month');
        
        // Decodifica distribuições
        $state_dist = $stats ? json_decode($stats->state_distribution, true) : [];
        $payment_dist = $stats ? json_decode($stats->payment_distribution, true) : [];
        $products_dist = $stats ? json_decode($stats->products_distribution, true) : [];
        ?>
        <div class="lrp-tab-content lrp-tab-performance">
            
            <!-- Ranking -->
            <?php if ($rank_data): ?>
            <div class="lrp-ranking-section">
                <div class="lrp-ranking-card">
                    <div class="lrp-ranking-position">
                        <span class="lrp-ranking-number">#<?php echo esc_html($rank_data['position']); ?></span>
                        <?php if ($rank_data['position_change'] > 0): ?>
                            <span class="lrp-ranking-change lrp-up">▲ <?php echo esc_html($rank_data['position_change']); ?></span>
                        <?php elseif ($rank_data['position_change'] < 0): ?>
                            <span class="lrp-ranking-change lrp-down">▼ <?php echo esc_html(abs($rank_data['position_change'])); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="lrp-ranking-info">
                        <h3><?php _e('Sua Posição', 'lab-resumos-parceiros'); ?></h3>
                        <p class="lrp-ranking-percentile">
                            <?php printf(__('Você está no TOP %d%% dos parceiros', 'lab-resumos-parceiros'), 100 - $rank_data['percentile']); ?>
                        </p>
                    </div>
                </div>
                
                <div class="lrp-comparison-cards">
                    <?php if ($comparison['sales']): ?>
                    <div class="lrp-comparison-item">
                        <span class="lrp-comparison-label"><?php _e('Vendas', 'lab-resumos-parceiros'); ?></span>
                        <span class="lrp-comparison-value <?php echo $comparison['sales']['diff_from_average'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $comparison['sales']['diff_from_average'] >= 0 ? '+' : ''; ?><?php echo esc_html($comparison['sales']['diff_from_average']); ?>%
                        </span>
                        <span class="lrp-comparison-desc"><?php echo esc_html($comparison['sales']['diff_label']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($comparison['ticket']): ?>
                    <div class="lrp-comparison-item">
                        <span class="lrp-comparison-label"><?php _e('Ticket Médio', 'lab-resumos-parceiros'); ?></span>
                        <span class="lrp-comparison-value <?php echo $comparison['ticket']['diff_from_average'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $comparison['ticket']['diff_from_average'] >= 0 ? '+' : ''; ?><?php echo esc_html($comparison['ticket']['diff_from_average']); ?>%
                        </span>
                        <span class="lrp-comparison-desc"><?php echo esc_html($comparison['ticket']['diff_label']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($comparison['commission']): ?>
                    <div class="lrp-comparison-item">
                        <span class="lrp-comparison-label"><?php _e('Comissões', 'lab-resumos-parceiros'); ?></span>
                        <span class="lrp-comparison-value <?php echo $comparison['commission']['diff_from_average'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $comparison['commission']['diff_from_average'] >= 0 ? '+' : ''; ?><?php echo esc_html($comparison['commission']['diff_from_average']); ?>%
                        </span>
                        <span class="lrp-comparison-desc"><?php echo esc_html($comparison['commission']['diff_label']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="lrp-performance-grid">
                <!-- Vendas por Estado -->
                <div class="lrp-section">
                    <h3>🗺️ <?php _e('Vendas por Estado', 'lab-resumos-parceiros'); ?></h3>
                    <?php if (!empty($state_dist)): ?>
                        <table class="lrp-table lrp-table-compact">
                            <thead>
                                <tr>
                                    <th><?php _e('UF', 'lab-resumos-parceiros'); ?></th>
                                    <th><?php _e('Vendas', 'lab-resumos-parceiros'); ?></th>
                                    <th><?php _e('% Total', 'lab-resumos-parceiros'); ?></th>
                                    <th><?php _e('Ticket Médio', 'lab-resumos-parceiros'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_sales = array_sum(array_column($state_dist, 'sales'));
                                foreach (array_slice($state_dist, 0, 10) as $state): 
                                    $share = $total_sales > 0 ? round(($state['sales'] / $total_sales) * 100) : 0;
                                    $avg_ticket = $state['sales'] > 0 ? ($state['revenue'] / $state['sales']) : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($state['uf']); ?></strong></td>
                                    <td><?php echo esc_html($state['sales']); ?></td>
                                    <td><?php echo esc_html($share); ?>%</td>
                                    <td>R$ <?php echo esc_html(number_format($avg_ticket, 2, ',', '.')); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="lrp-empty-text"><?php _e('Dados de estado ainda não disponíveis.', 'lab-resumos-parceiros'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Forma de Pagamento -->
                <div class="lrp-section">
                    <h3>💳 <?php _e('Forma de Pagamento', 'lab-resumos-parceiros'); ?></h3>
                    <?php if (!empty($payment_dist)): ?>
                        <div class="lrp-payment-chart">
                            <?php 
                            $total_payments = array_sum(array_column($payment_dist, 'sales'));
                            foreach ($payment_dist as $payment): 
                                $percentage = $total_payments > 0 ? round(($payment['sales'] / $total_payments) * 100) : 0;
                                $icon = $this->get_payment_icon($payment['payment_type']);
                            ?>
                            <div class="lrp-payment-item">
                                <span class="lrp-payment-icon"><?php echo $icon; ?></span>
                                <span class="lrp-payment-name"><?php echo esc_html($payment['payment_type']); ?></span>
                                <div class="lrp-payment-bar-container">
                                    <div class="lrp-payment-bar" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                </div>
                                <span class="lrp-payment-percentage"><?php echo esc_html($percentage); ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="lrp-empty-text"><?php _e('Dados de pagamento ainda não disponíveis.', 'lab-resumos-parceiros'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Produtos Mais Vendidos -->
            <?php if (!empty($products_dist)): ?>
            <div class="lrp-section">
                <h3>🏆 <?php _e('Produtos Mais Vendidos', 'lab-resumos-parceiros'); ?></h3>
                <table class="lrp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php _e('Produto', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Qtd', 'lab-resumos-parceiros'); ?></th>
                            <th><?php _e('Receita', 'lab-resumos-parceiros'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $position = 1;
                        foreach (array_slice($products_dist, 0, 5) as $product): 
                        ?>
                        <tr>
                            <td><?php echo esc_html($position++); ?></td>
                            <td><?php echo esc_html($product['product_name']); ?></td>
                            <td><?php echo esc_html($product['quantity']); ?></td>
                            <td>R$ <?php echo esc_html(number_format($product['revenue'], 2, ',', '.')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Dicas de Desempenho -->
            <div class="lrp-help-section">
                <details class="lrp-help-toggle">
                    <summary>
                        <span class="lrp-help-icon">💡</span>
                        <?php _e('Como melhorar meu desempenho?', 'lab-resumos-parceiros'); ?>
                        <?php echo $this->get_chevron_icon(); ?>
                    </summary>
                    <div class="lrp-help-content">
                        <p><?php _e('Algumas dicas para aumentar suas vendas:', 'lab-resumos-parceiros'); ?></p>
                        <ul>
                            <li><strong><?php _e('Estados com mais vendas:', 'lab-resumos-parceiros'); ?></strong> <?php _e('Foque conteúdo relevante para essas regiões.', 'lab-resumos-parceiros'); ?></li>
                            <li><strong><?php _e('PIX domina:', 'lab-resumos-parceiros'); ?></strong> <?php _e('Destaque que aceitamos PIX com desconto automático.', 'lab-resumos-parceiros'); ?></li>
                            <li><strong><?php _e('Produtos campeões:', 'lab-resumos-parceiros'); ?></strong> <?php _e('Promova mais os produtos que já vendem bem.', 'lab-resumos-parceiros'); ?></li>
                            <li><strong><?php _e('Ranking:', 'lab-resumos-parceiros'); ?></strong> <?php _e('Compare-se com a média e busque melhorar a cada mês.', 'lab-resumos-parceiros'); ?></li>
                        </ul>
                    </div>
                </details>
            </div>
        </div>
        <?php
    }

    /**
     * Obtém estatísticas por hora do dia
     *
     * @return array
     */
    private function get_hourly_stats() {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                CASE 
                    WHEN HOUR(created_at) BETWEEN 0 AND 5 THEN '00-06'
                    WHEN HOUR(created_at) BETWEEN 6 AND 11 THEN '06-12'
                    WHEN HOUR(created_at) BETWEEN 12 AND 17 THEN '12-18'
                    ELSE '18-24'
                END AS period,
                COUNT(*) AS clicks
            FROM {$wpdb->prefix}lrp_visits
            WHERE affiliate_id = %d
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY period
            ORDER BY FIELD(period, '00-06', '06-12', '12-18', '18-24')
        ", $this->affiliate->get_id()), ARRAY_A);
        
        // Inicializa todos os períodos
        $stats = [
            '00-06' => 0,
            '06-12' => 0,
            '12-18' => 0,
            '18-24' => 0,
        ];
        
        foreach ($results as $row) {
            $stats[$row['period']] = (int) $row['clicks'];
        }
        
        return $stats;
    }

    /**
     * Obtém estatísticas por dia da semana
     *
     * @return array
     */
    private function get_weekday_stats() {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DAYOFWEEK(created_at) - 1 AS day_of_week,
                COUNT(*) AS clicks
            FROM {$wpdb->prefix}lrp_visits
            WHERE affiliate_id = %d
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY day_of_week
            ORDER BY day_of_week
        ", $this->affiliate->get_id()), ARRAY_A);
        
        // Inicializa todos os dias (0 = Domingo, 6 = Sábado)
        $stats = array_fill(0, 7, 0);
        
        foreach ($results as $row) {
            $stats[(int) $row['day_of_week']] = (int) $row['clicks'];
        }
        
        return $stats;
    }

    /**
     * Obtém ícone do dispositivo
     *
     * @param string $device
     * @return string
     */
    private function get_device_icon($device) {
        $icons = [
            'mobile'  => '📱',
            'desktop' => '💻',
            'tablet'  => '📟',
            'Mobile'  => '📱',
            'Desktop' => '💻',
            'Tablet'  => '📟',
        ];
        
        return $icons[$device] ?? '❓';
    }

    /**
     * Obtém ícone do método de pagamento
     *
     * @param string $payment
     * @return string
     */
    private function get_payment_icon($payment) {
        $icons = [
            'PIX'     => '⚡',
            'Cartão'  => '💳',
            'Boleto'  => '📄',
            'Outro'   => '💰',
        ];
        
        return $icons[$payment] ?? '💰';
    }

    /**
     * Aba: Minha Rede
     */
    private function render_tab_network() {
        $network = LRP_Network::instance();
        $stats = $network->get_network_stats($this->affiliate->get_id());
        $tree = $network->get_downline_tree($this->affiliate->get_id());
        $sponsor_url = $this->affiliate->get_sponsor_url();
        ?>
        <div class="lrp-tab-content lrp-tab-network">
            
            <!-- Card: Link de Convite -->
            <div class="lrp-link-card">
                <div class="lrp-link-card-header">
                    <div class="lrp-link-card-title">
                        <div class="lrp-link-card-icon lrp-icon-network">👥</div>
                        <div>
                            <h4><?php _e('Link de Convite para Novos Parceiros', 'lab-resumos-parceiros'); ?></h4>
                            <p><?php _e('Convide pessoas para serem afiliados e ganhe comissões sobre as vendas deles', 'lab-resumos-parceiros'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="lrp-link-input-wrapper">
                    <div class="lrp-link-input-box">
                        <input type="text" readonly value="<?php echo esc_url($sponsor_url); ?>" class="lrp-link-input">
                    </div>
                    <button class="lrp-copy-btn-large" data-copy="<?php echo esc_attr($sponsor_url); ?>">
                        <?php echo $this->get_copy_icon(); ?>
                        <span><?php _e('Copiar Link', 'lab-resumos-parceiros'); ?></span>
                    </button>
                </div>
                
                <div class="lrp-link-card-info">
                    <?php echo $this->get_info_icon(); ?>
                    <div>
                        <?php _e('Quando alguém se cadastra usando seu link, essa pessoa entra na <strong>sua rede</strong>. Você ganha uma porcentagem sobre todas as vendas que ela fizer!', 'lab-resumos-parceiros'); ?>
                    </div>
                </div>
            </div>
            
            <!-- Estatísticas da Rede -->
            <div class="lrp-stats-grid lrp-stats-grid-small">
                <div class="lrp-card">
                    <div class="lrp-card-value"><?php echo esc_html($stats['level_2_count']); ?></div>
                    <div class="lrp-card-label"><?php _e('Sub-afiliados Diretos', 'lab-resumos-parceiros'); ?></div>
                </div>
                <div class="lrp-card">
                    <div class="lrp-card-value"><?php echo esc_html($stats['level_3_count']); ?></div>
                    <div class="lrp-card-label"><?php _e('Nível 3', 'lab-resumos-parceiros'); ?></div>
                </div>
                <div class="lrp-card lrp-card-highlight">
                    <div class="lrp-card-value">R$ <?php echo esc_html(number_format($stats['total_commissions'], 2, ',', '.')); ?></div>
                    <div class="lrp-card-label"><?php _e('Ganhos da Rede', 'lab-resumos-parceiros'); ?></div>
                </div>
            </div>
            
            <!-- Seção de Ajuda sobre Rede -->
            <div class="lrp-help-section">
                <details class="lrp-help-toggle" open>
                    <summary>
                        <span class="lrp-help-icon">🎓</span>
                        <?php _e('Como funciona a rede de afiliados?', 'lab-resumos-parceiros'); ?>
                        <?php echo $this->get_chevron_icon(); ?>
                    </summary>
                    <div class="lrp-help-content">
                        <p><?php _e('O programa de afiliados tem <strong>3 níveis</strong>. Você pode ganhar comissões não só das suas vendas diretas, mas também das vendas dos parceiros que você indicar:', 'lab-resumos-parceiros'); ?></p>
                        
                        <table class="lrp-commission-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Nível', 'lab-resumos-parceiros'); ?></th>
                                    <th><?php _e('Quem é', 'lab-resumos-parceiros'); ?></th>
                                    <th><?php _e('Sua comissão', 'lab-resumos-parceiros'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="lrp-level-badge">👤 <?php _e('Nível 1', 'lab-resumos-parceiros'); ?></span></td>
                                    <td><?php _e('Você (suas vendas diretas)', 'lab-resumos-parceiros'); ?></td>
                                    <td><strong>15%</strong></td>
                                </tr>
                                <tr>
                                    <td><span class="lrp-level-badge">👥 <?php _e('Nível 2', 'lab-resumos-parceiros'); ?></span></td>
                                    <td><?php _e('Pessoas que você convidou', 'lab-resumos-parceiros'); ?></td>
                                    <td><strong>3%</strong> <?php _e('sobre as vendas deles', 'lab-resumos-parceiros'); ?></td>
                                </tr>
                                <tr>
                                    <td><span class="lrp-level-badge">👥👥 <?php _e('Nível 3', 'lab-resumos-parceiros'); ?></span></td>
                                    <td><?php _e('Pessoas convidadas pelos seus convidados', 'lab-resumos-parceiros'); ?></td>
                                    <td><strong>1%</strong> <?php _e('sobre as vendas deles', 'lab-resumos-parceiros'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="lrp-example-box">
                            <div class="lrp-example-box-title">📊 <?php _e('Exemplo prático', 'lab-resumos-parceiros'); ?></div>
                            <div class="lrp-example-content">
                                <?php _e('<strong>Você</strong> convida <strong>João</strong> para ser parceiro.<br>
                                João convida <strong>Maria</strong> para ser parceira.<br><br>
                                Quando <strong>Maria</strong> faz uma venda de R$100:<br>
                                → Maria ganha <span class="lrp-example-highlight">R$15</span> (15% - venda dela)<br>
                                → João ganha <span class="lrp-example-highlight">R$3</span> (3% - nível 2 dele)<br>
                                → Você ganha <span class="lrp-example-highlight">R$1</span> (1% - nível 3 seu)', 'lab-resumos-parceiros'); ?>
                            </div>
                        </div>
                    </div>
                </details>
                
                <details class="lrp-help-toggle">
                    <summary>
                        <span class="lrp-help-icon">🚀</span>
                        <?php _e('Como convidar novos parceiros?', 'lab-resumos-parceiros'); ?>
                        <?php echo $this->get_chevron_icon(); ?>
                    </summary>
                    <div class="lrp-help-content">
                        <p><?php _e('É simples! Basta compartilhar o <strong>Link de Convite</strong> acima. Quando alguém clicar e se cadastrar, automaticamente entra na sua rede.', 'lab-resumos-parceiros'); ?></p>
                        
                        <p><strong><?php _e('Dicas para encontrar bons parceiros:', 'lab-resumos-parceiros'); ?></strong></p>
                        <ul>
                            <li><?php _e('Professores e educadores que conhecem o público-alvo', 'lab-resumos-parceiros'); ?></li>
                            <li><?php _e('Criadores de conteúdo na área de concursos/estudos', 'lab-resumos-parceiros'); ?></li>
                            <li><?php _e('Colegas de estudo e grupos de preparação', 'lab-resumos-parceiros'); ?></li>
                            <li><?php _e('Pessoas com presença em redes sociais', 'lab-resumos-parceiros'); ?></li>
                        </ul>
                        
                        <div class="lrp-example-box">
                            <div class="lrp-example-box-title">💡 <?php _e('Lembre-se', 'lab-resumos-parceiros'); ?></div>
                            <div class="lrp-example-content">
                                <?php _e('Quanto melhor o parceiro que você indicar, mais ele vai vender, e mais você vai ganhar! Busque pessoas comprometidas e com audiência relevante.', 'lab-resumos-parceiros'); ?>
                            </div>
                        </div>
                    </div>
                </details>
            </div>
            
            <!-- Árvore da Rede -->
            <?php if (!empty($tree)): ?>
            <div class="lrp-section">
                <h3>👥 <?php _e('Sua Rede', 'lab-resumos-parceiros'); ?></h3>
                <div class="lrp-network-tree">
                    <?php foreach ($tree as $member): ?>
                    <div class="lrp-network-member lrp-level-2">
                        <div class="lrp-member-info">
                            <span class="lrp-member-name"><?php echo esc_html($member['name']); ?></span>
                            <span class="lrp-member-stats">
                                <?php printf(__('%d vendas | R$ %s', 'lab-resumos-parceiros'), 
                                    $member['total_sales'], 
                                    number_format($member['total_revenue'], 2, ',', '.')
                                ); ?>
                            </span>
                        </div>
                        <?php if (!empty($member['children'])): ?>
                        <div class="lrp-member-children">
                            <?php foreach ($member['children'] as $child): ?>
                            <div class="lrp-network-member lrp-level-3">
                                <div class="lrp-member-info">
                                    <span class="lrp-member-name"><?php echo esc_html($child['name']); ?></span>
                                    <span class="lrp-member-stats">
                                        <?php printf(__('%d vendas', 'lab-resumos-parceiros'), $child['total_sales']); ?>
                                    </span>
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
                <div class="lrp-section">
                    <div class="lrp-empty">
                        <div class="lrp-empty-icon">👥</div>
                        <div class="lrp-empty-title"><?php _e('Sua rede está vazia', 'lab-resumos-parceiros'); ?></div>
                        <div class="lrp-empty-text"><?php _e('Compartilhe seu link de convite e comece a construir sua rede de parceiros!', 'lab-resumos-parceiros'); ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Aba: Financeiro
     */
    private function render_tab_financial() {
        $settings = LRP_Settings::instance();
        $company = $settings->get_company_data();
        $nf_data = $settings->get_nf_data();
        $closings = LRP_Closing::get_by_affiliate($this->affiliate->get_id(), ['limit' => 12]);
        $pending_closings = LRP_Closing::get_all_pending_closings($this->affiliate->get_id());
        $is_rpa = $this->affiliate->is_rpa();
        ?>
        <div class="lrp-tab-content lrp-tab-financial">
            
            <?php if (!$is_rpa): ?>
            <!-- Dados para emissão de NF (sempre visível para PJ) -->
            <div class="lrp-section">
                <h3><?php _e('Dados para Emissão de Nota Fiscal', 'lab-resumos-parceiros'); ?></h3>
                <p class="lrp-text-muted"><?php _e('Use estes dados ao emitir sua NF de serviços. Você pode pré-cadastrar o tomador no sistema da sua prefeitura.', 'lab-resumos-parceiros'); ?></p>
                
                <div class="lrp-company-data">
                    <p><strong><?php _e('Tomador:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($company['name']); ?></p>
                    <?php if ($company['cnpj']): ?>
                    <p><strong><?php _e('CNPJ:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($company['cnpj']); ?></p>
                    <?php endif; ?>
                    <?php if ($company['address']): ?>
                    <p><strong><?php _e('Endereço:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($company['address']); ?></p>
                    <?php endif; ?>
                    <p><strong><?php _e('Descrição do Serviço:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($nf_data['service_description']); ?></p>
                </div>
                
                <?php if (!empty($nf_data['instructions'])): ?>
                <div class="lrp-nf-instructions" style="margin-top: 10px;">
                    <h4><?php _e('Como emitir sua Nota Fiscal:', 'lab-resumos-parceiros'); ?></h4>
                    <div><?php echo wp_kses_post($nf_data['instructions']); ?></div>
                </div>
                <?php endif; ?>
                
                <p style="margin-top: 10px;">
                    <strong><?php _e('Dúvidas sobre emissão de NF?', 'lab-resumos-parceiros'); ?></strong> 
                    <?php printf(
                        __('Entre em contato: %s', 'lab-resumos-parceiros'),
                        '<a href="mailto:' . esc_attr($nf_data['contact_email']) . '">' . esc_html($nf_data['contact_email']) . '</a>'
                    ); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($pending_closings)): ?>
            <?php foreach ($pending_closings as $pending): ?>
            <div class="lrp-section lrp-section-highlight" style="margin-bottom: 15px;">
                <?php
                $period_label = str_pad($pending->period_month, 2, '0', STR_PAD_LEFT) . '/' . $pending->period_year;
                $value_label = 'R$ ' . esc_html(number_format($pending->total_commissions, 2, ',', '.'));
                ?>

                <?php if ($pending->status === 'awaiting_rpa'): ?>
                <h3><?php printf(__('Aguardando Emissão de RPA — %s', 'lab-resumos-parceiros'), $period_label); ?></h3>
                <p><?php printf(__('Seu fechamento de %s está pronto. A empresa irá emitir o RPA para pagamento.', 'lab-resumos-parceiros'), $period_label); ?></p>
                <p><strong><?php _e('Valor:', 'lab-resumos-parceiros'); ?></strong> <?php echo $value_label; ?></p>

                <?php elseif ($pending->status === 'awaiting_invoice'): ?>
                <h3><?php printf(__('NF Pendente de Envio — %s', 'lab-resumos-parceiros'), $period_label); ?></h3>
                <p><?php printf(__('Você tem %s a receber referente a %s.', 'lab-resumos-parceiros'), $value_label, $period_label); ?></p>
                
                <form class="lrp-invoice-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="lrp_upload_invoice">
                    <input type="hidden" name="closing_id" value="<?php echo esc_attr($pending->id); ?>">
                    <?php wp_nonce_field('lrp_upload_invoice', 'lrp_nonce'); ?>
                    
                    <div class="lrp-form-group">
                        <label><?php _e('Número da NF', 'lab-resumos-parceiros'); ?></label>
                        <input type="text" name="invoice_number" class="lrp-input">
                    </div>
                    
                    <div class="lrp-form-group">
                        <label><?php _e('Arquivo da NF (PDF)', 'lab-resumos-parceiros'); ?></label>
                        <input type="file" name="invoice_file" accept=".pdf" required>
                    </div>
                    
                    <button type="submit" class="lrp-btn lrp-btn-primary"><?php _e('Enviar NF', 'lab-resumos-parceiros'); ?></button>
                </form>

                <?php elseif ($pending->status === 'invoice_received'): ?>
                <h3><?php printf(__('NF em Análise — %s', 'lab-resumos-parceiros'), $period_label); ?></h3>
                <p><?php _e('Sua Nota Fiscal está sendo validada. Você será notificado por email.', 'lab-resumos-parceiros'); ?></p>
                <p><strong><?php _e('Valor:', 'lab-resumos-parceiros'); ?></strong> <?php echo $value_label; ?></p>

                <?php elseif ($pending->status === 'approved'): ?>
                <h3><?php printf(__('Pagamento em Processamento — %s', 'lab-resumos-parceiros'), $period_label); ?></h3>
                <p><?php _e('Aprovado! O pagamento será realizado via PIX em até 5 dias úteis.', 'lab-resumos-parceiros'); ?></p>
                <p><strong><?php _e('Valor:', 'lab-resumos-parceiros'); ?></strong> <?php echo $value_label; ?></p>

                <?php elseif ($pending->status === 'rejected'): ?>
                <h3><?php printf(__('NF Rejeitada — %s', 'lab-resumos-parceiros'), $period_label); ?></h3>
                <p><?php _e('Sua NF precisa de correção. Por favor, emita uma nova.', 'lab-resumos-parceiros'); ?></p>
                <?php if ($pending->rejection_reason): ?>
                <p><strong><?php _e('Motivo:', 'lab-resumos-parceiros'); ?></strong> <?php echo esc_html($pending->rejection_reason); ?></p>
                <?php endif; ?>
                
                <form class="lrp-invoice-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="lrp_upload_invoice">
                    <input type="hidden" name="closing_id" value="<?php echo esc_attr($pending->id); ?>">
                    <?php wp_nonce_field('lrp_upload_invoice', 'lrp_nonce'); ?>
                    
                    <div class="lrp-form-group">
                        <label><?php _e('Número da NF', 'lab-resumos-parceiros'); ?></label>
                        <input type="text" name="invoice_number" class="lrp-input">
                    </div>
                    
                    <div class="lrp-form-group">
                        <label><?php _e('Arquivo da NF (PDF)', 'lab-resumos-parceiros'); ?></label>
                        <input type="file" name="invoice_file" accept=".pdf" required>
                    </div>
                    
                    <button type="submit" class="lrp-btn lrp-btn-primary"><?php _e('Enviar Nova NF', 'lab-resumos-parceiros'); ?></button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            
            <div class="lrp-section">
                <h3>📋 <?php _e('Histórico de Fechamentos', 'lab-resumos-parceiros'); ?></h3>
                
                <?php if (empty($closings)): ?>
                    <div class="lrp-empty">
                        <div class="lrp-empty-icon">📋</div>
                        <div class="lrp-empty-title"><?php _e('Nenhum fechamento ainda', 'lab-resumos-parceiros'); ?></div>
                        <div class="lrp-empty-text"><?php _e('Os fechamentos aparecem aqui no início de cada mês.', 'lab-resumos-parceiros'); ?></div>
                    </div>
                <?php else: ?>
                    <table class="lrp-table">
                        <thead>
                            <tr>
                                <th><?php _e('Período', 'lab-resumos-parceiros'); ?></th>
                                <th><?php _e('Vendas', 'lab-resumos-parceiros'); ?></th>
                                <th><?php _e('Comissões', 'lab-resumos-parceiros'); ?></th>
                                <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($closings as $closing): ?>
                            <tr>
                                <td><?php printf('%02d/%d', $closing->period_month, $closing->period_year); ?></td>
                                <td><?php echo esc_html($closing->total_sales); ?></td>
                                <td>R$ <?php echo esc_html(number_format($closing->total_commissions, 2, ',', '.')); ?></td>
                                <td><span class="lrp-status lrp-status-<?php echo esc_attr($closing->status); ?>">
                                    <?php echo esc_html($this->get_closing_status_label($closing->status)); ?>
                                </span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Aba: Ajustes e Bônus
     * 
     * @since 1.4.0
     */
    private function render_tab_adjustments() {
        // Busca ajustes do afiliado
        $adjustments = [];
        $pending_sum = 0;
        $total_bonus = 0;
        $total_discount = 0;
        
        if (class_exists('LRP_Adjustment')) {
            $adjustments = LRP_Adjustment::get_by_affiliate($this->affiliate->get_id(), [
                'limit' => 50,
            ]);
            $pending_sum = LRP_Adjustment::get_pending_sum($this->affiliate->get_id());
            
            // Calcula totais
            foreach ($adjustments as $adj) {
                if ($adj->status !== 'cancelled') {
                    if ((float) $adj->amount >= 0) {
                        $total_bonus += (float) $adj->amount;
                    } else {
                        $total_discount += abs((float) $adj->amount);
                    }
                }
            }
        }
        ?>
        <div class="lrp-tab-content lrp-tab-adjustments">
            <div class="lrp-section">
                <h3>🎁 <?php _e('Ajustes e Bônus', 'lab-resumos-parceiros'); ?></h3>
                
                <p class="lrp-section-description">
                    <?php _e('Aqui você pode acompanhar os ajustes aplicados à sua conta, como bônus especiais, correções e outras bonificações.', 'lab-resumos-parceiros'); ?>
                </p>
                
                <!-- Cards de resumo -->
                <div class="lrp-stats-grid lrp-stats-grid-small">
                    <div class="lrp-card">
                        <div class="lrp-card-value lrp-text-success">
                            +R$ <?php echo esc_html(number_format($total_bonus, 2, ',', '.')); ?>
                        </div>
                        <div class="lrp-card-label"><?php _e('Total em Bônus', 'lab-resumos-parceiros'); ?></div>
                    </div>
                    <div class="lrp-card">
                        <div class="lrp-card-value lrp-text-danger">
                            -R$ <?php echo esc_html(number_format($total_discount, 2, ',', '.')); ?>
                        </div>
                        <div class="lrp-card-label"><?php _e('Total em Descontos', 'lab-resumos-parceiros'); ?></div>
                    </div>
                    <div class="lrp-card lrp-card-highlight">
                        <div class="lrp-card-value">
                            <?php 
                            $prefix = $pending_sum >= 0 ? '+' : '';
                            $class = $pending_sum >= 0 ? 'lrp-text-success' : 'lrp-text-danger';
                            ?>
                            <span class="<?php echo esc_attr($class); ?>">
                                <?php echo esc_html($prefix . 'R$ ' . number_format($pending_sum, 2, ',', '.')); ?>
                            </span>
                        </div>
                        <div class="lrp-card-label"><?php _e('Ajustes Pendentes (no saldo)', 'lab-resumos-parceiros'); ?></div>
                    </div>
                </div>
                
                <?php if (empty($adjustments)): ?>
                    <div class="lrp-empty">
                        <div class="lrp-empty-icon">🎁</div>
                        <div class="lrp-empty-title"><?php _e('Nenhum ajuste registrado', 'lab-resumos-parceiros'); ?></div>
                        <div class="lrp-empty-text"><?php _e('Quando houver bônus ou ajustes na sua conta, eles aparecerão aqui.', 'lab-resumos-parceiros'); ?></div>
                    </div>
                <?php else: ?>
                    <table class="lrp-table">
                        <thead>
                            <tr>
                                <th><?php _e('Data', 'lab-resumos-parceiros'); ?></th>
                                <th><?php _e('Valor', 'lab-resumos-parceiros'); ?></th>
                                <th><?php _e('Motivo', 'lab-resumos-parceiros'); ?></th>
                                <th><?php _e('Status', 'lab-resumos-parceiros'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adjustments as $adj): ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($adj->created_at))); ?></td>
                                <td>
                                    <?php 
                                    $amount = (float) $adj->amount;
                                    $class = $amount >= 0 ? 'lrp-text-success' : 'lrp-text-danger';
                                    $prefix = $amount >= 0 ? '+' : '';
                                    ?>
                                    <strong class="<?php echo esc_attr($class); ?>">
                                        <?php echo esc_html($prefix . 'R$ ' . number_format($amount, 2, ',', '.')); ?>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($adj->reason); ?></td>
                                <td>
                                    <?php
                                    $status_labels = [
                                        'pending'   => ['label' => __('Pendente', 'lab-resumos-parceiros'), 'class' => 'lrp-status-pending'],
                                        'closed'    => ['label' => __('Fechado', 'lab-resumos-parceiros'), 'class' => 'lrp-status-approved'],
                                        'paid'      => ['label' => __('Pago', 'lab-resumos-parceiros'), 'class' => 'lrp-status-paid'],
                                        'cancelled' => ['label' => __('Cancelado', 'lab-resumos-parceiros'), 'class' => 'lrp-status-rejected'],
                                    ];
                                    $status = $status_labels[$adj->status] ?? ['label' => $adj->status, 'class' => ''];
                                    ?>
                                    <span class="lrp-status <?php echo esc_attr($status['class']); ?>">
                                        <?php echo esc_html($status['label']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Seção de Ajuda -->
            <div class="lrp-help-section">
                <details class="lrp-help-toggle">
                    <summary>
                        <span class="lrp-help-icon">💡</span>
                        <?php _e('O que são ajustes e bônus?', 'lab-resumos-parceiros'); ?>
                        <?php echo $this->get_chevron_icon(); ?>
                    </summary>
                    <div class="lrp-help-content">
                        <p><?php _e('Ajustes são valores adicionados ou subtraídos da sua conta pela equipe administrativa. Podem incluir:', 'lab-resumos-parceiros'); ?></p>
                        <ul>
                            <li><strong class="lrp-text-success"><?php _e('Bônus (+):', 'lab-resumos-parceiros'); ?></strong> <?php _e('Bonificações especiais, premiações por metas atingidas, compensações, etc.', 'lab-resumos-parceiros'); ?></li>
                            <li><strong class="lrp-text-danger"><?php _e('Descontos (-):', 'lab-resumos-parceiros'); ?></strong> <?php _e('Correções de valores, ajustes por reembolsos especiais, etc.', 'lab-resumos-parceiros'); ?></li>
                        </ul>
                        <p><?php _e('Os ajustes <strong>pendentes</strong> já estão incluídos no seu saldo disponível e serão incluídos no próximo fechamento mensal para pagamento.', 'lab-resumos-parceiros'); ?></p>
                    </div>
                </details>
            </div>
        </div>
        <?php
    }

    /**
     * Aba: Materiais
     */
    private function render_tab_materials() {
        global $wpdb;
        
        $materials = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}lrp_materials WHERE is_active = 1 ORDER BY category, display_order"
        );
        
        $categories = [];
        foreach ($materials as $m) {
            $cat = $m->category ?: 'geral';
            if (!isset($categories[$cat])) {
                $categories[$cat] = [];
            }
            $categories[$cat][] = $m;
        }
        ?>
        <div class="lrp-tab-content lrp-tab-materials">
            <div class="lrp-section">
                <h3>📦 <?php _e('Materiais de Divulgação', 'lab-resumos-parceiros'); ?></h3>
                
                <?php if (empty($materials)): ?>
                    <div class="lrp-empty">
                        <div class="lrp-empty-icon">📦</div>
                        <div class="lrp-empty-title"><?php _e('Nenhum material disponível', 'lab-resumos-parceiros'); ?></div>
                        <div class="lrp-empty-text"><?php _e('Em breve disponibilizaremos materiais para você usar em suas divulgações.', 'lab-resumos-parceiros'); ?></div>
                    </div>
                <?php else: ?>
                    <?php foreach ($categories as $cat_name => $items): ?>
                    <div class="lrp-materials-category">
                        <h4><?php echo esc_html(ucfirst($cat_name)); ?></h4>
                        <div class="lrp-materials-grid">
                            <?php foreach ($items as $material): ?>
                            <div class="lrp-material-item">
                                <?php if ($material->type === 'image' && $material->file_url): ?>
                                    <img src="<?php echo esc_url($material->file_url); ?>" alt="<?php echo esc_attr($material->title); ?>">
                                <?php endif; ?>
                                <h5><?php echo esc_html($material->title); ?></h5>
                                <?php if ($material->description): ?>
                                    <p><?php echo esc_html($material->description); ?></p>
                                <?php endif; ?>
                                <?php if ($material->type === 'text' && $material->content): ?>
                                    <div class="lrp-material-content">
                                        <textarea readonly><?php echo esc_textarea($material->content); ?></textarea>
                                        <button class="lrp-copy-btn" data-copy="<?php echo esc_attr($material->content); ?>">
                                            <?php echo $this->get_copy_icon(); ?> <?php _e('Copiar', 'lab-resumos-parceiros'); ?>
                                        </button>
                                    </div>
                                <?php elseif ($material->file_url): ?>
                                    <a href="<?php echo esc_url($material->file_url); ?>" download class="lrp-btn"><?php _e('Download', 'lab-resumos-parceiros'); ?></a>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Aba: Área do Aluno
     */
    private function render_tab_area_aluno() {
        ?>
        <div class="lrp-tab-content lrp-tab-area-aluno">
            <div class="lrp-section">
                <h3>📚 <?php _e('Área do Aluno', 'lab-resumos-parceiros'); ?></h3>
                
                <div class="lrp-area-aluno-card">
                    <div class="lrp-area-aluno-icon">
                        📚
                    </div>
                    <div class="lrp-area-aluno-content">
                        <h4><?php _e('Como acessar seu conteúdo', 'lab-resumos-parceiros'); ?></h4>
                        <p><?php _e('Todo o material adquirido estará disponível na nossa <strong>Área do Aluno</strong>.', 'lab-resumos-parceiros'); ?></p>
                        <p><?php _e('Acesse agora mesmo em:', 'lab-resumos-parceiros'); ?></p>
                        <a href="https://aluno.labresumos.com.br/" target="_blank" class="lrp-btn lrp-btn-primary lrp-btn-area-aluno">
                            <?php _e('Acessar Área do Aluno', 'lab-resumos-parceiros'); ?> →
                        </a>
                    </div>
                </div>
                
                <div class="lrp-area-aluno-info">
                    <h4>💡 <?php _e('Informações importantes', 'lab-resumos-parceiros'); ?></h4>
                    <ul>
                        <li><?php _e('Utilize o mesmo e-mail cadastrado no Lab Resumos para acessar.', 'lab-resumos-parceiros'); ?></li>
                        <li><?php _e('Se for seu primeiro acesso, clique em "Perdeu a senha?" para criar sua senha de acesso.', 'lab-resumos-parceiros'); ?></li>
                        <li><?php _e('Em caso de dúvidas, entre em contato com nosso suporte.', 'lab-resumos-parceiros'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Aba: FAQ
     */
    private function render_tab_faq() {
        global $wpdb;
        
        $faqs = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}lrp_faq WHERE is_active = 1 ORDER BY category, display_order"
        );
        
        $categories = [];
        foreach ($faqs as $faq) {
            $cat = $faq->category ?: 'geral';
            if (!isset($categories[$cat])) {
                $categories[$cat] = [];
            }
            $categories[$cat][] = $faq;
        }
        ?>
        <div class="lrp-tab-content lrp-tab-faq">
            <div class="lrp-section">
                <h3>❓ <?php _e('Perguntas Frequentes', 'lab-resumos-parceiros'); ?></h3>
                
                <?php foreach ($categories as $cat_name => $items): ?>
                <div class="lrp-faq-category">
                    <h4><?php echo esc_html(ucfirst(str_replace('-', ' ', $cat_name))); ?></h4>
                    <div class="lrp-faq-list">
                        <?php foreach ($items as $faq): ?>
                        <details class="lrp-faq-item">
                            <summary><?php echo esc_html($faq->question); ?></summary>
                            <div class="lrp-faq-answer"><?php echo wp_kses_post($faq->answer); ?></div>
                        </details>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Aba: Meu Perfil
     */
    private function render_tab_profile() {
        $payment_data = $this->affiliate->get_payment_data();
        $user = $this->affiliate->get_user();
        ?>
        <div class="lrp-tab-content lrp-tab-profile">
            <div class="lrp-section">
                <h3>👤 <?php _e('Meu Perfil', 'lab-resumos-parceiros'); ?></h3>
                
                <form class="lrp-profile-form" id="lrp-profile-form">
                    <input type="hidden" name="action" value="lrp_update_profile">
                    <?php wp_nonce_field('lrp_update_profile', 'lrp_nonce'); ?>
                    
                    <div class="lrp-form-section">
                        <h4><?php _e('Dados Pessoais', 'lab-resumos-parceiros'); ?></h4>
                        
                        <div class="lrp-form-group">
                            <label><?php _e('Nome', 'lab-resumos-parceiros'); ?></label>
                            <input type="text" value="<?php echo esc_attr($user->display_name); ?>" readonly class="lrp-input lrp-input-readonly">
                        </div>
                        
                        <div class="lrp-form-group">
                            <label><?php _e('Email', 'lab-resumos-parceiros'); ?></label>
                            <input type="email" value="<?php echo esc_attr($user->user_email); ?>" readonly class="lrp-input lrp-input-readonly">
                        </div>
                    </div>
                    
                    <div class="lrp-form-section">
                        <h4><?php _e('Dados para Pagamento', 'lab-resumos-parceiros'); ?></h4>
                        
                        <div class="lrp-form-group">
                            <label><?php _e('Método de Pagamento', 'lab-resumos-parceiros'); ?></label>
                            <select name="payment_method" class="lrp-input">
                                <option value="pix" <?php selected($payment_data['method'], 'pix'); ?>><?php _e('PIX', 'lab-resumos-parceiros'); ?></option>
                                <option value="bank_transfer" <?php selected($payment_data['method'], 'bank_transfer'); ?>><?php _e('Transferência Bancária', 'lab-resumos-parceiros'); ?></option>
                            </select>
                        </div>
                        
                        <div class="lrp-pix-fields" <?php echo $payment_data['method'] !== 'pix' ? 'style="display:none;"' : ''; ?>>
                            <div class="lrp-form-group">
                                <label><?php _e('Tipo de Chave PIX', 'lab-resumos-parceiros'); ?></label>
                                <select name="pix_key_type" class="lrp-input">
                                    <option value="cpf" <?php selected($payment_data['pix_key_type'], 'cpf'); ?>>CPF</option>
                                    <option value="cnpj" <?php selected($payment_data['pix_key_type'], 'cnpj'); ?>>CNPJ</option>
                                    <option value="email" <?php selected($payment_data['pix_key_type'], 'email'); ?>>Email</option>
                                    <option value="phone" <?php selected($payment_data['pix_key_type'], 'phone'); ?>><?php _e('Telefone', 'lab-resumos-parceiros'); ?></option>
                                    <option value="random" <?php selected($payment_data['pix_key_type'], 'random'); ?>><?php _e('Chave Aleatória', 'lab-resumos-parceiros'); ?></option>
                                </select>
                            </div>
                            
                            <div class="lrp-form-group">
                                <label><?php _e('Chave PIX', 'lab-resumos-parceiros'); ?></label>
                                <input type="text" name="pix_key" value="<?php echo esc_attr($payment_data['pix_key']); ?>" class="lrp-input">
                            </div>
                        </div>
                        
                        <div class="lrp-form-group">
                            <label><?php _e('Nome do Titular', 'lab-resumos-parceiros'); ?></label>
                            <input type="text" name="holder_name" value="<?php echo esc_attr($payment_data['holder_name']); ?>" class="lrp-input">
                        </div>
                        
                        <div class="lrp-form-group">
                            <label><?php _e('CPF/CNPJ do Titular', 'lab-resumos-parceiros'); ?></label>
                            <input type="text" name="holder_document" value="<?php echo esc_attr($payment_data['holder_document']); ?>" class="lrp-input">
                        </div>
                    </div>
                    
                    <button type="submit" class="lrp-btn lrp-btn-primary"><?php _e('Salvar Alterações', 'lab-resumos-parceiros'); ?></button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Obtém estatísticas com cache
     *
     * @return array
     */
    private function get_cached_stats() {
        $cache_key = 'lrp_affiliate_stats_' . $this->affiliate->get_id();
        $stats = get_transient($cache_key);
        
        if ($stats === false) {
            global $wpdb;
            
            // Vendas do mês
            $month_sales = LRP_Referral::count_this_month($this->affiliate->get_id());
            
            // Comissões do mês
            $month_commission = LRP_Referral::sum_commissions_this_month($this->affiliate->get_id());
            
            // Taxa de conversão
            $visit_stats = LRP_Cookie_Tracker::instance()->get_visit_stats($this->affiliate->get_id(), 'month');
            $conversion_rate = $visit_stats['conversion_rate'];
            
            // Pendente de pagamento
            $pending_payment = LRP_Commission::sum_by_status($this->affiliate->get_id(), 'approved');
            
            $stats = [
                'month_sales'      => $month_sales,
                'month_commission' => $month_commission,
                'conversion_rate'  => $conversion_rate,
                'pending_payment'  => $pending_payment,
            ];
            
            set_transient($cache_key, $stats, HOUR_IN_SECONDS);
        }
        
        return $stats;
    }

    /**
     * Retorna label de status do referral
     *
     * @param string $status
     * @return string
     */
    public static function get_status_label($status) {
        $labels = [
            'pending'  => __('Pendente', 'lab-resumos-parceiros'),
            'approved' => __('Aprovado', 'lab-resumos-parceiros'),
            'rejected' => __('Rejeitado', 'lab-resumos-parceiros'),
            'refunded' => __('Reembolsado', 'lab-resumos-parceiros'),
            'paid'     => __('Pago', 'lab-resumos-parceiros'),
        ];
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * Retorna label do tipo de atribuição
     *
     * @param string $type
     * @return string
     */
    private function get_attribution_type_label($type) {
        switch ($type) {
            case 'both':
                return '🔗🎫 ' . __('Link + Cupom', 'lab-resumos-parceiros');
            case 'coupon':
                return '🎫 ' . __('Cupom', 'lab-resumos-parceiros');
            case 'link':
            default:
                return '🔗 ' . __('Link', 'lab-resumos-parceiros');
        }
    }
    
    /**
     * Retorna label do tipo de atribuição (versão estática)
     *
     * @param string $type
     * @return string
     */
    public static function get_attribution_type_label_static($type) {
        switch ($type) {
            case 'both':
                return '🔗🎫 ' . __('Link + Cupom', 'lab-resumos-parceiros');
            case 'coupon':
                return '🎫 ' . __('Cupom', 'lab-resumos-parceiros');
            case 'link':
            default:
                return '🔗 ' . __('Link', 'lab-resumos-parceiros');
        }
    }

    /**
     * Retorna label de status do fechamento
     *
     * @param string $status
     * @return string
     */
    private function get_closing_status_label($status) {
        $labels = [
            'open'             => __('Aberto', 'lab-resumos-parceiros'),
            'closed'           => __('Acumulado', 'lab-resumos-parceiros'),
            'awaiting_invoice' => __('Aguardando NF', 'lab-resumos-parceiros'),
            'awaiting_rpa'     => __('Aguardando RPA', 'lab-resumos-parceiros'),
            'invoice_received' => __('NF Recebida', 'lab-resumos-parceiros'),
            'approved'         => __('Aprovado', 'lab-resumos-parceiros'),
            'rejected'         => __('NF Rejeitada', 'lab-resumos-parceiros'),
            'paid'             => __('Pago', 'lab-resumos-parceiros'),
        ];
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * Retorna ícone SVG de copiar
     *
     * @return string
     */
    private function get_copy_icon() {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
    }
    
    /**
     * Retorna ícone SVG de check
     *
     * @return string
     */
    private function get_check_icon() {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
    }
    
    /**
     * Retorna ícone SVG de info
     *
     * @return string
     */
    private function get_info_icon() {
        return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
    }
    
    /**
     * Retorna ícone SVG de chevron
     *
     * @return string
     */
    private function get_chevron_icon() {
        return '<svg class="lrp-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
    }
    
    /**
     * Retorna ícone SVG de clique
     *
     * @return string
     */
    private function get_click_icon() {
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>';
    }
}
