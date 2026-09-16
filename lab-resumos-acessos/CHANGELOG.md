# Changelog — Lab Resumos: Acessos

## 1.6.0 — 2026-09-16

Três melhorias para o suporte (Mavi) operar sem depender de administrador.

### Adicionado
- **Busca por CPF na tela de Pedidos** (`includes/class-lra-order-search.php`).
  Com HPOS ativo, a busca cobria ID, e-mail, cliente e produtos, mas **não**
  o CPF — justamente o dado que o aluno informa no atendimento. Agora há um
  filtro "CPF" no seletor de busca, e um CPF completo (11 dígitos) também
  funciona com o seletor em "Tudo". Compara apenas dígitos dos dois lados, então
  `123.456.789-00` e `12345678900` encontram o mesmo pedido (há 26 CPFs gravados
  com pontuação em produção). Cobre `_billing_cpf`, `billing_cpf`,
  `_lrg_guruja_cpf` e `_shipping_cpf`.
- **Coluna "Bloqueado" na tela de Usuários** (`includes/class-lra-users.php`),
  com filtro "Bloqueados (N)" e cap `list_users` no papel de suporte
  (somente leitura: editar/criar/excluir/promover continuam negados).
- **Busca ampla na tela "Matrículas"** — por nome, login, e-mail e CPF do aluno,
  além do nome do curso.

### Contexto técnico

**Matrículas.** Matricular/desmatricular já funcionava para o suporte desde a
1.3.0 (validado em produção nesta entrega). O que faltava era **busca**: o
Edwiser monta a lista com a condição fixa `p.post_title LIKE '%termo%'`, ou seja
só acha pelo nome do curso — inútil para quem tem o e-mail/CPF do aluno, e com
10.096 matrículas a tela virava paginação infinita. Existe um snippet WPCode que
resolve isso, mas ele só age em `page=mucp-manage-enrollment` (a tela nativa do
Edwiser, que exige `manage_options`), portanto **nunca** na nossa
`lra-matriculas` — a única que o suporte consegue abrir. A lógica foi replicada
em `LRA_Enrollment::broaden_search()`, escopada na nossa página.

**Bloqueado.** Esse estado não existe no WordPress: não há marcador em
`wp_users`/`wp_usermeta` (`user_status` é 0 para todos os 1.378 usuários) nem
coluna nativa. A fonte da verdade é o Moodle (`mdl_user.suspended`). O token do
web service **não** libera `core_user_get_users` ("Access control exception"),
que seria a via direta para pedir `suspended=1`; o que funciona é
`core_user_get_users_by_field` por e-mail, em lotes de 200 (~0,4s por lote,
~8s para os ~1,1k alunos).

