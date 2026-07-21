<?php
/**
 * Página de Configurações
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) exit;

$settings_fields = LRP_Admin_Settings::get_settings_fields();
?>
<div class="wrap lrp-admin-wrap">
    <h1>⚙️ <?php _e('Configurações', 'lab-resumos-parceiros'); ?></h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('lrp_settings'); ?>
        
        <?php foreach ($settings_fields as $section_id => $section): ?>
        <div class="lrp-metabox" style="margin-top: 20px;">
            <div class="lrp-metabox-header"><?php echo esc_html($section['title']); ?></div>
            <div class="lrp-metabox-content">
                <table class="form-table lrp-form-table">
                    <?php foreach ($section['fields'] as $field_key => $field): ?>
                    <tr>
                        <th scope="row">
                            <label for="lrp_<?php echo esc_attr($field_key); ?>">
                                <?php echo esc_html($field['label']); ?>
                            </label>
                        </th>
                        <td>
                            <?php 
                            LRP_Admin_Settings::render_field(
                                $field_key, 
                                $field, 
                                $settings[$field_key] ?? ''
                            ); 
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        
        <p class="submit">
            <?php submit_button(__('Salvar Configurações', 'lab-resumos-parceiros'), 'primary', 'submit', false); ?>
        </p>
    </form>
    
    <hr>
    
    <h2><?php _e('Informações do Sistema', 'lab-resumos-parceiros'); ?></h2>
    
    <table class="form-table">
        <tr>
            <th><?php _e('Versão do Plugin', 'lab-resumos-parceiros'); ?></th>
            <td><?php echo esc_html(LRP_VERSION); ?></td>
        </tr>
        <tr>
            <th><?php _e('Versão do WordPress', 'lab-resumos-parceiros'); ?></th>
            <td><?php echo esc_html(get_bloginfo('version')); ?></td>
        </tr>
        <tr>
            <th><?php _e('Versão do WooCommerce', 'lab-resumos-parceiros'); ?></th>
            <td><?php echo esc_html(defined('WC_VERSION') ? WC_VERSION : 'N/A'); ?></td>
        </tr>
        <tr>
            <th><?php _e('Plugin Guruja Ativo', 'lab-resumos-parceiros'); ?></th>
            <td><?php echo LRP_Guruja::instance()->is_guruja_active() ? '✅ Sim' : '❌ Não'; ?></td>
        </tr>
        <tr>
            <th><?php _e('HPOS Ativo', 'lab-resumos-parceiros'); ?></th>
            <td>
                <?php 
                $hpos = class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && 
                        \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
                echo $hpos ? '✅ Sim' : '❌ Não';
                ?>
            </td>
        </tr>
    </table>
</div>

