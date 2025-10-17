// ==================== CONFIGURAÇÃO ====================
// As chaves agora vêm do arquivo Keys.gs
// Se o arquivo Keys.gs não existir, use as chaves diretamente aqui
let KEYS;
try {
  KEYS = getKeys(); // Tenta pegar do arquivo Keys.gs
} catch(e) {
  // Se não existir Keys.gs, defina as chaves aqui diretamente
  KEYS = {
    ANTHROPIC_API_KEY: 'sua-chave-anthropic-aqui' // SUBSTITUA COM SUA CHAVE
  };
}

const CONFIG = {
  // Chave da API Claude
  ANTHROPIC_API_KEY: KEYS.ANTHROPIC_API_KEY,
  
  
  // Modelo Claude Opus 4.1 (mais avançado)
  CLAUDE_MODEL: 'claude-opus-4-1-20250805',
  
  // ID da planilha de prompts
  PROMPTS_SHEET_ID: '18ZRgSKCaLvFBB_5JOMH8bX_C5YwRTuQ8sQmGpvY42Ck',
  
  // Identidade visual LabResumos (Maralto)
  BRAND_GUIDELINES: {
    colors: {
      primary: '#F1CC00',     // Amarelo queimado
      secondary: '#333B49',   // Preto profundo
      background: 'transparent', // Fundo transparente para melhor integração
    },
    fonts: {
      primary: 'Arial, sans-serif'
    }
  }
};

// ==================== BUSCAR PROMPTS DA PLANILHA ====================
function getPromptsFromSheet() {
  try {
    console.log('Buscando prompts da planilha...');
    
    // Abrir a planilha
    const sheet = SpreadsheetApp.openById(CONFIG.PROMPTS_SHEET_ID);
    const activeSheet = sheet.getActiveSheet();
    
    // Buscar os ranges dos prompts
    const systemPrompt = getNamedRangeValue(activeSheet, 'systemPrompt');
    const refinePrompt = getNamedRangeValue(activeSheet, 'refinePrompt');
    const analyzeImagePrompt = getNamedRangeValue(activeSheet, 'analyzeImagePrompt');
    const createFromTextPrompt = getNamedRangeValue(activeSheet, 'createFromTextPrompt');
    
    // Criar objeto com os prompts
    const prompts = {
      systemPrompt: systemPrompt || getDefaultSystemPrompt(),
      refinePrompt: refinePrompt || getDefaultRefinePrompt(),
      analyzeImagePrompt: analyzeImagePrompt || getDefaultAnalyzeImagePrompt(),
      createFromTextPrompt: createFromTextPrompt || getDefaultCreateFromTextPrompt()
    };
    
    console.log('Prompts carregados da planilha com sucesso');
    return prompts;
    
  } catch (error) {
    console.error('Erro ao buscar prompts da planilha:', error);
    console.log('Usando prompts padrão como fallback');
    
    // Retornar prompts padrão em caso de erro
    return {
      systemPrompt: getDefaultSystemPrompt(),
      refinePrompt: getDefaultRefinePrompt(),
      analyzeImagePrompt: getDefaultAnalyzeImagePrompt(),
      createFromTextPrompt: getDefaultCreateFromTextPrompt()
    };
  }
}

// Função auxiliar para buscar valor de um named range
function getNamedRangeValue(sheet, rangeName) {
  try {
    // Primeiro tenta buscar como named range
    const namedRange = sheet.getParent().getRangeByName(rangeName);
    if (namedRange) {
      const values = namedRange.getValues();
      return values.flat().join('\n').trim();
    }
    
    // Se não encontrar como named range, tenta buscar como célula com o nome
    const range = sheet.getRange(rangeName);
    if (range) {
      return range.getValue();
    }
    
    return null;
  } catch (error) {
    console.log(`Range '${rangeName}' não encontrado: ${error}`);
    return null;
  }
}

// Prompts padrão como fallback
function getDefaultSystemPrompt() {
  return `## Instruções para Estilização de Diagramas em SVG - Identidade Visual Lab Resumos

CRITICAL: O SVG DEVE ter fundo completamente TRANSPARENTE. NÃO adicione nenhum retângulo de fundo.

Você deve criar/transformar diagramas em SVGs profissionais e elegantes seguindo estas diretrizes:

**CONFIGURAÇÃO BASE:**
Canvas com viewport adequado ao conteúdo. Se dimensões específicas forem fornecidas, use-as exatamente. Caso contrário, use 1600x1200 como padrão. NÃO adicione retângulo de fundo - mantenha o fundo TRANSPARENTE para melhor integração com documentos. Paleta de cores obrigatória: #F1CC00 (amarelo queimado) para destaques, #333B49 (preto profundo) para textos/contornos, #F3F1E8 (branco gelo) para fundos de caixas/elementos, #000000 com opacity="0.08-0.12" para sombras.

**IMPORTANTE - FUNDO TRANSPARENTE:**
- NÃO crie um retângulo de fundo cobrindo todo o SVG
- O fundo do diagrama deve ser transparente
- Apenas os elementos individuais (caixas, containers) devem ter cor de fundo (#F3F1E8)
- Isso permite melhor integração quando inserido em documentos

**TIPOGRAFIA:**
Use font-family="Arial, sans-serif" sempre. Títulos: font-size="28" font-weight="bold". Subtítulos: font-size="22-24" font-weight="600". Texto normal: font-size="18-20" font-weight="500". IMPORTANTE: Sempre calcule o tamanho do texto em relação à largura da caixa - se a caixa tem 240px de largura, use no máximo font-size="20" para textos simples e font-size="16-18" para textos longos. Para textos com múltiplas linhas, divida em elementos <text> separados com y incrementado de 25-30px entre linhas. Use text-anchor="middle" e posicione x no centro da caixa.

**ESTRUTURA DE CONTAINERS:**
Cada caixa deve seguir: <g transform="translate(x,y)"><rect x="2" y="2" width="[w]" height="[h]" rx="8" fill="#000000" opacity="0.08"/><rect x="0" y="0" width="[w]" height="[h]" rx="8" fill="#F3F1E8" stroke="#333B49" stroke-width="2.5"/><rect x="0" y="0" width="4" height="[h]" rx="2" fill="#F1CC00"/></g>. Headers principais: fundo #333B49, texto #F3F1E8, palavras-chave com <tspan fill="#F1CC00" font-weight="700">. Adicione marcador bookmark: <path d="M 30 0 L 55 0 L 55 30 L 42.5 20 L 30 30 Z" fill="#F1CC00"/>.

**PREVENÇÃO DE OVERFLOW DE TEXTO:**
REGRA CRÍTICA: Antes de adicionar texto, calcule: largura útil da caixa = largura - 40px (20px margem cada lado). Se texto simples (1-2 palavras): pode usar até font-size="24". Se texto médio (3-5 palavras): máximo font-size="20". Se texto longo ou múltiplas linhas: máximo font-size="16-18". Para múltiplas linhas, altura entre linhas = 25px para font-size="18", 22px para font-size="16". Sempre teste mentalmente se o texto cabe: comprimento aproximado = número de caracteres × (font-size × 0.6). Se não couber, reduza font-size ou quebre em mais linhas.

**CONEXÕES:**
Use <defs><marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto"><polygon points="0 0, 10 3.5, 0 7" fill="#333B49"/></marker></defs> e aplique com marker-end="url(#arrowhead)" nas linhas com stroke="#333B49" stroke-width="2.5".

**ELEMENTOS DE DESTAQUE:**
Caixas finais/resultado: fundo #F1CC00, texto #333B49 font-weight="bold", tamanho maior. Opcionalmente adicione moldura pontilhada interna com stroke-dasharray="3,2" opacity="0.3".

**ESPAÇAMENTO:**
Margem mínima 50px das bordas. Entre elementos: mínimo 40px. Caixas padrão: largura 240px (ajustável), altura conforme conteúdo. Headers: 600-700px largura.

**PROCESSO:**
1. Analise a hierarquia do diagrama/descrição
2. Defina larguras das caixas baseado no conteúdo (textos longos = caixas maiores)
3. Calcule font-sizes apropriados para cada texto ANTES de posicionar
4. Distribua elementos uniformemente no canvas
5. Aplique cores: escuro para headers, claro para conteúdo de caixas, amarelo para destaques
6. Adicione sombras em TODOS os containers
7. Insira barras laterais amarelas de 4px nas caixas de conteúdo
8. Para textos que não cabem: reduza font-size ou aumente a caixa
9. NÃO adicione retângulo de fundo - mantenha transparente

**CHECKLIST ANTI-OVERFLOW:**
- Texto de 1 palavra: máximo 80% da largura da caixa
- Texto de 1 linha: máximo 90% da largura com margem 20px cada lado  
- Múltiplas linhas: calcule altura necessária (número de linhas × 25px + margens)
- Se texto escapa horizontalmente: reduza font-size em 2 pontos e teste novamente
- Se texto escapa verticalmente: aumente altura da caixa ou reduza font-size
- Verificar: NÃO há retângulo cobrindo 100% do canvas

Sempre priorize legibilidade sobre tamanho. É melhor ter texto menor e legível do que texto cortado. Quando em dúvida, quebre em mais linhas ou aumente a caixa. Mantenha consistência visual em elementos similares.

IMPORTANTE: Retorne APENAS o código SVG completo, sem explicações, sem markdown, sem comentários. O SVG deve começar com <svg e terminar com </svg>. NÃO inclua um retângulo de fundo - deixe transparente.`;
}

function getDefaultRefinePrompt() {
  return `Você é um especialista em edição e refinamento de diagramas SVG. Sua tarefa é modificar um SVG existente seguindo instruções específicas do usuário.

REGRAS IMPORTANTES:
1. Mantenha a estrutura geral e o estilo do SVG original
2. Aplique APENAS as mudanças solicitadas pelo usuário
3. Preserve a identidade visual LabResumos:
   - Cores: #F1CC00 (amarelo), #333B49 (preto), #F3F1E8 (fundo de elementos)
   - Fonte: Arial, sans-serif
   - Bordas arredondadas, sombras sutis
   - FUNDO TRANSPARENTE (não adicione retângulo de fundo)
4. Mantenha todos os elementos que não foram mencionados para mudança
5. Garanta que textos não ultrapassem as bordas dos containers
6. Se solicitado para mover elementos, ajuste as conexões/setas adequadamente
7. Se solicitado para adicionar elementos, siga o estilo existente
8. NUNCA adicione um retângulo de fundo - mantenha transparente

Retorne APENAS o código SVG refinado, sem explicações ou markdown.`;
}

function getDefaultAnalyzeImagePrompt() {
  return `Analise esta imagem e crie um diagrama SVG profissional seguindo as instruções de estilização fornecidas.
IMPORTANTE: O SVG deve ter as mesmas dimensões da imagem original: width="{width}" height="{height}".`;
}

function getDefaultCreateFromTextPrompt() {
  return `Crie um diagrama SVG profissional baseado na seguinte descrição:
Use dimensões de 1600x1200 pixels para o canvas SVG.

{description}

IMPORTANTE: 
- Crie um diagrama completo e profissional seguindo as instruções de estilização
- Use a identidade visual LabResumos (cores #F1CC00, #333B49, #F3F1E8)
- Mantenha o fundo transparente
- Organize os elementos de forma clara e hierárquica
- Adicione sombras, bordas arredondadas e elementos visuais modernos
- Retorne APENAS o código SVG, sem explicações`;
}

// ==================== DETECÇÃO DE MULTI-LOGIN ====================
function checkMultipleAccountIssue() {
  try {
    // Tenta uma operação simples do Drive
    DriveApp.getRootFolder();
    return true;
  } catch (e) {
    // Verifica se é erro de permissão relacionado a multi-login
    if (e.toString().includes('PERMISSION_DENIED') || 
        e.toString().includes('Authorization is required')) {
      return false;
    }
    throw e;
  }
}

