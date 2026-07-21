<?php
/**
 * Classe de gerenciamento de casos de recuperação
 *
 * @package LR_Recuperacao_Vendas
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LR_Recovery_Manager
 * Gerencia a lógica de negócio para casos de recuperação
 */
class LR_Recovery_Manager {

    /**
     * Nome da tabela de casos
     * @var string
     */
    private $table_cases;

    /**
     * Nome da tabela de logs
     * @var string
     */
    private $table_logs;

    /**
     * Construtor
     */
    public function __construct() {
        global $wpdb;
        $this->table_cases = $wpdb->prefix . 'lr_recovery_cases';
        $this->table_logs = $wpdb->prefix . 'lr_recovery_logs';

        // Hooks para detectar pedidos falhados
        add_action('woocommerce_order_status_failed', [$this, 'handle_failed_order'], 10, 2);
        
        // Hook para auto-resolução
        add_action('woocommerce_order_status_completed', [$this, 'check_auto_resolution'], 10, 2);
        add_action('woocommerce_order_status_processing', [$this, 'check_auto_resolution'], 10, 2);
    }

    /**
     * Manipula pedido que falhou
     * @param int $order_id
     * @param WC_Order $order
     */
    public function handle_failed_order($order_id, $order = null) {
        if (!$order) {
            $order = wc_get_order($order_id);
        }

        if (!$order) {
            return;
        }

        // Verificar se já existe caso para este pedido
        $existing = $this->get_case_by_order($order_id);
        if ($existing) {
            return;
        }

        // Extrair informações de falha
        $failure_info = $this->extract_failure_reason($order_id);

        // Criar caso
        $case_id = $this->create_case($order_id, $failure_info);

        if ($case_id) {
            // Disparar hook
            do_action('lr_recovery_case_created', $case_id, $order_id);
        }
    }

    /**
     * Cria um novo caso de recuperação
     * @param int $order_id
     * @param array $failure_info
     * @return int|false ID do caso ou false em caso de erro
     */
    public function create_case($order_id, $failure_info = []) {
        global $wpdb;

        $default_checklist = $this->get_default_checklist();
        $order = wc_get_order($order_id);

        // Aplicar filtro para customizar checklist
        $checklist = apply_filters('lr_recovery_checklist_items', $default_checklist, $order);

        $data = [
            'order_id' => $order_id,
            'status' => 'novo',
            'failure_reason' => isset($failure_info['message']) ? $failure_info['message'] : '',
            'failure_type' => isset($failure_info['type']) ? $failure_info['type'] : 'outro',
            'charge_id' => isset($failure_info['charge_id']) ? $failure_info['charge_id'] : '',
            'checklist' => wp_json_encode($checklist),
            'created_at' => current_time('mysql'),
        ];

        $result = $wpdb->insert($this->table_cases, $data, [
            '%d', '%s', '%s', '%s', '%s', '%s', '%s'
        ]);

        if ($result) {
            $case_id = $wpdb->insert_id;
            
            // Registrar log de criação
            $this->add_log($case_id, 0, 'created', __('Caso criado automaticamente', 'lr-recuperacao-vendas'));
            
            // Limpar cache de contagem
            delete_transient('lr_recovery_pending_count');

            return $case_id;
        }

        return false;
    }

    /**
     * Retorna checklist padrão
     * @return array
     */
    public function get_default_checklist() {
        return [
            'contact_customer' => [
                'label' => __('Contatar cliente via WhatsApp', 'lr-recuperacao-vendas'),
                'description' => __('Confirmar se é compra legítima e se podemos reprocessar', 'lr-recuperacao-vendas'),
                'completed' => false,
                'completed_at' => null,
                'completed_by' => null,
            ],
            'reprocess_payment' => [
                'label' => __('Reprocessar pagamento na Pagar.me', 'lr-recuperacao-vendas'),
                'description' => __('Reprocessar sem antifraude', 'lr-recuperacao-vendas'),
                'completed' => false,
                'completed_at' => null,
                'completed_by' => null,
            ],
            'enroll_student' => [
                'label' => __('Matricular aluno no curso', 'lr-recuperacao-vendas'),
                'description' => __('Via Edwiser Bridge', 'lr-recuperacao-vendas'),
                'completed' => false,
                'completed_at' => null,
                'completed_by' => null,
            ],
            'complete_order' => [
                'label' => __('Alterar pedido para Concluído', 'lr-recuperacao-vendas'),
                'description' => '',
                'completed' => false,
                'completed_at' => null,
                'completed_by' => null,
            ],
            'issue_invoice' => [
                'label' => __('Verificar/Emitir NF-e', 'lr-recuperacao-vendas'),
                'description' => '',
                'completed' => false,
                'completed_at' => null,
                'completed_by' => null,
            ],
        ];
    }

