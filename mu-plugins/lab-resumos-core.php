<?php
/**
 * Plugin Name: Lab Resumos - Core
 * Description: Biblioteca comum (CPF, HPOS, log, WhatsApp, Telegram, autologin) para
 *              os plugins custom do labresumos.com.br. Fase F1 do roadmap de
 *              otimização/consolidação (docs/plugins-custom-analise-e-roadmap.md).
 *              ADITIVO: nenhum plugin existente consome estas classes ainda — este
 *              mu-plugin só disponibiliza; comportamento de prod fica inalterado.
 * Version: 1.0.0
 * Author: Lab Resumos
 */

defined('ABSPATH') || exit;

/**
 * LR_CPF — validação/normalização/formatação de CPF.
 *
 * Algoritmo portado de lab-resumos-acessos/includes/class-lra-identity.php
 * (idêntico ao de lab-resumos-parceiros/includes/core/class-lrp-affiliate.php) —
 * o dígito verificador real, não o length-check fraco do guruja-discount
 * (class-guruja-integration.php), que hoje aceita CPF inválido.
 */
if (!class_exists('LR_CPF')) {

    class LR_CPF {

        /**
         * Remove tudo que não é dígito.
         *
         * @param string $cpf
         * @return string
         */
        public static function clean($cpf) {
            return preg_replace('/\D/', '', (string) $cpf);
        }

        /**
         * Valida o dígito verificador de um CPF.
         *
         * @param string $cpf CPF com ou sem máscara.
         * @return bool
         */
        public static function validate($cpf) {
            $cpf = self::clean($cpf);

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

        /**
         * Formata um CPF válido como 000.000.000-00.
         *
         * @param string $cpf
         * @return string CPF formatado, ou os dígitos limpos se não tiver 11 dígitos.
         */
        public static function format($cpf) {
            $cpf = self::clean($cpf);

            if (strlen($cpf) !== 11) {
                return $cpf;
            }

            return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
        }
    }
}

/**
 * LR_HPOS — detecção segura do modo de armazenamento de pedidos do WooCommerce
 * (High-Performance Order Storage).
 *
 * Rotina defensiva portada de lab-resumos-parceiros/includes/integrations/class-lrp-woocommerce.php
 * (reescrita de forma equivalente em lab-resumos-guruja-discount, lab-resumos-acessos e
 * lab-resumos-recuperacao-de-vendas). Cacheada por request.
 */
if (!class_exists('LR_HPOS')) {

    class LR_HPOS {

        /** @var bool|null */
        private static $cache = null;

        /**
         * @return bool True se HPOS (custom order tables) está ativo.
         */
        public static function enabled() {
            if (self::$cache !== null) {
                return self::$cache;
            }

            if (!class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
                return self::$cache = false;
            }

            if (!method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
                return self::$cache = false;
            }

            try {
                return self::$cache = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            } catch (\Throwable $e) {
                LR_Log::warning('hpos', 'Erro ao verificar HPOS: ' . $e->getMessage());
                return self::$cache = false;
            }
        }
    }
}

/**
 * LR_Log — logger único (substitui as 4 variantes incompatíveis hoje em uso:
 * wc_logger em parceiros/discount, error_log em acessos, tabela custom em recuperacao).
 * Usa wc_get_logger() (padrão WooCommerce) quando disponível; cai para error_log senão.
 */
if (!class_exists('LR_Log')) {

    class LR_Log {

        const SOURCE = 'lab-resumos-core';

        public static function info($context, $message) {
            self::write('info', $context, $message);
        }

        public static function warning($context, $message) {
            self::write('warning', $context, $message);
        }

        public static function error($context, $message) {
            self::write('error', $context, $message);
        }

        private static function write($level, $context, $message) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->log($level, $message, ['source' => self::SOURCE, 'context' => $context]);
                return;
            }

            error_log(sprintf('[%s][%s] %s: %s', self::SOURCE, $level, $context, $message));
        }
    }
}

/**
 * LR_WhatsApp — normalização de telefone BR e montagem de link wa.me.
 *
 * Portado de lab-resumos-recuperacao-de-vendas/includes/class-lr-autologin-integration.php
 * (format_phone_for_whatsapp / generate_whatsapp_url) — única implementação existente hoje.
 */
