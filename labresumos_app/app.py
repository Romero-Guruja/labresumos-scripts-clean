"""
Aplicativo principal para gerar resumos laboratoriais a partir do Google Docs.

Este módulo fornece uma classe ``LabResumosAPIApp`` que combina
as funcionalidades de ``DocsClient``, ``DocsJsonParser`` e
``PDFRenderer`` para produzir PDFs a partir de documentos do
Google Docs. Inclui também um ``main()`` para ser utilizado via
linha de comando.
"""

from __future__ import annotations

import argparse
import json
import logging
from pathlib import Path
from typing import Optional

from .config import DEFAULT_DOC_URL, DEFAULT_OUTPUT_DIR
from .docs_client import DocsClient, load_credentials
from .parser import DocsJsonParser, ParsedDoc
from .renderer import PDFRenderer

logger = logging.getLogger(__name__)


class LabResumosAPIApp:
    """Classe de alto nível para orquestrar o fluxo de processamento.

    Responsável por criar o cliente de APIs, o parser e o
    renderizador. Oferece um método ``process_document`` que recebe
    uma URL ou ID de documento, processa-o e gera um PDF no
    diretório desejado.
    """

    def __init__(self, template_dir: Optional[Path] = None) -> None:
        creds = load_credentials()
        self.client = DocsClient(creds)
        self.parser = DocsJsonParser(self.client)
        self.renderer = PDFRenderer(template_dir=template_dir)

    def process_document(
        self, source: str, output_dir: Optional[Path] = None, output_name: Optional[str] = None
    ) -> Path:
        """Processa um documento do Google Docs e gera um PDF.

        :param source: URL completa ou ID do documento a ser processado.
        :param output_dir: diretório de saída onde o PDF e arquivos auxiliares
            serão gravados. Se ``None``, utiliza ``DEFAULT_OUTPUT_DIR``.
        :param output_name: nome do arquivo PDF gerado. Caso seja ``None``,
            o nome será gerado com base na data e hora.
        :return: caminho do PDF gerado.
        """
        # Extrai o ID do documento a partir da URL, se necessário
        doc_id = self.extract_doc_id(source)
        logger.info("Baixando documento JSON: %s", doc_id)
        raw = self.client.get_document(doc_id)
        parsed: ParsedDoc = self.parser.parse(raw)

        out_dir = output_dir or DEFAULT_OUTPUT_DIR
        out_dir.mkdir(parents=True, exist_ok=True)
        if not output_name:
            from datetime import datetime

            output_name = f"lab_resumos_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
        out_path = out_dir / output_name

        # Salva o JSON bruto
        try:
            json_out = out_path.with_suffix('.json')
            with open(json_out, 'w', encoding='utf-8') as jf:
                json.dump(raw, jf, ensure_ascii=False, indent=2)
            logger.info("JSON salvo: %s", json_out)
        except Exception:
            logger.exception("Falha ao salvar JSON ao lado do PDF")

        # Salva o JSON parseado (para debug)
        try:
            parsed_out = out_path.with_suffix('.parsed.json')
            with open(parsed_out, 'w', encoding='utf-8') as pf:
                json.dump(
                    {
                        'titulo': parsed.titulo,
                        'data_geracao': parsed.data_geracao,
                        'elementos_ordenados': parsed.elementos_ordenados,
                    },
                    pf,
                    ensure_ascii=False,
                    indent=2,
                )
            logger.info("JSON parseado salvo: %s", parsed_out)
        except Exception:
            logger.exception("Falha ao salvar JSON parseado")

        self.renderer.render_pdf(parsed, out_path)
        return out_path

    @staticmethod
    def extract_doc_id(source: str) -> str:
        """Extrai o ID do documento a partir de uma URL completa.

        :param source: URL ou ID do documento.
        :return: ID do documento.
        """
        import re

        m = re.search(r"/document/d/([a-zA-Z0-9-_]+)", source)
        return m.group(1) if m else source


def main(argv: Optional[list[str]] = None) -> None:
    """Função principal para ser usada via CLI.

    Aceita os argumentos descritos em ``argparse`` e delega o
    processamento à classe ``LabResumosAPIApp``. Se nenhum
    argumento ``source`` for fornecido, utiliza a URL padrão
    definida em config.py.
    """
    parser = argparse.ArgumentParser(description='Lab Resumos – GDocs API JSON -> PDF')
    parser.add_argument(
        'source',
        nargs='?',
        default=DEFAULT_DOC_URL,
        help='URL do Google Docs ou documentId (padrão: documento predefinido)',
    )
    parser.add_argument('-o', '--output', help='nome do PDF', default=None)
    parser.add_argument('-d', '--output-dir', default=str(DEFAULT_OUTPUT_DIR), help='diretório de saída')
    parser.add_argument('-t', '--template-dir', default=None, help='diretório com templates Jinja personalizados')
    parser.add_argument('-v', '--verbose', action='store_true', help='ativa logging detalhado')
    args = parser.parse_args(argv)

    if args.verbose:
        logging.getLogger().setLevel(logging.DEBUG)
    else:
        logging.getLogger().setLevel(logging.INFO)

    app = LabResumosAPIApp(template_dir=Path(args.template_dir) if args.template_dir else None)
    out = app.process_document(args.source, output_dir=Path(args.output_dir), output_name=args.output)
    print(f"✅ PDF: {out}")


if __name__ == '__main__':
    main()