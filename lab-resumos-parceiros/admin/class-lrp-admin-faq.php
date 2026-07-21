<?php
/**
 * Admin - FAQ
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Admin_FAQ
 */
class LRP_Admin_FAQ {

    /**
     * Cria/atualiza FAQ
     *
     * @param array $data
     * @return int|false
     */
    public static function save($data) {
        global $wpdb;
        
        $insert_data = [
            'question'      => sanitize_text_field($data['question']),
            'answer'        => wp_kses_post($data['answer']),
            'category'      => sanitize_key($data['category'] ?? 'geral'),
            'display_order' => intval($data['display_order'] ?? 0),
            'is_active'     => isset($data['is_active']) ? 1 : 0,
        ];
        
        if (!empty($data['id'])) {
            $result = $wpdb->update(
                $wpdb->prefix . 'lrp_faq',
                $insert_data,
                ['id' => (int) $data['id']]
            );
            return $result !== false ? (int) $data['id'] : false;
        } else {
            $insert_data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($wpdb->prefix . 'lrp_faq', $insert_data);
            return $result ? $wpdb->insert_id : false;
        }
    }

    /**
     * Exclui FAQ
     *
     * @param int $id
     * @return bool
     */
    public static function delete($id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'lrp_faq', ['id' => (int) $id]) !== false;
    }

    /**
     * Obtém categorias disponíveis
     *
     * @return array
     */
    public static function get_categories() {
        return [
            'como-funciona' => __('Como Funciona', 'lab-resumos-parceiros'),
            'comissoes'     => __('Comissões', 'lab-resumos-parceiros'),
            'pagamentos'    => __('Pagamentos', 'lab-resumos-parceiros'),
            'notas-fiscais' => __('Notas Fiscais', 'lab-resumos-parceiros'),
            'rede'          => __('Minha Rede', 'lab-resumos-parceiros'),
            'cupons'        => __('Cupons e Links', 'lab-resumos-parceiros'),
            'geral'         => __('Geral', 'lab-resumos-parceiros'),
        ];
    }
}

