# Documentação do Endpoint - CPF Sender API

## Visão Geral

Esta documentação descreve a especificação completa do endpoint que receberá os dados de CPF e email enviados pelo plugin WordPress **CPF Sender API**.

**Versão:** 1.0.0  
**Última atualização:** Janeiro 2025  
**Desenvolvido para:** Lab Resumos

---

## 1. Informações Gerais

### 1.1 Propósito

O endpoint recebe dados de clientes (email e CPF) após a conclusão de compras e matrículas em cursos via WooCommerce/Edwiser Bridge.

### 1.2 Fluxo de Dados

```
WordPress (Plugin) → Delay Configurável → Endpoint da API → Processamento → Resposta
```

### 1.3 Métodos HTTP Suportados

O plugin suporta os seguintes métodos HTTP (configurável no admin):
- **POST** (recomendado e padrão)
- **GET**
- **PUT**
- **PATCH**

---

## 2. Especificação da Requisição

### 2.1 URL do Endpoint

A URL do endpoint é configurável no painel administrativo do WordPress. Exemplos:

```
https://api.exemplo.com/cpf
https://api.exemplo.com/v1/users/cpf
https://api.exemplo.com/webhook/cpf-sender
```

### 2.2 Headers Obrigatórios

| Header | Tipo | Descrição | Exemplo |
|--------|------|-----------|---------|
| `Content-Type` | String | Sempre `application/json` | `application/json` |
| `X-API-Key` | String | Chave de autenticação (nome configurável) | `abc123xyz789` |

**Nota:** O nome do header de autenticação é configurável no WordPress. O padrão é `X-API-Key`, mas pode ser alterado para `Authorization`, `Api-Key`, etc.

### 2.3 Payload (Body)

O payload é sempre enviado como JSON no body da requisição.

#### Estrutura do Payload

```json
{
  "email": "usuario@exemplo.com",
  "cpf": "12345678900"
}
```

#### Campos

| Campo | Tipo | Obrigatório | Descrição | Validação |
|-------|------|-------------|-----------|-----------|
| `email` | String | Sim | Email do cliente | Formato de email válido |
| `cpf` | String | Sim | CPF do cliente (apenas números) | Exatamente 11 dígitos numéricos |

#### Exemplo Completo de Requisição

**POST /cpf**

```http
POST /cpf HTTP/1.1
Host: api.exemplo.com
Content-Type: application/json
X-API-Key: abc123xyz789
Content-Length: 45

{
  "email": "joao.silva@exemplo.com",
  "cpf": "12345678900"
}
```

---

## 3. Especificação da Resposta

### 3.1 Códigos de Status HTTP

O endpoint deve retornar os seguintes códigos de status:

| Código | Significado | Descrição |
|--------|-------------|-----------|
| `200` | OK | Requisição processada com sucesso |
| `201` | Created | Recurso criado com sucesso |
| `400` | Bad Request | Dados inválidos (email ou CPF inválido) |
| `401` | Unauthorized | API Key inválida ou ausente |
| `403` | Forbidden | Acesso negado |
| `404` | Not Found | Endpoint não encontrado |
| `409` | Conflict | CPF já cadastrado |
| `422` | Unprocessable Entity | Dados válidos mas não processáveis |
| `500` | Internal Server Error | Erro interno do servidor |
| `503` | Service Unavailable | Serviço temporariamente indisponível |

### 3.2 Formato da Resposta

A resposta deve ser sempre em JSON, independente do status HTTP.

#### Resposta de Sucesso (2xx)

```json
{
  "success": true,
  "message": "CPF recebido com sucesso",
  "data": {
    "user_id": "12345",
    "email": "joao.silva@exemplo.com",
    "cpf": "***.***.***-00",
    "created_at": "2025-01-04T10:30:00Z"
  }
}
```

#### Resposta de Erro (4xx, 5xx)

```json
{
  "success": false,
  "error": {
    "code": "INVALID_CPF",
    "message": "CPF inválido ou já cadastrado",
    "details": "O CPF informado não é válido ou já existe no sistema"
  }
}
```

