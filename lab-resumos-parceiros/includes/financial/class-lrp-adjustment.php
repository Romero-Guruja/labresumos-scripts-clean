<?php
/**
 * Gerenciamento de Ajustes Manuais
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Adjustment
 * 
 * Gerencia ajustes manuais (bônus/descontos) para afiliados.
 */
class LRP_Adjustment {

    /**
     * Nome da tabela
     *
     * @var string
     */
    private static $table;

    /**
     * Inicializa o nome da tabela
     */
    private static function init_table() {
        global $wpdb;
        self::$table = $wpdb->prefix . 'lrp_adjustments';
    }

    /**
     * Cria um novo ajuste
     *
     * @param int $affiliate_id ID do afiliado
     * @param float $amount Valor (positivo = bônus, negativo = desconto)
     * @param string $reason Motivo do ajuste (obrigatório)
     * @param int|null $created_by ID do admin (usa current_user_id se não informado)
     * @return int|WP_Error ID do ajuste criado ou erro
     */
    public static function create($affiliate_id, $amount, $reason, $created_by = null) {
        global $wpdb;
        self::init_table();
        
        // Verifica permissão
        if (!current_user_can('lrp_manage_commissions')) {
            return new WP_Error('unauthorized', __('Permissão negada.', 'lab-resumos-parceiros'));
        }
        
        // Valida afiliado
        $affiliate = new LRP_Affiliate($affiliate_id);
        if (!$affiliate->exists()) {
            return new WP_Error('invalid_affiliate', __('Afiliado não encontrado.', 'lab-resumos-parceiros'));
        }
        
        // Valida valor
        $amount = floatval($amount);
        if ($amount == 0) {
            return new WP_Error('invalid_amount', __('O valor do ajuste não pode ser zero.', 'lab-resumos-parceiros'));
        }
        
        // Valida motivo
        $reason = trim($reason);
        if (empty($reason)) {
            return new WP_Error('missing_reason', __('O motivo do ajuste é obrigatório.', 'lab-resumos-parceiros'));
        }
        
        $created_by = $created_by ?: get_current_user_id();
        
        $result = $wpdb->insert(
            self::$table,
            [
                'affiliate_id' => (int) $affiliate_id,
                'amount'       => $amount,
                'reason'       => sanitize_textarea_field($reason),
                'status'       => 'pending',
                'created_by'   => (int) $created_by,
                'created_at'   => current_time('mysql'),
            ],
            ['%d', '%f', '%s', '%s', '%d', '%s']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao criar ajuste.', 'lab-resumos-parceiros'));
        }
        
        $adjustment_id = $wpdb->insert_id;
        
        // Log
        lrp_log('Ajuste criado', [
            'adjustment_id' => $adjustment_id,
            'affiliate_id'  => $affiliate_id,
            'amount'        => $amount,
            'reason'        => $reason,
            'created_by'    => $created_by,
        ]);
        
        // Hook para extensões
        do_action('lrp_adjustment_created', $adjustment_id, $affiliate_id, $amount, $reason);
        
        return $adjustment_id;
    }

