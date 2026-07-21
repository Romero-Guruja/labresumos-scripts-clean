<?php
/**
 * Classe de administração do plugin
 * 
 * @package Lab_Resumos_Guruja
 */

defined('ABSPATH') || exit;

class LRG_Admin {

    /**
     * Construtor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('plugin_action_links_' . LRG_PLUGIN_BASENAME, [$this, 'add_settings_link']);
    }

    /**
     * Adiciona menu no admin
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Desconto Guruja', 'lab-resumos-guruja'),
            __('Desconto Guruja', 'lab-resumos-guruja'),
            'manage_woocommerce',
            'lrg-guruja-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Adiciona link de configurações na lista de plugins
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=lrg-guruja-settings'),
            __('Configurações', 'lab-resumos-guruja')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Registra configurações
     */
    public function register_settings() {
        // Seção principal
        add_settings_section(
            'lrg_main_section',
            __('Configurações da API Guruja', 'lab-resumos-guruja'),
            [$this, 'render_section_description'],
            'lrg-guruja-settings'
        );

        // Campo: Ativar/Desativar
        register_setting('lrg_settings', 'lrg_enabled', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'yes',
        ]);

        add_settings_field(
            'lrg_enabled',
            __('Ativar integração', 'lab-resumos-guruja'),
            [$this, 'render_checkbox_field'],
            'lrg-guruja-settings',
            'lrg_main_section',
            [
                'id' => 'lrg_enabled',
                'description' => __('Ativa ou desativa a verificação de desconto no checkout.', 'lab-resumos-guruja'),
            ]
        );

