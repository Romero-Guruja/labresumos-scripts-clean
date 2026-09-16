<?php
/**
 * E-mails de aviso de prazo de acesso.
 *
 * Dois disparos, ambos por WP-Cron (nada externo):
 *   1. 30 dias antes do vencimento
 *   2. no dia em que o acesso encerra
 *
 * AGRUPAMENTO POR ALUNO (decisão de projeto, não detalhe):
 * o levantamento de 26/08/2026 mostrou 4 alunos com 24 materiais vencendo no
 * MESMO dia e 92 alunos com 18. Um e-mail por matrícula significaria 24
 * mensagens simultâneas para a mesma pessoa -- caminho curto para o aluno
 * marcar como spam e derrubar a reputação do domínio no SendGrid. Aqui a
 * unidade de envio é (aluno, data de vencimento): 1.144 e-mails no total em
 * vez de 4.708, com pico de 24 num único dia.
 *
 * NÃO FALAMOS DE RENOVAÇÃO/RECOMPRA (decisão do usuário): o produto pode ter
 * saído do ar, mudado de nome ou de preço. Prometer uma renovação que talvez
 * não exista gera frustração e ticket. O e-mail informa o prazo e convida a
 * aproveitar o material enquanto está disponível.
 *
 * @package Lab_Resumos_Acessos
 */

defined('ABSPATH') || exit;

/**
 * Class LRA_Expiry_Mail
 */
class LRA_Expiry_Mail {

    /** Dias de antecedência do primeiro aviso. */
    const DIAS_AVISO = 30;

    /** Máximo de alunos processados por execução (proteção contra pico). */
    const LOTE = 200;

    /** Option que liga/desliga o envio real. */
    const OPT_ENVIO = 'lra_expiry_mail_enabled';

    /**
     * Taxonomia de marca (plugin Perfect WooCommerce Brands) usada no site
     * como filtro Pré edital / Pós edital.
     */
    const TAX_MARCA = 'pwb-brand';

    /**
     * Slug da marca "Pós edital".
     *
     * Decisão comercial (Gustavo, 26/08/2026): NÃO sugerir nova compra para
     * pós-edital -- o concurso já passou e o material perdeu validade prática.
     *
     * Antes isto era um regex no nome do curso (casava "SEFAZ"), o que era
     * heurística frágil: pegaria por acidente qualquer curso novo com SEFAZ
     * no título e não pegaria um pós-edital de outra banca. A classificação
     * real já existe no catálogo, é ela que vale.
     */
    const MARCA_POS_EDITAL = 'pos-edital';

