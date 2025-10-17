# Lab Resumos Scripts - Especificações do Projeto

## Visão Geral
Sistema automatizado para geração de materiais didáticos padronizados usando a API do APITemplate.io, integrado com Azure Key Vault para gerenciamento seguro de configurações. Inclui também a aplicação `labresumos_app` para processamento direto de documentos do Google Docs via API oficial.

## Arquitetura

### Componentes Principais
- **projeto/app.py**: Executável simples que baixa HTML do Google Docs público e gera PDF local com WeasyPrint
- **projeto/parser_module.py (SimpleHTMLParser)**: Parser mínimo do HTML exportado; agora decodifica entidades HTML (emojis, acentos) com `html.unescape`
- **projeto/content_enricher.py (ContentEnricher)**: Enriquecimento de contexto e detecção de sintaxes; agora detecta `{{diagrama:iptu_principios}}` e incorpora SVG em base64
- **projeto/pdf_converter.py (SimplePDFConverter)**: Renderiza `templates/base_template.html` com capa padrão opcional via `config.yaml` e QR Code
- **projeto/templates/base_template.html**: Template base; agora suporta `cover_html`, bloco `diagramas` com SVG embutido e renderização inline de blocos customizados (`{{grafico:barras}}...{{/grafico}}`) via filtro `render_custom_blocks`
- **scripts/geracao_local.py**: Gerador unificado com diagramas SVG e HTML inline
- **PDF_Generator/**: Versão premium com template rico e conversor dedicado

### Frameworks e Tecnologias
- **Python 3.11+**
- **WeasyPrint**: Geração de PDF a partir de HTML/CSS
- **Jinja2**: Templates HTML
  - Filtro customizado `render_custom_blocks` registrado no conversor para processar e renderizar gráficos inline
- **CairoSVG (opcional)**: Suporte a SVG quando necessário
- **asyncio**: Programação assíncrona
- **Azure Key Vault** (opcional): Segredos para integrações

## Princípios Basilares

### 1. Segurança
- API Keys armazenadas no Azure Key Vault
- Nenhuma credencial hardcoded no código
- Logs detalhados para auditoria

### 2. Modularidade
- Separação clara de responsabilidades
- Classes e funções bem definidas
- Facilidade para extensão e manutenção

### 3. Robustez
- Tratamento completo de erros
- Timeouts configurados
- Métodos alternativos de fallback

### 4. Escalabilidade
- Processamento assíncrono
- Geração em lote
- Design para alta concorrência

## Estrutura de Diretórios
```
labresumos-scripts/
├── config.py                    # Configurações e Azure Key Vault
├── scripts/
│   ├── __init__.py
│   ├── lab_resumos_generator.py  # Gerador principal
│   └── example_test.py          # Exemplos de uso
├── outputs/                     # PDFs gerados
├── tests/                       # Testes unitários
├── utils/                       # Utilitários auxiliares
├── requirements.txt             # Dependências
└── README.md                    # Documentação básica
```

## API Template.io - Integração

### Endpoints Suportados
1. **Endpoint Principal**: `/v2/create`
   - Template ID como query parameter
   - Dados enviados diretamente no payload
   
2. **Endpoint Alternativo**: `/v2/create-pdf`
   - Template ID como query parameter
   - Usado como fallback

### Correções Implementadas (v2)
- ✅ URL corrigida para usar query parameters
- ✅ Estrutura do payload ajustada para API v2
- ✅ Tratamento de timeout e erros melhorado
- ✅ Método alternativo implementado
 - ✅ Renderização inline de gráficos de barras a partir de marcação no texto do Docs

## Tipos de Materiais
- **Resumo Teórico**: Conteúdo estruturado com conceitos
- **Lista de Exercícios**: Exercícios práticos
- **Apostila Completa**: Material completo do curso
- **Flashcards**: Cards para memorização

## Configurações

### Azure Key Vault
- **Secret Name**: `template_io_api_key`
- **URL Base**: `https://rest.apitemplate.io/v2`
- **Template IDs**: Configurados na classe Config

### PDF (projeto/config.yaml)
- `pdf.margins`: margens A4
- `pdf.cover.enabled`: habilita capa padrão
- `pdf.cover.path`: caminho da imagem (ex.: `assets/capa_padrao.png`)
- `pdf.components`: flags para gráficos/diagramas

## Funcionalidades

### Geração Individual
```python
resultado = await generator.gerar_material(
    tipo=TipoMaterial.RESUMO,
    dados=material_data,
    output_path="output.pdf"
)
```

### Nome do arquivo de saída
- Todos os geradores foram ajustados para salvar como `outputs/pdf_gerado_YYYYMMDD_HHMMSS.pdf`.

### Capa padrão
- A capa é controlada por `projeto/config.yaml` (`pdf.cover.enabled` e `pdf.cover.path`).
- O template injeta `cover_html` no início do documento.
 - As seções agora processam blocos `{{grafico:barras}}` e renderizam com `components/graficos/grafico_barras.html`.

### Geração em Lote
```python
materiais = [(tipo, dados, path), ...]
resultados = await generator.gerar_lote(materiais)
```

### Método Alternativo
```python
resultado = await generator.gerar_material_alternativo(
    tipo=TipoMaterial.RESUMO,
    dados=material_data,
    output_path="output.pdf"
)
```

## Estrutura de Dados

### MaterialData
- Informações gerais (título, curso, aluno)
- Conteúdo principal (introdução, seções)
- Conceitos estruturados
- Tabelas opcionais
- Metadados de geração

## Tratamento de Erros
- Timeout configurado para 60 segundos
- Retry automático com método alternativo
- Logs detalhados para debug
- Códigos de status HTTP tratados
- Exceções capturadas e reportadas

## Extensão com Diagramas SVG

### Nova Funcionalidade (v1.4 - SVG Standalone)
- **GeradorDiagramaSVG**: Classe principal para criação de diagramas SVG puros
- **MaterialDataComDiagrama**: Extensão do MaterialData com suporte a diagramas
- **LabResumosPDFGeneratorComDiagramas**: Gerador estendido com diagramas
- **TipoDiagramaSVG**: Enum com tipos específicos de diagramas SVG
- **DiagramaSVG**: Estrutura de dados para diagramas SVG completos

### Tipos de Diagramas SVG Suportados
- **IPTU_PRINCIPIOS**: Diagrama específico dos princípios do IPTU (baseado no exemplo fornecido)
- **FLUXOGRAMA_SIMPLES**: Fluxogramas de processo lineares
- **MAPA_CONCEITUAL**: Mapas mentais com conceito central e ramificações
- **ORGANOGRAMA**: Estruturas hierárquicas organizacionais
- **TIMELINE**: Cronologias e sequências temporais

### Características dos Diagramas SVG
- **Formato SVG Puro**: Escalável em qualquer resolução sem perda de qualidade
- **HTML Standalone**: Arquivos HTML completos e independentes
- **Identidade Visual**: Cores precisas (#e74c3c para vermelho, #fff5f5 para fundos)
- **Tipografia Controlada**: Fontes e tamanhos específicos
- **Elementos Interativos**: Linhas pontilhadas, conexões e marcadores
- **Exportação Múltipla**: HTML completo, SVG puro, ou embedding

### Funcionalidades Principais

#### 1. Geração HTML Standalone
```python
# Gerar diagrama IPTU como HTML completo
gerador = GeradorDiagramaSVG()
diagrama = gerador.gerar_diagrama(TipoDiagramaSVG.IPTU_PRINCIPIOS, {})
sucesso = gerador.salvar_html(diagrama, "diagrama.html")
```

#### 2. Integração com Gerador Principal
```python
# Usar o gerador integrado
generator = LabResumosPDFGeneratorComDiagramas()

# Gerar diagrama SVG standalone
sucesso = await generator.gerar_diagrama_svg_html(
    TipoDiagramaSVG.IPTU_PRINCIPIOS, 
    {}, 
    "outputs/iptu.html"
)

# Gerar múltiplos diagramas em lote
diagramas = [
    (TipoDiagramaSVG.IPTU_PRINCIPIOS, {}, "iptu.html"),
    (TipoDiagramaSVG.FLUXOGRAMA_SIMPLES, dados_fluxo, "fluxo.html")
]
resultados = await generator.gerar_lote_diagramas_svg(diagramas)
```

#### 3. SVG Puro para Embedding
```python
# Obter apenas o conteúdo SVG
svg_content = generator.obter_svg_puro(TipoDiagramaSVG.IPTU_PRINCIPIOS, {})
# Usar em outros documentos, PDFs, etc.
```

### Estrutura de Dados SVG

#### ElementoSVG
- **id**: Identificador único
- **texto**: Conteúdo textual
- **posição**: Coordenadas (x, y)
- **dimensões**: Largura e altura
- **cores**: Fundo, borda e texto
- **tipo**: box, circle, diamond, texto

#### ConexaoSVG
- **origem/destino**: IDs dos elementos conectados
- **tipo**: linha, seta, pontilhada
- **pontos**: Lista de coordenadas para path
- **estilo**: Cores e larguras

### Templates Disponíveis

#### 1. Diagrama IPTU (Reprodução Exata)
Baseado no exemplo fornecido pelo usuário:
- Texto principal: "O IPTU obedece aos princípios da:"
- Princípios: Legalidade, Anterioridade, Noventena
- Exceção destacada: Base de Cálculo
- Observação das bancas: "alíquota"
- Conexões específicas com pontos de marcação

#### 2. Fluxograma Simples
- Etapas sequenciais verticais
- Cores personalizáveis por etapa
- Conexões automáticas com setas
- Suporte a diferentes tipos de caixas

#### 3. Mapa Conceitual
- Conceito central circular
- Conceitos periféricos em círculo
- Conexões radiais automáticas
- Distribuição geométrica equilibrada

#### 4. Organograma
- Estrutura hierárquica recursiva
- Conexões verticais e horizontais
- Distribuição automática de posições
- Suporte a múltiplos níveis

#### 5. Timeline
- Linha temporal horizontal
- Marcos circulares na linha
- Eventos alternados acima/abaixo
- Conexões verticais para eventos

### Exemplos de Uso Avançados

#### Diagrama IPTU Personalizado
```python
# Função de conveniência
sucesso = salvar_diagrama_iptu_html("meu_diagrama.html")

# Ou usando dados personalizados
dados_iptu = {
    "destacar_noventena": True,
    "mostrar_observacao_bancas": True
}
diagrama = criar_diagrama_iptu(dados_iptu)
```

#### Fluxograma Tributário
```python
dados_fluxo = {
    "titulo": "Processo de Cobrança do IPTU",
    "etapas": [
        {"texto": "Lançamento do IPTU", "cor": "#e8f4f8"},
        {"texto": "Notificação ao Contribuinte", "cor": "#fff2e8"},
        {"texto": "Prazo para Pagamento", "cor": "#f0f8e8"},
        {"texto": "Cobrança Administrativa", "cor": "#f8e8e8"},
        {"texto": "Execução Fiscal", "cor": "#f5e8f8"}
    ]
}
diagrama = gerador.gerar_diagrama(TipoDiagramaSVG.FLUXOGRAMA_SIMPLES, dados_fluxo)
```

### Integração com Material Didático
```python
# Criar material com diagramas SVG
material = MaterialDataComDiagrama(...)

# Adicionar diagrama SVG específico
material.adicionar_diagrama(
    tipo=TipoDiagrama.HIERARQUIA,
    dados={'tipo_especifico': 'iptu'},
    titulo="Princípios do IPTU e suas Exceções"
)

# Gerar PDF com diagramas + HTML standalone
generator = LabResumosPDFGeneratorComDiagramas()
resultado_pdf = await generator.gerar_material(material, "material.pdf")
resultado_html = await generator.gerar_diagrama_svg_html(
    TipoDiagramaSVG.IPTU_PRINCIPIOS, 
    {}, 
    "diagrama_standalone.html"
)
```

## Sistema de Gráficos Premium (v1.7)

### Arquitetura dos Gráficos

#### ComponentFactory Renovado
- **Classe Principal**: `ComponentFactory` - Factory pattern para criação de componentes
- **Protocolo Renderable**: Interface unificada para todos os componentes visuais
- **Sistema de Cores**: `LabResumosColors` - Paleta oficial da marca com geração automática de cores harmoniosas

#### Componentes Disponíveis
1. **GraphBarComponent**: Gráficos de barras com múltiplas séries
2. **GraphLineComponent**: Gráficos de linha com área preenchida
3. **GraphPieComponent**: Gráficos de pizza com animações
4. **FlowDiagramComponent**: Diagramas de fluxo estilizados

### Características Premium

#### Paleta de Cores Oficial
```python
# Cores principais do Lab Resumos
AMARELO_QUEIMADO = "#F1CC00"
PRETO_PROFUNDO = "#333B49"  
AZUL_CELESTE = "#2A6B9F"
AZUL_CLARO = "#A0DDFC"
AMARELO_VIBRANTE = "#FEEF4C"
BRANCO_GELO = "#F3F1E8"
```

#### Funcionalidades Avançadas
- **SVG Dinâmico**: Gráficos vetoriais escaláveis gerados programaticamente
- **Animações CSS**: Transições suaves e animações de entrada
- **Base64 Embedding**: SVGs incorporados diretamente no HTML
- **Gradientes**: Efeitos visuais sofisticados com gradientes automáticos
- **Sombras e Filtros**: Efeitos de profundidade e sombra suave
- **Tipografia Controlada**: Fonte Figtree para consistência visual

#### Geração Automática de Cores
- **Paleta Harmoniosa**: Geração automática de cores usando HSL
- **Múltiplas Séries**: Suporte a N séries com cores distintas
- **Pares de Gradiente**: Combinações pré-definidas para efeitos visuais

### Exemplos de Uso

#### Gráfico de Barras Múltiplas Séries
```python
factory = ComponentFactory()

bar_data = {
    'titulo': 'Desempenho por Módulo',
    'categorias': ['Módulo 1', 'Módulo 2', 'Módulo 3', 'Módulo 4'],
    'series': [
        {'nome': 'Teoria', 'dados': [80, 85, 75, 90]},
        {'nome': 'Prática', 'dados': [70, 75, 80, 85]}
    ]
}

html_output = factory.create_component('grafico:barras', bar_data)
```

#### Gráfico de Linha Temporal
```python
line_data = {
    'titulo': 'Evolução de Notas',
    'labels': ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
    'dados': [65, 70, 75, 72, 80, 85],
    'cor': '#2A6B9F'
}

html_output = factory.create_component('grafico:linha', line_data)
```

#### Gráfico de Pizza com Percentuais
```python
pie_data = {
    'titulo': 'Distribuição do Tempo de Estudo',
    'labels': ['Teoria', 'Exercícios', 'Revisão'],
    'dados': [45, 35, 20]
}

html_output = factory.create_component('grafico:pizza', pie_data)
```

### Integração com Templates
- **Embedding Automático**: SVGs convertidos para base64 e incorporados
- **Responsividade**: Gráficos adaptáveis a diferentes tamanhos de tela  
- **Fallback Gracioso**: Sistema de fallback para tipos não suportados
- **Compatibilidade**: Funciona com WeasyPrint para geração de PDF

## Correções de Bugs (v1.8)

### Problemas Resolvidos
- ✅ **Erro "max() iterable argument is empty"**: Corrigido proteções para listas vazias em `component_factory.py` e `components/charts.py`
- ✅ **Erro "list index out of range"**: Adicionadas verificações de índices em acessos a arrays de dados
- ✅ **Robustez nos gráficos**: Implementadas validações para `points`, `dados`, `cores` e outros arrays
- ✅ **Fallbacks defensivos**: Valores padrão seguros quando dados estão ausentes

### Melhorias de Estabilidade
- **Proteção `max()/min()`**: Todas as chamadas agora verificam se listas não estão vazias
- **Acesso seguro a índices**: Uso de `get()` e verificações de tamanho antes de acessar elementos
- **Validação de dados**: Verificações automáticas para prevenir erros de execução
- **Componentes resilientes**: Gráficos funcionam mesmo com dados incompletos ou ausentes

## Próximos Passos
1. Implementar testes unitários para os novos componentes
2. Adicionar validação de dados nos gráficos
3. Criar templates específicos por tipo de material
4. Implementar cache de gráficos gerados
5. Adicionar métricas e monitoramento
6. Expandir tipos de diagramas disponíveis
7. Implementar editor visual de diagramas
8. Adicionar gráficos de radar e área

## Ajustes Parser de Gráficos (v1.9)

### Correções Implementadas

#### 1. Parser de Gráficos Robusto (`pdf_converter.py`)
- **Problema**: Parser simples não processava corretamente o formato do Google Docs
- **Solução**: Implementação de parser mais robusto que:
  - Processa formato `chave: valor` do Google Docs
  - Suporta campos especiais como "Mínima" e "Máxima"
  - Converte vírgulas para pontos em números decimais
  - Trata casos especiais para alíquotas por tipo de imóvel

#### 2. Melhorias no SVG (`component_factory.py`)
- **Viewport Responsivo**: Aumentado de 400x250 para 500x300 pixels
- **Margens Melhoradas**: Ajustadas para acomodar labels (60px left, 40px top, 60px bottom)
- **Animações CSS**: Adicionadas transições suaves e efeitos hover
- **Estilo Visual**: Melhor contraste e efeitos visuais

#### 3. Detecção de Blocos de Gráficos (`content_enricher.py`)
- **Método `_extract_chart_blocks`**: Detecta e processa blocos de gráficos
- **Suporte para casos específicos**: Mínima/Máxima para alíquotas
- **Parser específico para Google Docs**: Adaptado ao formato de export

#### 4. Template HTML Responsivo
- **Container de gráficos**: Wrapper com largura máxima de 600px
- **Page-break protection**: Evita quebra de página nos gráficos
- **Centralização automática**: Gráficos centralizados automaticamente

#### 5. Logging e Debug
- **Logging temporário**: Adicionado para facilitar debug
- **Monitoramento**: Logs do processo de parsing e geração
- **Rastreamento**: Comprimento do HTML gerado

### Casos de Uso Específicos

#### Exemplo: Alíquotas por Tipo de Imóvel
```
{{grafico:barras}}
titulo: Alíquotas por Tipo de Imóvel
categorias: Residencial, Comercial, Industrial, Terreno Baldio
Mínima: 0.5, 1.0, 1.2, 2.0
Máxima: 1.5, 3.0, 3.5, 5.0
{{/grafico}}
```

#### Processamento Robusto
- Detecta palavras-chave em português ("Mínima", "Máxima")
- Converte automaticamente para nomes de série apropriados
- Processa valores decimais com vírgula
- Ignora linhas vazias e malformadas

### Benefícios
- **Compatibilidade**: Total com formato de export do Google Docs
- **Robustez**: Parser resistente a variações de formato
- **Visual**: Gráficos com melhor qualidade visual
- **Debug**: Facilita identificação de problemas

## Versionamento
- **v1.0**: Implementação inicial
- **v1.1**: Correções para API v2 do APITemplate.io
- **v1.2**: Método alternativo e tratamento robusto de erros
- **v1.3**: Extensão com diagramas SVG educacionais (Graphviz)
- **v1.4**: Gerador SVG standalone com HTML completo (baseado no exemplo IPTU fornecido)
- **v1.5**: Correção de emojis (HTML entities), suporte a capa padrão via config, bloco de `diagramas` no template base, e naming com timestamp
- **v1.6**: Gráficos inline — suporte a `{{grafico:barras}}` com N séries, cores automáticas e template dinâmico; remoção do `<pre>` para permitir HTML renderizável na seção
- **v1.7**: Sistema de Gráficos Premium — implementação completa com SVG dinâmicos, paleta de cores Lab Resumos, animações suaves e componentes visuais de alta qualidade
- **v1.8**: Correções de Bugs Críticos — resolução dos erros "max() iterable argument is empty" e "list index out of range", melhorias na robustez e estabilidade dos componentes de gráficos
- **v1.9**: Ajustes Parser de Gráficos — correção robusto do parser para formato Google Docs, melhorias visuais no SVG, template responsivo e debug avançado

## Correções e Ajustes (2025-08-20) — Lab Resumos Premium (PDF Generator v2)

- Atualizações no `PDF Generator v2/lab_resumos.py`:
  - GoogleDocsParser._add_defaults: não sobrescreve valores vazios porém válidos. Agora só adiciona defaults quando a chave não existe no `document`.
  - GoogleDocsParser._extract_sections: parser linha-a-linha que preserva TODO o conteúdo, criando seção única quando não há headings.
  - GoogleDocsParser._clean_html: atualizado para preservar toda a estrutura (headings h1-h3, listas, parágrafos) e decodificar entidades HTML.
  - GoogleDocsParser.parse_document: adicionados logs de debug (título, prévia do conteúdo, elementos detectados e número de seções).
  - LabResumosApp.process_document: prioridade para conteúdo do Google Docs; merge não destrutivo do `cabecalho` (defaults só se ausente).
  - CLI: parâmetro `source` agora é opcional e usa por padrão o link do Google Docs fornecido, permitindo executar o script sem argumentos.

- Parsers específicos:
  - CustomSyntaxParser._parse_grafico: corrigido para aceitar formatos "Mês: valor", "Categoria: valor" e categoria/séries para barras, incluindo fallback e conversão de vírgula para ponto.
  - CustomSyntaxParser._parse_table: robusto para `tabela_performance` e `cronograma` com headers e linhas flexíveis, incluindo ✓ e ⚠.
  - CustomSyntaxParser._parse_lista_aninhada: implementado parser hierárquico simples (título + subitens com - ou *).
  - CustomSyntaxParser._parse_glossario: corrigido split pelo primeiro `|` e validação de termo/definição.

- Conversor PDF:
  - LabResumosPDFConverter: geração de gráficos reais via matplotlib (`_generate_line_chart`, `_generate_pie_chart`, `_generate_bar_chart`) e injeção como imagens base64 antes da renderização do template.

- Template premium `lab_resumos_template.html`:
  - Adicionadas seções: "Tabela de Performance", "Cronograma" e "Lista Aninhada" após a Checklist.
  - Glossário com validação (`item.termo` e `item.definicao`) para evitar páginas vazias.
  - Blocos para exibir gráficos reais (`grafico_linha_img`, `grafico_pizza_img`, `grafico_barras_img`).

- Dependências:
  - Adicionada `matplotlib>=3.8.0` para geração de gráficos.

- Template `PDF Generator v2/templates/lab_resumos_template.html` auditado: sem conteúdo hardcoded indevido; utiliza variáveis do Jinja2 para renderização dinâmica de conceitos, tabelas, listas e demais componentes.

## Correções Críticas no GoogleDocsParser (2025-01-05)

### Problemas Resolvidos
1. **PROBLEMA PRINCIPAL**: No método `parse_document`, a linha `'secoes': self._extract_sections(parsed['texto_puro'])` estava usando o texto já processado pelo `custom_parser`, que havia removido todas as tags customizadas, resultando em seções vazias.

2. **SOLUÇÃO IMPLEMENTADA**: Mudança para usar o conteúdo original antes do processamento: `'secoes': self._extract_sections(content)` - preservando todo o conteúdo do documento.

### Melhorias Implementadas

#### 1. Método `_extract_sections` Renovado
- **Processamento em duas etapas**: Remove apenas tags customizadas primeiro, depois processa seções
- **Detecção melhorada de headings**: Suporte completo para H1 (`# Título`), H2 (`## Título`) e H3 (`### Título`)
- **Preservação de conteúdo**: Mantém todo o texto, incluindo formatação e quebras de linha
- **Estrutura de dados melhorada**: Inclui `tipo` (h1, h2, h3, paragrafo), `titulo`, `conteudo` e `nivel`

#### 2. Template HTML Atualizado
- **Renderização hierárquica**: Suporte diferenciado para H1, H2 e H3 com estilos específicos
- **Preservação de formatação**: Usa `white-space: pre-wrap` para manter quebras de linha
- **Condições melhoradas**: Renderiza seções mesmo quando só têm título (sem conteúdo)
- **Estilos visuais**: H1 com 28px, H2 padrão, H3 com 18px e cor azul

#### 3. Método `_clean_html` Melhorado
- **Preservação de formatação**: Converte `<strong>` e `<b>` para `**texto**`
- **Suporte a itálico**: Converte `<em>` e `<i>` para `*texto*`
- **Listas aprimoradas**: Processamento separado de `<ul>`, `<ol>` e `<li>`
- **Estrutura preservada**: Mantém hierarquia original do documento

#### 4. Suporte Completo para Listas
- **CSS para listas**: Estilos específicos para `ul`, `ol` e `li` no template
- **Processamento robusto**: Separação correta de elementos de lista no parser
- **Aninhamento**: Suporte para listas aninhadas com margem superior

### Impacto das Correções
- ✅ **Seções não mais vazias**: Conteúdo original preservado durante extração
- ✅ **Hierarquia visual**: H1, H2, H3 renderizados com estilos apropriados
- ✅ **Formatação preservada**: Negrito, itálico e listas mantidos
- ✅ **Quebras de linha**: Estrutura original do documento respeitada
- ✅ **Robustez**: Parser resistente a variações de formato do Google Docs

### Benefícios
- **Conteúdo completo**: Nenhuma informação perdida durante o processamento
- **Visual profissional**: Hierarquia clara com tipografia adequada
- **Compatibilidade**: Funciona com qualquer documento do Google Docs
- **Manutenibilidade**: Código mais limpo e estruturado

## Ajustes PDF Generator v3 (2025-08-20)

- **Template Jinja (progresso)**: Corrigido acesso à chave `items` para evitar colisão com `dict.items()` do Jinja.
  - Antes: `e.dados.items`
  - Depois: `e.dados['items']`
- **Parser (Docs JSON → modelo interno)**:
  - Implementado percurso canônico dos elementos do Google Docs (parágrafos, listas, tabelas, objetos inline) de forma recursiva.
  - **Listas**: suporte a níveis aninhados via `bullet.listId` e `nestingLevel`, escolhendo `<ul>` ou `<ol>` conforme `lists[listId].listProperties.nestingLevels`.
  - **Tabelas**: suporte completo a `tableRows` → `tableCells` → `content`, concatenação de `textRun` por célula e emissão de `<table><tbody>...`.
  - **Imagens**: download de `inlineObjects.imageProperties.contentUri` com refresh automático de credenciais em caso de `401/403`.
  - **Estilos de texto**: mapeamento de `textRun.textStyle` para negrito/itálico e links; `namedStyleType` mapeado para headings H1–H3 no markdown interno.
- **CSS para PDF (WeasyPrint)**:
  - `@page { size: A4; margin: 15mm; }`
  - `img { max-width: 100%; height: auto; }`
  - Classes utilitárias para quebras: `.pagebreak { break-before: page; }` e evitar quebra dentro de títulos.

## Extensão de Tipos de Blocos (2025-01-05) — PDF Generator v3

### Novos Tipos de Blocos Implementados

#### 1. Gráficos e Visualizações
- **`grafico_linha`**: Gráficos de linha com séries de dados
  - Formato: `titulo: X` + linhas `Label: valor`
  - Suporte a valores numéricos e texto
  - Conversão automática de vírgula para ponto decimal

- **`grafico_pizza`**: Gráficos de pizza circulares
  - Mesmo formato do gráfico de linha
  - Renderização em lista com labels e valores

- **`grafico_barras`**: Gráficos de barras com múltiplas séries
  - Formato: `titulo: X` + `categorias: a,b,c` + `serie1: v1,v2...`
  - Suporte a N séries de dados
  - Processamento robusto de valores numéricos

#### 2. Tabelas Estruturadas
- **`tabela_performance`**: Tabelas de performance com cabeçalhos
  - Primeira linha como cabeçalho (separada por `|`)
  - Linhas de dados opcionais
  - Linha de total opcional com formato `total: valor1|valor2...`

- **`cronograma`**: Tabelas de cronograma
  - Headers padrão: Fase, Início, Fim, Responsável, Status
  - Headers customizáveis via primeira linha
  - Estrutura flexível para projetos e planejamento

#### 3. Listas e Estruturas
- **`lista_aninhada`**: Listas hierárquicas
  - Títulos em linhas sem `-`
  - Itens com `- ` no início
  - Suporte a múltiplos blocos aninhados

- **`referencias`**: Lista simples de referências
  - Uma referência por linha
  - Formato limpo e direto

#### 4. Elementos Educacionais
- **`citacao`**: Citações com atribuição
  - Formato: `texto: "..."`, `autor: Nome`, `ano: 2024`
  - Renderização estilizada com aspas e atribuição

- **`formula`**: Fórmulas matemáticas
  - Campos: `nome`, `formula`, `descricao`
  - Apresentação estruturada para conteúdo técnico

- **`questao_multipla`**: Questões de múltipla escolha
  - Formato: `pergunta: ...`, `resposta: ...`
  - Alternativas em linhas separadas (estilo "A) texto")
  - Renderização com numeração automática

#### 5. Código e Técnico
- **`codigo`**: Blocos de código
  - Campos opcionais: `linguagem`, `titulo`, `descricao`
  - Código preservado em `<pre>` estilizado
  - Suporte a syntax highlighting via linguagem

- **`fluxograma`**: Fluxogramas simples
  - Cada linha como um nó
  - Renderização em lista para visualização

#### 6. Métricas e Progresso
- **`metricas`**: Tabelas de métricas
  - Formato: `Nome | Valor | Delta`
  - Estrutura flexível para KPIs

- **`progresso`**: Indicadores de progresso
  - Formato: `Etiqueta: valor`
  - Suporte a valores numéricos e texto

#### 7. Recursos Interativos
- **`links`**: Links externos
  - Formato: `Texto | URL`
  - Renderização com links clicáveis

- **`qrcode`**: Códigos QR
  - Aceita `url: ...` ou texto direto
  - Geração automática via função `generate_qr_code`
  - Integração com ambiente Jinja

- **`barcode`**: Códigos de barras
  - Suporte a `codigo: ...` ou texto direto
  - Renderização textual para códigos

### Implementação Técnica

#### Parser Robusto (`_parse_block`)
- **Processamento linha-a-linha**: Análise robusta de cada linha do bloco
- **Conversão automática**: Vírgulas para pontos em valores numéricos
- **Fallbacks inteligentes**: Valores padrão quando campos estão ausentes
- **Validação de dados**: Verificações para evitar erros de parsing

#### Template Jinja2 Extendido
- **Renderização condicional**: Cada tipo de bloco com template específico
- **Estilos CSS consistentes**: Uso da paleta de cores Lab Resumos
- **Responsividade**: Layout adaptável para diferentes tamanhos de tela
- **Integração QR Code**: Função `generate_qr_code` exposta no ambiente Jinja

#### Função QR Code Integrada
```python
# Exposição no ambiente Jinja
self.env.globals['generate_qr_code'] = generate_qr_code

# Uso no template
{% set img = e.dados.url and ('data:image/png;base64,' ~ (generate_qr_code(e.dados.url))) %}
```

### Casos de Uso

#### Exemplo: Gráfico de Barras
```
{{grafico_barras}}
titulo: Desempenho por Módulo
categorias: Módulo 1, Módulo 2, Módulo 3
Teoria: 80, 85, 75
Prática: 70, 75, 80
{{/grafico_barras}}
```

#### Exemplo: Questão de Múltipla Escolha
```
{{questao_multipla}}
pergunta: Qual é o princípio fundamental do IPTU?
A) Legalidade
B) Anterioridade  
C) Noventena
D) Todos os anteriores
resposta: D
{{/questao_multipla}}
```

#### Exemplo: Cronograma
```
{{cronograma}}
Fase | Início | Fim | Responsável | Status
Análise | 01/01 | 15/01 | João | Concluído
Desenvolvimento | 16/01 | 31/01 | Maria | Em andamento
{{/cronograma}}
```

### Benefícios da Implementação
- **Flexibilidade**: Suporte a 20+ tipos de blocos diferentes
- **Robustez**: Parser resistente a variações de formato
- **Visual**: Renderização consistente com identidade visual
- **Educacional**: Tipos específicos para conteúdo didático
- **Interativo**: QR codes e links funcionais
- **Extensível**: Fácil adição de novos tipos de blocos
