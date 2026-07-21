<?php
/**
 * Modelo de Comissão
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Commission
 * 
 * Representa uma comissão gerada.
 */
class LRP_Commission {

    /**
     * ID da comissão
     *
     * @var int
     */
    private $id = 0;

    /**
     * Dados da comissão
     *
     * @var array
     */
    private $data = [];

    /**
     * Construtor
     *
     * @param int|object $commission
     */
    public function __construct($commission = 0) {
        if (is_numeric($commission) && $commission > 0) {
            $this->id = (int) $commission;
            $this->read();
        } elseif (is_object($commission)) {
            $this->set_props($commission);
        }
    }

    /**
     * Lê dados do banco
     */
    private function read() {
        global $wpdb;
        
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lrp_commissions WHERE id = %d",
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

    public function get_referral_id() {
        return (int) ($this->data['referral_id'] ?? 0);
    }

    public function get_affiliate_id() {
        return (int) ($this->data['affiliate_id'] ?? 0);
    }

    public function get_affiliate() {
        return new LRP_Affiliate($this->get_affiliate_id());
    }

    public function get_commission_type() {
        return $this->data['commission_type'] ?? 'direct';
    }

    public function get_source_affiliate_id() {
        return isset($this->data['source_affiliate_id']) ? (int) $this->data['source_affiliate_id'] : null;
    }

    public function get_commission_rate() {
        return (float) ($this->data['commission_rate'] ?? 0);
    }

    public function get_commission_amount() {
        return (float) ($this->data['commission_amount'] ?? 0);
    }

    public function get_status() {
        return $this->data['status'] ?? 'pending';
    }

    public function get_closing_id() {
        return isset($this->data['closing_id']) ? (int) $this->data['closing_id'] : null;
    }

    public function get_created_at() {
        return $this->data['created_at'] ?? '';
    }

    public function exists() {
        return $this->id > 0;
    }

    public function is_pending() {
        return $this->get_status() === 'pending';
    }

    public function is_approved() {
        return $this->get_status() === 'approved';
    }

    public function is_paid() {
        return $this->get_status() === 'paid';
    }

    // ========================================
    // CRUD
    // ========================================

    /**
     * Busca comissão por ID
     *
     * @param int $id
     * @return LRP_Commission|null
     */
    public static function get($id) {
        $commission = new self($id);
        return $commission->exists() ? $commission : null;
    }

    /**
     * Cria nova comissão
     *
     * @param array $data
     * @return LRP_Commission|WP_Error
     */
    public static function create($data) {
        global $wpdb;
        
        $required = ['referral_id', 'affiliate_id', 'commission_type', 'commission_rate', 'commission_amount'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Campo obrigatório: %s', 'lab-resumos-parceiros'), $field));
            }
        }
        
        $insert_data = [
            'referral_id'         => (int) $data['referral_id'],
            'affiliate_id'        => (int) $data['affiliate_id'],
            'commission_type'     => $data['commission_type'],
            'source_affiliate_id' => $data['source_affiliate_id'] ?? null,
            'commission_rate'     => (float) $data['commission_rate'],
            'commission_amount'   => (float) $data['commission_amount'],
            'status'              => $data['status'] ?? 'pending',
            'created_at'          => current_time('mysql'),
        ];
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'lrp_commissions',
            $insert_data
        );
        
        if (!$result) {
            return new WP_Error('db_error', __('Erro ao criar comissão.', 'lab-resumos-parceiros'));
        }
        
        $commission = new self($wpdb->insert_id);
        
        do_action('lrp_commission_created', $commission);
        
        return $commission;
    }

    /**
     * Atualiza status da comissão
     *
     * @param string $status
     * @return bool
     */
    public function update_status($status) {
        global $wpdb;
        
        if (!$this->exists()) {
            return false;
        }
        
        $valid_statuses = ['pending', 'approved', 'paid', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            return false;
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_commissions',
            ['status' => $status],
            ['id' => $this->id]
        );
        
        if ($result !== false) {
            $this->data['status'] = $status;
        }
        
        return $result !== false;
    }

    /**
     * Busca comissões por afiliado
     *
     * @param int $affiliate_id
     * @param array $args
     * @return array
     */
    public static function get_by_affiliate($affiliate_id, $args = []) {
        global $wpdb;
        
        $defaults = [
            'status'   => null,
            'limit'    => 20,
            'offset'   => 0,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM {$wpdb->prefix}lrp_commissions WHERE affiliate_id = %d";
        $params = [$affiliate_id];
        
        if ($args['status']) {
            $sql .= " AND status = %s";
            $params[] = $args['status'];
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
     * Busca comissões por referral
     *
     * @param int $referral_id
     * @return array
     */
    public static function get_by_referral($referral_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lrp_commissions WHERE referral_id = %d ORDER BY commission_type",
            $referral_id
        ));
        
        return array_map(function($row) {
            return new self($row);
        }, $results ?: []);
    }

    /**
     * Busca comissões por fechamento
     *
     * @param int $closing_id
     * @return array
     */
    public static function get_by_closing($closing_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lrp_commissions WHERE closing_id = %d ORDER BY created_at",
            $closing_id
        ));
        
        return array_map(function($row) {
            return new self($row);
        }, $results ?: []);
    }

    /**
     * Conta comissões por status
     *
     * @param int $affiliate_id
     * @param string $status
     * @return int
     */
    public static function count_by_status($affiliate_id, $status) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_commissions WHERE affiliate_id = %d AND status = %s",
            $affiliate_id,
            $status
        ));
    }

    /**
     * Soma comissões por status
     *
     * @param int $affiliate_id
     * @param string $status
     * @return float
     */
    public static function sum_by_status($affiliate_id, $status) {
        global $wpdb;
        
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(commission_amount), 0) FROM {$wpdb->prefix}lrp_commissions WHERE affiliate_id = %d AND status = %s",
            $affiliate_id,
            $status
        ));
    }
}

