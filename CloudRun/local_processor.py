# -*- coding: utf-8 -*-
"""
Lab Resumos - Processador Local
Arquivo para testar a geração de PDF localmente, usando o processor.py
"""

import os
import logging
import time
from pathlib import Path
from datetime import datetime
from typing import Optional

# Importa as classes do processor.py
from processor import LabResumosAPIApp, extract_doc_id

# ---------------------------------------------------------------------
# Configurações
# ---------------------------------------------------------------------

# URL do Google Docs para teste (pode ser alterada aqui)
# Padrão: a mesma URL do processor.py
DEFAULT_DOC_URL = "https://docs.google.com/document/d/1mQh2krssppvvnBEZRS3JfJpF6atmvj_wxgw3CnQj6nE/edit?tab=t.0"



# URL customizada - altere aqui se quiser testar com outro documento
CUSTOM_DOC_URL = "https://docs.google.com/document/d/1KA3mEr28ZpX48QkUhvRiC0oYHEID0QUGWradkRo6014/edit?tab=t.7iznbhuhmpc#heading=h.y7ygbva7njh0" # None  # Ex: "https://docs.google.com/document/d/SEU_DOC_ID/edit"

# Diretório de saída para testes locais
OUTPUT_DIR = Path("./outputs")

# ---------------------------------------------------------------------
# Configuração de Logging
# ---------------------------------------------------------------------