    /**
     * Cancela um ajuste pendente
     *
     * @param int $adjustment_id ID do ajuste
     * @param int|null $cancelled_by ID do admin
     * @return true|WP_Error
     */
    public static function cancel($adjustment_id, $cancelled_by = null) {
        global $wpdb;
        self::init_table();
        
        // Verifica permissão
        if (!current_user_can('lrp_manage_commissions')) {
            return new WP_Error('unauthorized', __('Permissão negada.', 'lab-resumos-parceiros'));
        }
        
        $adjustment = self::get($adjustment_id);
        
        if (!$adjustment) {
            return new WP_Error('not_found', __('Ajuste não encontrado.', 'lab-resumos-parceiros'));
        }
        
        // Só pode cancelar ajustes pendentes
        if ($adjustment->status !== 'pending') {
            return new WP_Error('invalid_status', __('Apenas ajustes pendentes podem ser cancelados.', 'lab-resumos-parceiros'));
        }
        
        $cancelled_by = $cancelled_by ?: get_current_user_id();
        
        $result = $wpdb->update(
            self::$table,
            [
                'status'       => 'cancelled',
                'cancelled_at' => current_time('mysql'),
                'cancelled_by' => (int) $cancelled_by,
            ],
            ['id' => $adjustment_id],
            ['%s', '%s', '%d'],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao cancelar ajuste.', 'lab-resumos-parceiros'));
        }
        
        lrp_log('Ajuste cancelado', [
            'adjustment_id' => $adjustment_id,
            'cancelled_by'  => $cancelled_by,
        ]);
        
        do_action('lrp_adjustment_cancelled', $adjustment_id);
        
        return true;
    }