    /**
     * Extrai motivo da falha das order notes
     * @param int $order_id
     * @return array
     */
    public function extract_failure_reason($order_id) {
        $notes = wc_get_order_notes(['order_id' => $order_id]);

        $failure_info = [
            'type' => 'outro',
            'message' => '',
            'charge_id' => '',
            'is_antifraud' => false,
        ];

        $has_approved = false;
        $has_failed = false;

        foreach ($notes as $note) {
            $content = $note->content;

            // Extrair charge_id
            if (preg_match('/ch_[A-Za-z0-9]+/', $content, $matches)) {
                $failure_info['charge_id'] = $matches[0];
            }

            // Verificar se houve aprovação
            if (stripos($content, 'aprovada com sucesso') !== false) {
                $has_approved = true;
            }

            // Verificar se houve falha
            if (stripos($content, 'payment_failed') !== false || 
                stripos($content, 'Charge failed') !== false ||
                stripos($content, 'failed') !== false) {
                $has_failed = true;
            }

            // Identificar tipo de falha
            if (stripos($content, 'excesso de retentativas') !== false) {
                $failure_info['type'] = 'retentativas';
                $failure_info['message'] = __('Transação recusada por excesso de retentativas', 'lr-recuperacao-vendas');
            } elseif (stripos($content, 'recusada pelo banco') !== false) {
                $failure_info['type'] = 'banco';
                $failure_info['message'] = __('Transação recusada pelo banco', 'lr-recuperacao-vendas');
            } elseif (stripos($content, 'antifraude') !== false) {
                $failure_info['type'] = 'antifraude';
                $failure_info['message'] = __('Transação bloqueada por antifraude', 'lr-recuperacao-vendas');
                $failure_info['is_antifraud'] = true;
            }
        }

        // Se teve aprovação E falha, provavelmente é antifraude
        if ($has_approved && $has_failed && $failure_info['type'] !== 'antifraude') {
            $failure_info['is_antifraud'] = true;
            $failure_info['type'] = 'antifraude';
            if (empty($failure_info['message'])) {
                $failure_info['message'] = __('Provável bloqueio por antifraude (aprovado pelo banco, mas falhou)', 'lr-recuperacao-vendas');
            }
        }

        return $failure_info;
    }

    /**
     * Verifica se pedido provavelmente foi bloqueado por antifraude
     * @param int $order_id
     * @return bool
     */
    public function is_likely_antifraud($order_id) {
        $failure_info = $this->extract_failure_reason($order_id);
        return $failure_info['is_antifraud'];
    }

