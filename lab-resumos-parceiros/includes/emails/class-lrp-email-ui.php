<?php
/**
 * Componentes visuais dos e-mails do Programa de Parceiros.
 *
 * Toda a identidade Lab Resumos (paleta, tipografia, blocos) vive aqui.
 * Os templates em `includes/emails/templates/` montam a mensagem chamando
 * estes helpers, em vez de repetir `<table>` e estilo inline.
 *
 * REGRAS DE E-MAIL HTML aplicadas em todos os componentes (mesma técnica
 * dos e-mails de rollout do Arena, validados em Gmail dark com 2.321 envios):
 *
 * 1. Layout em `<table role="presentation">`, nunca flex/grid.
 * 2. Todo estilo inline, nada de `<style>` ou classe.
 * 3. Cor de fundo sempre em TRÊS lugares: atributo `bgcolor`,
 *    `background-color` e `background-image:linear-gradient(cor,cor)`.
 *    O gradiente é o que impede o cliente de repintar o fundo.
 * 4. **A PALETA É DARK POR DECISÃO, NÃO POR ESTÉTICA.** Foi a correção de um
 *    bug real: com fundo claro, o Gmail iOS em dark mode inverteu o TEXTO
 *    (navy -> azul claro) mas NÃO conseguiu inverter o fundo (travado pelo
 *    gradiente do item 3) - resultado: texto claro sobre fundo claro,
 *    ilegível. Num design já escuro o cliente não tem o que inverter, que é
 *    exatamente por que o e-mail do Arena (`#0B0D12`) nunca teve o problema.
 *    NÃO converter esta paleta para fundo claro.
 * 5. Cor de texto é `color:#hex` simples. NÃO usar `color:transparent` +
 *    `background-clip:text`: em cliente que suporta `transparent` mas ignora
 *    `background-clip`, o texto fica invisível (reproduzido em renderização).
 * 6. Largura fixa 600px com `max-width:100%` para caber no mobile.
 *
 * A identidade continua sendo a da Lab: o site já é navy + creme + amarelo
 * (ver `labresumos-child`), então o e-mail escuro é a marca, não um desvio.
 *
 * @package Lab_Resumos_Parceiros
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Email_UI
 */
class LRP_Email_UI {

    /**
     * Paleta Lab Resumos em modo escuro (o site já é navy + creme + amarelo).
     * Os nomes NAVY/CREAM seguem a marca; o papel de cada um no e-mail está
     * no comentário. Ver item 4 do cabeçalho antes de clarear qualquer fundo.
     */
    const SHELL       = '#12161D'; // fundo externo da mensagem
    const NAVY        = '#1E242F'; // cartão principal
    const SURFACE     = '#2A3240'; // blocos elevados (detalhes, avisos)
    const CREAM       = '#F3F1E8'; // texto principal (papel da marca)
    /**
     * Texto de maior ênfase. É off-white COM MATIZ, nunca `#FFFFFF`.
     *
     * Terceira armadilha do Gmail iOS em dark mode, observada em teste real:
     * ele inverte cores NEUTRAS EXTREMAS (`#FFFFFF`, `#000000`) e preserva
     * qualquer cor com matiz. Com `#FFFFFF` os títulos, os valores em reais e
     * os `strong` ficaram escuros sobre fundo escuro - sumiram - enquanto o
     * creme `#F3F1E8` ao lado, no mesmo parágrafo, apareceu normal.
     * Mantenha o mesmo perfil de matiz do CREAM (R > G > B, spread ~10).
     */
    const CREAM_LIGHT = '#FAF8F0';
    const GOLD        = '#F1CC00'; // dinheiro, CTA principal
    const GOLD_SOFT   = '#FFE066'; // amarelo legível como texto sobre escuro
    const BLUE        = '#4BA3F0'; // informação, CTA secundário
    const RED         = '#F4756B'; // atenção
    const MUTED       = '#A3AAB8'; // texto de apoio
    const LINE        = '#39414F'; // divisórias

    /** Compatibilidade: navy claro usado como texto sobre superfícies claras. */
    const NAVY_SOFT   = '#1E242F';

    const FONT = "'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif";

