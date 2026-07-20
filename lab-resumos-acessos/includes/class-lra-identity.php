<?php
/**
 * Resolucao e provisionamento de identidade.
 *
 * Regras de negocio (definidas pelo cliente):
 *  - CPF ja existe num usuario  -> reusa esse usuario e MANTEM o email/login antigo
 *    (CPF e a verdade; nao mexe na conta Moodle/SSO existente).
 *  - Email ja existe com CPF diferente -> BLOQUEIA e registra conflito.
 *  - Nenhum encontrado -> provisiona um novo usuario WordPress.
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Identity
 */
class LRA_Identity {

    /**
     * Resolve (ou cria) o usuario WordPress correspondente.
     *
     * @param string $email
     * @param string $cpf  CPF (com ou sem mascara).
     * @param string $nome Nome completo (usado ao criar).
     * @param string $context Contexto da concessao (para log de conflito).
     * @return array|WP_Error ['user_id' => int, 'created' => bool] ou WP_Error.
     */
    public static function resolve($email, $cpf, $nome = '', $context = '') {
        $email = sanitize_email($email);
        $cpf   = preg_replace('/\D/', '', (string) $cpf);

        if (!is_email($email)) {
            return new WP_Error('lra_invalid_email', __('Email invalido.', 'lab-resumos-acessos'));
        }

        if (strlen($cpf) !== 11 || !self::validate_cpf($cpf)) {
            return new WP_Error('lra_invalid_cpf', __('CPF invalido.', 'lab-resumos-acessos'));
        }

        // 1. Busca por CPF (verdade legal). Mantem o email antigo.
        $user = self::find_user_by_cpf($cpf);
        if ($user) {
            self::ensure_billing_cpf($user->ID, $cpf);
            return ['user_id' => (int) $user->ID, 'created' => false];
        }

        // 2. Busca por email.
        $user = get_user_by('email', $email);
        if ($user) {
            $existing = preg_replace('/\D/', '', (string) get_user_meta($user->ID, 'billing_cpf', true));

            if ($existing && $existing !== $cpf) {
                // Conflito: email pertence a outra pessoa (CPF diferente). Bloqueia.
                LRA_Conflicts::add($email, $cpf, $existing, $user->ID, $nome, $context);
                return new WP_Error(
                    'lra_cpf_email_conflict',
                    sprintf(
                        /* translators: %s: email */
                        __('O email %s ja pertence a um usuario com outro CPF. Registrado como conflito para revisao manual.', 'lab-resumos-acessos'),
                        $email
                    )
                );
            }

            // Mesmo CPF (ou ainda sem CPF cadastrado) -> reusa e faz backfill.
            self::ensure_billing_cpf($user->ID, $cpf);
            return ['user_id' => (int) $user->ID, 'created' => false];
        }

        // 3. Nenhum encontrado -> provisiona.
        list($first, $last) = self::split_name($nome ?: $email);

        $user_id = wp_insert_user([
            'user_login'   => $email,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password(24),
            'display_name' => $nome ?: $email,
            'first_name'   => $first,
            'last_name'    => $last,
            'role'         => 'customer',
        ]);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        self::ensure_billing_cpf($user_id, $cpf);

        lra_log('Usuario provisionado', ['user_id' => $user_id, 'email' => $email]);

        return ['user_id' => (int) $user_id, 'created' => true];
    }

    /**
     * Busca usuario pelo CPF (normalizando mascara em ambos os lados).
     *
     * @param string $cpf CPF apenas digitos.
     * @return WP_User|null
     */
    public static function find_user_by_cpf($cpf) {
        global $wpdb;

        $cpf = preg_replace('/\D/', '', (string) $cpf);
        if (strlen($cpf) !== 11) {
            return null;
        }

        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta}
                 WHERE meta_key = 'billing_cpf'
                 AND REPLACE(REPLACE(REPLACE(meta_value, '.', ''), '-', ''), ' ', '') = %s
                 LIMIT 1",
                $cpf
            )
        );

        return $user_id ? get_user_by('id', (int) $user_id) : null;
    }

    /**
     * Garante billing_cpf / _billing_cpf no usuario (digitos apenas).
     *
     * @param int    $user_id
     * @param string $cpf
     */
    public static function ensure_billing_cpf($user_id, $cpf) {
        $cpf = preg_replace('/\D/', '', (string) $cpf);
        update_user_meta($user_id, 'billing_cpf', $cpf);
        update_user_meta($user_id, '_billing_cpf', $cpf);
    }

    /**
     * Quebra um nome completo em [first, last].
     *
     * @param string $nome
     * @return array
     */
    private static function split_name($nome) {
        $nome  = trim(preg_replace('/\s+/', ' ', (string) $nome));
        if ($nome === '') {
            return ['', ''];
        }
        $parts = explode(' ', $nome);
        $first = array_shift($parts);
        $last  = implode(' ', $parts);
        return [$first, $last];
    }

    /**
     * Valida um CPF (apenas digitos).
     *
     * @param string $cpf
     * @return bool
     */
    public static function validate_cpf($cpf) {
        $cpf = preg_replace('/\D/', '', (string) $cpf);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