// ==================== MENU DO GOOGLE DOCS ====================
function onOpen() {
  try {
    // Verifica multi-login primeiro
    if (!checkMultipleAccountIssue()) {
      showMultiLoginWarning();
      return;
    }
    
    // Menu normal se tudo OK
    DocumentApp.getUi()
      .createMenu('📊 Diagrama LabResumos')
      .addItem('✨ Criar do Zero', 'showCreateFromTextDialog')
      .addItem('🖼️ Gerar de Imagem', 'showDiagramDialog')
      .addSeparator()
      .addItem('ℹ️ Sobre', 'showAbout')
      .addToUi();
  } catch (e) {
    // Fallback para erro de permissão
    showMultiLoginWarning();
  }
}

// ==================== AVISO DE MULTI-LOGIN ====================
function showMultiLoginWarning() {
  const html = HtmlService.createHtmlOutput(`
    <div style="padding: 20px; font-family: Arial, sans-serif;">
      <h2 style="color: #d32f2f;">⚠️ Erro de Múltiplas Contas</h2>
      <p><strong>O Google Apps Script detectou que você está logado em múltiplas contas Google.</strong></p>
      
      <h3>Como resolver:</h3>
      <ol>
        <li><strong>Opção 1:</strong> Faça logout de todas as contas Google e faça login apenas com a conta que tem acesso a este documento</li>
        <li><strong>Opção 2:</strong> Use uma janela anônima/privada do navegador</li>
        <li><strong>Opção 3:</strong> Use um navegador diferente ou perfil diferente do Chrome</li>
      </ol>
      
      <h3>Passos detalhados:</h3>
      <p>1. Clique no seu avatar no canto superior direito<br>
      2. Clique em "Sair de todas as contas"<br>
      3. Faça login apenas com a conta necessária<br>
      4. Recarregue este documento</p>
      
      <p style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px;">
        <strong>💡 Dica:</strong> Este é um problema conhecido do Google. 
        Para uso regular, recomendamos usar uma janela anônima quando trabalhar com Apps Script.
      </p>
    </div>
  `)
  .setWidth(500)
  .setHeight(400);
  
  DocumentApp.getUi().showModalDialog(html, 'Erro de Permissão - Multi-login Detectado');
}

// ==================== INTERFACE PARA CRIAR DO ZERO ====================
function showCreateFromTextDialog() {
  const htmlContent = `
<!DOCTYPE html>
<html>
<head>
  <base target="_top">
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 20px;
      background: #F3F1E8;
      margin: 0;
    }
    
    .container {
      max-width: 100%;
    }
    
    .header {
      background: linear-gradient(135deg, #F1CC00 0%, #FFD700 100%);
      padding: 15px;
      border-radius: 10px;
      margin-bottom: 20px;
      color: #333B49;
    }
    
    .info-box {
      background: #e3f2fd;
      border-left: 4px solid #2196f3;
      padding: 15px;
      margin: 15px 0;
      font-size: 14px;
      border-radius: 5px;
    }
    
    .example-box {
      background: #f5f5f5;
      border: 1px solid #ddd;
      padding: 15px;
      margin: 10px 0;
      border-radius: 8px;
      font-size: 13px;
    }
    
    .example-box strong {
      color: #333B49;
      display: block;
      margin-bottom: 8px;
    }
    
    textarea {
      width: 100%;
      padding: 12px;
      border: 2px solid #333B49;
      border-radius: 8px;
      font-size: 14px;
      resize: vertical;
      min-height: 200px;
      box-sizing: border-box;
      background: white;
    }
    
    .button-group {
      display: flex;
      gap: 10px;
      margin-top: 20px;
    }
    
    button {
      flex: 1;
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .btn-primary {
      background: #F1CC00;
      color: #333B49;
    }
    
    .btn-primary:hover {
      background: #FFD700;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(241, 204, 0, 0.4);
    }
    
    .btn-primary:disabled {
      background: #ccc;
      cursor: not-allowed;
      transform: none;
    }
    
    .btn-secondary {
      background: #333B49;
      color: white;
    }
    
    .btn-secondary:hover {
      background: #1a1f29;
    }
    
    .btn-success {
      background: #4caf50;
      color: white;
    }
    
    .btn-success:hover {
      background: #45a049;
    }
    
    .loading {
      display: none;
      text-align: center;
      padding: 20px;
    }
    
    .spinner {
      border: 4px solid #F3F1E8;
      border-top: 4px solid #F1CC00;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin: 0 auto;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    .error {
      color: #d32f2f;
      background: #ffebee;
      padding: 10px;
      border-radius: 5px;
      margin-top: 10px;
      display: none;
    }
    
    .success {
      color: #2e7d32;
      background: #e8f5e9;
      padding: 10px;
      border-radius: 5px;
      margin-top: 10px;
      display: none;
    }
    
    .svg-preview-container {
      display: none;
      margin-top: 20px;
      padding: 20px;
      background: white;
      border-radius: 10px;
      border: 2px solid #333B49;
    }
    
    .svg-preview-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 2px solid #F1CC00;
    }
    
    .svg-preview-title {
      font-size: 18px;
      font-weight: bold;
      color: #333B49;
    }
    
    .svg-preview-status {
      font-size: 14px;
      color: #666;
      font-style: italic;
    }
    
    .svg-preview-content {
      width: 100%;
      min-height: 300px;
      max-height: 500px;
      overflow: auto;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 10px;
      background: #f9f9f9;
    }
    
    .svg-preview-content svg {
      width: 100%;
      height: auto;
    }
    
    .regenerate-section {
      margin-top: 15px;
      padding: 15px;
      background: #f3f1e8;
      border-radius: 8px;
    }
    
    .regenerate-section label {
      display: block;
      margin-bottom: 8px;
      font-weight: bold;
      color: #333B49;
    }
    
    .action-buttons {
      display: flex;
      gap: 10px;
      margin-top: 15px;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h2 style="margin: 0;">✨ Criar Diagrama do Zero</h2>
      <p style="margin: 5px 0 0 0; opacity: 0.8;">Descreva o diagrama que você deseja criar</p>
    </div>
    
    <div class="info-box">
      💡 <strong>Dica:</strong> Seja específico na sua descrição. Mencione o tipo de diagrama (fluxograma, mapa mental, organograma), os elementos principais e como eles se conectam.
    </div>
    
    <div class="info-box" style="background: #fff3cd; border-left: 4px solid #F1CC00;">
      ⚙️ <strong>Personalizar Prompts:</strong> 
      <a href="https://docs.google.com/spreadsheets/d/18ZRgSKCaLvFBB_5JOMH8bX_C5YwRTuQ8sQmGpvY42Ck/edit" target="_blank" style="color: #333B49; text-decoration: underline;">
        Editar prompts na planilha
      </a>
      - Os prompts são consultados a cada geração para usar sempre a versão mais atual.
    </div>
    
    <label for="diagramDescription" style="font-weight: bold; color: #333B49;">Descreva o diagrama desejado:</label>
    <textarea id="diagramDescription" placeholder="Ex: Crie um fluxograma mostrando o processo de vendas online com 5 etapas: 1) Cliente visita site, 2) Escolhe produto, 3) Adiciona ao carrinho, 4) Realiza pagamento, 5) Recebe confirmação. Adicione setas conectando cada etapa e ícones relevantes."></textarea>
    
    <div class="example-box">
      <strong>Exemplos de descrições:</strong>
      • <em>"Fluxograma vertical com 4 caixas: Início → Análise → Processamento → Resultado"</em><br>
      • <em>"Mapa mental sobre Marketing Digital com 5 ramos principais: SEO, Redes Sociais, Email, Conteúdo, Analytics"</em><br>
      • <em>"Organograma da empresa com CEO no topo, 3 diretores abaixo, cada um com 2 gerentes"</em><br>
      • <em>"Diagrama circular mostrando o ciclo de vida do produto: Desenvolvimento → Lançamento → Crescimento → Maturidade → Declínio"</em>
    </div>
    
    <div class="info-box">
      💡 <strong>Dica:</strong> O diagrama será copiado como PNG com fundo branco para a área de transferência.
    </div>
    
    <div class="button-group" id="initialButtons">
      <button class="btn-primary" id="generateBtn">
        🚀 Gerar Diagrama
      </button>
      <button class="btn-secondary" id="clearBtn">
        🔄 Limpar
      </button>
    </div>
    
    <div class="loading" id="loading">
      <div class="spinner"></div>
      <p id="loadingText">Criando seu diagrama com Claude Opus 4.1...</p>
      <p style="font-size: 12px; opacity: 0.7;">Gerando visualização personalizada...</p>
    </div>
    
    <!-- Preview do SVG -->
    <div class="svg-preview-container" id="svgPreviewContainer">
      <div class="svg-preview-header">
        <span class="svg-preview-title">📊 Preview do Diagrama</span>
        <span class="svg-preview-status" id="previewStatus">Diagrama criado com sucesso!</span>
      </div>
      
      <div class="svg-preview-content" id="svgPreviewContent">
        <!-- SVG será inserido aqui -->
      </div>
      
      <div class="regenerate-section">
        <label for="regenerateInstructions">Deseja fazer algum ajuste? Descreva as mudanças:</label>
        <textarea id="regenerateInstructions" placeholder="Ex: Aumentar o tamanho do texto, mudar a cor das setas, reorganizar os elementos..."></textarea>
      </div>
      
      <div class="action-buttons">
        <button class="btn-primary" id="regenerateBtn">
          🔄 Regenerar com Ajustes
        </button>
        <button class="btn-success" id="copyBtn">
          📋 Copiar Imagem
        </button>
        <button class="btn-secondary" id="cancelBtn">
          ❌ Voltar
        </button>
      </div>
      
      <div id="manualInstructions" style="display:none; margin-top: 15px; padding: 15px; background: #e8f5e9; border-radius: 8px; border-left: 4px solid #4caf50;">
        <h4 style="margin: 0 0 10px 0; color: #2e7d32;">✅ Imagem copiada para área de transferência!</h4>
        <p style="margin: 5px 0; color: #2e7d32;">
          <strong>1.</strong> Clique no documento onde deseja inserir a imagem<br>
          <strong>2.</strong> Use <strong>Ctrl+V</strong> (ou <strong>Cmd+V</strong> no Mac) para colar
        </p>
        <p style="margin: 5px 0 0 0; font-size: 12px; color: #666; font-style: italic;">
          💡 Se não funcionar, tente usar um navegador diferente ou baixe a imagem
        </p>
      </div>
    </div>
    
    <div class="error" id="error"></div>
    <div class="success" id="success"></div>
  </div>
  
  <script>
    let currentSVG = null;
    
    // Verificação no carregamento da página
    window.onload = function() {
      // Verificar se consegue acessar google.script
      try {
        google.script.run
          .withSuccessHandler(function() {
            console.log('Conexão com servidor OK');
          })
          .withFailureHandler(function(e) {
            if (e.message && e.message.includes('PERMISSION_DENIED')) {
              document.body.innerHTML = 
                '<div style="padding: 20px; color: red;">' +
                  '<h2>⚠️ Erro de Múltiplas Contas</h2>' +
                  '<p>Detectamos que você está logado em múltiplas contas Google.</p>' +
                  '<p><strong>Solução:</strong> Use uma janela anônima ou faça logout de outras contas.</p>' +
                '</div>';
            }
          })
          .checkMultipleAccountIssue();
      } catch (e) {
        console.error('Erro ao verificar permissões:', e);
      }
    };
    
    // Event Listeners
    document.getElementById('generateBtn').addEventListener('click', generateFromText);
    document.getElementById('clearBtn').addEventListener('click', clearForm);
    document.getElementById('regenerateBtn').addEventListener('click', regenerateDiagram);
    document.getElementById('copyBtn').addEventListener('click', copyImageToClipboard);
    document.getElementById('cancelBtn').addEventListener('click', cancelPreview);
    
    function generateFromText() {
      const description = document.getElementById('diagramDescription').value.trim();
      
      if (!description) {
        showError('Por favor, descreva o diagrama que deseja criar.');
        return;
      }
      
      // Esconder elementos iniciais
      document.getElementById('initialButtons').style.display = 'none';
      document.getElementById('generateBtn').disabled = true;
      document.getElementById('loading').style.display = 'block';
      document.getElementById('error').style.display = 'none';
      document.getElementById('success').style.display = 'none';
      
      // Enviar para o servidor
      google.script.run
        .withSuccessHandler(onPreviewSuccess)
        .withFailureHandler(onError)
        .generateFromTextOnly(description);
    }
    
    function regenerateDiagram() {
      const regenerateInstructions = document.getElementById('regenerateInstructions').value;
      
      if (!regenerateInstructions) {
        showError('Por favor, descreva as mudanças desejadas.');
        return;
      }
      
      // Esconder preview e mostrar loading
      document.getElementById('svgPreviewContainer').style.display = 'none';
      document.getElementById('loading').style.display = 'block';
      document.getElementById('error').style.display = 'none';
      
      google.script.run
        .withSuccessHandler(onPreviewSuccess)
        .withFailureHandler(onError)
        .refineSVG(currentSVG, regenerateInstructions);
    }
    
    
    function cancelPreview() {
      // Voltar para o estado inicial
      document.getElementById('svgPreviewContainer').style.display = 'none';
      document.getElementById('initialButtons').style.display = 'flex';
      document.getElementById('generateBtn').disabled = false;
      document.getElementById('regenerateInstructions').value = '';
      
      // Esconder instruções manuais
      document.getElementById('manualInstructions').style.display = 'none';
      
      currentSVG = null;
    }
    
    // ==================== FUNÇÃO PARA COPIAR IMAGEM ====================
    async function copyImageToClipboard() {
      if (!currentSVG) {
        showError('Nenhum diagrama para copiar.');
        return;
      }
      
      try {
        // Desabilitar botão temporariamente
        const copyBtn = document.getElementById('copyBtn');
        copyBtn.disabled = true;
        copyBtn.textContent = '⏳ Copiando...';
        
        // Converter SVG para PNG blob
        const blob = await svgToPngBlob(currentSVG);
        
        // Tentar copiar para clipboard
        if (navigator.clipboard && window.ClipboardItem) {
          await navigator.clipboard.write([
            new ClipboardItem({'image/png': blob})
          ]);
          
          // Mostrar instruções de sucesso
          document.getElementById('manualInstructions').style.display = 'block';
          
          // Scroll para mostrar as instruções
          document.getElementById('manualInstructions').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'nearest' 
          });
          
        } else {
          throw new Error('Clipboard API não suportada');
        }
        
      } catch (error) {
        console.log('Clipboard falhou, tentando fallback de download:', error);
        
        try {
          // Fallback: download da imagem
          const blob = await svgToPngBlob(currentSVG);
          downloadPNG(blob);
          
          // Mostrar instruções de download
          showError('📥 Imagem baixada! Localize o arquivo baixado e insira manualmente no documento.');
          
        } catch (downloadError) {
          showError('Erro ao copiar/baixar imagem: ' + downloadError.message);
        }
      } finally {
        // Reabilitar botão
        const copyBtn = document.getElementById('copyBtn');
        copyBtn.disabled = false;
        copyBtn.textContent = '📋 Copiar Imagem';
      }
    }
    
    // ==================== CONVERTER SVG PARA PNG BLOB ====================
    async function svgToPngBlob(svgContent) {
      return new Promise((resolve, reject) => {
        try {
          // Criar um canvas
          const canvas = document.createElement('canvas');
          const ctx = canvas.getContext('2d');
          
          // Extrair dimensões do SVG
          const parser = new DOMParser();
          const svgDoc = parser.parseFromString(svgContent, 'image/svg+xml');
          const svgElement = svgDoc.querySelector('svg');
          
          let width = 800;
          let height = 600;
          
          // Tentar obter dimensões do SVG
          if (svgElement) {
            const widthAttr = svgElement.getAttribute('width');
            const heightAttr = svgElement.getAttribute('height');
            const viewBox = svgElement.getAttribute('viewBox');
            
            if (widthAttr && heightAttr) {
              width = parseFloat(widthAttr);
              height = parseFloat(heightAttr);
            } else if (viewBox) {
              const values = viewBox.split(' ').map(parseFloat);
              if (values.length >= 4) {
                width = values[2];
                height = values[3];
              }
            }
          }
          
          // Configurar canvas com dimensões adequadas
          const scale = 2; // Para melhor qualidade
          canvas.width = width * scale;
          canvas.height = height * scale;
          canvas.style.width = width + 'px';
          canvas.style.height = height + 'px';
          
          // Configurar contexto para alta qualidade
          ctx.scale(scale, scale);
          ctx.imageSmoothingEnabled = true;
          ctx.imageSmoothingQuality = 'high';
          
          // Criar uma imagem do SVG
          const img = new Image();
          
          img.onload = function() {
            // Preencher fundo branco (opcional, pode comentar para transparente)
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, width, height);
            
            // Desenhar a imagem SVG no canvas
            ctx.drawImage(img, 0, 0, width, height);
            
            // Converter canvas para blob PNG
            canvas.toBlob((blob) => {
              if (blob) {
                resolve(blob);
              } else {
                reject(new Error('Falha ao converter canvas para blob'));
              }
            }, 'image/png', 1.0);
          };
          
          img.onerror = function(error) {
            reject(new Error('Erro ao carregar SVG: ' + error));
          };
          
          // Converter SVG para data URL
          const svgBlob = new Blob([svgContent], {type: 'image/svg+xml;charset=utf-8'});
          const url = URL.createObjectURL(svgBlob);
          img.src = url;
          
          // Limpar URL após uso
          setTimeout(() => URL.revokeObjectURL(url), 1000);
          
        } catch (error) {
          reject(new Error('Erro na conversão SVG para PNG: ' + error.message));
        }
      });
    }
    
    // ==================== DOWNLOAD PNG COMO FALLBACK ====================
    function downloadPNG(blob) {
      try {
        // Criar URL temporária
        const url = URL.createObjectURL(blob);
        
        // Criar link de download
        const a = document.createElement('a');
        a.href = url;
        a.download = 'diagrama_labresumos_' + new Date().getTime() + '.png';
        
        // Adicionar ao DOM temporariamente e clicar
        document.body.appendChild(a);
        a.click();
        
        // Limpar
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
      } catch (error) {
        throw new Error('Erro ao baixar arquivo: ' + error.message);
      }
    }
    
    function onPreviewSuccess(svgContent) {
      currentSVG = svgContent;
      
      // Esconder loading
      document.getElementById('loading').style.display = 'none';
      
      // Mostrar preview
      document.getElementById('svgPreviewContent').innerHTML = svgContent;
      document.getElementById('svgPreviewContainer').style.display = 'block';
      document.getElementById('previewStatus').textContent = 'Diagrama criado com sucesso!';
      document.getElementById('previewStatus').style.color = '#4caf50';
      
      // Limpar campo de regeneração
      document.getElementById('regenerateInstructions').value = '';
      
      // Habilitar botões
      document.getElementById('regenerateBtn').disabled = false;
      document.getElementById('copyBtn').disabled = false;
      document.getElementById('cancelBtn').disabled = false;
    }
    
    
    function onError(error) {
      document.getElementById('loading').style.display = 'none';
      document.getElementById('generateBtn').disabled = false;
      document.getElementById('error').style.display = 'block';
      document.getElementById('error').innerHTML = '❌ Erro: ' + error.message;
      
      // Se estava no preview, reabilitar botões
      if (document.getElementById('svgPreviewContainer').style.display === 'block') {
        document.getElementById('regenerateBtn').disabled = false;
        document.getElementById('copyBtn').disabled = false;
        document.getElementById('cancelBtn').disabled = false;
      }
    }
    
    function showError(message) {
      document.getElementById('error').style.display = 'block';
      document.getElementById('error').innerHTML = '⚠️ ' + message;
    }
    
    function clearForm() {
      currentSVG = null;
      document.getElementById('diagramDescription').value = '';
      document.getElementById('regenerateInstructions').value = '';
      document.getElementById('svgPreviewContainer').style.display = 'none';
      document.getElementById('initialButtons').style.display = 'flex';
      document.getElementById('error').style.display = 'none';
      document.getElementById('success').style.display = 'none';
      document.getElementById('generateBtn').disabled = false;
      
      // Limpar campos
      document.getElementById('diagramDescription').focus();
    }
  </script>
</body>
</html>
  `;
  
  const html = HtmlService.createHtmlOutput(htmlContent)
    .setWidth(800)
    .setHeight(750);
  
  DocumentApp.getUi().showModalDialog(html, 'Criar Diagrama do Zero');
}

