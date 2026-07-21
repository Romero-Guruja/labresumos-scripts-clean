# Plugins custom Lab Resumos — Análise e Roadmap de Otimização

> **Objetivo:** entender e reduzir a fragmentação/duplicação dos plugins custom e dos
> snippets WPCode do labresumos.com.br, com ganho de manutenção, robustez e performance —
> **sem quebrar nada**. Vendas acontecem a todo momento e a **integração com o Moodle
> (Edwiser) é crítica**. Todo trabalho é faseado, testável no staging antes de prod.
>
> Data-base: 2026-07-21. Acesso: `ssh wp-napoleon` (usuário `servergurujalab`), WP-CLI com
> `--path=~/domains/labresumos.com.br/public_html`. Ver `TOOLS.md §12` do guruja-backoffice.

---

## 0. Veredito

Há espaço real de otimização, mas o maior ganho **não** é micro-otimizar cada plugin (a
qualidade individual é razoável). É **estrutural**:

1. Os 6 plugins + 23 snippets **reimplementam as mesmas coisas** (CPF, HPOS, logging,
   autologin, telefone/WhatsApp, Telegram), cada um do seu jeito → *bug-drift*.
2. Funcionalidade **crítica mora em snippet WPCode sem versionamento** — em especial o
   **autologin (magic link)**, do qual 2 plugins dependem.
3. Há **hotspots de performance** concretos (cron horário caro, N+1, queries não-sargáveis).

**Estratégia:** extrair uma **biblioteca comum**, **tirar código crítico de snippet**,
**desduplicar** e **corrigir os hotspots** — mantendo os domínios de negócio separados
(NÃO fundir tudo num plugin só). Tudo validado no staging `venture` antes de tocar prod.

---

## 1. Inventário

### 1.1 Plugins custom ativos (autor "Lab Resumos")

| Plugin | Tamanho | Papel | Toca Moodle? | Toca checkout/pagamento? |
|---|---|---|---|---|
| `lab-resumos-parceiros` | 84 arq / ~30.026 linhas | Programa de afiliados (cupons, tracking, comissões multinível, fechamento, ranking, termos) | Não | Sim (cupom/atribuição no carrinho) |
| `lab-resumos-recuperacao-de-vendas` | ~3.426 linhas | Gestão de pedidos "Malsucedido" (failed) + recuperação | Só linka | Lê pedidos |
| `cpf-sender-api` | 1 arq / ~2.171 linhas | Envia CPF p/ endpoint externo (Edwiser + Parceiros); cron + Telegram próprios | **Sim** (`eb_created_user`) | `order_status_completed` |
| `lab-resumos-acessos` | ~1.640 linhas | Cortesia + **matrícula Edwiser/Moodle** + validação CPF | **Sim (crítico)** | Cria pedido cortesia |
| `lab-resumos-guruja-discount` | ~1.166 linhas | Desconto de aluno via API Guruja no checkout | Não | **Sim (fee no carrinho)** |

### 1.2 Snippets WPCode — **23 snippets, ~120 KB de código** (via plugin `insert-headers-and-footers`)

Armazenados no `wp_options` (`wpcode_snippets`), **fora do git**. Agrupados por domínio:

- **Autologin/conta/login:** `#1241` AutoLogin v7 (**38 KB!**), `#1023` reset de senha,
  `#1214` lost password URL, `#1283` banner "Meus Materiais", `#1014` msg thank-you,
  `#995` ver thank-you no admin.
- **Checkout/CPF:** `#1123` validação de CPF, `#937` reordenar CPF, `#1319` sync
  billing_phone↔cellphone.
- **NF-e:** `#1087` forçar emissão em pedido concluído.
- **Storefront/filtros:** `#2774` fix load-more (Essential Addons), `#2382` força
  menu_order, `#2831` ocultar loja Edwiser, `#3039` diferenciador de cursos, `#1422`
  remove msg "adicionado ao carrinho", `#953` Webmania frete.
