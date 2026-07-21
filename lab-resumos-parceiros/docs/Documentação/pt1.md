# Programa de Parceiros Lab Resumos - Parte 1: Visão Geral

## 1. Informações do Projeto

### 1.1 Dados Básicos
- **Nome do Plugin**: Programa de Parceiros Lab Resumos
- **Slug**: `lab-resumos-parceiros`
- **Prefixo de funções/classes**: `LRP_`
- **Prefixo de banco de dados**: `lrp_`
- **Text Domain**: `lab-resumos-parceiros`
- **Versão inicial**: 1.0.0
- **Requisitos**: WordPress 5.8+, WooCommerce 5.0+, PHP 7.4+

### 1.2 Contexto do Ambiente
- **Site**: labresumos.com.br (WordPress/WooCommerce)
- **Checkout**: Fluid Checkout Pro
- **Gateway**: Pagar.me
- **Plugin existente que deve coexistir**: Lab Resumos Guruja Discount
- **Empresa**: SOLUCOES EDUCACIONAIS INTELIGENTES LTDA
- **Email financeiro padrão**: financeiro@labresumos.com.br (editável)

---

## 2. Objetivo do Sistema

Criar um plugin WordPress/WooCommerce completo para gerenciar o **Programa de Parceiros Lab Resumos**, incluindo:

1. **Sistema de afiliados** com cupons exclusivos e links de rastreamento
2. **Estrutura multi-nível** (até 3 níveis de afiliados)
3. **Tracking híbrido**: cupom (100% certeza) + cookie (atribuição provável)
4. **Integração com Guruja** sem conflitos de desconto
5. **Dashboard completo** para afiliados gerenciarem suas vendas
6. **Fechamento mensal automatizado** com gestão de NFs
7. **Área do contador** para validação e pagamentos
8. **FAQ editável** e materiais de divulgação

---

## 3. Princípios de Desenvolvimento

1. **Nunca quebrar o checkout**: Erros devem falhar silenciosamente
2. **Flexibilidade**: Configurações globais com override por afiliado
3. **Rastreabilidade**: Tudo deve ser logado e auditável
4. **Independência**: Funcionar mesmo se Guruja estiver desativado
5. **Performance**: Queries otimizadas, cache quando possível
6. **Comissão sobre valor pago**: SEMPRE calcular sobre o valor efetivamente pago pelo cliente

---

## 4. Regras de Negócio Fundamentais

### 4.1 Atribuição de Vendas

**Prioridade de atribuição:**
1. **Cupom de afiliado usado** → 100% certeza → Comissão cheia (ex: 10%)
2. **Cookie válido sem cupom** → Atribuição provável → Comissão reduzida (ex: 5%)
3. **Nenhum** → Venda orgânica → Sem comissão

### 4.2 Estrutura Multi-Nível

```
VOCÊ (Lab Resumos)
       │
       ├── Afiliado A (Nível 1) ─── 10% comissão direta
       │        │
       │        ├── Afiliado D (Nível 2) ─── A ganha 3% das vendas de D
       │        │        │
       │        │        └── Afiliado F (Nível 3) ─── A ganha 1%, D ganha 3%
       │        │
       │        └── Afiliado E (Nível 2)
       │
       ├── Afiliado B (Nível 1)
       │
       └── Afiliado C (Nível 1)
```

**Comissões padrão (editáveis globalmente e por afiliado):**
- Nível 1 (venda direta via cupom): 10%
- Nível 1 (venda direta via link): 5%
- Nível 2 (sub-afiliado vende): 3%
- Nível 3 (sub-sub-afiliado vende): 1%

### 4.3 Interação com Desconto Guruja

**Regra fundamental:** Desconto Guruja e cupom de afiliado são MUTUAMENTE EXCLUSIVOS.

**Regras configuráveis por afiliado (guruja_rule):**

| Regra | Comportamento |
|-------|---------------|
| `higher_discount` (padrão) | Cliente ganha o MAIOR desconto entre Guruja e cupom |
| `affiliate_priority` | Cupom do afiliado sempre prevalece |
| `guruja_priority` | Desconto Guruja sempre prevalece |
| `no_commission` | Se Guruja aplicar, afiliado não ganha comissão |