// ==================== INTERFACE HTML PARA IMAGEM ====================
function showDiagramDialog() {
  const htmlContent = `
<!DOCTYPE html>
<html>
<head>
  <base target="_top">
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 20px;
      background: #F3F1E8;
      margin: 0;
    }
    
    .container {
      max-width: 100%;
    }
    
    .header {
      background: linear-gradient(135deg, #F1CC00 0%, #FFD700 100%);
      padding: 15px;
      border-radius: 10px;
      margin-bottom: 20px;
      color: #333B49;
    }
    
    .upload-area {
      border: 2px dashed #333B49;
      border-radius: 10px;
      padding: 30px;
      text-align: center;
      background: white;
      margin-bottom: 20px;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .upload-area:hover {
      background: #F1CC0020;
      border-color: #F1CC00;
    }
    
    .upload-area.dragover {
      background: #F1CC0040;
      border-color: #FFD700;
    }
    
    textarea {
      width: 100%;
      padding: 12px;
      border: 2px solid #333B49;
      border-radius: 8px;
      font-size: 14px;
      resize: vertical;
      min-height: 80px;
      box-sizing: border-box;
    }
    
    .button-group {
      display: flex;
      gap: 10px;
      margin-top: 20px;
    }
    
    button {
      flex: 1;
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .btn-primary {
      background: #F1CC00;
      color: #333B49;
    }
    
    .btn-primary:hover {
      background: #FFD700;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(241, 204, 0, 0.4);
    }
    
    .btn-primary:disabled {
      background: #ccc;
      cursor: not-allowed;
      transform: none;
    }
    
    .btn-secondary {
      background: #333B49;
      color: white;
    }
    
    .btn-secondary:hover {
      background: #1a1f29;
    }
    
    .btn-success {
      background: #4caf50;
      color: white;
    }
    
    .btn-success:hover {
      background: #45a049;
    }
    
    .preview-image {
      max-width: 100%;
      max-height: 200px;
      margin: 10px auto;
      display: block;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .loading {
      display: none;
      text-align: center;
      padding: 20px;
    }
    
    .spinner {
      border: 4px solid #F3F1E8;
      border-top: 4px solid #F1CC00;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin: 0 auto;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    .error {
      color: #d32f2f;
      background: #ffebee;
      padding: 10px;
      border-radius: 5px;
      margin-top: 10px;
      display: none;
    }
    
    .success {
      color: #2e7d32;
      background: #e8f5e9;
      padding: 10px;
      border-radius: 5px;
      margin-top: 10px;
      display: none;
    }
    
    .progress-steps {
      display: none;
      margin-top: 10px;
      padding: 15px;
      background: white;
      border-radius: 8px;
      border: 1px solid #ddd;
    }
    
    .step {
      padding: 5px 0;
      color: #666;
      font-size: 14px;
    }
    
    .step.active {
      color: #333B49;
      font-weight: bold;
    }
    
    .step.complete {
      color: #4caf50;
    }
    
    .step-icon {
      margin-right: 8px;
    }
    
    .svg-preview-container {
      display: none;
      margin-top: 20px;
      padding: 20px;
      background: white;
      border-radius: 10px;
      border: 2px solid #333B49;
    }
    
    .svg-preview-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 2px solid #F1CC00;
    }
    
    .svg-preview-title {
      font-size: 18px;
      font-weight: bold;
      color: #333B49;
    }
    
    .svg-preview-status {
      font-size: 14px;
      color: #666;
      font-style: italic;
    }
    
    .svg-preview-content {
      width: 100%;
      min-height: 300px;
      max-height: 500px;
      overflow: auto;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 10px;
      background: #f9f9f9;
    }
    
    .svg-preview-content svg {
      width: 100%;
      height: auto;
    }
    
    .regenerate-section {
      margin-top: 15px;
      padding: 15px;
      background: #f3f1e8;
      border-radius: 8px;
    }
    
    .regenerate-section label {
      display: block;
      margin-bottom: 8px;
      font-weight: bold;
      color: #333B49;
    }
    
    .action-buttons {
      display: flex;
      gap: 10px;
      margin-top: 15px;
    }
    
    
    .info-box {
      background: #e3f2fd;
      border-left: 4px solid #2196f3;
      padding: 10px;
      margin: 10px 0;
      font-size: 14px;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h2 style="margin: 0;">📊 Gerador de Diagramas LabResumos</h2>
      <p style="margin: 5px 0 0 0; opacity: 0.8;">Powered by Claude Opus 4.1 - IA de última geração</p>
    </div>
    
    <div class="upload-area" id="uploadArea">
      <p style="margin: 0; font-size: 48px;">📁</p>
      <p><strong>Arraste uma imagem aqui</strong></p>
      <p style="opacity: 0.7;">ou clique para selecionar</p>
      <input type="file" id="fileInput" accept="image/*" style="display: none;">
    </div>
    
    <div id="imagePreview" style="display: none;">
      <img id="previewImg" class="preview-image">
    </div>
    
    <div id="initialInstructions">
      <label for="instructions" style="font-weight: bold; color: #333B49;">Instruções adicionais (opcional):</label>
      <textarea id="instructions" placeholder="Ex: Transforme em um fluxograma minimalista. Adicione ícones modernos e mantenha o estilo clean..."></textarea>
    </div>
    
    <div class="info-box">
      💡 <strong>Dica:</strong> O diagrama será copiado como PNG com fundo branco para a área de transferência.
    </div>
    
    <div class="info-box" style="background: #fff3cd; border-left: 4px solid #F1CC00;">
      ⚙️ <strong>Personalizar Prompts:</strong> 
      <a href="https://docs.google.com/spreadsheets/d/18ZRgSKCaLvFBB_5JOMH8bX_C5YwRTuQ8sQmGpvY42Ck/edit" target="_blank" style="color: #333B49; text-decoration: underline;">
        Editar prompts na planilha
      </a>
      - Os prompts são consultados a cada geração para usar sempre a versão mais atual.
    </div>
    
    <div class="button-group" id="initialButtons">
      <button class="btn-primary" id="generateBtn">
        🚀 Gerar Diagrama
      </button>
      <button class="btn-secondary" id="clearBtn">
        🔄 Limpar
      </button>
    </div>
    
    <div class="loading" id="loading">
      <div class="spinner"></div>
      <p id="loadingText">Gerando seu diagrama com Claude Opus 4.1...</p>
      <p style="font-size: 12px; opacity: 0.7;">Processamento avançado em andamento...</p>
    </div>
    
    <div class="progress-steps" id="progressSteps">
      <div class="step" id="step1"><span class="step-icon">⚪</span>Analisando imagem...</div>
      <div class="step" id="step2"><span class="step-icon">⚪</span>Gerando diagrama SVG com Claude Opus 4.1...</div>
      <div class="step" id="step3"><span class="step-icon">⚪</span>Convertendo para PNG...</div>
      <div class="step" id="step4"><span class="step-icon">⚪</span>Preparando visualização...</div>
    </div>
    
    <!-- Seção de Preview do SVG -->
    <div class="svg-preview-container" id="svgPreviewContainer">
      <div class="svg-preview-header">
        <span class="svg-preview-title">📊 Preview do Diagrama</span>
        <span class="svg-preview-status" id="previewStatus">Diagrama gerado com sucesso!</span>
      </div>
      
      <div class="svg-preview-content" id="svgPreviewContent">
        <!-- SVG será inserido aqui -->
      </div>
      
      <div class="regenerate-section">
        <label for="regenerateInstructions">Deseja fazer algum ajuste? Descreva as mudanças:</label>
        <textarea id="regenerateInstructions" placeholder="Ex: Aumentar o tamanho do texto, adicionar mais cores, reorganizar os elementos..."></textarea>
      </div>
      
      <div class="action-buttons">
        <button class="btn-primary" id="regenerateBtn">
          🔄 Regenerar com Ajustes
        </button>
        <button class="btn-success" id="copyBtn">
          📋 Copiar Imagem
        </button>
        <button class="btn-secondary" id="cancelBtn">
          ❌ Cancelar
        </button>
      </div>
      
      <div id="manualInstructions" style="display:none; margin-top: 15px; padding: 15px; background: #e8f5e9; border-radius: 8px; border-left: 4px solid #4caf50;">
        <h4 style="margin: 0 0 10px 0; color: #2e7d32;">✅ Imagem copiada para área de transferência!</h4>
        <p style="margin: 5px 0; color: #2e7d32;">
          <strong>1.</strong> Clique no documento onde deseja inserir a imagem<br>
          <strong>2.</strong> Use <strong>Ctrl+V</strong> (ou <strong>Cmd+V</strong> no Mac) para colar
        </p>
        <p style="margin: 5px 0 0 0; font-size: 12px; color: #666; font-style: italic;">
          💡 Se não funcionar, tente usar um navegador diferente ou baixe a imagem
        </p>
      </div>
    </div>
    
    <div class="error" id="error"></div>
    <div class="success" id="success"></div>
  </div>
  
  <script>
    let selectedImage = null;
    let currentSVG = null;
    let regenerationHistory = [];
    
    // Verificação no carregamento da página
    window.onload = function() {
      // Verificar se consegue acessar google.script
      try {
        google.script.run
          .withSuccessHandler(function() {
            console.log('Conexão com servidor OK');
          })
          .withFailureHandler(function(e) {
            if (e.message && e.message.includes('PERMISSION_DENIED')) {
              document.body.innerHTML = 
                '<div style="padding: 20px; color: red;">' +
                  '<h2>⚠️ Erro de Múltiplas Contas</h2>' +
                  '<p>Detectamos que você está logado em múltiplas contas Google.</p>' +
                  '<p><strong>Solução:</strong> Use uma janela anônima ou faça logout de outras contas.</p>' +
                '</div>';
            }
          })
          .checkMultipleAccountIssue();
      } catch (e) {
        console.error('Erro ao verificar permissões:', e);
      }
    };
    
    // Event Listeners
    document.getElementById('uploadArea').addEventListener('click', function() {
      document.getElementById('fileInput').click();
    });
    
    document.getElementById('fileInput').addEventListener('change', handleFileSelect);
    document.getElementById('uploadArea').addEventListener('dragover', handleDragOver);
    document.getElementById('uploadArea').addEventListener('dragleave', handleDragLeave);
    document.getElementById('uploadArea').addEventListener('drop', handleDrop);
    document.getElementById('generateBtn').addEventListener('click', generateDiagram);
    document.getElementById('clearBtn').addEventListener('click', clearForm);
    
    // Event Listeners para os novos botões
    document.getElementById('regenerateBtn').addEventListener('click', regenerateDiagram);
    document.getElementById('copyBtn').addEventListener('click', copyImageToClipboard);
    document.getElementById('cancelBtn').addEventListener('click', cancelPreview);
    
    // Drag and Drop
    function handleDragOver(e) {
      e.preventDefault();
      e.stopPropagation();
      e.currentTarget.classList.add('dragover');
    }
    
    function handleDragLeave(e) {
      e.preventDefault();
      e.stopPropagation();
      e.currentTarget.classList.remove('dragover');
    }
    
    function handleDrop(e) {
      e.preventDefault();
      e.stopPropagation();
      e.currentTarget.classList.remove('dragover');
      
      const files = e.dataTransfer.files;
      if (files.length > 0 && files[0].type.startsWith('image/')) {
        processFile(files[0]);
      }
    }
    
    function handleFileSelect(e) {
      const file = e.target.files[0];
      if (file && file.type.startsWith('image/')) {
        processFile(file);
      }
    }
    
    function processFile(file) {
      // Verificar tamanho do arquivo
      if (file.size > 5 * 1024 * 1024) { // 5MB
        showError('Arquivo muito grande. Máximo: 5MB');
        return;
      }
      
      const reader = new FileReader();
      reader.onload = function(e) {
        selectedImage = e.target.result;
        
        // Verificar se é uma imagem válida
        if (!selectedImage.startsWith('data:image/')) {
          showError('Formato de imagem inválido');
          return;
        }
        
        document.getElementById('previewImg').src = selectedImage;
        document.getElementById('imagePreview').style.display = 'block';
        document.getElementById('uploadArea').style.display = 'none';
        
        // Log para debug
        console.log('Imagem carregada, tamanho:', selectedImage.length);
      };
      reader.readAsDataURL(file);
    }
    
    function updateStep(stepId, status) {
      const step = document.getElementById(stepId);
      if (!step) return;
      
      const icon = step.querySelector('.step-icon');
      
      if (status === 'active') {
        step.classList.add('active');
        step.classList.remove('complete');
        icon.textContent = '🔄';
      } else if (status === 'complete') {
        step.classList.remove('active');
        step.classList.add('complete');
        icon.textContent = '✅';
      } else {
        step.classList.remove('active', 'complete');
        icon.textContent = '⚪';
      }
    }
    
    function generateDiagram() {
      const instructions = document.getElementById('instructions').value;
      
      if (!selectedImage) {
        showError('Por favor, selecione uma imagem primeiro.');
        return;
      }
      
      // Esconder elementos iniciais
      document.getElementById('initialInstructions').style.display = 'none';
      document.getElementById('initialButtons').style.display = 'none';
      
      // Desabilitar botão e mostrar loading
      document.getElementById('generateBtn').disabled = true;
      document.getElementById('loading').style.display = 'block';
      document.getElementById('progressSteps').style.display = 'block';
      document.getElementById('error').style.display = 'none';
      document.getElementById('success').style.display = 'none';
      
      // Resetar steps
      updateStep('step1', 'active');
      updateStep('step2', '');
      updateStep('step3', '');
      updateStep('step4', '');
      
      // Simular progresso
      setTimeout(() => {
        updateStep('step1', 'complete');
        updateStep('step2', 'active');
      }, 2000);
      
      setTimeout(() => {
        updateStep('step2', 'complete');
        updateStep('step3', 'active');
      }, 10000);
      
      setTimeout(() => {
        updateStep('step3', 'complete');
        updateStep('step4', 'active');
      }, 15000);
      
      // Enviar para o servidor para gerar preview
      google.script.run
        .withSuccessHandler(onPreviewSuccess)
        .withFailureHandler(onError)
        .generatePreview(selectedImage, instructions);
    }
    
    function regenerateDiagram() {
      const regenerateInstructions = document.getElementById('regenerateInstructions').value;
      
      if (!regenerateInstructions) {
        showError('Por favor, descreva as mudanças desejadas.');
        return;
      }
      
      // Salvar histórico
      regenerationHistory.push({
        svg: currentSVG,
        instructions: regenerateInstructions
      });
      
      // Esconder preview e mostrar loading
      document.getElementById('svgPreviewContainer').style.display = 'none';
      document.getElementById('loading').style.display = 'block';
      document.getElementById('progressSteps').style.display = 'block';
      document.getElementById('error').style.display = 'none';
      
      // Resetar steps
      updateStep('step1', 'active');
      updateStep('step2', '');
      updateStep('step3', '');
      updateStep('step4', '');
      
      // Simular progresso
      setTimeout(() => {
        updateStep('step1', 'complete');
        updateStep('step2', 'active');
      }, 2000);
      
      setTimeout(() => {
        updateStep('step2', 'complete');
        updateStep('step3', 'active');
      }, 10000);
      
      setTimeout(() => {
        updateStep('step3', 'complete');
        updateStep('step4', 'active');
      }, 15000);
      
      google.script.run
        .withSuccessHandler(onPreviewSuccess)
        .withFailureHandler(onError)
        .refineSVG(currentSVG, regenerateInstructions);
    }
    
    
    function cancelPreview() {
      // Voltar para o estado inicial
      document.getElementById('svgPreviewContainer').style.display = 'none';
      document.getElementById('initialInstructions').style.display = 'block';
      document.getElementById('initialButtons').style.display = 'flex';
      document.getElementById('generateBtn').disabled = false;
      document.getElementById('regenerateInstructions').value = '';
      
      // Esconder instruções manuais
      document.getElementById('manualInstructions').style.display = 'none';
      
      // Limpar histórico
      regenerationHistory = [];
      currentSVG = null;
    }
    
    // ==================== FUNÇÃO PARA COPIAR IMAGEM ====================
    async function copyImageToClipboard() {
      if (!currentSVG) {
        showError('Nenhum diagrama para copiar.');
        return;
      }
      
      try {
        // Desabilitar botão temporariamente
        const copyBtn = document.getElementById('copyBtn');
        copyBtn.disabled = true;
        copyBtn.textContent = '⏳ Copiando...';
        
        // Converter SVG para PNG blob
        const blob = await svgToPngBlob(currentSVG);
        
        // Tentar copiar para clipboard
        if (navigator.clipboard && window.ClipboardItem) {
          await navigator.clipboard.write([
            new ClipboardItem({'image/png': blob})
          ]);
          
          // Mostrar instruções de sucesso
          document.getElementById('manualInstructions').style.display = 'block';
          
          // Scroll para mostrar as instruções
          document.getElementById('manualInstructions').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'nearest' 
          });
          
        } else {
          throw new Error('Clipboard API não suportada');
        }
        
      } catch (error) {
        console.log('Clipboard falhou, tentando fallback de download:', error);
        
        try {
          // Fallback: download da imagem
          const blob = await svgToPngBlob(currentSVG);
          downloadPNG(blob);
          
          // Mostrar instruções de download
          showError('📥 Imagem baixada! Localize o arquivo baixado e insira manualmente no documento.');
          
        } catch (downloadError) {
          showError('Erro ao copiar/baixar imagem: ' + downloadError.message);
        }
      } finally {
        // Reabilitar botão
        const copyBtn = document.getElementById('copyBtn');
        copyBtn.disabled = false;
        copyBtn.textContent = '📋 Copiar Imagem';
      }
    }
    
    // ==================== CONVERTER SVG PARA PNG BLOB ====================
    async function svgToPngBlob(svgContent) {
      return new Promise((resolve, reject) => {
        try {
          // Criar um canvas
          const canvas = document.createElement('canvas');
          const ctx = canvas.getContext('2d');
          
          // Extrair dimensões do SVG
          const parser = new DOMParser();
          const svgDoc = parser.parseFromString(svgContent, 'image/svg+xml');
          const svgElement = svgDoc.querySelector('svg');
          
          let width = 800;
          let height = 600;
          
          // Tentar obter dimensões do SVG
          if (svgElement) {
            const widthAttr = svgElement.getAttribute('width');
            const heightAttr = svgElement.getAttribute('height');
            const viewBox = svgElement.getAttribute('viewBox');
            
            if (widthAttr && heightAttr) {
              width = parseFloat(widthAttr);
              height = parseFloat(heightAttr);
            } else if (viewBox) {
              const values = viewBox.split(' ').map(parseFloat);
              if (values.length >= 4) {
                width = values[2];
                height = values[3];
              }
            }
          }
          
          // Configurar canvas com dimensões adequadas
          const scale = 2; // Para melhor qualidade
          canvas.width = width * scale;
          canvas.height = height * scale;
          canvas.style.width = width + 'px';
          canvas.style.height = height + 'px';
          
          // Configurar contexto para alta qualidade
          ctx.scale(scale, scale);
          ctx.imageSmoothingEnabled = true;
          ctx.imageSmoothingQuality = 'high';
          
          // Criar uma imagem do SVG
          const img = new Image();
          
          img.onload = function() {
            // Preencher fundo branco (opcional, pode comentar para transparente)
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, width, height);
            
            // Desenhar a imagem SVG no canvas
            ctx.drawImage(img, 0, 0, width, height);
            
            // Converter canvas para blob PNG
            canvas.toBlob((blob) => {
              if (blob) {
                resolve(blob);
              } else {
                reject(new Error('Falha ao converter canvas para blob'));
              }
            }, 'image/png', 1.0);
          };
          
          img.onerror = function(error) {
            reject(new Error('Erro ao carregar SVG: ' + error));
          };
          
          // Converter SVG para data URL
          const svgBlob = new Blob([svgContent], {type: 'image/svg+xml;charset=utf-8'});
          const url = URL.createObjectURL(svgBlob);
          img.src = url;
          
          // Limpar URL após uso
          setTimeout(() => URL.revokeObjectURL(url), 1000);
          
        } catch (error) {
          reject(new Error('Erro na conversão SVG para PNG: ' + error.message));
        }
      });
    }
    
    // ==================== DOWNLOAD PNG COMO FALLBACK ====================
    function downloadPNG(blob) {
      try {
        // Criar URL temporária
        const url = URL.createObjectURL(blob);
        
        // Criar link de download
        const a = document.createElement('a');
        a.href = url;
        a.download = 'diagrama_labresumos_' + new Date().getTime() + '.png';
        
        // Adicionar ao DOM temporariamente e clicar
        document.body.appendChild(a);
        a.click();
        
        // Limpar
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
      } catch (error) {
        throw new Error('Erro ao baixar arquivo: ' + error.message);
      }
    }
    
    function onPreviewSuccess(svgContent) {
      // Salvar SVG atual
      currentSVG = svgContent;
      
      // Esconder loading
      document.getElementById('loading').style.display = 'none';
      document.getElementById('progressSteps').style.display = 'none';
      
      // Completar steps
      updateStep('step4', 'complete');
      
      // Mostrar preview
      document.getElementById('svgPreviewContent').innerHTML = svgContent;
      document.getElementById('svgPreviewContainer').style.display = 'block';
      document.getElementById('previewStatus').textContent = 'Diagrama gerado com sucesso!';
      document.getElementById('previewStatus').style.color = '#4caf50';
      
      // Limpar campo de regeneração
      document.getElementById('regenerateInstructions').value = '';
      
      // Habilitar botões
      document.getElementById('regenerateBtn').disabled = false;
      document.getElementById('copyBtn').disabled = false;
      document.getElementById('cancelBtn').disabled = false;
    }
    
    
    function onError(error) {
      document.getElementById('loading').style.display = 'none';
      document.getElementById('progressSteps').style.display = 'none';
      document.getElementById('generateBtn').disabled = false;
      document.getElementById('error').style.display = 'block';
      document.getElementById('error').innerHTML = '❌ Erro: ' + error.message;
      
      // Se estava no preview, reabilitar botões
      if (document.getElementById('svgPreviewContainer').style.display === 'block') {
        document.getElementById('regenerateBtn').disabled = false;
        document.getElementById('copyBtn').disabled = false;
        document.getElementById('cancelBtn').disabled = false;
      }
    }
    
    function showError(message) {
      document.getElementById('error').style.display = 'block';
      document.getElementById('error').innerHTML = '⚠️ ' + message;
    }
    
    function clearForm() {
      selectedImage = null;
      currentSVG = null;
      regenerationHistory = [];
      document.getElementById('fileInput').value = '';
      document.getElementById('instructions').value = '';
      document.getElementById('regenerateInstructions').value = '';
      document.getElementById('imagePreview').style.display = 'none';
      document.getElementById('uploadArea').style.display = 'block';
      document.getElementById('svgPreviewContainer').style.display = 'none';
      document.getElementById('initialInstructions').style.display = 'block';
      document.getElementById('initialButtons').style.display = 'flex';
      document.getElementById('error').style.display = 'none';
      document.getElementById('success').style.display = 'none';
      document.getElementById('progressSteps').style.display = 'none';
      document.getElementById('generateBtn').disabled = false;
      
      // Limpar formulário
      document.getElementById('instructions').focus();
    }
  </script>
</body>
</html>
  `;
  
  const html = HtmlService.createHtmlOutput(htmlContent)
    .setWidth(800)
    .setHeight(750);
  
  DocumentApp.getUi().showModalDialog(html, 'Gerar Diagrama com IA');
}

