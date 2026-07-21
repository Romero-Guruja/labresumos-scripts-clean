<?php
/**
 * Lógica de Rede Multi-Nível
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Network
 * 
 * Gerencia estrutura de rede e comissões multi-nível.
 */
class LRP_Network {

    /**
     * Instância única
     *
     * @var LRP_Network|null
     */
    private static $instance = null;

    /**
     * Retorna instância única
     *
     * @return LRP_Network
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
        // Distribui comissões quando referral é criado
        add_action('lrp_referral_created', [$this, 'distribute_multilevel_commissions']);
        
        // Notifica sponsors quando sub-afiliado é aprovado
        add_action('lrp_affiliate_approved', [$this, 'notify_sponsor_new_affiliate']);
    }

    /**
     * Obtém sponsor de um afiliado
     *
     * @param int $affiliate_id
     * @return LRP_Affiliate|null
     */
    public function get_sponsor($affiliate_id) {
        $affiliate = new LRP_Affiliate($affiliate_id);
        return $affiliate->get_sponsor();
    }

    /**
     * Obtém downline (sub-afiliados diretos)
     *
     * @param int $affiliate_id
     * @param int $levels Níveis a buscar (1 = diretos, 2 = inclui nível 3)
     * @return array
     */
    public function get_downline($affiliate_id, $levels = 2) {
        global $wpdb;
        
        $downline = [];
        
        // Nível 2 (diretos)
        $level_2 = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lrp_affiliates 
             WHERE sponsor_id = %d AND status = 'active'",
            $affiliate_id
        ));
        
        foreach ($level_2 as $row) {
            $downline[] = [
                'affiliate' => new LRP_Affiliate($row),
                'level'     => 2,
            ];
        }
        
        // Nível 3 (se solicitado)
        if ($levels >= 2) {
            foreach ($level_2 as $l2) {
                $level_3 = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}lrp_affiliates 
                     WHERE sponsor_id = %d AND status = 'active'",
                    $l2->id
                ));
                
                foreach ($level_3 as $row) {
                    $downline[] = [
                        'affiliate' => new LRP_Affiliate($row),
                        'level'     => 3,
                    ];
                }
            }
        }
        
        return $downline;
    }

    /**
     * Obtém árvore de downline formatada
     *
     * @param int $affiliate_id
     * @param int $max_levels
     * @return array
     */
    public function get_downline_tree($affiliate_id, $max_levels = 2) {
        global $wpdb;
        
        $tree = [];
        
        // Busca sub-afiliados diretos
        $directs = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name, u.user_email
             FROM {$wpdb->prefix}lrp_affiliates a
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE a.sponsor_id = %d AND a.status = 'active'
             ORDER BY a.created_at DESC",
            $affiliate_id
        ));
        
        foreach ($directs as $direct) {
            $item = [
                'id'           => $direct->id,
                'name'         => $direct->display_name,
                'email'        => $direct->user_email,
                'level'        => 2,
                'created_at'   => $direct->created_at,
                'total_sales'  => (int) $direct->total_sales,
                'total_revenue'=> (float) $direct->total_revenue,
                'children'     => [],
            ];
            
            // Busca nível 3 se permitido
            if ($max_levels >= 2) {
                $sub_directs = $wpdb->get_results($wpdb->prepare(
                    "SELECT a.*, u.display_name, u.user_email
                     FROM {$wpdb->prefix}lrp_affiliates a
                     JOIN {$wpdb->users} u ON a.user_id = u.ID
                     WHERE a.sponsor_id = %d AND a.status = 'active'
                     ORDER BY a.created_at DESC",
                    $direct->id
                ));
                
                foreach ($sub_directs as $sub) {
                    $item['children'][] = [
                        'id'           => $sub->id,
                        'name'         => $sub->display_name,
                        'email'        => $sub->user_email,
                        'level'        => 3,
                        'created_at'   => $sub->created_at,
                        'total_sales'  => (int) $sub->total_sales,
                        'total_revenue'=> (float) $sub->total_revenue,
                    ];
                }
            }
            
            $tree[] = $item;
        }
        
        return $tree;
    }

    /**
     * Obtém estatísticas da rede
     *
     * @param int $affiliate_id
     * @return array
     */
    public function get_network_stats($affiliate_id) {
        global $wpdb;
        
        // Conta sub-afiliados
        $total_affiliates = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_affiliates 
             WHERE sponsor_id = %d AND status = 'active'",
            $affiliate_id
        ));
        
        // Conta nível 3
        $level_3_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_affiliates a2
             WHERE a2.sponsor_id IN (
                SELECT id FROM {$wpdb->prefix}lrp_affiliates 
                WHERE sponsor_id = %d AND status = 'active'
             ) AND a2.status = 'active'",
            $affiliate_id
        ));
        
        // Soma vendas e comissões da rede
        $network_data = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COALESCE(SUM(r.commission_base), 0) as total_revenue,
                COALESCE(SUM(c.commission_amount), 0) as total_commissions,
                COUNT(DISTINCT r.id) as total_sales
             FROM {$wpdb->prefix}lrp_commissions c
             JOIN {$wpdb->prefix}lrp_referrals r ON c.referral_id = r.id
             WHERE c.affiliate_id = %d 
             AND c.commission_type IN ('level_2', 'level_3')
             AND c.status IN ('approved', 'paid')",
            $affiliate_id
        ));
        
        return [
            'total_affiliates'    => (int) $total_affiliates + (int) $level_3_count,
            'level_2_count'       => (int) $total_affiliates,
            'level_3_count'       => (int) $level_3_count,
            'total_sales'         => (int) $network_data->total_sales,
            'total_revenue'       => (float) $network_data->total_revenue,
            'total_commissions'   => (float) $network_data->total_commissions,
        ];
    }

    /**
     * Distribui comissões multi-nível com compressão
     *
     * Implementa a regra de compressão: afiliados inativos para rede
     * são pulados e o próximo afiliado ativo acima recebe a comissão.
     *
     * @param LRP_Referral $referral
     */
    public function distribute_multilevel_commissions($referral) {
        $affiliate = $referral->get_affiliate();
        
        if (!$affiliate || !$affiliate->exists()) {
            return;
        }
        
        $commission_base = $referral->get_commission_base();
        
        // Nível de comissão atual (começamos no 2, pois 1 é a comissão direta)
        $current_level = 2;
        
        // Começa pelo sponsor direto do afiliado que fez a venda
        $current_sponsor = $affiliate->get_sponsor();
        
        // Lista de afiliados pulados (para log)
        $skipped_affiliates = [];
        
        // Loop com compressão - sobe a árvore até nível 3 ou acabar os sponsors
        while ($current_sponsor && $current_level <= 3) {
            // Verifica se o sponsor está ativo no programa (status)
            if (!$current_sponsor->is_active()) {
                // Sponsor não está ativo no programa - sobe para próximo
                $current_sponsor = $current_sponsor->get_sponsor();
                continue;
            }
            
            // Verifica se o sponsor está ATIVO PARA REDE (regra de compressão)
            $is_network_active = LRP_Activity_Calculator::is_affiliate_active($current_sponsor->get_id());
            
            if (!$is_network_active) {
                // Afiliado INATIVO para rede - COMPRESSÃO: pula para próximo
                $skipped_affiliates[] = [
                    'affiliate_id' => $current_sponsor->get_id(),
                    'name'         => $current_sponsor->get_display_name(),
                    'level'        => $current_level,
                ];
                
                lrp_log('Compressão aplicada - afiliado pulado', [
                    'referral_id'       => $referral->get_id(),
                    'skipped_affiliate' => $current_sponsor->get_id(),
                    'would_be_level'    => $current_level,
                    'reason'            => 'network_inactive',
                ]);
                
                // Sobe para próximo sponsor sem incrementar nível
                $current_sponsor = $current_sponsor->get_sponsor();
                continue;
            }
            
            // Afiliado ATIVO para rede - paga comissão
            $commission_type = $current_level === 2 ? 'l2' : 'l3';
            $rate = $current_sponsor->get_commission_rate($commission_type);
            
            if ($rate > 0) {
                $commission_amount = $commission_base * ($rate / 100);
                
                $commission = LRP_Commission::create([
                    'referral_id'         => $referral->get_id(),
                    'affiliate_id'        => $current_sponsor->get_id(),
                    'commission_type'     => 'level_' . $current_level,
                    'source_affiliate_id' => $affiliate->get_id(),
                    'commission_rate'     => $rate,
                    'commission_amount'   => $commission_amount,
                    'status'              => 'pending',
                ]);
                
                if (!is_wp_error($commission)) {
                    lrp_log('Comissão L' . $current_level . ' criada' . (!empty($skipped_affiliates) ? ' (com compressão)' : ''), [
                        'referral_id'        => $referral->get_id(),
                        'sponsor_id'         => $current_sponsor->get_id(),
                        'source_affiliate'   => $affiliate->get_id(),
                        'commission_amount'  => $commission_amount,
                        'level'              => $current_level,
                        'skipped_affiliates' => $skipped_affiliates,
                    ]);
                    
                    // Notifica sponsor
                    do_action('lrp_sub_affiliate_sale', $current_sponsor, $affiliate, $commission, $referral);
                }
            }
            
            // Incrementa nível e sobe para próximo sponsor
            $current_level++;
            $current_sponsor = $current_sponsor->get_sponsor();
            
            // Limpa lista de pulados para o próximo nível
            $skipped_affiliates = [];
        }
    }

    /**
     * Verifica se atribuir um sponsor criaria ciclo
     *
     * @param int $affiliate_id
     * @param int $new_sponsor_id
     * @return bool True se criaria ciclo
     */
    public function would_create_cycle($affiliate_id, $new_sponsor_id) {
        // Não pode ser sponsor de si mesmo
        if ($affiliate_id == $new_sponsor_id) {
            return true;
        }
        
        // Rastreia a cadeia de sponsors para detectar ciclos
        $current = $new_sponsor_id;
        $visited = [$affiliate_id];
        
        // Limite de profundidade
        $max_depth = 10;
        $depth = 0;
        
        while ($current && $depth < $max_depth) {
            // Se encontrou o afiliado na cadeia, há ciclo
            if (in_array($current, $visited)) {
                return true;
            }
            
            $visited[] = $current;
            
            // Busca sponsor do afiliado atual
            $sponsor = new LRP_Affiliate($current);
            $current = $sponsor->get_sponsor_id();
            
            $depth++;
        }
        
        return false;
    }

    /**
     * Define sponsor de um afiliado
     *
     * @param int $affiliate_id
     * @param int $sponsor_id
     * @return bool|WP_Error
     */
    public function set_sponsor($affiliate_id, $sponsor_id) {
        // Valida que não cria ciclo
        if ($this->would_create_cycle($affiliate_id, $sponsor_id)) {
            return new WP_Error('cycle', __('Esta atribuição criaria um ciclo na rede.', 'lab-resumos-parceiros'));
        }
        
        $affiliate = new LRP_Affiliate($affiliate_id);
        
        if (!$affiliate->exists()) {
            return new WP_Error('not_found', __('Afiliado não encontrado.', 'lab-resumos-parceiros'));
        }
        
        $sponsor = new LRP_Affiliate($sponsor_id);
        
        if (!$sponsor->exists() || !$sponsor->is_active()) {
            return new WP_Error('invalid_sponsor', __('Sponsor inválido ou inativo.', 'lab-resumos-parceiros'));
        }
        
        // Calcula nível
        $sponsor_level = $sponsor->get_level();
        $new_level = $sponsor_level + 1;
        
        // Limite de 3 níveis
        if ($new_level > 3) {
            return new WP_Error('max_level', __('Nível máximo de rede atingido.', 'lab-resumos-parceiros'));
        }
        
        $result = $affiliate->update([
            'sponsor_id' => $sponsor_id,
            'level'      => $new_level,
        ]);
        
        if ($result) {
            // Notifica sponsor
            do_action('lrp_new_sub_affiliate', $sponsor, $affiliate);
            
            lrp_log('Sponsor definido', [
                'affiliate_id' => $affiliate_id,
                'sponsor_id'   => $sponsor_id,
                'new_level'    => $new_level,
            ]);
        }
        
        return $result;
    }

    /**
     * Remove sponsor de um afiliado
     *
     * @param int $affiliate_id
     * @return bool
     */
    public function remove_sponsor($affiliate_id) {
        $affiliate = new LRP_Affiliate($affiliate_id);
        
        if (!$affiliate->exists()) {
            return false;
        }
        
        return $affiliate->update([
            'sponsor_id' => null,
            'level'      => 1,
        ]);
    }

    /**
     * Notifica sponsor quando novo afiliado é aprovado
     *
     * @param LRP_Affiliate $affiliate
     */
    public function notify_sponsor_new_affiliate($affiliate) {
        $sponsor = $affiliate->get_sponsor();
        
        if ($sponsor && $sponsor->is_active()) {
            do_action('lrp_new_sub_affiliate', $sponsor, $affiliate);
        }
    }

    /**
     * Retorna ranking de afiliados da rede
     *
     * @param int $affiliate_id
     * @param int $limit
     * @return array
     */
    public function get_network_ranking($affiliate_id, $limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, u.display_name, a.total_sales, a.total_revenue,
                    CASE 
                        WHEN a.sponsor_id = %d THEN 2
                        ELSE 3
                    END as level
             FROM {$wpdb->prefix}lrp_affiliates a
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE a.sponsor_id = %d
             OR a.sponsor_id IN (
                SELECT id FROM {$wpdb->prefix}lrp_affiliates WHERE sponsor_id = %d
             )
             ORDER BY a.total_revenue DESC
             LIMIT %d",
            $affiliate_id,
            $affiliate_id,
            $affiliate_id,
            $limit
        ));
    }
}

