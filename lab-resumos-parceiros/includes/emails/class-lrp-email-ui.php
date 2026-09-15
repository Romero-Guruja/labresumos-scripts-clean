<?php
/**
 * Componentes visuais dos e-mails do Programa de Parceiros.
 *
 * Toda a identidade Lab Resumos (paleta, tipografia, blocos) vive aqui.
 * Os templates em `includes/emails/templates/` montam a mensagem chamando
 * estes helpers, em vez de repetir `<table>` e estilo inline.
 *
 * REGRAS DE E-MAIL HTML aplicadas em todos os componentes (mesma técnica
 * usada nos e-mails de rollout do Arena, validados em Gmail/Outlook/Apple):
 *
 * 1. Layout em `<table role="presentation">`, nunca flex/grid.
 * 2. Todo estilo inline, nada de `<style>` ou classe.
 * 3. Cor de fundo sempre em TRÊS lugares: atributo `bgcolor`,
 *    `background-color` e `background-image:linear-gradient(cor,cor)`.
 *    O gradiente é o que impede o dark mode do Gmail/Outlook de inverter.
 * 4. Cor de texto travada com `color:transparent` + `background-image` +
 *    `background-clip:text` (mesmo motivo). Fallback: clientes que não
 *    suportam `background-clip` caem no `color` declarado antes.
 * 5. Largura fixa 600px com `max-width:100%` para caber no mobile.
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

    /** Paleta Lab Resumos (espelha o site e o child theme labresumos-child). */
    const NAVY        = '#262D38'; // fundo institucional
    const NAVY_SOFT   = '#333B49'; // texto principal
    const CREAM       = '#F3F1E8'; // papel
    const CREAM_LIGHT = '#FCFBF6'; // cartão
    const GOLD        = '#F1CC00'; // dinheiro, CTA principal
    const GOLD_DARK   = '#B9861A';
    const BLUE        = '#0475CF'; // informação, CTA secundário
    const RED         = '#B8231E'; // atenção
    const MUTED       = '#69727D'; // texto de apoio
    const LINE        = '#E4E1D6'; // divisórias

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
                'bg'     => '#FDF6D4',
                'border' => '#EFD873',
                'ink'    => '#5C4A00',
                'accent' => self::GOLD,
            ],
            'blue' => [
                'bg'     => '#E7F1FB',
                'border' => '#B9D7F2',
                'ink'    => '#0B4C85',
                'accent' => self::BLUE,
            ],
            'navy' => [
                'bg'     => self::NAVY,
                'border' => '#3F4858',
                'ink'    => self::CREAM,
                'accent' => self::GOLD,
            ],
            'red' => [
                'bg'     => '#FBEAE9',
                'border' => '#F0C2C0',
                'ink'    => '#7E1815',
                'accent' => self::RED,
            ],
        ];

        return isset($map[$tone]) ? $map[$tone] : [
            'bg'     => '#F0EEE4',
            'border' => self::LINE,
            'ink'    => self::NAVY_SOFT,
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
     * Cor de texto.
     *
     * NÃO usar a técnica `color:transparent` + `background-clip:text` aqui,
     * mesmo ela sendo comum em newsletters: num cliente que suporta
     * `color:transparent` mas ignora `background-clip`, o texto fica
     * literalmente INVISÍVEL. Foi reproduzido ao renderizar estes e-mails
     * (WeasyPrint) e o mesmo vale para vários webmails.
     *
     * A proteção real contra dark mode é o fundo travado em `bg()` (um
     * gradiente não é invertido pelos clientes), somada às metas
     * `color-scheme`/`supported-color-schemes` do envelope. Com o fundo
     * preservado, a cor declarada aqui é mantida.
     *
     * @param string $hex
     * @return string trecho de CSS inline
     */
    public static function ink($hex) {
        return 'color:' . $hex . ';';
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
            $out .= '<div style="font-family:' . self::FONT . '; font-size:26px; font-weight:bold; line-height:1.25; letter-spacing:-0.02em; text-align:center; margin:0 0 10px; ' . self::ink(self::NAVY) . '">'
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
        $a = array_merge(['align' => 'left', 'size' => 16, 'color' => self::NAVY_SOFT], $args);

        return '<p style="font-family:' . self::FONT . '; font-size:' . (int) $a['size'] . 'px; line-height:1.65; text-align:' . $a['align'] . '; margin:16px 0; ' . self::ink($a['color']) . '">' . $html . '</p>';
    }

    /**
     * Destaque forte dentro de um parágrafo (mantém a cor travada).
     *
     * @param string $text
     * @param string $color
     * @return string
     */
    public static function strong($text, $color = self::NAVY) {
        return '<b style="' . self::ink($color) . '">' . $text . '</b>';
    }

    /**
     * Subtítulo de seção.
     *
     * @param string $text
     * @return string
     */
    public static function h3($text) {
        return '<div style="font-family:' . self::FONT . '; font-size:18px; font-weight:bold; line-height:1.35; margin:30px 0 6px; ' . self::ink(self::NAVY) . '">' . $text . '</div>';
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

        $out .= '<div style="font-family:' . self::FONT . '; font-size:38px; font-weight:bold; line-height:1.1; letter-spacing:-0.03em; text-align:center; margin:0; ' . self::ink(self::NAVY) . '">' . $a['value'] . '</div>';

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
            $value_ink = $is_strong ? self::ink(self::NAVY) : self::ink(self::NAVY_SOFT);

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
        $fg   = $gold ? self::NAVY : self::CREAM;

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
                . '<td width="26" height="26" align="center" valign="middle" bgcolor="' . self::GOLD . '" style="width:26px; height:26px; border-radius:13px; ' . self::bg(self::GOLD) . ' font-family:' . self::FONT . '; font-size:13px; font-weight:bold; line-height:26px; text-align:center; ' . self::ink(self::NAVY) . '">' . ($i + 1) . '</td>'
                . '</tr></table></td>'
                . '<td valign="top" style="font-family:' . self::FONT . '; font-size:15px; line-height:1.55; ' . self::ink(self::NAVY_SOFT) . '">'
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
            . '<td align="center" bgcolor="' . self::NAVY . '" style="text-align:center; border-radius:14px; padding:' . ($a['big'] ? '20px' : '14px') . ' 18px; ' . self::bg(self::NAVY) . '">'
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
        return '<p style="font-family:' . self::FONT . '; font-size:16px; line-height:1.6; margin:28px 0 0; ' . self::ink(self::NAVY_SOFT) . '">'
            . esc_html($line) . '<br>'
            . self::strong('Equipe Lab Resumos')
            . '</p>';
    }
}
