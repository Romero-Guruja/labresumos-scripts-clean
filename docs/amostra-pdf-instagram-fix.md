# Fix: amostra em PDF disparando "You're leaving our app" no Instagram

**Data:** 2026-07-20 · **Site:** labresumos.com.br · **Reportado por:** Rafael (tráfego)

## Sintoma

Anúncios do Instagram para páginas de produto (ex.: `/produto/pacotebasicofiscal/`)
mostravam o diálogo **"You're leaving our app"** e jogavam o usuário para
`.../wp-content/uploads/2026/07/RLM-Amostra-Gratis.pdf`, em vez de manter na página de
venda. Não reproduzia no Chrome/Safari de desktop.

## Causa raiz

A aba **"Amostra Grátis"** dos produtos (plugin `wb-custom-product-tabs-for-woocommerce`,
gravada no **postmeta `wb_custom_tabs`**) continha um `<iframe src="....pdf">` renderizado
direto no HTML do servidor. O iframe começa a **buscar o PDF no carregamento da página**,
independente de a aba estar aberta.

- Navegador normal → tem viewer de PDF nativo → renderiza inline, sem alerta.
- **Webview do Instagram/Facebook → não renderiza PDF** → trata como "abrir handler
  externo" → dispara o diálogo sozinho, no load.

**Por que era sistêmico:** 34 produtos tinham o mesmo iframe. Qualquer um usado como
landing de anúncio reproduzia.

## Correção (estado final)

O `<iframe>` **nunca** fica no HTML inicial. A aba passou a conter um card `.lr-amostra`
com `data-pdf` e um `<script>` de detecção:

- **Padrão (mobile + navegadores in-app)** → botão "Ver amostra grátis (PDF)"
  (`<a target="_blank">`). Nada de PDF é buscado no load → sem diálogo.
- **Desktop** → o script injeta o `<iframe>` inline via JS (embed, como antes) e esconde
  o botão. Detecção por User-Agent:
  `/Instagram|FBAN|FBAV|FB_IAB|Line|Twitter|WhatsApp|Snapchat|Pinterest|Mobi|Android|iPhone|iPad|iPod/i`
  → se casar, mantém o botão; senão, injeta o embed.
- **Invariante de segurança**: como o iframe só é criado por JS (e só no desktop), se o
  JS falhar/for adiado, todos veem o botão → o bug nunca volta.

Aplicado em **34 produtos** (a URL do PDF é extraída de cada um). Scripts em
`docs/scripts/` (idempotentes, com backup por produto em `~/lra-backups/` no servidor).

## Dois gotchas que travaram o fix

1. **LiteSpeed "adiar JS"** reescrevia o `<script>` inline para
   `type="litespeed/javascript"` → o navegador não executava → embed não aparecia no
   desktop. **Fix:** atributo **`data-no-optimize="1"`** no `<script>` (LiteSpeed preserva
   e não troca o type). Sempre marcar assim scripts inline críticos.
2. **Paliativo antigo brigando com o fix.** Havia um snippet WPCode
   **#2975 "Impede direcionamento para PDF nos anúncios"** com um `MutationObserver` que
   destruía **qualquer** `iframe[src*=".pdf"]` injetado e o trocava por uma caixa própria.
   Ele matava o embed que o novo script injetava no desktop. **Desativado** — e exige
   **2 passos**: (1) `WPCode_Snippet(2975)->deactivate()` + `post_status=draft`;
   (2) **remover o item `id=2975` do option `wpcode_snippets`** — esse option é o *cache
   de execução* do WPCode (guarda o código inteiro) e o `deactivate()` **não** o regenera.

> **Nota:** esse paliativo era intrinsecamente frágil (JS client-side): no webview do
> Instagram ele perdia a corrida — o request do PDF começa quando o parser cria o
> `<iframe>`, antes do `MutationObserver` rodar, e JS não cancela request já iniciado.
> Por isso "não adiantava". A correção correta é remover o iframe da origem (feito).

## Como re-aplicar / operar

Acesso ao servidor: `ssh wp-napoleon` (usuário `servergurujalab`), WP-CLI com
`--path=~/domains/labresumos.com.br/public_html`. Rodar `wp eval-file` a partir do
**`$HOME`** (o `open_basedir` bloqueia PHP em `/tmp`). Sempre purgar depois:
`wp litespeed-purge all && wp cache flush`.

Verificar no HTML servido (deve dar 0 iframe de PDF na fonte, para qualquer UA):

```bash
curl -s -A "Mozilla/5.0 (iPhone; Instagram 300.0)" \
  "https://labresumos.com.br/produto/pacotebasicofiscal/?z=$(date +%s)" \
  | grep -oic '<iframe[^>]*\.pdf'   # esperado: 0
```

## Rollback

Backups por produto em `~/lra-backups/wb_custom_tabs_<ID>*.json` (JSON do meta original,
com o iframe). Restaurar um produto:

```bash
wp --path=~/domains/labresumos.com.br/public_html eval '
  $j = file_get_contents(getenv("HOME")."/lra-backups/wb_custom_tabs_2814.json");
  update_post_meta(2814, "wb_custom_tabs", json_decode($j, true));'
```

Reativar o paliativo WPCode (se algum dia necessário): `wp post update 2975 --post_status=publish`
pela UI do WPCode (para regenerar o cache `wpcode_snippets`).

## Upgrade futuro (opcional)

UX perfeita no in-app = renderizar a amostra como **imagens das páginas** (ou PDF.js) em
vez de PDF. O servidor **não tem ghostscript** (`convert` sozinho não rasteriza PDF), então
exigiria gerar as imagens fora e subir. Não feito — o botão click-to-open já resolve o bug.
