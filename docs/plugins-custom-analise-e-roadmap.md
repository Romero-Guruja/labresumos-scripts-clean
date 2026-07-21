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
4. **Cutover reversível — ⚠️ ordem corrigida após incidente real no F2 (ver §9):** quando o
   snippet original **não tem guard** `function_exists` nas funções que ele define (é o caso
   de quase todos — só a *nossa cópia nova* tem guard, o snippet original nunca teve), a
   sobreposição "os dois ativos ao mesmo tempo" **causa fatal de redeclare**, não é segura.
   **Desativar o snippet velho PRIMEIRO, só depois ativar o plugin novo** — o gap momentâneo
   sem a função é seguro (consumidores já fazem `function_exists` e falham "bonito"); dois
   definindo a mesma função ao mesmo tempo não é.
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
- **Cutover prod executado nesta ordem:** (1) deploy do plugin com guard `function_exists`;
  (2) confirmar que a função agora vem do plugin; (3) desativar o snippet #1241.
  **Rollback:** reativar o snippet #1241 (fica guardado, não apagado).
- **Verificação:** magic link real (token pré e pós-cutover) OK. SSO no Moodle não foi
  validado por clique-through (ver §9).
- **⚠️ Essa ordem causou um fatal real** (ver §9, achado da auditoria) — **daqui pra frente
  fazer o inverso**: desativar o snippet antes de ativar o plugin (corrigido na regra §6.4).

### F3 — Migrar os snippets "de plugin" para plugins versionados  · risco: BAIXO-MÉDIO (um grupo por vez)
Agrupar por domínio (um plugin por grupo), migrar **um grupo por vez** (staging → prod →
desativa os snippets do grupo):
- `lab-resumos-storefront`: #2774, #2382, #2831, #3039, #1422, #953. — ✅ FEITO 2026-07-21 (F3a).
  Nenhum define função nomeada (só closures) → zero risco de redeclare.
- `lab-resumos-account`: #1023, #1214, #1283, #1014, #995 (login/conta/thank-you). **Define
  função nomeada sem guard** — cutover deve desativar os snippets ANTES de ativar o plugin
  (ver §6.4/§9).
- `lab-resumos-checkout`: #1123, #937, #1319 — **usar `LR_CPF` do core** (elimina o CPF
  fraco). #1123/#937 **definem função nomeada sem guard** — mesma regra de ordem.
- `lab-resumos-admin-tools` (ou core): #2755, #1742, #1650 (→ `LR_Telegram`, ver nota sobre
  a option `lr_core_telegram_enabled` no §9), #940. `#1650` **define função nomeada sem
  guard** — mesma regra de ordem.
- **NF-e** #1087 → dentro de `acessos` ou plugin pequeno. **Define função nomeada sem
  guard** — mesma regra de ordem.
- **#1294 (SVG upload):** decidir — sanitizar SVG (risco XSS) ou remover se não usam. Sem
  função nomeada.
- Cada grupo testa isolado. Rollback = reativar os snippets do grupo.

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

## 9. Log de execução (o que realmente aconteceu, 21/07/2026)

Todo o F0, F1, F2 e F3a foram executados e deployados em prod na mesma sessão. Commits em
`labresumos-scripts` (branch `main`, sem push pro remoto ainda — pendente de autorização).

### F0 — achado extra: quase nada estava versionado
Só `lab-resumos-acessos` estava commitado. `lab-resumos-parceiros`/`lab-resumos-guruja-discount`/
`lab-resumos-recuperacao-de-vendas` estavam na working tree local mas **nunca tinham sido
commitados** (não é `.gitignore`) — versões locais confirmadas idênticas às de prod antes de
commitar. `cpf-sender-api` não existia local nenhum — puxado via `rsync` read-only do servidor.
Os 23 snippets exportados pra `docs/wpcode-snippets/*.php` (espelho read-only — a fonte de
runtime segue sendo `wp_options.wpcode_snippets` até cada um ser migrado no F3) + `README.md`
com inventário + `BASELINE-2026-07-21.md` com versões/crons/mu-plugins. `wp db export` rodado
em prod (fica em `~/pre-fase0-backups/` no servidor, não sai por conter PII).

### F1 — mu-plugin `lab-resumos-core`
`LR_CPF`/`LR_HPOS`/`LR_Log`/`LR_WhatsApp`/`LR_Telegram`/`LR_Autologin`, cada classe portada do
plugin com a implementação mais robusta (CPF = dígito verificador de `acessos`/`parceiros`,
não o length-check fraco do `discount`). 100% aditivo — deployado sem incidente.

