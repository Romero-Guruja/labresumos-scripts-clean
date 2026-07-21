# lab-resumos-parceiros — bugs corrigidos no gerenciador de e-mails de afiliado

> Achado numa varredura geral de bugs no labresumos.com.br (21/07/2026), **sem relação** com
> o roadmap de consolidação de plugins/snippets (`docs/plugins-custom-analise-e-roadmap.md`).
> É outro domínio do mesmo plugin `lab-resumos-parceiros` (programa de afiliados).

## Onde

`lab-resumos-parceiros/includes/emails/class-lrp-email-manager.php`. O método privado
`get_template($template, $vars)` inclui o arquivo do template e usa `extract($vars)` pra
transformar as chaves do array em variáveis dentro dele. Se o template espera um objeto
(`$affiliate`, `$closing`) e o `get_template()` recebe só um valor "achatado"
(`'affiliate_name' => $affiliate->get_display_name()`), a variável objeto **nunca existe**
dentro do template — qualquer `$affiliate->algumMetodo()` lá dentro é uma chamada de método
num `null`, que em PHP 8 é **fatal** (`Uncaught Error`), não um warning.

## Bugs encontrados e corrigidos

### 1. `send_invoice_approved_email()` — era fatal 100% reproduzível
Antes: passava só `'affiliate_name' => $affiliate->get_display_name()` pro template
`templates/invoice-approved.php`, que espera os objetos `$affiliate` **e** `$closing`
(usa `$closing->period_month`, `$closing->period_year`, `$closing->invoice_number`,
`$closing->total_commissions`, `$affiliate->get_display_name()`). Nenhum dos dois objetos
chegava → `Call to a member function get_display_name() on null`.

**Confirmado em produção**, `wp-content/uploads/wc-logs/fatal-errors-*.log`: 1 ocorrência em
2026-07-02 e 1 em 2026-07-06 — ou seja, **toda vez que um admin aprovava a NF de um afiliado**,
o e-mail de confirmação quebrava com fatal. Como o `$wpdb->update()` que marca a NF como
aprovada roda *antes* do `do_action('lrp_invoice_approved', ...)` que dispara o e-mail, a
aprovação em si "vale" no banco — só o e-mail (e possivelmente a resposta HTTP daquela ação
admin) quebrava.

**Fix:** busca `$closing = LRP_Closing::get($closing_id)` (com null-check, loga e retorna se
não achar) e passa `'affiliate' => $affiliate, 'closing' => $closing` pro `get_template()`,
igual ao padrão que já existia — e funcionava — em `send_invoice_received_to_accountant()`.

### 2. `send_invoice_rejected_email()` — mesmo bug, corrigido preventivamente
Mesmo padrão: só passava `affiliate_name`/`reason`/`dashboard_url`; o template
`invoice-rejected.php` também espera `$affiliate` e `$closing`. Não tinha ocorrência no log
ainda (nenhuma NF foi rejeitada no período coberto pelos logs disponíveis), mas ia quebrar
exatamente igual na primeira rejeição. Mesmo fix do item 1.

### 3. `send_payment_completed_email()` — nome de arquivo errado (sem fatal, mas e-mail errado)
Chamava `get_template('payment-completed', ...)`, mas o arquivo real na pasta `templates/` é
`payment-received.php` — não existe `payment-completed.php`. `get_template()` detecta que o
arquivo não existe e cai silenciosamente no `get_basic_template()` (um fallback genérico) —
**sem erro nenhum**, então isso nunca apareceu em log. O afiliado recebia um e-mail de
pagamento sem a formatação/dados do template "Pagamento realizado 🎉💰" pretendido.

**Fix:** troca a string pro nome certo do arquivo (`payment-received`) — e, como esse
template *também* usa `$affiliate`/`$closing` como objetos, adiciona os dois no array (senão
a correção do nome, por si só, teria trocado "e-mail genérico sem erro" por "fatal", só
movendo o problema).

### 4. `templates/monthly-closing.php` — template órfão, **não tocado**
Buscando por `get_template('monthly-closing'` em todo o plugin, a única ocorrência é numa
doc (`docs/Documentação/pt5.md`) — nenhum código realmente chama esse template. É uma
feature documentada e nunca conectada, ou desconectada num refactor sem apagar o arquivo.
Não é bug técnico (não quebra nada por estar lá) — é decisão de negócio se vale implementar
o envio ou só apagar o arquivo morto. Deixado como está.

## Como foi testado

Em staging e depois em produção, interceptando o envio real com o filtro nativo do WordPress
`pre_wp_mail` (que permite inspecionar o e-mail e cancelar o envio de verdade):

```php
add_filter('pre_wp_mail', function($null, $atts) use (&$captured) {
    $captured[] = $atts;
    return true; // curto-circuita o wp_mail() real
}, 10, 2);

$affiliate = new LRP_Affiliate(85);
$em = LRP_Email_Manager::instance();
$em->send_invoice_approved_email($affiliate, 382);   // closing_id real, só leitura
$em->send_invoice_rejected_email($affiliate, 382, 'CNPJ divergente');
$em->send_payment_completed_email($affiliate, 382);
```

Confirmado nos dois ambientes: as 3 funções renderizam o template certo (não o fallback),
com nome do afiliado / período / motivo de rejeição aparecendo corretamente no HTML, sem
fatal. **Nenhum e-mail real foi enviado** durante o teste. `fatal-errors-*.log` sem novas
entradas depois do deploy em prod.

## Commit

`fix(lab-resumos-parceiros): corrige 3 bugs no envio de e-mails de afiliado` — ver histórico
do git em `labresumos-scripts` pro diff completo.
