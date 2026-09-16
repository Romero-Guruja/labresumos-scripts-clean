<?php
/**
 * Busca de pedidos por CPF na tela de Pedidos (WooCommerce HPOS).
 *
 * Problema: com HPOS ativo, a busca da tela de pedidos cobre ID, e-mail,
 * cliente e produtos - mas NAO o CPF, que e o dado que o aluno informa no
 * atendimento. O CPF fica em wp_wc_orders_meta (_billing_cpf, gravado pelo
 * woocommerce-extra-checkout-fields-for-brazil) e nao era alcancado por
 * nenhum filtro da busca.
 *
 * Solucao: usar os filtros oficiais do HPOS (WooCommerce 8.9+) para declarar
 * um filtro de busca "CPF" e fornecer JOIN/WHERE proprios, sem tocar em
 * arquivo do WooCommerce. Fica disponivel tanto no filtro "CPF" explicito
 * quanto na busca "Tudo".
 *
 * Normalizacao: o CPF e gravado de formas diferentes ao longo do tempo (com
 * e sem pontuacao). Comparamos apenas digitos nos dois lados, entao
 * "123.456.789-00" e "12345678900" encontram o mesmo pedido.
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Order_Search
 */
class LRA_Order_Search {

    /**
     * Nome do filtro de busca.
     */
    const FILTER = 'lra_cpf';

    /**
     * Alias da tabela de meta no JOIN.
     */
    const ALIAS = 'lra_cpf_meta';

    /**
     * Meta keys que guardam CPF no pedido.
     *
     * _billing_cpf e o do plugin de campos brasileiros; os demais aparecem em
     * pedidos antigos e no fluxo Guruja.
     *
     * @var string[]
     */
    const META_KEYS = ['_billing_cpf', 'billing_cpf', '_lrg_guruja_cpf', '_shipping_cpf'];

    /**
     * Registra os hooks.
     */
    public static function init() {
        add_filter('woocommerce_hpos_admin_search_filters', [__CLASS__, 'add_search_filter']);
        add_filter('woocommerce_hpos_generate_join_for_search_filter', [__CLASS__, 'generate_join'], 10, 4);
        add_filter('woocommerce_hpos_generate_where_for_search_filter', [__CLASS__, 'generate_where'], 10, 4);

        // Faz o CPF funcionar tambem com o filtro em "Tudo" (ver metodo).
        add_filter('woocommerce_order_list_table_prepare_items_query_args', [__CLASS__, 'route_cpf_search']);
    }

    /**
     * Roteia a busca para o filtro de CPF quando o termo e um CPF completo.
     *
     * Por que e necessario: na opcao "Tudo" o WooCommerce NAO consulta filtros
     * customizados - ele percorre uma lista fixa de filtros do core
     * (order_id, transaction_id, customer_email, customers, products), e para
     * 'customers' ele nem chega a aplicar o filtro de WHERE (retorna antes,
     * no proprio core). Ou seja, um filtro nosso so participa quando
     * explicitamente selecionado no seletor.
     *
     * Como o atendimento normalmente cola o CPF sem trocar o seletor, quando o
     * termo tem exatamente 11 digitos assumimos CPF e redirecionamos a busca
     * para o nosso filtro. Isso e seguro e nao ambiguo: o maior ID de pedido
     * do site tem 4 digitos, entao um numero de 11 digitos nunca e um pedido.
     *
     * Termo parcial (menos de 11 digitos) em "Tudo" segue o comportamento
     * nativo; para busca parcial por CPF basta escolher "CPF" no seletor.
     *
     * @param array $args
     * @return array
     */
    public static function route_cpf_search($args) {
        if (empty($args['s'])) {
            return $args;
        }

        $current = $args['search_filter'] ?? 'all';
        if (!in_array($current, ['all', ''], true)) {
            return $args;
        }

        $digits = preg_replace('/\D/', '', (string) $args['s']);
        if (11 !== strlen($digits)) {
            return $args;
        }

        $args['search_filter'] = self::FILTER;

        return $args;
    }