### 3.3 Estrutura Padrão de Resposta

#### Sucesso

```json
{
  "success": true,
  "message": "string",
  "data": {
    // Dados específicos da resposta
  }
}
```

#### Erro

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Mensagem de erro legível",
    "details": "Detalhes adicionais (opcional)"
  }
}
```

---

## 4. Validações Esperadas

### 4.1 Validação de Email

- Formato válido de email (RFC 5322)
- Não pode estar vazio
- Máximo de 255 caracteres

**Exemplos válidos:**
- `usuario@exemplo.com`
- `joao.silva@exemplo.com.br`
- `teste+tag@exemplo.com`

**Exemplos inválidos:**
- `usuario@` (domínio incompleto)
- `@exemplo.com` (sem nome de usuário)
- `usuario exemplo.com` (sem @)

### 4.2 Validação de CPF

- Deve conter exatamente **11 dígitos numéricos**
- Não pode conter pontos, traços ou espaços
- Deve passar na validação de dígitos verificadores (opcional, mas recomendado)

**Exemplos válidos:**
- `12345678900` (11 dígitos)
- `00000000000` (usado em testes)

**Exemplos inválidos:**
- `1234567890` (10 dígitos - muito curto)
- `123456789001` (12 dígitos - muito longo)
- `123.456.789-00` (contém formatação)

### 4.3 Validação de API Key

- Deve ser válida e não expirada
- Deve ter permissões para acessar o endpoint
- Deve estar presente no header configurado

---

## 5. Exemplos de Implementação

### 5.1 Node.js (Express)

```javascript
const express = require('express');
const app = express();

app.use(express.json());

// Middleware de autenticação
const authenticateApiKey = (req, res, next) => {
  const apiKey = req.headers['x-api-key'];
  
  if (!apiKey || apiKey !== process.env.API_KEY) {
    return res.status(401).json({
      success: false,
      error: {
        code: 'UNAUTHORIZED',
        message: 'API Key inválida ou ausente'
      }
    });
  }
  
  next();
};

// Validação de CPF
const validateCPF = (cpf) => {
  if (!cpf || cpf.length !== 11 || !/^\d+$/.test(cpf)) {
    return false;
  }
  
  // Validação de dígitos verificadores (opcional)
  // Implementar algoritmo de validação de CPF aqui
  
  return true;
};

// Validação de Email
const validateEmail = (email) => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email) && email.length <= 255;
};

// Endpoint principal
app.post('/cpf', authenticateApiKey, async (req, res) => {
  try {
    const { email, cpf } = req.body;
    
    // Validações
    if (!email || !validateEmail(email)) {
      return res.status(400).json({
        success: false,
        error: {
          code: 'INVALID_EMAIL',
          message: 'Email inválido'
        }
      });
    }
    
    if (!cpf || !validateCPF(cpf)) {
      return res.status(400).json({
        success: false,
        error: {
          code: 'INVALID_CPF',
          message: 'CPF inválido. Deve conter exatamente 11 dígitos numéricos'
        }
      });
    }
    
    // Verificar se CPF já existe
    const existingUser = await checkExistingCPF(cpf);
    if (existingUser) {
      return res.status(409).json({
        success: false,
        error: {
          code: 'CPF_ALREADY_EXISTS',
          message: 'CPF já cadastrado no sistema'
        }
      });
    }
    
    // Processar e salvar dados
    const userData = await saveUserData(email, cpf);
    
    // Resposta de sucesso
    res.status(200).json({
      success: true,
      message: 'CPF recebido com sucesso',
      data: {
        user_id: userData.id,
        email: userData.email,
        cpf: maskCPF(userData.cpf), // Mascarar CPF na resposta
        created_at: userData.created_at
      }
    });
    
  } catch (error) {
    console.error('Erro ao processar requisição:', error);
    
    res.status(500).json({
      success: false,
      error: {
        code: 'INTERNAL_ERROR',
        message: 'Erro interno do servidor',
        details: process.env.NODE_ENV === 'development' ? error.message : undefined
      }
    });
  }
});