Por isso a varredura **nunca** roda no carregamento da tela: o resultado é
persistido em option com marcador de frescor em transient
(*stale-while-revalidate*). A tela sempre mostra o último status conhecido e
dispara a revalidação em background; há também um cron horário, para o status
não depender de alguém abrir a tela. Se **todos** os lotes falharem, o cache
antigo é preservado em vez de gravar um mapa vazio (que apareceria como "todo
mundo sem conta no Moodle").

### Corrigido
- **`create_roles()` apagava capabilities de outros plugins.** Usava
  `remove_role()` + `add_role()`, então cada bump de `ROLES_VERSION` recriava o
  papel e destruía caps concedidas por terceiros ao mesmo `lra_suporte` — em
  particular `lr_manage_recovery`, do `lab-resumos-recuperacao-de-vendas`, que
  dá acesso à tela de Recuperação de Vendas. Isso **de fato ocorreu** no deploy
  desta versão (a cap foi perdida e restaurada na sequência).

  Agora a sincronização é **estritamente aditiva**: nunca remove capability.
  Motivo adicional para não fazer nem remoção seletiva: o site usa Redis como
  object cache e `WP_Role::add_cap()`/`remove_cap()` regravam o array inteiro de
  capabilities a partir do que foi lido em `wp_user_roles` — se essa leitura vier
  de cache defasado, a regravação apaga o que não estava na cópia lida. Sendo
  aditivo, o pior caso é "uma cap deixou de ser adicionada nesta passada", nunca
  "uma cap de outro plugin foi perdida". `create_roles()` também invalida
  `wp_user_roles`/`alloptions` antes de ler.

  **Consequência aceita:** remover uma cap do papel agora exige ação explícita
  (WP-CLI/migration); não basta tirar de `support_caps()`.

### `ROLES_VERSION`
Bumpado 3 → 4 (adiciona `list_users` ao papel `lra_suporte`).

### Nota de verificação (mesma data)
Na verificação pós-deploy apareceu um bug na normalização de CPF, corrigido em
seguida: os dígitos eram extraídos de **qualquer** termo, então uma busca por
`romero+teste1@labresumos.com.br` reduzia a `"1"` e virava `CPF LIKE '%1%'`,
casando com quase toda a base — a tela Matrículas devolvia **7.386** linhas para
um aluno com 2 matrículas. Agora a comparação por dígitos só entra quando o termo
tem **apenas dígitos e pontuação de CPF** (`/^[0-9.\-\s]+$/`, 3+ dígitos), nos
dois pontos (`LRA_Enrollment::broaden_search()` e `LRA_Order_Search::digits()`).
Revalidado: e-mail → 2, nome → 4, CPF (com e sem pontuação) → 3, curso → 5.922.

Também validado ponta a ponta o item "Bloqueado": suspendendo um aluno real no
Moodle a contagem foi de 11 → 12 e ele apareceu no filtro; revertido em seguida
(estado original restaurado). Cruzamento com `mdl_user.suspended`: **zero falsos
positivos**; o único suspenso ausente não tem conta correspondente no WordPress.

---

## 1.3.0 — 2026-07-20

### Adicionado
- **Página "Matrículas"** (submenu de *Acessos*) para o papel **Suporte Lab**
  matricular/desmatricular alunos no Moodle sem precisar de administrador.
  - Nova capability `lra_manage_enrollment` (papel `lra_suporte` + `administrator`).
  - Reaproveita a tela *Manage Enrollment* do Edwiser Bridge (`Eb_Manage_Enrollment`)
    sob a nossa capability, em vez do `manage_options` que o menu do Edwiser exige.
  - `includes/class-lra-enrollment.php` (`LRA_Enrollment`).

### Contexto técnico
A tela do Edwiser só é gateada por `manage_options` **no registro do menu**. O render
(`out_put`), a matrícula (`handle_new_enrollment`) e a desmatrícula em massa
(`multiple_unenroll_by_rec_id`) validam apenas **nonce** — então basta re-expor a tela
sob a nossa capability. O **único** ponto com `manage_options` hardcoded é o AJAX de
desmatrícula individual (`wp_ajax_wdm_eb_user_manage_unenroll_unenroll_user`); para ele
há uma **ponte** (`LRA_Enrollment::unenroll_ajax_bridge`, prioridade 5) que atende quem
tem `lra_manage_enrollment` replicando exatamente os args do Edwiser (`complete_unenroll`),
com o mesmo nonce `eb_admin_nonce`, e loga a ação. Administradores seguem no handler
original do Edwiser. **O plugin Edwiser Bridge não foi modificado** — atualizações dele
não quebram este recurso (só quebraria se o Edwiser mudasse os nomes das classes/ação AJAX).

### Corrigido
- **Bug latente de ativação**: `register_activation_hook` estava dentro de `init_hooks()`
  (chamado no `plugins_loaded`), então nunca disparava numa requisição de ativação — a
  tabela `wp_lra_conflicts` não seria criada numa instalação nova. Movido para o escopo
  raiz do arquivo principal.

### `ROLES_VERSION`
Bumpado 2 → 3 (força o resync do papel `lra_suporte` no `init` para incluir a nova cap).

---

## 1.2.1 e anteriores
Motor de acesso de cortesia: `LRA_Access::grant()` (identidade por CPF/email →
pedido WooCommerce zerado → Edwiser matricula no Moodle + cpf-sender/DRM), papel
"Suporte Lab", fila de conflitos de identidade, página admin "Acessos".