    /**
     * Adiciona "CPF" ao seletor de filtros da busca de pedidos.
     *
     * Inserido antes de "Tudo" para ficar junto dos demais campos.
     *
     * @param array $options
     * @return array
     */
    public static function add_search_filter($options) {
        $label = __('CPF', 'lab-resumos-acessos');

        if (!isset($options['all'])) {
            $options[self::FILTER] = $label;
            return $options;
        }

        $reordered = [];
        foreach ($options as $key => $value) {
            if ('all' === $key) {
                $reordered[self::FILTER] = $label;
            }
            $reordered[$key] = $value;
        }

        return $reordered;
    }

    /**
     * Extrai somente os digitos do termo buscado.
     *
     * Retorna string vazia quando o termo nao parece um CPF (evita JOIN
     * inutil em buscas por nome/e-mail dentro do filtro "Tudo").
     *
     * @param string $search_term
     * @return string
     */
    private static function digits($search_term) {
        $digits = preg_replace('/\D/', '', (string) $search_term);

        // CPF tem 11 digitos; aceitamos buscas parciais de 3+ digitos, mas
        // nunca mais de 11 (aí não é CPF).
        if (strlen($digits) < 3 || strlen($digits) > 11) {
            return '';
        }

        return $digits;
    }

    /**
     * Este filtro deve agir para o filtro de busca informado?
     *
     * Apenas o nosso filtro. Nao tentamos nos pendurar em 'customers' para
     * cobrir a busca "Tudo": o core retorna a clausula de 'customers' antes
     * de aplicar o filtro de WHERE, entao so o JOIN entraria - custo sem
     * beneficio. A cobertura do "Tudo" e feita em route_cpf_search().
     *
     * @param string $search_filter
     * @return bool
     */
    private static function handles($search_filter) {
        return self::FILTER === $search_filter;
    }

    /**
     * JOIN com a tabela de meta dos pedidos.
     *
     * @param string $join
     * @param string $search_term
     * @param string $search_filter
     * @param object $query
     * @return string
     */
    public static function generate_join($join, $search_term, $search_filter, $query) {
        if (!self::handles($search_filter)) {
            return $join;
        }
        if ('' === self::digits($search_term)) {
            return $join;
        }

        global $wpdb;

        $orders_table = method_exists($query, 'get_table_name')
            ? $query->get_table_name('orders')
            : $wpdb->prefix . 'wc_orders';
        $meta_table   = $wpdb->prefix . 'wc_orders_meta';
        $alias        = self::ALIAS;

        $placeholders = implode(',', array_fill(0, count(self::META_KEYS), '%s'));

        // LEFT JOIN: a busca precisa continuar retornando pedidos que casem
        // por outros campos (nome, e-mail) mesmo sem CPF gravado.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $cpf_join = $wpdb->prepare(
            "LEFT JOIN {$meta_table} AS {$alias}
                    ON {$alias}.order_id = {$orders_table}.id
                   AND {$alias}.meta_key IN ({$placeholders})",
            self::META_KEYS
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Evita JOIN duplicado quando o core chama mais de uma vez.
        if (false !== strpos((string) $join, $alias)) {
            return $join;
        }

        return trim($join . ' ' . $cpf_join);
    }

    /**
     * WHERE comparando apenas digitos dos dois lados.
     *
     * @param string $where
     * @param string $search_term
     * @param string $search_filter
     * @param object $query
     * @return string
     */
    public static function generate_where($where, $search_term, $search_filter, $query) {
        if (!self::handles($search_filter)) {
            return $where;
        }

        $digits = self::digits($search_term);
        if ('' === $digits) {
            return $where;
        }

        global $wpdb;

        $alias = self::ALIAS;

        // Remove pontuacao do valor armazenado antes de comparar.
        $normalized = "REPLACE(REPLACE(REPLACE(REPLACE({$alias}.meta_value, '.', ''), '-', ''), ' ', ''), '/', '')";

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $cpf_where = $wpdb->prepare(
            "{$normalized} LIKE %s",
            '%' . $wpdb->esc_like($digits) . '%'
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (empty($where)) {
            return $cpf_where;
        }

        return '(' . $where . ' OR ' . $cpf_where . ')';
    }
}