- **Infra/admin:** `#2755` botão limpar cache (Cloudflare+LiteSpeed+Redis), `#1742`
  contador acessa admin, `#1650` alerta de erro Edwiser via Telegram (11 KB), `#1294`
  upload de .apkg/**SVG**/PDF, `#940` remove Font Awesome duplicado do Edwiser.

> `#1241` e `#1650` já estavam desativados durante o hardening? Não — o **DEBUGZÃO (#3043)**
> foi desativado; o AutoLogin (#1241) segue ATIVO (magic link precisa funcionar).

### 1.3 Tabelas custom (no mesmo DB `servergurujalab_wp_labresumos_com_br`)

`lrp_*` (parceiros: afiliados, comissões, referrals, closings, faq, terms, visits, …),
`lr_recovery_cases` + `lr_recovery_logs` (recuperação), `lra_conflicts` (acessos),
`cpf_sender_logs` (cpf-sender).

---

## 2. Duplicação (a raiz do problema)

| Capacidade | Onde está reimplementada | Observação |
|---|---|---|
| **Validação de CPF (dígito verificador)** | `parceiros` `core/class-lrp-affiliate.php:1477`; `acessos` `class-lra-identity.php:158` (idênticos) | `guruja-discount` só faz length-check (`class-guruja-integration.php:159`) → **aceita CPF inválido**; #1123 tem outra cópia |
| **Normalizar CPF** (`preg_replace('/\D/')`) | ~15+ pontos | — |
| **Autologin / magic link** | wrappers em `acessos/includes/class-lra-onboarding.php:32` e `recuperacao/includes/class-lr-autologin-integration.php:48` | ambos chamam `lr_get_autologin_url` — **definida SÓ no snippet #1241** |
| **Detecção HPOS** | `parceiros` `class-lrp-woocommerce.php:80`; `acessos:101`; `discount:88`; `recuperacao:318` | mesma rotina defensiva reescrita 4× |
| **Logging** | `lrp_log`(wc_logger), `lra_log`(error_log), discount(wc_logger), recuperacao(tabela) | 4 implementações incompatíveis |
| **Telefone→WhatsApp** | só `recuperacao` `class-lr-autologin-integration.php:246` | útil também a parceiros/comercial |
| **Telegram** | `cpf-sender`, `parceiros` (`lab-resumos-parceiros.php:931` webhook hardcoded), snippet #1650 | 3 caminhos |
| **Coordenação desconto Guruja** | `parceiros/…/class-lrp-guruja.php:117` ⇄ `discount/class-guruja-integration.php` | acoplamento bidirecional por `class_exists` + **chave de sessão mágica** `lrg_guruja_descontos` |

---

## 3. Fragilidades estruturais

1. **Magic link mora em snippet.** `lr_get_autologin_url`/`lr_get_payment_link_for_order`
   **só existem no snippet #1241** (confirmado: nenhum plugin as define). `acessos` e
   `recuperacao` dependem delas via `function_exists`. Se o snippet for desativado/editado
   errado/perdido → magic link quebra em 2 plugins. E snippets **não estão no git**.
2. **120 KB de lógica de negócio fora do versionamento** (23 snippets), difícil de auditar,
   sem histórico, sem review.
3. **Acoplamento informal** parceiros⇄discount por chave de sessão e `class_exists` cruzado.

---

## 4. Hotspots de performance

| # | Onde | Problema | Correção |
|---|---|---|---|
| P1 | `parceiros` `class-lrp-stats-calculator.php:154` | Cron **horário** recalcula stats de **todos** os afiliados (~14 queries/afiliado) mesmo sem venda nova | Event-driven (recalcular em `lrp_referral_approved`) ou incremental/throttled |
| P2 | `acessos` `class-lra-identity.php:110` | `find_user_by_cpf` usa `REPLACE(REPLACE(meta_value…))` → **full scan** de `billing_cpf` | Guardar CPF normalizado em meta indexada; buscar por igualdade |
| P3 | `recuperacao` `class-lr-recovery-manager.php:405` | `get_statistics()` carrega todos os casos resolvidos + `wc_get_order()` em loop, sem cache | Cachear em transient (invalidar em resolve/abandon) |
| P4 | `parceiros` `class-lrp-woocommerce.php:509` | `get_monthly_stats` usa `MONTH()/YEAR()` → ignora índice de data | Range `BETWEEN` |
| P5 | `parceiros` `lab-resumos-parceiros.php:682` | `lrp_maybe_upgrade()` + ~660 linhas de migrations inline carregadas em toda request | Mover migrations p/ `LRP_Upgrader` dedicado |

### Código morto / higiene / PII
- `recuperacao` `class-lr-notifications.php:175` `update_menu_badge` inerte (registrado como
  filter, é action, corpo só `break`); `:226` `send_resolved_email` stub vazio.
- `parceiros` `class-lrp-guruja.php:609` `get_applied_discount_source` `@deprecated` ainda presente.
- **PII em log:** `guruja-discount` `class-guruja-integration.php:143` loga email+CPF em claro
  com debug ligado (mitigado após o hardening que desligou WP_DEBUG, mas o código continua).
- `guruja-discount` `test_connection()` considera 2xx–4xx como OK → health check passa com 401.
- Pasta duplicada vazia `lab-resumos-parceiros 3/` no repo — apagar.

---

## 5. Staging (`venture`) — como testar antes de prod

**URL:** `https://labresumos.com.br/venture` · **Arquivos:** `~/domains/labresumos.com.br/public_html/venture/`
· **WP-CLI:** `wp --path=.../venture` · Prefixo de tabelas `wpstg0_` (mesmo banco do prod).

### ⚠️ O staging NÃO é isolado — 4 armadilhas

1. **Mesmo banco de dados** (prefixo diferente). Operações normais dos plugins usam
   `$wpdb->prefix` = `wpstg0_` → ficam no staging. **Mas** qualquer query com prefixo
   hardcoded/`dbo` ou que não respeite o prefixo pode tocar prod. Revisar SQL bruto antes.
2. **E-mails HABILITADOS** no clone (`emailsAllowed: true`) → checkout/cadastro/reset
   disparam e-mail **real**. **Desligar e-mails antes** de testes que enviam.
3. **Edwiser aponta pro Moodle REAL** (`eb_url=https://aluno.labresumos.com.br`, **igual ao
   prod** — confirmado). **Matricular no staging = matricular no Moodle de verdade.** Antes
   de qualquer teste de matrícula/autologin→Moodle: usar **usuário e curso descartáveis de
   teste**, ou apontar `eb_url` do staging pra uma URL dummy / desabilitar o sync.
4. **Pagar.me é o de produção** → checkout real gera cobrança real. Usar pedido de valor
   zero (fluxo cortesia) ou gateway em modo teste antes de testar pagamento.

### Protocolo de teste seguro no staging (antes de cada fase)
```bash
V="wp --path=$HOME/domains/labresumos.com.br/public_html/venture"
# 1) desligar e-mails no staging (evita e-mail real)
$V plugin install disable-emails --activate        # ou filtro wp_mail via mu-plugin no staging
# 2) neutralizar Moodle no staging ANTES de testar matrícula (guardar valor original!)
$V option get eb_connection --format=json > ~/staging-eb-backup.json
#   (apontar eb_url pra uma URL dummy OU criar usuário/curso de teste dedicados)
# 3) rodar o teste da fase; conferir com Query Monitor (ativo no staging) e logs
# 4) restaurar o que mexeu no staging
```
- **O que dá pra testar 100% no staging sem risco externo:** ativação de plugin novo,
  helpers de CPF/HPOS/log (unitário), refactors que não disparam e-mail/Moodle/pagamento,
  telas admin, medição de queries (Query Monitor está ativo lá).
- **O que exige neutralizar integração primeiro:** autologin→Moodle, matrícula, checkout
  com pagamento, envio de e-mail/WhatsApp/Telegram.

---

## 6. Guardrails de produção (vendas ao vivo + Moodle crítico)

**Regra de ouro:** nada vai pra prod sem passar no staging. Toda mudança em prod segue:

1. **Backup antes:** `wp db export` (do prefixo afetado) + cópia dos arquivos que vão mudar.
   (Backups full já existem offline em `~/backups-offline/`.)
2. **Janela de baixo tráfego** para qualquer coisa que toque checkout, pedido, matrícula ou
   pagamento. Evitar horário de pico de vendas.
3. **Deploy de arquivo por `rsync -c` com `--dry-run` primeiro** (ver a diff exata) — nunca
   editar direto no servidor. Código vem do git.
4. **Cutover reversível:** preferir "adicionar o novo, depois desligar o velho" (nunca os
   dois ativos ao mesmo tempo quando redefinem a mesma função → usar guard `function_exists`).
5. **Purga de cache:** `wp litespeed-purge all && wp cache flush`.
6. **Verificação imediata pós-deploy** (checklist §8 de cada fase): site 200, checkout
   carrega, um pedido de teste, **matrícula no Moodle funcionando**, magic link funcionando.
7. **Monitorar** logs/pedidos por algumas horas. **Rollback definido** para cada fase.

**Nunca quebrar o Moodle:** qualquer fase que toque `acessos`/`cpf-sender`/autologin exige
teste explícito de: (a) compra → matrícula automática no Moodle; (b) magic link → SSO no
Moodle. Só vai pra prod com esses dois verdes no staging (com usuário de teste).

---

## 7. Roadmap faseado

Ordem por **valor × segurança**. Cada fase é independente e reversível. F0 é pré-requisito.

### F0 — Rede de segurança & versionamento  · risco: ~zero (só leitura/backup) — ✅ FEITO 2026-07-21
- **Exportar os 23 snippets WPCode para o git** (`docs/wpcode-snippets/` ou plugin
  `lab-resumos-snippets-export`), com id/título/tipo/localização — fim do "código fora do git".
- Snapshot de baseline: versões dos plugins, `wp_options wpcode_snippets`, lista de crons.
- `wp db export` do prefixo prod + confirmar backups offline.
- **Entregável:** snippets versionados + baseline documentado. **Nada muda em prod.**

### F1 — `lab-resumos-core` (biblioteca comum)  · risco: BAIXO (aditivo) — ✅ FEITO 2026-07-21
- Criar mu-plugin `lab-resumos-core` com: `LR_CPF::clean/validate/format` (usar o algoritmo
  forte do `acessos`), `LR_HPOS::enabled`, `LR_Log`, `LR_WhatsApp::format_phone/build_url`,
  `LR_Telegram::alert`, `LR_Autologin` (wrapper).
- **Ainda NÃO troca os plugins** — só disponibiliza. Comportamento inalterado.
- **Staging:** ativar, rodar testes de CPF (bateria de CPFs válidos/ inválidos conhecidos),
  confirmar site 200 e nada regrediu.
- **Prod:** aditivo, seguro. Rollback = remover o mu-plugin.

### F2 — Tirar o autologin do snippet  · risco: MÉDIO (magic link + Moodle) — MUITO cuidado — ✅ FEITO 2026-07-21
- Mover o código do snippet **#1241** para um plugin versionado (`lab-resumos-autologin`)
  ou para o core, **mantendo `lr_get_autologin_url` e `lr_get_payment_link_for_order` com
  assinatura idêntica**, dentro de `if (!function_exists(...))` (evita fatal de redeclare).
- **Staging (com Moodle neutralizado/usuário de teste):** gerar magic link → logar →
  confirmar SSO no Moodle; testar os dois consumidores (`acessos` e `recuperacao`).
- **Cutover prod (ordem importa):** (1) deploy do plugin com guard `function_exists`;
  (2) confirmar que a função agora vem do plugin; (3) **só então desativar o snippet #1241**.
  **Rollback:** reativar o snippet #1241 (fica guardado, não apagado).
- **Verificação:** magic link real + login → Moodle OK.

### F3 — Migrar os snippets "de plugin" para plugins versionados  · risco: BAIXO-MÉDIO (um por vez)
Agrupar por domínio, migrar **um snippet por vez** (staging → prod → desativa o snippet):
- `lab-resumos-account`: #1023, #1214, #1283, #1014, #995 (login/conta/thank-you).
- `lab-resumos-checkout`: #1123, #937, #1319 — **usar `LR_CPF` do core** (elimina o CPF fraco).
- `lab-resumos-storefront`: #2774, #2382, #2831, #3039, #1422, #953. — ✅ FEITO 2026-07-21 (F3a)
- `lab-resumos-admin-tools` (ou core): #2755, #1742, #1650 (→ `LR_Telegram`), #940.
- **NF-e** #1087 → dentro de `acessos` ou plugin pequeno.
- **#1294 (SVG upload):** decidir — sanitizar SVG (risco XSS) ou remover se não usam.
- Cada migração testa isolada. Rollback = reativar o snippet.

### F4 — Performance quick wins  · risco: BAIXO (medível)
- P1 cron de stats event-driven/incremental; P2 índice/coluna normalizada p/ CPF; P3 cache
  em `get_statistics`; P4 range de data; P5 mover migrations inline. Remover código morto.
- **Staging:** medir antes/depois com Query Monitor. **Prod:** deploy + monitorar.

### F5 — Consolidar `guruja-discount` ⇄ `parceiros`  · risco: MÉDIO-ALTO (checkout) — POR ÚLTIMO
- Formalizar o contrato (trocar `class_exists`+sessão mágica por `do_action`/`apply_filters`
  e constante de chave compartilhada) **ou** absorver `guruja-discount` como módulo de `parceiros`.
- **Staging E2E:** carrinho com **aluno Guruja + cupom de afiliado**, validar qual desconto
  prevalece, checkout completo, sem regressão. **Prod:** janela de baixíssimo tráfego +
  monitorar pedidos de perto. Rollback = versão anterior dos dois plugins.

---

## 8. Ordem, dependências e critério de "pronto"

```
F0 (pré-req) → F1 (core) → F2 (autologin) ─┬─→ F3 (snippets→plugins)
                                           └─→ F4 (perf)         → F5 (discount⇄parceiros)
```
- F1 antes de F2/F3 (eles usam o core).
- F2 antes de F3-account (autologin já num lar versionado).
- F4 é paralelizável com F3.
- F5 é o último (maior risco, toca checkout).

**Critério de pronto por fase:** staging verde (incl. Moodle+magic link quando aplicável) →
deploy prod com backup+janela → checklist de verificação verde → 24h monitorado sem regressão.

---

## 9. Apêndice — comandos úteis

```bash
# acesso
ssh wp-napoleon
W="wp --path=$HOME/domains/labresumos.com.br/public_html"          # PROD
V="wp --path=$HOME/domains/labresumos.com.br/public_html/venture"  # STAGING

# deploy de plugin (do git, com dry-run primeiro)
cd <repo>/<plugin> && rsync -rcn --itemize-changes -e ssh . wp-napoleon:'domains/labresumos.com.br/public_html/wp-content/plugins/<plugin>/'
# (revisar a diff; repetir sem o 'n' para aplicar)

# backup antes de mexer
$W db export ~/pre-fase-$(date +%Y%m%d).sql

# purga de cache pós-deploy
$W litespeed-purge all && $W cache flush

# verificação Moodle/checkout (fumaça)
$W eval 'echo function_exists("lr_get_autologin_url")?"autologin OK":"FALTOU";'
curl -s -o /dev/null -w "%{http_code}\n" https://labresumos.com.br/finalizar-compra/
```

> **Snippets WPCode** vivem no `wp_options.wpcode_snippets`. Desativar corretamente exige 2
> passos (marcar draft **e** remover do índice do option) — ver
> `docs/scripts/desativar_paliativo_wpcode_2975.php`.
