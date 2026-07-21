<?php
/**
 * Desativação do plugin
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Deactivator
 * 
 * Responsável por limpar cron jobs na desativação.
 */
class LRP_Deactivator {

    /**
     * Executa desativação do plugin
     */
    public static function deactivate() {
        // Remove cron jobs
        wp_clear_scheduled_hook('lrp_daily_check');
        wp_clear_scheduled_hook('lrp_cleanup_expired');
        wp_clear_scheduled_hook('lrp_weekly_summary');
        
        // Limpa rewrite rules
        flush_rewrite_rules();
    }
}

