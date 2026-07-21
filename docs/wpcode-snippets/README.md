# Snippets WPCode do labresumos.com.br — espelho read-only

Fase F0 do roadmap (`docs/plugins-custom-analise-e-roadmap.md`): versionamento dos 23
snippets do plugin **WPCode** (slug ativo: `insert-headers-and-footers`), que hoje vivem
só no `wp_options.wpcode_snippets` de produção — fora do git, sem histórico.

**Exportado em 2026-07-21** via `wp eval` read-only (`ssh wp-napoleon`), lendo cada item de
cada location (`everywhere` / `admin_only` / `site_wide_header`) do option.

## ⚠️ Isto é um espelho, não a fonte de runtime

Estes arquivos são só para diff/histórico/auditoria. **Editar um arquivo aqui não muda
nada em produção** — o site continua lendo do `wp_options.wpcode_snippets`. A migração de
cada snippet para código versionado que roda de verdade (plugin ou mu-plugin) é a **Fase
F3** do roadmap, feita um por vez, com staging → prod → desativar o snippet original.

Nome do arquivo: `<id>-<slug-do-titulo>.<ext>` (extensão pelo `code_type`: `php`/`js`/`css`).

## Inventário (id → papel → observação)

| ID | Título | Location | Domínio | Observação |
|---|---|---|---|---|
| 3039 | Diferenciador de Cursos no Seletor Woo↔Moodle | everywhere | Storefront/filtros | Cosmético, admin-only |
| 2831 | Ocultar loja Edwiser | everywhere | Storefront/filtros | |
| 2774 | Fix load-more (Essential Addons) | everywhere | Storefront/filtros | |
| 2755 | Botão limpar cache (Cloudflare+LiteSpeed+Redis) | everywhere | Infra/admin | |
| 2382 | Força `menu_order` | everywhere | Storefront/filtros | |
| 1742 | Permitir Contador acessar Admin | everywhere | Infra/admin | |
| 1650 | Edwiser Bridge - Alerta de Erros via Telegram | everywhere | Infra/admin | 11 KB; candidato a `LR_Telegram` no F1 |
| 1422 | Remove msg "adicionado ao carrinho" | everywhere | Storefront/filtros | |
| 1319 | Sync billing_phone ↔ billing_cellphone | everywhere | Checkout/CPF | |
| 1294 | Upload de .apkg (Anki) e **SVG** | everywhere | Infra/admin | Risco XSS (SVG) — decidir em F3 |
| 1283 | Banner "Meus Materiais" | everywhere | Autologin/conta | |
| **1241** | **AutoLogin DEBUG VERSION (v7)** | everywhere | **Autologin/conta** | **38 KB — CRÍTICO.** Define `lr_get_autologin_url`/`lr_get_payment_link_for_order`, usadas por `acessos` e `recuperacao` via `function_exists`. Alvo da Fase F2. Nome "DEBUG VERSION" é legado, revisar/renomear quando migrar. |
| 1214 | Fix Lost Password URL | everywhere | Autologin/conta | |
| 1123 | Validação de CPF no Checkout | everywhere | Checkout/CPF | Outra cópia do algoritmo de CPF — candidato a `LR_CPF` no F3 |
| 1087 | Forçar Emissão de NF-e em pedido concluído | everywhere | NF-e | |
| 1023 | Personalização de Reset de Senha | everywhere | Autologin/conta | |
| 1014 | Msg de acesso na Thank You page | everywhere | Autologin/conta | |
| 1011 | Reenviar emails NATIVOS do Edwiser Bridge | admin_only | Infra/admin | |
| 995 | Ver Thank You Page (admin) | everywhere | Autologin/conta | |
| 953 | Webmania - Modalidade Frete Sem Transporte | everywhere | Storefront/filtros | |
| 940 | Remove Font Awesome duplicado do Edwiser | everywhere | Storefront/filtros | |
| 937 | Reordenar CPF no Checkout | everywhere | Checkout/CPF | |
| 914 | FluidCheckout v4 | site_wide_header | Storefront/filtros | Único `code_type=js` do lote |

Ver seção 1.2 e 7 (roadmap) de `../plugins-custom-analise-e-roadmap.md` para o plano
completo de migração de cada domínio.

## Como re-exportar (auditoria de drift)

```bash
ssh wp-napoleon '
W="wp --path=$HOME/domains/labresumos.com.br/public_html"
$W eval "
\$s = get_option(\"wpcode_snippets\");
\$out = array();
foreach (\$s as \$loc => \$items) {
  foreach (\$items as \$item) {
    \$out[] = array(
      \"id\" => \$item[\"id\"], \"title\" => \$item[\"title\"], \"code\" => \$item[\"code\"],
      \"code_type\" => \$item[\"code_type\"] ?? null, \"location\" => \$loc,
      \"auto_insert\" => \$item[\"auto_insert\"] ?? null, \"priority\" => \$item[\"priority\"] ?? null,
    );
  }
}
echo wp_json_encode(\$out);
"
' > /tmp/wpcode_snippets_export.json
```
Depois comparar com `git diff` nos arquivos deste diretório para ver se algo mudou em prod
sem passar pelo git.
