<?php
/**
 * WPCode snippet #1011 — Reenviar emails NATIVOS do Edwiser Bridge
 * location: admin_only | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */

/**
 * Reenviar emails NATIVOS do Edwiser Bridge
 * v2 - Inclui usuários WooCommerce sem vínculo Moodle
 */

if (!is_admin()) {
    return;
}

add_action('admin_menu', 'lab_eb_resend_native_menu', 99);

function lab_eb_resend_native_menu() {
    add_submenu_page(
        'edit.php?post_type=eb_course',
        'Reenviar Emails',
        '📧 Reenviar Emails',
        'manage_options',
        'lab-eb-resend',
        'lab_eb_resend_native_page'
    );
}

function lab_eb_resend_native_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado.');
    }
    
    $message = '';
    
    // Processa reset de senha
    if (isset($_POST['lab_reset_password']) && wp_verify_nonce($_POST['_wpnonce'], 'lab_eb_resend')) {
        $user_id = intval($_POST['reset_user_id']);
        $user = get_userdata($user_id);
        
        if ($user) {
            $result = retrieve_password($user->user_login);
            if ($result === true) {
                $message = '<div class="notice notice-success"><p>✅ Email de redefinição de senha enviado para: <strong>' . esc_html($user->user_email) . '</strong></p></div>';
            } else {
                $message = '<div class="notice notice-error"><p>❌ Erro: ' . esc_html($result->get_error_message()) . '</p></div>';
            }
        }
    }
    
    // Processa reenvio de email EB
    if (isset($_POST['lab_resend_action']) && wp_verify_nonce($_POST['_wpnonce'], 'lab_eb_resend')) {
        $user_id = intval($_POST['user_id']);
        $email_type = sanitize_text_field($_POST['email_type']);
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        
        $result = lab_trigger_eb_native_email($user_id, $email_type, $course_id);
        
        if ($result['success']) {
            $message = '<div class="notice notice-success"><p>✅ ' . esc_html($result['message']) . '</p></div>';
        } else {
            $message = '<div class="notice notice-error"><p>❌ ' . esc_html($result['message']) . '</p></div>';
        }
    }
    
    // Busca usuários: vinculados ao Moodle OU com pedidos WooCommerce
    $moodle_users = get_users(array(
        'meta_query' => array(
            array('key' => 'moodle_user_id', 'compare' => 'EXISTS')
        ),
        'fields' => 'ID'
    ));
    
    // Usuários com pedidos WooCommerce recentes
    global $wpdb;
    $woo_user_ids = $wpdb->get_col("
        SELECT DISTINCT pm.meta_value 
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = '_customer_user' 
        AND p.post_type IN ('shop_order', 'wc_order')
        AND pm.meta_value > 0
        ORDER BY p.post_date DESC
        LIMIT 200
    ");
    
    // Combina e remove duplicatas
    $all_user_ids = array_unique(array_merge($moodle_users, array_map('intval', $woo_user_ids)));
    
    // Busca dados dos usuários
    $users = get_users(array(
        'include' => $all_user_ids,
        'orderby' => 'registered',
        'order' => 'DESC',
        'number' => 200
    ));
    
    ?>
    <div class="wrap">
        <h1>📧 Reenviar Emails - Edwiser Bridge</h1>
        <p>Reenvia os emails usando os templates configurados em <strong>Edwiser Bridge → Gerenciar modelos de e-mail</strong>.</p>
        
        <?php echo $message; ?>
        
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <!-- Formulário principal -->
            <div style="flex: 1; min-width: 400px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2 style="margin-top: 0;">Reenviar Email do Edwiser Bridge</h2>
                
                <form method="post">
                    <?php wp_nonce_field('lab_eb_resend'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th>Usuário</th>
                            <td>
                                <select name="user_id" id="user_id" required style="width: 100%;">
                                    <option value="">Selecione um usuário...</option>
                                    <?php foreach ($users as $user) : 
                                        $has_moodle = get_user_meta($user->ID, 'moodle_user_id', true);
                                        $badge = $has_moodle ? '🟢' : '🟡';
                                    ?>
                                        <option value="<?php echo $user->ID; ?>">
                                            <?php echo $badge . ' ' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">🟢 Vinculado ao Moodle | 🟡 Apenas WooCommerce</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th>Tipo de Email</th>
                            <td>
                                <select name="email_type" id="email_type" required style="width: 100%;">
                                    <option value="new_user">🆕 Novo Usuário (credenciais)</option>
                                    <option value="existing_user_linked">🔗 Usuário Vinculado ao Moodle</option>
                                    <option value="order_complete">✅ Pedido Completo (matrícula)</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr id="course_row" style="display:none;">
                            <th>Curso</th>
                            <td>
                                <select name="course_id" id="course_id" style="width: 100%;">
                                    <option value="0">Selecione um curso...</option>
                                    <?php
                                    $courses = get_posts(array(
                                        'post_type' => 'eb_course',
                                        'posts_per_page' => -1,
                                        'post_status' => 'publish',
                                        'orderby' => 'title',
                                        'order' => 'ASC'
                                    ));
                                    foreach ($courses as $course) {
                                        echo '<option value="' . $course->ID . '">' . esc_html($course->post_title) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" name="lab_resend_action" class="button button-primary button-large">
                            📤 Reenviar Email
                        </button>
                    </p>
                    
                    <div class="notice notice-warning inline" style="margin: 15px 0 0 0; padding: 10px;">
                        <strong>⚠️ Nota:</strong> O email "Novo Usuário" não incluirá a senha original. 
                        Use o box ao lado para enviar um link de redefinição de senha.
                    </div>
                </form>
            </div>
            
            <!-- Box de Reset de Senha -->
            <div style="flex: 0 0 350px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2 style="margin-top: 0;">🔑 Redefinir Senha</h2>
                <p>Envia email com link para criar nova senha.</p>
                
                <form method="post">
                    <?php wp_nonce_field('lab_eb_resend'); ?>
                    
                    <p>
                        <label><strong>Usuário:</strong></label>
                        <select name="reset_user_id" id="reset_user_id" required style="width: 100%; margin-top: 5px;">
                            <option value="">Selecione...</option>
                            <?php foreach ($users as $user) : ?>
                                <option value="<?php echo $user->ID; ?>">
                                    <?php echo esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    
                    <p>
                        <button type="submit" name="lab_reset_password" class="button button-secondary" style="width: 100%;">
                            🔑 Enviar Link de Redefinição
                        </button>
                    </p>
                </form>
                
                <hr>
                <p style="margin-bottom: 0; font-size: 12px;">
                    <strong>Link público:</strong><br>
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" target="_blank">
                        <?php echo esc_html(wp_lostpassword_url()); ?>
                    </a>
                </p>
            </div>
        </div>
        
        <hr style="margin: 30px 0;">
        
        <h2>Últimos 20 usuários (Moodle + WooCommerce)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px;">Status</th>
                    <th style="width:160px;">Usuário</th>
                    <th style="width:220px;">Email</th>
                    <th>Cursos / Pedidos</th>
                    <th style="width:100px;">Registrado</th>
                    <th style="width:260px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach (array_slice($users, 0, 20) as $user) {
                    $moodle_id = get_user_meta($user->ID, 'moodle_user_id', true);
                    $enrolled = get_user_meta($user->ID, 'eb_user_enrolled_courses', true);
                    $first_course_id = 0;
                    $info = array();
                    
                    // Cursos EB
                    if (!empty($enrolled)) {
                        foreach ((array)$enrolled as $cid) {
                            $c = get_post($cid);
                            if ($c) {
                                $info[] = '📚 ' . $c->post_title;
                                if (!$first_course_id) $first_course_id = $cid;
                            }
                        }
                    }
                    
                    // Pedidos WooCommerce (últimos 3)
                    $orders = wc_get_orders(array(
                        'customer_id' => $user->ID,
                        'limit' => 3,
                        'orderby' => 'date',
                        'order' => 'DESC'
                    ));
                    
                    foreach ($orders as $order) {
                        $items = array();
                        foreach ($order->get_items() as $item) {
                            $items[] = $item->get_name();
                        }
                        $info[] = '🛒 #' . $order->get_id() . ': ' . implode(', ', $items);
                    }
                    
                    $status_icon = $moodle_id ? '🟢' : '🟡';
                    $status_title = $moodle_id ? 'Vinculado ao Moodle (ID: ' . $moodle_id . ')' : 'Não vinculado ao Moodle';
                    ?>
                    <tr>
                        <td title="<?php echo esc_attr($status_title); ?>" style="text-align:center; font-size: 18px;">
                            <?php echo $status_icon; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($user->display_name); ?></strong>
                            <div class="row-actions">
                                <a href="<?php echo get_edit_user_link($user->ID); ?>">Editar</a>
                            </div>
                        </td>
                        <td><code style="font-size: 11px;"><?php echo esc_html($user->user_email); ?></code></td>
                        <td style="font-size: 12px;">
                            <?php echo !empty($info) ? esc_html(implode(' | ', array_slice($info, 0, 2))) : '—'; ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($user->user_registered)); ?></td>
                        <td>
                            <?php if ($moodle_id) : ?>
                                <button type="button" class="button button-small quick-action" 
                                        data-action="new_user"
                                        data-user-id="<?php echo $user->ID; ?>"
                                        data-user-name="<?php echo esc_attr($user->display_name); ?>">
                                    📧 Credenciais
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" class="button button-small quick-action" 
                                    data-action="reset_password"
                                    data-user-id="<?php echo $user->ID; ?>"
                                    data-user-name="<?php echo esc_attr($user->display_name); ?>">
                                🔑 Senha
                            </button>
                            
                            <?php if ($first_course_id) : ?>
                                <button type="button" class="button button-small quick-action" 
                                        data-action="order_complete"
                                        data-user-id="<?php echo $user->ID; ?>"
                                        data-user-name="<?php echo esc_attr($user->display_name); ?>"
                                        data-course-id="<?php echo $first_course_id; ?>">
                                    📚 Matrícula
                                </button>
                            <?php endif; ?>
                            
                            <?php if (!$moodle_id) : ?>
                                <span style="color: #999; font-size: 11px;" title="Usuário não vinculado ao Moodle">
                                    ⚠️ Sem Moodle
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                }
                
                if (empty($users)) {
                    echo '<tr><td colspan="6">Nenhum usuário encontrado.</td></tr>';
                }
                ?>
            </tbody>
        </table>
        
        <p style="margin-top: 15px; color: #666;">
            <strong>Legenda:</strong> 
            🟢 Vinculado ao Moodle | 
            🟡 Apenas WooCommerce (não sincronizado) |
            📚 Curso matriculado |
            🛒 Pedido WooCommerce
        </p>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#email_type').on('change', function() {
            $('#course_row').toggle($(this).val() === 'order_complete');
        });
        
        $('.quick-action').on('click', function() {
            var action = $(this).data('action');
            var userId = $(this).data('user-id');
            var userName = $(this).data('user-name');
            var courseId = $(this).data('course-id') || 0;
            
            var labels = {
                'new_user': 'email de credenciais',
                'reset_password': 'link de redefinição de senha',
                'order_complete': 'email de matrícula'
            };
            
            if (confirm('Enviar ' + labels[action] + ' para "' + userName + '"?')) {
                if (action === 'reset_password') {
                    $('#reset_user_id').val(userId);
                    $('button[name="lab_reset_password"]').closest('form').submit();
                } else {
                    $('#user_id').val(userId);
                    $('#email_type').val(action).trigger('change');
                    if (courseId) $('#course_id').val(courseId);
                    $('button[name="lab_resend_action"]').closest('form').submit();
                }
            }
        });
    });
    </script>
    
    <style>.quick-action { margin: 2px !important; }</style>
    <?php
}

