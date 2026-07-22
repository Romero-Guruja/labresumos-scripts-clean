# Incidente — páginas de produto exibindo o widget cru do Edwiser (não é regressão do F3)

> **Resumo:** relatado como "algo quebrou no port de snippets de ontem" — páginas de produto
> passaram a mostrar o design bruto do Edwiser Bridge (`eb-pro-product-page-widget`) em vez do
> layout customizado da Lab Resumos. **Não foi causado pela migração F3** (nem pelo
> `lab-resumos-storefront` nem por nenhum outro plugin novo). Causa real: o template Elementor
> genérico de produto (post `101`) tinha o `_elementor_data` reduzido a quase nada — um estado
> que já tinha ocorrido uma vez, isoladamente, em 07/01/2026, e ficou mascarado por meses pelo
> cache (LiteSpeed + Cloudflare). A purga de cache real feita durante os testes do F3d
> (ontem) foi o que **revelou** o problema, não o que o causou. Corrigido restaurando o
> conteúdo a partir do histórico de revisões nativo do WordPress. Afetava ~34 dos 51 produtos
> publicados (todos que usam o template genérico — os com a tag "Flashcard" usam outro
> template, nunca afetado).

## Como foi encontrado

Duas URLs de comparação dadas pelo Romero:
- ✅ `https://labresumos.com.br/produto/pacote-basico-fiscal-2/` (produto `2877`, tag
  **Flashcard**) — design correto.
- ❌ `https://labresumos.com.br/produto/pacotebasicofiscal/` (produto `2814`, tag **Combo**) —
  mostrando breadcrumb "All Courses / Área Fiscal / ...", "Category", "Review 0 (0)",
  "Associated Courses" — rótulos em inglês, sem estilo, direto do widget
  `eb-pro-product-page-widget` do Edwiser Bridge Pro.

`curl` nas duas páginas mostrou `body class` diferente só no `elementor-page-<ID>`: `1856` na
página certa, `101` na quebrada. Ambos são templates do **Elementor Theme Builder**
(`post_type=elementor_library`):

| ID | Título | Condição (`_elementor_conditions`) |
|---|---|---|
| `1856` | Edwiser Bridge Single Product Page - flashcard | `include/product/in_product_tag/49` (só tag Flashcard) |
| `101`  | Edwiser Bridge Single Product Page | `include/product` (**todos** os produtos sem condição mais específica) |

Ambos os produtos (`2877` e `2814`) têm `post_modified` de 08/07/2026 — **antes** de qualquer
trabalho do F3 (21/07). O template `101` em si também não tinha `post_modified` recente
(13/04/2026) — ou seja, nada relacionado à migração de snippets tocou esses posts.

## Causa raiz

`wp post meta get 101 _elementor_data` retornou só **350 caracteres** — na prática, um único
widget:
```json
[{"id":"f05411c","elType":"container","settings":[],"elements":[
  {"id":"ed57592","elType":"widget","settings":{"product_id":3029},"elements":[],"widgetType":"eb-pro-product-page-widget"},
  {"id":"eca12b3","elType":"widget","settings":{"title":"Related Courses","per_page":4},"elements":[],"widgetType":"eb-pro-related-product-widget"}
],"isInner":false}]
```
Isso **é** literalmente o widget cru do Edwiser — sem nenhuma das seções customizadas
(benefícios, "Neurociência"/"Expertise"/"Design", descrição do material, etc.).

O histórico de revisões nativo do WordPress (Elementor grava snapshot completo do
`_elementor_data` em cada revisão, via postmeta da própria revisão) mostrou o tamanho do
conteúdo ao longo do tempo:

```
... 968 → 27726  969 → 27731
1165 (2026-01-07 18:11:03) → 350   ← mesmo conteúdo "vazio" de hoje
1166 (2026-01-07 18:11:04) → 27731 ← corrigido 1s depois, na época
... segue crescendo normalmente até ...
2229 (2026-04-13 14:08:51) → 29827 ← última revisão boa, e também o estado "live" até o incidente
```