    /**
     * Paleta de cada "tom" usado nos blocos.
     *
     * @param string $tone gold|blue|navy|red|neutral
     * @return array{bg:string,border:string,ink:string,accent:string}
     */
    private static function tone($tone) {
        $map = [
            'gold' => [
                'bg'     => '#2E2A14',
                'border' => '#5C5320',
                'ink'    => self::GOLD_SOFT,
                'accent' => self::GOLD,
            ],
            'blue' => [
                'bg'     => '#17293C',
                'border' => '#2C4A66',
                'ink'    => '#9CCBF5',
                'accent' => self::BLUE,
            ],
            'navy' => [
                'bg'     => self::SURFACE,
                'border' => self::LINE,
                'ink'    => self::CREAM,
                'accent' => self::GOLD,
            ],
            'red' => [
                'bg'     => '#33201F',
                'border' => '#6B3733',
                'ink'    => '#F6A6A0',
                'accent' => self::RED,
            ],
        ];

        return isset($map[$tone]) ? $map[$tone] : [
            'bg'     => self::SURFACE,
            'border' => self::LINE,
            'ink'    => self::MUTED,
            'accent' => self::MUTED,
        ];
    }

    /**
     * Trava uma cor de fundo contra o dark mode dos clientes.
     *
     * @param string $hex
     * @return string trecho de CSS inline
     */
    public static function bg($hex) {
        return 'background-color:' . $hex . '; background-image:linear-gradient(' . $hex . ',' . $hex . ');';
    }

    /**
     * Cor de texto blindada contra o dark mode do Gmail iOS - em DUAS camadas.
     *
     * O QUE O GMAIL iOS FAZ (Rémi Parmentier, autor do Can I Email; reproduzido
     * aqui em teste real): em dark mode ele reescreve `color` e
     * `background-color` de TODO elemento - inverte claro em escuro. Não é
     * heurística de "branco puro", como parecia: o off-white #FAF8F0 sumiu
     * igual. A única coisa que ele NÃO reescreve é `background-image`.
     * Por isso o e-mail do Arena sobreviveu a 2.321 envios: o texto era
     * pintado por um `linear-gradient` recortado pelas letras.
     *
     * A forma ingênua (`color:transparent` + `background-clip:text` inline)
     * tem uma falha grave, reproduzida em renderização: cliente sem
     * `background-clip` mostra NADA (Outlook clássico, Yahoo, GANGA, vários
     * webmails). Então separamos em duas camadas com destino conhecido:
     *
     *   CAMADA 1 (inline, aqui):  `color:#hex` sólido + classe `lr-ink-<hex>`.
     *      Todo cliente enxerga a cor. É o fallback universal.
     *
     *   CAMADA 2 (<style> no envelope, ver LRP_Email_Manager::wrap_html):
     *      `u + .body .lr-ink-<hex> { color:transparent;
     *       background-image:linear-gradient(hex,hex);
     *       -webkit-background-clip:text; background-clip:text }`
     *      O seletor `u + .body` só casa no Gmail (ele troca o doctype por
     *      `<u></u>`, howtotarget.email). E o Gmail suporta `<style>` +
     *      `background-clip` (Can I Email). Ali a tinta vira gradiente, que o
     *      dark mode não toca. Onde `<style>` não existe (GANGA, Outlook
     *      Windows) a regra nem chega: fica a camada 1, nunca texto vazio.
     *
     *      POR QUE `!important` EM TUDO: a camada 1 é INLINE, e estilo inline
     *      vence qualquer regra de <style> sem `!important` - regra básica de
     *      cascata. Sem ele o `color:transparent` da camada 2 nunca aplicou:
     *      a cor inline (invertida pelo Gmail para escuro) venceu, o gradiente
     *      aplicou sozinho e o texto virou miolo escuro com halo claro. Foi o
     *      quarto teste em produção que revelou isso (set/2026). A referência
     *      pública que fez esta técnica funcionar no Gmail iOS usa exatamente
     *      `color: transparent!important` (stackoverflow 71914548).
     *
     *      POR QUE `color:transparent` E NÃO `-webkit-text-fill-color`:
     *      testado em produção (set/2026). Com text-fill-color o Gmail iOS
     *      DESCARTOU a propriedade (não consta no Can I Email), inverteu a
     *      `color` sólida para escuro e o gradiente claro ficou só como um
     *      halo em volta das letras. `color:transparent` o Gmail preserva -
     *      é exatamente o que o e-mail do Arena usava. E, como esta regra só
     *      roda no Gmail, o risco de "transparente sem tinta" não existe aqui.
     *      POR QUE `background-image` LONGHAND E NÃO O SHORTHAND: é a forma
     *      que Rémi Parmentier documenta como intocada pelo dark mode e a que
     *      o Arena usou. Não arriscar o shorthand.
     *
     * As classes usadas são coletadas em self::$inks para o envelope emitir
     * só as regras necessárias.
     *
     * @param string $hex
     * @return string trecho de CSS inline (a classe vem por ink_class())
     */
    public static function ink($hex) {
        self::$inks[strtoupper($hex)] = true;
        return 'color:' . $hex . ';';
    }

