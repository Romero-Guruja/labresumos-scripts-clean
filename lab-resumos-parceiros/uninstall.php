<?php
/**
 * Desinstalação do plugin
 * 
 * Executado quando o plugin é deletado pelo WordPress.
 *
 * @package Lab_Resumos_Parceiros
 */

// Se não foi chamado pelo WordPress, aborta
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Verifica se deve remover todos os dados
$remove_all_data = get_option('lrp_remove_data_on_uninstall', false);

if ($remove_all_data) {
    global $wpdb;
    
    // Remove tabelas
    $tables = [
        'lrp_affiliates',
        'lrp_referrals',
        'lrp_commissions',
        'lrp_closings',
        'lrp_visits',
        'lrp_materials',
        'lrp_faq',
        'lrp_activity_log',
    ];
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
    }
    
    // Remove opções
    delete_option('lrp_settings');
    delete_option('lrp_db_version');
    delete_option('lrp_dashboard_page_id');
    delete_option('lrp_registration_page_id');
    delete_option('lrp_remove_data_on_uninstall');
    
    // Remove user meta
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'lrp_%'");
    
    // Remove order meta
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_lrp_%'");
    
    // Remove roles
    remove_role('lrp_affiliate');
    remove_role('lrp_accountant');
    
    // Remove capabilities do admin
    $admin = get_role('administrator');
    if ($admin) {
        $admin->remove_cap('lrp_manage_affiliates');
        $admin->remove_cap('lrp_manage_commissions');
        $admin->remove_cap('lrp_manage_settings');
        $admin->remove_cap('lrp_view_reports');
        $admin->remove_cap('lrp_manage_invoices');
        $admin->remove_cap('lrp_manage_payments');
    }
    
    // Remove páginas criadas
    $dashboard_page = get_option('lrp_dashboard_page_id');
    $registration_page = get_option('lrp_registration_page_id');
    
    if ($dashboard_page) {
        wp_delete_post($dashboard_page, true);
    }
    if ($registration_page) {
        wp_delete_post($registration_page, true);
    }
    
    // Remove arquivos de upload usando WP_Filesystem
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
        require_once(ABSPATH . '/wp-admin/includes/file.php');
        WP_Filesystem();
    }
    
    $upload_dir = wp_upload_dir();
    $dirs = ['lrp-invoices', 'lrp-payments', 'lrp-materials'];
    
    foreach ($dirs as $dir) {
        $path = $upload_dir['basedir'] . '/' . $dir;
        if ($wp_filesystem && $wp_filesystem->exists($path)) {
            $wp_filesystem->rmdir($path, true);
        }
    }
    
    // Limpa transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lrp_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_lrp_%'");
    
    // Remove cron jobs (caso ainda existam)
    wp_clear_scheduled_hook('lrp_daily_check');
    wp_clear_scheduled_hook('lrp_cleanup_expired');
    wp_clear_scheduled_hook('lrp_weekly_summary');
}

