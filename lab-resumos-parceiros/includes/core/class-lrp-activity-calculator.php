<?php
/**
 * Calculador de Atividade de Rede
 *
 * Gerencia o status de atividade dos afiliados para fins de
 * comissões de rede (compressão).
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Activity_Calculator
 * 
 * Calcula e gerencia o status de atividade dos afiliados.
 * 
 * Regras:
 * - Afiliado Ativo: 3+ vendas nos últimos 3 meses fechados
 * - Afiliados novos (< 3 meses): Sempre considerados ativos
 * - Status atualizado no fechamento mensal
 */
class LRP_Activity_Calculator {

    /**
     * Número mínimo de vendas para ser considerado ativo
     */
    const MIN_SALES_FOR_ACTIVE = 3;

    /**
     * Número de meses a considerar para atividade
     */
    const ACTIVITY_PERIOD_MONTHS = 3;

    /**
     * Meses de proteção para novos afiliados
     */
    const NEW_AFFILIATE_PROTECTION_MONTHS = 3;

    /**
     * Verifica se um afiliado está ativo para receber comissões de rede
     *
     * @param int $affiliate_id ID do afiliado
     * @return bool True se ativo, false se inativo
     */
    public static function is_affiliate_active($affiliate_id) {
        // Novos afiliados são sempre considerados ativos
        if (self::is_new_affiliate($affiliate_id)) {
            return true;
        }

        // Verifica o status armazenado no banco
        global $wpdb;
        
        $network_active = $wpdb->get_var($wpdb->prepare(
            "SELECT network_active FROM {$wpdb->prefix}lrp_affiliates WHERE id = %d",
            $affiliate_id
        ));

        // Se não há status definido, calcula dinamicamente
        if ($network_active === null) {
            $status = self::calculate_activity_status($affiliate_id);
            return $status['is_active'];
        }

        return (bool) $network_active;
    }

    /**
     * Verifica se o afiliado é novo (menos de 3 meses no programa)
     *
     * @param int $affiliate_id ID do afiliado
     * @return bool True se é novo (proteção ativa)
     */
    public static function is_new_affiliate($affiliate_id) {
        global $wpdb;

        $approved_at = $wpdb->get_var($wpdb->prepare(
            "SELECT approved_at FROM {$wpdb->prefix}lrp_affiliates WHERE id = %d",
            $affiliate_id
        ));

        // Se não tem data de aprovação, usa created_at
        if (!$approved_at) {
            $approved_at = $wpdb->get_var($wpdb->prepare(
                "SELECT created_at FROM {$wpdb->prefix}lrp_affiliates WHERE id = %d",
                $affiliate_id
            ));
        }

        if (!$approved_at) {
            return true; // Na dúvida, considera novo
        }

        $approved_date = new DateTime($approved_at);
        $protection_end = clone $approved_date;
        $protection_end->modify('+' . self::NEW_AFFILIATE_PROTECTION_MONTHS . ' months');

        $now = new DateTime();

        return $now < $protection_end;
    }

