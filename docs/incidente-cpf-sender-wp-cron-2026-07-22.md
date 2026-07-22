# Incidente — sincronização de CPF travada por ~3h (WP-Cron sem rede de segurança)

> **Resumo:** em 22/07/2026, o aluno `gilmarbrabo@gmail.com` comprou e não conseguiu baixar o
> material (ficava baixando em HTML). Causa: o CPF dele nunca foi gravado em `dbo.lab_user` —
> o plugin `cpf-sender-api` (WordPress, `labresumos.com.br`) agendou o envio normalmente, mas
> a rede de segurança de retry (`cpf_sender_check_pending`, WP-Cron a cada minuto) **não estava
> agendada** havia dias, provavelmente desde a migração do site pro servidor Napoleon em
> 2026-07-20. Ninguém foi avisado — o envio ficou em `pending` por ~3h até resend manual.
> Resolvido na hora via `/cpf-sender-status`; depois disso, robustecemos o fluxo inteiro em 4
> camadas independentes (detalhe na seção "O que foi feito").

## Como o fluxo funciona (contexto pra quem não conhece)

```
WooCommerce (compra) ──> Edwiser Bridge cria usuário/matrícula no Moodle
                              │
                              ▼
                  plugin cpf-sender-api (WordPress)
                  agenda envio do CPF (delay configurável, default 30s)
                              │
                              ▼
                  POST -> Hookdeck -> POST /api/v1/hookdeck/lab-user/document
                  (api-laboratorio-resumos, grava em dbo.lab_user.document)
                              │
                              ▼
        Moodle usa lab_user.document pra liberar o material corretamente
        (sem CPF, o download cai num fallback que serve HTML em vez do arquivo)
```

O plugin tem, historicamente, duas formas de disparar o envio:
- Hook `eb_created_user` (Edwiser Bridge) — caminho principal.
- Hook `woocommerce_order_status_completed` — fallback, se o Edwiser não disparar.

E uma rede de segurança: se o envio não tiver sucesso, o registro fica com
`_cpf_sender_status = pending` e um cron recorrente (`cpf_sender_check_pending`, a cada minuto)
reprocessa com backoff exponencial (1min → 2min → 4min → 8min → 15min, até 15 tentativas).

## O que estava quebrado

Investigado via SSH (`wp-napoleon`, ver `guruja-backoffice/TOOLS.md` §12 pra acesso) e pelos
logs do App Service (`az webapp log download`):

1. `wp_next_scheduled('cpf_sender_check_pending')` retornava **`false`** — a rede de segurança
   simplesmente não existia no WP-Cron. Ela só é criada em `register_activation_hook`, então
   nunca se recupera sozinha se o registro for perdido sem o plugin ser reativado.
2. Não havia crontab real no servidor apontando pro `wp-cron.php` (`crontab -l` vazio) — o site
   dependia 100% do pseudo-cron do WordPress, disparado só por tráfego.
3. `wp_cpf_sender_logs` confirmou: o envio do aluno ficou registrado só como
   "Agendado via WooCommerce fallback" às 09:14:27 — nenhuma tentativa HTTP depois disso,
   enquanto outros alunos do mesmo dia (`lukasamaral22@gmail.com`, `san.ecomp@gmail.com`)
   completaram o ciclo inteiro (agendado → Hookdeck → confirmado) em 1-2 minutos, normalmente.
   Ou seja: **não foi regressão de nenhum deploy** — foi um agendamento que simplesmente não
   disparou, sem rede de segurança pra pegar a falha.

## O que foi feito

Quatro camadas independentes, cada uma cobrindo a falha da anterior:

### 1. Migração de WP-Cron puro pra Action Scheduler
`cpf-sender-api/cpf-sender-api.php` (repo `labresumos-scripts`, commit `8cd4eee`, versão
**2.3.0**). Action Scheduler já vem embutido no WooCommerce — guarda estado numa tabela própria
do banco (`wp_actionscheduler_actions`), não no option fragmentável `cron` do `wp_options`, e tem
UI de admin (**WooCommerce → Status → Ações Agendadas**) pra ver pendências sem precisar de SSH.

Trocado em todos os 5 pontos que usavam `wp_schedule_single_event`/`wp_schedule_event`/
`wp_next_scheduled`/`wp_clear_scheduled_hook`: envio agendado, verificação de escrita, checagem
do Telegram, limpeza de logs diária, e a própria rede de segurança recorrente.

### 2. Auto-recuperação nos dois pontos de entrada de compra
Em `cpf_sender_after_user_created()` (hook `eb_created_user`) e `cpf_sender_woo_fallback()`
(hook de WooCommerce) — os dois lugares que já rodam a cada compra — foi somada uma chamada a
`cpf_sender_ensure_check_pending_scheduled()`, que rearma a rede de segurança se ela não
estiver agendada. **Testado de verdade**: desarmei via `wp eval` e confirmei que uma chamada
equivalente a uma compra rearma sozinha, sem precisar reativar o plugin.

### 3. Gatilho extra: `woocommerce_payment_complete`
Somado ao `woocommerce_order_status_completed` já existente, pro mesmo fallback — mais um ponto
de entrada redundante. Seguro duplicar (a função já é idempotente).

### 4. Heartbeat + backstop cross-system (não confiar só no WordPress pra avisar sobre o WordPress)
- `cpf_sender_process_stale_pending()` grava `update_option('cpf_sender_last_heartbeat', time())`
  a cada execução — só um sinal de vida.