// Função auxiliar para mascarar CPF
const maskCPF = (cpf) => {
  return `***.${cpf.substring(3, 6)}.***-${cpf.substring(9)}`;
};

// Função para salvar dados (implementar conforme seu banco de dados)
async function saveUserData(email, cpf) {
  // Implementar lógica de salvamento
  // Exemplo com banco de dados:
  // const result = await db.query('INSERT INTO users (email, cpf) VALUES (?, ?)', [email, cpf]);
  // return result;
  
  return {
    id: '12345',
    email,
    cpf,
    created_at: new Date().toISOString()
  };
}

// Função para verificar CPF existente
async function checkExistingCPF(cpf) {
  // Implementar verificação no banco de dados
  // return await db.query('SELECT * FROM users WHERE cpf = ?', [cpf]);
  return null;
}

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Servidor rodando na porta ${PORT}`);
});
```

### 5.2 Python (Flask)

```python
from flask import Flask, request, jsonify
import re
import os
from datetime import datetime

app = Flask(__name__)

# Configuração
API_KEY = os.getenv('API_KEY', 'sua-chave-api-aqui')

def validate_api_key():
    """Middleware de autenticação"""
    api_key = request.headers.get('X-API-Key')
    
    if not api_key or api_key != API_KEY:
        return jsonify({
            'success': False,
            'error': {
                'code': 'UNAUTHORIZED',
                'message': 'API Key inválida ou ausente'
            }
        }), 401
    
    return None

def validate_email(email):
    """Valida formato de email"""
    if not email:
        return False
    
    pattern = r'^[^\s@]+@[^\s@]+\.[^\s@]+$'
    return bool(re.match(pattern, email)) and len(email) <= 255

def validate_cpf(cpf):
    """Valida formato de CPF"""
    if not cpf:
        return False
    
    # Deve ter exatamente 11 dígitos numéricos
    if len(cpf) != 11 or not cpf.isdigit():
        return False
    
    # Validação de dígitos verificadores (opcional)
    # Implementar algoritmo de validação de CPF aqui
    
    return True

def mask_cpf(cpf):
    """Mascara CPF para resposta"""
    return f"***.{cpf[3:6]}.***-{cpf[9:]}"

@app.route('/cpf', methods=['POST'])
def receive_cpf():
    # Verificar autenticação
    auth_error = validate_api_key()
    if auth_error:
        return auth_error
    
    # Obter dados do body
    data = request.get_json()
    
    if not data:
        return jsonify({
            'success': False,
            'error': {
                'code': 'INVALID_REQUEST',
                'message': 'Body da requisição deve ser JSON'
            }
        }), 400
    
    email = data.get('email')
    cpf = data.get('cpf')
    
    # Validar email
    if not email or not validate_email(email):
        return jsonify({
            'success': False,
            'error': {
                'code': 'INVALID_EMAIL',
                'message': 'Email inválido'
            }
        }), 400
    
    # Validar CPF
    if not cpf or not validate_cpf(cpf):
        return jsonify({
            'success': False,
            'error': {
                'code': 'INVALID_CPF',
                'message': 'CPF inválido. Deve conter exatamente 11 dígitos numéricos'
            }
        }), 400
    
    # Verificar se CPF já existe (implementar conforme seu banco)
    # existing_user = check_existing_cpf(cpf)
    # if existing_user:
    #     return jsonify({
    #         'success': False,
    #         'error': {
    #             'code': 'CPF_ALREADY_EXISTS',
    #             'message': 'CPF já cadastrado no sistema'
    #         }
    #     }), 409
    
    try:
        # Salvar dados (implementar conforme seu banco)
        # user_data = save_user_data(email, cpf)
        
        # Resposta de sucesso
        return jsonify({
            'success': True,
            'message': 'CPF recebido com sucesso',
            'data': {
                'user_id': '12345',
                'email': email,
                'cpf': mask_cpf(cpf),
                'created_at': datetime.utcnow().isoformat() + 'Z'
            }
        }), 200
        
    except Exception as e:
        # Log do erro
        print(f"Erro ao processar requisição: {str(e)}")
        
        return jsonify({
            'success': False,
            'error': {
                'code': 'INTERNAL_ERROR',
                'message': 'Erro interno do servidor'
            }
        }), 500

if __name__ == '__main__':
    app.run(debug=True, port=5000)
```

