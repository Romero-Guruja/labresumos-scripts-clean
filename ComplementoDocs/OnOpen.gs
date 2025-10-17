// ==================== MENU CENTRALIZADO DO GOOGLE DOCS ====================
// Este arquivo gerencia todos os menus do add-on LabResumos

function onOpen() {
  const ui = DocumentApp.getUi();
  
  // Menu principal com todas as funcionalidades
  ui.createMenu('📊 LabResumos')
    // Seção de Diagramas
    .addItem('✨ Criar Diagrama do Zero', 'showCreateFromTextDialog')
    .addItem('🎨 Gerar Diagrama de Imagem', 'showDiagramDialog')
    .addSeparator()
    // Seção de PDF
    .addItem('📄 Gerar PDF', 'generateQuickPDF')
    .addToUi();
}

// Função de instalação - chama onOpen ao instalar
function onInstall(e) {
  onOpen(e);
}