    /**
     * Busca ajuste por ID
     *
     * @param int $adjustment_id
     * @return object|null
     */
    public static function get($adjustment_id) {
        global $wpdb;
        self::init_table();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::$table . " WHERE id = %d",
            $adjustment_id
        ));
    }

    /**
     * Busca ajustes de um afiliado
     *
     * @param int $affiliate_id
     * @param array $args Argumentos opcionais
     * @return array
     */
    public static function get_by_affiliate($affiliate_id, $args = []) {
        global $wpdb;
        self::init_table();
        
        $defaults = [
            'status'  => null,
            'limit'   => 50,
            'offset'  => 0,
            'orderby' => 'created_at',
            'order'   => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT a.*, u.display_name as created_by_name 
                FROM " . self::$table . " a
                LEFT JOIN {$wpdb->users} u ON a.created_by = u.ID
                WHERE a.affiliate_id = %d";
        $params = [$affiliate_id];
        
        if ($args['status']) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $sql .= " AND a.status IN ($placeholders)";
                $params = array_merge($params, $args['status']);
            } else {
                $sql .= " AND a.status = %s";
                $params[] = $args['status'];
            }
        }
        
        // Validação segura de orderby
        $allowed_orderby = ['created_at', 'amount', 'status'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql .= " ORDER BY a.{$orderby} {$order}";
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }

    /**
     * Soma ajustes pendentes de um afiliado
     *
     * @param int $affiliate_id
     * @return float
     */
    public static function get_pending_sum($affiliate_id) {
        global $wpdb;
        self::init_table();
        
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM " . self::$table . " 
             WHERE affiliate_id = %d AND status = 'pending'",
            $affiliate_id
        ));
    }

    /**
     * Conta ajustes pendentes de um afiliado
     *
     * @param int $affiliate_id
     * @return int
     */
    public static function count_pending($affiliate_id) {
        global $wpdb;
        self::init_table();
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$table . " 
             WHERE affiliate_id = %d AND status = 'pending'",
            $affiliate_id
        ));
    }

    /**
     * Busca todos os ajustes com filtros (para admin)
     *
     * @param array $args Filtros
     * @return array ['items' => [], 'total' => int]
     */
    public static function get_all($args = []) {
        global $wpdb;
        self::init_table();
        
        $defaults = [
            'affiliate_id' => null,
            'status'       => null,
            'date_from'    => null,
            'date_to'      => null,
            'limit'        => 50,
            'offset'       => 0,
            'orderby'      => 'created_at',
            'order'        => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = "WHERE 1=1";
        $params = [];
        
        if ($args['affiliate_id']) {
            $where .= " AND a.affiliate_id = %d";
            $params[] = (int) $args['affiliate_id'];
        }
        
        if ($args['status']) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where .= " AND a.status IN ($placeholders)";
                $params = array_merge($params, $args['status']);
            } else {
                $where .= " AND a.status = %s";
                $params[] = $args['status'];
            }
        }
        
        if ($args['date_from']) {
            $where .= " AND a.created_at >= %s";
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        
        if ($args['date_to']) {
            $where .= " AND a.created_at <= %s";
            $params[] = $args['date_to'] . ' 23:59:59';
        }
        
        // Count total
        $count_sql = "SELECT COUNT(*) FROM " . self::$table . " a $where";
        $total = $wpdb->get_var(
            empty($params) ? $count_sql : $wpdb->prepare($count_sql, ...$params)
        );
        
        // Validação segura de orderby
        $allowed_orderby = ['created_at', 'amount', 'status', 'affiliate_id'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Items
        $sql = "SELECT a.*, 
                       aff.user_id, 
                       u.display_name as affiliate_name,
                       uc.display_name as created_by_name
                FROM " . self::$table . " a
                JOIN {$wpdb->prefix}lrp_affiliates aff ON a.affiliate_id = aff.id
                JOIN {$wpdb->users} u ON aff.user_id = u.ID
                LEFT JOIN {$wpdb->users} uc ON a.created_by = uc.ID
                $where
                ORDER BY a.{$orderby} {$order}
                LIMIT %d OFFSET %d";
        
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        $items = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        
        return [
            'items' => $items,
            'total' => (int) $total,
        ];
    }

    /**
     * Vincula ajustes pendentes a um fechamento
     *
     * @param int $affiliate_id
     * @param int $closing_id
     * @param string $end_date Data limite (formato Y-m-d H:i:s)
     * @return int Quantidade de ajustes vinculados
     */
    public static function link_to_closing($affiliate_id, $closing_id, $end_date) {
        global $wpdb;
        self::init_table();
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE " . self::$table . " 
             SET closing_id = %d, status = 'closed'
             WHERE affiliate_id = %d 
             AND status = 'pending'
             AND created_at <= %s",
            $closing_id,
            $affiliate_id,
            $end_date
        ));
        
        return $result !== false ? $result : 0;
    }

    /**
     * Soma ajustes vinculados a um fechamento
     *
     * @param int $closing_id
     * @return float
     */
    public static function get_closing_sum($closing_id) {
        global $wpdb;
        self::init_table();
        
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM " . self::$table . " 
             WHERE closing_id = %d AND status IN ('closed', 'paid')",
            $closing_id
        ));
    }

    /**
     * Busca ajustes de um fechamento
     *
     * @param int $closing_id
     * @return array
     */
    public static function get_by_closing($closing_id) {
        global $wpdb;
        self::init_table();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name as created_by_name 
             FROM " . self::$table . " a
             LEFT JOIN {$wpdb->users} u ON a.created_by = u.ID
             WHERE a.closing_id = %d
             ORDER BY a.created_at ASC",
            $closing_id
        ));
    }

    /**
     * Marca ajustes de um fechamento como pagos
     *
     * @param int $closing_id
     * @return bool
     */
    public static function mark_as_paid($closing_id) {
        global $wpdb;
        self::init_table();
        
        $result = $wpdb->update(
            self::$table,
            ['status' => 'paid'],
            ['closing_id' => $closing_id, 'status' => 'closed'],
            ['%s'],
            ['%d', '%s']
        );
        
        return $result !== false;
    }

    /**
     * Obtém estatísticas gerais de ajustes
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;
        self::init_table();
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'pending' AND amount > 0 THEN amount ELSE 0 END) as pending_bonus,
                SUM(CASE WHEN status = 'pending' AND amount < 0 THEN amount ELSE 0 END) as pending_discount,
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid
             FROM " . self::$table
        );
        
        return [
            'total'            => (int) $stats->total,
            'pending_count'    => (int) $stats->pending_count,
            'pending_bonus'    => (float) $stats->pending_bonus,
            'pending_discount' => (float) $stats->pending_discount,
            'pending_total'    => (float) $stats->pending_bonus + (float) $stats->pending_discount,
            'total_paid'       => (float) $stats->total_paid,
        ];
    }
}
