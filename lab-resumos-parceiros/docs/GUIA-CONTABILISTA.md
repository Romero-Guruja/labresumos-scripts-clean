# Guia do Contabilista
## Programa de Parceiros Lab Resumos

---

## Acesso

**Menu WordPress:** `Lab Resumos → Área do Contador`

Ou diretamente: `seusite.com/wp-admin/admin.php?page=lrp-accountant`

> ⚠️ Você precisa ter a role `lrp_accountant` ou ser administrador.

---

## 1. Visão Geral

Seu papel é:
1. **Validar** Notas Fiscais enviadas pelos parceiros
2. **Aprovar ou Rejeitar** NFs
3. **Confirmar** pagamentos realizados

---

## 2. Dashboard do Contador

### 2.1 Métricas

| Métrica | Descrição |
|---------|-----------|
| NFs Pendentes | Aguardando sua validação |
| Pagamentos Pendentes | NFs aprovadas, aguardando PIX |
| Total a Pagar | Soma dos valores aprovados |

### 2.2 Filas

**NFs Recebidas:** Lista de NFs aguardando validação

**Pagamentos Pendentes:** NFs aprovadas prontas para pagamento

---

## 3. Validar Nota Fiscal

### 3.1 Acessar NF

1. Vá em **NFs Pendentes**
2. Clique na linha do parceiro
3. Visualize os dados

### 3.2 Informações Exibidas

| Campo | Descrição |
|-------|-----------|
| Parceiro | Nome do afiliado |
| Período | Mês/Ano de referência |
| Valor | Total de comissões |
| Número NF | Informado pelo parceiro |
| Arquivo | Link para download do PDF |
| Data Envio | Quando foi enviada |

### 3.3 Verificar NF

Confira:
- ✅ Dados do emitente (CPF/CNPJ do parceiro)
- ✅ Dados do tomador (CNPJ da empresa)
- ✅ Valor corresponde ao fechamento
- ✅ Descrição do serviço
- ✅ NF válida (não cancelada)

### 3.4 Aprovar

1. Clique em **Aprovar NF**
2. Confirme a ação
3. NF vai para fila de pagamento
4. Parceiro recebe email de confirmação

### 3.5 Rejeitar

1. Clique em **Rejeitar NF**
2. Informe o motivo (obrigatório)
   - Exemplos: "Valor divergente", "CNPJ incorreto", "NF ilegível"
3. Confirme a ação
4. Parceiro recebe email com o motivo
5. Parceiro pode enviar nova NF

---

## 4. Confirmar Pagamento

### 4.1 Realizar PIX

1. Acesse **Pagamentos Pendentes**
2. Veja os dados do parceiro:
   - Tipo de chave PIX
   - Chave PIX
   - Nome do titular
   - CPF/CNPJ
   - Valor a pagar

3. Realize o PIX no banco

### 4.2 Registrar Pagamento

1. Após fazer o PIX, clique em **Confirmar Pagamento**
2. Faça upload do comprovante (PDF, JPG ou PNG)
3. Adicione notas (opcional)
4. Confirme

### 4.3 Após Confirmação

- Status muda para **Pago**
- Comissões são marcadas como pagas
- Saldo do parceiro é atualizado
- Parceiro recebe email de confirmação

---

## 5. Fluxo Completo

```
┌─────────────────┐
│   Fechamento    │  Dia 1 do mês (automático)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Aguardando NF   │  Parceiro faz upload
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  NF Recebida    │  Você valida
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
    ▼         ▼
┌────────┐ ┌──────────┐
│Aprovada│ │ Rejeitada│ → Volta para "Aguardando NF"
└───┬────┘ └──────────┘
    │
    ▼
┌─────────────────┐
│ Você faz PIX    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│     Pago        │  Upload do comprovante
└─────────────────┘
```

---

## 6. Emails Automáticos

| Evento | Destinatário | Descrição |
|--------|--------------|-----------|
| NF Recebida | Você | Nova NF para validar |
| NF Aprovada | Parceiro | Confirmação de aprovação |
| NF Rejeitada | Parceiro | Motivo da rejeição |
| Pagamento | Parceiro | Confirmação de pagamento |

---

## 7. Relatórios

### 7.1 Pagamentos do Mês

Lista de todos os pagamentos confirmados no período.

**Exportar:** CSV para conciliação bancária

### 7.2 NFs Processadas

Histórico de NFs aprovadas e rejeitadas.

---

## 8. Boas Práticas

### ✅ Recomendado

- Validar NFs em até 2 dias úteis
- Informar motivo claro na rejeição
- Manter comprovantes organizados
- Conferir dados bancários antes do PIX

### ⚠️ Atenção

- NFs só podem ser aprovadas/rejeitadas uma vez
- Pagamento só pode ser confirmado após aprovação
- Comprovante é obrigatório para confirmar pagamento

---

## 9. Permissões

Sua role `lrp_accountant` permite:

| Ação | Permitido |
|------|-----------|
| Ver NFs pendentes | ✅ |
| Aprovar NF | ✅ |
| Rejeitar NF | ✅ |
| Confirmar pagamento | ✅ |
| Editar parceiros | ❌ |
| Alterar configurações | ❌ |
| Ver relatórios gerais | ❌ |

---

## 10. Checklist Diário

- [ ] Verificar NFs pendentes
- [ ] Validar NFs recebidas
- [ ] Processar pagamentos aprovados
- [ ] Upload de comprovantes

---

## 11. Problemas Comuns

### NF com valor diferente

**Causa:** Parceiro emitiu NF com valor incorreto.

**Solução:** Rejeitar com motivo "Valor divergente. Valor correto: R$ X.XXX,XX"

### Arquivo ilegível

**Causa:** PDF corrompido ou escaneado com baixa qualidade.

**Solução:** Rejeitar com motivo "Arquivo ilegível. Por favor, envie novamente."

### Dados bancários incorretos

**Causa:** Parceiro cadastrou chave PIX errada.

**Solução:** Contatar admin para atualizar dados do parceiro antes do pagamento.

---

## Suporte

Problemas técnicos? Contate o administrador do sistema.

---

*Versão 1.0.0 - Janeiro 2026*