    /**
     * Conta vendas aprovadas de um afiliado em um período
     *
     * @param int $affiliate_id ID do afiliado
     * @param string $start_date Data de início (Y-m-d)
     * @param string $end_date Data de fim (Y-m-d)
     * @return int Número de vendas
     */
    public static function count_sales_in_period($affiliate_id, $start_date, $end_date) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}lrp_referrals 
             WHERE affiliate_id = %d 
             AND status IN ('approved', 'paid')
             AND created_at >= %s 
             AND created_at <= %s",
            $affiliate_id,
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
    }

    /**
     * Calcula o período de atividade (3 meses anteriores fechados)
     *
     * @param int|null $reference_month Mês de referência (null = mês atual)
     * @param int|null $reference_year Ano de referência (null = ano atual)
     * @return array ['start_date' => 'Y-m-d', 'end_date' => 'Y-m-d', 'months' => [...]]
     */
    public static function get_activity_period($reference_month = null, $reference_year = null) {
        if ($reference_month === null) {
            $reference_month = (int) date('n');
        }
        if ($reference_year === null) {
            $reference_year = (int) date('Y');
        }

        // Mês de referência (fechamento) - consideramos os 3 meses ANTERIORES ao mês de referência
        $reference_date = new DateTime("$reference_year-$reference_month-01");

        // Fim do período: último dia do mês anterior ao de referência
        $end_date = clone $reference_date;
        $end_date->modify('-1 day');

        // Início do período: primeiro dia de 3 meses antes do fim
        $start_date = clone $end_date;
        $start_date->modify('first day of this month');
        $start_date->modify('-' . (self::ACTIVITY_PERIOD_MONTHS - 1) . ' months');

        // Monta lista de meses considerados
        $months = [];
        $current = clone $start_date;
        while ($current <= $end_date) {
            $months[] = [
                'month' => (int) $current->format('n'),
                'year'  => (int) $current->format('Y'),
                'label' => $current->format('M/Y'),
            ];
            $current->modify('+1 month');
        }

        return [
            'start_date' => $start_date->format('Y-m-d'),
            'end_date'   => $end_date->format('Y-m-t'),
            'months'     => $months,
        ];
    }

    /**
     * Calcula o status de atividade de um afiliado
     *
     * @param int $affiliate_id ID do afiliado
     * @param int|null $reference_month Mês de referência
     * @param int|null $reference_year Ano de referência
     * @return array Status completo com informações
     */
    public static function calculate_activity_status($affiliate_id, $reference_month = null, $reference_year = null) {
        // Verifica se é novo afiliado
        $is_new = self::is_new_affiliate($affiliate_id);

        if ($is_new) {
            return [
                'is_active'        => true,
                'is_new_affiliate' => true,
                'sales_count'      => 0,
                'sales_required'   => self::MIN_SALES_FOR_ACTIVE,
                'sales_missing'    => 0,
                'period'           => null,
                'message'          => __('Afiliado novo - período de proteção ativo', 'lab-resumos-parceiros'),
            ];
        }

        // Obtém período de atividade
        $period = self::get_activity_period($reference_month, $reference_year);

        // Conta vendas no período
        $sales_count = self::count_sales_in_period(
            $affiliate_id,
            $period['start_date'],
            $period['end_date']
        );

        $is_active = $sales_count >= self::MIN_SALES_FOR_ACTIVE;
        $sales_missing = max(0, self::MIN_SALES_FOR_ACTIVE - $sales_count);

        // Monta mensagem
        if ($is_active) {
            $message = sprintf(
                __('Ativo para rede - %d vendas nos últimos 3 meses', 'lab-resumos-parceiros'),
                $sales_count
            );
        } else {
            $message = sprintf(
                __('Inativo para rede - faltam %d vendas para ativar', 'lab-resumos-parceiros'),
                $sales_missing
            );
        }

        return [
            'is_active'        => $is_active,
            'is_new_affiliate' => false,
            'sales_count'      => $sales_count,
            'sales_required'   => self::MIN_SALES_FOR_ACTIVE,
            'sales_missing'    => $sales_missing,
            'period'           => $period,
            'message'          => $message,
        ];
    }

    /**
     * Retorna informações de atividade formatadas para exibição
     *
     * @param int $affiliate_id ID do afiliado
     * @return array Informações formatadas
     */
    public static function get_activity_info($affiliate_id) {
        $status = self::calculate_activity_status($affiliate_id);

        // Dados para o dashboard
        $info = [
            'is_active'         => $status['is_active'],
            'is_new_affiliate'  => $status['is_new_affiliate'],
            'sales_count'       => $status['sales_count'],
            'sales_required'    => $status['sales_required'],
            'sales_missing'     => $status['sales_missing'],
            'progress_percent'  => min(100, ($status['sales_count'] / $status['sales_required']) * 100),
            'message'           => $status['message'],
            'status_label'      => $status['is_active'] 
                ? __('Ativo', 'lab-resumos-parceiros') 
                : __('Inativo', 'lab-resumos-parceiros'),
            'status_class'      => $status['is_active'] ? 'active' : 'inactive',
        ];

        // Adiciona período se disponível
        if ($status['period']) {
            $info['period_start'] = $status['period']['start_date'];
            $info['period_end'] = $status['period']['end_date'];
            $info['period_months'] = $status['period']['months'];
            
            // Formata período legível
            $months_labels = array_column($status['period']['months'], 'label');
            $info['period_label'] = implode(', ', $months_labels);
        }

        return $info;
    }

    /**
     * Atualiza o status de atividade de um afiliado no banco
     *
     * @param int $affiliate_id ID do afiliado
     * @return bool Sucesso
     */
    public static function update_affiliate_status($affiliate_id) {
        global $wpdb;

        $status = self::calculate_activity_status($affiliate_id);

        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_affiliates',
            [
                'network_active'            => $status['is_active'] ? 1 : 0,
                'network_active_updated_at' => current_time('mysql'),
            ],
            ['id' => $affiliate_id],
            ['%d', '%s'],
            ['%d']
        );

        if ($result !== false) {
            lrp_log('Status de atividade atualizado', [
                'affiliate_id'   => $affiliate_id,
                'network_active' => $status['is_active'],
                'sales_count'    => $status['sales_count'],
            ]);
        }

        return $result !== false;
    }

    /**
     * Atualiza o status de atividade de TODOS os afiliados ativos
     * 
     * Este método é chamado no fechamento mensal
     *
     * @return array ['updated' => int, 'errors' => int]
     */
    public static function update_all_statuses() {
        global $wpdb;

        // Busca todos os afiliados ativos (status do programa)
        $affiliate_ids = $wpdb->get_col(
            "SELECT id FROM {$wpdb->prefix}lrp_affiliates WHERE status = 'active'"
        );

        $updated = 0;
        $errors = 0;

        foreach ($affiliate_ids as $affiliate_id) {
            $result = self::update_affiliate_status($affiliate_id);
            
            if ($result) {
                $updated++;
            } else {
                $errors++;
            }
        }

        lrp_log('Atualização em massa de status de atividade', [
            'total_affiliates' => count($affiliate_ids),
            'updated'          => $updated,
            'errors'           => $errors,
        ]);

        return [
            'updated' => $updated,
            'errors'  => $errors,
        ];
    }

    /**
     * Obtém estatísticas gerais de atividade
     *
     * @return array Estatísticas
     */
    public static function get_activity_stats() {
        global $wpdb;

        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_affiliates WHERE status = 'active'"
        );

        $active = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_affiliates 
             WHERE status = 'active' AND network_active = 1"
        );

        $inactive = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_affiliates 
             WHERE status = 'active' AND network_active = 0"
        );

        return [
            'total'            => (int) $total,
            'network_active'   => (int) $active,
            'network_inactive' => (int) $inactive,
            'active_percent'   => $total > 0 ? round(($active / $total) * 100, 1) : 0,
        ];
    }
}
