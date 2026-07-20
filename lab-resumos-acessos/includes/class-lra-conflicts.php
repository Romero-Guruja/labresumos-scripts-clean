<?php
/**
 * Fila de conflitos de identidade.
 *
 * Registra casos em que o email informado ja pertence a um usuario com CPF
 * diferente (regra de bloqueio definida pelo negocio). Esses casos exigem
 * revisao manual antes de conceder acesso.
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Conflicts
 */
class LRA_Conflicts {

    /**
     * Nome da tabela (sem prefixo).
     */
    const TABLE = 'lra_conflicts';

    /**
     * Retorna o nome completo da tabela.
     *
     * @return string
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Cria a tabela de conflitos (chamado na ativacao).
     */
    public static function install_table() {
        global $wpdb;

        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            cpf_informado VARCHAR(14) NOT NULL,
            cpf_existente VARCHAR(14) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            nome VARCHAR(255) NULL,
            context VARCHAR(50) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            INDEX idx_status (status),
            INDEX idx_email (email)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Registra um conflito.
     *
     * @param string $email
     * @param string $cpf_informado
     * @param string $cpf_existente
     * @param int    $user_id
     * @param string $nome
     * @param string $context
     * @return int|false ID inserido ou false.
     */
    public static function add($email, $cpf_informado, $cpf_existente, $user_id, $nome = '', $context = '') {
        global $wpdb;

        $inserted = $wpdb->insert(
            self::table_name(),
            [
                'email'         => $email,
                'cpf_informado' => preg_replace('/\D/', '', $cpf_informado),
                'cpf_existente' => preg_replace('/\D/', '', $cpf_existente),
                'user_id'       => absint($user_id),
                'nome'          => $nome,
                'context'       => $context,
                'status'        => 'open',
                'created_at'    => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        if ($inserted) {
            lra_log('Conflito de identidade registrado', [
                'email'   => $email,
                'user_id' => $user_id,
            ], 'warning');
            return (int) $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Retorna conflitos abertos.
     *
     * @param int $limit
     * @return array
     */
    public static function get_open($limit = 50) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table_name() . " WHERE status = 'open' ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Marca um conflito como resolvido.
     *
     * @param int $id
     * @return bool
     */
    public static function resolve($id) {
        global $wpdb;

        return (bool) $wpdb->update(
            self::table_name(),
            ['status' => 'resolved', 'resolved_at' => current_time('mysql')],
            ['id' => absint($id)],
            ['%s', '%s'],
            ['%d']
        );
    }
}
