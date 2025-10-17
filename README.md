# LabResumos - Sistema de Geração de Materiais Educacionais

Repositório para scripts de geração de materiais educacionais em Python, com suporte a diagramas SVG e deploy no Google Cloud Run.

## 📋 Visão Geral

O LabResumos é um sistema completo para geração automática de materiais educacionais em PDF, incluindo:

- ✅ Geração de PDFs a partir de Google Docs
- ✅ Suporte a diagramas SVG educacionais
- ✅ Deploy automatizado no Google Cloud Run
- ✅ Integração com Azure Key Vault para segurança
- ✅ Templates HTML responsivos

## 🚀 Funcionalidades

### Sistema Base
- **Geração de PDFs**: Converte documentos do Google Docs para PDF
- **Templates HTML**: Sistema de templates customizáveis
- **Logging Detalhado**: Rastreamento completo das operações
- **Configuração Segura**: Integração com Azure Key Vault

### Extensão com Diagramas SVG 🎨
- **📊 Fluxogramas**: Processos e fluxos de trabalho
- **🧠 Mapas Mentais**: Organização hierárquica de conceitos
- **🏢 Organogramas**: Estruturas organizacionais
- **📅 Timelines**: Cronologias e sequências temporais
- **⚙️ Diagramas de Processo**: Etapas específicas com decisões
- **🏗️ Hierarquias**: Estruturas de classificação
- **⚖️ Comparativos**: Análises lado a lado

## 📁 Estrutura do Projeto

```
labresumos-scripts/
├── scripts/                    # Scripts principais do sistema
├── labresumos_app/            # Aplicação web Flask
├── CloudRun/                  # Configuração para Google Cloud Run
├── outputs/                   # Arquivos de saída gerados
├── utils/                     # Utilitários e helpers
├── config.py                  # Configurações globais
└── requirements.txt           # Dependências Python
```

## 🛠️ Instalação e Configuração

### 1. Dependências
```bash
pip install -r requirements.txt
```

### 2. Configuração do Google Cloud
```bash
# Definir credenciais
export GOOGLE_APPLICATION_CREDENTIALS="./CloudRun/service-account-key.json"
```

### 3. Configuração do Azure Key Vault (opcional)
Configure as variáveis de ambiente para integração com Azure Key Vault.

## 📖 Como Usar

### Geração Local
```bash
# Usar o processador local
cd CloudRun
python local_processor.py
```

### Com Diagramas
```python
from scripts.lab_resumos_generator_com_diagramas import (
    MaterialDataComDiagrama,
    LabResumosPDFGeneratorComDiagramas,
    TipoDiagrama
)

# Criar material com diagramas
material = MaterialDataComDiagrama(
    titulo_material="Seu Título",
    subtitulo="Subtítulo",
    nome_curso="Nome do Curso"
)

# Adicionar diagrama
material.adicionar_diagrama(
    tipo=TipoDiagrama.MAPA_MENTAL,
    dados={
        'titulo': 'Conceito Principal',
        'topicos': {
            'Categoria A': ['Item 1', 'Item 2'],
            'Categoria B': ['Item 3', 'Item 4']
        }
    }
)
```

## 🎯 Casos de Uso por Área

### Direito
- **Fluxogramas**: Processos judiciais, procedimentos administrativos
- **Hierarquias**: Estrutura do judiciário, competências
- **Mapas Mentais**: Princípios constitucionais, direitos fundamentais

### Português
- **Mapas Mentais**: Classes de palavras, figuras de linguagem
- **Fluxogramas**: Análise sintática, processo de concordância
- **Comparativos**: Diferenças entre tempos verbais

### Matemática
- **Fluxogramas**: Algoritmos de resolução
- **Hierarquias**: Classificação de números, operações
- **Processos**: Passos para resolver equações

### História
- **Timelines**: Cronologias históricas
- **Organogramas**: Estruturas de governo
- **Mapas Mentais**: Causas e consequências de eventos

## 🚀 Deploy no Google Cloud Run

### Configuração Automática
```bash
cd CloudRun
./setup_gcp.sh
```

### Deploy Manual
```bash
cd CloudRun
./build-and-deploy.sh
```

## 🔧 Desenvolvimento

### Scripts Disponíveis
- `scripts/lab_resumos_generator.py` - Sistema base
- `scripts/lab_resumos_generator_com_diagramas.py` - Extensão com diagramas
- `scripts/exemplo_diagramas.py` - Exemplos práticos

### Testes
```bash
cd tests
python test_*.py
```

## 📊 Arquivos de Saída

Todos os arquivos gerados são salvos em `outputs/`:
- `*.pdf` - Documentos finais em PDF
- `*.json` - Dados JSON brutos
- `*.html` - Templates HTML gerados
- `*.parsed.json` - Dados processados para debug

## 🔐 Segurança

- **Azure Key Vault**: Gerenciamento seguro de credenciais
- **Logs Detalhados**: Rastreamento completo das operações
- **Tratamento de Erros**: Robustez em cenários de falha
- **Credenciais Google Cloud**: Autenticação segura via service account

## 🆘 Troubleshooting

### Erro: ModuleNotFoundError
```bash
pip install -r requirements.txt
```

### Erro: Credenciais não encontradas
```bash
export GOOGLE_APPLICATION_CREDENTIALS="./CloudRun/service-account-key.json"
```

### Erro: Documento não acessível
Verifique se o documento está público ou se suas credenciais têm acesso a ele.

## 📈 Melhorias Futuras

- [ ] Editor visual de diagramas
- [ ] Mais tipos de diagramas especializados
- [ ] Templates de diagramas por área de conhecimento
- [ ] Animações SVG para apresentações
- [ ] Integração com IA para geração automática

---

**Lab Resumos © 2024** - Sistema de Geração de Materiais Educacionais com Diagramas SVG