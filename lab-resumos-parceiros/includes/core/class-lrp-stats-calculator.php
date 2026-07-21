<?php
/**
 * Calculadora de Estatísticas de Afiliados
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Stats_Calculator
 * 
 * Responsável por calcular e cachear métricas enriquecidas de afiliados.
 * Implementa fallbacks robustos e logging detalhado para diagnóstico.
 */
class LRP_Stats_Calculator {

    /**
     * Prefixo das tabelas
     *
     * @var string
     */
    private $prefix;

    /**
     * Instância do wpdb
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Contador de erros na execução atual
     *
     * @var int
     */
    private $error_count = 0;

    /**
     * Log de erros da execução atual
     *
     * @var array
     */
    private $error_log = [];

    /**
     * Construtor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix;
    }

    /**
     * Log interno com contexto
     *
     * @param string $message
     * @param array $context
     * @param string $level info|warning|error
     */
    private function log($message, $context = [], $level = 'info') {
        $prefix = '[LRP Stats Calculator]';
        $formatted = sprintf('%s [%s] %s', $prefix, strtoupper($level), $message);
        
        if (!empty($context)) {
            $formatted .= ' | Context: ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        // Usa sistema de log do plugin se disponível
        if (function_exists('lrp_log')) {
            lrp_log($message, $context, $level);
        }
        
        // Também loga no error_log do WordPress para debug
        if ($level === 'error') {
            error_log($formatted);
            $this->error_count++;
            $this->error_log[] = $message;
        } elseif ($level === 'warning') {
            error_log($formatted);
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($formatted);
        }
    }

    /**
     * Verifica se tabela existe no banco
     *
     * @param string $table_name Nome da tabela (sem prefixo)
     * @return bool
     */
    private function table_exists($table_name) {
        $full_name = $this->prefix . $table_name;
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare("SHOW TABLES LIKE %s", $full_name)
        );
        return $result === $full_name;
    }

    /**
     * Executa query com tratamento de erro
     *
     * @param string $query Query preparada
     * @param string $context Contexto para log
     * @return mixed Resultado ou null em caso de erro
     */
    private function safe_query($query, $context = 'query') {
        $this->wpdb->suppress_errors(true);
        $result = $this->wpdb->get_results($query, ARRAY_A);
        $this->wpdb->suppress_errors(false);
        
        if ($this->wpdb->last_error) {
            $this->log("Erro SQL em {$context}", [
                'error' => $this->wpdb->last_error,
                'query' => substr($query, 0, 200) . '...',
            ], 'error');
            return null;
        }
        
        return $result;
    }

    /**
     * Executa query de linha única com tratamento de erro
     *
     * @param string $query Query preparada
     * @param string $context Contexto para log
     * @return array|null Resultado ou null em caso de erro
     */
    private function safe_query_row($query, $context = 'query') {
        $this->wpdb->suppress_errors(true);
        $result = $this->wpdb->get_row($query, ARRAY_A);
        $this->wpdb->suppress_errors(false);
        
        if ($this->wpdb->last_error) {
            $this->log("Erro SQL em {$context}", [
                'error' => $this->wpdb->last_error,
            ], 'error');
            return null;
        }
        
        return $result;
    }

