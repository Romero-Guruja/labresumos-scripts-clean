<?php
/**
 * Modelo de Referral (Venda Atribuída)
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Referral
 * 
 * Representa uma venda atribuída a um afiliado.
 */
class LRP_Referral {

    /**
     * ID do referral
     *
     * @var int
     */
    private $id = 0;

    /**
     * Dados do referral
     *
     * @var array
     */
    private $data = [];

    /**
     * Construtor
     *
     * @param int|object $referral
     */
    public function __construct($referral = 0) {
        if (is_numeric($referral) && $referral > 0) {
            $this->id = (int) $referral;
            $this->read();
        } elseif (is_object($referral)) {
            $this->set_props($referral);
        }
    }

    /**
     * Lê dados do banco
     */
    private function read() {
        global $wpdb;
        
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lrp_referrals WHERE id = %d",
            $this->id
        ));
        
        if ($data) {
            $this->set_props($data);
        }
    }

    /**
     * Define propriedades
     *
     * @param object $data
     */
    private function set_props($data) {
        $this->id = (int) $data->id;
        $this->data = (array) $data;
    }

    // ========================================
    // GETTERS
    // ========================================

    public function get_id() {
        return $this->id;
    }

    public function get_affiliate_id() {
        return (int) ($this->data['affiliate_id'] ?? 0);
    }

    public function get_affiliate() {
        return new LRP_Affiliate($this->get_affiliate_id());
    }

    public function get_order_id() {
        return (int) ($this->data['order_id'] ?? 0);
    }

    public function get_order() {
        return wc_get_order($this->get_order_id());
    }

    public function get_attribution_type() {
        return $this->data['attribution_type'] ?? 'direct';
    }

    public function get_coupon_used() {
        return $this->data['coupon_used'] ?? '';
    }

    public function get_order_total() {
        return (float) ($this->data['order_total'] ?? 0);
    }

    public function get_discount_amount() {
        return (float) ($this->data['discount_amount'] ?? 0);
    }

    public function get_discount_source() {
        return $this->data['discount_source'] ?? null;
    }

    public function get_commission_base() {
        return (float) ($this->data['commission_base'] ?? 0);
    }

    public function get_status() {
        return $this->data['status'] ?? 'pending';
    }

    public function get_customer_id() {
        return (int) ($this->data['customer_id'] ?? 0);
    }

    public function get_customer_email() {
        return $this->data['customer_email'] ?? '';
    }

    public function is_guruja_student() {
        return (bool) ($this->data['is_guruja_student'] ?? 0);
    }

    public function get_created_at() {
        return $this->data['created_at'] ?? '';
    }

    public function exists() {
        return $this->id > 0;
    }

    /**
     * Retorna comissão direta deste referral
     *
     * @return float
     */
    public function get_direct_commission() {
        $commissions = LRP_Commission::get_by_referral($this->id);
        
        foreach ($commissions as $commission) {
            if ($commission->get_commission_type() === 'direct') {
                return $commission->get_commission_amount();
            }
        }
        
        return 0;
    }

    /**
     * Retorna todas as comissões deste referral
     *
     * @return array
     */
    public function get_commissions() {
        return LRP_Commission::get_by_referral($this->id);
    }

    // ========================================
    // CRUD
    // ========================================

    /**
     * Busca referral por ID
     *
     * @param int $id
     * @return LRP_Referral|null
     */
    public static function get($id) {
        $referral = new self($id);
        return $referral->exists() ? $referral : null;
    }

    /**
     * Busca referral por order_id
     *
     * @param int $order_id
     * @return LRP_Referral|null
     */
    public static function get_by_order_id($order_id) {
        global $wpdb;
        
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lrp_referrals WHERE order_id = %d",
            $order_id
        ));
        
        return $id ? new self($id) : null;
    }

    /**
     * Cria novo referral
     *
     * @param array $data
     * @param bool $allow_multiple Permite múltiplos referrals para mesmo pedido (ex: afiliados diferentes em comissão cumulativa)
     * @return LRP_Referral|WP_Error
     */
    public static function create($data, $allow_multiple = false) {
        global $wpdb;
        
        $required = ['affiliate_id', 'order_id', 'attribution_type', 'order_total', 'commission_base'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Campo obrigatório: %s', 'lab-resumos-parceiros'), $field));
            }
        }
        
        // Verifica se já existe referral para esta order (exceto em modo múltiplo)
        if (!$allow_multiple) {
            $existing = self::get_by_order_id($data['order_id']);
            if ($existing) {
                return new WP_Error('duplicate', __('Já existe referral para este pedido.', 'lab-resumos-parceiros'));
            }
        } else {
            // Em modo múltiplo, verifica se já existe referral para ESTE afiliado + pedido
            $existing = self::get_by_order_and_affiliate($data['order_id'], $data['affiliate_id']);
            if ($existing) {
                return new WP_Error('duplicate', __('Já existe referral para este pedido e afiliado.', 'lab-resumos-parceiros'));
            }
        }
        
        $insert_data = [
            'affiliate_id'      => (int) $data['affiliate_id'],
            'order_id'          => (int) $data['order_id'],
            'attribution_type'  => $data['attribution_type'],
            'coupon_used'       => $data['coupon_used'] ?? null,
            'order_total'       => (float) $data['order_total'],
            'discount_amount'   => (float) ($data['discount_amount'] ?? 0),
            'discount_source'   => $data['discount_source'] ?? null,
            'commission_base'   => (float) $data['commission_base'],
            'status'            => $data['status'] ?? 'pending',
            'customer_id'       => $data['customer_id'] ?? null,
            'customer_email'    => $data['customer_email'] ?? null,
            'is_guruja_student' => $data['is_guruja_student'] ?? 0,
            'created_at'        => current_time('mysql'),
        ];
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'lrp_referrals',
            $insert_data
        );
        
        if (!$result) {
            return new WP_Error('db_error', __('Erro ao criar referral.', 'lab-resumos-parceiros'));
        }
        
        $referral = new self($wpdb->insert_id);
        
        do_action('lrp_referral_created', $referral);
        
        return $referral;
    }
    
    /**
     * Busca referral por order_id e affiliate_id
     *
     * @param int $order_id
     * @param int $affiliate_id
     * @return LRP_Referral|null
     */
    public static function get_by_order_and_affiliate($order_id, $affiliate_id) {
        global $wpdb;
        
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lrp_referrals WHERE order_id = %d AND affiliate_id = %d",
            $order_id,
            $affiliate_id
        ));
        
        return $id ? new self($id) : null;
    }
    
    /**
     * Busca todos os referrals de um pedido
     *
     * @param int $order_id
     * @return array
     */
    public static function get_all_by_order_id($order_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lrp_referrals WHERE order_id = %d",
            $order_id
        ));
        
        return array_map(function($row) {
            return new self($row);
        }, $results ?: []);
    }

    /**
     * Atualiza status do referral
     *
     * @param string $status
     * @return bool
     */
    public function update_status($status) {
        global $wpdb;
        
        if (!$this->exists()) {
            return false;
        }
        
        $valid_statuses = ['pending', 'approved', 'rejected', 'refunded'];
        if (!in_array($status, $valid_statuses)) {
            return false;
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_referrals',
            ['status' => $status],
            ['id' => $this->id]
        );
        
        if ($result !== false) {
            $this->data['status'] = $status;
            
            if ($status === 'approved') {
                do_action('lrp_referral_approved', $this);
            }
        }
        
        return $result !== false;
    }

    /**
     * Busca referrals por afiliado
     *
     * @param int $affiliate_id
     * @param array $args
     * @return array
     */
    public static function get_by_affiliate($affiliate_id, $args = []) {
        global $wpdb;
        
        $defaults = [
            'status'     => null,
            'start_date' => null,
            'end_date'   => null,
            'limit'      => 20,
            'offset'     => 0,
            'orderby'    => 'created_at',
            'order'      => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM {$wpdb->prefix}lrp_referrals WHERE affiliate_id = %d";
        $params = [$affiliate_id];
        
        if ($args['status']) {
            $sql .= " AND status = %s";
            $params[] = $args['status'];
        }
        
        if ($args['start_date']) {
            $sql .= " AND created_at >= %s";
            $params[] = $args['start_date'];
        }
        
        if ($args['end_date']) {
            $sql .= " AND created_at <= %s";
            $params[] = $args['end_date'];
        }
        
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        
        return array_map(function($row) {
            return new self($row);
        }, $results ?: []);
    }

    /**
     * Busca referrals recentes por afiliado
     *
     * @param int $affiliate_id
     * @param int $limit
     * @return array
     */
    public static function get_recent_by_affiliate($affiliate_id, $limit = 5) {
        return self::get_by_affiliate($affiliate_id, [
            'limit'   => $limit,
            'orderby' => 'created_at',
            'order'   => 'DESC',
        ]);
    }

    /**
     * Conta referrals do mês atual
     *
     * @param int $affiliate_id
     * @return int
     */
    public static function count_this_month($affiliate_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_referrals 
             WHERE affiliate_id = %d 
             AND MONTH(created_at) = MONTH(CURRENT_DATE())
             AND YEAR(created_at) = YEAR(CURRENT_DATE())",
            $affiliate_id
        ));
    }

    /**
     * Soma comissões do mês atual
     *
     * @param int $affiliate_id
     * @return float
     */
    public static function sum_commissions_this_month($affiliate_id) {
        global $wpdb;
        
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(c.commission_amount), 0) 
             FROM {$wpdb->prefix}lrp_commissions c
             JOIN {$wpdb->prefix}lrp_referrals r ON c.referral_id = r.id
             WHERE c.affiliate_id = %d 
             AND MONTH(c.created_at) = MONTH(CURRENT_DATE())
             AND YEAR(c.created_at) = YEAR(CURRENT_DATE())",
            $affiliate_id
        ));
    }
}

