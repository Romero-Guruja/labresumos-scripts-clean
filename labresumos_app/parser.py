"""
Parser do JSON retornado pela API do Google Docs.

Este módulo converte a estrutura complexa de um documento do
Google Docs em uma forma mais simples, adequada para renderização
posterior em HTML/PDF. O parser identifica elementos como
títulos, listas, tabelas, imagens e uma série de blocos
customizados (definidos por marcações {{ ... }}) e retorna uma
lista ordenada de elementos com seus dados estruturados.
"""

from __future__ import annotations

import base64
import io
import json
import logging
import re
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional

import qrcode

from .docs_client import DocsClient

logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------
# Blocos customizados
# ---------------------------------------------------------------------

# Dicionário de tags customizadas suportadas. Cada item contém uma
# expressão regular que captura o conteúdo entre as tags de abertura
# ``{{nome}}`` e fechamento ``{{/nome}}``. Essas tags são
# utilizadas para delimitar blocos especiais dentro de textos
# comuns. O parser explodirá o conteúdo do elemento em blocos
# separados com base nestas marcações.
CUSTOM_TAGS: Dict[str, str] = {
    'grafico_linha': r'\{\{grafico_linha\}\}(.*?)\{\{/grafico_linha\}\}',
    'grafico_pizza': r'\{\{grafico_pizza\}\}(.*?)\{\{/grafico_pizza\}\}',
    'grafico_barras': r'\{\{grafico_barras\}\}(.*?)\{\{/grafico_barras\}\}',
    'lista_aninhada': r'\{\{lista_aninhada\}\}(.*?)\{\{/lista_aninhada\}\}',
    'citacao': r'\{\{citacao\}\}(.*?)\{\{/citacao\}\}',
    'formula': r'\{\{formula\}\}(.*?)\{\{/formula\}\}',
    'fluxograma': r'\{\{fluxograma\}\}(.*?)\{\{/fluxograma\}\}',
    'codigo': r'\{\{codigo\}\}(.*?)\{\{/codigo\}\}',
    'questao_multipla': r'\{\{questao_multipla\}\}(.*?)\{\{/questao_multipla\}\}',
    'referencias': r'\{\{referencias\}\}(.*?)\{\{/referencias\}\}',
    'qrcode': r'\{\{qrcode\}\}(.*?)\{\{/qrcode\}\}',
    'barcode': r'\{\{barcode\}\}(.*?)\{\{/barcode\}\}',
    'cabecalho': r'\{\{cabecalho\}\}(.*?)\{\{/cabecalho\}\}',
    'tags': r'\{\{tags\}\}(.*?)\{\{/tags\}\}',
    'conceitos': r'\{\{conceitos\}\}(.*?)\{\{/conceitos\}\}',
    'timeline': r'\{\{timeline\}\}(.*?)\{\{/timeline\}\}',
    'dica': r'\{\{dica\}\}(.*?)\{\{/dica\}\}',
    'alerta': r'\{\{alerta\}\}(.*?)\{\{/alerta\}\}',
    'questao_dissertativa': r'\{\{questao_dissertativa\}\}(.*?)\{\{/questao_dissertativa\}\}',
    'glossario': r'\{\{glossario\}\}(.*?)\{\{/glossario\}\}',
    'checklist': r'\{\{checklist\}\}(.*?)\{\{/checklist\}\}',
}


@dataclass
class ParsedDoc:
    """Representa um documento parseado.

    Atributos:
        titulo: título do documento original.
        elementos_ordenados: lista ordenada de elementos (dicts) a
            serem renderizados. Cada elemento possui uma chave
            ``tipo`` e dados adicionais dependentes do tipo.
        data_geracao: string com a data/hora de geração, usada em
            rodapés e cabeçalhos.
    """

    titulo: str
    elementos_ordenados: List[Dict[str, Any]]
    data_geracao: str