    /**
     * Calcula estatísticas para todos os afiliados ativos
     *
     * @return array Resumo da execução
     */
    public function calculate_all_affiliates() {
        $start_time = microtime(true);
        $this->error_count = 0;
        $this->error_log = [];
        
        $this->log('Iniciando cálculo de estatísticas para todos os afiliados');
        
        // Verifica se tabela de cache existe
        if (!$this->table_exists('lrp_affiliate_stats_cache')) {
            $this->log('Tabela lrp_affiliate_stats_cache não encontrada. Execute a ativação do plugin.', [], 'error');
            return [
                'success' => false,
                'error' => 'Tabela de cache não existe',
                'affiliates_processed' => 0,
            ];
        }
        
        // Verifica se tabela de afiliados existe
        if (!$this->table_exists('lrp_affiliates')) {
            $this->log('Tabela lrp_affiliates não encontrada. Plugin não está instalado corretamente.', [], 'error');
            return [
                'success' => false,
                'error' => 'Tabela de afiliados não existe',
                'affiliates_processed' => 0,
            ];
        }
        
        // Busca afiliados ativos
        $affiliates = $this->wpdb->get_col("
            SELECT id FROM {$this->prefix}lrp_affiliates 
            WHERE status = 'active'
        ");
        
        if ($this->wpdb->last_error) {
            $this->log('Erro ao buscar afiliados ativos', [
                'error' => $this->wpdb->last_error,
            ], 'error');
            return [
                'success' => false,
                'error' => 'Erro ao buscar afiliados',
                'affiliates_processed' => 0,
            ];
        }
        
        if (empty($affiliates)) {
            $this->log('Nenhum afiliado ativo encontrado', [], 'info');
            return [
                'success' => true,
                'affiliates_processed' => 0,
                'message' => 'Nenhum afiliado ativo',
            ];
        }
        
        $processed = 0;
        $failed = 0;
        
        foreach ($affiliates as $affiliate_id) {
            try {
                $this->calculate_affiliate_stats($affiliate_id, 'month');
                $this->calculate_affiliate_stats($affiliate_id, 'all');
                $processed++;
            } catch (Exception $e) {
                $failed++;
                $this->log("Erro ao calcular stats do afiliado #{$affiliate_id}", [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 'error');
            }
        }
        
        // Calcular rankings após todas as estatísticas
        try {
            $this->calculate_rankings('month');
            $this->calculate_rankings('all');
        } catch (Exception $e) {
            $this->log('Erro ao calcular rankings', [
                'error' => $e->getMessage(),
            ], 'error');
        }
        
        $execution_time = round(microtime(true) - $start_time, 2);
        
        $summary = [
            'success' => $failed === 0,
            'affiliates_processed' => $processed,
            'affiliates_failed' => $failed,
            'total_affiliates' => count($affiliates),
            'execution_time_seconds' => $execution_time,
            'errors_count' => $this->error_count,
        ];
        
        $this->log('Cálculo de estatísticas concluído', $summary, $failed > 0 ? 'warning' : 'info');
        
        return $summary;
    }

    /**
     * Calcula estatísticas de um afiliado específico
     *
     * @param int $affiliate_id
     * @param string $period_type day|week|month|year|all
     * @return bool Sucesso ou falha
     */
    public function calculate_affiliate_stats($affiliate_id, $period_type) {
        $affiliate_id = absint($affiliate_id);
        
        if ($affiliate_id <= 0) {
            $this->log('ID de afiliado inválido', ['affiliate_id' => $affiliate_id], 'error');
            return false;
        }
        
        $period_value = $this->get_period_value($period_type);
        $period_start = $this->get_period_start($period_type);
        
        // Métricas de vendas (crítico)
        $sales_stats = $this->get_sales_stats($affiliate_id, $period_start);
        
        // Métricas de tráfego (crítico)
        $traffic_stats = $this->get_traffic_stats($affiliate_id, $period_start);
        
        // Distribuições (não-crítico - usa fallback vazio se falhar)
        $source_dist = $this->get_source_distribution($affiliate_id, $period_start) ?: [];
        $state_dist = $this->get_state_distribution($affiliate_id, $period_start) ?: [];
        $device_dist = $this->get_device_distribution($affiliate_id, $period_start) ?: [];
        $payment_dist = $this->get_payment_distribution($affiliate_id, $period_start) ?: [];
        $products_dist = $this->get_products_distribution($affiliate_id, $period_start) ?: [];
        
        // Upsert no cache
        $this->wpdb->suppress_errors(true);
        $result = $this->wpdb->replace(
            "{$this->prefix}lrp_affiliate_stats_cache",
            [
                'affiliate_id'         => $affiliate_id,
                'period_type'          => $period_type,
                'period_value'         => $period_value,
                'total_sales'          => $sales_stats['total_sales'],
                'total_revenue'        => $sales_stats['total_revenue'],
                'total_commission'     => $sales_stats['total_commission'],
                'avg_ticket'           => $sales_stats['avg_ticket'],
                'total_clicks'         => $traffic_stats['total_clicks'],
                'unique_visitors'      => $traffic_stats['unique_visitors'],
                'conversion_rate'      => $traffic_stats['conversion_rate'],
                'source_distribution'  => wp_json_encode($source_dist, JSON_UNESCAPED_UNICODE),
                'state_distribution'   => wp_json_encode($state_dist, JSON_UNESCAPED_UNICODE),
                'device_distribution'  => wp_json_encode($device_dist, JSON_UNESCAPED_UNICODE),
                'payment_distribution' => wp_json_encode($payment_dist, JSON_UNESCAPED_UNICODE),
                'products_distribution'=> wp_json_encode($products_dist, JSON_UNESCAPED_UNICODE),
                'calculated_at'        => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%f', '%f', '%f', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        $this->wpdb->suppress_errors(false);
        
        if ($result === false || $this->wpdb->last_error) {
            $this->log("Erro ao salvar cache do afiliado #{$affiliate_id}", [
                'error' => $this->wpdb->last_error,
                'period' => $period_type,
            ], 'error');
            return false;
        }
        
        return true;
    }

    /**
     * Obtém estatísticas de vendas
     *
     * @param int $affiliate_id
     * @param string $period_start
     * @return array
     */
    private function get_sales_stats($affiliate_id, $period_start) {
        $default = [
            'total_sales'      => 0,
            'total_revenue'    => 0.0,
            'total_commission' => 0.0,
            'avg_ticket'       => 0.0,
        ];
        
        // Verifica se tabelas necessárias existem
        if (!$this->table_exists('lrp_referrals')) {
            $this->log('Tabela lrp_referrals não encontrada em get_sales_stats', [], 'warning');
            return $default;
        }
        
        $query = $this->wpdb->prepare("
            SELECT 
                COUNT(r.id) AS total_sales,
                COALESCE(SUM(r.commission_base), 0) AS total_revenue,
                COALESCE(SUM(c.commission_amount), 0) AS total_commission,
                COALESCE(AVG(r.commission_base), 0) AS avg_ticket
            FROM {$this->prefix}lrp_referrals r
            LEFT JOIN {$this->prefix}lrp_commissions c 
                ON r.id = c.referral_id AND c.commission_type = 'direct'
            WHERE r.affiliate_id = %d
                AND r.status = 'approved'
                AND r.created_at >= %s
        ", $affiliate_id, $period_start);
        
        $result = $this->safe_query_row($query, 'get_sales_stats');
        
        if (!$result) {
            return $default;
        }
        
        return [
            'total_sales'      => (int) ($result['total_sales'] ?? 0),
            'total_revenue'    => (float) ($result['total_revenue'] ?? 0),
            'total_commission' => (float) ($result['total_commission'] ?? 0),
            'avg_ticket'       => (float) ($result['avg_ticket'] ?? 0),
        ];
    }

    /**
     * Obtém estatísticas de tráfego
     *
     * @param int $affiliate_id
     * @param string $period_start
     * @return array
     */
    private function get_traffic_stats($affiliate_id, $period_start) {
        $default = [
            'total_clicks'    => 0,
            'unique_visitors' => 0,
            'conversions'     => 0,
            'conversion_rate' => 0.0,
        ];
        
        // Verifica se tabela existe
        if (!$this->table_exists('lrp_visits')) {
            $this->log('Tabela lrp_visits não encontrada em get_traffic_stats', [], 'warning');
            return $default;
        }
        
        $query = $this->wpdb->prepare("
            SELECT 
                COUNT(*) AS total_clicks,
                COUNT(DISTINCT visitor_hash) AS unique_visitors,
                COALESCE(SUM(converted), 0) AS conversions
            FROM {$this->prefix}lrp_visits
            WHERE affiliate_id = %d
                AND created_at >= %s
        ", $affiliate_id, $period_start);
        
        $result = $this->safe_query_row($query, 'get_traffic_stats');
        
        if (!$result) {
            return $default;
        }
        
        $total_clicks = (int) ($result['total_clicks'] ?? 0);
        $conversions = (int) ($result['conversions'] ?? 0);
        
        return [
            'total_clicks'    => $total_clicks,
            'unique_visitors' => (int) ($result['unique_visitors'] ?? 0),
            'conversions'     => $conversions,
            'conversion_rate' => $total_clicks > 0 
                ? round(($conversions / $total_clicks) * 100, 2)
                : 0,
        ];
    }

    /**
     * Obtém distribuição por fonte de tráfego
     *
     * @param int $affiliate_id
     * @param string $period_start
     * @return array
     */
    private function get_source_distribution($affiliate_id, $period_start) {
        if (!$this->table_exists('lrp_visits')) {
            return [];
        }
        
        $query = $this->wpdb->prepare("
            SELECT 
                COALESCE(NULLIF(traffic_source, ''), 'Direct') AS source,
                COUNT(*) AS clicks,
                COALESCE(SUM(converted), 0) AS conversions
            FROM {$this->prefix}lrp_visits
            WHERE affiliate_id = %d
                AND created_at >= %s
            GROUP BY source
            ORDER BY clicks DESC
            LIMIT 10
        ", $affiliate_id, $period_start);
        
        $results = $this->safe_query($query, 'get_source_distribution');
        
        return $results ?: [];
    }

    /**
     * Obtém distribuição por estado (UF)
     *
     * @param int $affiliate_id
     * @param string $period_start
     * @return array
     */
    private function get_state_distribution($affiliate_id, $period_start) {
        if (!$this->table_exists('lrp_referrals')) {
            return [];
        }
        
        $hpos_enabled = $this->is_hpos_enabled();
        
        if ($hpos_enabled && $this->table_exists('wc_orders') && $this->table_exists('wc_order_addresses')) {
            $query = $this->wpdb->prepare("
                SELECT 
                    COALESCE(addr.state, 'N/A') AS uf,
                    COUNT(DISTINCT r.order_id) AS sales,
                    COALESCE(SUM(o.total_amount), 0) AS revenue,
                    COALESCE(AVG(o.total_amount), 0) AS avg_ticket
                FROM {$this->prefix}lrp_referrals r
                INNER JOIN {$this->prefix}wc_orders o ON r.order_id = o.id
                LEFT JOIN {$this->prefix}wc_order_addresses addr 
                    ON o.id = addr.order_id AND addr.address_type = 'billing'
                WHERE r.affiliate_id = %d
                    AND r.status = 'approved'
                    AND r.created_at >= %s
                GROUP BY addr.state
                ORDER BY sales DESC
                LIMIT 15
            ", $affiliate_id, $period_start);
        } else {
            // Fallback para estrutura tradicional
            $query = $this->wpdb->prepare("
                SELECT 
                    COALESCE(pm.meta_value, 'N/A') AS uf,
                    COUNT(DISTINCT r.order_id) AS sales,
                    COALESCE(SUM(r.commission_base), 0) AS revenue,
                    COALESCE(AVG(r.commission_base), 0) AS avg_ticket
                FROM {$this->prefix}lrp_referrals r
                LEFT JOIN {$this->prefix}postmeta pm 
                    ON r.order_id = pm.post_id AND pm.meta_key = '_billing_state'
                WHERE r.affiliate_id = %d
                    AND r.status = 'approved'
                    AND r.created_at >= %s
                GROUP BY pm.meta_value
                ORDER BY sales DESC
                LIMIT 15
            ", $affiliate_id, $period_start);
        }
        
        $results = $this->safe_query($query, 'get_state_distribution');
        
        return $results ?: [];
    }

    /**
     * Obtém distribuição por dispositivo
     *
     * @param int $affiliate_id
     * @param string $period_start
     * @return array
     */
    private function get_device_distribution($affiliate_id, $period_start) {
        // Primeiro tenta via Order Attribution do WooCommerce
        $hpos_enabled = $this->is_hpos_enabled();
        $results = null;
        
        if ($hpos_enabled && $this->table_exists('wc_orders') && $this->table_exists('wc_orders_meta')) {
            $query = $this->wpdb->prepare("
                SELECT 
                    COALESCE(om.meta_value, 'Unknown') AS device,
                    COUNT(*) AS sales
                FROM {$this->prefix}lrp_referrals r
                INNER JOIN {$this->prefix}wc_orders o ON r.order_id = o.id
                LEFT JOIN {$this->prefix}wc_orders_meta om 
                    ON o.id = om.order_id 
                    AND om.meta_key = '_wc_order_attribution_device_type'
                WHERE r.affiliate_id = %d
                    AND r.status = 'approved'
                    AND r.created_at >= %s
                GROUP BY device
                ORDER BY sales DESC
            ", $affiliate_id, $period_start);
            
            $results = $this->safe_query($query, 'get_device_distribution_hpos');
        } elseif ($this->table_exists('lrp_referrals')) {
            $query = $this->wpdb->prepare("
                SELECT 
                    COALESCE(pm.meta_value, 'Unknown') AS device,
                    COUNT(*) AS sales
                FROM {$this->prefix}lrp_referrals r
                LEFT JOIN {$this->prefix}postmeta pm 
                    ON r.order_id = pm.post_id 
                    AND pm.meta_key = '_wc_order_attribution_device_type'
                WHERE r.affiliate_id = %d
                    AND r.status = 'approved'
                    AND r.created_at >= %s
                GROUP BY pm.meta_value
                ORDER BY sales DESC
            ", $affiliate_id, $period_start);
            
            $results = $this->safe_query($query, 'get_device_distribution_legacy');
        }
        
        // Se não tem dados de Order Attribution, usa da tabela visits como fallback
        if (empty($results) || (count($results) === 1 && ($results[0]['device'] ?? '') === 'Unknown')) {
            if ($this->table_exists('lrp_visits')) {
                $query = $this->wpdb->prepare("
                    SELECT 
                        COALESCE(device_type, 'Unknown') AS device,
                        COUNT(*) AS clicks
                    FROM {$this->prefix}lrp_visits
                    WHERE affiliate_id = %d
                        AND created_at >= %s
                        AND device_type IS NOT NULL
                        AND device_type != ''
                    GROUP BY device_type
                    ORDER BY clicks DESC
                ", $affiliate_id, $period_start);
                
                $results = $this->safe_query($query, 'get_device_distribution_visits');
            }
        }
        
        return $results ?: [];
    }

    /**
     * Obtém distribuição por forma de pagamento
     *
     * @param int $affiliate_id
     * @param string $period_start
     * @return array
     */
    private function get_payment_distribution($affiliate_id, $period_start) {
        if (!$this->table_exists('lrp_referrals')) {
            return [];
        }
        
        $hpos_enabled = $this->is_hpos_enabled();
        
        if ($hpos_enabled && $this->table_exists('wc_orders')) {
            $query = $this->wpdb->prepare("
                SELECT 
                    CASE 
                        WHEN o.payment_method LIKE '%%pix%%' THEN 'PIX'
                        WHEN o.payment_method LIKE '%%credit%%' 
                            OR o.payment_method LIKE '%%card%%' THEN 'Cartão'
                        WHEN o.payment_method LIKE '%%boleto%%' THEN 'Boleto'
                        ELSE 'Outro'
                    END AS payment_type,
                    COUNT(*) AS sales,
                    COALESCE(SUM(o.total_amount), 0) AS revenue
                FROM {$this->prefix}lrp_referrals r
                INNER JOIN {$this->prefix}wc_orders o ON r.order_id = o.id
                WHERE r.affiliate_id = %d
                    AND r.status = 'approved'
                    AND r.created_at >= %s
                GROUP BY payment_type
                ORDER BY sales DESC
            ", $affiliate_id, $period_start);
        } else {
            $query = $this->wpdb->prepare("
                SELECT 
                    CASE 
                        WHEN pm.meta_value LIKE '%%pix%%' THEN 'PIX'
                        WHEN pm.meta_value LIKE '%%credit%%' 
                            OR pm.meta_value LIKE '%%card%%' THEN 'Cartão'
                        WHEN pm.meta_value LIKE '%%boleto%%' THEN 'Boleto'
                        ELSE 'Outro'
                    END AS payment_type,
                    COUNT(*) AS sales,
                    COALESCE(SUM(r.commission_base), 0) AS revenue
                FROM {$this->prefix}lrp_referrals r
                LEFT JOIN {$this->prefix}postmeta pm 
                    ON r.order_id = pm.post_id AND pm.meta_key = '_payment_method'
                WHERE r.affiliate_id = %d
                    AND r.status = 'approved'
                    AND r.created_at >= %s
                GROUP BY payment_type
                ORDER BY sales DESC
            ", $affiliate_id, $period_start);
        }
        
        $results = $this->safe_query($query, 'get_payment_distribution');
        
        return $results ?: [];
    }

    /**
     * Obtém distribuição por produtos
     *
     * @param int $affiliate_id
     * @param string $period_start
     * @return array
     */
    private function get_products_distribution($affiliate_id, $period_start) {
        if (!$this->table_exists('lrp_referrals')) {
            return [];
        }
        
        // Tenta primeiro com WooCommerce Analytics (mais eficiente)
        if ($this->table_exists('wc_order_product_lookup')) {
            $query = $this->wpdb->prepare("
                SELECT 
                    oi.product_id,
                    p.post_title AS product_name,
                    COUNT(*) AS quantity,
                    COALESCE(SUM(oi.product_net_revenue), 0) AS revenue
                FROM {$this->prefix}lrp_referrals r
                INNER JOIN {$this->prefix}wc_order_product_lookup oi ON r.order_id = oi.order_id
                INNER JOIN {$this->prefix}posts p ON oi.product_id = p.ID
                WHERE r.affiliate_id = %d
                    AND r.status = 'approved'
                    AND r.created_at >= %s
                GROUP BY oi.product_id, p.post_title
                ORDER BY quantity DESC
                LIMIT 10
            ", $affiliate_id, $period_start);
            
            $results = $this->safe_query($query, 'get_products_distribution_analytics');
            
            if ($results !== null) {
                return $results;
            }
            
            $this->log('Fallback para order_items após falha em wc_order_product_lookup', [], 'warning');
        }
        
        // Fallback: usa order items diretamente
        if ($this->table_exists('woocommerce_order_items') && $this->table_exists('woocommerce_order_itemmeta')) {
            $query = $this->wpdb->prepare("
                SELECT 
                    oim.meta_value AS product_id,
                    p.post_title AS product_name,
                    COUNT(*) AS quantity,
                    COALESCE(SUM(CAST(oim2.meta_value AS DECIMAL(15,2))), 0) AS revenue
                FROM {$this->prefix}lrp_referrals r
                INNER JOIN {$this->prefix}woocommerce_order_items oi ON r.order_id = oi.order_id
                INNER JOIN {$this->prefix}woocommerce_order_itemmeta oim 
                    ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'
                LEFT JOIN {$this->prefix}woocommerce_order_itemmeta oim2 
                    ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_line_total'
                INNER JOIN {$this->prefix}posts p ON oim.meta_value = p.ID
                WHERE r.affiliate_id = %d
                    AND r.status = 'approved'
                    AND r.created_at >= %s
                    AND oi.order_item_type = 'line_item'
                GROUP BY oim.meta_value, p.post_title
                ORDER BY quantity DESC
                LIMIT 10
            ", $affiliate_id, $period_start);
            
            $results = $this->safe_query($query, 'get_products_distribution_fallback');
            
            return $results ?: [];
        }
        
        $this->log('Nenhuma fonte de dados de produtos disponível', [], 'warning');
        return [];
    }

    /**
     * Calcula rankings de todos os afiliados
     *
     * @param string $period_type
     * @return bool Sucesso
     */
    private function calculate_rankings($period_type) {
        $period_value = $this->get_period_value($period_type);
        
        $query = $this->wpdb->prepare("
            SELECT affiliate_id, total_sales, total_revenue
            FROM {$this->prefix}lrp_affiliate_stats_cache
            WHERE period_type = %s AND period_value = %s
            ORDER BY total_sales DESC, total_revenue DESC
        ", $period_type, $period_value);
        
        $rankings = $this->safe_query($query, 'calculate_rankings');
        
        if ($rankings === null) {
            return false;
        }
        
        $total = count($rankings);
        
        if ($total === 0) {
            return true;
        }
        
        foreach ($rankings as $position => $row) {
            $rank_position = $position + 1;
            $percentile = $total > 0 ? round((1 - ($rank_position / $total)) * 100) : 0;
            
            $this->wpdb->update(
                "{$this->prefix}lrp_affiliate_stats_cache",
                [
                    'rank_position'   => $rank_position,
                    'rank_percentile' => max(0, min(100, $percentile)),
                ],
                [
                    'affiliate_id' => $row['affiliate_id'],
                    'period_type'  => $period_type,
                    'period_value' => $period_value,
                ],
                ['%d', '%d'],
                ['%d', '%s', '%s']
            );
            
            if ($this->wpdb->last_error) {
                $this->log("Erro ao atualizar ranking do afiliado #{$row['affiliate_id']}", [
                    'error' => $this->wpdb->last_error,
                ], 'warning');
            }
        }
        
        return true;
    }

    /**
     * Verifica se HPOS está habilitado
     *
     * @return bool
     */
    private function is_hpos_enabled() {
        try {
            if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
                return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            }
        } catch (Exception $e) {
            $this->log('Erro ao verificar HPOS', ['error' => $e->getMessage()], 'warning');
        }
        return false;
    }

    /**
     * Obtém valor do período
     *
     * @param string $period_type
     * @return string
     */
    private function get_period_value($period_type) {
        switch ($period_type) {
            case 'day':   return date('Y-m-d');
            case 'week':  return date('Y-W');
            case 'month': return date('Y-m');
            case 'year':  return date('Y');
            default:      return 'all';
        }
    }

    /**
     * Obtém data de início do período
     *
     * @param string $period_type
     * @return string
     */
    private function get_period_start($period_type) {
        switch ($period_type) {
            case 'day':   return date('Y-m-d 00:00:00');
            case 'week':  return date('Y-m-d', strtotime('monday this week'));
            case 'month': return date('Y-m-01 00:00:00');
            case 'year':  return date('Y-01-01 00:00:00');
            default:      return '1970-01-01 00:00:00';
        }
    }

    /**
     * Obtém estatísticas cacheadas de um afiliado
     *
     * @param int $affiliate_id
     * @param string $period_type
     * @return object|null
     */
    public static function get_cached_stats($affiliate_id, $period_type = 'month') {
        global $wpdb;
        
        $affiliate_id = absint($affiliate_id);
        if ($affiliate_id <= 0) {
            return null;
        }
        
        // Verifica se tabela existe
        $table = $wpdb->prefix . 'lrp_affiliate_stats_cache';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        
        if (!$table_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[LRP Stats Calculator] Tabela de cache não existe ao buscar stats');
            }
            return null;
        }
        
        $calculator = new self();
        $period_value = $calculator->get_period_value($period_type);
        
        $wpdb->suppress_errors(true);
        $result = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$table}
            WHERE affiliate_id = %d 
                AND period_type = %s 
                AND period_value = %s
        ", $affiliate_id, $period_type, $period_value));
        $wpdb->suppress_errors(false);
        
        if ($wpdb->last_error) {
            error_log('[LRP Stats Calculator] Erro ao buscar cache: ' . $wpdb->last_error);
            return null;
        }
        
        return $result;
    }

    /**
     * Força recálculo das estatísticas de um afiliado
     *
     * @param int $affiliate_id
     * @return bool Sucesso
     */
    public static function recalculate_affiliate($affiliate_id) {
        $calculator = new self();
        
        $success_month = $calculator->calculate_affiliate_stats($affiliate_id, 'month');
        $success_all = $calculator->calculate_affiliate_stats($affiliate_id, 'all');
        
        if ($success_month && $success_all) {
            $calculator->log("Estatísticas recalculadas para afiliado #{$affiliate_id}", [], 'info');
        }
        
        return $success_month && $success_all;
    }
    
    /**
     * Obtém resumo de erros da última execução
     *
     * @return array
     */
    public function get_error_summary() {
        return [
            'count' => $this->error_count,
            'errors' => $this->error_log,
        ];
    }
}
