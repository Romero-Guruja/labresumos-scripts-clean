<?php
/**
 * Catalogo de cursos: leitura do mapeamento produto <-> curso Moodle do
 * Edwiser Bridge (tabela {prefix}eb_moodle_course_products).
 *
 * Colunas relevantes: product_id, moodle_post_id (CPT eb_course), moodle_course_id.
 * NOTA: a tabela {prefix}moodle_enrollment.course_id referencia o moodle_post_id,
 * nao o moodle_course_id.
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Catalog
 */
class LRA_Catalog {

    /**
     * Nome da tabela de mapeamento do Edwiser.
     *
     * @return string
     */
    public static function map_table() {
        global $wpdb;
        return $wpdb->prefix . 'eb_moodle_course_products';
    }

    /**
     * Retorna os produtos que estao mapeados a algum curso Moodle.
     *
     * @return array Lista de ['product_id' => int, 'name' => string, 'courses' => int[]].
     */
    public static function get_mapped_products() {
        global $wpdb;

        $table = self::map_table();

        $rows = $wpdb->get_results(
            "SELECT product_id, moodle_post_id, moodle_course_id FROM {$table} ORDER BY product_id ASC"
        );

        if (!$rows) {
            return [];
        }

        $products = [];
        foreach ($rows as $row) {
            $pid = (int) $row->product_id;
            if (!isset($products[$pid])) {
                $product            = wc_get_product($pid);
                $products[$pid]     = [
                    'product_id' => $pid,
                    'name'       => $product ? $product->get_name() : sprintf('#%d (produto removido)', $pid),
                    'courses'    => [],
                ];
            }
            $products[$pid]['courses'][] = [
                'moodle_post_id'   => (int) $row->moodle_post_id,
                'moodle_course_id' => (int) $row->moodle_course_id,
            ];
        }

        return array_values($products);
    }

    /**
     * Retorna os cursos (moodle_post_id) vinculados a um produto.
     *
     * @param int $product_id
     * @return array Lista de moodle_post_id.
     */
    public static function get_product_course_post_ids($product_id) {
        global $wpdb;

        $table = self::map_table();

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT moodle_post_id FROM {$table} WHERE product_id = %d",
                absint($product_id)
            )
        );

        return array_map('intval', $ids);
    }

    /**
     * Verifica se um produto esta mapeado a pelo menos um curso.
     *
     * @param int $product_id
     * @return bool
     */
    public static function product_is_mapped($product_id) {
        global $wpdb;

        $table = self::map_table();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE product_id = %d",
                absint($product_id)
            )
        );

        return (int) $count > 0;
    }
}
