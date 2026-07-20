# Changelog — Lab Resumos: Acessos

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
