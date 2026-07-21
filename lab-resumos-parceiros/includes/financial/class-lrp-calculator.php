<?php
/**
 * Calculador de Comissões
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Calculator
 * 
 * Utilitários para cálculo de comissões.
 */
class LRP_Calculator {

    /**
     * Calcula comissão direta
     *
     * @param float $base_value Valor base (valor pago)
     * @param LRP_Affiliate $affiliate
     * @param string $type coupon|link
     * @return float
     */
    public static function calculate_direct_commission($base_value, $affiliate, $type = 'coupon') {
        $rate = $affiliate->get_commission_rate($type);
        return self::calculate($base_value, $rate);
    }

    /**
     * Calcula comissão de nível 2
     *
     * @param float $base_value
     * @param LRP_Affiliate $sponsor
     * @return float
     */
    public static function calculate_l2_commission($base_value, $sponsor) {
        $rate = $sponsor->get_commission_rate('l2');
        return self::calculate($base_value, $rate);
    }

    /**
     * Calcula comissão de nível 3
     *
     * @param float $base_value
     * @param LRP_Affiliate $sponsor
     * @return float
     */
    public static function calculate_l3_commission($base_value, $sponsor) {
        $rate = $sponsor->get_commission_rate('l3');
        return self::calculate($base_value, $rate);
    }

    /**
     * Cálculo base
     *
     * @param float $value
     * @param float $rate Percentual
     * @return float
     */
    public static function calculate($value, $rate) {
        if ($rate <= 0 || $value <= 0) {
            return 0;
        }
        
        return round($value * ($rate / 100), 2);
    }

    /**
     * Calcula todas as comissões de uma venda
     *
     * @param float $base_value
     * @param LRP_Affiliate $affiliate
     * @param string $attribution_type
     * @return array
     */
    public static function calculate_all_commissions($base_value, $affiliate, $attribution_type) {
        $commissions = [];
        
        // Comissão direta
        $direct_rate = $affiliate->get_commission_rate($attribution_type);
        $commissions[] = [
            'type'         => 'direct',
            'affiliate_id' => $affiliate->get_id(),
            'rate'         => $direct_rate,
            'amount'       => self::calculate($base_value, $direct_rate),
        ];
        
        // Comissão L2
        $sponsor_l2 = $affiliate->get_sponsor();
        if ($sponsor_l2 && $sponsor_l2->is_active()) {
            $l2_rate = $sponsor_l2->get_commission_rate('l2');
            if ($l2_rate > 0) {
                $commissions[] = [
                    'type'              => 'level_2',
                    'affiliate_id'      => $sponsor_l2->get_id(),
                    'source_affiliate'  => $affiliate->get_id(),
                    'rate'              => $l2_rate,
                    'amount'            => self::calculate($base_value, $l2_rate),
                ];
            }
            
            // Comissão L3
            $sponsor_l3 = $sponsor_l2->get_sponsor();
            if ($sponsor_l3 && $sponsor_l3->is_active()) {
                $l3_rate = $sponsor_l3->get_commission_rate('l3');
                if ($l3_rate > 0) {
                    $commissions[] = [
                        'type'              => 'level_3',
                        'affiliate_id'      => $sponsor_l3->get_id(),
                        'source_affiliate'  => $affiliate->get_id(),
                        'rate'              => $l3_rate,
                        'amount'            => self::calculate($base_value, $l3_rate),
                    ];
                }
            }
        }
        
        return $commissions;
    }

    /**
     * Calcula projeção de ganhos para o dashboard
     *
     * @param int $affiliate_id
     * @return array
     */
    public static function calculate_earnings_projection($affiliate_id) {
        global $wpdb;
        
        // Média de vendas dos últimos 3 meses
        $avg = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                AVG(monthly_sales) as avg_sales,
                AVG(monthly_commission) as avg_commission
             FROM (
                SELECT 
                    MONTH(created_at) as month,
                    COUNT(*) as monthly_sales,
                    SUM(commission_amount) as monthly_commission
                FROM {$wpdb->prefix}lrp_commissions
                WHERE affiliate_id = %d
                AND status IN ('approved', 'paid')
                AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                GROUP BY YEAR(created_at), MONTH(created_at)
             ) as monthly_data",
            $affiliate_id
        ));
        
        return [
            'avg_monthly_sales'      => (float) ($avg->avg_sales ?? 0),
            'avg_monthly_commission' => (float) ($avg->avg_commission ?? 0),
            'projected_annual'       => (float) ($avg->avg_commission ?? 0) * 12,
        ];
    }

    /**
     * Calcula valor líquido após impostos (estimativa)
     *
     * @param float $gross_value
     * @param string $tax_regime simples|mei|lucro_presumido
     * @return array
     */
    public static function calculate_net_value($gross_value, $tax_regime = 'mei') {
        $tax_rates = [
            'mei'             => 0.05,  // ~5% aproximado
            'simples'         => 0.06,  // ~6% faixa inicial
            'lucro_presumido' => 0.16,  // ~16% serviços
        ];
        
        $rate = $tax_rates[$tax_regime] ?? 0.05;
        $tax_amount = $gross_value * $rate;
        
        return [
            'gross'      => $gross_value,
            'tax_rate'   => $rate * 100,
            'tax_amount' => round($tax_amount, 2),
            'net'        => round($gross_value - $tax_amount, 2),
        ];
    }
}