        // Campo: URL da API
        register_setting('lrg_settings', 'lrg_api_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => 'https://backoffice.guruja.com.br/woocommerce/verificar-desconto',
        ]);

        add_settings_field(
            'lrg_api_url',
            __('URL da API', 'lab-resumos-guruja'),
            [$this, 'render_text_field'],
            'lrg-guruja-settings',
            'lrg_main_section',
            [
                'id' => 'lrg_api_url',
                'description' => __('Endpoint da API Guruja para verificação de desconto.', 'lab-resumos-guruja'),
                'placeholder' => 'https://backoffice.guruja.com.br/woocommerce/verificar-desconto',
            ]
        );

        // Campo: Token de autenticação
        register_setting('lrg_settings', 'lrg_api_token', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        add_settings_field(
            'lrg_api_token',
            __('Token de Autenticação', 'lab-resumos-guruja'),
            [$this, 'render_password_field'],
            'lrg-guruja-settings',
            'lrg_main_section',
            [
                'id' => 'lrg_api_token',
                'description' => __('Token Bearer para autenticação na API Guruja.', 'lab-resumos-guruja'),
            ]
        );

        // Campo: Modo debug
        register_setting('lrg_settings', 'lrg_debug_mode', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'no',
        ]);

        add_settings_field(
            'lrg_debug_mode',
            __('Modo Debug', 'lab-resumos-guruja'),
            [$this, 'render_checkbox_field'],
            'lrg-guruja-settings',
            'lrg_main_section',
            [
                'id' => 'lrg_debug_mode',
                'description' => __('Ativa logs detalhados em wp-content/debug.log (requer WP_DEBUG_LOG ativo).', 'lab-resumos-guruja'),
            ]
        );

        // Campo: Timeout da API
        register_setting('lrg_settings', 'lrg_api_timeout', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 10,
        ]);

        add_settings_field(
            'lrg_api_timeout',
            __('Timeout da API (segundos)', 'lab-resumos-guruja'),
            [$this, 'render_number_field'],
            'lrg-guruja-settings',
            'lrg_main_section',
            [
                'id' => 'lrg_api_timeout',
                'description' => __('Tempo máximo de espera pela resposta da API.', 'lab-resumos-guruja'),
                'min' => 5,
                'max' => 60,
                'default' => 10,
            ]
        );
    }

    /**
     * Renderiza descrição da seção
     */
    public function render_section_description() {
        echo '<p>' . esc_html__('Configure a integração com a API Guruja para aplicar descontos automáticos no checkout.', 'lab-resumos-guruja') . '</p>';
    }

    /**
     * Renderiza campo de texto
     */
    public function render_text_field($args) {
        $value = get_option($args['id'], '');
        printf(
            '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s" />',
            esc_attr($args['id']),
            esc_attr($value),
            esc_attr($args['placeholder'] ?? '')
        );
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Renderiza campo de senha
     */
    public function render_password_field($args) {
        $value = get_option($args['id'], '');
        printf(
            '<input type="password" id="%1$s" name="%1$s" value="%2$s" class="regular-text" />',
            esc_attr($args['id']),
            esc_attr($value)
        );
        printf(
            '<button type="button" class="button button-secondary" onclick="togglePasswordVisibility(\'%s\')">%s</button>',
            esc_attr($args['id']),
            esc_html__('Mostrar', 'lab-resumos-guruja')
        );
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Renderiza campo checkbox
     */
    public function render_checkbox_field($args) {
        $value = get_option($args['id'], 'no');
        printf(
            '<input type="checkbox" id="%1$s" name="%1$s" value="yes" %2$s />',
            esc_attr($args['id']),
            checked($value, 'yes', false)
        );
        if (!empty($args['description'])) {
            printf('<label for="%s"> %s</label>', esc_attr($args['id']), esc_html($args['description']));
        }
    }

    /**
     * Renderiza campo numérico
     */
    public function render_number_field($args) {
        $value = get_option($args['id'], $args['default'] ?? 10);
        printf(
            '<input type="number" id="%1$s" name="%1$s" value="%2$s" min="%3$s" max="%4$s" class="small-text" />',
            esc_attr($args['id']),
            esc_attr($value),
            esc_attr($args['min'] ?? 1),
            esc_attr($args['max'] ?? 100)
        );
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Renderiza página de configurações
     */
    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'lab-resumos-guruja'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('lrg_settings');
                do_settings_sections('lrg-guruja-settings');
                submit_button(__('Salvar Configurações', 'lab-resumos-guruja'));
                ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Testar Conexão', 'lab-resumos-guruja'); ?></h2>
            <p><?php esc_html_e('Clique no botão abaixo para testar a conexão com a API Guruja.', 'lab-resumos-guruja'); ?></p>
            <button type="button" id="lrg-test-connection" class="button button-secondary">
                <?php esc_html_e('Testar Conexão', 'lab-resumos-guruja'); ?>
            </button>
            <span id="lrg-test-result" style="margin-left: 10px;"></span>

            <hr>

            <h2><?php esc_html_e('Contrato da API', 'lab-resumos-guruja'); ?></h2>
            <p><?php esc_html_e('O plugin envia e espera receber dados no seguinte formato:', 'lab-resumos-guruja'); ?></p>
            
            <h3><?php esc_html_e('Request (WordPress → Guruja)', 'lab-resumos-guruja'); ?></h3>
            <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;">
POST <?php echo esc_html(get_option('lrg_api_url', '[URL_DA_API]')); ?>

Headers:
  Authorization: Bearer [TOKEN]
  Content-Type: application/json

Body:
{
  "email": "aluno@email.com",
  "cpf": "12345678900",
  "produtos": [
    { "product_id": 123, "sku": "CURSO-001", "valor": 297.00 },
    { "product_id": 456, "sku": "CURSO-002", "valor": 197.00 }
  ]
}</pre>

            <h3><?php esc_html_e('Response esperada (Guruja → WordPress)', 'lab-resumos-guruja'); ?></h3>
            <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;">
{
  "elegivel": true,
  "descontos": [
    { "product_id": 123, "tipo": "percentual", "valor": 15 },
    { "product_id": 456, "tipo": "fixo", "valor": 50.00 }
  ]
}</pre>

        </div>

        <script>
        function togglePasswordVisibility(fieldId) {
            var field = document.getElementById(fieldId);
            var button = field.nextElementSibling;
            if (field.type === 'password') {
                field.type = 'text';
                button.textContent = '<?php echo esc_js(__('Ocultar', 'lab-resumos-guruja')); ?>';
            } else {
                field.type = 'password';
                button.textContent = '<?php echo esc_js(__('Mostrar', 'lab-resumos-guruja')); ?>';
            }
        }

        jQuery(document).ready(function($) {
            $('#lrg-test-connection').on('click', function() {
                var $button = $(this);
                var $result = $('#lrg-test-result');
                
                $button.prop('disabled', true);
                $result.html('<span style="color: #666;">Testando...</span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lrg_test_connection',
                        nonce: '<?php echo wp_create_nonce('lrg_test_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                        } else {
                            $result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color: red;">✗ Erro de conexão</span>');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Inicializa a classe admin
new LRG_Admin();