### 5.3 PHP (Laravel)

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CpfSenderController extends Controller
{
    /**
     * Receber dados de CPF do WordPress
     */
    public function receive(Request $request)
    {
        // Validar API Key
        $apiKey = $request->header('X-API-Key');
        
        if (!$apiKey || $apiKey !== config('app.api_key')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'API Key inválida ou ausente'
                ]
            ], 401);
        }
        
        // Validar dados
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'cpf' => 'required|string|size:11|regex:/^\d+$/'
        ], [
            'email.required' => 'Email é obrigatório',
            'email.email' => 'Email inválido',
            'cpf.required' => 'CPF é obrigatório',
            'cpf.size' => 'CPF deve conter exatamente 11 dígitos',
            'cpf.regex' => 'CPF deve conter apenas números'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Dados inválidos',
                    'details' => $validator->errors()->all()
                ]
            ], 400);
        }
        
        $email = $request->input('email');
        $cpf = $request->input('cpf');
        
        // Verificar se CPF já existe
        $existingUser = User::where('cpf', $cpf)->first();
        if ($existingUser) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CPF_ALREADY_EXISTS',
                    'message' => 'CPF já cadastrado no sistema'
                ]
            ], 409);
        }
        
        try {
            // Salvar dados
            $user = User::create([
                'email' => $email,
                'cpf' => $cpf,
            ]);
            
            // Resposta de sucesso
            return response()->json([
                'success' => true,
                'message' => 'CPF recebido com sucesso',
                'data' => [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'cpf' => $this->maskCpf($user->cpf),
                    'created_at' => $user->created_at->toIso8601String()
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Erro ao processar CPF Sender', [
                'error' => $e->getMessage(),
                'email' => $email,
                'cpf' => $cpf
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Erro interno do servidor'
                ]
            ], 500);
        }
    }
    
    /**
     * Mascarar CPF para resposta
     */
    private function maskCpf($cpf)
    {
        return '***.' . substr($cpf, 3, 3) . '.***-' . substr($cpf, -2);
    }
}
```

### 5.4 Python (FastAPI)

```python
from fastapi import FastAPI, Header, HTTPException, status
from pydantic import BaseModel, EmailStr, validator
import re
from typing import Optional

app = FastAPI()

# Configuração
API_KEY = "sua-chave-api-aqui"

# Modelos
class CpfRequest(BaseModel):
    email: EmailStr
    cpf: str
    
    @validator('cpf')
    def validate_cpf(cls, v):
        if not v or len(v) != 11 or not v.isdigit():
            raise ValueError('CPF deve conter exatamente 11 dígitos numéricos')
        return v

class SuccessResponse(BaseModel):
    success: bool = True
    message: str
    data: dict

class ErrorResponse(BaseModel):
    success: bool = False
    error: dict

def validate_api_key(x_api_key: Optional[str] = Header(None)):
    """Validar API Key"""
    if not x_api_key or x_api_key != API_KEY:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail={
                "success": False,
                "error": {
                    "code": "UNAUTHORIZED",
                    "message": "API Key inválida ou ausente"
                }
            }
        )
    return x_api_key

@app.post("/cpf", response_model=SuccessResponse)
async def receive_cpf(
    request: CpfRequest,
    x_api_key: str = Header(..., alias="X-API-Key")
):
    # Validar API Key
    validate_api_key(x_api_key)
    
    # Verificar se CPF já existe (implementar conforme seu banco)
    # existing_user = await check_existing_cpf(request.cpf)
    # if existing_user:
    #     raise HTTPException(
    #         status_code=status.HTTP_409_CONFLICT,
    #         detail={
    #             "success": False,
    #             "error": {
    #                 "code": "CPF_ALREADY_EXISTS",
    #                 "message": "CPF já cadastrado no sistema"
    #             }
    #         }
    #     )
    
    try:
        # Salvar dados (implementar conforme seu banco)
        # user_data = await save_user_data(request.email, request.cpf)
        
        return SuccessResponse(
            success=True,
            message="CPF recebido com sucesso",
            data={
                "user_id": "12345",
                "email": request.email,
                "cpf": mask_cpf(request.cpf),
                "created_at": "2025-01-04T10:30:00Z"
            }
        )
        
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "success": False,
                "error": {
                    "code": "INTERNAL_ERROR",
                    "message": "Erro interno do servidor"
                }
            }
        )

