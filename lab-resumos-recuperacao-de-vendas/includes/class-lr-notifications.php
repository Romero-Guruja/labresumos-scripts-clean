<?php
/**
 * Classe de Notificações
 *
 * @package LR_Recuperacao_Vendas
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LR_Notifications
 * Gerencia notificações por email e admin
 */
class LR_Notifications {

    /**
     * Construtor
     */
    public function __construct() {
        // Hook para enviar email quando caso é criado
        add_action('lr_recovery_case_created', [$this, 'send_new_case_email'], 10, 2);

        // Badge no menu admin
        add_filter('admin_menu', [$this, 'update_menu_badge'], 999);

        // Admin bar notification
        add_action('admin_bar_menu', [$this, 'add_admin_bar_notification'], 100);
    }

    /**
     * Envia email de notificação para novo caso
     * @param int $case_id
     * @param int $order_id
     */
    public function send_new_case_email($case_id, $order_id) {
        // Verificar se email está habilitado
        if (get_option('lr_recovery_email_enabled', 'yes') !== 'yes') {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $manager = lr_recovery()->manager;
        $failure_info = $manager->extract_failure_reason($order_id);

        // Destinatários
        $recipients = get_option('lr_recovery_email_recipients', get_option('admin_email'));
        $recipients = apply_filters('lr_recovery_email_recipients', $recipients, $order);

        // Assunto
        $subject = sprintf(
            /* translators: %d: order ID */
            __('[Lab Resumos] 🔴 Pedido #%d falhou - Recuperação necessária', 'lr-recuperacao-vendas'),
            $order_id
        );

        // Dados do pedido
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = $item->get_name() . ' (x' . $item->get_quantity() . ')';
        }

        // Corpo do email
        $message = $this->get_email_template([
            'order_id' => $order_id,
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_meta('billing_cellphone') ?: $order->get_billing_phone(),
            'order_total' => $order->get_formatted_order_total(),
            'items' => implode("\n", $items),
            'failure_message' => $failure_info['message'],
            'failure_type' => LR_Admin_Dashboard::get_failure_type_label($failure_info['type']),
            'charge_id' => $failure_info['charge_id'],
            'dashboard_url' => admin_url('admin.php?page=lr-recuperacao-vendas&case=' . $order_id),
        ]);

        // Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Lab Resumos <' . get_option('admin_email') . '>',
        ];

        // Enviar
        wp_mail($recipients, $subject, $message, $headers);
    }

    /**
     * Retorna template do email
     * @param array $data
     * @return string
     */
    private function get_email_template($data) {
        ob_start();
        
        // Se existir template customizado, usar
        $template_path = LR_RECOVERY_PLUGIN_DIR . 'templates/email-notification.php';
        if (file_exists($template_path)) {
            extract($data);
            include $template_path;
        } else {
            // Template padrão inline
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #F1CC00; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .header h1 { margin: 0; color: #333B49; font-size: 24px; }
                    .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                    .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #dc3545; }
                    .info-box.warning { border-left-color: #ffc107; }
                    .btn { display: inline-block; padding: 12px 24px; background: #2A6B9F; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px; }
                    .footer { padding: 15px; text-align: center; color: #666; font-size: 12px; }
                    .label { font-weight: 600; color: #555; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>🔴 Pedido com Falha de Pagamento</h1>
                    </div>
                    <div class="content">
                        <p>Um novo pedido falhou e requer atenção:</p>
                        
                        <div class="info-box">
                            <p><span class="label">Pedido:</span> #<?php echo esc_html($data['order_id']); ?></p>
                            <p><span class="label">Cliente:</span> <?php echo esc_html($data['customer_name']); ?></p>
                            <p><span class="label">Email:</span> <?php echo esc_html($data['customer_email']); ?></p>
                            <p><span class="label">Telefone:</span> <?php echo esc_html($data['customer_phone']); ?></p>
                            <p><span class="label">Valor:</span> <?php echo wp_kses_post($data['order_total']); ?></p>
                        </div>

                        <div class="info-box warning">
                            <p><span class="label">Tipo de Falha:</span> <?php echo esc_html($data['failure_type']); ?></p>
                            <?php if (!empty($data['failure_message'])): ?>
                                <p><span class="label">Mensagem:</span> <?php echo esc_html($data['failure_message']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($data['charge_id'])): ?>
                                <p><span class="label">Charge ID:</span> <?php echo esc_html($data['charge_id']); ?></p>
                            <?php endif; ?>
                        </div>

                        <p><strong>Produtos:</strong></p>
                        <pre style="background: white; padding: 10px; border-radius: 4px;"><?php echo esc_html($data['items']); ?></pre>

                        <p style="text-align: center;">
                            <a href="<?php echo esc_url($data['dashboard_url']); ?>" class="btn">
                                Ver Caso no Painel de Recuperação
                            </a>
                        </p>
                    </div>
                    <div class="footer">
                        <p>Este email foi enviado automaticamente pelo plugin Lab Resumos - Recuperação de Vendas.</p>
                    </div>
                </div>
            </body>
            </html>
            <?php
        }
        
        return ob_get_clean();
    }

    /**
     * Atualiza badge no menu
     */
    public function update_menu_badge() {
        global $menu, $submenu;

        $pending_count = lr_recovery()->manager->count_pending_cases();

        if ($pending_count > 0 && isset($submenu['woocommerce'])) {
            foreach ($submenu['woocommerce'] as $key => $item) {
                if (isset($item[2]) && $item[2] === 'lr-recuperacao-vendas') {
                    // Badge já adicionado no add_menu
                    break;
                }
            }
        }
    }

    /**
     * Adiciona notificação na admin bar
     * @param WP_Admin_Bar $admin_bar
     */
    public function add_admin_bar_notification($admin_bar) {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $pending_count = lr_recovery()->manager->count_pending_cases();

        if ($pending_count > 0) {
            $admin_bar->add_node([
                'id' => 'lr-recovery-notification',
                'title' => sprintf(
                    '<span class="ab-icon dashicons dashicons-backup" style="font-family: dashicons; font-size: 20px; top: 2px;"></span><span class="ab-label" style="background: #dc3545; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-left: 5px;">%d</span>',
                    $pending_count
                ),
                'href' => admin_url('admin.php?page=lr-recuperacao-vendas'),
                'meta' => [
                    'title' => sprintf(
                        /* translators: %d: number of pending cases */
                        _n('%d caso de recuperação pendente', '%d casos de recuperação pendentes', $pending_count, 'lr-recuperacao-vendas'),
                        $pending_count
                    ),
                ],
            ]);
        }
    }

    /**
     * Envia email de caso resolvido
     * @param int $case_id
     * @param int $order_id
     * @param string $resolution_type
     */
    public function send_resolved_email($case_id, $order_id, $resolution_type) {
        // Implementação futura se necessário
    }
}
