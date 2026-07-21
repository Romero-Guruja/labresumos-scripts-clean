<?php
/**
 * Sistema de Ranking de Afiliados
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Ranking
 * 
 * Responsável por calcular rankings e comparativos de afiliados.
 * NUNCA expõe dados absolutos de outros afiliados (privacidade).
 * Implementa fallbacks robustos e logging detalhado.
 */
class LRP_Ranking {

    /**
     * Instância única
     *
     * @var LRP_Ranking|null
     */
    private static $instance = null;

    /**
     * Cache local de rankings (evita queries repetidas)
     *
     * @var array
     */
    private $cache = [];

    /**
     * Retorna instância única
     *
     * @return LRP_Ranking
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
    private function __construct() {}

    /**
     * Log interno com contexto
     *
     * @param string $message
     * @param array $context
     * @param string $level
     */
    private function log($message, $context = [], $level = 'info') {
        $prefix = '[LRP Ranking]';
        $formatted = sprintf('%s [%s] %s', $prefix, strtoupper($level), $message);
        
        if (!empty($context)) {
            $formatted .= ' | ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        if (function_exists('lrp_log')) {
            lrp_log($message, $context, $level);
        }
        
        if ($level === 'error' || ($level === 'warning' && defined('WP_DEBUG') && WP_DEBUG)) {
            error_log($formatted);
        }
    }

