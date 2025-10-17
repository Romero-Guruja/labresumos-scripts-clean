from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import os
import logging
import base64
import io
from pathlib import Path
from datetime import datetime
import tempfile
import shutil

# Importações do processor.py
from processor import (
    LabResumosAPIApp,
    extract_doc_id,
    load_credentials
)

app = Flask(__name__)
CORS(app)

# Configuração de logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Inicialização do processador
try:
    # Cria instância única do processador
    pdf_processor = LabResumosAPIApp()
    logger.info("✅ Processador PDF inicializado com sucesso")
except Exception as e:
    logger.error(f"❌ Erro ao inicializar processador: {str(e)}")
    pdf_processor = None

@app.route('/')
def home():
    """Endpoint principal - status do serviço"""
    return jsonify({
        'status': 'ok',
        'service': 'LabResumos PDF Generator',
        'version': '2.0.0',
        'processor': 'ready' if pdf_processor else 'not initialized',
        'endpoints': [
            'GET /',
            'GET /health',
            'POST /generate-pdf',
            'POST /generate-pdf-stream',
            'POST /validate-document'
        ]
    })

@app.route('/health')
def health():
    """Health check para Cloud Run"""
    health_status = {
        'status': 'healthy' if pdf_processor else 'degraded',
        'service': 'LabResumos PDF Generator',
        'version': '2.0.0',
        'timestamp': datetime.now().isoformat(),
        'processor_ready': pdf_processor is not None
    }
    
    if not pdf_processor:
        health_status['error'] = 'PDF processor not initialized'
        return jsonify(health_status), 503
    
    return jsonify(health_status)

@app.route('/generate-pdf', methods=['POST'])
def generate_pdf():
    """Gera PDF e retorna em base64"""
    try:
        # Valida processador
        if not pdf_processor:
            return jsonify({
                'success': False,
                'error': 'PDF processor not initialized'
            }), 503
        
        # Obtém dados da requisição
        data = request.get_json() if request.is_json else {}
        logger.info(f"📥 Received request: {data}")
        
        # Extrai URL do documento
        doc_url = data.get('documentUrl') or data.get('docUrl') or data.get('url')
        if not doc_url:
            return jsonify({
                'success': False,
                'error': 'Missing documentUrl in request'
            }), 400
        
        # Extrai ID do documento
        doc_id = extract_doc_id(doc_url)
        logger.info(f"📄 Processing document ID: {doc_id}")
        
        # Cria diretório temporário
        with tempfile.TemporaryDirectory() as tmp_dir:
            tmp_path = Path(tmp_dir)
            
            # Nome do arquivo com timestamp
            timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
            output_name = f"lab_resumos_{doc_id[:8]}_{timestamp}.pdf"
            
            # Processa o documento
            logger.info(f"🔄 Starting PDF generation for: {doc_id}")
            pdf_path = pdf_processor.process_document(
                source=doc_url,
                output_dir=tmp_path,
                output_name=output_name
            )
            
            # Lê o PDF gerado
            with open(pdf_path, 'rb') as pdf_file:
                pdf_content = pdf_file.read()
            
            # Converte para base64
            pdf_base64 = base64.b64encode(pdf_content).decode('utf-8')
            
            # Resposta de sucesso
            response = {
                'success': True,
                'fileName': output_name,
                'pdfBase64': pdf_base64,
                'size': len(pdf_content),
                'documentId': doc_id,
                'generatedAt': datetime.now().isoformat()
            }
            
            logger.info(f"✅ PDF generated successfully: {output_name} ({len(pdf_content)} bytes)")
            return jsonify(response)
            
    except Exception as e:
        logger.error(f"❌ Error generating PDF: {str(e)}", exc_info=True)
        return jsonify({
            'success': False,
            'error': str(e),
            'type': type(e).__name__
        }), 500

@app.route('/generate-pdf-stream', methods=['POST'])
def generate_pdf_stream():
    """Gera PDF e retorna como stream (download direto)"""
    try:
        # Valida processador
        if not pdf_processor:
            return jsonify({
                'success': False,
                'error': 'PDF processor not initialized'
            }), 503
        
        # Obtém dados da requisição
        data = request.get_json() if request.is_json else {}
        logger.info(f"📥 Received stream request: {data}")
        
        # Extrai URL do documento
        doc_url = data.get('documentUrl') or data.get('docUrl') or data.get('url')
        if not doc_url:
            return jsonify({
                'success': False,
                'error': 'Missing documentUrl in request'
            }), 400
        
        # Extrai ID do documento
        doc_id = extract_doc_id(doc_url)
        logger.info(f"📄 Processing document ID for stream: {doc_id}")
        
        # Cria diretório temporário
        with tempfile.TemporaryDirectory() as tmp_dir:
            tmp_path = Path(tmp_dir)
            
            # Nome do arquivo com timestamp
            timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
            output_name = f"lab_resumos_{doc_id[:8]}_{timestamp}.pdf"
            
            # Processa o documento
            logger.info(f"🔄 Starting PDF stream generation for: {doc_id}")
            pdf_path = pdf_processor.process_document(
                source=doc_url,
                output_dir=tmp_path,
                output_name=output_name
            )
            
            # Lê o PDF para memória
            with open(pdf_path, 'rb') as pdf_file:
                pdf_buffer = io.BytesIO(pdf_file.read())
            
            pdf_buffer.seek(0)
            
            logger.info(f"✅ PDF stream ready: {output_name}")
            
            # Retorna o arquivo como stream
            return send_file(
                pdf_buffer,
                mimetype='application/pdf',
                as_attachment=True,
                download_name=output_name
            )
            
    except Exception as e:
        logger.error(f"❌ Error generating PDF stream: {str(e)}", exc_info=True)
        return jsonify({
            'success': False,
            'error': str(e),
            'type': type(e).__name__
        }), 500

