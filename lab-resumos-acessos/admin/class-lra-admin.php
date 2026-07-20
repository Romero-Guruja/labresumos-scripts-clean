<?php
/**
 * Interface administrativa do plugin.
 *
 * Pagina "Acessos": formulario para liberar acesso de cortesia, lista das
 * concessoes recentes e fila de conflitos de identidade.
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Admin
 */
class LRA_Admin {

    /**
     * Instancia unica.
     *
     * @var LRA_Admin|null
     */
    private static $instance = null;

    /**
     * Slug da pagina.
     */
    const PAGE = 'lra-acessos';

    /**
     * Retorna instancia unica.
     *
     * @return LRA_Admin
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor.
     */
    private function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_lra_grant', [$this, 'handle_grant']);
        add_action('admin_post_lra_revoke', [$this, 'handle_revoke']);
        add_action('admin_post_lra_resolve_conflict', [$this, 'handle_resolve_conflict']);
    }

    /**
     * Registra o menu.
     */
    public function register_menu() {
        add_menu_page(
            __('Acessos Cortesia', 'lab-resumos-acessos'),
            __('Acessos', 'lab-resumos-acessos'),
            LRA_Roles::CAP_MANAGE_ACCESS,
            self::PAGE,
            [$this, 'render_page'],
            'dashicons-unlock',
            56
        );

        // O menu Marketing (que abriga os cupons) exige manage_woocommerce;
        // para o papel de suporte, expoe um atalho direto para os cupons.
        if (current_user_can('edit_shop_coupons') && !current_user_can('manage_woocommerce')) {
            add_menu_page(
                __('Cupons', 'lab-resumos-acessos'),
                __('Cupons', 'lab-resumos-acessos'),
                'edit_shop_coupons',
                'edit.php?post_type=shop_coupon',
                '',
                'dashicons-tickets-alt',
                57
            );
        }
    }

    /**
     * Renderiza a pagina.
     */
    public function render_page() {
        if (!LRA_Roles::user_can_manage_access()) {
            wp_die(esc_html__('Acesso negado.', 'lab-resumos-acessos'));
        }

        $products  = LRA_Catalog::get_mapped_products();
        $conflicts = LRA_Conflicts::get_open();
        $orders    = LRA_Access::list_courtesy_order_ids(30);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Acessos de Cortesia', 'lab-resumos-acessos'); ?></h1>

            <?php $this->render_notices(); ?>

            <h2><?php esc_html_e('Liberar acesso', 'lab-resumos-acessos'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="lra_grant">
                <?php wp_nonce_field('lra_grant'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="lra_nome"><?php esc_html_e('Nome', 'lab-resumos-acessos'); ?></label></th>
                        <td><input name="nome" id="lra_nome" type="text" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lra_email"><?php esc_html_e('Email', 'lab-resumos-acessos'); ?></label></th>
                        <td><input name="email" id="lra_email" type="email" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lra_cpf"><?php esc_html_e('CPF', 'lab-resumos-acessos'); ?></label></th>
                        <td><input name="cpf" id="lra_cpf" type="text" class="regular-text" placeholder="000.000.000-00" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lra_context"><?php esc_html_e('Contexto', 'lab-resumos-acessos'); ?></label></th>
                        <td>
                            <select name="context" id="lra_context">
                                <option value="manual"><?php esc_html_e('Manual', 'lab-resumos-acessos'); ?></option>
                                <option value="affiliate_courtesy"><?php esc_html_e('Cortesia de afiliado', 'lab-resumos-acessos'); ?></option>
                                <option value="prize"><?php esc_html_e('Premio/Bonificacao', 'lab-resumos-acessos'); ?></option>
                                <option value="support"><?php esc_html_e('Suporte', 'lab-resumos-acessos'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Cursos (produtos)', 'lab-resumos-acessos'); ?></th>
                        <td>
                            <?php if (empty($products)): ?>
                                <p><?php esc_html_e('Nenhum produto mapeado a curso encontrado.', 'lab-resumos-acessos'); ?></p>
                            <?php else: ?>
                                <fieldset style="max-height:260px;overflow:auto;border:1px solid #ccd0d4;padding:10px;">
                                    <?php foreach ($products as $p): ?>
                                        <label style="display:block;margin-bottom:4px;">
                                            <input type="checkbox" name="product_ids[]" value="<?php echo esc_attr($p['product_id']); ?>">
                                            <?php echo esc_html($p['name']); ?>
                                            <small style="color:#666;">
                                                (#<?php echo esc_html($p['product_id']); ?> &rarr;
                                                <?php
                                                $course_ids = wp_list_pluck($p['courses'], 'moodle_course_id');
                                                echo esc_html(implode(', ', array_map(fn($c) => 'curso ' . $c, $course_ids)));
                                                ?>)
                                            </small>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Liberar acesso', 'lab-resumos-acessos')); ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Conflitos de identidade (revisao manual)', 'lab-resumos-acessos'); ?></h2>
            <?php if (empty($conflicts)): ?>
                <p><?php esc_html_e('Nenhum conflito aberto.', 'lab-resumos-acessos'); ?></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Data', 'lab-resumos-acessos'); ?></th>
                            <th><?php esc_html_e('Email', 'lab-resumos-acessos'); ?></th>
                            <th><?php esc_html_e('CPF informado', 'lab-resumos-acessos'); ?></th>
                            <th><?php esc_html_e('CPF existente', 'lab-resumos-acessos'); ?></th>
                            <th><?php esc_html_e('Usuario', 'lab-resumos-acessos'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conflicts as $c): ?>
                            <tr>
                                <td><?php echo esc_html($c->created_at); ?></td>
                                <td><?php echo esc_html($c->email); ?></td>
                                <td><?php echo esc_html($this->mask_cpf($c->cpf_informado)); ?></td>
                                <td><?php echo esc_html($this->mask_cpf($c->cpf_existente)); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_user_link($c->user_id)); ?>">#<?php echo esc_html($c->user_id); ?></a>
                                </td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                        <input type="hidden" name="action" value="lra_resolve_conflict">
                                        <input type="hidden" name="conflict_id" value="<?php echo esc_attr($c->id); ?>">
                                        <?php wp_nonce_field('lra_resolve_conflict_' . $c->id); ?>
                                        <button type="submit" class="button button-small"><?php esc_html_e('Marcar como resolvido', 'lab-resumos-acessos'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <hr>

            <h2><?php esc_html_e('Concessoes recentes', 'lab-resumos-acessos'); ?></h2>
            <?php if (empty($orders)): ?>
                <p><?php esc_html_e('Nenhuma concessao registrada.', 'lab-resumos-acessos'); ?></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Pedido', 'lab-resumos-acessos'); ?></th>
                            <th><?php esc_html_e('Data', 'lab-resumos-acessos'); ?></th>
                            <th><?php esc_html_e('Cliente', 'lab-resumos-acessos'); ?></th>
                            <th><?php esc_html_e('Contexto', 'lab-resumos-acessos'); ?></th>
                            <th><?php esc_html_e('Status', 'lab-resumos-acessos'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $oid):
                            $order = wc_get_order($oid);
                            if (!$order) {
                                continue;
                            }
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($order->get_edit_order_url()); ?>">#<?php echo esc_html($oid); ?></a>
                                </td>
                                <td><?php echo esc_html($order->get_date_created() ? $order->get_date_created()->date('d/m/Y H:i') : ''); ?></td>
                                <td><?php echo esc_html($order->get_billing_email()); ?></td>
                                <td><?php echo esc_html($order->get_meta('_lra_context')); ?></td>
                                <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                                <td>
                                    <?php if ($order->get_status() !== 'cancelled'): ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;"
                                              onsubmit="return confirm('<?php echo esc_js(__('Revogar este acesso?', 'lab-resumos-acessos')); ?>');">
                                            <input type="hidden" name="action" value="lra_revoke">
                                            <input type="hidden" name="order_id" value="<?php echo esc_attr($oid); ?>">
                                            <?php wp_nonce_field('lra_revoke_' . $oid); ?>
                                            <button type="submit" class="button button-small"><?php esc_html_e('Revogar', 'lab-resumos-acessos'); ?></button>
                                        </form>
                                    <?php else: ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Processa o formulario de concessao.
     */
    public function handle_grant() {
        if (!LRA_Roles::user_can_manage_access()) {
            wp_die(esc_html__('Acesso negado.', 'lab-resumos-acessos'));
        }
        check_admin_referer('lra_grant');

        $result = LRA_Access::grant([
            'email'       => wp_unslash($_POST['email'] ?? ''),
            'cpf'         => wp_unslash($_POST['cpf'] ?? ''),
            'nome'        => sanitize_text_field(wp_unslash($_POST['nome'] ?? '')),
            'product_ids' => array_map('absint', (array) ($_POST['product_ids'] ?? [])),
            'context'     => sanitize_key($_POST['context'] ?? 'manual'),
        ]);

        if (is_wp_error($result)) {
            $this->redirect_with_notice('error', $result->get_error_message());
        }

        $msg = sprintf(
            /* translators: 1: order id, 2: user id */
            __('Acesso liberado. Pedido #%1$d para o usuario #%2$d.', 'lab-resumos-acessos'),
            $result['order_id'],
            $result['user_id']
        );

        if ($result['created_user']) {
            $msg .= ' ' . __('Usuario novo criado.', 'lab-resumos-acessos');
        }
        if (!empty($result['magic_login_url'])) {
            $msg .= ' ' . sprintf(
                /* translators: %s: magic login url */
                __('Link de acesso: %s', 'lab-resumos-acessos'),
                $result['magic_login_url']
            );
        }

        $this->redirect_with_notice('success', $msg);
    }

    /**
     * Processa revogacao.
     */
    public function handle_revoke() {
        if (!LRA_Roles::user_can_manage_access()) {
            wp_die(esc_html__('Acesso negado.', 'lab-resumos-acessos'));
        }
        $order_id = absint($_POST['order_id'] ?? 0);
        check_admin_referer('lra_revoke_' . $order_id);

        $result = LRA_Access::revoke($order_id);
        if (is_wp_error($result)) {
            $this->redirect_with_notice('error', $result->get_error_message());
        }

        $this->redirect_with_notice('success', sprintf(
            /* translators: %d: order id */
            __('Acesso do pedido #%d revogado.', 'lab-resumos-acessos'),
            $order_id
        ));
    }

    /**
     * Processa resolucao de conflito.
     */
    public function handle_resolve_conflict() {
        if (!LRA_Roles::user_can_manage_access()) {
            wp_die(esc_html__('Acesso negado.', 'lab-resumos-acessos'));
        }
        $id = absint($_POST['conflict_id'] ?? 0);
        check_admin_referer('lra_resolve_conflict_' . $id);

        LRA_Conflicts::resolve($id);
        $this->redirect_with_notice('success', __('Conflito marcado como resolvido.', 'lab-resumos-acessos'));
    }

    /**
     * Redireciona de volta a pagina com uma mensagem.
     *
     * @param string $type
     * @param string $message
     */
    private function redirect_with_notice($type, $message) {
        set_transient('lra_notice_' . get_current_user_id(), ['type' => $type, 'message' => $message], 60);
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE));
        exit;
    }

    /**
     * Exibe a mensagem armazenada.
     */
    private function render_notices() {
        $key    = 'lra_notice_' . get_current_user_id();
        $notice = get_transient($key);
        if (!$notice) {
            return;
        }
        delete_transient($key);

        $class = $notice['type'] === 'error' ? 'notice-error' : 'notice-success';
        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr($class),
            esc_html($notice['message'])
        );
    }

    /**
     * Mascara CPF para exibicao.
     *
     * @param string $cpf
     * @return string
     */
    private function mask_cpf($cpf) {
        $cpf = preg_replace('/\D/', '', (string) $cpf);
        if (strlen($cpf) !== 11) {
            return '***';
        }
        return '***.' . substr($cpf, 3, 3) . '.***-' . substr($cpf, -2);
    }
}