    /**
     * Obtém caso por ID do pedido
     * @param int $order_id
     * @return object|null
     */
    public function get_case_by_order($order_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_cases} WHERE order_id = %d",
            $order_id
        ));
    }

    /**
     * Obtém caso por ID
     * @param int $case_id
     * @return object|null
     */
    public function get_case($case_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_cases} WHERE id = %d",
            $case_id
        ));
    }

    /**
     * Obtém casos com filtros
     * @param array $args
     * @return array
     */
    public function get_cases($args = []) {
        global $wpdb;

        $defaults = [
            'status' => '',
            'assigned_to' => '',
            'failure_type' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if (!empty($args['assigned_to'])) {
            $where[] = 'assigned_to = %d';
            $values[] = $args['assigned_to'];
        }

        if (!empty($args['failure_type'])) {
            $where[] = 'failure_type = %s';
            $values[] = $args['failure_type'];
        }

        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        $where_clause = implode(' AND ', $where);
        
        // Sanitizar orderby e order
        $allowed_orderby = ['id', 'order_id', 'status', 'created_at', 'updated_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$this->table_cases} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Conta casos por status
     * @param string $status
     * @return int
     */
    public function count_cases($status = '') {
        global $wpdb;

        if (!empty($status)) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_cases} WHERE status = %s",
                $status
            ));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_cases}");
    }

    /**
     * Conta casos pendentes (não resolvidos)
     * @return int
     */
    public function count_pending_cases() {
        $cached = get_transient('lr_recovery_pending_count');
        
        if ($cached !== false) {
            return (int) $cached;
        }

        global $wpdb;

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_cases} WHERE status NOT IN ('resolvido', 'abandonado')"
        );

        set_transient('lr_recovery_pending_count', $count, 5 * MINUTE_IN_SECONDS);

        return $count;
    }

    /**
     * Obtém estatísticas
     * @return array
     */
    public function get_statistics() {
        global $wpdb;

        $stats = [
            'novo' => 0,
            'em_atendimento' => 0,
            'aguardando_cliente' => 0,
            'resolvido' => 0,
            'abandonado' => 0,
            'total_recuperado' => 0,
        ];

        // Contar por status
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table_cases} GROUP BY status"
        );

        foreach ($results as $row) {
            if (isset($stats[$row->status])) {
                $stats[$row->status] = (int) $row->count;
            }
        }

        // Calcular valor total recuperado (casos resolvidos)
        $resolved_cases = $wpdb->get_results(
            "SELECT order_id FROM {$this->table_cases} WHERE status = 'resolvido'"
        );

        foreach ($resolved_cases as $case) {
            $order = wc_get_order($case->order_id);
            if ($order) {
                $stats['total_recuperado'] += (float) $order->get_total();
            }
        }

        return $stats;
    }

    /**
     * Atualiza status do caso
     * @param int $case_id
     * @param string $status
     * @param int $user_id
     * @return bool
     */
    public function update_case_status($case_id, $status, $user_id = 0) {
        global $wpdb;

        $valid_statuses = ['novo', 'em_atendimento', 'aguardando_cliente', 'resolvido', 'abandonado'];
        
        if (!in_array($status, $valid_statuses)) {
            return false;
        }

        $data = [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ];

        // Se resolvido ou abandonado, registrar data de resolução
        if (in_array($status, ['resolvido', 'abandonado'])) {
            $data['resolved_at'] = current_time('mysql');
        }

        $result = $wpdb->update(
            $this->table_cases,
            $data,
            ['id' => $case_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            // Registrar log
            $status_labels = [
                'novo' => __('Novo', 'lr-recuperacao-vendas'),
                'em_atendimento' => __('Em atendimento', 'lr-recuperacao-vendas'),
                'aguardando_cliente' => __('Aguardando cliente', 'lr-recuperacao-vendas'),
                'resolvido' => __('Resolvido', 'lr-recuperacao-vendas'),
                'abandonado' => __('Abandonado', 'lr-recuperacao-vendas'),
            ];

            $this->add_log(
                $case_id,
                $user_id ?: get_current_user_id(),
                'status_changed',
                sprintf(__('Status alterado para: %s', 'lr-recuperacao-vendas'), $status_labels[$status])
            );

            // Limpar cache
            delete_transient('lr_recovery_pending_count');

            // Disparar hook se resolvido
            if ($status === 'resolvido') {
                $case = $this->get_case($case_id);
                do_action('lr_recovery_case_resolved', $case_id, $case->order_id, 'manual');
            }

            return true;
        }

        return false;
    }

    /**
     * Atribui caso a um usuário
     * @param int $case_id
     * @param int $user_id
     * @return bool
     */
    public function assign_case($case_id, $user_id) {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_cases,
            [
                'assigned_to' => $user_id,
                'status' => 'em_atendimento',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $case_id],
            ['%d', '%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            $user = get_userdata($user_id);
            $this->add_log(
                $case_id,
                $user_id,
                'assigned',
                sprintf(__('Caso assumido por %s', 'lr-recuperacao-vendas'), $user->display_name)
            );

            delete_transient('lr_recovery_pending_count');

            return true;
        }

        return false;
    }

    /**
     * Atualiza item do checklist
     * @param int $case_id
     * @param string $item_key
     * @param bool $completed
     * @param int $user_id
     * @return bool
     */
    public function update_checklist_item($case_id, $item_key, $completed, $user_id = 0) {
        global $wpdb;

        $case = $this->get_case($case_id);
        if (!$case) {
            return false;
        }

        $checklist = json_decode($case->checklist, true);
        if (!isset($checklist[$item_key])) {
            return false;
        }

        $checklist[$item_key]['completed'] = (bool) $completed;
        
        if ($completed) {
            $checklist[$item_key]['completed_at'] = current_time('mysql');
            $checklist[$item_key]['completed_by'] = $user_id ?: get_current_user_id();
        } else {
            $checklist[$item_key]['completed_at'] = null;
            $checklist[$item_key]['completed_by'] = null;
        }

        $result = $wpdb->update(
            $this->table_cases,
            [
                'checklist' => wp_json_encode($checklist),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $case_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            $action = $completed ? 'checklist_completed' : 'checklist_unchecked';
            $this->add_log(
                $case_id,
                $user_id ?: get_current_user_id(),
                $action,
                sprintf(
                    $completed 
                        ? __('Item marcado como concluído: %s', 'lr-recuperacao-vendas')
                        : __('Item desmarcado: %s', 'lr-recuperacao-vendas'),
                    $checklist[$item_key]['label']
                )
            );

            // Disparar hook
            if ($completed) {
                do_action('lr_recovery_checklist_completed', $case_id, $item_key, $user_id ?: get_current_user_id());
            }

            return true;
        }

        return false;
    }

    /**
     * Adiciona log ao caso
     * @param int $case_id
     * @param int $user_id
     * @param string $action
     * @param string $details
     * @return int|false
     */
    public function add_log($case_id, $user_id, $action, $details = '') {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_logs,
            [
                'case_id' => $case_id,
                'user_id' => $user_id,
                'action' => $action,
                'details' => $details,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Obtém logs de um caso
     * @param int $case_id
     * @param int $limit
     * @return array
     */
    public function get_case_logs($case_id, $limit = 50) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_logs} WHERE case_id = %d ORDER BY created_at DESC LIMIT %d",
            $case_id,
            $limit
        ));
    }

    /**
     * Resolve caso automaticamente
     * @param int $case_id
     * @param string $resolution_type
     * @param string $message
     * @return bool
     */
    public function resolve_case($case_id, $resolution_type = 'manual', $message = '') {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_cases,
            [
                'status' => 'resolvido',
                'resolved_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $case_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            $this->add_log(
                $case_id,
                0,
                'resolved_' . $resolution_type,
                $message ?: __('Caso resolvido', 'lr-recuperacao-vendas')
            );

            delete_transient('lr_recovery_pending_count');

            $case = $this->get_case($case_id);
            do_action('lr_recovery_case_resolved', $case_id, $case->order_id, $resolution_type);

            return true;
        }

        return false;
    }

    /**
     * Verifica auto-resolução quando pedido é completado
     * @param int $order_id
     * @param WC_Order $order
     */
    public function check_auto_resolution($order_id, $order = null) {
        if (!$order) {
            $order = wc_get_order($order_id);
        }

        if (!$order) {
            return;
        }

        // 1) Resolver caso do próprio pedido, se existir
        $own_case = $this->get_case_by_order($order_id);
        if ($own_case && !in_array($own_case->status, ['resolvido', 'abandonado'])) {
            $this->resolve_case(
                $own_case->id,
                'auto',
                sprintf(
                    __('Pedido #%d concluído — caso resolvido automaticamente', 'lr-recuperacao-vendas'),
                    $order_id
                )
            );
        }

        // 2) Resolver casos de outros pedidos do mesmo cliente com produtos em comum
        $email = $order->get_billing_email();
        $new_products = $this->get_order_product_ids($order);

        global $wpdb;

        $cases = $wpdb->get_results($wpdb->prepare(
            "SELECT rc.* FROM {$this->table_cases} rc
            INNER JOIN {$wpdb->prefix}wc_orders o ON rc.order_id = o.id
            WHERE rc.status NOT IN ('resolvido', 'abandonado')
            AND o.billing_email = %s
            AND rc.order_id != %d",
            $email,
            $order_id
        ));

        if (empty($cases)) {
            $cases = $wpdb->get_results($wpdb->prepare(
                "SELECT rc.* FROM {$this->table_cases} rc
                INNER JOIN {$wpdb->postmeta} pm ON rc.order_id = pm.post_id AND pm.meta_key = '_billing_email'
                WHERE rc.status NOT IN ('resolvido', 'abandonado')
                AND pm.meta_value = %s
                AND rc.order_id != %d",
                $email,
                $order_id
            ));
        }

        foreach ($cases as $case) {
            $old_order = wc_get_order($case->order_id);
            if (!$old_order) {
                continue;
            }

            $old_products = $this->get_order_product_ids($old_order);

            if (array_intersect($old_products, $new_products)) {
                $this->resolve_case(
                    $case->id,
                    'auto',
                    sprintf(
                        __('Cliente completou nova compra (Pedido #%d) com os mesmos produtos', 'lr-recuperacao-vendas'),
                        $order_id
                    )
                );
            }
        }
    }

    /**
     * Obtém IDs dos produtos de um pedido
     * @param WC_Order $order
     * @return array
     */
    public function get_order_product_ids($order) {
        $product_ids = [];

        foreach ($order->get_items() as $item) {
            $product_ids[] = $item->get_product_id();
        }

        return $product_ids;
    }

    /**
     * Marca pedido como concluído
     * @param int $order_id
     * @return bool
     */
    public function complete_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }

        if ($order->get_status() !== 'completed') {
            $order->update_status('completed', __('Pedido marcado como concluído via Recuperação de Vendas', 'lr-recuperacao-vendas'));
            return true;
        }

        return false;
    }

    // ==========================================
    // AJAX Handlers
    // ==========================================

    /**
     * AJAX: Atualizar item do checklist
     */
    public function ajax_update_checklist() {
        check_ajax_referer('lr_recovery_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permissão negada', 'lr-recuperacao-vendas')]);
        }

        $case_id = isset($_POST['case_id']) ? absint($_POST['case_id']) : 0;
        $item_key = isset($_POST['item']) ? sanitize_text_field($_POST['item']) : '';
        $completed = isset($_POST['completed']) ? filter_var($_POST['completed'], FILTER_VALIDATE_BOOLEAN) : false;

        if (!$case_id || !$item_key) {
            wp_send_json_error(['message' => __('Dados inválidos', 'lr-recuperacao-vendas')]);
        }

        $result = $this->update_checklist_item($case_id, $item_key, $completed);

        if ($result) {
            wp_send_json_success(['message' => __('Checklist atualizado', 'lr-recuperacao-vendas')]);
        } else {
            wp_send_json_error(['message' => __('Erro ao atualizar checklist', 'lr-recuperacao-vendas')]);
        }
    }

    /**
     * AJAX: Atribuir caso
     */
    public function ajax_assign_case() {
        check_ajax_referer('lr_recovery_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permissão negada', 'lr-recuperacao-vendas')]);
        }

        $case_id = isset($_POST['case_id']) ? absint($_POST['case_id']) : 0;
        $user_id = get_current_user_id();

        if (!$case_id) {
            wp_send_json_error(['message' => __('Dados inválidos', 'lr-recuperacao-vendas')]);
        }

        $result = $this->assign_case($case_id, $user_id);

        if ($result) {
            wp_send_json_success(['message' => __('Caso assumido com sucesso', 'lr-recuperacao-vendas')]);
        } else {
            wp_send_json_error(['message' => __('Erro ao assumir caso', 'lr-recuperacao-vendas')]);
        }
    }

    /**
     * AJAX: Adicionar observação
     */
    public function ajax_add_note() {
        check_ajax_referer('lr_recovery_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permissão negada', 'lr-recuperacao-vendas')]);
        }

        $case_id = isset($_POST['case_id']) ? absint($_POST['case_id']) : 0;
        $note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';

        if (!$case_id || empty($note)) {
            wp_send_json_error(['message' => __('Dados inválidos', 'lr-recuperacao-vendas')]);
        }

        $result = $this->add_log($case_id, get_current_user_id(), 'note', $note);

        if ($result) {
            wp_send_json_success(['message' => __('Observação adicionada', 'lr-recuperacao-vendas')]);
        } else {
            wp_send_json_error(['message' => __('Erro ao adicionar observação', 'lr-recuperacao-vendas')]);
        }
    }

    /**
     * AJAX: Atualizar status do caso
     */
    public function ajax_update_case_status() {
        check_ajax_referer('lr_recovery_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permissão negada', 'lr-recuperacao-vendas')]);
        }

        $case_id = isset($_POST['case_id']) ? absint($_POST['case_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$case_id || !$status) {
            wp_send_json_error(['message' => __('Dados inválidos', 'lr-recuperacao-vendas')]);
        }

        $result = $this->update_case_status($case_id, $status);

        if ($result) {
            wp_send_json_success(['message' => __('Status atualizado', 'lr-recuperacao-vendas')]);
        } else {
            wp_send_json_error(['message' => __('Erro ao atualizar status', 'lr-recuperacao-vendas')]);
        }
    }

    /**
     * AJAX: Marcar pedido como concluído
     */
    public function ajax_complete_order() {
        check_ajax_referer('lr_recovery_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permissão negada', 'lr-recuperacao-vendas')]);
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $case_id = isset($_POST['case_id']) ? absint($_POST['case_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(['message' => __('Dados inválidos', 'lr-recuperacao-vendas')]);
        }

        $result = $this->complete_order($order_id);

        if ($result) {
            // Também atualizar o checklist se case_id foi fornecido
            if ($case_id) {
                $this->update_checklist_item($case_id, 'complete_order', true);
            }
            
            wp_send_json_success(['message' => __('Pedido marcado como concluído', 'lr-recuperacao-vendas')]);
        } else {
            wp_send_json_error(['message' => __('Erro ao marcar pedido como concluído ou pedido já está concluído', 'lr-recuperacao-vendas')]);
        }
    }
}