def setup_logging(verbose: bool = True):
    """Configura logging detalhado para debug"""
    level = logging.DEBUG if verbose else logging.INFO
    
    # Formato detalhado com timestamp
    formatter = logging.Formatter(
        '%(asctime)s - %(name)s - %(levelname)s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    
    # Handler para console
    console_handler = logging.StreamHandler()
    console_handler.setFormatter(formatter)
    
    # Configura logger principal
    logger = logging.getLogger()
    logger.setLevel(level)
    logger.addHandler(console_handler)
    
    # Configura loggers específicos
    for logger_name in ['labresumos.api', 'weasyprint', 'fontTools']:
        specific_logger = logging.getLogger(logger_name)
        specific_logger.setLevel(level)
    
    return logging.getLogger("local_processor")

# ---------------------------------------------------------------------
# Processador Local
# ---------------------------------------------------------------------

class LocalProcessor:
    """Wrapper para processamento local com funcionalidades extras"""
    
    def __init__(self, output_dir: Optional[Path] = None, verbose: bool = True):
        self.logger = setup_logging(verbose)
        self.output_dir = output_dir or OUTPUT_DIR
        self.output_dir.mkdir(parents=True, exist_ok=True)
        
        self.logger.info("=" * 60)
        self.logger.info("🚀 Lab Resumos - Processador Local Iniciado")
        self.logger.info("=" * 60)
        self.logger.info(f"📁 Diretório de saída: {self.output_dir.absolute()}")
        
        # Inicializa o processador principal
        try:
            self.processor = LabResumosAPIApp()
            self.logger.info("✅ Processador PDF inicializado com sucesso")
        except Exception as e:
            self.logger.error(f"❌ Erro ao inicializar processador: {str(e)}")
            raise
    
    def process_document(self, doc_url: Optional[str] = None, output_name: Optional[str] = None) -> Path:
        """
        Processa um documento Google Docs e gera PDF
        
        Args:
            doc_url: URL do documento (se None, usa a URL padrão)
            output_name: Nome do arquivo de saída (se None, gera automaticamente)
        
        Returns:
            Path do PDF gerado
        """
        # Define URL do documento
        url_to_use = doc_url or CUSTOM_DOC_URL or DEFAULT_DOC_URL
        doc_id = extract_doc_id(url_to_use)
        
        self.logger.info("-" * 50)
        self.logger.info("📄 Iniciando processamento do documento")
        self.logger.info(f"🔗 URL: {url_to_use}")
        self.logger.info(f"🆔 ID do documento: {doc_id}")
        
        # Define nome do arquivo de saída
        if not output_name:
            timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
            output_name = f"lab_resumos_local_{timestamp}.pdf"
        
        self.logger.info(f"📝 Nome do arquivo: {output_name}")
        
        # Inicia cronômetro
        start_time = time.time()
        
        try:
            # Processa o documento
            self.logger.info("🔄 Processando documento...")
            pdf_path = self.processor.process_document(
                source=url_to_use,
                output_dir=self.output_dir,
                output_name=output_name
            )
            
            # Calcula tempo de processamento
            processing_time = time.time() - start_time
            
            # Informações do arquivo gerado
            file_size = pdf_path.stat().st_size
            file_size_mb = file_size / (1024 * 1024)
            
            self.logger.info("=" * 50)
            self.logger.info("✅ PROCESSAMENTO CONCLUÍDO COM SUCESSO!")
            self.logger.info("=" * 50)
            self.logger.info(f"📄 PDF gerado: {pdf_path}")
            self.logger.info(f"📊 Tamanho: {file_size_mb:.2f} MB ({file_size:,} bytes)")
            self.logger.info(f"⏱️  Tempo de processamento: {processing_time:.2f} segundos")
            
            # Verifica arquivos auxiliares gerados
            json_file = pdf_path.with_suffix('.json')
            parsed_json_file = pdf_path.with_suffix('.parsed.json')
            
            if json_file.exists():
                json_size = json_file.stat().st_size / 1024
                self.logger.info(f"📋 JSON raw: {json_file} ({json_size:.1f} KB)")
            
            if parsed_json_file.exists():
                parsed_size = parsed_json_file.stat().st_size / 1024
                self.logger.info(f"📋 JSON parseado: {parsed_json_file} ({parsed_size:.1f} KB)")
            
            self.logger.info("-" * 50)
            
            return pdf_path
            
        except Exception as e:
            processing_time = time.time() - start_time
            self.logger.error("=" * 50)
            self.logger.error("❌ ERRO NO PROCESSAMENTO!")
            self.logger.error("=" * 50)
            self.logger.error(f"⏱️  Tempo até erro: {processing_time:.2f} segundos")
            self.logger.error(f"🚨 Erro: {str(e)}")
            self.logger.error("-" * 50)
            raise
    
    def list_output_files(self):
        """Lista todos os arquivos na pasta de saída"""
        self.logger.info("📁 Arquivos na pasta de saída:")
        self.logger.info("-" * 40)
        
        files = list(self.output_dir.glob("*"))
        if not files:
            self.logger.info("   (pasta vazia)")
            return
        
        # Ordena por data de modificação (mais recente primeiro)
        files.sort(key=lambda f: f.stat().st_mtime, reverse=True)
        
        for file_path in files:
            if file_path.is_file():
                size = file_path.stat().st_size
                size_str = f"{size/1024:.1f} KB" if size < 1024*1024 else f"{size/(1024*1024):.1f} MB"
                modified = datetime.fromtimestamp(file_path.stat().st_mtime)
                
                self.logger.info(f"   📄 {file_path.name} ({size_str}) - {modified.strftime('%H:%M:%S')}")

# ---------------------------------------------------------------------
# Funções de Conveniência
# ---------------------------------------------------------------------

def test_default_document():
    """Testa com o documento padrão"""
    processor = LocalProcessor()
    # Usa CUSTOM_DOC_URL se estiver definido, senão usa DEFAULT_DOC_URL
    doc_url = CUSTOM_DOC_URL if CUSTOM_DOC_URL else DEFAULT_DOC_URL
    processor.process_document(doc_url=doc_url)
    processor.list_output_files()

def test_custom_document(doc_url: str):
    """Testa com um documento customizado"""
    processor = LocalProcessor()
    processor.process_document(doc_url=doc_url)
    processor.list_output_files()

def clean_output():
    """Limpa a pasta de saída"""
    output_dir = OUTPUT_DIR
    if output_dir.exists():
        for file_path in output_dir.glob("*"):
            if file_path.is_file():
                file_path.unlink()
                print(f"🗑️  Removido: {file_path.name}")
        print(f"✅ Pasta {output_dir} limpa")
    else:
        print(f"📁 Pasta {output_dir} não existe")

# ---------------------------------------------------------------------
# Execução Principal
# ---------------------------------------------------------------------

def main():
    """Função principal para execução via linha de comando"""
    import argparse
    
    parser = argparse.ArgumentParser(description='Lab Resumos - Processador Local')
    parser.add_argument('--url', '-u', help='URL do documento Google Docs')
    parser.add_argument('--output', '-o', help='Nome do arquivo de saída')
    parser.add_argument('--clean', action='store_true', help='Limpar pasta de saída antes de processar')
    parser.add_argument('--list', action='store_true', help='Apenas listar arquivos na pasta de saída')
    parser.add_argument('--quiet', '-q', action='store_true', help='Modo silencioso (menos logs)')
    
    args = parser.parse_args()
    
    if args.list:
        processor = LocalProcessor(verbose=not args.quiet)
        processor.list_output_files()
        return
    
    if args.clean:
        clean_output()
    
    try:
        processor = LocalProcessor(verbose=not args.quiet)
        processor.process_document(doc_url=args.url, output_name=args.output)
        processor.list_output_files()
    except Exception as e:
        print(f"❌ Erro: {e}")
        exit(1)

if __name__ == "__main__":
    # Se executado diretamente, processa o documento padrão
    if len(os.sys.argv) > 1:
        main()
    else:
        print("🚀 Executando teste com documento padrão...")
        print("💡 Use --help para ver outras opções")
        print()
        test_default_document()
