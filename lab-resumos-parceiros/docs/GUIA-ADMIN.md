# Guia do Administrador
## Programa de Parceiros Lab Resumos

---

## Acesso

**Menu WordPress:** `Lab Resumos → Parceiros`

---

## 1. Dashboard

Visão geral do programa:

| Métrica | Descrição |
|---------|-----------|
| Parceiros Ativos | Total de afiliados aprovados |
| Vendas do Mês | Quantidade de vendas atribuídas |
| Comissões do Mês | Valor total de comissões geradas |
| Pendente Pagamento | Valor aguardando pagamento |

**Gráficos:**
- Vendas por mês (últimos 12 meses)
- Top 10 parceiros por faturamento

---

## 2. Gerenciar Parceiros

### 2.1 Lista de Parceiros

**Caminho:** `Parceiros → Todos os Parceiros`

**Filtros disponíveis:**
- Status: Pendente, Ativo, Inativo, Rejeitado
- Busca por nome/email

**Ações em massa:**
- Aprovar selecionados
- Desativar selecionados

### 2.2 Aprovar Novo Parceiro

1. Acesse `Parceiros → Pendentes`
2. Clique no nome do parceiro
3. Revise os dados:
   - Notas de aplicação
   - Dados de pagamento
4. Clique em **Aprovar** ou **Rejeitar**

> ⚠️ Ao aprovar, o cupom WooCommerce é criado automaticamente e o parceiro recebe email de boas-vindas.

### 2.3 Link de Cadastro

Envie para potenciais parceiros:

```
https://seusite.com.br/seja-parceiro/
```

O formulário permite cadastro completo sem login prévio (a conta WordPress é criada automaticamente).

### 2.4 Editar Parceiro

**Dados editáveis:**
- Status (ativo/inativo)
- Código do cupom
- Taxas de comissão individuais (sobrescreve global)
- Duração do cookie (dias)
- Regra Guruja
- Permissão de auto-referência
- Notas administrativas

**Dados somente leitura:**
- Estatísticas de vendas
- Histórico de comissões
- Rede de indicados

---

## 3. Comissões

### 3.1 Visualizar Comissões

**Caminho:** `Parceiros → Comissões`

**Filtros:**
- Parceiro
- Status: Pendente, Aprovada, Paga, Cancelada
- Período

### 3.2 Status das Comissões

| Status | Descrição |
|--------|-----------|
| Pendente | Pedido criado, aguardando pagamento |
| Aprovada | Pedido pago/processando |
| Paga | Comissão já foi paga ao parceiro |
| Cancelada | Pedido cancelado/reembolsado |

---

## 4. Pagamentos

### 4.1 Fechamentos Mensais

**Caminho:** `Parceiros → Pagamentos`

**Fluxo:**
```
Fechado → Aguardando NF → NF Recebida → Aprovada → Paga
```

**Ações disponíveis:**
- Ver detalhes do fechamento
- Download da NF enviada
- Download do comprovante de pagamento

### 4.2 Mínimo para Saque

Configurável em `Parceiros → Configurações`

- Padrão: R$ 200,00
- Se não atingir, saldo acumula para próximo mês

---

## 5. Relatórios

### 5.1 Relatório de Vendas

**Caminho:** `Parceiros → Relatórios → Vendas`

**Filtros:**
- Período
- Parceiro
- Tipo de atribuição (cupom/link)

**Exportar:** CSV ou Excel

### 5.2 Relatório de Comissões

**Caminho:** `Parceiros → Relatórios → Comissões`

**Dados:**
- Comissões por parceiro
- Comissões por período
- Comissões pendentes vs pagas

### 5.3 Relatório de Rede

**Caminho:** `Parceiros → Relatórios → Rede`

**Dados:**
- Estrutura multi-nível
- Comissões por nível (L1, L2, L3)

---

## 6. Configurações

### 6.1 Taxas de Comissão (Padrão)

| Tipo | Taxa Padrão |
|------|-------------|
| Via Cupom | 10% |
| Via Link | 5% |
| Nível 2 (sub-afiliado) | 3% |
| Nível 3 | 1% |

### 6.2 Desconto do Cliente

- Padrão: 10% via cupom do parceiro

### 6.3 Cookie

- Duração padrão: 60 dias

### 6.4 Regra Guruja (Conflito de Desconto)

| Opção | Comportamento |
|-------|---------------|
| Maior Desconto | Aplica o desconto maior (Guruja ou Cupom) |
| Prioridade Afiliado | Sempre aplica cupom do afiliado |
| Prioridade Guruja | Sempre aplica desconto Guruja |
| Sem Comissão | Se Guruja, afiliado não ganha comissão |

### 6.5 Dados da Empresa

Para emissão de NF pelos parceiros:
- Razão Social
- CNPJ
- Endereço

### 6.6 Emails

- Email do Administrador (notificações)
- Email do Contador (NFs recebidas)

### 6.7 Aprovação Automática

- **Ativado:** Novos cadastros são aprovados automaticamente
- **Desativado:** Requer aprovação manual (recomendado)

---

## 7. Materiais de Divulgação

### 7.1 Gerenciar Materiais

**Caminho:** `Parceiros → Materiais`

**Tipos:**
- Imagens (banners, posts)
- Textos (copy pronta)
- Vídeos (links)
- Documentos (PDFs)

### 7.2 Adicionar Material

1. Clique em **Adicionar Novo**
2. Preencha título e descrição
3. Selecione tipo e categoria
4. Faça upload ou cole o conteúdo
5. Defina ordem de exibição
6. Salve

---

## 8. FAQ

### 8.1 Gerenciar Perguntas

**Caminho:** `Parceiros → FAQ`

**Categorias padrão:**
- Como Funciona
- Comissões
- Pagamentos
- Rede

### 8.2 Adicionar Pergunta

1. Clique em **Adicionar Nova**
2. Preencha pergunta e resposta (HTML permitido)
3. Selecione categoria
4. Defina ordem
5. Salve

---

## 9. Logs

**Caminho:** `Parceiros → Logs`

Registra todas as ações do sistema:
- Cadastros
- Aprovações/Rejeições
- Vendas atribuídas
- Uploads de NF
- Pagamentos confirmados
- Erros

**Retenção:** 90 dias (LGPD)

---

## 10. Pedidos WooCommerce

### 10.1 Coluna de Parceiro

Na lista de pedidos, aparece coluna "Parceiro" com:
- 🎫 = Venda via cupom
- 🔗 = Venda via link

### 10.2 Detalhes do Pedido

Na página do pedido, seção "Venda de Parceiro" mostra:
- Nome do parceiro
- Tipo de atribuição
- Valor da comissão
- Se cliente é aluno Guruja

---

## Atalhos

| Ação | Caminho |
|------|---------|
| Aprovar parceiros | Parceiros → Pendentes |
| Ver comissões do mês | Parceiros → Dashboard |
| Configurar taxas | Parceiros → Configurações |
| Exportar relatório | Parceiros → Relatórios → Exportar |

---

## Suporte

Em caso de problemas técnicos, verifique:
1. WooCommerce está ativo e atualizado
2. PHP 8.0+ instalado
3. Logs em `Parceiros → Logs`

---

*Versão 1.0.0 - Janeiro 2026*