def mask_cpf(cpf: str) -> str:
    """Mascarar CPF"""
    return f"***.{cpf[3:6]}.***-{cpf[9:]}"
```

---

## 6. Tratamento de Erros

### 6.1 Erros Comuns e Respostas

| Situação | Código HTTP | Código de Erro | Mensagem |
|----------|-------------|----------------|----------|
| API Key ausente | 401 | `UNAUTHORIZED` | API Key inválida ou ausente |
| API Key inválida | 401 | `UNAUTHORIZED` | API Key inválida ou ausente |
| Email inválido | 400 | `INVALID_EMAIL` | Email inválido |
| CPF inválido | 400 | `INVALID_CPF` | CPF inválido. Deve conter exatamente 11 dígitos numéricos |
| CPF já existe | 409 | `CPF_ALREADY_EXISTS` | CPF já cadastrado no sistema |
| Body não é JSON | 400 | `INVALID_REQUEST` | Body da requisição deve ser JSON |
| Campos obrigatórios ausentes | 400 | `VALIDATION_ERROR` | Dados inválidos |
| Erro no banco de dados | 500 | `INTERNAL_ERROR` | Erro interno do servidor |
| Serviço indisponível | 503 | `SERVICE_UNAVAILABLE` | Serviço temporariamente indisponível |

### 6.2 Logs e Monitoramento

Recomendações para logging:

1. **Logar todas as requisições recebidas** (sem CPF completo, apenas mascarado)
2. **Logar erros com detalhes** (stack trace em ambiente de desenvolvimento)
3. **Monitorar taxa de sucesso/erro**
4. **Alertar em caso de muitas falhas consecutivas**

Exemplo de log estruturado:

```json
{
  "timestamp": "2025-01-04T10:30:00Z",
  "method": "POST",
  "endpoint": "/cpf",
  "email": "usuario@exemplo.com",
  "cpf_masked": "***.***.***-00",
  "status": "success",
  "response_time_ms": 45
}
```

---

## 7. Segurança

### 7.1 Autenticação

- **Sempre validar a API Key** antes de processar qualquer requisição
- Use HTTPS em produção (obrigatório)
- Considere implementar rate limiting para prevenir abuso
- Rotacione as chaves de API periodicamente

### 7.2 Validação de Dados

- **Nunca confie nos dados recebidos** - sempre valide
- Sanitize todos os inputs antes de processar
- Valide formato de email e CPF rigorosamente
- Implemente validação de dígitos verificadores do CPF

### 7.3 Proteção contra Ataques

- Implemente **rate limiting** (ex: máximo 100 requisições por minuto por IP)
- Use **CORS** adequadamente se necessário
- Implemente **timeout** nas requisições (máximo 30 segundos)
- Considere usar **WAF** (Web Application Firewall) em produção

### 7.4 Privacidade e LGPD

- **Nunca retorne o CPF completo** nas respostas (sempre mascarar)
- Logue apenas CPF mascarado
- Implemente políticas de retenção de dados conforme LGPD
- Criptografe dados sensíveis no banco de dados

---

## 8. Testes

### 8.1 Casos de Teste Recomendados

#### Teste 1: Requisição Válida
```bash
curl -X POST https://api.exemplo.com/cpf \
  -H "Content-Type: application/json" \
  -H "X-API-Key: sua-chave-api" \
  -d '{
    "email": "teste@exemplo.com",
    "cpf": "12345678900"
  }'