    /**
     * Registra hooks.
     */
    public static function init() {
        add_action(LRA_Expiration::CRON_NOTICE_HOOK, [__CLASS__, 'run'], 5);

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('lra mail-preview', [__CLASS__, 'cli_preview']);
            \WP_CLI::add_command('lra mail-run', [__CLASS__, 'cli_run']);
        }
    }

    /**
     * O envio real está ligado?
     *
     * Padrão: NÃO. Enquanto desligado o cron apenas registra em log quem
     * receberia o quê, permitindo conferir antes de falar com o aluno.
     *
     * @return bool
     */
    public static function is_enabled() {
        return 'yes' === get_option(self::OPT_ENVIO, 'no');
    }


    /**
     * Pode sugerir uma nova compra deste conjunto de materiais?
     *
     * Um curso só é elegível se existir, HOJE, ao menos um produto que:
     *   - esteja publicado, comprável e em estoque; e
     *   - NÃO esteja marcado como "Pós edital" na taxonomia `pwb-brand`
     *     (o mesmo filtro Pré/Pós edital que o site usa na loja).
     *
     * As duas condições respondem a preocupações diferentes:
     *   - "vai que o produto já saiu do ar" (Romero) -> checagem de catálogo;
     *   - "não oferecer pós-edital" (Gustavo) -> checagem de marca.
     *
     * Um único item reprovado bloqueia o lote inteiro. É conservador de
     * propósito: melhor um e-mail que informa a menos que um que promete o
     * que não existe mais.
     *
     * Nunca há link (decisão do Romero: a URL pode ter mudado) -- o texto
     * apenas informa que os materiais seguem disponíveis no site.
     *
     * @param int[] $course_ids IDs dos cursos (eb_course).
     * @return bool
     */
    public static function pode_sugerir_compra($course_ids) {
        if (empty($course_ids)) {
            return false;
        }

        // Basta UM material pós-edital no lote para omitir a menção:
        // material de concurso que já passou não se oferece de novo.
        foreach ($course_ids as $cid) {
            if (self::curso_e_pos_edital($cid)) {
                return false;
            }
        }

        return true;
    }

    /**
     * O curso é servido por produto marcado como "Pós edital"?
     *
     * Consulta a taxonomia `pwb-brand` -- o MESMO filtro Pré/Pós edital que o
     * site usa na loja. Antes isto era um regex no título casando "SEFAZ",
     * heurística que erraria nos dois sentidos (pegaria curso novo com SEFAZ
     * no nome; perderia pós-edital de outra banca).
     *
     * Só considera pós-edital quando NENHUM produto pré-edital serve aquele
     * curso -- se o conteúdo também é vendido numa versão corrente, o aluno
     * pode perfeitamente encontrá-la na loja.
     *
     * @param int $course_id
     * @return bool
     */
    public static function curso_e_pos_edital($course_id) {
        static $cache = null;

        if ($cache === null) {
            $cache = [];
            $ids = get_posts([
                'post_type'      => 'product',
                // 'trash' É NECESSÁRIO: quando um concurso passa, o produto
                // pós-edital vai para a lixeira em vez de ser despublicado.
                // Sem incluir trash, os 3 produtos pós-edital (SEFAZ/CE) some
                // do mapa e o curso passaria como elegível por engano.
                'post_status'    => ['publish', 'private', 'draft', 'pending', 'trash'],
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ]);

            foreach ($ids as $pid) {
                $opts = get_post_meta($pid, 'product_options', true);
                if (empty($opts['moodle_post_course_id'])
                    || !is_array($opts['moodle_post_course_id'])) {
                    continue;
                }

                $pos = has_term(self::MARCA_POS_EDITAL, self::TAX_MARCA, $pid);

                // Só conta como oferta VIVA um produto publicado, comprável e
                // em estoque. Produto na lixeira não vale como "pré-edital
                // disponível" -- é o caso dos cursos SEFAZ/RN, /GO e /SP, cujo
                // único produto está em trash e SEM marca: contá-lo como pré
                // faria o e-mail convidar para uma loja onde não há o que
                // comprar daquele material.
                $vivo = false;
                if (get_post_status($pid) === 'publish' && function_exists('wc_get_product')) {
                    $produto = wc_get_product($pid);
                    $vivo = ($produto && $produto->is_purchasable() && $produto->is_in_stock());
                }

                foreach ($opts['moodle_post_course_id'] as $cid) {
                    $cid = (int) $cid;
                    if (!isset($cache[$cid])) {
                        $cache[$cid] = ['pos' => 0, 'pre' => 0];
                    }
                    if ($pos) {
                        $cache[$cid]['pos']++;
                    } elseif ($vivo) {
                        $cache[$cid]['pre']++;
                    }
                }
            }
        }

        $cid = (int) $course_id;

        // Sem NENHUM produto mapeado, tratamos como não-ofertável: é o caso
        // real dos cursos "Reforma Tributária para a SEFAZ/RN /GO /SP"
        // (16 matrículas), que existem no Moodle mas não têm produto nenhum
        // apontando para eles -- material de concurso encerrado, sem versão
        // à venda. Fail-closed: na dúvida, não convida para a loja.
        if (!isset($cache[$cid])) {
            return true;
        }

        // Bloqueia quando não há NENHUMA versão pré-edital à venda hoje --
        // seja porque só existe produto pós-edital, seja porque os produtos
        // daquele curso saíram todos do ar.
        return $cache[$cid]['pre'] === 0;
    }

    /**
     * Busca grupos (aluno + data) para um marco.
     *
     * @param int $dias 30 para aviso prévio, 0 para o dia do vencimento.
     * @return array
     */
    public static function get_grupos($dias) {
        global $wpdb;

        // Agrupa por aluno + dia de vencimento. GROUP_CONCAT traz os cursos
        // numa tacada só, evitando N+1 queries.
        $sql = $wpdb->prepare(
            "SELECT me.user_id,
                    DATE(me.expire_time) AS dia,
                    GROUP_CONCAT(me.course_id ORDER BY me.course_id) AS cursos
               FROM {$wpdb->prefix}moodle_enrollment me
              WHERE me.expire_time <> '0000-00-00 00:00:00'
                AND me.suspended = 0
                AND DATE(me.expire_time) = DATE(DATE_ADD(UTC_TIMESTAMP(), INTERVAL %d DAY))
           GROUP BY me.user_id, DATE(me.expire_time)
              LIMIT %d",
            (int) $dias,
            self::LOTE
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Executa os dois marcos.
     *
     * @return array
     */
    public static function run() {
        $resultado = [];
        foreach ([self::DIAS_AVISO, 0] as $marco) {
            $resultado[$marco] = self::processa_marco($marco);
        }
        return $resultado;
    }

    /**
     * Processa um marco (30 dias antes ou no dia).
     *
     * @param int  $marco
     * @param bool $preview Só relata, não envia nem marca.
     * @return array
     */
    public static function processa_marco($marco, $preview = false) {
        $grupos   = self::get_grupos($marco);
        $enviados = 0;
        $pulados  = 0;
        $linhas   = [];

        foreach ($grupos as $g) {
            $meta_key = '_lra_aviso_' . (int) $marco . '_' . str_replace('-', '', $g->dia);

            // Idempotência: nunca reenviar o mesmo marco para o mesmo
            // vencimento, mesmo que o cron rode várias vezes no dia.
            if (!$preview && get_user_meta($g->user_id, $meta_key, true)) {
                $pulados++;
                continue;
            }

            $user = get_userdata($g->user_id);
            if (!$user || !is_email($user->user_email)) {
                $pulados++;
                continue;
            }

            $ids     = array_filter(array_map('absint', explode(',', $g->cursos)));
            $titulos = [];
            foreach ($ids as $cid) {
                $t = get_the_title($cid);
                if ($t) {
                    $titulos[] = $t;
                }
            }
            if (empty($titulos)) {
                $pulados++;
                continue;
            }

            $linhas[] = [
                'email'  => $user->user_email,
                'nome'   => self::primeiro_nome($user),
                'dia'    => $g->dia,
                'cursos' => $titulos,
            ];

            if ($preview) {
                continue;
            }

            if (!self::is_enabled()) {
                lra_log('[email][dry-run] enviaria aviso de prazo', [
                    'user_id' => (int) $g->user_id,
                    'marco'   => $marco,
                    'vence'   => $g->dia,
                    'cursos'  => count($titulos),
                ]);
                $enviados++;
                continue;
            }

            $ok = self::envia($user, $titulos, $g->dia, $marco, $ids);
            if ($ok) {
                update_user_meta($g->user_id, $meta_key, time());
                $enviados++;
            } else {
                $pulados++;
            }
        }

        if ($grupos && !$preview) {
            lra_log('[email] marco processado', [
                'marco'    => $marco,
                'grupos'   => count($grupos),
                'enviados' => $enviados,
                'pulados'  => $pulados,
                'modo'     => self::is_enabled() ? 'real' : 'dry-run',
            ]);
        }

        return ['grupos' => count($grupos), 'enviados' => $enviados,
                'pulados' => $pulados, 'linhas' => $linhas];
    }

    /**
     * Primeiro nome utilizável do aluno.
     *
     * @param WP_User $user
     * @return string
     */
    private static function primeiro_nome($user) {
        $nome = $user->first_name ? $user->first_name : $user->display_name;
        $nome = trim((string) $nome);
        if ($nome === '' || is_email($nome)) {
            return '';
        }
        $partes = preg_split('/\s+/', $nome);
        return ucfirst(mb_strtolower($partes[0], 'UTF-8'));
    }

    /**
     * Envia o e-mail do marco.
     *
     * @param WP_User $user
     * @param array   $cursos Títulos.
     * @param string  $dia    Y-m-d do vencimento.
     * @param int     $marco  30 ou 0.
     * @return bool
     */
    public static function envia($user, $cursos, $dia, $marco, $course_ids = []) {
        $data_br = date_i18n('d/m/Y', strtotime($dia));

        if ($marco > 0) {
            $assunto = (count($cursos) === 1)
                ? 'Seu acesso ao material termina em ' . $data_br
                : 'Seus materiais ficam disponíveis até ' . $data_br;
        } else {
            $assunto = (count($cursos) === 1)
                ? 'Seu acesso ao material foi encerrado hoje'
                : 'Seu acesso aos materiais foi encerrado hoje';
        }

        // Decide, no momento do envio, se cabe mencionar nova compra.
        $sugerir = self::pode_sugerir_compra($course_ids);

        $html = self::template($user, $cursos, $data_br, $marco, $sugerir);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        // O WP Mail SMTP força o remetente (contato@labresumos.com.br,
        // "Laboratório de Resumos") via from_email_force -- não sobrescrevemos.
        $enviado = wp_mail($user->user_email, $assunto, $html, $headers);

        if (!$enviado) {
            lra_log('[email] falha no envio', [
                'user_id' => (int) $user->ID,
                'marco'   => $marco,
            ], 'warning');
        }

        return $enviado;
    }

    /**
     * URL da loja.
     *
     * Fonte: `wc_get_page_permalink('shop')`. A option `lra_expiry_shop_url`
     * permite sobrescrever sem tocar em código -- útil porque hoje `/loja/`
     * responde 301 para `/materiais/`, e um redirect a menos no e-mail evita
     * atrito com scanners de segurança de provedor corporativo.
     *
     * O link é da LOJA, nunca de um produto específico (decisão do Gustavo):
     * o aluno chega na vitrine e escolhe o combo/pacote mais próximo do que
     * estava usando. Isso resolve o receio original do Romero -- se aquele
     * produto exato saiu do ar, o aluno ainda encontra o equivalente.
     *
     * @return string
     */
    public static function url_loja() {
        $custom = get_option('lra_expiry_shop_url');
        if (!empty($custom)) {
            return $custom;
        }
        if (function_exists('wc_get_page_permalink')) {
            $u = wc_get_page_permalink('shop');
            if (!empty($u)) {
                return $u;
            }
        }
        return home_url('/');
    }

    /**
     * Logo oficial (assinatura horizontal) — o mesmo arquivo que o
     * WooCommerce usa em `woocommerce_email_header_image`.
     *
     * @return string
     */
    public static function logo_url() {
        $woo = get_option('woocommerce_email_header_image');
        if (!empty($woo)) {
            return $woo;
        }
        return 'https://labresumos.com.br/wp-content/uploads/2025/12/assinatura-horizontal.png';
    }

    /**
     * Template HTML na identidade visual da marca.
     *
     * PALETA extraída do próprio logo (assinatura-horizontal.png):
     *   grafite #333B49 (institucional) e amarelo #F1CC00 (acento).
     * O azul #2A6B9F que estava aqui antes veio do e-mail de senha, mas não é
     * cor de marca — o logo não tem azul nenhum. O WooCommerce está com um
     * roxo #8526ff que também destoa (default herdado de tema). O logo é a
     * fonte mais confiável, então é ele que manda.
     *
     * O logo tem fundo TRANSPARENTE com arte em grafite, logo precisa de
     * fundo claro: header branco com o logo e faixa amarela fina no topo.
     * Sobre header escuro ele ficaria ilegível.
     *
     * Tabelas de 600px com estilo inline — o que sobrevive a Outlook e Gmail.
     *
     * @param WP_User $user
     * @param array   $cursos
     * @param string  $data_br
     * @param int     $marco
     * @return string
     */
    public static function template($user, $cursos, $data_br, $marco, $sugerir_compra = false) {
        $previo = ($marco > 0);
        $um     = (count($cursos) === 1);

        $grafite = '#333B49';
        $amarelo = '#F1CC00';
        $fonte   = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

        $saudacao = self::primeiro_nome($user);
        $ola      = $saudacao
            ? 'Olá, <strong>' . esc_html($saudacao) . '</strong>!'
            : 'Olá!';

        if ($previo) {
            $titulo = $um ? 'Seu acesso termina em breve' : 'Seus acessos terminam em breve';
            $frase  = $um
                ? 'Passando para avisar que seu acesso a este material fica disponível até <strong style="color:' . $grafite . ';">' . $data_br . '</strong>.'
                : 'Passando para avisar que seus acessos aos materiais abaixo ficam disponíveis até <strong style="color:' . $grafite . ';">' . $data_br . '</strong>.';
            $fecho       = 'Se ainda tem algum conteúdo para revisar ou baixar, aproveite esse tempo com calma.';
            // Link é da LOJA, nunca de um produto: o aluno acha o combo mais
            // próximo do que estava usando, mesmo que aquele item exato tenha
            // saído do ar. Evita "não fique sem" e "assinatura": o produto é
            // compra avulsa com prazo, não plano recorrente.
            $extra       = $sugerir_compra
                ? 'Quer seguir com o material atualizado depois dessa data? É só dar uma olhada na nossa loja e escolher o pacote que combina com a sua preparação.'
                : '';
            $faixa_txt   = 'Faltam ' . self::DIAS_AVISO . ' dias de acesso';
            $faixa_bg    = '#FFFBEA';
            $faixa_borda = $amarelo;
            $faixa_cor   = '#6B5800';
            $cta         = 'Acessar meus materiais';
        } else {
            $titulo = $um ? 'Seu acesso foi encerrado' : 'Seus acessos foram encerrados';
            $frase  = $um
                ? 'O período de acesso a este material chegou ao fim em <strong style="color:' . $grafite . ';">' . $data_br . '</strong>.'
                : 'O período de acesso aos materiais abaixo chegou ao fim em <strong style="color:' . $grafite . ';">' . $data_br . '</strong>.';
            $fecho       = 'Esperamos que o conteúdo tenha ajudado na sua preparação. Obrigado por estudar com a gente.';
            $extra       = $sugerir_compra
                ? 'Para retomar os estudos com o material atualizado, é só passar na nossa loja e escolher o pacote ideal para a sua preparação.'
                : '';
            $faixa_txt   = 'Acesso encerrado em ' . $data_br;
            $faixa_bg    = '#F4F6F8';
            $faixa_borda = '#D8DEE5';
            $faixa_cor   = '#5A6673';
            $cta         = 'Ver minha conta';
        }

        $itens = '';
        foreach ($cursos as $c) {
            $itens .= '<tr>'
                . '<td width="18" valign="top" style="padding:7px 0; color:' . $amarelo . '; font-size:15px; line-height:1.5; font-weight:700;">&bull;</td>'
                . '<td style="padding:7px 0; color:' . $grafite . '; font-size:15px; line-height:1.5;">'
                . esc_html($c) . '</td>'
                . '</tr>';
        }

        $ano  = date('Y');
        $site = esc_url(home_url('/'));
        $logo = esc_url(self::logo_url());
        $link = esc_url(self::url_aluno());

        $preheader = $previo
            ? 'Seus materiais ficam disponíveis até ' . $data_br . '.'
            : 'O acesso aos seus materiais foi encerrado em ' . $data_br . '.';

        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="pt-BR">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>' . esc_html($titulo) . '</title>
</head>
<body style="margin:0; padding:0; background-color:#F0F2F4; -webkit-font-smoothing:antialiased;">

<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; height:0; width:0;">'
. esc_html($preheader) .
'</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F0F2F4;">
<tr><td align="center" style="padding:32px 16px;">

  <table width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#FFFFFF; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(51,59,73,0.10);">

    <tr><td style="background-color:' . $amarelo . '; height:5px; line-height:5px; font-size:0;">&nbsp;</td></tr>

    <tr>
      <td align="center" style="padding:30px 40px 22px 40px; background-color:#FFFFFF;">
        <img src="' . $logo . '" alt="Laboratório de Resumos" width="190"
             style="display:block; width:190px; max-width:190px; height:auto; border:0; outline:none; text-decoration:none;" />
      </td>
    </tr>

    <tr><td style="padding:0 40px;"><div style="height:1px; background-color:#EDF0F3; line-height:1px; font-size:0;">&nbsp;</div></td></tr>

    <tr>
      <td style="padding:30px 40px 34px 40px;">

        <h1 style="color:' . $grafite . '; font-family:' . $fonte . '; font-size:22px; line-height:1.3; margin:0 0 20px 0; font-weight:700;">'
          . esc_html($titulo) . '</h1>

        <p style="color:#4A5561; font-family:' . $fonte . '; font-size:16px; line-height:1.65; margin:0 0 16px 0;">' . $ola . '</p>

        <p style="color:#4A5561; font-family:' . $fonte . '; font-size:16px; line-height:1.65; margin:0 0 24px 0;">' . $frase . '</p>

        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 26px 0;">
          <tr>
            <td style="background-color:' . $faixa_bg . '; border-left:4px solid ' . $faixa_borda . '; border-radius:0 6px 6px 0; padding:13px 16px; color:' . $faixa_cor . '; font-family:' . $fonte . '; font-size:14px; font-weight:700; letter-spacing:.2px;">'
              . esc_html($faixa_txt) . '</td>
          </tr>
        </table>

        <p style="color:#8A94A0; font-family:' . $fonte . '; font-size:12px; font-weight:700; margin:0 0 4px 0; text-transform:uppercase; letter-spacing:.8px;">'
          . ($um ? 'Material' : 'Materiais') . '</p>

        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px 0; font-family:' . $fonte . ';">'
          . $itens .
        '</table>

        <p style="color:#4A5561; font-family:' . $fonte . '; font-size:16px; line-height:1.65; margin:0 0 ' . ($extra ? '14px' : '28px') . ' 0;">'
          . esc_html($fecho) . '</p>'

        . ($extra
            ? '<p style="color:#4A5561; font-family:' . $fonte . '; font-size:16px; line-height:1.65; margin:0 0 28px 0;">'
              . esc_html($extra) . '</p>'
            : '') . '

        <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto;">
          <tr>
            <td align="center" style="background-color:' . $grafite . '; border-radius:6px;">
              <a href="' . $link . '"
                 style="display:inline-block; padding:15px 38px; color:#FFFFFF; font-family:' . $fonte . '; font-size:16px; font-weight:700; text-decoration:none; border-radius:6px;">'
                . esc_html($cta) . '</a>
            </td>
          </tr>
        </table>'

        . ($sugerir_compra
            ? '<table cellpadding="0" cellspacing="0" border="0" align="center" style="margin:12px auto 0 auto;">
          <tr>
            <td align="center" style="border:2px solid ' . $amarelo . '; border-radius:6px; background-color:#FFFDF3;">
              <a href="' . esc_url(self::url_loja()) . '"
                 style="display:inline-block; padding:13px 34px; color:' . $grafite . '; font-family:' . $fonte . '; font-size:15px; font-weight:700; text-decoration:none; border-radius:6px;">Ver materiais na loja</a>
            </td>
          </tr>
        </table>'
            : '') . '

        <p style="color:#8A94A0; font-family:' . $fonte . '; font-size:14px; line-height:1.6; margin:28px 0 0 0; text-align:center;">
          Qualquer dúvida, é só responder este e-mail que a gente ajuda.
        </p>

      </td>
    </tr>

    <tr>
      <td style="background-color:#FAFBFC; padding:22px 40px; border-top:1px solid #EDF0F3;">
        <p style="color:#8A94A0; font-family:' . $fonte . '; font-size:12px; line-height:1.7; margin:0; text-align:center;">
          <strong style="color:' . $grafite . ';">Laboratório de Resumos</strong><br />
          <a href="' . $site . '" style="color:#8A94A0; text-decoration:none;">labresumos.com.br</a><br />
          <span style="color:#AAB3BC;">&copy; ' . $ano . ' &middot; Todos os direitos reservados</span>
        </p>
      </td>
    </tr>

  </table>

</td></tr>
</table>
</body>
</html>';
    }

    /**
     * URL da área do aluno (Moodle), com fallback para o site.
     *
     * @return string
     */
    public static function url_aluno() {
        $conn = get_option('eb_connection');
        if (!empty($conn['eb_url'])) {
            return trailingslashit($conn['eb_url']) . 'my/courses.php';
        }
        return home_url('/');
    }

    /**
     * WP-CLI: mostra quem receberia o quê, sem enviar.
     *
     * ## OPTIONS
     *
     * [--marco=<n>]
     * : 30 (aviso prévio) ou 0 (dia do vencimento). Padrão: ambos.
     *
     * [--html]
     * : Imprime o HTML do primeiro e-mail (para conferir o visual).
     */
    public static function cli_preview($args, $assoc) {
        $marcos = isset($assoc['marco']) ? [(int) $assoc['marco']] : [self::DIAS_AVISO, 0];

        foreach ($marcos as $m) {
            $r = self::processa_marco($m, true);
            $rot = $m > 0 ? "AVISO PREVIO ($m dias antes)" : 'DIA DO VENCIMENTO';
            \WP_CLI::log("\n=== $rot ===");
            \WP_CLI::log('Alunos que receberiam: ' . count($r['linhas']));

            foreach (array_slice($r['linhas'], 0, 10) as $l) {
                \WP_CLI::log(sprintf('   %-38s vence %s | %d material(is)',
                    $l['email'], $l['dia'], count($l['cursos'])));
                foreach (array_slice($l['cursos'], 0, 3) as $c) {
                    \WP_CLI::log('        - ' . $c);
                }
                if (count($l['cursos']) > 3) {
                    \WP_CLI::log('        ... +' . (count($l['cursos']) - 3) . ' outros');
                }
            }

            if (isset($assoc['html']) && !empty($r['linhas'])) {
                $l = $r['linhas'][0];
                $u = get_user_by('email', $l['email']);
                \WP_CLI::log("\n--- HTML ---\n" .
                    self::template($u, $l['cursos'], date_i18n('d/m/Y', strtotime($l['dia'])), $m));
            }
        }
    }

    /**
     * WP-CLI: dispara o processamento agora (respeita o dry-run da option).
     */
    public static function cli_run() {
        $r = self::run();
        foreach ($r as $marco => $d) {
            \WP_CLI::log(sprintf('marco %-3s: %d grupos, %d enviados, %d pulados',
                $marco, $d['grupos'], $d['enviados'], $d['pulados']));
        }
        \WP_CLI::success(self::is_enabled()
            ? 'Envio real concluido.'
            : 'Dry-run (lra_expiry_mail_enabled desligado): nada foi enviado.');
    }
}