if (!class_exists('LR_WhatsApp')) {

    class LR_WhatsApp {

        /**
         * Normaliza um telefone BR para o formato E.164 sem "+" esperado pelo wa.me
         * (55 + DDD + número).
         *
         * @param string $phone
         * @return string
         */
        public static function format_phone($phone) {
            $clean = preg_replace('/\D/', '', (string) $phone);

            if (strlen($clean) === 11 && substr($clean, 0, 2) !== '55') {
                $clean = '55' . $clean;
            }

            if (strlen($clean) === 10) {
                $clean = '55' . $clean;
            }

            return $clean;
        }

        /**
         * Monta a URL do wa.me com mensagem pré-preenchida.
         *
         * @param string $phone
         * @param string $message
         * @return string
         */
        public static function build_url($phone, $message = '') {
            $phone_clean = self::format_phone($phone);
            $message     = str_replace(["\r\n", "\r"], "\n", (string) $message);

            return 'https://wa.me/' . $phone_clean . '?text=' . rawurlencode($message);
        }
    }
}

/**
 * LR_Telegram — alerta via webhook (n8n → Telegram).
 *
 * Consolida os 3 caminhos existentes hoje (cpf-sender-api, lab-resumos-parceiros.php:931,
 * snippet WPCode #1650) — todos batem no MESMO webhook e no MESMO payload
 * {evento, descricao}, só variando enabled-flag/rate-limit/timeout. Mantém o rate-limit
 * (10 alertas/hora) do snippet #1650, que é o comportamento mais defensivo dos três.
 */
if (!class_exists('LR_Telegram')) {

    class LR_Telegram {

        const DEFAULT_WEBHOOK_URL = 'https://automation.guruja.com.br/webhook/b87b165b-6017-4156-97a6-1431cec04356';
        const RATE_LIMIT_PER_HOUR = 10;

        /**
         * Envia um alerta não-bloqueante para o Telegram via webhook n8n.
         *
         * @param string $evento Título curto do alerta.
         * @param string $descricao Detalhes.
         * @return bool True se a chamada foi disparada (não garante entrega — é non-blocking).
         */
        public static function alert($evento, $descricao = '') {
            if (get_option('cpf_sender_telegram_enabled', '1') !== '1') {
                return false;
            }

            $webhook_url = apply_filters('lr_core_telegram_webhook_url', self::DEFAULT_WEBHOOK_URL);
            if (empty($webhook_url)) {
                return false;
            }

            $rate_key = 'lr_core_telegram_count_' . gmdate('YmdH');
            $count    = (int) get_transient($rate_key);

            if ($count >= self::RATE_LIMIT_PER_HOUR) {
                error_log("[LR_Telegram throttled] {$evento}: {$descricao}");
                return false;
            }

            set_transient($rate_key, $count + 1, HOUR_IN_SECONDS);

            wp_remote_post($webhook_url, [
                'timeout'  => 5,
                'blocking' => false,
                'headers'  => ['Content-Type' => 'application/json'],
                'body'     => wp_json_encode(['evento' => $evento, 'descricao' => $descricao]),
            ]);

            return true;
        }
    }
}

/**
 * LR_Autologin — wrapper fino sobre lr_get_autologin_url()/lr_get_payment_link_for_order(),
 * hoje definidas SÓ no snippet WPCode #1241. Não move o código (isso é a Fase F2) — só
 * dá um ponto único de chamada que já funciona hoje E continua funcionando quando o F2
 * mover a implementação real para plugin, sem precisar tocar nos consumidores de novo.
 */
if (!class_exists('LR_Autologin')) {

    class LR_Autologin {

        /**
         * @return bool True se a implementação (hoje: snippet #1241) está carregada.
         */
        public static function is_available() {
            return function_exists('lr_get_autologin_url');
        }

        /**
         * @param mixed ...$args Mesma assinatura de lr_get_autologin_url().
         * @return string|WP_Error
         */
        public static function get_autologin_url(...$args) {
            if (!self::is_available()) {
                return new WP_Error('lr_autologin_unavailable', 'lr_get_autologin_url não está definida.');
            }

            return lr_get_autologin_url(...$args);
        }

        /**
         * @param mixed ...$args Mesma assinatura de lr_get_payment_link_for_order().
         * @return string|WP_Error
         */
        public static function get_payment_link_for_order(...$args) {
            if (!function_exists('lr_get_payment_link_for_order')) {
                return new WP_Error('lr_payment_link_unavailable', 'lr_get_payment_link_for_order não está definida.');
            }

            return lr_get_payment_link_for_order(...$args);
        }
    }
}