function lab_trigger_eb_native_email($user_id, $email_type, $course_id = 0) {
    $user = get_userdata($user_id);
    
    if (!$user) {
        return array('success' => false, 'message' => 'Usuário não encontrado.');
    }
    
    $moodle_id = get_user_meta($user_id, 'moodle_user_id', true);
    
    // Verifica se precisa de vínculo Moodle
    if (in_array($email_type, array('new_user', 'existing_user_linked')) && !$moodle_id) {
        return array(
            'success' => false, 
            'message' => 'Este usuário não está vinculado ao Moodle. Sincronize primeiro em Edwiser Bridge → Configurações → Sincronização.'
        );
    }
    
    $user_args = array(
        'user_email' => $user->user_email,
        'username'   => $user->user_login,
        'first_name' => $user->first_name ?: $user->display_name,
        'last_name'  => $user->last_name ?: '',
        'password'   => '********',
    );
    
    switch ($email_type) {
        case 'new_user':
            do_action('eb_created_user', $user_args);
            return array('success' => true, 'message' => 'Email "Novo Usuário" enviado para: ' . $user->user_email);
            
        case 'existing_user_linked':
            do_action('eb_linked_to_existing_wordpress_user', $user_args);
            return array('success' => true, 'message' => 'Email "Usuário Vinculado" enviado para: ' . $user->user_email);
            
        case 'order_complete':
            if (!$course_id) {
                return array('success' => false, 'message' => 'Selecione um curso.');
            }
            
            global $wpdb;
            $order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                 WHERE p.post_type = 'eb_order' AND pm.meta_key = 'eb_order_options'
                 AND pm.meta_value LIKE %s ORDER BY p.ID DESC LIMIT 1",
                '%"buyer_id";i:' . $user_id . '%'
            ));
            
            if ($order_id) {
                do_action('eb_order_status_completed', $order_id);
                return array('success' => true, 'message' => 'Email "Pedido Completo" enviado para: ' . $user->user_email);
            } else {
                do_action('eb_user_courses_updated', $user_id, true, array($course_id));
                return array('success' => true, 'message' => 'Email de matrícula enviado para: ' . $user->user_email);
            }
            
        default:
            return array('success' => false, 'message' => 'Tipo de email desconhecido.');
    }
}