**Importante:** A atribuição da venda ao afiliado é INDEPENDENTE da fonte do desconto. Se cliente veio por link do afiliado mas recebeu desconto Guruja (por ser maior), o afiliado ainda ganha comissão sobre o valor pago.

### 4.4 Auto-Referência

- **Padrão**: Afiliado NÃO pode usar próprio cupom para comprar
- **Configurável por afiliado**: Campo `can_self_refer`

### 4.5 Fechamento e Pagamento

- **Mínimo para saque**: R$ 200,00 (editável)
- **Fechamento**: Dia 1 de cada mês (automático)
- **Fluxo**: Fechamento → Afiliado envia NF → Contador valida → Contador paga → Sobe comprovante
- **Acumulação**: Se não atingir mínimo, saldo acumula para próximo mês

---

## 5. Papéis de Usuário

| Papel | Acesso | Responsabilidades |
|-------|--------|-------------------|
| **Admin** | WP Admin completo | Gestão total do programa |
| **Contador** | Área específica | Validar NFs, fazer pagamentos, subir comprovantes |
| **Afiliado** | Dashboard público | Ver vendas, links, cupons, enviar NF |
| **Cliente** | Checkout | Usar cupom/link |

---

## 6. Fluxos Principais

### 6.1 Fluxo de Venda via Cupom

```
1. Afiliado divulga cupom (ex: JOAO10)
2. Cliente aplica cupom no checkout
3. Sistema valida:
   - Cupom é de afiliado ativo?
   - Cliente não é o próprio afiliado?
   - Há conflito com Guruja?
4. Se válido, aplica desconto
5. Pedido finalizado → Cria referral + comissão
6. Pedido pago → Aprova comissão
7. Fechamento mensal → Disponível para saque
```

### 6.2 Fluxo de Venda via Link

```
1. Afiliado divulga link (ex: labresumos.com.br/?ref=joao123)
2. Visitante clica → Cookie setado (60 dias padrão)
3. Visitante compra (mesmo dias depois)
4. Sistema verifica cookie válido
5. Se válido, atribui venda ao afiliado
6. Comissão = taxa de link (menor que cupom)
```

### 6.3 Fluxo de Fechamento Mensal

```
Dia 1 do mês (automático):
1. Sistema fecha período anterior
2. Calcula comissões de cada afiliado
3. Se >= R$ 200: status "Aguardando NF"
4. Se < R$ 200: acumula para próximo mês
5. Email para afiliados elegíveis

Afiliado:
6. Acessa painel → Vê fechamento
7. Emite NF com dados da empresa
8. Faz upload da NF no sistema

Sistema:
9. Notifica contador por email (com NF anexa)

Contador:
10. Acessa área do contador
11. Valida NF (valor, CNPJ, descrição)
12. Se OK: Faz PIX, sobe comprovante
13. Se NOK: Rejeita com motivo

Sistema:
14. Notifica afiliado do resultado
```

### 6.4 Fluxo de Cadastro de Afiliado

```
1. Visitante acessa /seja-parceiro/
2. Se veio por link de sponsor: captura código
3. Preenche formulário (nome, email, CPF, como vai divulgar)
4. Sistema cria usuário WordPress + registro pendente
5. Admin recebe email de novo cadastro
6. Admin aprova/rejeita no painel
7. Se aprovado:
   - Status = active
   - Gera cupom exclusivo (via método `create_affiliate_coupon()`)
   - Gera código de referral
   - Vincula sponsor (se houver)
   - Email de boas-vindas para afiliado
```

### 6.5 Método de Criação de Cupom

Quando um afiliado é aprovado, o sistema deve criar automaticamente um cupom WooCommerce exclusivo:

```php
<?php
/**
 * Cria cupom WooCommerce exclusivo para o afiliado
 * Deve ser chamado ao aprovar um afiliado
 */
public function create_affiliate_coupon($affiliate) {
    // Verifica se cupom já existe
    $coupon_code = $affiliate->get_coupon_code();
    $existing_coupon = new WC_Coupon($coupon_code);
    
    if ($existing_coupon->get_id() > 0) {
        // Cupom já existe, retorna ID
        return $existing_coupon->get_id();
    }
    
    // Cria novo cupom
    $coupon = new WC_Coupon();
    $coupon->set_code($coupon_code);
    $coupon->set_discount_type('percent');
    
    // Obtém desconto padrão das configurações
    $settings = LRP_Settings::instance();
    $discount_amount = $settings->get('default_customer_discount', 20.00);
    $coupon->set_amount($discount_amount);
    
    // Configurações do cupom
    $coupon->set_individual_use(true); // Não pode ser combinado com outros cupons
    $coupon->set_usage_limit(0); // Sem limite de uso
    $coupon->set_usage_limit_per_user(0); // Sem limite por usuário
    $coupon->set_limit_usage_to_x_items(null); // Aplica a todos os itens
    $coupon->set_free_shipping(false);
    $coupon->set_exclude_sale_items(false);
    
    // Meta dados para identificação
    $coupon->add_meta_data('_lrp_affiliate_id', $affiliate->get_id());
    $coupon->add_meta_data('_lrp_is_affiliate_coupon', true);
    
    // Salva cupom
    $coupon_id = $coupon->save();
    
    if (is_wp_error($coupon_id)) {
        return false;
    }
    
    return $coupon_id;
}
```

**Uso:** Este método deve ser chamado na classe `LRP_Admin_Affiliates` ou `LRP_Affiliate` quando o status do afiliado muda de `pending` para `active`.

### 6.6 Validação de Sponsor Circular

Antes de atribuir um sponsor a um afiliado, é necessário validar que não será criado um ciclo circular (ex: A → B → C → A):

```php
<?php
/**
 * Verifica se atribuir um novo sponsor criaria um ciclo circular
 * 
 * @param int $affiliate_id ID do afiliado que receberá o sponsor
 * @param int $new_sponsor_id ID do afiliado que será o sponsor
 * @return bool True se criaria ciclo, False caso contrário
 */
private function would_create_cycle($affiliate_id, $new_sponsor_id) {
    // Não pode ser sponsor de si mesmo
    if ($affiliate_id == $new_sponsor_id) {
        return true;
    }
    
    // Rastreia a cadeia de sponsors para detectar ciclos
    $current = $new_sponsor_id;
    $visited = [];
    
    // Limite de profundidade para evitar loops infinitos
    $max_depth = 10;
    $depth = 0;
    
    while ($current && $depth < $max_depth) {
        // Se encontrou o afiliado na cadeia, há ciclo
        if ($current == $affiliate_id) {
            return true;
        }
        
        // Se já visitou este nó, há ciclo
        if (in_array($current, $visited)) {
            return true;
        }
        
        $visited[] = $current;
        
        // Busca sponsor do afiliado atual
        $sponsor = new LRP_Affiliate($current);
        $current = $sponsor->get_sponsor_id();
        
        $depth++;
    }
    
    return false;
}
```

**Uso:** Este método deve ser chamado antes de atualizar o campo `sponsor_id` de um afiliado, seja no cadastro inicial ou em edição posterior.

### 6.7 Rate Limiting para Cadastro

Para prevenir spam de cadastros, implementar validação de IP:

```php
<?php
/**
 * Obtém IP real do cliente, considerando proxies confiáveis quando configurado
 * 
 * @return string IP do cliente
 */
private function get_client_ip() {
    // Verifica se há proxy confiável configurado
    if (defined('LRP_TRUSTED_PROXY') && LRP_TRUSTED_PROXY) {
        // Verifica headers de proxy apenas se proxy confiável
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
            // Valida formato IP
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim($_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    // Fallback para REMOTE_ADDR
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * Verifica rate limiting por IP no cadastro de afiliados
 * 
 * @return WP_Error|bool Retorna WP_Error se excedeu limite, True caso contrário
 */
private function check_registration_rate_limit() {
    global $wpdb;
    
    $ip_address = $this->get_client_ip();
    
    if (empty($ip_address)) {
        return true; // Se não conseguir IP, permite (não bloqueia)
    }
    
    // Verifica cadastros deste IP nos últimos 30 minutos
    $recent_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) 
         FROM {$wpdb->prefix}lrp_affiliates 
         WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
         AND application_ip = %s",
        $ip_address
    ));
    
    // Limite: 3 cadastros por IP a cada 30 minutos
    $max_attempts = 3;
    
    if ($recent_count >= $max_attempts) {
        return new WP_Error(
            'rate_limit',
            __('Muitas tentativas de cadastro. Por favor, tente novamente em 30 minutos.', 'lab-resumos-parceiros')
        );
    }
    
    return true;
}

/**
 * Verifica rate limiting para endpoints AJAX
 * 
 * @param string $action Nome da ação (ex: 'check_coupon', 'register')
 * @param int $limit Limite de tentativas
 * @param int $period Período em segundos
 * @return WP_Error|bool Retorna WP_Error se excedeu limite, True caso contrário
 */
private function check_ajax_rate_limit($action, $limit = 10, $period = 60) {
    $transient_key = 'lrp_rate_limit_' . $action . '_' . $this->get_client_ip();
    $attempts = get_transient($transient_key);
    
    if ($attempts === false) {
        set_transient($transient_key, 1, $period);
        return true;
    }
    
    if ($attempts >= $limit) {
        return new WP_Error('rate_limit', 'Muitas tentativas. Tente novamente em alguns minutos.');
    }
    
    set_transient($transient_key, $attempts + 1, $period);
    return true;
}
```

