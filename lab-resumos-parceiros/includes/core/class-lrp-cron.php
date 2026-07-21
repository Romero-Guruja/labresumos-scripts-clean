<?php
/**
 * Cron Jobs
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Cron
 * 
 * Gerencia tarefas agendadas do plugin.
 * Implementa fallbacks robustos e logging detalhado para diagnóstico.
 */
class LRP_Cron {

    /**
     * Log interno com contexto
     *
     * @param string $message
     * @param array $context
     * @param string $level info|warning|error
     */
    private static function log($message, $context = [], $level = 'info') {
        $prefix = '[LRP Cron]';
        $formatted = sprintf('%s [%s] %s', $prefix, strtoupper($level), $message);
        
        if (!empty($context)) {
            $formatted .= ' | ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        // Usa sistema de log do plugin se disponível
        if (class_exists('LRP_Logger') && method_exists('LRP_Logger', 'log')) {
            LRP_Logger::log('cron', $message, $context);
        }
        
        // Também loga no error_log para debug
        if ($level === 'error') {
            error_log($formatted);
        } elseif ($level === 'warning') {
            error_log($formatted);
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($formatted);
        }
    }

    /**
     * Verifica se tabela existe
     *
     * @param string $table Nome da tabela (sem prefixo)
     * @return bool
     */
    private static function table_exists($table) {
        global $wpdb;
        $full_name = $wpdb->prefix . $table;
        return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_name)) === $full_name;
    }

    /**
     * Inicializa os hooks de cron
     */
    public static function init() {
        // Verificação diária para fechamento mensal
        add_action('lrp_daily_check', [__CLASS__, 'check_monthly_closing']);
        
        // Limpeza de dados expirados
        add_action('lrp_cleanup_expired', [__CLASS__, 'cleanup_expired']);
        add_action('lrp_cleanup_expired', [__CLASS__, 'cleanup_old_logs']);
        
        // Resumo semanal para admin
        add_action('lrp_weekly_summary', [__CLASS__, 'send_weekly_summary']);
        
        // Cálculo de estatísticas de afiliados (horário)
        add_action('lrp_calculate_stats', [__CLASS__, 'calculate_affiliate_stats']);
        
        // Registra cron se não existir
        if (!wp_next_scheduled('lrp_calculate_stats')) {
            wp_schedule_event(time(), 'hourly', 'lrp_calculate_stats');
            self::log('Cron lrp_calculate_stats agendado');
        }
    }

    /**
     * Verifica se é dia 1 e executa fechamento mensal
     */
    public static function check_monthly_closing() {
        try {
            // Verifica se é dia 1 do mês
            if ((int) date('j') !== 1) {
                return;
            }
            
            self::log('Iniciando fechamento mensal');
            
            if (!class_exists('LRP_Closing')) {
                self::log('Classe LRP_Closing não encontrada', [], 'error');
                return;
            }
            
            if (!method_exists('LRP_Closing', 'run_monthly_closing')) {
                self::log('Método run_monthly_closing não encontrado', [], 'error');
                return;
            }
            
            LRP_Closing::run_monthly_closing();
            
            self::log('Fechamento mensal executado', [
                'period' => date('m/Y', strtotime('-1 month')),
            ]);
            
        } catch (Exception $e) {
            self::log('Erro no fechamento mensal', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 'error');
        }
    }

    /**
     * Limpa dados expirados (visitas antigas)
     */
    public static function cleanup_expired() {
        global $wpdb;
        
        try {
            // Verifica se tabela existe
            if (!self::table_exists('lrp_visits')) {
                self::log('Tabela lrp_visits não encontrada em cleanup_expired', [], 'warning');
                return;
            }
            
            // Remove visitas antigas (mais de 90 dias)
            $wpdb->suppress_errors(true);
            $deleted = $wpdb->query(
                "DELETE FROM {$wpdb->prefix}lrp_visits 
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );
            $wpdb->suppress_errors(false);
            
            if ($wpdb->last_error) {
                self::log('Erro SQL ao limpar visitas antigas', [
                    'error' => $wpdb->last_error,
                ], 'error');
                return;
            }
            
            if ($deleted > 0) {
                self::log('Visitas antigas removidas', [
                    'deleted_count' => $deleted,
                ]);
            }
            
        } catch (Exception $e) {
            self::log('Erro em cleanup_expired', [
                'error' => $e->getMessage(),
            ], 'error');
        }
    }

    /**
     * Limpa logs antigos e anonimiza IPs (conformidade LGPD)
     */
    public static function cleanup_old_logs() {
        global $wpdb;
        
        try {
            $deleted_logs = 0;
            $anonymized_visits = 0;
            $anonymized_affiliates = 0;
            
            // Remove logs com mais de 90 dias
            if (self::table_exists('lrp_activity_log')) {
                $wpdb->suppress_errors(true);
                $deleted_logs = $wpdb->query(
                    "DELETE FROM {$wpdb->prefix}lrp_activity_log 
                     WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
                );
                $wpdb->suppress_errors(false);
                
                if ($wpdb->last_error) {
                    self::log('Erro ao limpar activity_log', ['error' => $wpdb->last_error], 'warning');
                }
            }
            
            // Anonimiza IPs de visitas antigas (LGPD)
            if (self::table_exists('lrp_visits')) {
                $wpdb->suppress_errors(true);
                $anonymized_visits = $wpdb->query(
                    "UPDATE {$wpdb->prefix}lrp_visits 
                     SET visitor_ip = NULL 
                     WHERE visitor_ip IS NOT NULL
                     AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
                );
                $wpdb->suppress_errors(false);
                
                if ($wpdb->last_error) {
                    self::log('Erro ao anonimizar IPs de visitas', ['error' => $wpdb->last_error], 'warning');
                }
            }
            
            // Anonimiza IPs de afiliados (cadastro antigo - LGPD)
            if (self::table_exists('lrp_affiliates')) {
                $wpdb->suppress_errors(true);
                $anonymized_affiliates = $wpdb->query(
                    "UPDATE {$wpdb->prefix}lrp_affiliates 
                     SET application_ip = NULL 
                     WHERE application_ip IS NOT NULL
                     AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
                );
                $wpdb->suppress_errors(false);
                
                if ($wpdb->last_error) {
                    self::log('Erro ao anonimizar IPs de afiliados', ['error' => $wpdb->last_error], 'warning');
                }
            }
            
            if ($deleted_logs > 0 || $anonymized_visits > 0 || $anonymized_affiliates > 0) {
                self::log('Limpeza LGPD executada', [
                    'deleted_logs' => $deleted_logs,
                    'anonymized_visit_ips' => $anonymized_visits,
                    'anonymized_affiliate_ips' => $anonymized_affiliates,
                ]);
            }
            
        } catch (Exception $e) {
            self::log('Erro em cleanup_old_logs', [
                'error' => $e->getMessage(),
            ], 'error');
        }
    }

    /**
     * Calcula estatísticas enriquecidas de todos os afiliados
     * Executado a cada hora
     * 
     * @since 1.2.0
     */
    public static function calculate_affiliate_stats() {
        $start_time = microtime(true);
        
        try {
            self::log('Iniciando cálculo de estatísticas de afiliados');
            
            // Verifica se classe existe
            if (!class_exists('LRP_Stats_Calculator')) {
                self::log('Classe LRP_Stats_Calculator não encontrada. Verifique se o plugin está atualizado.', [], 'error');
                return;
            }
            
            $calculator = new LRP_Stats_Calculator();
            $result = $calculator->calculate_all_affiliates();
            
            $execution_time = round(microtime(true) - $start_time, 2);
            
            // Log resultado
            if (isset($result['success']) && $result['success']) {
                self::log('Cálculo de estatísticas concluído', [
                    'affiliates_processed' => $result['affiliates_processed'] ?? 0,
                    'execution_time' => $execution_time . 's',
                ]);
            } else {
                self::log('Cálculo de estatísticas concluído com erros', array_merge(
                    $result,
                    ['execution_time' => $execution_time . 's']
                ), 'warning');
            }
            
        } catch (Exception $e) {
            self::log('Erro crítico no cálculo de estatísticas', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], 'error');
        } catch (Error $e) {
            self::log('Erro fatal no cálculo de estatísticas', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 'error');
        }
    }

    /**
     * Envia resumo semanal para admin
     */
    public static function send_weekly_summary() {
        try {
            // Verifica se classe de settings existe
            if (!class_exists('LRP_Settings')) {
                self::log('Classe LRP_Settings não encontrada', [], 'warning');
                return;
            }
            
            $settings = LRP_Settings::instance();
            $admin_email = $settings->get('admin_email') ?: get_option('admin_email');
            
            if (empty($admin_email) || !is_email($admin_email)) {
                self::log('Email de admin inválido para resumo semanal', [
                    'email' => $admin_email ?? 'null',
                ], 'warning');
                return;
            }
            
            // Período da última semana
            $start_date = date('Y-m-d 00:00:00', strtotime('-7 days'));
            $end_date = date('Y-m-d 23:59:59');
            
            // Obtém estatísticas
            $stats = self::get_weekly_stats($start_date, $end_date);
            
            // Só envia se houve alguma atividade
            if ($stats['total_sales'] == 0 && $stats['new_affiliates'] == 0) {
                self::log('Resumo semanal não enviado: sem atividade');
                return;
            }
            
            // Prepara email
            $subject = sprintf(
                '[Lab Resumos Parceiros] Resumo Semanal - %d vendas, R$ %s em comissões',
                $stats['total_sales'],
                number_format($stats['total_commissions'], 2, ',', '.')
            );
            
            $message = self::build_weekly_email($stats);
            
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: Lab Resumos <noreply@labresumos.com.br>',
            ];
            
            $sent = wp_mail($admin_email, $subject, $message, $headers);
            
            if ($sent) {
                self::log('Resumo semanal enviado', [
                    'email' => $admin_email,
                    'sales' => $stats['total_sales'],
                    'commissions' => $stats['total_commissions'],
                ]);
            } else {
                self::log('Falha ao enviar resumo semanal', [
                    'email' => $admin_email,
                ], 'error');
            }
            
        } catch (Exception $e) {
            self::log('Erro no envio do resumo semanal', [
                'error' => $e->getMessage(),
            ], 'error');
        }
    }

    /**
     * Obtém estatísticas semanais
     *
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    private static function get_weekly_stats($start_date, $end_date) {
        global $wpdb;
        
        $default = [
            'total_sales' => 0,
            'total_revenue' => 0.0,
            'total_commissions' => 0.0,
            'new_affiliates' => 0,
            'pending_affiliates' => 0,
            'pending_invoices' => 0,
            'top_affiliates' => [],
            'period' => sprintf('%s a %s', 
                date('d/m/Y', strtotime($start_date)),
                date('d/m/Y', strtotime($end_date))
            ),
        ];
        
        // Verifica tabelas necessárias
        if (!self::table_exists('lrp_referrals')) {
            self::log('Tabela lrp_referrals não encontrada para resumo semanal', [], 'warning');
            return $default;
        }
        
        try {
            // Vendas e comissões
            $wpdb->suppress_errors(true);
            $sales = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_sales,
                    COALESCE(SUM(commission_base), 0) as total_revenue
                 FROM {$wpdb->prefix}lrp_referrals
                 WHERE created_at BETWEEN %s AND %s
                 AND status IN ('pending', 'approved')",
                $start_date,
                $end_date
            ));
            $wpdb->suppress_errors(false);
            
            if ($wpdb->last_error) {
                self::log('Erro ao buscar vendas semanais', ['error' => $wpdb->last_error], 'warning');
                $sales = (object) ['total_sales' => 0, 'total_revenue' => 0];
            }
            
            // Comissões
            $wpdb->suppress_errors(true);
            $commissions = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(commission_amount), 0)
                 FROM {$wpdb->prefix}lrp_commissions
                 WHERE created_at BETWEEN %s AND %s
                 AND status IN ('pending', 'approved')",
                $start_date,
                $end_date
            ));
            $wpdb->suppress_errors(false);
            
            // Novos afiliados
            $wpdb->suppress_errors(true);
            $new_affiliates = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$wpdb->prefix}lrp_affiliates
                 WHERE created_at BETWEEN %s AND %s",
                $start_date,
                $end_date
            ));
            $wpdb->suppress_errors(false);
            
            // Afiliados pendentes
            $wpdb->suppress_errors(true);
            $pending_affiliates = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_affiliates WHERE status = 'pending'"
            );
            $wpdb->suppress_errors(false);
            
            // NFs pendentes
            $pending_invoices = 0;
            if (self::table_exists('lrp_closings')) {
                $wpdb->suppress_errors(true);
                $pending_invoices = $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}lrp_closings WHERE status = 'invoice_received'"
                );
                $wpdb->suppress_errors(false);
            }
            
            // Top afiliados da semana
            $wpdb->suppress_errors(true);
            $top_affiliates = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    a.id,
                    u.display_name,
                    COUNT(r.id) as sales,
                    COALESCE(SUM(r.commission_base), 0) as revenue
                 FROM {$wpdb->prefix}lrp_affiliates a
                 JOIN {$wpdb->users} u ON a.user_id = u.ID
                 JOIN {$wpdb->prefix}lrp_referrals r ON a.id = r.affiliate_id
                 WHERE r.created_at BETWEEN %s AND %s
                 GROUP BY a.id, u.display_name
                 ORDER BY revenue DESC
                 LIMIT 5",
                $start_date,
                $end_date
            ), ARRAY_A);
            $wpdb->suppress_errors(false);
            
            return [
                'total_sales' => (int) ($sales->total_sales ?? 0),
                'total_revenue' => (float) ($sales->total_revenue ?? 0),
                'total_commissions' => (float) ($commissions ?? 0),
                'new_affiliates' => (int) ($new_affiliates ?? 0),
                'pending_affiliates' => (int) ($pending_affiliates ?? 0),
                'pending_invoices' => (int) ($pending_invoices ?? 0),
                'top_affiliates' => $top_affiliates ?: [],
                'period' => sprintf('%s a %s', 
                    date('d/m/Y', strtotime($start_date)),
                    date('d/m/Y', strtotime($end_date))
                ),
            ];
            
        } catch (Exception $e) {
            self::log('Erro ao obter estatísticas semanais', [
                'error' => $e->getMessage(),
            ], 'error');
            return $default;
        }
    }

    /**
     * Constrói email de resumo semanal
     *
     * @param array $stats
     * @return string
     */
    private static function build_weekly_email($stats) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding: 20px 0; border-bottom: 2px solid #2A6B9F; }
                .stats { display: flex; flex-wrap: wrap; gap: 15px; margin: 20px 0; }
                .stat-card { background: #f8f9fa; padding: 15px; border-radius: 8px; flex: 1; min-width: 120px; text-align: center; }
                .stat-value { font-size: 24px; font-weight: bold; color: #2A6B9F; }
                .stat-label { font-size: 12px; color: #666; }
                .alert { background: #fff3cd; padding: 10px; border-radius: 5px; margin: 15px 0; }
                .table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                .table th, .table td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
                .table th { background: #f8f9fa; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>📊 Resumo Semanal - Programa de Parceiros</h2>
                    <p><?php echo esc_html($stats['period']); ?></p>
                </div>
                
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo (int) $stats['total_sales']; ?></div>
                        <div class="stat-label">Vendas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">R$ <?php echo number_format((float) $stats['total_revenue'], 0, ',', '.'); ?></div>
                        <div class="stat-label">Receita</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">R$ <?php echo number_format((float) $stats['total_commissions'], 0, ',', '.'); ?></div>
                        <div class="stat-label">Comissões</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo (int) $stats['new_affiliates']; ?></div>
                        <div class="stat-label">Novos Parceiros</div>
                    </div>
                </div>
                
                <?php if (($stats['pending_affiliates'] ?? 0) > 0 || ($stats['pending_invoices'] ?? 0) > 0): ?>
                <div class="alert">
                    <strong>⚠️ Atenção:</strong>
                    <?php if (($stats['pending_affiliates'] ?? 0) > 0): ?>
                        <?php echo (int) $stats['pending_affiliates']; ?> parceiro(s) aguardando aprovação.
                    <?php endif; ?>
                    <?php if (($stats['pending_invoices'] ?? 0) > 0): ?>
                        <?php echo (int) $stats['pending_invoices']; ?> NF(s) aguardando validação.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($stats['top_affiliates'])): ?>
                <h3>🏆 Top Parceiros da Semana</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Parceiro</th>
                            <th>Vendas</th>
                            <th>Receita</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['top_affiliates'] as $affiliate): ?>
                        <tr>
                            <td><?php echo esc_html($affiliate['display_name'] ?? 'N/A'); ?></td>
                            <td><?php echo (int) ($affiliate['sales'] ?? 0); ?></td>
                            <td>R$ <?php echo number_format((float) ($affiliate['revenue'] ?? 0), 2, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <p style="text-align: center; margin-top: 30px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-dashboard')); ?>" style="display: inline-block; padding: 12px 24px; background: #2A6B9F; color: #fff; text-decoration: none; border-radius: 6px;">
                        Ver Dashboard Completo
                    </a>
                </p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Executa todos os crons manualmente (para debug)
     *
     * @return array Resultado de cada cron
     */
    public static function run_all_manual() {
        $results = [];
        
        $results['monthly_closing'] = ['skipped' => (int) date('j') !== 1];
        
        self::cleanup_expired();
        $results['cleanup_expired'] = ['executed' => true];
        
        self::cleanup_old_logs();
        $results['cleanup_old_logs'] = ['executed' => true];
        
        self::calculate_affiliate_stats();
        $results['calculate_stats'] = ['executed' => true];
        
        return $results;
    }
    
    /**
     * Verifica status dos crons
     *
     * @return array
     */
    public static function get_cron_status() {
        return [
            'lrp_daily_check' => [
                'scheduled' => (bool) wp_next_scheduled('lrp_daily_check'),
                'next_run' => wp_next_scheduled('lrp_daily_check') 
                    ? date('Y-m-d H:i:s', wp_next_scheduled('lrp_daily_check'))
                    : null,
            ],
            'lrp_cleanup_expired' => [
                'scheduled' => (bool) wp_next_scheduled('lrp_cleanup_expired'),
                'next_run' => wp_next_scheduled('lrp_cleanup_expired')
                    ? date('Y-m-d H:i:s', wp_next_scheduled('lrp_cleanup_expired'))
                    : null,
            ],
            'lrp_weekly_summary' => [
                'scheduled' => (bool) wp_next_scheduled('lrp_weekly_summary'),
                'next_run' => wp_next_scheduled('lrp_weekly_summary')
                    ? date('Y-m-d H:i:s', wp_next_scheduled('lrp_weekly_summary'))
                    : null,
            ],
            'lrp_calculate_stats' => [
                'scheduled' => (bool) wp_next_scheduled('lrp_calculate_stats'),
                'next_run' => wp_next_scheduled('lrp_calculate_stats')
                    ? date('Y-m-d H:i:s', wp_next_scheduled('lrp_calculate_stats'))
                    : null,
            ],
        ];
    }
}