    /**
     * Verifica se tabelas necessárias existem
     *
     * @return bool
     */
    private function tables_exist() {
        global $wpdb;
        
        $tables = ['lrp_affiliates', 'lrp_referrals', 'lrp_commissions'];
        
        foreach ($tables as $table) {
            $full_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_name));
            if (!$exists) {
                $this->log("Tabela {$table} não encontrada", [], 'warning');
                return false;
            }
        }
        
        return true;
    }

    /**
     * Obtém posição e percentil do afiliado
     * Retorna apenas dados relativos, nunca absolutos
     *
     * @param int $affiliate_id
     * @param string $metric sales|commission|ticket
     * @param string $period week|month|year|all
     * @return array|null
     */
    public function get_affiliate_ranking($affiliate_id, $metric = 'sales', $period = 'month') {
        global $wpdb;
        
        $affiliate_id = absint($affiliate_id);
        
        if ($affiliate_id <= 0) {
            $this->log('ID de afiliado inválido para ranking', ['id' => $affiliate_id], 'warning');
            return null;
        }
        
        // Verifica cache local
        $cache_key = "{$affiliate_id}_{$metric}_{$period}";
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        // Verifica tabelas
        if (!$this->tables_exist()) {
            return $this->get_default_ranking();
        }
        
        $period_start = $this->get_period_start($period);
        $order_column = $this->get_order_column($metric);
        
        // Busca ranking de todos os afiliados ativos
        $wpdb->suppress_errors(true);
        $rankings = $wpdb->get_results($wpdb->prepare("
            SELECT 
                a.id,
                COUNT(r.id) AS total_sales,
                COALESCE(SUM(c.commission_amount), 0) AS total_commission,
                COALESCE(AVG(r.commission_base), 0) AS avg_ticket
            FROM {$wpdb->prefix}lrp_affiliates a
            LEFT JOIN {$wpdb->prefix}lrp_referrals r 
                ON a.id = r.affiliate_id 
                AND r.status = 'approved'
                AND r.created_at >= %s
            LEFT JOIN {$wpdb->prefix}lrp_commissions c 
                ON r.id = c.referral_id 
                AND c.commission_type = 'direct'
            WHERE a.status = 'active'
            GROUP BY a.id
            ORDER BY {$order_column} DESC
        ", $period_start));
        $wpdb->suppress_errors(false);
        
        if ($wpdb->last_error) {
            $this->log('Erro SQL ao calcular ranking', [
                'error' => $wpdb->last_error,
                'affiliate_id' => $affiliate_id,
            ], 'error');
            return $this->get_default_ranking();
        }
        
        if (empty($rankings)) {
            $this->log('Nenhum afiliado encontrado para ranking', [], 'info');
            return $this->get_default_ranking();
        }
        
        $total_affiliates = count($rankings);
        $position = 0;
        $affiliate_data = null;
        
        foreach ($rankings as $index => $row) {
            if ((int) $row->id === $affiliate_id) {
                $position = $index + 1;
                $affiliate_data = $row;
                break;
            }
        }
        
        if (!$affiliate_data) {
            $this->log('Afiliado não encontrado nos rankings', [
                'affiliate_id' => $affiliate_id,
            ], 'warning');
            return $this->get_default_ranking();
        }
        
        // Calcular média (sem expor o valor absoluto)
        $metric_key = $this->get_metric_key($metric);
        $sum_metric = 0;
        
        foreach ($rankings as $row) {
            $sum_metric += (float) ($row->$metric_key ?? 0);
        }
        
        $avg_metric = $total_affiliates > 0 ? $sum_metric / $total_affiliates : 0;
        
        $affiliate_value = (float) ($affiliate_data->$metric_key ?? 0);
        $diff_from_avg = $avg_metric > 0 
            ? (($affiliate_value - $avg_metric) / $avg_metric) * 100 
            : 0;
        
        // Busca posição do mês anterior para evolução
        $previous_position = $this->get_previous_position($affiliate_id, $metric);
        $position_change = $previous_position > 0 ? $previous_position - $position : 0;
        
        $result = [
            'position'              => $position,
            'total_affiliates'      => $total_affiliates,
            'percentile'            => round((1 - ($position / max($total_affiliates, 1))) * 100),
            'diff_from_average'     => round($diff_from_avg, 1),
            'diff_label'            => $this->get_comparison_label($diff_from_avg),
            'position_change'       => $position_change,
            'position_change_label' => $this->get_position_change_label($position_change),
        ];
        
        // Cache local
        $this->cache[$cache_key] = $result;
        
        return $result;
    }

    /**
     * Retorna ranking padrão quando não há dados
     *
     * @return array
     */
    private function get_default_ranking() {
        return [
            'position'              => 0,
            'total_affiliates'      => 0,
            'percentile'            => 0,
            'diff_from_average'     => 0,
            'diff_label'            => __('Sem dados suficientes', 'lab-resumos-parceiros'),
            'position_change'       => 0,
            'position_change_label' => __('Sem histórico', 'lab-resumos-parceiros'),
        ];
    }

    /**
     * Obtém comparativo do afiliado com a média para múltiplas métricas
     *
     * @param int $affiliate_id
     * @param string $period
     * @return array
     */
    public function get_comparison_summary($affiliate_id, $period = 'month') {
        return [
            'sales'      => $this->get_affiliate_ranking($affiliate_id, 'sales', $period),
            'commission' => $this->get_affiliate_ranking($affiliate_id, 'commission', $period),
            'ticket'     => $this->get_affiliate_ranking($affiliate_id, 'ticket', $period),
        ];
    }

    /**
     * Obtém dados de tráfego comparativos
     *
     * @param int $affiliate_id
     * @param string $period
     * @return array
     */
    public function get_traffic_comparison($affiliate_id, $period = 'month') {
        global $wpdb;
        
        $affiliate_id = absint($affiliate_id);
        
        $default = [
            'clicks' => ['value' => 0, 'diff' => 0, 'label' => __('Sem dados', 'lab-resumos-parceiros')],
            'visitors' => ['value' => 0, 'diff' => 0],
            'conversion' => ['value' => 0, 'diff' => 0, 'label' => __('Sem dados', 'lab-resumos-parceiros')],
        ];
        
        // Verifica tabela de visitas
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'lrp_visits')
        );
        
        if (!$table_exists) {
            $this->log('Tabela lrp_visits não existe para comparação de tráfego', [], 'warning');
            return $default;
        }
        
        $period_start = $this->get_period_start($period);
        
        // Estatísticas de todos os afiliados ativos
        $wpdb->suppress_errors(true);
        $all_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                AVG(stats.total_clicks) AS avg_clicks,
                AVG(stats.unique_visitors) AS avg_visitors,
                AVG(stats.conversion_rate) AS avg_conversion
            FROM (
                SELECT 
                    v.affiliate_id,
                    COUNT(*) AS total_clicks,
                    COUNT(DISTINCT v.visitor_hash) AS unique_visitors,
                    CASE WHEN COUNT(*) > 0 
                        THEN (SUM(v.converted) / COUNT(*)) * 100 
                        ELSE 0 
                    END AS conversion_rate
                FROM {$wpdb->prefix}lrp_visits v
                INNER JOIN {$wpdb->prefix}lrp_affiliates a ON v.affiliate_id = a.id
                WHERE a.status = 'active'
                    AND v.created_at >= %s
                GROUP BY v.affiliate_id
            ) AS stats
        ", $period_start));
        $wpdb->suppress_errors(false);
        
        if ($wpdb->last_error) {
            $this->log('Erro ao buscar estatísticas médias de tráfego', [
                'error' => $wpdb->last_error,
            ], 'error');
            return $default;
        }
        
        // Estatísticas do afiliado
        $wpdb->suppress_errors(true);
        $affiliate_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) AS total_clicks,
                COUNT(DISTINCT visitor_hash) AS unique_visitors,
                CASE WHEN COUNT(*) > 0 
                    THEN (SUM(converted) / COUNT(*)) * 100 
                    ELSE 0 
                END AS conversion_rate
            FROM {$wpdb->prefix}lrp_visits
            WHERE affiliate_id = %d
                AND created_at >= %s
        ", $affiliate_id, $period_start));
        $wpdb->suppress_errors(false);
        
        if ($wpdb->last_error || !$affiliate_stats) {
            $this->log('Erro ao buscar estatísticas de tráfego do afiliado', [
                'error' => $wpdb->last_error,
                'affiliate_id' => $affiliate_id,
            ], 'error');
            return $default;
        }
        
        $avg_clicks = (float) ($all_stats->avg_clicks ?? 0);
        $avg_visitors = (float) ($all_stats->avg_visitors ?? 0);
        $avg_conversion = (float) ($all_stats->avg_conversion ?? 0);
        
        $aff_clicks = (int) ($affiliate_stats->total_clicks ?? 0);
        $aff_visitors = (int) ($affiliate_stats->unique_visitors ?? 0);
        $aff_conversion = (float) ($affiliate_stats->conversion_rate ?? 0);
        
        $clicks_diff = $avg_clicks > 0 ? (($aff_clicks - $avg_clicks) / $avg_clicks) * 100 : 0;
        $visitors_diff = $avg_visitors > 0 ? (($aff_visitors - $avg_visitors) / $avg_visitors) * 100 : 0;
        $conversion_diff = $avg_conversion > 0 ? (($aff_conversion - $avg_conversion) / $avg_conversion) * 100 : 0;
        
        return [
            'clicks' => [
                'value' => $aff_clicks,
                'diff'  => round($clicks_diff, 1),
                'label' => $this->get_comparison_label($clicks_diff),
            ],
            'visitors' => [
                'value' => $aff_visitors,
                'diff'  => round($visitors_diff, 1),
            ],
            'conversion' => [
                'value' => round($aff_conversion, 2),
                'diff'  => round($conversion_diff, 1),
                'label' => $this->get_comparison_label($conversion_diff),
            ],
        ];
    }

    /**
     * Obtém posição do mês anterior
     *
     * @param int $affiliate_id
     * @param string $metric
     * @return int
     */
    private function get_previous_position($affiliate_id, $metric) {
        global $wpdb;
        
        $previous_period_start = date('Y-m-01 00:00:00', strtotime('-1 month'));
        $previous_period_end = date('Y-m-t 23:59:59', strtotime('-1 month'));
        
        $order_column = $this->get_order_column($metric);
        
        $wpdb->suppress_errors(true);
        $rankings = $wpdb->get_results($wpdb->prepare("
            SELECT 
                a.id,
                COUNT(r.id) AS total_sales,
                COALESCE(SUM(c.commission_amount), 0) AS total_commission,
                COALESCE(AVG(r.commission_base), 0) AS avg_ticket
            FROM {$wpdb->prefix}lrp_affiliates a
            LEFT JOIN {$wpdb->prefix}lrp_referrals r 
                ON a.id = r.affiliate_id 
                AND r.status = 'approved'
                AND r.created_at BETWEEN %s AND %s
            LEFT JOIN {$wpdb->prefix}lrp_commissions c 
                ON r.id = c.referral_id 
                AND c.commission_type = 'direct'
            WHERE a.status = 'active'
            GROUP BY a.id
            ORDER BY {$order_column} DESC
        ", $previous_period_start, $previous_period_end));
        $wpdb->suppress_errors(false);
        
        if ($wpdb->last_error || empty($rankings)) {
            return 0;
        }
        
        foreach ($rankings as $index => $row) {
            if ((int) $row->id === $affiliate_id) {
                return $index + 1;
            }
        }
        
        return 0;
    }

    /**
     * Obtém coluna para ordenação baseada na métrica
     *
     * @param string $metric
     * @return string
     */
    private function get_order_column($metric) {
        $columns = [
            'sales'      => 'total_sales',
            'commission' => 'total_commission',
            'ticket'     => 'avg_ticket',
        ];
        
        return $columns[$metric] ?? 'total_sales';
    }

    /**
     * Obtém chave da métrica para acesso ao objeto
     *
     * @param string $metric
     * @return string
     */
    private function get_metric_key($metric) {
        $keys = [
            'sales'      => 'total_sales',
            'commission' => 'total_commission',
            'ticket'     => 'avg_ticket',
        ];
        
        return $keys[$metric] ?? 'total_sales';
    }

    /**
     * Obtém label de comparação com média
     *
     * @param float $diff
     * @return string
     */
    private function get_comparison_label($diff) {
        if ($diff > 50)  return __('Muito acima da média', 'lab-resumos-parceiros');
        if ($diff > 20)  return __('Acima da média', 'lab-resumos-parceiros');
        if ($diff > -20) return __('Na média', 'lab-resumos-parceiros');
        if ($diff > -50) return __('Abaixo da média', 'lab-resumos-parceiros');
        return __('Muito abaixo da média', 'lab-resumos-parceiros');
    }

    /**
     * Obtém label de mudança de posição
     *
     * @param int $change
     * @return string
     */
    private function get_position_change_label($change) {
        if ($change > 0) {
            return sprintf(__('Subiu %d posição(ões)', 'lab-resumos-parceiros'), $change);
        } elseif ($change < 0) {
            return sprintf(__('Desceu %d posição(ões)', 'lab-resumos-parceiros'), abs($change));
        }
        return __('Manteve posição', 'lab-resumos-parceiros');
    }

    /**
     * Obtém data de início do período
     *
     * @param string $period
     * @return string
     */
    private function get_period_start($period) {
        switch ($period) {
            case 'week':  return date('Y-m-d', strtotime('-7 days'));
            case 'month': return date('Y-m-01');
            case 'year':  return date('Y-01-01');
            default:      return date('Y-m-01');
        }
    }
    
    /**
     * Limpa cache local
     */
    public function clear_cache() {
        $this->cache = [];
    }
}