```

**Esperado:** Status 200, resposta de sucesso

#### Teste 2: API Key Inválida
```bash
curl -X POST https://api.exemplo.com/cpf \
  -H "Content-Type: application/json" \
  -H "X-API-Key: chave-invalida" \
  -d '{
    "email": "teste@exemplo.com",
    "cpf": "12345678900"
  }'
```

**Esperado:** Status 401, erro de autenticação

#### Teste 3: Email Inválido
```bash
curl -X POST https://api.exemplo.com/cpf \
  -H "Content-Type: application/json" \
  -H "X-API-Key: sua-chave-api" \
  -d '{
    "email": "email-invalido",
    "cpf": "12345678900"
  }'
```

**Esperado:** Status 400, erro de validação

#### Teste 4: CPF Inválido (tamanho incorreto)
```bash
curl -X POST https://api.exemplo.com/cpf \
  -H "Content-Type: application/json" \
  -H "X-API-Key: sua-chave-api" \
  -d '{
    "email": "teste@exemplo.com",
    "cpf": "123456789"
  }'
```

**Esperado:** Status 400, erro de validação

#### Teste 5: CPF com Formatação
```bash
curl -X POST https://api.exemplo.com/cpf \
  -H "Content-Type: application/json" \
  -H "X-API-Key: sua-chave-api" \
  -d '{
    "email": "teste@exemplo.com",
    "cpf": "123.456.789-00"
  }'
```

**Esperado:** Status 400, erro de validação (o plugin envia sem formatação, mas é bom validar)

#### Teste 6: Campos Ausentes
```bash
curl -X POST https://api.exemplo.com/cpf \
  -H "Content-Type: application/json" \
  -H "X-API-Key: sua-chave-api" \
  -d '{
    "email": "teste@exemplo.com"
  }'
```

**Esperado:** Status 400, erro de validação

### 8.2 Testes de Carga

Recomenda-se testar:
- **100 requisições simultâneas**
- **1000 requisições em 1 minuto**
- **Tempo de resposta médio < 500ms**

---

## 9. Integração com o Plugin WordPress

### 9.1 Como o Plugin Envia os Dados

1. **Trigger:** Após matrícula no Moodle via Edwiser Bridge (hook `eb_user_courses_updated`)
2. **Delay:** Aguarda tempo configurável (padrão: 30 segundos)
3. **Busca CPF:** Procura em meta keys do WordPress (`billing_cpf`, `billing_document`, etc.)
4. **Envio:** Faz requisição HTTP para o endpoint configurado
5. **Log:** Registra resultado (sucesso/erro) na tabela de logs

### 9.2 Retry e Tratamento de Erros

- O plugin **não faz retry automático** em caso de erro
- Erros são logados e alertas por email são enviados
- Administrador pode reenviar manualmente pela interface

### 9.3 Timeout

- O plugin usa timeout de **30 segundos** por padrão
- Se o endpoint não responder em 30 segundos, será considerado erro

---

## 10. Checklist de Implementação

- [ ] Endpoint configurado e acessível via HTTPS
- [ ] Autenticação por API Key implementada
- [ ] Validação de email implementada
- [ ] Validação de CPF (11 dígitos numéricos) implementada
- [ ] Validação de dígitos verificadores do CPF (recomendado)
- [ ] Respostas em formato JSON padronizado
- [ ] Códigos de status HTTP corretos
- [ ] CPF nunca retornado completo nas respostas (sempre mascarado)
- [ ] Logs estruturados implementados
- [ ] Tratamento de erros robusto
- [ ] Rate limiting implementado
- [ ] Testes unitários criados
- [ ] Testes de integração realizados
- [ ] Documentação da API atualizada
- [ ] Monitoramento e alertas configurados

---

## 11. Suporte e Contato

Para dúvidas sobre a integração:
- **Documentação do Plugin:** Ver `readme.txt` no plugin WordPress
- **Especificação:** Esta documentação

---

## 12. Changelog

### Versão 1.0.0 (Janeiro 2025)
- Versão inicial da especificação
- Suporte a métodos HTTP: POST, GET, PUT, PATCH
- Autenticação via API Key
- Validação de email e CPF
- Respostas padronizadas em JSON

---

**Fim da Documentação**