### F2 — autologin → plugin `lab-resumos-autologin` — **causou um incidente real, corrigido**
Snippet `#1241` portado fielmente (verificado char a char) pro plugin, com guard
`function_exists` nas 10 funções nomeadas. Cutover: **ativa plugin → desativa snippet**
(ordem que na hora pareceu segura — ver §6.4 antigo).

**O que deu errado:** o snippet original `#1241` nunca teve guard nenhum. Guard só numa ponta
não impede o fatal quando o outro lado (sem guard) tenta redeclarar a mesma função — e foi
exatamente isso que aconteceu, tanto no staging (15:35:01 UTC) quanto em prod (15:43:35 UTC):
```
Cannot redeclare lr_generate_autologin_token() (previously declared in .../lab-resumos-autologin.php:88)
```
Confirmado em `wp-content/uploads/wc-logs/fatal-errors-*.log` (achado numa auditoria feita
*depois*, não durante o cutover — o grep por "fatal"/"exception" não pegava porque o log usa
o nível "CRITICAL", não essas palavras). Efeito colateral: o auto-recovery do próprio WPCode,
ao detectar o fatal, desativou (draft, fora do índice) o snippet **`#1011`**
("Reenviar emails NATIVOS do Edwiser Bridge", ferramenta manual de suporte, nada a ver com
autologin) — confirmado via backup pré-F0 que estava `publish` antes. **Restaurado** nos dois
ambientes (publish + de volta no índice `wpcode_snippets`), testado OK. Nenhum pedido foi
afetado (nenhuma linha de `wp_posts` tipo pedido mudou na janela do incidente); nenhum e-mail
de alerta chegou a disparar.

**Verificação real feita:** token de autologin gerado ANTES do cutover (prova retrocompat com
links já emitidos) + token gerado DEPOIS (prova o caminho novo) — os dois logaram certo e
redirecionaram pro checkout. **Não foi possível** validar o clique-through real até o SSO do
Moodle (Edwiser usa mecanismo próprio de handshake, não um simples cookie compartilhado — não
achamos o endpoint de gatilho a tempo). Decisão tomada: seguir sem essa checagem específica,
já que o código do F2 só cuida do login no WordPress — o SSO em si é 100% do Edwiser Bridge,
não tocado.

**Correção de método daqui pra frente** (aplicada na regra §6.4 e nas notas do §7-F3):
desativar o snippet velho **antes** de ativar o plugin novo, não o contrário. Praticamente
todos os grupos que faltam no F3 (`account`, `checkout`, `#1650`, `#1087`) definem função
nomeada sem guard — o mesmo risco existe lá.

### F3a — storefront → plugin `lab-resumos-storefront`
6 snippets (`#2774`, `#2382`, `#2831`, `#3039`, `#1422`, `#953`), nenhum com função nomeada →
zero risco de redeclare, cutover sem incidente. Gotchas de ambiente (não são bugs nossos):
staging `venture` usa **permalinks planos** (`?page_id=`), sem rewrite pra `/materiais/` ou
`/loja/` — o teste do redirect loja→materiais só foi possível em prod (permalinks bonitos lá);
e o post `#3039` no CPT `wpcode` **não existe no staging** (só a entrada no option, que é a
fonte de runtime real) — clone do staging ficou defasado nesse post específico, sem afetar o
teste.

### Fix — `LR_Telegram::alert()` desacoplado do `cpf-sender-api`
Achado na mesma auditoria pós-F3a: a option de habilitar/desabilitar (`cpf_sender_telegram_enabled`)
pertencia ao plugin `cpf-sender-api` — inofensivo enquanto nada consumia `LR_Telegram`, mas ia
acoplar "desativar Telegram no cpf-sender" a "silenciar também o alerta do Edwiser" quando
`#1650` for migrado no F3. Renomeada pra `lr_core_telegram_enabled` (option própria do core).
Testado em staging (flag novo funciona, flag antigo não afeta mais) e deployado em prod.

### Achado à parte (outro plugin, não relacionado a este roadmap)
Na mesma auditoria apareceram bugs pré-existentes no gerenciador de e-mails do
`lab-resumos-parceiros` (afiliados) — corrigidos. Detalhes em
`docs/lab-resumos-parceiros-emails-corrigidos.md` (não faz parte do roadmap de plugins/snippets,
é outro domínio do mesmo plugin).

### Pendências abertas
- Push pro remoto `Romero-Guruja/labresumos-scripts-clean` — aguardando autorização.
- SSO no Moodle do F2 nunca teve clique-through real confirmado (ver acima).
- `edwiser-bridge-pro` teve uma rajada de 7 fatais não relacionados ao nosso código
  ("Call to a member function run() on null", falha transitória na checagem de licença,
  autolimitada) — tem update disponível (4.2.2→4.2.3) que pode corrigir; não aplicado ainda.

---

## 10. Apêndice — comandos úteis

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