class DocsJsonParser:
    """Parser que converte o JSON do Google Docs em elementos renderizáveis."""

    def __init__(self, client: DocsClient) -> None:
        self.client = client

    def parse(self, doc: Dict[str, Any]) -> ParsedDoc:
        """Converte o JSON de um documento em uma estrutura ParsedDoc.

        :param doc: dicionário retornado pela API do Google Docs.
        :return: instância de ParsedDoc com elementos ordenados.
        """
        title = doc.get("title", "")
        body = doc.get("body", {}).get("content", [])
        lists_index = doc.get("lists") or {}

        elementos: List[Dict[str, Any]] = []
        text_buffer: List[str] = []  # acumula texto plano para detectar {{tags}}

        # Mapa de imagens resolvidas (objectId -> dataURL)
        img_map: Dict[str, str] = {}

        # Pré-carrega inlineObjects para contentUri
        inline_objs = doc.get("inlineObjects") or {}

        def b64_png(data: bytes) -> str:
            return base64.b64encode(data).decode("utf-8")

        def generate_qr_code(data: str) -> str:
            try:
                qr = qrcode.QRCode(
                    version=1,
                    error_correction=qrcode.constants.ERROR_CORRECT_M,
                    box_size=4,
                    border=2,
                )
                qr.add_data(data)
                qr.make(fit=True)
                img = qr.make_image(fill_color="black", back_color="white")
                buf = io.BytesIO()
                img.save(buf, format="PNG")
                return base64.b64encode(buf.getvalue()).decode()
            except Exception as e:
                logger.exception("QR error: %s", e)
                return ""

        def resolve_inline_image(obj_id: str) -> Optional[str]:
            if obj_id in img_map:
                return img_map[obj_id]
            meta = (
                inline_objs.get(obj_id, {})
                .get("inlineObjectProperties", {})
                .get("embeddedObject", {})
            )
            uri = (meta.get("imageProperties") or {}).get("contentUri")
            if not uri:
                return None
            try:
                blob = self.client.fetch_content_uri(uri)
                dataurl = f"data:image/png;base64,{b64_png(blob)}"
                img_map[obj_id] = dataurl
                return dataurl
            except Exception:
                logger.warning("Falha ao baixar contentUri para %s", obj_id)
                return None

        # Helpers para renderizar runs com estilos
        def render_text_run(tr: Dict[str, Any]) -> str:
            content = tr.get("content", "")
            if not content:
                return ""
            style = tr.get("textStyle") or {}
            href = None
            link = style.get("link")
            if link and isinstance(link, dict):
                href = link.get("url")
            if style.get("bold"):
                content = f"<strong>{content}</strong>"
            if style.get("italic"):
                content = f"<em>{content}</em>"
            if href:
                content = f"<a href=\"{href}\">{content}</a>"
            return content

        def render_paragraph_inline(para: Dict[str, Any]) -> str:
            parts: List[str] = []
            for ce in para.get("elements", []):
                if "inlineObjectElement" in ce:
                    obj_id = ce["inlineObjectElement"].get("inlineObjectId")
                    src = resolve_inline_image(obj_id) if obj_id else None
                    if src:
                        parts.append(f"<img src=\"{src}\" />")
                tr = ce.get("textRun")
                if tr:
                    parts.append(render_text_run(tr))
            return "".join(parts)

        # Controle de listas aninhadas (ul/ol) baseado em listId/nestingLevel
        list_open_stack: List[str] = []  # valores 'ul' ou 'ol'
        list_buffer: List[str] = []
        current_list_id: Optional[str] = None

        def close_lists_to_level(level: int) -> None:
            nonlocal list_open_stack, list_buffer
            while len(list_open_stack) > level:
                tag = list_open_stack.pop()
                list_buffer.append(f"</{tag}>")

        def flush_list_if_any() -> None:
            nonlocal list_buffer, list_open_stack, current_list_id, elementos
            if list_open_stack:
                close_lists_to_level(0)
            if list_buffer:
                elementos.append({"tipo": "texto", "conteudo": "".join(list_buffer)})
                list_buffer = []
            list_open_stack = []
            current_list_id = None

        def get_list_tag_for(list_id: Optional[str], level: int) -> str:
            if not list_id:
                return "ul"
            props = (lists_index.get(list_id) or {}).get("listProperties") or {}
            nesting = (props.get("nestingLevels") or [])
            cfg = nesting[level] if level < len(nesting) else {}
            if cfg.get("glyphType") or cfg.get("glyphFormat"):
                return "ol"
            return "ul"

        # Caminha pela estrutura do corpo do documento
        for el in body:
            # Tabelas
            table = el.get("table")
            if table:
                flush_list_if_any()
                rows_html: List[str] = []
                for row in table.get("tableRows", []):
                    cells_html: List[str] = []
                    for cell in row.get("tableCells", []):
                        cell_parts: List[str] = []
                        for ccontent in cell.get("content", []):
                            cpara = ccontent.get("paragraph")
                            if cpara:
                                cell_parts.append(render_paragraph_inline(cpara))
                        cells_html.append(f"<td>{''.join(cell_parts)}</td>")
                    rows_html.append(f"<tr>{''.join(cells_html)}</tr>")
                table_html = f"<table><tbody>{''.join(rows_html)}</tbody></table>"
                text_buffer.append(table_html)
                continue

            para = el.get("paragraph")
            if not para:
                continue

            named = (para.get("paragraphStyle") or {}).get("namedStyleType", "NORMAL_TEXT")
            bullet = para.get("bullet")

            if bullet:
                list_id = bullet.get("listId")
                level = int(bullet.get("nestingLevel", 0))
                tag = get_list_tag_for(list_id, level)
                if current_list_id is None or current_list_id != list_id:
                    if text_buffer:
                        elementos.append({"tipo": "texto", "conteudo": "".join(text_buffer).strip()})
                        text_buffer = []
                    flush_list_if_any()
                    current_list_id = list_id
                while len(list_open_stack) < level + 1:
                    to_open_tag = get_list_tag_for(list_id, len(list_open_stack))
                    list_open_stack.append(to_open_tag)
                    list_buffer.append(f"<{to_open_tag}>")
                if len(list_open_stack) > level + 1:
                    close_lists_to_level(level + 1)
                content_html = render_paragraph_inline(para).rstrip("\n")
                list_buffer.append(f"<li>{content_html}</li>")
                continue

            if list_open_stack:
                flush_list_if_any()

            paragraph_text = render_paragraph_inline(para)

            if named in ("TITLE", "HEADING_1", "HEADING_2", "HEADING_3"):
                if text_buffer:
                    elementos.append({"tipo": "texto", "conteudo": "".join(text_buffer).strip()})
                    text_buffer = []
                hlevel = {"TITLE": 1, "HEADING_1": 1, "HEADING_2": 2, "HEADING_3": 3}.get(named, 1)
                elementos.append({"tipo": "texto", "conteudo": "#" * hlevel + " " + (paragraph_text or "").strip() + "\n"})
            else:
                text_buffer.append(paragraph_text)

        if text_buffer:
            elementos.append({"tipo": "texto", "conteudo": "".join(text_buffer).strip()})
        if 'list_open_stack' in locals() and list_open_stack:
            flush_list_if_any()

        # Explode custom blocks dentro dos textos
        elementos = self._explode_custom_blocks(elementos)

        return ParsedDoc(
            titulo=title,
            elementos_ordenados=elementos,
            data_geracao=datetime.now().strftime('%d/%m/%Y %H:%M'),
        )

    # -----------------
    def _explode_custom_blocks(self, elementos: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Separa blocos customizados encontrados em textos.

        Busca dentro de cada elemento de texto sequências delimitadas
        por tags definidas em ``CUSTOM_TAGS``. Para cada ocorrência,
        cria um novo elemento com tipo igual ao nome da tag e dados
        processados pelo método ``_parse_block``.
        """
        out: List[Dict[str, Any]] = []
        for el in elementos:
            if el.get("tipo") != "texto":
                out.append(el)
                continue
            text = el.get("conteudo", "")
            consumed = set()
            for key, pat in CUSTOM_TAGS.items():
                for m in re.finditer(pat, text, flags=re.DOTALL | re.IGNORECASE | re.MULTILINE):
                    consumed.add((m.start(), m.end(), key, m.group(1)))
            if not consumed:
                out.append(el)
                continue
            parts = sorted(list(consumed), key=lambda x: x[0])
            cursor = 0
            for start, end, key, raw in parts:
                if start > cursor:
                    out.append({"tipo": "texto", "conteudo": text[cursor:start].strip()})
                out.append({"tipo": key, "dados": self._parse_block(key, raw)})
                cursor = end
            if cursor < len(text):
                out.append({"tipo": "texto", "conteudo": text[cursor:].strip()})
        return [e for e in out if not (e.get("tipo") == "texto" and not (e.get("conteudo") or "").strip())]

    def _parse_block(self, key: str, raw: str) -> Any:
        """Processa o conteúdo cru de um bloco customizado.

        Para cada tipo de bloco, existe uma lógica específica para
        transformar o conteúdo textual em dados estruturados. Este
        método replica a lógica existente no script original, mas
        agrupa as implementações num único lugar para facilitar
        manutenção e futuras extensões.
        """
        raw = raw.strip()
        if key == 'cabecalho':
            data: Dict[str, str] = {}
            for line in raw.splitlines():
                if ':' in line:
                    k, v = line.split(':', 1)
                    data[k.strip()] = v.strip()
            return data
        if key == 'tags':
            return re.findall(r'#(\w+)', raw)
        if key == 'objetivos':
            items: List[str] = []
            for line in raw.splitlines():
                line = line.strip()
                if not line:
                    continue
                if line[0:1] in ['*', '-', '•']:
                    items.append(line[1:].strip())
                else:
                    items.append(line)
            return items
        if key == 'conceitos':
            out_conc: List[Dict[str, str]] = []
            for line in raw.splitlines():
                if '|' in line:
                    a, b = line.split('|', 1)
                    out_conc.append({'termo': a.strip(), 'definicao': b.strip()})
            return out_conc
        if key == 'quadro_comparativo':
            lines = [l.strip() for l in raw.splitlines() if l.strip()]
            result = {'titulo': '', 'headers': [], 'rows': []}
            for line in lines:
                if line.lower().startswith('titulo:'):
                    result['titulo'] = line.split(':', 1)[1].strip()
                elif '|' in line:
                    cells = [c.strip() for c in line.split('|')]
                    if not result['headers']:
                        result['headers'] = cells
                    else:
                        row = {h: (cells[i] if i < len(cells) else '') for i, h in enumerate(result['headers'])}
                        result['rows'].append(row)
            return result
        if key == 'checklist':
            items: List[Dict[str, Any]] = []
            for line in raw.splitlines():
                if not line.strip():
                    continue
                checked = '[x]' in line.lower()
                line_clean = line.replace('[x]', '').replace('[X]', '').replace('[ ]', '').strip()
                prio = 'Media'
                if '|' in line_clean:
                    a, *rest = [p.strip() for p in line_clean.split('|')]
                    line_clean = a
                    if rest:
                        prio = rest[0]
                items.append({'item': line_clean, 'concluido': checked, 'prioridade': prio})
            return items
        if key == 'timeline':
            out_tl: List[Dict[str, str]] = []
            for line in raw.splitlines():
                if '|' in line:
                    p = [x.strip() for x in line.split('|')]
                    out_tl.append({'data': p[0], 'evento': p[1], 'tipo': p[2] if len(p) > 2 else 'normal'})
            return out_tl
        if key == 'introducao':
            return raw
        if key == 'glossario':
            out_gloss: List[Dict[str, str]] = []
            for line in raw.splitlines():
                if '|' in line:
                    t, d = line.split('|', 1)
                    if t.strip() and d.strip():
                        out_gloss.append({'termo': t.strip(), 'definicao': d.strip()})
            return out_gloss
        if key == 'dica' or key == 'alerta':
            return raw
        if key == 'rodape':
            data: Dict[str, str] = {}
            for line in raw.splitlines():
                if ':' in line:
                    k, v = line.split(':', 1)
                    data[k.strip().lower().replace(' ', '_')] = v.strip()
            return data
        if key in ('grafico_linha', 'grafico_pizza'):
            data_graf: Dict[str, Any] = {'titulo': '', 'series': []}
            for line in raw.splitlines():
                line = line.strip()
                if not line:
                    continue
                if line.lower().startswith('titulo:'):
                    data_graf['titulo'] = line.split(':', 1)[1].strip()
                elif ':' in line:
                    l, v = line.split(':', 1)
                    try:
                        val: Any = float(v.strip().replace(',', '.'))
                    except Exception:
                        val = v.strip()
                        # attempt to cast to number fails
                    data_graf['series'].append({'label': l.strip(), 'valor': val})
            return data_graf
        if key == 'grafico_barras':
            data_bar: Dict[str, Any] = {'titulo': '', 'categorias': [], 'series': []}
            for line in raw.splitlines():
                line = line.strip()
                if not line:
                    continue
                if line.lower().startswith('titulo:'):
                    data_bar['titulo'] = line.split(':', 1)[1].strip()
                elif line.lower().startswith('categorias:'):
                    cats = line.split(':', 1)[1]
                    data_bar['categorias'] = [c.strip() for c in cats.split(',')]
                elif ':' in line:
                    nome, vals = line.split(':', 1)
                    nums = [v.strip() for v in vals.split(',')]
                    parsed: List[Any] = []
                    for x in nums:
                        try:
                            parsed.append(float(x.replace(',', '.')))
                        except Exception:
                            parsed.append(x)
                    data_bar['series'].append({'nome': nome.strip(), 'valores': parsed})
            return data_bar
        if key == 'tabela_performance':
            lines = [l.strip() for l in raw.splitlines() if l.strip()]
            headers: List[str] = []
            rows: List[List[str]] = []
            total: Optional[List[str]] = None
            for i, line in enumerate(lines):
                if i == 0 and '|' in line:
                    headers = [c.strip() for c in line.split('|')]
                elif line.lower().startswith('total:'):
                    total = [c.strip() for c in line.split(':', 1)[1].split('|')]
                elif '|' in line:
                    cells = [c.strip() for c in line.split('|')]
                    rows.append(cells)
            return {'headers': headers, 'rows': rows, 'total': total}
        if key == 'cronograma':
            headers_cr: List[str] = []
            rows_cr: List[List[str]] = []
            for i, line in enumerate([l for l in raw.splitlines() if l.strip()]):
                if i == 0 and '|' in line and not line.lower().startswith('fase '):
                    headers_cr = [c.strip() for c in line.split('|')]
                elif '|' in line:
                    rows_cr.append([c.strip() for c in line.split('|')])
            if not headers_cr:
                headers_cr = ['Fase', 'Início', 'Fim', 'Responsável', 'Status']
            return {'headers': headers_cr, 'rows': rows_cr}
        if key == 'lista_aninhada':
            blocos: List[Dict[str, Any]] = []
            atual: Optional[Dict[str, Any]] = None
            for line in raw.splitlines():
                s = line.strip()
                if not s:
                    continue
                if not s.startswith('-'):
                    if atual:
                        blocos.append(atual)
                    atual = {'titulo': s, 'itens': []}
                else:
                    if not atual:
                        atual = {'titulo': '', 'itens': []}
                    atual['itens'].append(s.lstrip('-').strip())
            if atual:
                blocos.append(atual)
            return blocos
        if key == 'citacao':
            data_ct: Dict[str, str] = {'texto': '', 'autor': '', 'ano': ''}
            for line in raw.splitlines():
                if ':' in line:
                    k, v = line.split(':', 1)
                    data_ct[k.strip().lower()] = v.strip()
            return data_ct
        if key == 'formula':
            # Para fórmulas, esperamos linhas no formato "campo: valor".
            # Campos reconhecidos: nome, formula e descricao.
            data_f: Dict[str, str] = {'nome': '', 'formula': '', 'descricao': ''}
            for line in raw.splitlines():
                if ':' in line:
                    k, v = line.split(':', 1)
                    k = k.strip().lower()
                    if k in ('nome', 'formula', 'descricao'):
                        data_f[k] = v.strip()
            return data_f
        if key == 'fluxograma':
            nodes: List[str] = [l.strip() for l in raw.splitlines() if l.strip()]
            return {'nodes': nodes}
        if key == 'codigo':
            data_code: Dict[str, Any] = {'linguagem': '', 'titulo': '', 'descricao': '', 'codigo': ''}
            buff_code: List[str] = []
            for line in raw.splitlines():
                if ':' in line and not buff_code:
                    k, v = line.split(':', 1)
                    k = k.strip().lower()
                    if k in ('linguagem', 'titulo', 'descricao'):
                        data_code[k] = v.strip()
                        continue
                buff_code.append(line)
            data_code['codigo'] = '\n'.join(buff_code).strip()
            return data_code
        if key == 'questao_multipla':
            data_qm: Dict[str, Any] = {'pergunta': '', 'alternativas': [], 'resposta': ''}
            for line in raw.splitlines():
                s = line.strip()
                if not s:
                    continue
                if s.lower().startswith('pergunta:'):
                    data_qm['pergunta'] = s.split(':', 1)[1].strip()
                    continue
                if s.lower().startswith('resposta:'):
                    data_qm['resposta'] = s.split(':', 1)[1].strip()
                    continue
                data_qm['alternativas'].append(s)
            return data_qm
        if key == 'topicos':
            items_top: List[Dict[str, Any]] = []
            cur: Optional[Dict[str, Any]] = None
            for line in raw.splitlines():
                s = line.rstrip()
                if not s:
                    continue
                if re.match(r'^\d+\.\s', s):
                    if cur:
                        items_top.append(cur)
                    cur = {
                        'titulo': re.sub(r'^\d+\.\s*', '', s).strip(),
                        'texto': [],
                        'subitens': [],
                    }
                elif s.lstrip().startswith('-'):
                    if not cur:
                        cur = {'titulo': '', 'texto': [], 'subitens': []}
                    cur['subitens'].append(s.lstrip('-').strip())
                else:
                    if not cur:
                        cur = {'titulo': '', 'texto': [], 'subitens': []}
                    cur['texto'].append(s.strip())
            if cur:
                items_top.append(cur)
            return items_top
        if key == 'questao_dissertativa':
            return {'pergunta': raw.strip()}
        if key == 'metricas':
            rows_met: List[List[str]] = []
            for line in raw.splitlines():
                if '|' in line:
                    rows_met.append([c.strip() for c in line.split('|')])
            return {'rows': rows_met}
        if key == 'progresso':
            itens_prog: List[Dict[str, Any]] = []
            for line in raw.splitlines():
                if ':' in line:
                    a, b = line.split(':', 1)
                    try:
                        val: Any = float(b.strip().replace(',', '.'))
                    except Exception:
                        val = b.strip()
                    itens_prog.append({'label': a.strip(), 'valor': val})
            return {'items': itens_prog}
        if key == 'referencias':
            refs: List[str] = [l.strip() for l in raw.splitlines() if l.strip()]
            return {'refs': refs}
        if key == 'links':
            links: List[Dict[str, str]] = []
            for line in raw.splitlines():
                if '|' in line:
                    t, u = line.split('|', 1)
                    links.append({'texto': t.strip(), 'url': u.strip()})
            return {'links': links}
        if key == 'qrcode':
            url: str = ''
            for line in raw.splitlines():
                if ':' in line:
                    k, v = line.split(':', 1)
                    if k.strip().lower() == 'url':
                        url = v.strip()
            return {'url': url or raw.strip()}
        if key == 'barcode':
            codigo: str = ''
            for line in raw.splitlines():
                if ':' in line:
                    k, v = line.split(':', 1)
                    if k.strip().lower() in ('codigo', 'código'):
                        codigo = v.strip()
            return {'codigo': codigo or raw.strip()}
        # Caso não exista parser específico, retorna o texto cru
        return raw
