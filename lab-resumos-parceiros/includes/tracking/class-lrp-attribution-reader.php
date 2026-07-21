<?php
/**
 * Leitor de Order Attribution do WooCommerce
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Attribution_Reader
 * 
 * Lê dados de atribuição de pedidos do WooCommerce 8.5+.
 */
class LRP_Attribution_Reader {

    /**
     * Prefixo das meta keys de atribuição
     */
    const META_PREFIX = '_wc_order_attribution_';

    /**
     * Instância única
     *
     * @var LRP_Attribution_Reader|null
     */
    private static $instance = null;

    /**
     * Mapeamento de domínios para nomes amigáveis
     *
     * @var array
     */
    private $source_mappings = [
        'instagram.com'     => 'Instagram',
        'l.instagram.com'   => 'Instagram',
        'facebook.com'      => 'Facebook',
        'l.facebook.com'    => 'Facebook',
        'm.facebook.com'    => 'Facebook',
        'fb.com'            => 'Facebook',
        'youtube.com'       => 'YouTube',
        'youtu.be'          => 'YouTube',
        'm.youtube.com'     => 'YouTube',
        'tiktok.com'        => 'TikTok',
        'vm.tiktok.com'     => 'TikTok',
        'twitter.com'       => 'Twitter/X',
        'x.com'             => 'Twitter/X',
        't.co'              => 'Twitter/X',
        'wa.me'             => 'WhatsApp',
        'web.whatsapp.com'  => 'WhatsApp',
        'api.whatsapp.com'  => 'WhatsApp',
        't.me'              => 'Telegram',
        'telegram.me'       => 'Telegram',
        'telegram.org'      => 'Telegram',
        'google.com'        => 'Google',
        'google.com.br'     => 'Google',
        'linkedin.com'      => 'LinkedIn',
        'pinterest.com'     => 'Pinterest',
        'reddit.com'        => 'Reddit',
        'bing.com'          => 'Bing',
        'yahoo.com'         => 'Yahoo',
    ];

    /**
     * Retorna instância única
     *
     * @return LRP_Attribution_Reader
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
    private function __construct() {}

    /**
     * Verifica se Order Attribution está habilitado no WooCommerce
     *
     * @return bool
     */
    public function is_attribution_enabled() {
        return get_option('woocommerce_feature_order_attribution_enabled', 'yes') === 'yes';
    }

    /**
     * Obtém todos os dados de atribuição de um pedido
     *
     * @param int|WC_Order $order ID do pedido ou objeto WC_Order
     * @return array
     */
    public function get_order_attribution($order) {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }
        
        if (!$order) {
            return [];
        }
        
