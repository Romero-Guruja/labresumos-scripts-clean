"""
Renderizador HTML/PDF para o aplicativo Lab Resumos.

Este módulo oferece a classe ``PDFRenderer`` que, a partir de um
``ParsedDoc``, renderiza um HTML utilizando templates Jinja2
modularizados e depois converte o resultado em um PDF via
WeasyPrint. A separação dos templates permite que cada tipo de
elemento seja customizado de forma independente em arquivos
individuais dentro da pasta ``templates/elements``.
"""

from __future__ import annotations

import json
import logging
from pathlib import Path
from typing import Optional

from jinja2 import Environment, FileSystemLoader, select_autoescape
from weasyprint import HTML, CSS

from .parser import ParsedDoc

logger = logging.getLogger(__name__)


class PDFRenderer:
    """Classe responsável por renderizar um ParsedDoc em PDF.

    Utiliza Jinja2 para montar o HTML a partir de templates
    modularizados. Em seguida, utiliza WeasyPrint para converter
    esse HTML em PDF. Pode receber um diretório de templates
    alternativo via ``template_dir``. Caso nenhum seja fornecido,
    utiliza a pasta ``templates`` situada no mesmo diretório do
    pacote.
    """

    def __init__(self, template_dir: Optional[Path] = None) -> None:
        # Determina a pasta de templates. Se nada for passado, usa
        # ``templates`` centralizado no CloudRun.
        if template_dir is None:
            # Usa o diretório centralizado CloudRun/templates
            default_tpl_dir = Path(__file__).resolve().parent.parent / "CloudRun" / "templates"
            self.template_dir: Path = default_tpl_dir
        else:
            self.template_dir = template_dir

        # Configura ambiente Jinja2. ``autoescape`` garante que
        # strings sejam escapadas por padrão, exceto quando marcadas
        # como seguras.
        self.env = Environment(
            loader=FileSystemLoader(str(self.template_dir)),
            autoescape=select_autoescape(['html', 'xml'])
        )

        # Filtro para serializar em JSON dentro do template, se necessário
        self.env.filters['json'] = json.dumps
        # Torna disponível globalmente no template a função generate_qr_code.
        # Esta função é definida aqui para evitar custo de import no
        # carregamento do módulo e para isolar dependências. Ela será
        # utilizada nos templates para gerar imagens de QR Code.
        def generate_qr_code(data: str) -> str:
            import qrcode as qr_lib  # import local para evitar dependência global
            try:
                qr = qr_lib.QRCode(
                    version=1,
                    error_correction=qr_lib.constants.ERROR_CORRECT_M,
                    box_size=4,
                    border=2,
                )
                qr.add_data(data)
                qr.make(fit=True)
                img = qr.make_image(fill_color="black", back_color="white")
                from io import BytesIO
                buf = BytesIO()
                img.save(buf, format="PNG")
                import base64
                return base64.b64encode(buf.getvalue()).decode()
            except Exception:
                return ""

        self.env.globals['generate_qr_code'] = generate_qr_code

        # Filtro markdown para converter texto simples em HTML leve.
        def process_md(text: str) -> str:
            """Converte trechos markdown básicos em HTML leve.

            Este filtro aplica algumas conversões simples: títulos
            iniciados por #, **negrito**, *itálico*, imagens inline
            no formato ![img](dataurl), e quebra de parágrafos.
            Mantém listas e tabelas em HTML se já estiverem
            presentes no texto.
            """
            if not text:
                return ""
            # Não processa se já contém elementos HTML significativos
            if any(tag in text for tag in ('<ul', '<ol', '<table', '<thead', '<tbody', '<tr', '<td', '<th')):
                return text
            import re as _re
            # headings
            text = _re.sub(r"^### (.+)$", lambda m: f"<div class='h3'>{m.group(1)}</div>", text, flags=_re.MULTILINE)
            text = _re.sub(r"^## (.+)$", lambda m: f"<div class='h2'>{m.group(1)}</div>", text, flags=_re.MULTILINE)
            text = _re.sub(r"^# (.+)$", lambda m: f"<div class='h1'>{m.group(1)}</div>", text, flags=_re.MULTILINE)
            # bold/italic
            text = _re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", text)
            text = _re.sub(r"\*(.+?)\*", r"<em>\1</em>", text)
            # imagens inline ![img](dataurl)
            text = _re.sub(r"!\[img\]\((.*?)\)", r"<img src='\1' />", text)
            # parágrafos
            parts = [p for p in text.split("\n\n") if p.strip()]
            html_parts = [p if p.startswith("<") else f"<div class='p'>{p}</div>" for p in parts]
            return "\n".join(html_parts)

        self.env.filters['markdown'] = process_md

    def render_html(self, doc: ParsedDoc) -> str:
        """Renderiza o HTML a partir de um ParsedDoc.

        Utiliza o template ``base.html`` situado no diretório de
        templates. Este template deve iterar sobre ``elementos_ordenados``
        e incluir os sub-templates correspondentes a cada tipo de
        elemento.
        """
        tpl = self.env.get_template("base.html")
        html_content = tpl.render(
            titulo=doc.titulo,
            elementos_ordenados=doc.elementos_ordenados,
            data_geracao=doc.data_geracao,
        )
        return html_content

    def render_pdf(self, doc: ParsedDoc, output_path: Path) -> Path:
        """Gera um PDF a partir de um ParsedDoc e salva em ``output_path``.

        Primeiro, renderiza o HTML usando ``render_html``. Depois,
        utiliza WeasyPrint para converter em PDF. Um CSS básico é
        aplicado com margens adequadas para A4.
        """
        html_content = self.render_html(doc)
        try:
            html_out = output_path.with_suffix('.html')
            html_out.write_text(html_content, encoding='utf-8')
            logger.info("HTML salvo: %s", html_out)
        except Exception:
            logger.exception("Falha ao salvar HTML gerado")
        # Estilos adicionais para margens de impressão. O estilo
        # principal deve ser fornecido no template base através de
        # inclusão de style.css.
        CSS_STR = """
        @page { size: A4; margin: 15mm; }
        @media print { * { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
        img { max-width: 100%; height: auto; }
        .pagebreak { break-before: page; }
        .h1, .h2, .h3 { break-inside: avoid; }
        """
        HTML(string=html_content).write_pdf(str(output_path), stylesheets=[CSS(string=CSS_STR)])
        logger.info("PDF gerado: %s", output_path)
        return output_path