Ou seja: em 07/01/2026 já tinha ocorrido exatamente esse mesmo esvaziamento (provavelmente um
autosave do editor Elementor capturando o estado ainda carregando), só que na época foi
corrigido no minuto seguinte. Por alguma razão não identificada, o post `101` **voltou** a
ter o conteúdo de 350 bytes — igual ao blip de janeiro — sem que o `post_modified` do post
principal registrasse uma edição recente (13/04 é a última data real de edição pelo
Elementor). A hipótese mais provável, sem certeza de causa: o cache (LiteSpeed + Cloudflare)
vinha servindo a versão renderizada correta há meses; assim que o cache foi purgado de
verdade (via `lab-resumos-admin-tools`, botão "Limpar Cache" — testado ontem, ver F3d em
`docs/plugins-custom-analise-e-roadmap.md` §9), o site passou a servir o HTML gerado a partir
do dado real do banco — que já estava nesse estado. **Não foi possível confirmar quando
exatamente o dado voltou a ficar vazio** — não achamos uma revisão intermediária registrando
essa regressão entre abril e agora (o Elementor só cria revisão nova ao salvar pelo editor;
se o dado foi alterado por outro caminho — restore de backup parcial, sincronização de
staging→prod, etc. — não passa pelo mecanismo de revisão).

## O que foi feito

1. Backup do valor quebrado (`_elementor_data` e `_elementor_page_settings` do post `101`)
   salvo localmente antes de qualquer escrita.
2. **Restauração via SQL direto** (`UPDATE wp_postmeta SET meta_value = (SELECT meta_value
   FROM wp_postmeta WHERE post_id=2229 AND meta_key=...) WHERE post_id=101 AND meta_key=...`),
   copiando `_elementor_data` e `_elementor_page_settings` da revisão `2229` (última boa,
   13/04) pro post `101` (live).
   - **Achado de método:** a primeira tentativa usou `wp eval` com
     `update_post_meta(101, '_elementor_data', get_post_meta(2229, '_elementor_data', true))`
     — o round-trip **corrompeu o JSON** (`json_decode` passou a falhar, tamanho mudou de
     29827 para 29329). Não identificado exatamente qual hook/filtro alterou o conteúdo no
     meio do caminho, mas o SQL direto (que não passa pela API de meta do WordPress) copiou
     byte a byte sem esse problema — mesma lição do achado do F3d sobre `post_status`
     (preferir SQL direto a APIs internas quando precisão byte-a-byte importa).
3. `wp cache flush` **antes** de verificar — a primeira checagem pós-SQL parecia mostrar que
   nada tinha mudado (raw SQL `SELECT LENGTH()` já mostrava 29827 igual em ambos, mas
   `get_post_meta()` via PHP ainda retornava o valor antigo) — **cache de objeto (Redis)
   fica desatualizado depois de um `UPDATE` via SQL direto**, porque isso não invalida
   `wp_cache_delete()` automaticamente. Sempre `wp cache flush` depois de editar
   `wp_postmeta` via SQL puro, antes de confirmar o resultado.
4. `wp elementor flush_css` (regenera o CSS do template a partir do `_elementor_data` novo).
5. `wp litespeed-purge all && wp cache flush` (purga geral pós-fix).
6. Verificado em 2 produtos que usam o template genérico
   (`pacotebasicofiscal` e `reforma-tributaria-ec-132-lc-214-resumos-questoes-ineditas`) —
   ambos voltaram a mostrar o layout customizado (seções "Benefícios deste material",
   "Neurociência", "Expertise", preço formatado certo).

## Escopo do impacto

- **Template `101`** (genérico, `include/product`) é usado por qualquer produto **sem** a tag
  Flashcard — confirmado **34 de 51** produtos publicados nessa situação
  (`wp post list --post_type=product --post_status=publish` menos os 17 com a tag `49`).
  Todos os 34 estavam mostrando o widget cru do Edwiser até este fix.
- **Template `1856`** (tag Flashcard, `#49`) nunca foi afetado — os 17 produtos com essa tag
  sempre mostraram o design certo, o que originalmente mascarou a extensão real do problema
  (parecia "alguns produtos", quando na verdade era a maioria).

## Pendências / observações

- **Causa exata do esvaziamento não confirmada.** Não há uma revisão do WordPress registrando
  a transição de "bom" (13/04) pra "vazio" (visto hoje) — o mecanismo de revisão do Elementor
  só grava quando alguém salva pelo editor. Se isso se repetir, vale investigar: sincronização
  staging↔prod que sobrescreva `wp_postmeta` parcialmente, algum plugin de backup/restore
  rodando incompleto, ou o próprio WP Staging (mencionado como fonte de outros gotchas de
  ambiente no F3a/F3b) tocando tabelas de produção por engano.
- **Vale conferir os outros templates do Theme Builder** (`1853` "Edwiser Bridge Shop Page",
  `1340` "Modelo de produtos" — este sem nenhuma condição ativa, possivelmente órfão) pelo
  mesmo sintoma, já que não foram auditados a fundo nesta rodada.
- Backup do valor quebrado do post `101` está só localmente (`/tmp/`, efêmero) — se quiser
  guardar de forma permanente, vale mover pra algum lugar do repo ou do server (fora do git,
  por ser conteúdo de produto).