        return [
            'origin'           => $order->get_meta(self::META_PREFIX . 'origin'),
            'source_type'      => $order->get_meta(self::META_PREFIX . 'source_type'),
            'utm_source'       => $order->get_meta(self::META_PREFIX . 'utm_source'),
            'utm_medium'       => $order->get_meta(self::META_PREFIX . 'utm_medium'),
            'utm_campaign'     => $order->get_meta(self::META_PREFIX . 'utm_campaign'),
            'utm_content'      => $order->get_meta(self::META_PREFIX . 'utm_content'),
            'utm_term'         => $order->get_meta(self::META_PREFIX . 'utm_term'),
            'referrer'         => $order->get_meta(self::META_PREFIX . 'referrer'),
            'device_type'      => $order->get_meta(self::META_PREFIX . 'device_type'),
            'session_pages'    => $order->get_meta(self::META_PREFIX . 'session_pages'),
            'session_count'    => $order->get_meta(self::META_PREFIX . 'session_count'),
            'user_agent'       => $order->get_meta(self::META_PREFIX . 'user_agent'),
        ];
    }

    /**
     * Obtém a fonte de tráfego normalizada de um pedido
     *
     * @param int|WC_Order $order
     * @return string
     */
    public function get_traffic_source($order) {
        $attribution = $this->get_order_attribution($order);
        
        // Prioridade 1: UTM Source
        if (!empty($attribution['utm_source'])) {
            return $this->normalize_source_name($attribution['utm_source']);
        }
        
        // Prioridade 2: Referrer
        if (!empty($attribution['referrer'])) {
            return $this->parse_traffic_source($attribution['referrer']);
        }
        
        // Prioridade 3: Source Type
        if (!empty($attribution['source_type'])) {
            return $this->map_source_type($attribution['source_type']);
        }
        
        return 'Direct';
    }

    /**
     * Parseia fonte de tráfego a partir de URL de referrer
     *
     * @param string $referrer
     * @return string
     */
    public function parse_traffic_source($referrer) {
        if (empty($referrer)) {
            return 'Direct';
        }
        
        $domain = parse_url($referrer, PHP_URL_HOST);
        
        if (!$domain) {
            return 'Direct';
        }
        
        // Remove www.
        $domain = preg_replace('/^www\./', '', strtolower($domain));
        
        // Verifica mapeamento direto
        if (isset($this->source_mappings[$domain])) {
            return $this->source_mappings[$domain];
        }
        
        // Verifica subdomínios
        foreach ($this->source_mappings as $pattern => $name) {
            if (strpos($domain, $pattern) !== false) {
                return $name;
            }
        }
        
        // Retorna domínio limpo se não reconhecido
        return ucfirst($domain);
    }

    /**
     * Normaliza nome da fonte
     *
     * @param string $source
     * @return string
     */
    public function normalize_source_name($source) {
        if (empty($source)) {
            return 'Direct';
        }
        
        $source = strtolower(trim($source));
        
        $mappings = [
            'ig'        => 'Instagram',
            'insta'     => 'Instagram',
            'instagram' => 'Instagram',
            'fb'        => 'Facebook',
            'facebook'  => 'Facebook',
            'yt'        => 'YouTube',
            'youtube'   => 'YouTube',
            'tt'        => 'TikTok',
            'tiktok'    => 'TikTok',
            'tw'        => 'Twitter/X',
            'twitter'   => 'Twitter/X',
            'x'         => 'Twitter/X',
            'wpp'       => 'WhatsApp',
            'whatsapp'  => 'WhatsApp',
            'wa'        => 'WhatsApp',
            'zap'       => 'WhatsApp',
            'tg'        => 'Telegram',
            'telegram'  => 'Telegram',
            'google'    => 'Google',
            'gads'      => 'Google Ads',
            'linkedin'  => 'LinkedIn',
            'ln'        => 'LinkedIn',
            'email'     => 'Email',
            'newsletter'=> 'Email',
            'blog'      => 'Blog',
            'organic'   => 'Orgânico',
        ];
        
        return $mappings[$source] ?? ucfirst($source);
    }

    /**
     * Mapeia source_type do WooCommerce
     *
     * @param string $source_type
     * @return string
     */
    private function map_source_type($source_type) {
        $mappings = [
            'organic'  => 'Orgânico',
            'referral' => 'Referência',
            'utm'      => 'Campanha',
            'typein'   => 'Direct',
            'admin'    => 'Admin',
        ];
        
        return $mappings[$source_type] ?? ucfirst($source_type);
    }

    /**
     * Obtém tipo de dispositivo normalizado
     *
     * @param int|WC_Order $order
     * @return string
     */
    public function get_device_type($order) {
        $attribution = $this->get_order_attribution($order);
        
        if (!empty($attribution['device_type'])) {
            $device = strtolower($attribution['device_type']);
            
            $mappings = [
                'mobile'  => 'Mobile',
                'desktop' => 'Desktop',
                'tablet'  => 'Tablet',
            ];
            
            return $mappings[$device] ?? 'Unknown';
        }
        
        return 'Unknown';
    }

    /**
     * Obtém dados de campanha UTM de um pedido
     *
     * @param int|WC_Order $order
     * @return array
     */
    public function get_utm_data($order) {
        $attribution = $this->get_order_attribution($order);
        
        return [
            'source'   => $attribution['utm_source'] ?? '',
            'medium'   => $attribution['utm_medium'] ?? '',
            'campaign' => $attribution['utm_campaign'] ?? '',
            'content'  => $attribution['utm_content'] ?? '',
            'term'     => $attribution['utm_term'] ?? '',
        ];
    }

    /**
     * Verifica se pedido tem dados de atribuição
     *
     * @param int|WC_Order $order
     * @return bool
     */
    public function has_attribution_data($order) {
        $attribution = $this->get_order_attribution($order);
        
        return !empty($attribution['source_type']) 
            || !empty($attribution['utm_source']) 
            || !empty($attribution['referrer']);
    }

    /**
     * Obtém resumo de atribuição para exibição
     *
     * @param int|WC_Order $order
     * @return array
     */
    public function get_attribution_summary($order) {
        $attribution = $this->get_order_attribution($order);
        
        return [
            'source'  => $this->get_traffic_source($order),
            'device'  => $this->get_device_type($order),
            'pages'   => (int) ($attribution['session_pages'] ?? 0),
            'visits'  => (int) ($attribution['session_count'] ?? 1),
        ];
    }

    /**
     * Adiciona mapeamento customizado de fonte
     *
     * @param string $domain
     * @param string $name
     */
    public function add_source_mapping($domain, $name) {
        $this->source_mappings[strtolower($domain)] = $name;
    }
}

