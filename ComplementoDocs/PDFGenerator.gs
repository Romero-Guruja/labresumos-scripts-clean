// ==================== CONFIGURAÇÃO CLOUD RUN ====================
const CLOUD_RUN_CONFIG = {
  // SUBSTITUA com a URL do seu serviço Cloud Run
  SERVICE_URL: 'https://labresumos-pdf-generator-457320028768.southamerica-east1.run.app',
  
  // Timeout em milissegundos (5 minutos)
  TIMEOUT: 300000,
  
  // Tamanho máximo do documento em caracteres (para evitar timeout)
  MAX_DOC_SIZE: 500000
};


// ==================== FUNÇÕES DE SUPORTE ====================


function generatePDFWithCloudRun(options) {
  try {
    const doc = DocumentApp.getActiveDocument();
    const docUrl = doc.getUrl();
    
    // Preparar payload
    const payload = {
      documentUrl: docUrl,
      outputName: options.outputName || doc.getName() + '.pdf',
      options: {
        includeImages: options.includeImages,
        includeCharts: options.includeCharts,
        useCache: options.useCache
      }
    };
    
    console.log('Enviando requisição para Cloud Run:', CLOUD_RUN_CONFIG.SERVICE_URL);
    
    // Fazer requisição ao Cloud Run
    const response = UrlFetchApp.fetch(CLOUD_RUN_CONFIG.SERVICE_URL + '/generate-pdf', {
      method: 'POST',
      contentType: 'application/json',
      payload: JSON.stringify(payload),
      muteHttpExceptions: true,
      validateHttpsCertificates: true,
      followRedirects: true,
      headers: {
        'Accept': 'application/json',
        'User-Agent': 'LabResumos-GoogleDocs/1.0'
      }
    });
    
    const responseCode = response.getResponseCode();
    const responseText = response.getContentText();
    
    console.log('Response code:', responseCode);
    
    if (responseCode === 200) {
      const result = JSON.parse(responseText);
      
      if (result.success && result.pdfBase64) {
        // Salvar PDF no Drive (opcional)
        const pdfBlob = Utilities.newBlob(
          Utilities.base64Decode(result.pdfBase64),
          'application/pdf',
          result.fileName
        );
        
        const folder = DriveApp.getRootFolder(); // Ou uma pasta específica
        const pdfFile = folder.createFile(pdfBlob);
        
        // Adicionar URL do Drive ao resultado
        result.driveUrl = pdfFile.getUrl();
        result.driveId = pdfFile.getId();
        
        console.log('PDF salvo no Drive:', result.driveUrl);
        
        return result;
      } else {
        throw new Error('Resposta inválida do servidor');
      }
    } else {
      const errorData = JSON.parse(responseText);
      throw new Error(errorData.error || 'Erro ao gerar PDF');
    }
    
  } catch (error) {
    console.error('Erro ao gerar PDF:', error);
    throw error;
  }
}

// ==================== GERAÇÃO RÁPIDA DE PDF ====================
function generateQuickPDF() {
  const ui = DocumentApp.getUi();
  
  // Mostrar diálogo de progresso simples
  ui.alert(
    '⏳ Gerando PDF',
    'Por favor aguarde enquanto o PDF está sendo gerado...\n\n' +
    'Isso pode levar de 30 segundos a 2 minutos.',
    ui.ButtonSet.OK
  );
  
  try {
    const options = {
      outputName: null,
      includeImages: true,
      includeCharts: true,
      useCache: true
    };
    
    const result = generatePDFWithCloudRun(options);
    
    if (result && result.driveUrl) {
      // Mostrar link para download
      const response = ui.alert(
        '✅ PDF Gerado com Sucesso!',
        'Seu PDF foi gerado e salvo no Google Drive.\n\n' +
        'Nome: ' + result.fileName + '\n' +
        'Tamanho: ' + (result.size / 1024).toFixed(1) + ' KB\n\n' +
        'Deseja abrir o PDF agora?',
        ui.ButtonSet.YES_NO
      );
      
      if (response === ui.Button.YES) {
        // Abrir PDF em nova aba
        const htmlOutput = HtmlService
          .createHtmlOutput('<script>window.open("' + result.driveUrl + '");google.script.host.close();</script>')
          .setWidth(100)
          .setHeight(100);
        ui.showModalDialog(htmlOutput, 'Abrindo PDF...');
      }
    }
  } catch (error) {
    ui.alert(
      '❌ Erro ao Gerar PDF',
      'Ocorreu um erro ao gerar o PDF:\n\n' + error.toString(),
      ui.ButtonSet.OK
    );
  }
}