    /** Cores de texto usadas na mensagem atual (para o <style> do envelope). */
    public static $inks = [];

    /**
     * Nome da classe que a camada 2 usa para uma cor.
     *
     * @param string $hex
     * @return string
     */
    public static function ink_class($hex) {
        return 'lr-ink-' . strtolower(ltrim($hex, '#'));
    }

    /**
     * Atributos completos (class + style) para um elemento de texto.
     * Uso: '<div ' . LRP_Email_UI::text($hex, 'font-size:16px;') . '>'
     *
     * @param string $hex
     * @param string $extra_style
     * @return string
     */
    public static function text($hex, $extra_style = '') {
        return 'class="' . self::ink_class($hex) . '" style="' . $extra_style . ' ' . self::ink($hex) . '"';
    }

    /**
     * Bloco <style> da camada 2, para o envelope. Uma regra por cor usada.
     *
     * @return string
     */
    public static function gmail_ink_styles() {
        if (empty(self::$inks)) {
            return '';
        }
        $rules = '';
        foreach (array_keys(self::$inks) as $hex) {
            $rules .= 'u + .body .' . self::ink_class($hex)
                . '{color:transparent!important;'
                . 'background-image:linear-gradient(' . $hex . ',' . $hex . ')!important;'
                . '-webkit-background-clip:text!important;background-clip:text!important;}' . "\n";
        }
        return "<style>\n" . $rules . "</style>";
    }

    /**
     * Aplica a camada 1 em um HTML já montado: toda tag cujo `style` declara
     * `color:#hex` ganha a classe `lr-ink-<hex>` correspondente (e a cor é
     * registrada em self::$inks). Roda uma vez no envelope, sobre o corpo
     * inteiro, para os templates não precisarem se preocupar com classe.
     *
     * Ignora `background-color:` (lookbehind) e tags que já têm `class=`.
     *
     * @param string $html
     * @return string
     */
    public static function bind_ink_classes($html) {
        return preg_replace_callback(
            '/<([a-z][a-z0-9]*)((?:\s+[a-z-]+="[^"]*")*)\s*>/i',
            function ($m) {
                $tag   = $m[1];
                $attrs = $m[2];

                if (stripos($attrs, 'class=') !== false) {
                    return $m[0];
                }
                if (!preg_match('/style="([^"]*)"/i', $attrs, $sm)) {
                    return $m[0];
                }
                if (!preg_match('/(?<![\w-])color:\s*(#[0-9a-f]{6})/i', $sm[1], $cm)) {
                    return $m[0];
                }

                $hex = $cm[1];
                self::$inks[strtoupper($hex)] = true;

                return '<' . $tag . ' class="' . self::ink_class($hex) . '"' . $attrs . '>';
            },
            $html
        );
    }