@app.route('/validate-document', methods=['POST'])
def validate_document():
    """Valida se o documento pode ser acessado"""
    try:
        # Valida processador
        if not pdf_processor:
            return jsonify({
                'success': False,
                'error': 'PDF processor not initialized'
            }), 503
        
        # Obtém dados da requisição
        data = request.get_json() if request.is_json else {}
        doc_url = data.get('documentUrl') or data.get('docUrl') or data.get('url')
        
        if not doc_url:
            return jsonify({
                'valid': False,
                'error': 'Missing documentUrl'
            }), 400
        
        # Tenta extrair e validar o ID
        try:
            doc_id = extract_doc_id(doc_url)
            
            # Tenta buscar o documento para validar acesso
            logger.info(f"🔍 Validating document access: {doc_id}")
            doc_data = pdf_processor.client.get_document(doc_id)
            
            # Se chegou aqui, o documento é válido e acessível
            return jsonify({
                'valid': True,
                'documentId': doc_id,
                'title': doc_data.get('title', 'Untitled'),
                'accessible': True
            })
            
        except Exception as validation_error:
            logger.warning(f"Document validation failed: {str(validation_error)}")
            return jsonify({
                'valid': False,
                'documentId': doc_id if 'doc_id' in locals() else None,
                'error': str(validation_error),
                'accessible': False
            })
            
    except Exception as e:
        logger.error(f"❌ Error validating document: {str(e)}", exc_info=True)
        return jsonify({
            'valid': False,
            'error': str(e)
        }), 500

@app.route('/process-test', methods=['GET'])
def process_test():
    """Endpoint de teste com documento hardcoded"""
    try:
        if not pdf_processor:
            return jsonify({
                'success': False,
                'error': 'PDF processor not initialized'
            }), 503
        
        # URL do documento de teste (o mesmo do processor.py)
        test_doc_url = "https://docs.google.com/document/d/1mQh2krssppvvnBEZRS3JfJpF6atmvj_wxgw3CnQj6nE/edit?tab=t.0"
        
        logger.info("🧪 Running test with hardcoded document")
        
        # Cria diretório temporário
        with tempfile.TemporaryDirectory() as tmp_dir:
            tmp_path = Path(tmp_dir)
            
            # Processa o documento
            pdf_path = pdf_processor.process_document(
                source=test_doc_url,
                output_dir=tmp_path,
                output_name="test_document.pdf"
            )
            
            # Lê o PDF gerado
            with open(pdf_path, 'rb') as pdf_file:
                pdf_content = pdf_file.read()
            
            # Converte para base64
            pdf_base64 = base64.b64encode(pdf_content).decode('utf-8')
            
            return jsonify({
                'success': True,
                'message': 'Test document processed successfully',
                'fileName': 'test_document.pdf',
                'pdfBase64': pdf_base64,
                'size': len(pdf_content)
            })
            
    except Exception as e:
        logger.error(f"❌ Test failed: {str(e)}", exc_info=True)
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.errorhandler(404)
def not_found(e):
    """Handler para rotas não encontradas"""
    return jsonify({
        'error': 'Endpoint not found',
        'message': 'Use GET / to see available endpoints'
    }), 404

@app.errorhandler(500)
def internal_error(e):
    """Handler para erros internos"""
    logger.error(f"Internal server error: {str(e)}", exc_info=True)
    return jsonify({
        'error': 'Internal server error',
        'message': str(e)
    }), 500

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 8080))
    debug_mode = os.environ.get('DEBUG', 'False').lower() == 'true'
    
    logger.info(f"🚀 Starting LabResumos PDF Generator on port {port}")
    logger.info(f"📁 Working directory: {os.getcwd()}")
    logger.info(f"🔧 Debug mode: {debug_mode}")
    logger.info(f"🔑 Credentials file: {os.environ.get('GOOGLE_APPLICATION_CREDENTIALS', 'Not set')}")
    
    if pdf_processor:
        logger.info("✅ PDF processor ready")
    else:
        logger.warning("⚠️ PDF processor not initialized - service will have limited functionality")
    
    app.run(
        host='0.0.0.0',
        port=port,
        debug=debug_mode
    )