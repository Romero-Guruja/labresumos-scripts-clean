<?php
/**
 * Modelo de Afiliado
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Affiliate
 * 
 * Representa um afiliado do programa.
 */
class LRP_Affiliate {

    /**
     * ID do afiliado
     *
     * @var int
     */
    private $id = 0;

    /**
     * Dados do afiliado
     *
     * @var array
     */
    private $data = [];

    /**
     * Construtor
     *
     * @param int|object $affiliate ID ou objeto do afiliado
     */
    public function __construct($affiliate = 0) {
        if (is_numeric($affiliate) && $affiliate > 0) {
            $this->id = (int) $affiliate;
            $this->read();
        } elseif (is_object($affiliate)) {
            $this->set_props($affiliate);
        }
    }

    /**
     * Lê dados do banco
     */
    private function read() {
        global $wpdb;
        
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lrp_affiliates WHERE id = %d",
            $this->id
        ));
        
        if ($data) {
            $this->set_props($data);
        }
    }

    /**
     * Define propriedades a partir de objeto
     *
     * @param object $data
     */
    private function set_props($data) {
        $this->id = (int) $data->id;
        $this->data = (array) $data;
    }

    // ========================================
    // GETTERS BÁSICOS
    // ========================================

    /**
     * Retorna ID do afiliado
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Retorna valor de um campo de dados
     *
     * @param string $key Chave do campo
     * @param mixed $default Valor padrão se não existir
     * @return mixed
     */
    public function get_data($key, $default = null) {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    /**
     * Retorna ID do usuário WordPress
     *
     * @return int
     */
    public function get_user_id() {
        return (int) ($this->data['user_id'] ?? 0);
    }

    /**
     * Retorna objeto do usuário WordPress
     *
     * @return WP_User|false
     */
    public function get_user() {
        return get_user_by('id', $this->get_user_id());
    }

    /**
     * Retorna nome de exibição
     *
     * @return string
     */
    public function get_display_name() {
        // Prioriza dados da tabela de afiliados (v1.7.0)
        $first_name = $this->get_first_name();
        $last_name = $this->get_last_name();
        
        if (!empty($first_name) || !empty($last_name)) {
            return trim($first_name . ' ' . $last_name);
        }
        
        // Fallback para dados do WordPress
        $user = $this->get_user();
        return $user ? $user->display_name : '';
    }

    /**
     * Retorna primeiro nome do afiliado
     *
     * @return string
     */
    public function get_first_name() {
        return $this->data['first_name'] ?? '';
    }

    /**
     * Retorna sobrenome do afiliado
     *
     * @return string
     */
    public function get_last_name() {
        return $this->data['last_name'] ?? '';
    }

    /**
     * Retorna email do afiliado
     *
     * @return string
     */
    public function get_email() {
        $user = $this->get_user();
        return $user ? $user->user_email : '';
    }

    /**
     * Retorna status do afiliado
     *
     * @return string pending|active|inactive|rejected
     */
    public function get_status() {
        return $this->data['status'] ?? 'pending';
    }

    /**
     * Verifica se está ativo
     *
     * @return bool
     */
    public function is_active() {
        return $this->get_status() === 'active';
    }

    /**
     * Verifica se existe
     *
     * @return bool
     */
    public function exists() {
        return $this->id > 0;
    }

    // ========================================
    // CÓDIGOS E LINKS
    // ========================================

    /**
     * Retorna código do cupom
     *
     * @return string
     */
    public function get_coupon_code() {
        return $this->data['coupon_code'] ?? '';
    }

    /**
     * Retorna código de referral
     *
     * @return string
     */
    public function get_referral_code() {
        return $this->data['referral_code'] ?? '';
    }

    /**
     * Retorna URL de referral
     *
     * @param int|null $product_id ID do produto (opcional)
     * @return string
     */
    public function get_referral_url($product_id = null) {
        $base_url = home_url('/');
        
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $base_url = $product->get_permalink();
            }
        }
        
        return add_query_arg('ref', $this->get_referral_code(), $base_url);
    }

    /**
     * Retorna URL de convite de sponsor
     *
     * @return string
     */
    public function get_sponsor_url() {
        $registration_page_id = get_option('lrp_registration_page_id');
        $base_url = $registration_page_id ? get_permalink($registration_page_id) : home_url('/seja-parceiro/');
        
        return add_query_arg('sponsor', $this->get_referral_code(), $base_url);
    }

    // ========================================
    // COMISSÕES E CONFIGURAÇÕES
    // ========================================

    /**
     * Retorna taxa de comissão por tipo
     *
     * @param string $type coupon|link|l2|l3
     * @return float
     */
    public function get_commission_rate($type) {
        $key = 'commission_rate_' . $type;
        $zero_key = 'zero_' . $key;
        
        // Se checkbox "zerar" está marcado, retorna 0
        if (!empty($this->data[$zero_key])) {
            return 0.0;
        }
        
        // Se tem valor individual definido (não null e não vazio)
        if (isset($this->data[$key]) && $this->data[$key] !== null && $this->data[$key] !== '') {
            return (float) $this->data[$key];
        }
        
        // Fallback para configuração global
        return LRP_Settings::instance()->get_commission_rate($type);
    }

    /**
     * Retorna desconto para cliente (%)
     *
     * @return float
     */
    public function get_customer_discount() {
        // Se checkbox "zerar" está marcado, retorna 0
        if (!empty($this->data['zero_customer_discount'])) {
            return 0.0;
        }
        
        // Se tem valor individual definido
        if (isset($this->data['customer_discount']) && $this->data['customer_discount'] !== null && $this->data['customer_discount'] !== '') {
            return (float) $this->data['customer_discount'];
        }
        
        // Fallback para configuração global
        return LRP_Settings::instance()->get_customer_discount();
    }

    /**
     * Retorna duração do cookie em dias
     *
     * @return int
     */
    public function get_cookie_days() {
        if (isset($this->data['cookie_days']) && $this->data['cookie_days'] !== null) {
            return (int) $this->data['cookie_days'];
        }
        
        return LRP_Settings::instance()->get_cookie_days();
    }

    /**
     * Retorna regra Guruja
     *
     * @return string
     */
    public function get_guruja_rule() {
        if (isset($this->data['guruja_rule']) && $this->data['guruja_rule'] !== null) {
            return $this->data['guruja_rule'];
        }
        
        return LRP_Settings::instance()->get_guruja_rule();
    }

    /**
     * Verifica se pode fazer auto-referência
     *
     * @return bool
     */
    public function can_self_refer() {
        $value = $this->data['can_self_refer'] ?? null;

        // NULL/vazio = herdar padrão global
        if ($value === null || $value === '') {
            return (bool) LRP_Settings::instance()->get('default_can_self_refer', true);
        }

        return (bool) $value;
    }

    // ========================================
    // TIPO DE FATURAMENTO E DADOS (v1.4.0)
    // ========================================

    /**
     * Retorna tipo de faturamento
     *
     * @return string pj|rpa
     */
    public function get_billing_type() {
        return $this->data['billing_type'] ?? 'pj';
    }

    /**
     * Verifica se é Pessoa Jurídica
     *
     * @return bool
     */
    public function is_pj() {
        return $this->get_billing_type() === 'pj';
    }

    /**
     * Verifica se é RPA (Pessoa Física)
     *
     * @return bool
     */
    public function is_rpa() {
        return $this->get_billing_type() === 'rpa';
    }

    // ========================================
    // DADOS DA EMPRESA (PJ)
    // ========================================

    /**
     * Retorna CNPJ da empresa
     *
     * @return string
     */
    public function get_company_cnpj() {
        return $this->data['company_cnpj'] ?? '';
    }

    /**
     * Retorna CNPJ da empresa formatado
     *
     * @return string
     */
    public function get_company_cnpj_formatted() {
        $cnpj = $this->get_company_cnpj();
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }

    /**
     * Retorna razão social da empresa
     *
     * @return string
     */
    public function get_company_name() {
        return $this->data['company_name'] ?? '';
    }

    /**
     * Verifica se pode emitir NF
     *
     * @return bool
     */
    public function can_issue_nf() {
        return (bool) ($this->data['can_issue_nf'] ?? 0);
    }

    // ========================================
    // DADOS PARA RPA (PESSOA FÍSICA) - v1.4.0
    // ========================================

    /**
     * Retorna CPF
     *
     * @return string
     */
    public function get_cpf() {
        return $this->data['cpf'] ?? '';
    }

    /**
     * Retorna CPF formatado (###.###.###-##)
     *
     * @return string
     */
    public function get_cpf_formatted() {
        $cpf = $this->get_cpf();
        if (strlen($cpf) !== 11) {
            return $cpf;
        }
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }

    /**
     * Retorna endereço completo
     *
     * @return string
     */
    public function get_full_address() {
        return $this->data['full_address'] ?? '';
    }

    /**
     * Retorna telefone
     *
     * @return string
     */
    public function get_phone() {
        return $this->data['phone'] ?? '';
    }

    /**
     * Retorna número de inscrição no INSS
     *
     * @return string
     */
    public function get_inss_number() {
        return $this->data['inss_number'] ?? '';
    }

    /**
     * Retorna descrição do serviço para RPA
     * Com fallback para configuração global
     *
     * @return string
     */
    public function get_rpa_service_description() {
        $description = $this->data['rpa_service_description'] ?? '';
        
        if (empty($description)) {
            return LRP_Settings::instance()->get('rpa_service_description', 'Serviços de divulgação e indicação comercial');
        }
        
        return $description;
    }

    /**
     * Retorna todos os dados necessários para emissão de RPA
     *
     * @return array
     */
    public function get_rpa_data() {
        $birth = $this->data['birth_date'] ?? '';
        $birth_formatted = '';
        if (!empty($birth) && $birth !== '0000-00-00') {
            $birth_formatted = date_i18n('d/m/Y', strtotime($birth));
        }

        return [
            'nome_completo'        => $this->get_display_name(),
            'cpf'                  => $this->get_cpf(),
            'cpf_formatted'        => $this->get_cpf_formatted(),
            'data_nascimento'      => $birth,
            'data_nascimento_fmt'  => $birth_formatted,
            'endereco'             => $this->get_full_address(),
            'telefone'             => $this->get_phone(),
            'email'                => $this->get_email(),
            'inss_number'          => $this->get_inss_number(),
            'descricao_servico'    => $this->get_rpa_service_description(),
        ];
    }

    // ========================================
    // PERIODICIDADE DE PAGAMENTO - v1.4.0
    // ========================================

    /**
     * Retorna período de pagamento em meses
     * Com fallback para configuração global
     *
     * @return int
     */
    public function get_payment_period_months() {
        $period = $this->data['payment_period_months'] ?? null;
        
        if ($period === null || $period === '') {
            return (int) LRP_Settings::instance()->get('default_payment_period_months', 3);
        }
        
        return (int) $period;
    }

    /**
     * Retorna label do período de pagamento
     *
     * @return string
     */
    public function get_payment_period_label() {
        $months = $this->get_payment_period_months();
        
        $labels = [
            1  => __('Mensal', 'lab-resumos-parceiros'),
            2  => __('Bimestral', 'lab-resumos-parceiros'),
            3  => __('Trimestral', 'lab-resumos-parceiros'),
            4  => __('Quadrimestral', 'lab-resumos-parceiros'),
            6  => __('Semestral', 'lab-resumos-parceiros'),
            12 => __('Anual', 'lab-resumos-parceiros'),
        ];
        
        return $labels[$months] ?? sprintf(__('A cada %d meses', 'lab-resumos-parceiros'), $months);
    }

    /**
     * Retorna próxima data de pagamento
     *
     * @return string|null Data no formato Y-m-d ou null
     */
    public function get_next_payment_date() {
        return $this->data['next_payment_date'] ?? null;
    }

    /**
     * Retorna próxima data de pagamento formatada
     *
     * @return string
     */
    public function get_next_payment_date_formatted() {
        $date = $this->get_next_payment_date();
        
        if (!$date) {
            return __('Não definida', 'lab-resumos-parceiros');
        }
        
        return date_i18n('d/m/Y', strtotime($date));
    }

    /**
     * Verifica se o pagamento está vencido
     *
     * @return bool
     */
    public function is_payment_due() {
        $next_date = $this->get_next_payment_date();
        
        if (!$next_date) {
            return false;
        }
        
        return strtotime($next_date) <= strtotime('today');
    }

    /**
     * Calcula e define a próxima data de pagamento
     *
     * @return string Nova data no formato Y-m-d
     */
    public function calculate_next_payment_date() {
        $period_months = $this->get_payment_period_months();
        $current_date = $this->get_next_payment_date() ?: date('Y-m-01');
        
        // Adiciona o período em meses
        $next_date = date('Y-m-01', strtotime("+{$period_months} months", strtotime($current_date)));
        
        return $next_date;
    }

    /**
     * Atualiza a próxima data de pagamento
     *
     * @param string|null $date Data no formato Y-m-d ou null para calcular automaticamente
     * @return bool
     */
    public function update_next_payment_date($date = null) {
        global $wpdb;
        
        if ($date === null) {
            $date = $this->calculate_next_payment_date();
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_affiliates',
            ['next_payment_date' => $date],
            ['id' => $this->id]
        );
        
        if ($result !== false) {
            $this->data['next_payment_date'] = $date;
        }
        
        return $result !== false;
    }

    // ========================================
    // MULTI-NÍVEL
    // ========================================

    /**
     * Retorna ID do sponsor
     *
     * @return int|null
     */
    public function get_sponsor_id() {
        return isset($this->data['sponsor_id']) ? (int) $this->data['sponsor_id'] : null;
    }

    /**
     * Retorna objeto do sponsor
     *
     * @return LRP_Affiliate|null
     */
    public function get_sponsor() {
        $sponsor_id = $this->get_sponsor_id();
        
        if (!$sponsor_id) {
            return null;
        }
        
        return new self($sponsor_id);
    }

    /**
     * Retorna nível do afiliado
     *
     * @return int
     */
    public function get_level() {
        return (int) ($this->data['level'] ?? 1);
    }

    /**
     * Retorna notas da aplicação
     *
     * @return string
     */
    public function get_application_notes() {
        return $this->data['application_notes'] ?? '';
    }

    // ========================================
    // ATIVIDADE DE REDE (COMPRESSÃO)
    // ========================================

    /**
     * Verifica se o afiliado está ativo para receber comissões de rede
     * 
     * Afiliado ativo: 3+ vendas nos últimos 3 meses fechados
     * Novos afiliados: Sempre ativos nos primeiros 3 meses
     *
     * @return bool
     */
    public function is_network_active() {
        if (!class_exists('LRP_Activity_Calculator')) {
            return true; // Fallback se classe não carregada
        }
        
        return LRP_Activity_Calculator::is_affiliate_active($this->id);
    }

    /**
     * Retorna o status de atividade de rede armazenado no banco
     *
     * @return bool
     */
    public function get_network_active_status() {
        return (bool) ($this->data['network_active'] ?? 1);
    }

    /**
     * Retorna informações completas de atividade de rede
     *
     * @return array
     */
    public function get_network_activity_info() {
        if (!class_exists('LRP_Activity_Calculator')) {
            return [
                'is_active'         => true,
                'is_new_affiliate'  => false,
                'sales_count'       => 0,
                'sales_required'    => 3,
                'sales_missing'     => 0,
                'progress_percent'  => 100,
                'message'           => __('Informação não disponível', 'lab-resumos-parceiros'),
                'status_label'      => __('Ativo', 'lab-resumos-parceiros'),
                'status_class'      => 'active',
            ];
        }
        
        return LRP_Activity_Calculator::get_activity_info($this->id);
    }

    /**
     * Verifica se é um novo afiliado (período de proteção)
     *
     * @return bool
     */
    public function is_new_affiliate() {
        if (!class_exists('LRP_Activity_Calculator')) {
            return false;
        }
        
        return LRP_Activity_Calculator::is_new_affiliate($this->id);
    }

    // ========================================
    // DADOS FINANCEIROS
    // ========================================

    /**
     * Retorna método de pagamento
     *
     * @return string pix|bank_transfer
     */
    public function get_payment_method() {
        return $this->data['payment_method'] ?? 'pix';
    }

    /**
     * Retorna dados bancários completos
     *
     * @return array
     */
    public function get_payment_data() {
        return [
            'method'            => $this->get_payment_method(),
            'pix_key_type'      => $this->data['pix_key_type'] ?? null,
            'pix_key'           => $this->decrypt_pix_key($this->data['pix_key'] ?? ''),
            'bank_name'         => $this->data['bank_name'] ?? '',
            'bank_agency'       => $this->data['bank_agency'] ?? '',
            'bank_account'      => $this->data['bank_account'] ?? '',
            'bank_account_type' => $this->data['bank_account_type'] ?? 'checking',
            'holder_name'       => $this->data['holder_name'] ?? '',
            'holder_document'   => $this->data['holder_document'] ?? '',
        ];
    }

    /**
     * Retorna dados PIX
     *
     * @return array
     */
    public function get_pix_data() {
        return [
            'type' => $this->data['pix_key_type'] ?? null,
            'key'  => $this->decrypt_pix_key($this->data['pix_key'] ?? ''),
        ];
    }

    // ========================================
    // ESTATÍSTICAS
    // ========================================

    /**
     * Retorna total de vendas
     *
     * @return int
     */
    public function get_total_sales() {
        return (int) ($this->data['total_sales'] ?? 0);
    }

    /**
     * Retorna receita total
     *
     * @return float
     */
    public function get_total_revenue() {
        return (float) ($this->data['total_revenue'] ?? 0);
    }

    /**
     * Retorna total de comissões
     *
     * @return float
     */
    public function get_total_commissions() {
        return (float) ($this->data['total_commissions'] ?? 0);
    }

    /**
     * Retorna total já pago
     *
     * @return float
     */
    public function get_total_paid() {
        return (float) ($this->data['total_paid'] ?? 0);
    }

    /**
     * Retorna saldo atual
     *
     * @return float
     */
    public function get_current_balance() {
        return (float) ($this->data['current_balance'] ?? 0);
    }

    /**
     * Atualiza estatísticas do afiliado
     *
     * @return bool
     */
    public function refresh_stats() {
        global $wpdb;
        
        // Calcula totais a partir das tabelas
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT r.id) as total_sales,
                COALESCE(SUM(r.commission_base), 0) as total_revenue,
                (SELECT COALESCE(SUM(commission_amount), 0) FROM {$wpdb->prefix}lrp_commissions WHERE affiliate_id = %d AND status IN ('approved', 'paid')) as total_commissions,
                (SELECT COALESCE(SUM(commission_amount), 0) FROM {$wpdb->prefix}lrp_commissions WHERE affiliate_id = %d AND status = 'paid') as total_paid
             FROM {$wpdb->prefix}lrp_referrals r
             WHERE r.affiliate_id = %d AND r.status IN ('approved', 'paid')",
            $this->id,
            $this->id,
            $this->id
        ));
        
        if (!$stats) {
            return false;
        }
        
        $total_commissions = (float) $stats->total_commissions;
        $total_paid = (float) $stats->total_paid;
        
        // Adiciona ajustes pendentes ao saldo (v1.4.0)
        $pending_adjustments = 0.0;
        if (class_exists('LRP_Adjustment')) {
            $pending_adjustments = LRP_Adjustment::get_pending_sum($this->id);
        }
        
        // Saldo = Comissões aprovadas - Pagas + Ajustes pendentes
        $current_balance = $total_commissions - $total_paid + $pending_adjustments;
        
        // Atualiza no banco
        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_affiliates',
            [
                'total_sales'       => (int) $stats->total_sales,
                'total_revenue'     => (float) $stats->total_revenue,
                'total_commissions' => $total_commissions,
                'total_paid'        => $total_paid,
                'current_balance'   => $current_balance,
            ],
            ['id' => $this->id],
            ['%d', '%f', '%f', '%f', '%f'],
            ['%d']
        );
        
        // Atualiza dados locais
        $this->data['total_sales'] = (int) $stats->total_sales;
        $this->data['total_revenue'] = (float) $stats->total_revenue;
        $this->data['total_commissions'] = $total_commissions;
        $this->data['total_paid'] = $total_paid;
        $this->data['current_balance'] = $current_balance;
        
        // Limpa cache
        delete_transient('lrp_affiliate_stats_' . $this->id);
        
        return $result !== false;
    }

    // ========================================
    // CRUD ESTÁTICO
    // ========================================

    /**
     * Busca afiliado por ID
     *
     * @param int $id
     * @return LRP_Affiliate|null
     */
    public static function get($id) {
        $affiliate = new self($id);
        return $affiliate->exists() ? $affiliate : null;
    }

    /**
     * Busca afiliado por user_id
     *
     * @param int $user_id
     * @return LRP_Affiliate|null
     */
    public static function get_by_user_id($user_id) {
        global $wpdb;
        
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lrp_affiliates WHERE user_id = %d",
            $user_id
        ));
        
        return $id ? new self($id) : null;
    }

    /**
     * Busca afiliado por código do cupom
     *
     * @param string $coupon_code
     * @return LRP_Affiliate|null
     */
    public static function get_by_coupon_code($coupon_code) {
        global $wpdb;
        
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lrp_affiliates WHERE coupon_code = %s",
            $coupon_code
        ));
        
        return $id ? new self($id) : null;
    }

    /**
     * Busca afiliado por código de referral
     *
     * @param string $referral_code
     * @return LRP_Affiliate|null
     */
    public static function get_by_referral_code($referral_code) {
        global $wpdb;
        
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lrp_affiliates WHERE referral_code = %s",
            $referral_code
        ));
        
        return $id ? new self($id) : null;
    }

    /**
     * Cria novo afiliado
     *
     * @param array $data
     * @return LRP_Affiliate|WP_Error
     */
    public static function create($data) {
        global $wpdb;
        
        // Validações básicas
        if (empty($data['user_id'])) {
            return new WP_Error('missing_user', __('ID do usuário é obrigatório.', 'lab-resumos-parceiros'));
        }
        
        // Verifica se já existe afiliado para este usuário
        $existing = self::get_by_user_id($data['user_id']);
        if ($existing) {
            return new WP_Error('duplicate', __('Este usuário já é um afiliado.', 'lab-resumos-parceiros'));
        }
        
        // Gera códigos únicos se não fornecidos
        if (empty($data['coupon_code'])) {
            $data['coupon_code'] = self::generate_coupon_code($data['user_id']);
        }
        
        if (empty($data['referral_code'])) {
            $data['referral_code'] = self::generate_referral_code();
        }
        
        // Dados padrão
        $insert_data = [
            'user_id'           => (int) $data['user_id'],
            'status'            => $data['status'] ?? 'pending',
            'coupon_code'       => $data['coupon_code'],
            'referral_code'     => $data['referral_code'],
            'sponsor_id'        => $data['sponsor_id'] ?? null,
            'level'             => $data['level'] ?? 1,
            // Dados de identificação (v1.7.0 - sempre obrigatórios)
            'first_name'        => $data['first_name'] ?? null,
            'last_name'         => $data['last_name'] ?? null,
            'cpf'               => $data['cpf'] ?? null,
            // Tipo de faturamento (v1.4.0)
            'billing_type'      => $data['billing_type'] ?? 'pj',
            // Dados PJ
            'company_cnpj'      => $data['company_cnpj'] ?? null,
            'company_name'      => $data['company_name'] ?? null,
            'can_issue_nf'      => $data['can_issue_nf'] ?? 0,
            // Dados RPA (v1.4.0)
            'full_address'      => $data['full_address'] ?? null,
            'phone'             => $data['phone'] ?? null,
            'inss_number'       => $data['inss_number'] ?? null,
            'birth_date'        => $data['birth_date'] ?? null,
            'rpa_service_description' => $data['rpa_service_description'] ?? null,
            // Pagamento
            'holder_name'       => $data['holder_name'] ?? null,
            'holder_document'   => $data['holder_document'] ?? null,
            // Periodicidade (v1.4.0)
            'payment_period_months' => $data['payment_period_months'] ?? LRP_Settings::instance()->get('default_payment_period_months', 3),
            'next_payment_date' => $data['next_payment_date'] ?? date('Y-m-01', strtotime('first day of next month')),
            // Outros
            'application_notes' => $data['application_notes'] ?? null,
            'application_ip'    => $data['application_ip'] ?? null,
            'created_at'        => current_time('mysql'),
        ];
        
        // Criptografa PIX key se fornecida
        if (!empty($data['pix_key'])) {
            $insert_data['pix_key'] = self::encrypt_pix_key_static($data['pix_key']);
            $insert_data['pix_key_type'] = $data['pix_key_type'] ?? null;
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'lrp_affiliates',
            $insert_data
        );
        
        if (!$result) {
            return new WP_Error('db_error', __('Erro ao criar afiliado.', 'lab-resumos-parceiros'));
        }
        
        $affiliate = new self($wpdb->insert_id);
        
        // Adiciona role ao usuário
        $user = get_user_by('id', $data['user_id']);
        if ($user) {
            $user->add_role('lrp_affiliate');
        }
        
        // Dispara action
        do_action('lrp_affiliate_created', $affiliate);
        
        return $affiliate;
    }

    /**
     * Atualiza afiliado
     *
     * @param array $data
     * @return bool
     */
    public function update($data) {
        global $wpdb;
        
        if (!$this->exists()) {
            return false;
        }
        
        // Campos permitidos para atualização
        $allowed = [
            'status', 'sponsor_id', 'level',
            // Cupom (editável pelo admin)
            'coupon_code',
            // Tipo de faturamento (v1.4.0)
            'billing_type',
            // Dados PJ
            'company_cnpj', 'company_name', 'can_issue_nf',
            // Dados RPA (v1.4.0)
            'cpf', 'full_address', 'phone', 'inss_number', 'birth_date', 'rpa_service_description',
            // Comissões
            'commission_rate_coupon', 'commission_rate_link',
            'commission_rate_l2', 'commission_rate_l3',
            'zero_commission_rate_coupon', 'zero_commission_rate_link',
            'zero_commission_rate_l2', 'zero_commission_rate_l3',
            'customer_discount', 'zero_customer_discount',
            'cookie_days', 'guruja_rule', 'can_self_refer',
            // Pagamento
            'payment_method', 'pix_key_type', 'pix_key',
            'bank_name', 'bank_agency', 'bank_account',
            'bank_account_type', 'holder_name', 'holder_document',
            // Periodicidade (v1.4.0)
            'payment_period_months', 'next_payment_date',
            // Notas
            'admin_notes',
        ];
        
        $update_data = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                // Criptografa PIX key
                if ($key === 'pix_key' && !empty($value)) {
                    $value = self::encrypt_pix_key_static($value);
                }
                $update_data[$key] = $value;
            }
        }
        
        if (empty($update_data)) {
            return true;
        }
        
        $old_status = $this->get_status();
        
        $result = $wpdb->update(
            $wpdb->prefix . 'lrp_affiliates',
            $update_data,
            ['id' => $this->id]
        );
        
        if ($result !== false) {
            // Atualiza dados locais
            foreach ($update_data as $key => $value) {
                $this->data[$key] = $value;
            }
            
            // Verifica mudança de status
            $new_status = $data['status'] ?? $old_status;
            
            if ($old_status !== $new_status) {
                if ($new_status === 'active' && $old_status === 'pending') {
                    // Aprovado - cria cupom WooCommerce
                    $this->create_woocommerce_coupon();
                    
                    // Atualiza timestamp de aprovação
                    $wpdb->update(
                        $wpdb->prefix . 'lrp_affiliates',
                        ['approved_at' => current_time('mysql')],
                        ['id' => $this->id]
                    );
                    
                    do_action('lrp_affiliate_approved', $this);
                } elseif ($new_status === 'rejected') {
                    do_action('lrp_affiliate_rejected', $this, $data['rejection_reason'] ?? '');
                }
            }
            
            // Se alterou desconto do cliente, atualiza cupom WooCommerce
            if (isset($data['customer_discount']) || isset($data['zero_customer_discount'])) {
                $this->update_woocommerce_coupon();
            }
            
            // Se alterou código do cupom, atualiza no WooCommerce
            if (isset($data['coupon_code']) && !empty($data['original_coupon_code'])) {
                $this->update_woocommerce_coupon_code($data['original_coupon_code'], $data['coupon_code']);
            }
        }
        
        return $result !== false;
    }

    /**
     * Remove afiliado (soft delete)
     *
     * @return bool
     */
    public function delete() {
        global $wpdb;
        
        if (!$this->exists()) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'lrp_affiliates',
            [
                'status'     => 'inactive',
                'deleted_at' => current_time('mysql'),
            ],
            ['id' => $this->id]
        ) !== false;
    }

    // ========================================
    // UTILITÁRIOS
    // ========================================

    /**
     * Gera código de cupom único
     *
     * @param int $user_id
     * @return string
     */
    public static function generate_coupon_code($user_id) {
        $user = get_user_by('id', $user_id);
        
        if ($user) {
            // Usa primeiro nome do usuário
            $name = sanitize_title($user->first_name ?: $user->display_name);
            $name = preg_replace('/[^a-z0-9]/', '', strtoupper(substr($name, 0, 10)));
        } else {
            $name = 'PARCEIRO';
        }
        
        // Adiciona número aleatório
        $code = $name . rand(10, 99);
        
        // Verifica se já existe
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lrp_affiliates WHERE coupon_code = %s",
            $code
        ));
        
        if ($exists) {
            return self::generate_coupon_code($user_id);
        }
        
        return $code;
    }

    /**
     * Gera código de referral único
     *
     * @return string
     */
    public static function generate_referral_code() {
        $code = strtolower(wp_generate_password(8, false));
        
        // Verifica se já existe
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lrp_affiliates WHERE referral_code = %s",
            $code
        ));
        
        if ($exists) {
            return self::generate_referral_code();
        }
        
        return $code;
    }

    /**
     * Cria cupom no WooCommerce
     *
     * @return int|false ID do cupom ou false
     */
    public function create_woocommerce_coupon() {
        $coupon_code = $this->get_coupon_code();
        
        // Verifica se cupom já existe
        $existing = new WC_Coupon($coupon_code);
        if ($existing->get_id() > 0) {
            // Atualiza desconto se já existe (pode ter mudado)
            $discount = $this->get_customer_discount();
            $existing->set_amount($discount);
            $existing->save();
            return $existing->get_id();
        }
        
        // Obtém desconto do afiliado (individual ou global)
        $discount = $this->get_customer_discount();
        
        // Cria cupom
        $coupon = new WC_Coupon();
        $coupon->set_code($coupon_code);
        $coupon->set_discount_type('percent');
        $coupon->set_amount($discount);
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(0);
        $coupon->set_usage_limit_per_user(0);
        $coupon->set_free_shipping(false);
        $coupon->set_exclude_sale_items(false);
        
        // Meta dados para identificação
        $coupon->add_meta_data('_lrp_affiliate_id', $this->id);
        $coupon->add_meta_data('_lrp_is_affiliate_coupon', true);
        
        $coupon_id = $coupon->save();
        
        return is_wp_error($coupon_id) ? false : $coupon_id;
    }

    /**
     * Atualiza cupom WooCommerce com novo desconto
     *
     * @return bool
     */
    public function update_woocommerce_coupon() {
        $coupon_code = $this->get_coupon_code();
        $coupon = new WC_Coupon($coupon_code);
        
        if ($coupon->get_id() <= 0) {
            return false;
        }
        
        $discount = $this->get_customer_discount();
        $coupon->set_amount($discount);
        $coupon->save();
        
        return true;
    }

    /**
     * Atualiza código do cupom no WooCommerce
     * 
     * Deleta o cupom antigo e cria um novo com o código atualizado.
     *
     * @param string $old_code Código antigo do cupom
     * @param string $new_code Novo código do cupom
     * @return bool
     */
    public function update_woocommerce_coupon_code($old_code, $new_code) {
        // Busca cupom antigo
        $old_coupon = new WC_Coupon($old_code);
        
        if ($old_coupon->get_id() > 0) {
            // Salva configurações do cupom antigo
            $discount_type = $old_coupon->get_discount_type();
            $amount = $old_coupon->get_amount();
            $individual_use = $old_coupon->get_individual_use();
            
            // Deleta cupom antigo
            $old_coupon->delete(true);
            
            lrp_log('Cupom WooCommerce deletado para renomeação', [
                'affiliate_id' => $this->id,
                'old_code'     => $old_code,
                'new_code'     => $new_code,
            ]);
        }
        
        // Cria novo cupom com as mesmas configurações
        $new_coupon = new WC_Coupon();
        $new_coupon->set_code($new_code);
        $new_coupon->set_discount_type('percent');
        $new_coupon->set_amount($this->get_customer_discount());
        $new_coupon->set_individual_use(true);
        $new_coupon->set_usage_limit(0);
        $new_coupon->set_usage_limit_per_user(0);
        $new_coupon->set_free_shipping(false);
        $new_coupon->set_exclude_sale_items(false);
        
        // Meta dados para identificação
        $new_coupon->add_meta_data('_lrp_affiliate_id', $this->id);
        $new_coupon->add_meta_data('_lrp_is_affiliate_coupon', true);
        
        $coupon_id = $new_coupon->save();
        
        if (!is_wp_error($coupon_id) && $coupon_id > 0) {
            lrp_log('Cupom WooCommerce criado com novo código', [
                'affiliate_id' => $this->id,
                'coupon_id'    => $coupon_id,
                'new_code'     => $new_code,
            ]);
            return true;
        }
        
        return false;
    }

    /**
     * Criptografa chave PIX
     *
     * @param string $key
     * @return string
     */
    private function decrypt_pix_key($encrypted_key) {
        if (empty($encrypted_key)) {
            return '';
        }
        
        return self::decrypt_pix_key_static($encrypted_key);
    }

    /**
     * Criptografa chave PIX (estático)
     *
     * @param string $key
     * @return string
     */
    public static function encrypt_pix_key_static($key) {
        if (empty($key)) {
            return '';
        }
        
        $encryption_key = hash('sha256', wp_salt('lrp_pix_encryption'));
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = openssl_encrypt($key, 'AES-256-CBC', $encryption_key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Descriptografa chave PIX (estático)
     *
     * @param string $encrypted_key
     * @return string
     */
    public static function decrypt_pix_key_static($encrypted_key) {
        if (empty($encrypted_key)) {
            return '';
        }
        
        $encryption_key = hash('sha256', wp_salt('lrp_pix_encryption'));
        $data = base64_decode($encrypted_key);
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $encryption_key, 0, $iv);
        
        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Loga atividade do afiliado
     *
     * @param string $action
     * @param array $details
     */
    public function log_activity($action, $details = []) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'lrp_activity_log',
            [
                'affiliate_id' => $this->id,
                'action'       => $action,
                'details'      => wp_json_encode($details),
                'user_id'      => get_current_user_id(),
                'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? '',
                'created_at'   => current_time('mysql'),
            ]
        );
    }

    /**
     * Retorna chave PIX mascarada (para exibição)
     *
     * @return string
     */
    public function get_masked_pix_key() {
        $pix_data = $this->get_pix_data();
        $key = $pix_data['key'] ?? '';
        
        if (empty($key)) {
            return '';
        }
        
        $length = strlen($key);
        
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        
        // Mostra primeiros 3 e últimos 3 caracteres
        return substr($key, 0, 3) . str_repeat('*', max(0, $length - 6)) . substr($key, -3);
    }

    /**
     * Retorna chave PIX descriptografada
     *
     * @return string
     */
    public function get_decrypted_pix_key() {
        return $this->decrypt_pix_key($this->data['pix_key'] ?? '');
    }

    // ========================================
    // VALIDAÇÕES ESTÁTICAS
    // ========================================

    /**
     * Valida CPF
     *
     * @param string $cpf
     * @return bool
     */
    public static function validate_cpf($cpf) {
        // Remove caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        // Verifica se tem 11 dígitos
        if (strlen($cpf) !== 11) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        // Validação do primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $cpf[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        $digit1 = ($remainder < 2) ? 0 : (11 - $remainder);
        
        if ((int) $cpf[9] !== $digit1) {
            return false;
        }
        
        // Validação do segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int) $cpf[$i] * (11 - $i);
        }
        $remainder = $sum % 11;
        $digit2 = ($remainder < 2) ? 0 : (11 - $remainder);
        
        return (int) $cpf[10] === $digit2;
    }

    /**
     * Valida CNPJ
     *
     * @param string $cnpj
     * @return bool
     */
    public static function validate_cnpj($cnpj) {
        // Remove caracteres não numéricos
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        // Verifica se tem 14 dígitos
        if (strlen($cnpj) !== 14) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }
        
        // Validação do primeiro dígito verificador
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $cnpj[$i] * $weights1[$i];
        }
        $remainder = $sum % 11;
        $digit1 = ($remainder < 2) ? 0 : (11 - $remainder);
        
        if ((int) $cnpj[12] !== $digit1) {
            return false;
        }
        
        // Validação do segundo dígito verificador
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $cnpj[$i] * $weights2[$i];
        }
        $remainder = $sum % 11;
        $digit2 = ($remainder < 2) ? 0 : (11 - $remainder);
        
        return (int) $cnpj[13] === $digit2;
    }
}

