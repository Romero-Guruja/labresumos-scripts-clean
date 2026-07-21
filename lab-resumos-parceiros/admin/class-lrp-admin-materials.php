<?php
/**
 * Admin - Materiais de Divulgação
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Admin_Materials
 */
class LRP_Admin_Materials {

    /**
     * Cria/atualiza material
     *
     * @param array $data
     * @return int|false ID do material ou false
     */
    public static function save($data) {
        global $wpdb;
        
        $insert_data = [
            'title'         => sanitize_text_field($data['title']),
            'description'   => sanitize_textarea_field($data['description'] ?? ''),
            'type'          => sanitize_key($data['type']),
            'file_url'      => esc_url_raw($data['file_url'] ?? ''),
            'content'       => wp_kses_post($data['content'] ?? ''),
            'category'      => sanitize_key($data['category'] ?? 'geral'),
            'display_order' => intval($data['display_order'] ?? 0),
            'is_active'     => isset($data['is_active']) ? 1 : 0,
        ];
        
        if (!empty($data['id'])) {
            // Update
            $result = $wpdb->update(
                $wpdb->prefix . 'lrp_materials',
                $insert_data,
                ['id' => (int) $data['id']]
            );
            return $result !== false ? (int) $data['id'] : false;
        } else {
            // Insert
            $insert_data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($wpdb->prefix . 'lrp_materials', $insert_data);
            return $result ? $wpdb->insert_id : false;
        }
    }

    /**
     * Exclui material
     *
     * @param int $id
     * @return bool
     */
    public static function delete($id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'lrp_materials', ['id' => (int) $id]) !== false;
    }

    /**
     * Obtém categorias disponíveis
     *
     * @return array
     */
    public static function get_categories() {
        return [
            'banner'    => __('Banners', 'lab-resumos-parceiros'),
            'copy'      => __('Copies/Textos', 'lab-resumos-parceiros'),
            'social'    => __('Redes Sociais', 'lab-resumos-parceiros'),
            'email'     => __('Templates de Email', 'lab-resumos-parceiros'),
            'video'     => __('Vídeos', 'lab-resumos-parceiros'),
            'documento' => __('Documentos', 'lab-resumos-parceiros'),
            'geral'     => __('Geral', 'lab-resumos-parceiros'),
        ];
    }

    /**
     * Obtém tipos disponíveis
     *
     * @return array
     */
    public static function get_types() {
        return [
            'image'    => __('Imagem', 'lab-resumos-parceiros'),
            'text'     => __('Texto', 'lab-resumos-parceiros'),
            'video'    => __('Vídeo', 'lab-resumos-parceiros'),
            'document' => __('Documento', 'lab-resumos-parceiros'),
        ];
    }
}

