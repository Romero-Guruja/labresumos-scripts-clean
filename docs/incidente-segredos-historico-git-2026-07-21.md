# Incidente — segredos no histórico do git (achado e corrigido antes do 1º push)

> **Resumo:** antes de fazer o primeiro `git push` deste repo pro remoto
> (`Romero-Guruja/labresumos-scripts-clean`), uma varredura de segurança encontrou 2 segredos
> reais no histórico local. Os dois foram removidos via reescrita de histórico (`git filter-repo`)
> **antes** de qualquer push acontecer — nenhum dos dois chegou a ficar publicamente exposto
> por este repo.

## O que foi encontrado

### 1. Chaves de API da OpenAI e Anthropic (pré-existentes, ~9 meses)
- **Onde:** `ComplementoDocs/Keys.gs`, introduzidas no commit `3eebc2c` (17/10/2025).
- Um commit seguinte no mesmo dia (`707553d`, "security: Remove API keys from Keys.gs...")
  substituiu os valores por placeholders **na versão atual do arquivo** — mas isso não
  remove nada do **histórico**: as chaves reais continuavam 100% visíveis em `3eebc2c` pra
  qualquer um que rodasse `git log -p` ou clonasse o repo.
- **Não é achado desta sessão** — é de um trabalho anterior do Romero, só ficou pendente de
  limpar o histórico (comum: trocar o arquivo "resolve" visualmente, mas não risca do git).

### 2. Token de API da Cloudflare (desta sessão, meu erro)
- **Onde:** `docs/wpcode-snippets/2755-labresumos-purge-all-caches.php`, commit `a9d0bdb`
  (fase F0 desta sessão — exportei os 23 snippets WPCode como espelho fiel, e não me atentei
  que o `#2755` tinha um token cru dentro do código).
- Achado só mais tarde, na fase F3d, quando fui migrar esse snippet pra um plugin de verdade
  — aí percebi e corrigi o **plugin novo** (usa `wp-config.php`, ver
  `docs/plugins-custom-analise-e-roadmap.md` seção F3d), mas o snippet **exportado** com o
  token cru continuou no histórico até esta limpeza.

## O que foi feito

1. Backup completo do repo local antes de qualquer coisa (`labresumos-scripts-BACKUP-pre-filter-repo-<timestamp>`, no mesmo diretório pai).
2. `git stash` das mudanças não commitadas (do próprio Romero, em `CloudRun`/`labresumos_app`) — não fazem parte deste repo/histórico, só precisavam sair do caminho pra rodar o filter-repo com a working tree limpa.
3. `git filter-repo --replace-text` com uma lista de 4 strings a substituir (as 2 chaves, o token e o zone ID da Cloudflare) — reescreve **todos os 24 commits**, troca as strings por `REDACTED-...` em qualquer lugar que apareçam, sem tocar em mais nada.
4. Verificado com `git log --all -p | grep` que as 4 strings originais têm **zero** ocorrências em todo o histórico depois da reescrita.
5. Re-adicionado o remote (o `filter-repo` remove por segurança) e restaurado o `git stash`.
6. `git fsck --full` limpo (só um commit "dangling" da stash antiga, esperado/inofensivo).
7. **Só então** o primeiro `git push -u origin main` foi feito — o remoto nunca teve os
   segredos, em nenhum momento.

## Por que isso foi seguro fazer (reescrita de histórico é destrutiva, mas aqui não)

Reescrever histórico normalmente é arriscado porque quem já tem um clone fica com uma cópia
"velha" que diverge. Aqui o remoto **nunca tinha recebido nenhum push** — não existia
`origin/main`, então não havia nenhuma cópia externa pra conflitar. Era literalmente a
primeira vez que este repo saía da máquina do Romero.

## Pendência real que sobra: confirmar as chaves antigas

A limpeza do histórico impede que **este repo** exponha as chaves. Ela **não** revoga as
chaves em si — se `sk-proj-...` (OpenAI) e `sk-ant-api03-...` (Anthropic) ainda estiverem
ativas nos respectivos provedores, elas continuam sendo um segredo válido que já esteve
exposto localmente por ~9 meses (embora nunca publicamente, já que o repo nunca tinha sido
pushado antes de hoje). **Recomendo confirmar no painel da OpenAI/Anthropic que essas chaves
específicas já foram revogadas/rotacionadas** — se já foram (o commit de outubro sugere que
sim, na época), não há ação nenhuma pendente; se por acaso ainda estiverem ativas, vale
revogar agora por precaução.
