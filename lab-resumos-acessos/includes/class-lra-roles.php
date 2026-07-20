<?php
/**
 * Papel customizado "Suporte Lab" e capability do plugin.
 *
 * Cria um papel com permissoes granulares para a equipe de suporte:
 * pedidos (ver/editar/criar), cupons (ver/editar/criar) e a pagina
 * "Acessos" deste plugin - sem acesso a configuracoes do WooCommerce,
 * produtos, plugins ou demais areas do WordPress.
 *
 * A sincronizacao roda no init com controle de versao em option, pois o
 * plugin ja esta ativo em producao e o activation hook nao redispara.
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Roles
 */
class LRA_Roles {

    /**
     * Slug do papel de suporte.
     */
    const ROLE = 'lra_suporte';

    /**
     * Capability que libera a pagina "Acessos".
     */
    const CAP_MANAGE_ACCESS = 'lra_manage_access';

    /**
     * Capability que libera a pagina "Matriculas" (tela do Edwiser).
     */
    const CAP_MANAGE_ENROLLMENT = 'lra_manage_enrollment';

    /**
     * Versao do conjunto de capabilities. Incrementar ao alterar caps.
     */
    const ROLES_VERSION = '3';

    /**
     * Option que guarda a versao sincronizada.
     */
    const OPTION_VERSION = 'lra_roles_version';

    /**
     * Registra os hooks de sincronizacao e do perfil de usuario.
     */
    public static function init() {
        add_action('init', [__CLASS__, 'maybe_sync_roles']);

        // Checkbox no perfil para acumular o papel de suporte com o papel
        // principal do usuario (a tela de Usuarios so permite um papel).
        add_action('show_user_profile', [__CLASS__, 'render_profile_field']);
        add_action('edit_user_profile', [__CLASS__, 'render_profile_field']);

        // profile_update (e nao edit_user_profile_update): o WordPress aplica
        // o campo "Funcao" via set_role() DEPOIS dos hooks de update do perfil,
        // substituindo todos os papeis - o que apagaria o papel recem-adicionado.
        add_action('profile_update', [__CLASS__, 'save_profile_field'], 999);

        // Corrige o mapeamento de capability do post type placeholder do HPOS
        // (shop_order_placehold). Sem isso, a tela de edicao de um pedido
        // individual (current_user_can('edit_shop_order', $id)) resolve contra
        // capabilities genericas de post (edit_others_posts) em vez de
        // edit_others_shop_orders, negando acesso a quem tem apenas as
        // capabilities de pedido do WooCommerce. Precisa rodar antes do 'init'
        // do core, que e quando o post type e registrado.
        add_filter('register_post_type_args', [__CLASS__, 'fix_hpos_placeholder_caps'], 10, 2);
    }

    /**
     * Ajusta o registro do post type placeholder do HPOS para usar mapeamento
     * de capabilities do tipo "shop_order" em vez do padrao generico de post.
     *
     * @param array  $args
     * @param string $post_type
     * @return array
     */
    public static function fix_hpos_placeholder_caps($args, $post_type) {
        if ('shop_order_placehold' === $post_type) {
            $args['capability_type'] = 'shop_order';
            $args['map_meta_cap']    = true;
        }
        return $args;
    }

    /**
     * Cria/atualiza o papel quando a versao armazenada esta defasada.
     */
    public static function maybe_sync_roles() {
        if (get_option(self::OPTION_VERSION) === self::ROLES_VERSION) {
            return;
        }

        self::create_roles();
        update_option(self::OPTION_VERSION, self::ROLES_VERSION);
        lra_log('Papel de suporte sincronizado.', ['version' => self::ROLES_VERSION]);
    }

    /**
     * Cria o papel de suporte e garante a capability no administrador.
     */
    public static function create_roles() {
        // Remove antes de recriar para que atualizacoes de caps tenham efeito.
        remove_role(self::ROLE);

        add_role(
            self::ROLE,
            __('Suporte Lab', 'lab-resumos-acessos'),
            array_fill_keys(self::support_caps(), true)
        );

        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap(self::CAP_MANAGE_ACCESS);
            $admin->add_cap(self::CAP_MANAGE_ENROLLMENT);
        }
    }

    /**
     * Lista de capabilities do papel de suporte.
     *
     * Pedidos e cupons: ver, editar e criar - sem excluir. Nao inclui
     * manage_woocommerce (configuracoes da loja) nem edit_posts (blog).
     * view_admin_dashboard evita o redirect do WooCommerce para fora
     * do wp-admin em usuarios sem edit_posts/manage_woocommerce.
     *
     * @return string[]
     */
    private static function support_caps() {
        return [
            // Gerais.
            'read',
            'view_admin_dashboard',

            // Pedidos (WooCommerce 10.3+ libera a tela HPOS com edit_shop_orders).
            'edit_shop_orders',
            'edit_others_shop_orders',
            'edit_private_shop_orders',
            'edit_published_shop_orders',
            'publish_shop_orders',
            'read_private_shop_orders',

            // Cupons.
            'edit_shop_coupons',
            'edit_others_shop_coupons',
            'edit_private_shop_coupons',
            'edit_published_shop_coupons',
            'publish_shop_coupons',
            'read_private_shop_coupons',

            // Pagina "Acessos" deste plugin.
            self::CAP_MANAGE_ACCESS,

            // Pagina "Matriculas" (matricular/desmatricular aluno no Moodle
            // pela tela do Edwiser, sem manage_options).
            self::CAP_MANAGE_ENROLLMENT,
        ];
    }

    /**
     * Indica se o usuario atual pode gerenciar acessos de cortesia.
     *
     * @return bool
     */
    public static function user_can_manage_access() {
        return current_user_can('manage_woocommerce') || current_user_can(self::CAP_MANAGE_ACCESS);
    }

    /**
     * Exibe o checkbox "Suporte Lab" na tela de edicao de usuario.
     *
     * @param WP_User $user
     */
    public static function render_profile_field($user) {
        if (!current_user_can('edit_users')) {
            return;
        }
        $has_role = in_array(self::ROLE, (array) $user->roles, true);
        wp_nonce_field('lra_support_role_' . $user->ID, 'lra_support_role_nonce');
        ?>
        <h2><?php esc_html_e('Suporte Lab', 'lab-resumos-acessos'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Funcoes de suporte', 'lab-resumos-acessos'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="lra_support_role" value="1" <?php checked($has_role); ?>>
                        <?php esc_html_e('Adicionar o papel "Suporte Lab" (Pedidos, Cupons e Acessos), acumulando com o papel atual.', 'lab-resumos-acessos'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Salva o checkbox do perfil, adicionando/removendo o papel de suporte
     * sem tocar nos demais papeis do usuario.
     *
     * @param int $user_id
     */
    public static function save_profile_field($user_id) {
        if (!current_user_can('edit_users')) {
            return;
        }
        if (!isset($_POST['lra_support_role_nonce'])
            || !wp_verify_nonce($_POST['lra_support_role_nonce'], 'lra_support_role_' . $user_id)) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $wants_role = !empty($_POST['lra_support_role']);
        $has_role   = in_array(self::ROLE, (array) $user->roles, true);

        if ($wants_role && !$has_role) {
            $user->add_role(self::ROLE);
            lra_log('Papel de suporte adicionado ao usuario.', ['user_id' => $user_id]);
        } elseif (!$wants_role && $has_role) {
            $user->remove_role(self::ROLE);
            lra_log('Papel de suporte removido do usuario.', ['user_id' => $user_id]);
        }
    }
}
