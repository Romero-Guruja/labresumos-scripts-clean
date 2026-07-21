# Lab Resumos - Recuperação de Vendas

Plugin WordPress para gerenciar pedidos com status "Malsucedido" no WooCommerce, fornecendo um fluxo estruturado de recuperação de vendas.

## Visão Geral

Este plugin automatiza e organiza o processo de recuperação de vendas que falharam por diversos motivos:
- Bloqueio por antifraude
- Recusa do banco
- Excesso de retentativas
- Outros motivos

## Funcionalidades

### Dashboard Administrativo
- Visão geral de todos os casos de recuperação
- Estatísticas de casos por status
- Valor total recuperado
- Filtros por status, responsável, tipo de falha e período

### Detecção Automática
- Detecta automaticamente pedidos que mudam para status "Malsucedido"
- Analisa order notes do Pagar.me para identificar o tipo de falha
- Identifica padrões de bloqueio por antifraude

### Checklist de Recuperação
- Fluxo guiado para recuperar a venda
- Itens incluídos:
  1. Contatar cliente via WhatsApp
  2. Reprocessar pagamento na Pagar.me
  3. Matricular aluno no curso (Edwiser Bridge)
  4. Alterar pedido para Concluído
  5. Verificar/Emitir NF-e

### Integrações
- **Pagar.me**: Links diretos para o dashboard de cobranças
- **Edwiser Bridge**: Links para matrícula manual
- **Sistema de Autologin**: Gera links para o cliente tentar pagar novamente
- **WhatsApp**: Abre conversa com mensagem pré-formatada

### Notificações
- Email para administradores quando pedido falha
- Badge no menu do admin com contador de casos pendentes
- Notificação na admin bar

### Auto-Resolução
- Detecta quando o cliente completa uma nova compra com os mesmos produtos
- Marca o caso anterior como resolvido automaticamente

## Estrutura de Arquivos

```
lr-recuperacao-vendas/
├── lr-recuperacao-vendas.php          # Arquivo principal
├── includes/
│   ├── class-lr-recovery-manager.php  # Lógica de negócio
│   ├── class-lr-admin-dashboard.php   # Dashboard administrativo
│   ├── class-lr-notifications.php     # Sistema de notificações
│   ├── class-lr-order-metabox.php     # Metabox no pedido
│   └── class-lr-autologin-integration.php
├── assets/
│   ├── css/admin-style.css
│   └── js/admin-script.js
├── templates/
│   ├── dashboard.php
│   ├── case-detail.php
│   └── email-notification.php
└── languages/
    └── lr-recuperacao-vendas-pt_BR.po
```

## Requisitos

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

### Dependências Opcionais
- **Pagar.me para WooCommerce**: Para dados de cobrança
- **Edwiser Bridge**: Para matrícula manual
- **Sistema de Autologin (snippet WPCode)**: Para gerar links de recuperação

## Instalação

1. Faça upload da pasta `lr-recuperacao-vendas` para `/wp-content/plugins/`
2. Ative o plugin em "Plugins" no WordPress
3. Acesse "WooCommerce > Recuperação" para ver o dashboard

## Banco de Dados

O plugin cria duas tabelas:

### wp_lr_recovery_cases
Armazena os casos de recuperação.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | BIGINT | ID do caso |
| order_id | BIGINT | ID do pedido |
| status | ENUM | novo, em_atendimento, aguardando_cliente, resolvido, abandonado |
| assigned_to | BIGINT | ID do usuário responsável |
| failure_reason | VARCHAR | Mensagem do motivo da falha |
| failure_type | ENUM | antifraude, banco, retentativas, outro |
| charge_id | VARCHAR | ID da cobrança no Pagar.me |
| checklist | JSON | Estado do checklist |
| created_at | DATETIME | Data de criação |
| updated_at | DATETIME | Data de atualização |
| resolved_at | DATETIME | Data de resolução |

### wp_lr_recovery_logs
Armazena o histórico de ações.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | BIGINT | ID do log |
| case_id | BIGINT | ID do caso |
| user_id | BIGINT | ID do usuário |
| action | VARCHAR | Tipo de ação |
| details | TEXT | Detalhes da ação |
| created_at | DATETIME | Data/hora |

## Hooks Disponíveis

### Actions
```php
// Quando caso é criado
do_action('lr_recovery_case_created', $case_id, $order_id);

// Quando caso é resolvido
do_action('lr_recovery_case_resolved', $case_id, $order_id, $resolution_type);

// Quando item do checklist é completado
do_action('lr_recovery_checklist_completed', $case_id, $item_key, $user_id);
```

### Filters
```php
// Customizar mensagem WhatsApp
$message = apply_filters('lr_recovery_whatsapp_message', $message, $order, $case);

// Customizar itens do checklist
$checklist = apply_filters('lr_recovery_checklist_items', $default_checklist, $order);

// Customizar destinatários do email
$recipients = apply_filters('lr_recovery_email_recipients', $recipients, $order);
```

## Opções do Plugin

| Opção | Padrão | Descrição |
|-------|--------|-----------|
| lr_recovery_whatsapp_template | (template padrão) | Template da mensagem WhatsApp |
| lr_recovery_email_enabled | yes | Habilitar emails de notificação |
| lr_recovery_email_recipients | admin_email | Destinatários dos emails |

## API AJAX

O plugin expõe os seguintes endpoints AJAX:

- `lr_update_checklist` - Atualizar item do checklist
- `lr_generate_autologin` - Gerar link de autologin
- `lr_assign_case` - Atribuir caso a um usuário
- `lr_add_note` - Adicionar observação ao caso
- `lr_update_case_status` - Atualizar status do caso
- `lr_complete_order` - Marcar pedido como concluído

## Segurança

- Todas as requisições AJAX utilizam nonces
- Verificação de capabilities (`manage_woocommerce`)
- Sanitização de todas as entradas
- Escape de todas as saídas
- Prepared statements para queries SQL

## Changelog

### 1.0.0
- Versão inicial
- Dashboard de casos de recuperação
- Detecção automática de falhas
- Sistema de checklist
- Integrações com Pagar.me, Edwiser e WhatsApp
- Sistema de notificações
- Auto-resolução de casos

## Suporte

Para suporte, entre em contato com a equipe do Lab Resumos.

## Licença

GPL v2 ou posterior.