    /**
     * Topo da mensagem: selo, ícone, título e linha de apoio.
     *
     * @param array $args eyebrow, icon, title, subtitle, tone
     * @return string
     */
    public static function hero($args = []) {
        $a = array_merge([
            'eyebrow'  => '',
            'icon'     => '',
            'title'    => '',
            'subtitle' => '',
            'tone'     => 'gold',
        ], $args);

        $t = self::tone($a['tone']);
        $out = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;"><tr><td align="center" style="padding:0 0 4px;">';

        if ($a['eyebrow'] !== '') {
            $out .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 18px;"><tr>'
                . '<td align="center" bgcolor="' . $t['bg'] . '" style="text-align:center; border:1px solid ' . $t['border'] . '; border-radius:20px; padding:7px 16px; ' . self::bg($t['bg']) . '">'
                . '<span style="font-family:' . self::FONT . '; font-weight:bold; font-size:11px; letter-spacing:1.2px; text-transform:uppercase; ' . self::ink($t['ink']) . '">'
                . esc_html($a['eyebrow'])
                . '</span></td></tr></table>';
        }

        if ($a['icon'] !== '') {
            $out .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 18px;"><tr>'
                . '<td width="72" height="72" align="center" valign="middle" bgcolor="' . $t['bg'] . '" style="width:72px; height:72px; border:2px solid ' . $t['accent'] . '; border-radius:36px; ' . self::bg($t['bg']) . ' font-size:30px; line-height:72px; text-align:center;">'
                . $a['icon']
                . '</td></tr></table>';
        }

        if ($a['title'] !== '') {
            $out .= '<div style="font-family:' . self::FONT . '; font-size:26px; font-weight:bold; line-height:1.25; letter-spacing:-0.02em; text-align:center; margin:0 0 10px; ' . self::ink(self::CREAM_LIGHT) . '">'
                . $a['title'] . '</div>';
        }

        if ($a['subtitle'] !== '') {
            $out .= '<div style="font-family:' . self::FONT . '; font-size:16px; line-height:1.6; text-align:center; margin:0; ' . self::ink(self::MUTED) . '">'
                . $a['subtitle'] . '</div>';
        }

        return $out . '</td></tr></table>';
    }

    /**
     * Parágrafo padrão.
     *
     * @param string $html
     * @param array  $args align, size, color
     * @return string
     */
    public static function p($html, $args = []) {
        $a = array_merge(['align' => 'left', 'size' => 16, 'color' => self::CREAM], $args);

        return '<p style="font-family:' . self::FONT . '; font-size:' . (int) $a['size'] . 'px; line-height:1.65; text-align:' . $a['align'] . '; margin:16px 0; ' . self::ink($a['color']) . '">' . $html . '</p>';
    }

    /**
     * Destaque forte dentro de um parágrafo (mantém a cor travada).
     *
     * @param string $text
     * @param string $color
     * @return string
     */
    public static function strong($text, $color = self::CREAM_LIGHT) {
        return '<b style="' . self::ink($color) . '">' . $text . '</b>';
    }

    /**
     * Subtítulo de seção.
     *
     * @param string $text
     * @return string
     */
    public static function h3($text) {
        return '<div style="font-family:' . self::FONT . '; font-size:18px; font-weight:bold; line-height:1.35; margin:30px 0 6px; ' . self::ink(self::CREAM_LIGHT) . '">' . $text . '</div>';
    }

    /**
     * Cartão de valor: o número é o herói da mensagem.
     *
     * @param array $args label, value, caption, tone
     * @return string
     */
    public static function amount($args = []) {
        $a = array_merge([
            'label'   => '',
            'value'   => '',
            'caption' => '',
            'tone'    => 'gold',
        ], $args);

        $t = self::tone($a['tone']);

        $out = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:26px 0;"><tr>'
            . '<td align="center" bgcolor="' . $t['bg'] . '" style="text-align:center; border:1px solid ' . $t['border'] . '; border-radius:18px; padding:26px 22px; ' . self::bg($t['bg']) . '">';

        if ($a['label'] !== '') {
            $out .= '<div style="font-family:' . self::FONT . '; font-size:11px; font-weight:bold; letter-spacing:1.2px; text-transform:uppercase; text-align:center; margin:0 0 10px; ' . self::ink($t['ink']) . '">' . esc_html($a['label']) . '</div>';
        }

        $out .= '<div style="font-family:' . self::FONT . '; font-size:38px; font-weight:bold; line-height:1.1; letter-spacing:-0.03em; text-align:center; margin:0; ' . self::ink(self::CREAM_LIGHT) . '">' . $a['value'] . '</div>';

        if ($a['caption'] !== '') {
            $out .= '<div style="font-family:' . self::FONT . '; font-size:13px; line-height:1.5; text-align:center; margin:10px 0 0; ' . self::ink($t['ink']) . '">' . $a['caption'] . '</div>';
        }

        return $out . '</td></tr></table>';
    }