**Nota sobre IPs e LGPD:** Para rate limiting, considerar usar hash do IP ao invés de IP completo para reduzir exposição de dados pessoais. IPs são dados pessoais sob LGPD e devem ter política de retenção definida (recomendado: 90 dias).

**Nota:** É necessário adicionar o campo `application_ip VARCHAR(45)` na tabela `lrp_affiliates` para armazenar o IP do cadastro.

### 6.8 Cache de Estatísticas

Para melhorar performance em estatísticas pesadas, usar transients do WordPress:

```php
<?php
/**
 * Obtém estatísticas do afiliado com cache
 * 
 * @param int $affiliate_id ID do afiliado
 * @return array Estatísticas do afiliado
 */
public function get_affiliate_stats($affiliate_id) {
    // Tenta obter do cache primeiro
    $cache_key = 'lrp_affiliate_stats_' . $affiliate_id;
    $stats = get_transient($cache_key);
    
    if (false !== $stats) {
        return $stats;
    }
    
    // Se não está em cache, calcula
    $stats = $this->calculate_stats($affiliate_id);
    
    // Salva no cache por 1 hora
    set_transient($cache_key, $stats, HOUR_IN_SECONDS);
    
    return $stats;
}

/**
 * Limpa cache de estatísticas quando necessário
 */
public function clear_stats_cache($affiliate_id) {
    delete_transient('lrp_affiliate_stats_' . $affiliate_id);
}
```

**Uso:** Chamar `clear_stats_cache()` quando houver mudanças que afetem as estatísticas (nova venda, aprovação de comissão, etc).

### 6.9 Validação de Chaves PIX

Validar chaves PIX de acordo com o tipo:

```php
<?php
/**
 * Valida CPF com dígitos verificadores
 * 
 * @param string $cpf CPF sem formatação (11 dígitos)
 * @return bool True se válido, False caso contrário
 */
private function validate_cpf($cpf) {
    // Rejeita CPFs com todos os dígitos iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Valida primeiro dígito verificador
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += intval($cpf[$i]) * (10 - $i);
    }
    $remainder = $sum % 11;
    $digit1 = ($remainder < 2) ? 0 : 11 - $remainder;
    
    if (intval($cpf[9]) !== $digit1) {
        return false;
    }
    
    // Valida segundo dígito verificador
    $sum = 0;
    for ($i = 0; $i < 10; $i++) {
        $sum += intval($cpf[$i]) * (11 - $i);
    }
    $remainder = $sum % 11;
    $digit2 = ($remainder < 2) ? 0 : 11 - $remainder;
    
    return intval($cpf[10]) === $digit2;
}

/**
 * Valida CNPJ com dígitos verificadores
 * 
 * @param string $cnpj CNPJ sem formatação (14 dígitos)
 * @return bool True se válido, False caso contrário
 */
private function validate_cnpj($cnpj) {
    // Rejeita CNPJs com todos os dígitos iguais
    if (preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }
    
    // Valida primeiro dígito verificador
    $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += intval($cnpj[$i]) * $weights1[$i];
    }
    $remainder = $sum % 11;
    $digit1 = ($remainder < 2) ? 0 : 11 - $remainder;
    
    if (intval($cnpj[12]) !== $digit1) {
        return false;
    }
    
    // Valida segundo dígito verificador
    $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $sum = 0;
    for ($i = 0; $i < 13; $i++) {
        $sum += intval($cnpj[$i]) * $weights2[$i];
    }
    $remainder = $sum % 11;
    $digit2 = ($remainder < 2) ? 0 : 11 - $remainder;
    
    return intval($cnpj[13]) === $digit2;
}

/**
 * Valida chave PIX de acordo com o tipo
 * 
 * @param string $type Tipo da chave: cpf, cnpj, email, phone, random
 * @param string $key Chave PIX a validar
 * @return bool True se válida, False caso contrário
 */
private function validate_pix_key($type, $key) {
    if (empty($key)) {
        return false;
    }
    
    switch ($type) {
        case 'cpf':
            // Remove formatação e valida 11 dígitos
            $clean = preg_replace('/\D/', '', $key);
            if (!preg_match('/^\d{11}$/', $clean)) {
                return false;
            }
            // Valida dígitos verificadores
            return $this->validate_cpf($clean);
            
        case 'cnpj':
            // Remove formatação e valida 14 dígitos
            $clean = preg_replace('/\D/', '', $key);
            if (!preg_match('/^\d{14}$/', $clean)) {
                return false;
            }
            // Valida dígitos verificadores
            return $this->validate_cnpj($clean);
            
        case 'email':
            // Valida formato de email
            return filter_var($key, FILTER_VALIDATE_EMAIL) !== false;
            
        case 'phone':
            // Remove formatação e valida telefone brasileiro (10 ou 11 dígitos)
            // Aceita com ou sem código do país
            $clean = preg_replace('/\D/', '', $key);
            // Remove código do país se presente
            if (strpos($clean, '55') === 0 && strlen($clean) > 11) {
                $clean = substr($clean, 2);
            }
            return preg_match('/^\d{10,11}$/', $clean);
            
        case 'random':
            // Chave aleatória PIX do BACEN: 32 caracteres alfanuméricos (sem hífens)
            return preg_match('/^[a-zA-Z0-9]{32}$/', $key);
            
        default:
            return false;
    }
}
```

**Uso:** Chamar este método antes de salvar dados bancários do afiliado no formulário de perfil.

---

## 7. Sistema de Emails

### 7.1 Emails para Afiliado
1. ✅ Boas-vindas (aprovação do cadastro)
2. ✅ Nova venda realizada
3. ✅ Fechamento mensal disponível
4. ✅ NF aprovada
5. ✅ NF rejeitada (com motivo)
6. ✅ Pagamento realizado
7. ✅ Novo sub-afiliado cadastrado na rede
8. ✅ Sub-afiliado fez uma venda

### 7.2 Emails para Admin
9. ✅ Novo afiliado aguardando aprovação
10. ✅ Resumo semanal/mensal de vendas

### 7.3 Emails para Contador
11. ✅ Afiliado enviou NF (com anexo)
12. ✅ Resumo de NFs pendentes (semanal)

---

## 8. Configurações Globais

Todas editáveis no admin:

```php
[
    // Ativação
    'enabled' => true,
    
    // Comissões padrão (%)
    'default_commission_coupon' => 10.00,
    'default_commission_link' => 5.00,
    'default_commission_l2' => 3.00,
    'default_commission_l3' => 1.00,
    
    // Cookie
    'default_cookie_days' => 60,
    
    // Desconto para cliente
    'default_customer_discount' => 20.00,
    
    // Financeiro
    'minimum_payout' => 200.00,
    'closing_day' => 1,
    
    // Regra Guruja padrão
    'default_guruja_rule' => 'higher_discount',
    
    // Dados da empresa (para NF)
    'company_name' => 'SOLUCOES EDUCACIONAIS INTELIGENTES LTDA',
    'company_cnpj' => '',
    'company_address' => '',
    
    // Emails
    'accountant_email' => 'financeiro@labresumos.com.br',
    
    // Aprovação
    'auto_approve' => false,
]
```

**Importante:** Todas essas configurações podem ser sobrescritas individualmente por afiliado.