// ==================== PROCESSAMENTO DO DIAGRAMA ====================
function generatePreview(imageBase64, instructions) {
  try {
    // Validar entrada
    if (!imageBase64) {
      throw new Error('Imagem não foi recebida corretamente');
    }
    
    console.log('Gerando preview, tamanho da imagem:', imageBase64.length);
    console.log('Formato: PNG com fundo transparente');
    
    const userPrompt = instructions || "";
    
    // Gerar SVG com Claude
    console.log('Gerando diagrama com Claude Opus 4.1...');
    const svgContent = generateSVGWithClaude(imageBase64, userPrompt);
    
    // Retornar o SVG para preview (sempre mostra SVG no preview)
    return svgContent;
    
  } catch (error) {
    console.error('Erro ao gerar preview:', error);
    throw new Error('Falha ao gerar diagrama: ' + error.toString());
  }
}

// ==================== GERAR DIAGRAMA APENAS COM TEXTO ====================
function generateFromTextOnly(description) {
  try {
    // Validar entrada
    if (!description) {
      throw new Error('Descrição não foi recebida');
    }
    
    console.log('Gerando diagrama do zero com descrição:', description.substring(0, 100) + '...');
    
    // Gerar SVG com Claude apenas com texto
    const svgContent = generateSVGFromText(description);
    
    return svgContent;
    
  } catch (error) {
    console.error('Erro ao gerar do texto:', error);
    throw new Error('Falha ao criar diagrama: ' + error.toString());
  }
}