    /**
     * Lista de dados rótulo/valor (pedido, CPF, período...).
     *
     * @param array $rows   [['label' => '', 'value' => '', 'strong' => bool], ...]
     * @param array $args   title, tone
     * @return string
     */
    public static function details($rows, $args = []) {
        $a = array_merge(['title' => '', 'tone' => 'neutral'], $args);
        $t = self::tone($a['tone']);

        $rows = array_values(array_filter($rows, function ($r) {
            return isset($r['value']) && $r['value'] !== '' && $r['value'] !== null;
        }));

        if (empty($rows)) {
            return '';
        }

        $out = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:22px 0;"><tr>'
            . '<td bgcolor="' . $t['bg'] . '" style="border:1px solid ' . $t['border'] . '; border-radius:16px; padding:8px 20px; ' . self::bg($t['bg']) . '">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">';

        if ($a['title'] !== '') {
            $out .= '<tr><td colspan="2" style="padding:14px 0 6px; font-family:' . self::FONT . '; font-size:11px; font-weight:bold; letter-spacing:1.2px; text-transform:uppercase; ' . self::ink($t['ink']) . '">' . esc_html($a['title']) . '</td></tr>';
        }

        $last = count($rows) - 1;
        foreach ($rows as $i => $row) {
            $is_strong = !empty($row['strong']);
            $border    = $i === $last ? '' : 'border-bottom:1px solid ' . $t['border'] . ';';
            $size      = $is_strong ? 17 : 14;
            $weight    = $is_strong ? 'bold' : 'normal';
            $value_ink = $is_strong ? self::ink(self::CREAM_LIGHT) : self::ink(self::CREAM);

            $out .= '<tr>'
                . '<td align="left" valign="middle" style="' . $border . ' text-align:left; padding:13px 10px 13px 0; font-family:' . self::FONT . '; font-size:' . $size . 'px; font-weight:' . $weight . '; ' . self::ink(self::MUTED) . '">' . esc_html($row['label']) . '</td>'
                . '<td align="right" valign="middle" style="' . $border . ' text-align:right; padding:13px 0; font-family:' . self::FONT . '; font-size:' . $size . 'px; font-weight:bold; ' . $value_ink . '">' . $row['value'] . '</td>'
                . '</tr>';
        }

        return $out . '</table></td></tr></table>';
    }

    /**
     * Botão. `bulletproof`: fundo na `<td>`, não no `<a>`.
     *
     * @param string $url
     * @param string $label
     * @param array  $args tone
     * @return string
     */
    public static function button($url, $label, $args = []) {
        $a    = array_merge(['tone' => 'gold'], $args);
        $gold = $a['tone'] === 'gold';
        $bg   = $gold ? self::GOLD : self::BLUE;
        $fg   = $gold ? '#1E242F' : '#0B1B2B';

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:30px auto 8px;"><tr>'
            . '<td align="center" bgcolor="' . $bg . '" style="text-align:center; border-radius:16px; ' . self::bg($bg) . '">'
            . '<a href="' . esc_url($url) . '" style="display:inline-block; text-decoration:none; font-family:' . self::FONT . '; font-weight:bold; font-size:15px; letter-spacing:0.02em; text-transform:uppercase; padding:17px 38px;">'
            . '<span style="' . self::ink($fg) . '">' . esc_html($label) . '</span>'
            . '</a></td></tr></table>';
    }