- No repo `api-laboratorio-resumos` (commits `d29e7cb`, `5672175`, `85b7ce6`): novo endpoint
  `GET /api/v1/hookdeck/lab-user/document/health-check` (`routes/hookdeck.py`) que varre
  `dbo.lab_user` direto — **sem depender de nada do WordPress estar de pé** — por compradores
  com `document` vazio há mais de 20 min (e menos de 12h, pra não pegar backlog antigo de
  afiliado/teste). Filtra ruído conhecido (domínios `@labresumos.com.br`/`@guruja.com.br`,
  aliases com `+`) e faz dedup persistente numa tabela nova
  (`cpf_sender_healthcheck_alerted`, modelo `CpfSenderHealthcheckAlerted` em
  `models/database.py`) — só alerta no Telegram sobre email que ainda não tinha aparecido
  antes, pra não repetir o mesmo alerta a cada execução.
- Autenticado por header `X-Cpf-Healthcheck-Key` (chave no Key Vault `labresumos-cofre`,
  secret `CPF-SENDER-HEALTHCHECK-KEY` — **não está em nenhum arquivo do repo**, só no Vault).
- Disparado por um **Google Cloud Scheduler** (job `cpf-sender-healthcheck`, projeto
  `jenkins-394122`, região `us-central1`, a cada 10 minutos) — mesmo padrão já usado por outros
  jobs do ecossistema Guruja (`timeout-sweep`, `backoffice-usage-aggregate-job`, etc.).

### Camada de infraestrutura: cron real no servidor
O SSH do `servergurujalab` é um shell **jailed** — `/var/spool/cron` existe no servidor de
verdade, só que fora do jail, por isso `crontab -l`/`crontab -e` davam "No such file or
directory" mesmo com a feature de Cron Jobs habilitada na conta. Solução: criar pelo painel
DirectAdmin, mas **como admin direto não funciona** (dropdown de domínio fica vazio, sem
"default domain"). O jeito certo é impersonar o usuário: Account Manager → achar
`servergurujalab` → **"Login As"** → aí sim o painel mostra o domínio certo e o Cron Jobs
funciona de verdade.

Cron criado (a cada minuto):
```
curl --silent "https://labresumos.com.br/wp-cron.php?doing_wp_cron=$(date +\%s)" > /dev/null 2>&1
```
O `?doing_wp_cron=<timestamp>` evita que Cloudflare/LiteSpeed Cache sirvam uma resposta cacheada
em vez de disparar o WP-Cron de verdade. O `\%` (escapado) é necessário porque cron interpreta
`%` sem escape como quebra de linha no comando.

**Verificado end-to-end**: 3 leituras de `cpf_sender_last_heartbeat` com ~65s de intervalo entre
si mostraram defasagem de 17-26s em relação ao horário atual — confirma que o cron real está
disparando o WP-Cron de minuto em minuto, independente de tráfego no site.

## Runbook — como checar isso no futuro

| Quero saber | Como |
|---|---|
| A rede de segurança está agendada? | `wp eval 'var_dump(as_next_scheduled_action("cpf_sender_check_pending"));'` (via SSH `wp-napoleon`) — deve retornar um timestamp futuro, não `false`. |
| O que está pendente/travado agora? | WordPress admin → **WooCommerce → Status → Ações Agendadas**, grupo `cpf-sender`. Ou `/cpf-sender-status` no backoffice da API (lista + reenvio manual). |
| O cron real está batendo? | Comparar `wp option get cpf_sender_last_heartbeat` com `date +%s` — defasagem deve ficar sempre abaixo de ~70s. Se crescer sem parar, o cron do DirectAdmin parou (checar Account Manager → servergurujalab → Cron Jobs). |
| O health-check cross-system está rodando? | `gcloud scheduler jobs describe cpf-sender-healthcheck --location=us-central1 --project=jenkins-394122` — ver `lastAttemptTime`. Alertas reais saem no Telegram (`N8N-WEBHOOK-TELEGRAM-ROMERO-URL` / webhook do plugin). |
| Um aluno específico ficou sem CPF | `/cpf-sender-status` no backoffice → busca por email → reenviar manualmente. Endpoint por trás: `POST /api/v1/hookdeck/lab-user/document/resend`. |

## Pendências e limitações conhecidas

- O filtro de ruído do health-check (domínio + alias `+`) é uma heurística — não cobre 100% de
  contas de afiliado/teste com domínio externo (ex.: apareceram `rafael@rafaelkatz.com.br`,
  `amigosrycos@gmail.com` no primeiro teste). O dedup persistente (`cpf_sender_healthcheck_alerted`)
  cobre esse gap na prática: mesmo que passe o filtro uma vez, só alerta uma vez — não vira
  ruído recorrente.
- Se o Key Vault `labresumos-cofre` girar a secret `CPF-SENDER-HEALTHCHECK-KEY`, o job do Cloud
  Scheduler (`cpf-sender-healthcheck`) tem o header com o valor antigo fixo — precisa atualizar
  os dois lados juntos (`gcloud scheduler jobs update http ...`).
- Não confirmamos a causa raiz exata de por que a rede de segurança perdeu o agendamento (a
  migração pro Napoleon em 2026-07-20 é a suspeita mais forte, mas não foi provada). Com Action
  Scheduler + auto-recuperação + cron real, o cenário não deve se repetir de forma silenciosa —
  mas se acontecer de novo, vale investigar se há algo no servidor (backup, cache, segurança)
  zerando o `wp_options['cron']`/tabelas do Action Scheduler periodicamente.