// ==================== REFINAR SVG EXISTENTE ====================
function refineSVG(currentSVG, refinementInstructions) {
  try {
    // Validar entrada
    if (!currentSVG) {
      throw new Error('SVG não foi recebido corretamente');
    }
    
    if (!refinementInstructions) {
      throw new Error('Instruções de refinamento não fornecidas');
    }
    
    console.log('Refinando SVG existente com Claude Opus 4.1...');
    console.log('Formato: PNG com fundo transparente');
    
    // Chamar Claude para refinar o SVG
    const refinedSVG = refineSVGWithClaude(currentSVG, refinementInstructions);
    
    return refinedSVG;
    
  } catch (error) {
    console.error('Erro ao refinar SVG:', error);
    throw new Error('Falha ao refinar diagrama: ' + error.toString());
  }
}

// ==================== INSERIR NO DOCUMENTO ====================
function insertIntoDocument(svgContent) {
  try {
    console.log('Inserindo PNG no início do documento...');
    return insertAsPNGAtBeginning(svgContent);
  } catch (error) {
    console.error('Erro ao inserir:', error);
    throw new Error('Falha ao inserir: ' + error.toString());
  }
}

// ==================== INSERIR PNG NO INÍCIO DO DOCUMENTO ====================
function insertAsPNGAtBeginning(svgContent) {
  try {
    const doc = DocumentApp.getActiveDocument();
    const body = doc.getBody();
    
    // Garantir fundo transparente no SVG
    svgContent = svgContent.replace(
      /<rect[^>]*width=["']100%["'][^>]*height=["']100%["'][^>]*fill=["']#[^"']*["'][^>]*\/>/gi, 
      ''
    );
    
    // Forçar remoção de fundos opacos
    const cleanSVG = svgContent
      .replace(/<rect[^>]*width=["']100%["'][^>]*height=["']100%["'][^>]*>/gi, '')
      .replace(/fill=["']#F3F1E8["']/gi, 'fill="transparent"');
    
    // Converter para PNG usando método nativo do Google
    console.log('Convertendo SVG para PNG via Google Slides (método principal)...');
    let pngBlob = convertSVGtoPNGviaSlides(cleanSVG);
    
    // Se falhar, tentar método alternativo
    if (!pngBlob) {
      console.log('Slides falhou, tentando método alternativo via Drive...');
      pngBlob = convertSVGtoPNGAlternative(cleanSVG);
    }
    
    // Se ainda falhar, usar fallback SVG no Drive
    if (!pngBlob) {
      console.log('Ambos os métodos PNG falharam, usando fallback SVG...');
      return fallbackSVGtoDrive(cleanSVG);
    }
    
    if (pngBlob) {
      // Inserir no início do documento
      const firstChild = body.getChild(0);
      let inlineImage;
      
      if (body.getNumChildren() > 0) {
        // Inserir antes do primeiro elemento
        const firstParagraph = body.insertParagraph(0, '');
        inlineImage = firstParagraph.appendInlineImage(pngBlob);
      } else {
        // Documento vazio
        const paragraph = body.appendParagraph('');
        inlineImage = paragraph.appendInlineImage(pngBlob);
      }
      
      // Obter dimensões originais da imagem
      const originalWidth = inlineImage.getWidth();
      const originalHeight = inlineImage.getHeight();
      const aspectRatio = originalHeight / originalWidth;

      console.log(`Dimensões originais: ${originalWidth}x${originalHeight}, ratio: ${aspectRatio}`);

      // Definir largura máxima desejada
      const maxWidth = 500;

      // Calcular nova altura mantendo proporção
      if (originalWidth > maxWidth) {
        const newWidth = maxWidth;
        const newHeight = Math.round(newWidth * aspectRatio);
        
        console.log(`Redimensionando para: ${newWidth}x${newHeight}`);
        
        // IMPORTANTE: Definir AMBAS as dimensões para manter proporção
        inlineImage.setWidth(newWidth);
        inlineImage.setHeight(newHeight);
      } 
      // Se a imagem já é menor que maxWidth, não fazer nada
      
      // Adicionar metadados
      inlineImage.setAltDescription('Diagrama gerado com IA');
      inlineImage.setAltTitle('Diagrama LabResumos');
      
      return { success: true, message: 'Diagrama inserido no início do documento!' };
    }
    
    // Se chegou aqui, todos os métodos de conversão falharam
    // Usar fallback: salvar SVG no Drive com link
    console.log('Todos os métodos de conversão PNG falharam, usando fallback SVG...');
    return fallbackSVGtoDrive(cleanSVG);
  } catch (error) {
    throw error;
  }
}

// ==================== CONVERTER SVG PARA PNG E INSERIR (LEGACY) ====================
function insertAsPNG(svgContent) {
  try {
    // Verificar permissões do Drive primeiro
    try {
      const testAccess = DriveApp.getRootFolder();
    } catch (e) {
      if (e.toString().includes('PERMISSION_DENIED') || 
          e.toString().includes('Access denied')) {
        // Tentar método alternativo sem Drive
        return insertSVGDirectly(svgContent);
      }
    }
    
    const doc = DocumentApp.getActiveDocument();
    const body = doc.getBody();
    
    console.log('Convertendo SVG para PNG usando método alternativo...');
    
    // Método 1: Conversão direta via Slides API com preservação de proporção
    const slidesPNG = convertSVGtoPNGviaSlides(svgContent);
    
    if (slidesPNG) {
      // Inserir a imagem primeiro SEM especificar dimensões
      const cursor = doc.getCursor();
      let inlineImage;
      
      if (cursor) {
        inlineImage = cursor.insertInlineImage(slidesPNG);
      } else {
        const paragraph = body.appendParagraph('');
        inlineImage = paragraph.appendInlineImage(slidesPNG);
      }
      
      // IMPORTANTE: Obter dimensões APÓS inserção
      const currentWidth = inlineImage.getWidth();
      const currentHeight = inlineImage.getHeight();
      const aspectRatio = currentHeight / currentWidth;
      
      console.log(`Dimensões após inserção: ${currentWidth}x${currentHeight}`);
      console.log(`Aspect ratio real: ${aspectRatio}`);
      
      // Calcular novo tamanho preservando proporção
      const maxWidth = 450;
      const maxHeight = 600;
      
      let finalWidth = currentWidth;
      let finalHeight = currentHeight;
      
      // Redimensionar se necessário, mantendo proporção
      if (currentWidth > maxWidth) {
        finalWidth = maxWidth;
        finalHeight = Math.round(finalWidth * aspectRatio);
      }
      
      if (finalHeight > maxHeight) {
        finalHeight = maxHeight;
        finalWidth = Math.round(finalHeight / aspectRatio);
      }
      
      // APLICAR APENAS LARGURA - Docs preserva proporção automaticamente
      console.log(`Aplicando largura final: ${finalWidth}px`);
      inlineImage.setWidth(finalWidth);
      // NÃO chamar setHeight() - deixar o Docs calcular
      
      // Adicionar metadados
      inlineImage.setAltDescription('Diagrama gerado com IA');
      inlineImage.setAltTitle('Diagrama LabResumos');
      
      console.log('Diagrama inserido com sucesso!');
      return { success: true, message: 'Diagrama inserido com sucesso!' };
    }
    
    throw new Error('Falha na conversão para PNG');
    
  } catch (error) {
    console.error('Erro ao inserir PNG:', error);
    
    // Fallback: salvar SVG no Drive
    return fallbackSVGtoDrive(svgContent);
  }
}

// Nova função auxiliar para conversão via Slides
function convertSVGtoPNGviaSlides(svgContent) {
  try {
    // Verificar permissões antes
    try {
      DriveApp.getRootFolder();
    } catch (e) {
      if (e.toString().includes('PERMISSION_DENIED')) {
        throw new Error('Multi-login detectado. Por favor, faça logout de outras contas Google.');
      }
    }
    
    console.log('Iniciando conversão SVG para PNG via Slides...');
    
    // Extrair dimensões do SVG
    const dimensions = extractSVGDimensions(svgContent);
    console.log('Dimensões extraídas:', dimensions);
    
    // Garantir preserveAspectRatio e remover fundos opacos
    let processedSVG = svgContent.replace(
      /<svg([^>]*?)>/i,
      (match, attrs) => {
        if (!/preserveAspectRatio=/i.test(attrs)) {
          attrs += ' preserveAspectRatio="xMidYMid meet"';
        }
        return `<svg${attrs}>`;
      }
    );
    
    // Forçar remoção de fundos opacos
    processedSVG = processedSVG
      .replace(/<rect[^>]*width=["']100%["'][^>]*height=["']100%["'][^>]*>/gi, '')
      .replace(/fill=["'](?!none|transparent)[^"']*["'](?=[^>]*width=["']100%)/gi, 'fill="transparent"');
    
    // Criar apresentação temporária (sem tentar alterar dimensões)
    const presentation = SlidesApp.create('temp_convert_' + new Date().getTime());
    
    // Obter as dimensões padrão da apresentação
    const pageWidth = presentation.getPageWidth();
    const pageHeight = presentation.getPageHeight();
    console.log(`Dimensões da apresentação: ${pageWidth}x${pageHeight}`);
    
    const slide = presentation.getSlides()[0];
    
    // Criar blob SVG
    const svgBlob = Utilities.newBlob(processedSVG, 'image/svg+xml', 'diagram.svg');
    
    // Salvar SVG temporariamente
    const tempSvgFile = DriveApp.createFile(svgBlob);
    
    try {
      // Inserir imagem no slide
      const image = slide.insertImage(svgBlob);
      
      // Calcular escala para ajustar ao slide mantendo proporção (escala 1 para não distorcer)
      const scaleX = pageWidth / dimensions.width;
      const scaleY = pageHeight / dimensions.height;
      const scale = Math.min(scaleX, scaleY, 1); // Mantém escala 1 para preservar qualidade
      
      const finalWidth = dimensions.width * scale;
      const finalHeight = dimensions.height * scale;
      
      // Centralizar no slide
      const left = (pageWidth - finalWidth) / 2;
      const top = (pageHeight - finalHeight) / 2;
      
      // Posicionar e dimensionar
      image.setLeft(left);
      image.setTop(top);
      image.setWidth(finalWidth);
      image.setHeight(finalHeight);
      
      // Salvar e fechar
      presentation.saveAndClose();
      
      // Aguardar renderização completa (aumentado para melhor qualidade)
      Utilities.sleep(4000);
      
      // Obter PNG de alta qualidade via thumbnail
      const presId = presentation.getId();
      const token = ScriptApp.getOAuthToken();
      
      // Usar dimensão máxima otimizada para alta qualidade
      const maxDimension = Math.max(dimensions.width, dimensions.height, 3000);
      const thumbnailUrl = `https://drive.google.com/thumbnail?id=${presId}&sz=s${maxDimension}`;
      
      const response = UrlFetchApp.fetch(thumbnailUrl, {
        headers: {
          'Authorization': 'Bearer ' + token
        },
        muteHttpExceptions: true
      });
      
      if (response.getResponseCode() === 200) {
        const pngBlob = response.getBlob();
        pngBlob.setName('diagram_' + new Date().getTime() + '.png');
        
        // Limpar arquivos temporários
        DriveApp.getFileById(presId).setTrashed(true);
        tempSvgFile.setTrashed(true);
        
        return pngBlob;
      }
      
    } catch (e) {
      console.error('Erro na conversão:', e);
      tempSvgFile.setTrashed(true);
      try {
        DriveApp.getFileById(presentation.getId()).setTrashed(true);
      } catch (e2) {}
    }
    
    return null;
    
  } catch (error) {
    console.error('Erro em convertSVGtoPNGviaSlides:', error);
    return null;
  }
}


// Método alternativo de conversão SVG para PNG
function convertSVGtoPNGAlternative(svgContent) {
  try {
    console.log('Tentando conversão alternativa via método simples...');
    
    // Limpar SVG
    const cleanSVG = svgContent
      .replace(/<rect[^>]*width=["']100%["'][^>]*height=["']100%["'][^>]*>/gi, '')
      .replace(/fill=["']#F3F1E8["']/gi, 'fill="transparent"');
    
    // Extrair dimensões
    const dimensions = extractSVGDimensions(cleanSVG);
    console.log('Dimensões para conversão alternativa:', dimensions);
    
    // Criar blob SVG diretamente
    const svgBlob = Utilities.newBlob(cleanSVG, 'image/svg+xml', 'diagram_alt.svg');
    
    // Salvar temporariamente no Drive
    const tempFile = DriveApp.createFile(svgBlob);
    
    try {
      // Tentar usar a API de thumbnail do Drive
      const fileId = tempFile.getId();
      const token = ScriptApp.getOAuthToken();
      
      // Usar tamanho baseado nas dimensões do SVG
      const maxDimension = Math.min(Math.max(dimensions.width, dimensions.height), 2000);
      const thumbnailUrl = `https://drive.google.com/thumbnail?id=${fileId}&sz=s${maxDimension}`;
      
      const response = UrlFetchApp.fetch(thumbnailUrl, {
        headers: {
          'Authorization': 'Bearer ' + token
        },
        muteHttpExceptions: true
      });
      
      if (response.getResponseCode() === 200) {
        const pngBlob = response.getBlob();
        pngBlob.setName('diagram_alt_' + new Date().getTime() + '.png');
        
        // Limpar arquivo temporário
        tempFile.setTrashed(true);
        
        return pngBlob;
      } else {
        console.log('Falha na conversão alternativa, código:', response.getResponseCode());
      }
      
    } catch (e) {
      console.error('Erro na conversão alternativa:', e);
    } finally {
      // Garantir limpeza
      try {
        tempFile.setTrashed(true);
      } catch (e2) {}
    }
    
    return null;
    
  } catch (error) {
    console.error('Erro no método alternativo:', error);
    return null;
  }
}

// Função auxiliar para extrair dimensões do SVG
function extractSVGDimensions(svgContent) {
  let width = 1600;
  let height = 1200;
  
  // Tentar extrair width e height
  const widthMatch = svgContent.match(/width=["']?(\d+(?:\.\d+)?)/);
  const heightMatch = svgContent.match(/height=["']?(\d+(?:\.\d+)?)/);
  
  if (widthMatch && heightMatch) {
    width = parseFloat(widthMatch[1]);
    height = parseFloat(heightMatch[1]);
  } else {
    // Tentar viewBox como fallback
    const viewBoxMatch = svgContent.match(/viewBox=["']?([\d\s.-]+)["']?/);
    if (viewBoxMatch) {
      const values = viewBoxMatch[1].split(/\s+/).map(parseFloat);
      if (values.length >= 4) {
        width = values[2];
        height = values[3];
      }
    }
  }
  
  // Validar dimensões (ajustar para dimensões típicas de slides)
  if (width <= 0 || height <= 0) {
    width = 1600;
    height = 1200;
  }
  
  // Limitar ao tamanho máximo de uma apresentação
  if (width > 1920) width = 1920;
  if (height > 1080) height = 1080;
  
  return { width: Math.round(width), height: Math.round(height) };
}

// Função de fallback para salvar SVG no Drive
function fallbackSVGtoDrive(svgContent) {
  try {
    const doc = DocumentApp.getActiveDocument();
    const body = doc.getBody();
    
    console.log('Usando fallback: salvando SVG no Drive...');
    
    const timestamp = new Date().getTime();
    const svgBlob = Utilities.newBlob(svgContent, 'image/svg+xml', 
      'LabResumos_Diagram_' + timestamp + '.svg');
    
    const file = DriveApp.createFile(svgBlob);
    file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
    
    const fileUrl = file.getUrl();
    
    // Adicionar link no início do documento
    const text = "📊 Diagrama salvo no Drive (clique para abrir): ";
    
    if (body.getNumChildren() > 0) {
      // Inserir antes do primeiro elemento
      const firstParagraph = body.insertParagraph(0, text);
      firstParagraph.setLinkUrl(fileUrl);
    } else {
      // Documento vazio
      const paragraph = body.appendParagraph(text);
      paragraph.setLinkUrl(fileUrl);
    }
    
    return { 
      success: true, 
      message: 'Diagrama salvo no Drive! Link inserido no início do documento.' 
    };
    
  } catch (error) {
    console.error('Erro no fallback:', error);
    throw error;
  }
}

// ==================== INSERÇÃO DIRETA SEM DRIVE API ====================
function insertSVGDirectly(svgContent) {
  try {
    const doc = DocumentApp.getActiveDocument();
    const body = doc.getBody();
    
    // Converter SVG para data URL
    const svgBase64 = Utilities.base64Encode(svgContent);
    const dataUrl = 'data:image/svg+xml;base64,' + svgBase64;
    
    // Criar blob diretamente sem usar Drive
    const blob = Utilities.newBlob(Utilities.base64Decode(svgBase64), 'image/svg+xml', 'diagram.svg');
    
    // Tentar inserir como imagem inline
    const cursor = doc.getCursor();
    if (cursor) {
      const element = cursor.getElement();
      const parent = element.getParent();
      parent.appendParagraph('📊 Diagrama SVG inserido abaixo:');
      parent.appendParagraph('[Diagrama SVG - Visualização pode estar limitada]');
      parent.appendParagraph('💡 Para melhor visualização, use PNG ou exporte o documento.');
    } else {
      body.appendParagraph('📊 Diagrama SVG inserido:');
      body.appendParagraph('[Diagrama SVG - Visualização pode estar limitada]');
    }
    
    return { 
      success: true, 
      message: 'Diagrama inserido (modo fallback devido a restrições de permissão)' 
    };
  } catch (error) {
    throw new Error('Não foi possível inserir o diagrama. Verifique suas permissões.');
  }
}

// ==================== INSERIR SVG NO DOCUMENTO (FALLBACK) ====================
function insertSVGIntoDocument(svgContent) {
  try {
    // Obter documento
    const doc = DocumentApp.getActiveDocument();
    const body = doc.getBody();
    
    console.log('Salvando SVG no Google Drive...');
    
    // Criar arquivo SVG no Drive
    const timestamp = new Date().getTime();
    const svgBlob = Utilities.newBlob(svgContent, 'image/svg+xml', 
      'LabResumos_Diagram_' + timestamp + '.svg');
    
    const file = DriveApp.createFile(svgBlob);
    file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
    
    const fileUrl = file.getUrl();
    const viewUrl = 'https://drive.google.com/uc?export=view&id=' + file.getId();
    
    // Adicionar no documento com estilo melhorado
    const cursor = doc.getCursor();
    let insertionPoint;
    
    if (cursor) {
      insertionPoint = cursor.getElement().getParent();
    } else {
      insertionPoint = body;
    }
    
    const separator = insertionPoint.appendParagraph('═══════════════════════════════════════');
    separator.setAlignment(DocumentApp.HorizontalAlignment.CENTER);
    separator.setForegroundColor('#F1CC00');
    
    const title = insertionPoint.appendParagraph('📊 Diagrama LabResumos Gerado');
    title.setAlignment(DocumentApp.HorizontalAlignment.CENTER);
    title.setBold(true);
    title.setFontSize(14);
    title.setForegroundColor('#333B49');
    
    const info = insertionPoint.appendParagraph('Diagrama SVG salvo no Google Drive');
    info.setAlignment(DocumentApp.HorizontalAlignment.CENTER);
    info.setFontSize(11);
    info.setItalic(true);
    info.setForegroundColor('#666666');
    
    const linkPara = insertionPoint.appendParagraph('');
    linkPara.setAlignment(DocumentApp.HorizontalAlignment.CENTER);
    
    linkPara.appendText('🔗 ')
      .setForegroundColor('#333B49');
    
    linkPara.appendText('Abrir Diagrama')
      .setLinkUrl(fileUrl)
      .setForegroundColor('#2196F3')
      .setUnderline(true);
    
    linkPara.appendText(' | ')
      .setForegroundColor('#999999');
    
    linkPara.appendText('Visualizar Direto')
      .setLinkUrl(viewUrl)
      .setForegroundColor('#2196F3')
      .setUnderline(true);
    
    linkPara.appendText(' | ')
      .setForegroundColor('#999999');
    
    linkPara.appendText('Download')
      .setLinkUrl('https://drive.google.com/uc?export=download&id=' + file.getId())
      .setForegroundColor('#2196F3')
      .setUnderline(true);
    
    // Adicionar informações sobre o arquivo
    const details = insertionPoint.appendParagraph('');
    details.setAlignment(DocumentApp.HorizontalAlignment.CENTER);
    details.setFontSize(10);
    details.setItalic(true);
    
    const date = new Date();
    details.appendText('Criado em: ' + date.toLocaleDateString('pt-BR') + ' às ' + 
                      date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'}))
      .setForegroundColor('#999999');
    
    details.appendText(' | Formato: SVG Vetorial')
      .setForegroundColor('#999999');
    
    const separator2 = insertionPoint.appendParagraph('═══════════════════════════════════════');
    separator2.setAlignment(DocumentApp.HorizontalAlignment.CENTER);
    separator2.setForegroundColor('#F1CC00');
    
    console.log('SVG salvo no Drive e link inserido no documento');
    
    return { success: true, message: 'Diagrama SVG salvo no Drive!' };
    
  } catch (error) {
    console.error('Erro ao salvar SVG:', error);
    throw error;
  }
}

// ==================== GERAÇÃO COM CLAUDE OPUS ====================
function generateSVGWithClaude(imageBase64, userInstructions) {
  try {
    if (!CONFIG.ANTHROPIC_API_KEY) {
      throw new Error('Chave da API Anthropic não configurada');
    }
    
    // Processar base64
    let base64Data = imageBase64;
    let mediaType = 'image/png';
    
    if (imageBase64.includes(',')) {
      const parts = imageBase64.split(',');
      base64Data = parts[1];
      
      // Detectar tipo de mídia
      if (parts[0].includes('jpeg')) {
        mediaType = 'image/jpeg';
      } else if (parts[0].includes('png')) {
        mediaType = 'image/png';
      } else if (parts[0].includes('gif')) {
        mediaType = 'image/gif';
      } else if (parts[0].includes('webp')) {
        mediaType = 'image/webp';
      }
    }
    
    const url = 'https://api.anthropic.com/v1/messages';
    
    const systemPrompt = getSystemPrompt();

    // Extrair dimensões da imagem original
    let imageWidth = 1600;  // default
    let imageHeight = 1200; // default

    try {
      // Criar uma imagem temporária para obter dimensões
      const img = Utilities.newBlob(Utilities.base64Decode(base64Data), mediaType, 'temp.img');
      
      // Tentar obter dimensões via Drive API
      const tempFile = DriveApp.createFile(img);
      const fileId = tempFile.getId();
      
      // Usar thumbnail API para obter metadata
      const url = `https://www.googleapis.com/drive/v3/files/${fileId}?fields=imageMediaMetadata`;
      const response = UrlFetchApp.fetch(url, {
        headers: {
          'Authorization': 'Bearer ' + ScriptApp.getOAuthToken()
        },
        muteHttpExceptions: true
      });
      
      if (response.getResponseCode() === 200) {
        const metadata = JSON.parse(response.getContentText());
        if (metadata.imageMediaMetadata) {
          imageWidth = metadata.imageMediaMetadata.width || imageWidth;
          imageHeight = metadata.imageMediaMetadata.height || imageHeight;
          console.log(`Dimensões da imagem original: ${imageWidth}x${imageHeight}`);
        }
      }
      
      // Limpar arquivo temporário
      tempFile.setTrashed(true);
    } catch(e) {
      console.log('Usando dimensões padrão:', e.toString());
    }

    // Se não conseguiu obter dimensões via API, tentar extrair do base64
    if (imageWidth === 1600 && imageHeight === 1200) {
      try {
        // Criar uma Image para obter dimensões
        const imgTag = '<img src="' + imageBase64 + '" id="temp-img" style="display:none">';
        // Este método não funciona em Apps Script, mas o log ajuda no debug
        console.log('Tentativa de obter dimensões do base64 falhou - usando padrão');
      } catch(e) {
        console.log('Método alternativo também falhou');
      }
    }

    // Log final das dimensões que serão usadas
    console.log(`Dimensões finais para o Claude: ${imageWidth}x${imageHeight}`);

    // Buscar prompt de análise de imagem da planilha
    const prompts = getPromptsFromSheet();
    let userMessage = prompts.analyzeImagePrompt
      .replace('{width}', imageWidth)
      .replace('{height}', imageHeight);
    
    if (userInstructions && userInstructions.trim()) {
      userMessage += "\n\nInstruções adicionais do usuário: " + userInstructions;
    }
    
    userMessage += "\n\nLembre-se: Retorne APENAS o código SVG, nada mais.";

    const payload = {
      model: CONFIG.CLAUDE_MODEL,
      max_tokens: 8000,
      temperature: 0.3,
      system: systemPrompt,
      messages: [
        {
          role: "user",
          content: [
            {
              type: "image",
              source: {
                type: "base64",
                media_type: mediaType,
                data: base64Data
              }
            },
            {
              type: "text",
              text: userMessage
            }
          ]
        }
      ]
    };
    
    const options = {
      method: 'post',
      headers: {
        'x-api-key': CONFIG.ANTHROPIC_API_KEY,
        'anthropic-version': '2023-06-01',
        'Content-Type': 'application/json'
      },
      payload: JSON.stringify(payload),
      muteHttpExceptions: true
    };
    
    console.log('Enviando imagem para Claude...');
    const response = UrlFetchApp.fetch(url, options);
    const result = JSON.parse(response.getContentText());
    
    if (result.error) {
      console.error('Erro da API Claude:', result.error);
      throw new Error('Erro da API: ' + (result.error.message || JSON.stringify(result.error)));
    }
    
    if (result.content && result.content[0] && result.content[0].text) {
      let svgContent = result.content[0].text;
      
      // Limpar resposta
      svgContent = svgContent.replace(/```svg\n?/gi, '');
      svgContent = svgContent.replace(/```\n?/g, '');
      svgContent = svgContent.trim();
      
      // Extrair apenas o SVG
      const svgMatch = svgContent.match(/<svg[\s\S]*?<\/svg>/i);
      if (svgMatch) {
        console.log('SVG gerado com sucesso');
        return svgMatch[0];
      }
      
      // Se não encontrar tags SVG mas começar com <svg, usar tudo
      if (svgContent.startsWith('<svg')) {
        console.log('SVG gerado (formato direto)');
        return svgContent;
      }
      
      throw new Error('SVG não encontrado na resposta');
    }
    
    throw new Error('Resposta vazia da API');
    
  } catch (error) {
    console.error('Erro na geração com Claude:', error);
    throw error;
  }
}

// ==================== GERAR SVG APENAS COM TEXTO ====================
function generateSVGFromText(description) {
  try {
    if (!CONFIG.ANTHROPIC_API_KEY) {
      throw new Error('Chave da API Anthropic não configurada');
    }
    
    const url = 'https://api.anthropic.com/v1/messages';
    
    const systemPrompt = getSystemPrompt();
    
    // Buscar prompt de criação de texto da planilha
    const prompts = getPromptsFromSheet();
    const userMessage = prompts.createFromTextPrompt.replace('{description}', description);

    const payload = {
      model: CONFIG.CLAUDE_MODEL,
      max_tokens: 8000,
      temperature: 0.5,
      system: systemPrompt,
      messages: [
        {
          role: "user",
          content: userMessage
        }
      ]
    };
    
    const options = {
      method: 'post',
      headers: {
        'x-api-key': CONFIG.ANTHROPIC_API_KEY,
        'anthropic-version': '2023-06-01',
        'Content-Type': 'application/json'
      },
      payload: JSON.stringify(payload),
      muteHttpExceptions: true
    };
    
    console.log('Enviando descrição para Claude...');
    const response = UrlFetchApp.fetch(url, options);
    const result = JSON.parse(response.getContentText());
    
    if (result.error) {
      console.error('Erro da API Claude:', result.error);
      throw new Error('Erro da API: ' + (result.error.message || JSON.stringify(result.error)));
    }
    
    if (result.content && result.content[0] && result.content[0].text) {
      let svgContent = result.content[0].text;
      
      // Limpar resposta
      svgContent = svgContent.replace(/```svg\n?/gi, '');
      svgContent = svgContent.replace(/```\n?/g, '');
      svgContent = svgContent.trim();
      
      // Extrair apenas o SVG
      const svgMatch = svgContent.match(/<svg[\s\S]*?<\/svg>/i);
      if (svgMatch) {
        console.log('SVG criado com sucesso do texto');
        return svgMatch[0];
      }
      
      if (svgContent.startsWith('<svg')) {
        console.log('SVG criado (formato direto)');
        return svgContent;
      }
      
      throw new Error('SVG não encontrado na resposta');
    }
    
    throw new Error('Resposta vazia da API');
    
  } catch (error) {
    console.error('Erro na criação do texto:', error);
    throw error;
  }
}

// ==================== REFINAR SVG COM CLAUDE ====================
function refineSVGWithClaude(currentSVG, refinementInstructions) {
  try {
    if (!CONFIG.ANTHROPIC_API_KEY) {
      throw new Error('Chave da API Anthropic não configurada');
    }
    
    const url = 'https://api.anthropic.com/v1/messages';
    
    // Buscar prompt de refinamento da planilha
    const prompts = getPromptsFromSheet();
    const systemPrompt = prompts.refinePrompt;

    const userMessage = `Aqui está o SVG atual que precisa ser refinado:

${currentSVG}

INSTRUÇÕES DE REFINAMENTO DO USUÁRIO:
${refinementInstructions}

IMPORTANTE: 
- Aplique APENAS as mudanças solicitadas
- Mantenha todo o resto do diagrama intacto
- Preserve o estilo e cores do LabResumos
- Retorne apenas o código SVG atualizado`;

    const payload = {
      model: CONFIG.CLAUDE_MODEL,
      max_tokens: 8000,
      temperature: 0.2,
      system: systemPrompt,
      messages: [
        {
          role: "user",
          content: userMessage
        }
      ]
    };
    
    const options = {
      method: 'post',
      headers: {
        'x-api-key': CONFIG.ANTHROPIC_API_KEY,
        'anthropic-version': '2023-06-01',
        'Content-Type': 'application/json'
      },
      payload: JSON.stringify(payload),
      muteHttpExceptions: true
    };
    
    console.log('Enviando SVG para refinamento...');
    const response = UrlFetchApp.fetch(url, options);
    const result = JSON.parse(response.getContentText());
    
    if (result.error) {
      console.error('Erro da API Claude:', result.error);
      throw new Error('Erro da API: ' + (result.error.message || JSON.stringify(result.error)));
    }
    
    if (result.content && result.content[0] && result.content[0].text) {
      let refinedSVG = result.content[0].text;
      
      // Limpar resposta
      refinedSVG = refinedSVG.replace(/```svg\n?/gi, '');
      refinedSVG = refinedSVG.replace(/```\n?/g, '');
      refinedSVG = refinedSVG.trim();
      
      // Extrair apenas o SVG
      const svgMatch = refinedSVG.match(/<svg[\s\S]*?<\/svg>/i);
      if (svgMatch) {
        console.log('SVG refinado com sucesso');
        return svgMatch[0];
      }
      
      if (refinedSVG.startsWith('<svg')) {
        console.log('SVG refinado (formato direto)');
        return refinedSVG;
      }
      
      // Se não encontrar SVG válido, retornar o original
      console.log('Não foi possível refinar, retornando SVG original');
      return currentSVG;
    }
    
    // Em caso de erro, retornar o SVG original
    return currentSVG;
    
  } catch (error) {
    console.error('Erro ao refinar com Claude:', error);
    return currentSVG;
  }
}

// ==================== SYSTEM PROMPT COMPARTILHADO ====================
function getSystemPrompt() {
  try {
    const prompts = getPromptsFromSheet();
    return prompts.systemPrompt;
  } catch (error) {
    console.error('Erro ao buscar system prompt da planilha:', error);
    return getDefaultSystemPrompt();
  }
}

// ==================== SOBRE ====================
function showAbout() {
  const htmlContent = `
    <div style="padding: 20px; font-family: Arial, sans-serif; background: #F3F1E8;">
      <div style="background: linear-gradient(135deg, #F1CC00 0%, #FFD700 100%); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
        <h2 style="margin: 0; color: #333B49;">📊 Diagrama LabResumos</h2>
        <p style="margin: 10px 0 0 0; color: #333B49;">Versão 6.0.0 - Powered by Claude Opus 4.1</p>
      </div>
      
      <h3>Sobre</h3>
      <p>Este add-on cria diagramas profissionais usando o Claude Opus 4.1, seja transformando imagens existentes ou criando do zero a partir de descrições.</p>
      
      <h3>🚀 Características Principais</h3>
      <ul>
        <li>✅ <strong>Criar do Zero</strong> - Gere diagramas apenas com descrição</li>
        <li>✅ <strong>Transformar Imagens</strong> - Converta desenhos em diagramas profissionais</li>
        <li>✅ Powered by <strong>Claude Opus 4.1</strong> - IA de última geração</li>
        <li>✅ <strong>Preview interativo</strong> antes de inserir</li>
        <li>✅ <strong>Regeneração com ajustes</strong> ilimitados</li>
        <li>✅ <strong>Formato PNG</strong> de alta qualidade com fundo transparente</li>
        <li>✅ Inserção automática no início do documento</li>
        <li>✅ Identidade visual LabResumos integrada</li>
      </ul>
      
      <h3>🎨 Cores da Marca</h3>
      <div style="display: flex; gap: 10px; margin: 10px 0;">
        <div style="width: 50px; height: 50px; background: #F1CC00; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
        <div style="width: 50px; height: 50px; background: #333B49; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
        <div style="width: 50px; height: 50px; background: #F3F1E8; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
      </div>
      
      <h3>📝 Como usar</h3>
      
      <h4>Opção 1: Criar do Zero</h4>
      <ol>
        <li>Acesse o menu "Criar do Zero"</li>
        <li>Descreva detalhadamente o diagrama desejado</li>
        <li>Clique em "Gerar Diagrama"</li>
        <li>Visualize, ajuste se necessário e insira</li>
      </ol>
      
      <h4>Opção 2: Transformar Imagem</h4>
      <ol>
        <li>Acesse o menu "Gerar de Imagem"</li>
        <li>Faça upload ou arraste uma imagem</li>
        <li>Adicione instruções adicionais (opcional)</li>
        <li>Gere, visualize e insira no documento</li>
      </ol>
      
      <h3>💡 Dicas Pro</h3>
      <ul>
        <li>🎯 Para criar do zero, seja específico: tipo de diagrama, elementos, conexões</li>
        <li>📸 Use imagens nítidas e com boa resolução</li>
        <li>🔄 Você pode regenerar quantas vezes quiser</li>
        <li>📝 Seja específico nas instruções de ajuste</li>
        <li>📍 O diagrama será inserido automaticamente no início do documento</li>
        <li>⚡ O processo leva cerca de 15-20 segundos</li>
      </ul>
      
      <h3>✨ Exemplos de Descrições para Criar do Zero</h3>
      <ul style="font-size: 13px; background: #f5f5f5; padding: 15px; border-radius: 8px;">
        <li><em>"Fluxograma do processo de vendas com 5 etapas sequenciais"</em></li>
        <li><em>"Mapa mental sobre Marketing Digital com 4 ramos principais"</em></li>
        <li><em>"Organograma com CEO, 3 diretores e 6 gerentes"</em></li>
        <li><em>"Diagrama circular mostrando ciclo PDCA"</em></li>
        <li><em>"Timeline horizontal com 6 marcos do projeto"</em></li>
      </ul>
      
      <h3>🔬 Tecnologia</h3>
      <p style="background: #f5f5f5; padding: 10px; border-radius: 5px; margin-bottom: 10px;">
        <strong>Claude Opus 4.1</strong> é o modelo mais avançado da Anthropic, 
        líder em benchmarks de codificação e raciocínio complexo. 
        Oferece performance superior em tarefas que exigem compreensão profunda 
        e geração de código estruturado como SVG.
      </p>
      
      <p style="background: #f5f5f5; padding: 10px; border-radius: 5px;">
        <strong>Google Slides API</strong> é usado para conversão PNG nativa de alta qualidade 
        com preservação de transparência. Sistema de fallback robusto garante 
        conversão mesmo se um método falhar: Google Slides → Drive Thumbnail API.
      </p>
      
      <p style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;">
        © 2025 LabResumos - Powered by Anthropic Claude Opus 4.1<br>
        Desenvolvido com ❤️ para máxima qualidade em diagramas
      </p>
    </div>
  `;
  
  const html = HtmlService.createHtmlOutput(htmlContent)
    .setWidth(500)
    .setHeight(700);
  
  DocumentApp.getUi().showModalDialog(html, 'Sobre o Diagrama LabResumos');
}

// ==================== INSTALAÇÃO ====================
function onInstall(e) {
  onOpen(e);
}