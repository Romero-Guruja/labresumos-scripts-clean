<?php
/**
 * Gerenciador de Configurações
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Settings
 * 
 * Singleton para gerenciar configurações do plugin.
 */
class LRP_Settings {

    /**
     * Instância única
     *
     * @var LRP_Settings|null
     */
    private static $instance = null;

    /**
     * Configurações carregadas
     *
     * @var array
     */
    private $settings = [];

    /**
     * Valores padrão
     *
     * @var array
     */
    private $defaults = [
        'enabled' => true,
        'default_commission_coupon' => 10.00,
        'default_commission_link' => 5.00,
        'default_commission_l2' => 3.00,
        'default_commission_l3' => 1.00,
        'default_cookie_days' => 60,
        'default_customer_discount' => 20.00,
        'minimum_payout' => 200.00,
        'closing_day' => 1,
        'default_guruja_rule' => 'higher_discount',
        'default_can_self_refer' => true,
        'commission_base_type' => 'order_total', // order_total, subtotal_only, subtotal_minus_discount
        'company_name' => 'SOLUCOES EDUCACIONAIS INTELIGENTES LTDA',
        'company_cnpj' => '',
        'company_address' => '',
        'accountant_email' => 'financeiro@labresumos.com.br',
        'admin_email' => '',
        'auto_approve' => false,
        'debug_mode' => false,
        // Campos de NF
        'nf_contact_email' => 'financeiro@labresumos.com.br',
        'nf_service_description' => 'Serviços de divulgação e indicação comercial',
        'nf_instructions' => '',
    ];

    /**
     * Retorna instância única
     *
     * @return LRP_Settings
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado (singleton)
     */
    private function __construct() {
        $this->load();
    }

    /**
     * Carrega configurações do banco
     */
    private function load() {
        $saved = get_option('lrp_settings', []);
        $this->settings = wp_parse_args($saved, $this->defaults);
    }

    /**
     * Obtém uma configuração
     *
     * @param string $key Chave da configuração
     * @param mixed $default Valor padrão se não existir
     * @return mixed
     */
    public function get($key, $default = null) {
        if (isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        
        if ($default !== null) {
            return $default;
        }
        
        return isset($this->defaults[$key]) ? $this->defaults[$key] : null;
    }

    /**
     * Define uma configuração
     *
     * @param string $key Chave
     * @param mixed $value Valor
     * @return bool
     */
    public function set($key, $value) {
        $this->settings[$key] = $value;
        return $this->save();
    }

    /**
     * Define múltiplas configurações
     *
     * @param array $settings Array de configurações
     * @return bool
     */
    public function set_many($settings) {
        foreach ($settings as $key => $value) {
            $this->settings[$key] = $value;
        }
        return $this->save();
    }

    /**
     * Salva configurações no banco
     *
     * @return bool
     */
    public function save() {
        return update_option('lrp_settings', $this->settings);
    }

    /**
     * Retorna todas as configurações
     *
     * @return array
     */
    public function get_all() {
        return $this->settings;
    }

    /**
     * Retorna valores padrão
     *
     * @return array
     */
    public function get_defaults() {
        return $this->defaults;
    }

    /**
     * Verifica se o programa está ativo
     *
     * @return bool
     */
    public function is_enabled() {
        return (bool) $this->get('enabled', true);
    }

    /**
     * Obtém taxa de comissão padrão por tipo
     *
     * @param string $type Tipo: coupon, link, l2, l3
     * @return float
     */
    public function get_commission_rate($type) {
        $key = 'default_commission_' . $type;
        return (float) $this->get($key, 0);
    }

    /**
     * Obtém duração do cookie em dias
     *
     * @return int
     */
    public function get_cookie_days() {
        return (int) $this->get('default_cookie_days', 60);
    }

    /**
     * Obtém valor mínimo para saque
     *
     * @return float
     */
    public function get_minimum_payout() {
        return (float) $this->get('minimum_payout', 200.00);
    }

    /**
     * Obtém regra Guruja padrão
     *
     * @return string
     */
    public function get_guruja_rule() {
        return $this->get('default_guruja_rule', 'higher_discount');
    }

    /**
     * Verifica se a auto-referência é permitida por padrão
     *
     * @return bool
     */
    public function can_self_refer_default() {
        return (bool) $this->get('default_can_self_refer', true);
    }

    /**
     * Obtém tipo de base para cálculo de comissão
     * 
     * Opções:
     * - order_total: Total pago (inclui frete, taxas, descontos)
     * - subtotal_only: Apenas subtotal dos produtos
     * - subtotal_minus_discount: Subtotal menos descontos
     *
     * @return string
     */
    public function get_commission_base_type() {
        return $this->get('commission_base_type', 'order_total');
    }

    /**
     * Obtém desconto para cliente (%)
     *
     * @return float
     */
    public function get_customer_discount() {
        return (float) $this->get('default_customer_discount', 20.00);
    }

    /**
     * Obtém email do contador
     *
     * @return string
     */
    public function get_accountant_email() {
        return $this->get('accountant_email', 'financeiro@labresumos.com.br');
    }

    /**
     * Obtém email do admin
     *
     * @return string
     */
    public function get_admin_email() {
        $email = $this->get('admin_email', '');
        return !empty($email) ? $email : get_option('admin_email');
    }

    /**
     * Verifica se aprovação automática está ativa
     *
     * @return bool
     */
    public function is_auto_approve() {
        return (bool) $this->get('auto_approve', false);
    }

    /**
     * Verifica se modo debug está ativo
     *
     * @return bool
     */
    public function is_debug() {
        return (bool) $this->get('debug_mode', false);
    }

    /**
     * Obtém dados da empresa para NF
     *
     * @return array
     */
    public function get_company_data() {
        return [
            'name'    => $this->get('company_name', ''),
            'cnpj'    => $this->get('company_cnpj', ''),
            'address' => $this->get('company_address', ''),
        ];
    }

    /**
     * Obtém dados e instruções para emissão de NF
     *
     * @return array
     */
    public function get_nf_data() {
        return [
            'contact_email'       => $this->get('nf_contact_email', 'financeiro@labresumos.com.br'),
            'service_description' => $this->get('nf_service_description', 'Serviços de divulgação e indicação comercial'),
            'instructions'        => $this->get('nf_instructions', ''),
        ];
    }

    /**
     * Reseta configurações para padrão
     *
     * @return bool
     */
    public function reset() {
        $this->settings = $this->defaults;
        return $this->save();
    }
}

