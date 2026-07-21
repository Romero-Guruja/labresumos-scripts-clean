<?php
/**
 * Modelo de Restrições de Produtos por Afiliado
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Product_Restriction
 * 
 * Gerencia restrições de produtos (blacklist/whitelist) por afiliado.
 */
class LRP_Product_Restriction {

    /**
     * Instância única
     *
     * @var LRP_Product_Restriction|null
     */
    private static $instance = null;

    /**
     * Cache de restrições por afiliado
     *
     * @var array
     */
    private $cache = [];

    /**
     * Retorna instância única
     *
     * @return LRP_Product_Restriction
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado
     */
    private function __construct() {
        // Singleton
    }

    /**
     * Retorna nome da tabela
     *
     * @return string
     */
    private function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'lrp_product_restrictions';
    }

    /**
     * Verifica se a tabela existe no banco de dados
     *
     * @return bool
     */
    private function table_exists() {
        global $wpdb;
        $table = $this->get_table_name();
        
        // Cache da verificação para evitar queries repetidas
        static $exists = null;
        
        if ($exists === null) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            )) === $table;
        }
        
        return $exists;
    }

    /**
     * Obtém restrições ativas de um afiliado
     *
     * @param int $affiliate_id
     * @return array
     */
    public function get_active_restrictions($affiliate_id) {
        // Verifica se a tabela existe
        if (!$this->table_exists()) {
            return [];
        }
        
        // Verifica cache
        $cache_key = 'lrp_restrictions_' . $affiliate_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $table = $this->get_table_name();
        $today = current_time('Y-m-d');
        
        $restrictions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE affiliate_id = %d 
             AND start_date <= %s 
             AND (end_date IS NULL OR end_date >= %s)
             ORDER BY restriction_mode, item_type, created_at DESC",
            $affiliate_id,
            $today,
            $today
        ));
        
        // Cache por 1 hora
        set_transient($cache_key, $restrictions ?: [], HOUR_IN_SECONDS);
        
        return $restrictions ?: [];
    }

    /**
     * Obtém o modo de restrição do afiliado
     * 
     * Whitelist tem prioridade sobre blacklist.
     *
     * @param int $affiliate_id
     * @return string|null 'whitelist', 'blacklist' ou null
     */
    public function get_restriction_mode($affiliate_id) {
        $restrictions = $this->get_active_restrictions($affiliate_id);
        
        if (empty($restrictions)) {
            return null;
        }
        
        // Whitelist tem prioridade
        foreach ($restrictions as $r) {
            if ($r->restriction_mode === 'whitelist') {
                return 'whitelist';
            }
        }
        
        return 'blacklist';
    }

    /**
     * Verifica se um produto é permitido para o afiliado
     *
     * @param int $affiliate_id
     * @param int $product_id
     * @return bool
     */
    public function is_product_allowed($affiliate_id, $product_id) {
        $restrictions = $this->get_active_restrictions($affiliate_id);
        
        if (empty($restrictions)) {
            return true; // Sem restrições = tudo permitido
        }
        
        $mode = $this->get_restriction_mode($affiliate_id);
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return true;
        }
        
        // Obtém categorias do produto
        $product_categories = $product->get_category_ids();
        
        // Se for variação, pega categorias do pai
        if ($product->is_type('variation')) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent) {
                $product_categories = $parent->get_category_ids();
            }
        }
        
        // Filtra apenas restrições do modo atual
        $active_restrictions = array_filter($restrictions, function($r) use ($mode) {
            return $r->restriction_mode === $mode;
        });
        
        // Verifica se produto está na lista
        $is_in_list = false;
        
        foreach ($active_restrictions as $restriction) {
            if ($restriction->item_type === 'product') {
                // Verifica produto direto ou pai (se for variação)
                if ($restriction->item_id == $product_id) {
                    $is_in_list = true;
                    break;
                }
                // Se for variação, verifica o pai
                if ($product->is_type('variation') && $restriction->item_id == $product->get_parent_id()) {
                    $is_in_list = true;
                    break;
                }
            } elseif ($restriction->item_type === 'category') {
                if (in_array($restriction->item_id, $product_categories)) {
                    $is_in_list = true;
                    break;
                }
            }
        }
        
        // Whitelist: só permitido se estiver na lista
        // Blacklist: só permitido se NÃO estiver na lista
        if ($mode === 'whitelist') {
            return $is_in_list;
        } else {
            return !$is_in_list;
        }
    }

    /**
     * Filtra produtos do pedido retornando apenas os permitidos
     *
     * @param int $affiliate_id
     * @param WC_Order $order
     * @return array ['allowed_items' => [], 'restricted_items' => [], 'allowed_total' => 0]
     */
    public function filter_order_products($affiliate_id, $order) {
        $allowed_items = [];
        $restricted_items = [];
        $allowed_total = 0;
        
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            // Usa variation_id se existir
            $check_id = $variation_id ? $variation_id : $product_id;
            
            if ($this->is_product_allowed($affiliate_id, $check_id)) {
                $allowed_items[] = [
                    'item_id'      => $item_id,
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id,
                    'name'         => $item->get_name(),
                    'total'        => (float) $item->get_total(),
                    'quantity'     => $item->get_quantity(),
                ];
                $allowed_total += (float) $item->get_total();
            } else {
                $restricted_items[] = [
                    'item_id'      => $item_id,
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id,
                    'name'         => $item->get_name(),
                    'total'        => (float) $item->get_total(),
                    'quantity'     => $item->get_quantity(),
                ];
            }
        }
        
        return [
            'allowed_items'    => $allowed_items,
            'restricted_items' => $restricted_items,
            'allowed_total'    => $allowed_total,
        ];
    }

    /**
     * Adiciona uma restrição
     *
     * @param array $data
     * @return int|WP_Error ID da restrição ou erro
     */
    public function add_restriction($data) {
        global $wpdb;
        
        // Verifica se a tabela existe
        if (!$this->table_exists()) {
            return new WP_Error('table_missing', __('Tabela de restrições não encontrada. Reative o plugin.', 'lab-resumos-parceiros'));
        }
        
        // Validações
        if (empty($data['affiliate_id'])) {
            return new WP_Error('missing_affiliate', __('ID do afiliado é obrigatório.', 'lab-resumos-parceiros'));
        }
        
        if (empty($data['restriction_mode']) || !in_array($data['restriction_mode'], ['blacklist', 'whitelist'])) {
            return new WP_Error('invalid_mode', __('Modo de restrição inválido.', 'lab-resumos-parceiros'));
        }
        
        if (empty($data['item_type']) || !in_array($data['item_type'], ['product', 'category'])) {
            return new WP_Error('invalid_type', __('Tipo de item inválido.', 'lab-resumos-parceiros'));
        }
        
        if (empty($data['item_id'])) {
            return new WP_Error('missing_item', __('ID do item é obrigatório.', 'lab-resumos-parceiros'));
        }
        
        if (empty($data['start_date'])) {
            $data['start_date'] = current_time('Y-m-d');
        }
        
        // Verifica se já existe restrição igual ativa
        $existing = $this->get_existing_restriction(
            $data['affiliate_id'],
            $data['item_type'],
            $data['item_id']
        );
        
        if ($existing) {
            return new WP_Error('duplicate', __('Já existe uma restrição ativa para este item.', 'lab-resumos-parceiros'));
        }
        
        $result = $wpdb->insert(
            $this->get_table_name(),
            [
                'affiliate_id'     => (int) $data['affiliate_id'],
                'restriction_mode' => $data['restriction_mode'],
                'item_type'        => $data['item_type'],
                'item_id'          => (int) $data['item_id'],
                'start_date'       => $data['start_date'],
                'end_date'         => !empty($data['end_date']) ? $data['end_date'] : null,
                'reason'           => !empty($data['reason']) ? sanitize_textarea_field($data['reason']) : null,
                'created_by'       => get_current_user_id(),
                'created_at'       => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s']
        );
        
        if (!$result) {
            return new WP_Error('db_error', __('Erro ao salvar restrição.', 'lab-resumos-parceiros'));
        }
        
        // Limpa cache
        $this->clear_cache($data['affiliate_id']);
        
        // Log
        lrp_log('Restrição de produto adicionada', [
            'affiliate_id'     => $data['affiliate_id'],
            'restriction_mode' => $data['restriction_mode'],
            'item_type'        => $data['item_type'],
            'item_id'          => $data['item_id'],
        ]);
        
        return $wpdb->insert_id;
    }

    /**
     * Remove uma restrição
     *
     * @param int $restriction_id
     * @return bool
     */
    public function remove_restriction($restriction_id) {
        // Verifica se a tabela existe
        if (!$this->table_exists()) {
            return false;
        }
        
        global $wpdb;
        
        // Busca a restrição para obter affiliate_id
        $restriction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_table_name()} WHERE id = %d",
            $restriction_id
        ));
        
        if (!$restriction) {
            return false;
        }
        
        $result = $wpdb->delete(
            $this->get_table_name(),
            ['id' => $restriction_id],
            ['%d']
        );
        
        if ($result) {
            // Limpa cache
            $this->clear_cache($restriction->affiliate_id);
            
            lrp_log('Restrição de produto removida', [
                'restriction_id' => $restriction_id,
                'affiliate_id'   => $restriction->affiliate_id,
            ]);
        }
        
        return $result !== false;
    }

    /**
     * Verifica se já existe restrição ativa para o item
     *
     * @param int $affiliate_id
     * @param string $item_type
     * @param int $item_id
     * @return object|null
     */
    private function get_existing_restriction($affiliate_id, $item_type, $item_id) {
        if (!$this->table_exists()) {
            return null;
        }
        
        global $wpdb;
        $today = current_time('Y-m-d');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_table_name()} 
             WHERE affiliate_id = %d 
             AND item_type = %s 
             AND item_id = %d
             AND start_date <= %s 
             AND (end_date IS NULL OR end_date >= %s)",
            $affiliate_id,
            $item_type,
            $item_id,
            $today,
            $today
        ));
    }

    /**
     * Obtém restrição por ID
     *
     * @param int $restriction_id
     * @return object|null
     */
    public function get_restriction($restriction_id) {
        if (!$this->table_exists()) {
            return null;
        }
        
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_table_name()} WHERE id = %d",
            $restriction_id
        ));
    }

    /**
     * Obtém todas as restrições de um afiliado (incluindo expiradas)
     *
     * @param int $affiliate_id
     * @param bool $only_active
     * @return array
     */
    public function get_all_restrictions($affiliate_id, $only_active = false) {
        // Verifica se a tabela existe
        if (!$this->table_exists()) {
            return [];
        }
        
        global $wpdb;
        $table = $this->get_table_name();
        $today = current_time('Y-m-d');
        
        $sql = "SELECT * FROM $table WHERE affiliate_id = %d";
        $params = [$affiliate_id];
        
        if ($only_active) {
            $sql .= " AND start_date <= %s AND (end_date IS NULL OR end_date >= %s)";
            $params[] = $today;
            $params[] = $today;
        }
        
        $sql .= " ORDER BY restriction_mode, item_type, created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
    }

    /**
     * Obtém nome do item (produto ou categoria)
     *
     * @param string $item_type
     * @param int $item_id
     * @return string
     */
    public function get_item_name($item_type, $item_id) {
        if ($item_type === 'product') {
            $product = wc_get_product($item_id);
            return $product ? $product->get_name() : __('Produto não encontrado', 'lab-resumos-parceiros');
        } else {
            $term = get_term($item_id, 'product_cat');
            return $term && !is_wp_error($term) ? $term->name : __('Categoria não encontrada', 'lab-resumos-parceiros');
        }
    }

    /**
     * Limpa cache de um afiliado
     *
     * @param int $affiliate_id
     */
    public function clear_cache($affiliate_id) {
        delete_transient('lrp_restrictions_' . $affiliate_id);
    }

    /**
     * Obtém resumo das restrições para exibição
     *
     * @param int $affiliate_id
     * @return array
     */
    public function get_restrictions_summary($affiliate_id) {
        $restrictions = $this->get_active_restrictions($affiliate_id);
        $mode = $this->get_restriction_mode($affiliate_id);
        
        if (empty($restrictions) || !$mode) {
            return [
                'has_restrictions' => false,
                'mode'             => null,
                'items'            => [],
            ];
        }
        
        $items = [];
        
        foreach ($restrictions as $r) {
            if ($r->restriction_mode !== $mode) {
                continue; // Ignora restrições do modo oposto
            }
            
            $items[] = [
                'id'         => $r->id,
                'type'       => $r->item_type,
                'item_id'    => $r->item_id,
                'name'       => $this->get_item_name($r->item_type, $r->item_id),
                'start_date' => $r->start_date,
                'end_date'   => $r->end_date,
                'reason'     => $r->reason,
            ];
        }
        
        return [
            'has_restrictions' => true,
            'mode'             => $mode,
            'items'            => $items,
        ];
    }

    /**
     * Verifica se afiliado tem alguma restrição ativa
     *
     * @param int $affiliate_id
     * @return bool
     */
    public function has_restrictions($affiliate_id) {
        $restrictions = $this->get_active_restrictions($affiliate_id);
        return !empty($restrictions);
    }
}

