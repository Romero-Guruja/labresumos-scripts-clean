<?php
/**
 * Pagina "Matriculas": expoe a tela Manage Enrollment do Edwiser Bridge
 * (matricular/desmatricular aluno no Moodle) para o papel de suporte,
 * sem exigir manage_options.
 *
 * Contexto: o menu original do Edwiser gateia a tela com manage_options,
 * mas o render (out_put), a matricula (handle_new_enrollment) e a
 * desmatricula em massa validam apenas nonce. O unico ponto com capability
 * hardcoded e o AJAX de desmatricula individual
 * (wdm_eb_user_manage_unenroll_unenroll_user) - dai a ponte abaixo.
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Enrollment
 */
class LRA_Enrollment {

    /**
     * Slug da pagina.
     */
    const PAGE = 'lra-matriculas';

    /**
     * Registra os hooks.
     */
    public static function init() {
        // Prioridade 11: o menu pai (Acessos) e registrado pelo LRA_Admin na 10.
        add_action('admin_menu', [__CLASS__, 'register_menu'], 11);

        // Prioridade 5: roda ANTES do handler do Edwiser (10), que exige
        // manage_options. Admins retornam sem agir e seguem no fluxo original.
        add_action(
            'wp_ajax_wdm_eb_user_manage_unenroll_unenroll_user',
            [__CLASS__, 'unenroll_ajax_bridge'],
            5
        );
    }

    /**
     * Verifica se as classes do Edwiser Bridge estao disponiveis.
     *
     * @return bool
     */
    public static function edwiser_available() {
        return class_exists('\app\wisdmlabs\edwiserBridge\EdwiserBridge')
            && class_exists('\app\wisdmlabs\edwiserBridge\Eb_Manage_Enrollment')
            && class_exists('\app\wisdmlabs\edwiserBridge\Eb_Enrollment_Manager');
    }

    /**
     * Submenu "Matriculas" sob o menu "Acessos".
     */
    public static function register_menu() {
        add_submenu_page(
            LRA_Admin::PAGE,
            __('Matriculas Moodle', 'lab-resumos-acessos'),
            __('Matriculas', 'lab-resumos-acessos'),
            LRA_Roles::CAP_MANAGE_ENROLLMENT,
            self::PAGE,
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Renderiza a tela Manage Enrollment do Edwiser sob a nossa capability.
     */
    public static function render_page() {
        if (!current_user_can(LRA_Roles::CAP_MANAGE_ENROLLMENT)) {
            wp_die(esc_html__('Acesso negado.', 'lab-resumos-acessos'));
        }

        if (!self::edwiser_available()) {
            echo '<div class="wrap"><h1>' . esc_html__('Matriculas Moodle', 'lab-resumos-acessos') . '</h1>';
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Edwiser Bridge inativo ou indisponivel.', 'lab-resumos-acessos');
            echo '</p></div></div>';
            return;
        }

        $edwiser = \app\wisdmlabs\edwiserBridge\EdwiserBridge::instance();
        $manager = new \app\wisdmlabs\edwiserBridge\Eb_Manage_Enrollment(
            $edwiser->get_plugin_name(),
            $edwiser->get_version()
        );
        $manager->out_put();
    }

    /**
     * Ponte AJAX da desmatricula individual para o papel de suporte.
     *
     * Replica exatamente o unenroll do Edwiser (mesmos args, incluindo
     * complete_unenroll) trocando o gate manage_options pela nossa capability.
     */
    public static function unenroll_ajax_bridge() {
        if (current_user_can('manage_options')) {
            return; // Admin segue no handler original do Edwiser.
        }

        if (!current_user_can(LRA_Roles::CAP_MANAGE_ENROLLMENT)) {
            return; // Sem a capability, o handler do Edwiser nega.
        }

        if (!self::edwiser_available()) {
            wp_send_json_error(esc_html__('Edwiser Bridge indisponivel.', 'lab-resumos-acessos'));
        }

        if (!isset($_POST['admin_nonce'], $_POST['user_id'], $_POST['course_id'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['admin_nonce'])), 'eb_admin_nonce')) {
            wp_send_json_error(esc_html__('Requisicao invalida.', 'lab-resumos-acessos'));
        }

        $user_id   = absint($_POST['user_id']);
        $course_id = absint($_POST['course_id']);

        $edwiser = \app\wisdmlabs\edwiserBridge\EdwiserBridge::instance();
        $manager = new \app\wisdmlabs\edwiserBridge\Eb_Enrollment_Manager(
            $edwiser->get_plugin_name(),
            $edwiser->get_version()
        );

        $result = $manager->update_user_course_enrollment([
            'user_id'           => $user_id,
            'role_id'           => 5,
            'courses'           => [$course_id],
            'unenroll'          => 1,
            'suspend'           => 0,
            'complete_unenroll' => 1,
        ]);

        lra_log('Desmatricula individual via papel de suporte', [
            'user_id'   => $user_id,
            'course_id' => $course_id,
            'by'        => get_current_user_id(),
            'result'    => (bool) $result,
        ], $result ? 'info' : 'warning');

        if ($result) {
            $user        = get_userdata($user_id);
            $course_name = get_the_title($course_id);
            wp_send_json_success(sprintf(
                /* translators: 1: user login, 2: course name */
                esc_html__('%1$s foi desmatriculado(a) do curso %2$s.', 'lab-resumos-acessos'),
                $user ? ucfirst($user->user_login) : ('#' . $user_id),
                $course_name
            ));
        }

        wp_send_json_error(esc_html__('Falha ao desmatricular o usuario.', 'lab-resumos-acessos'));
    }
}