    /**
     * Aviso em caixa (atenção, informação, lembrete).
     *
     * @param string $html
     * @param array  $args tone, icon
     * @return string
     */
    public static function note($html, $args = []) {
        $a = array_merge(['tone' => 'blue', 'icon' => ''], $args);
        $t = self::tone($a['tone']);
        $icon = $a['icon'] !== '' ? $a['icon'] . ' ' : '';

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:22px 0;"><tr>'
            . '<td bgcolor="' . $t['bg'] . '" style="border:1px solid ' . $t['border'] . '; border-left:4px solid ' . $t['accent'] . '; border-radius:12px; padding:16px 18px; ' . self::bg($t['bg']) . '">'
            . '<div style="font-family:' . self::FONT . '; font-size:14px; line-height:1.6; margin:0; ' . self::ink($t['ink']) . '">' . $icon . $html . '</div>'
            . '</td></tr></table>';
    }

    /**
     * Passo a passo numerado, em cartões.
     *
     * @param array $steps ['texto', ...] ou [['title'=>'','text'=>''], ...]
     * @return string
     */
    public static function steps($steps) {
        $out = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:20px 0;">';

        foreach (array_values($steps) as $i => $step) {
            $title = is_array($step) ? $step['title'] : $step;
            $text  = is_array($step) && isset($step['text']) ? $step['text'] : '';

            $out .= '<tr><td style="padding:0 0 10px;">'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;"><tr>'
                . '<td width="34" valign="top" style="width:34px; padding:2px 0 0;">'
                . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
                . '<td width="26" height="26" align="center" valign="middle" bgcolor="' . self::GOLD . '" style="width:26px; height:26px; border-radius:13px; ' . self::bg(self::GOLD) . ' font-family:' . self::FONT . '; font-size:13px; font-weight:bold; line-height:26px; text-align:center;"><span style="' . self::ink('#1E242F') . '">' . ($i + 1) . '</span></td>'
                . '</tr></table></td>'
                . '<td valign="top" style="font-family:' . self::FONT . '; font-size:15px; line-height:1.55; ' . self::ink(self::CREAM) . '">'
                . self::strong($title)
                . ($text !== '' ? '<span style="' . self::ink(self::MUTED) . '"> - ' . $text . '</span>' : '')
                . '</td></tr></table></td></tr>';
        }

        return $out . '</table>';
    }

    /**
     * Bloco monoespaçado para cupom / link de indicação (fácil de copiar).
     *
     * @param string $value
     * @param array  $args label, big
     * @return string
     */
    public static function code_block($value, $args = []) {
        $a = array_merge(['label' => '', 'big' => false], $args);
        $size = $a['big'] ? 26 : 13;
        $font = $a['big'] ? self::FONT : "'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace";

        $out = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:14px 0 18px;"><tr>';

        if ($a['label'] !== '') {
            $out = '<div style="font-family:' . self::FONT . '; font-size:11px; font-weight:bold; letter-spacing:1.2px; text-transform:uppercase; text-align:center; margin:20px 0 6px; ' . self::ink(self::MUTED) . '">' . esc_html($a['label']) . '</div>' . $out;
        }

        return $out
            . '<td align="center" bgcolor="' . self::SURFACE . '" style="text-align:center; border:1px solid ' . self::LINE . '; border-radius:14px; padding:' . ($a['big'] ? '20px' : '14px') . ' 18px; ' . self::bg(self::SURFACE) . '">'
            . '<span style="font-family:' . $font . '; font-size:' . $size . 'px; font-weight:bold; letter-spacing:' . ($a['big'] ? '2px' : '0') . '; text-align:center; word-break:break-all; ' . self::ink($a['big'] ? self::GOLD : self::CREAM) . '">' . esc_html($value) . '</span>'
            . '</td></tr></table>';
    }

    /**
     * Linha divisória.
     *
     * @return string
     */
    public static function divider() {
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:26px 0;"><tr>'
            . '<td height="1" bgcolor="' . self::LINE . '" style="height:1px; line-height:1px; font-size:0; ' . self::bg(self::LINE) . '">&nbsp;</td>'
            . '</tr></table>';
    }

    /**
     * Assinatura da equipe.
     *
     * @param string $line
     * @return string
     */
    public static function signoff($line = 'Boas vendas,') {
        return '<p style="font-family:' . self::FONT . '; font-size:16px; line-height:1.6; margin:28px 0 0; ' . self::ink(self::CREAM) . '">'
            . esc_html($line) . '<br>'
            . self::strong('Equipe Lab Resumos')
            . '</p>';
    }
}
