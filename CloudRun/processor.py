# -*- coding: utf-8 -*-
"""F
Lab Resumos – Processor (Google Docs API JSON-first)

Mudanças-chave:
- Sai o "export HTML"; entra Google Docs API (documents.get) + Drive p/ mídia.
- Parser lê body.content -> paragraphs, headings, lists, inlineObjects.
- Mantém sintaxe customizada {{...}} do teu template, mas agora detecta em texto limpo.
- Imagens: resolve contentUri (curto prazo) e embute base64.
- Saída continua em PDF via WeasyPrint usando template Jinja refeito.

Requisitos:
  pip install google-api-python-client google-auth google-auth-httplib2 google-auth-oauthlib \
              requests jinja2 weasyprint qrcode

Auth:
- Recomendo ADC (Application Default Credentials). Exponha GOOGLE_APPLICATION_CREDENTIALS
  ou rode "gcloud auth application-default login" em dev.
- Scopes: Docs readonly, Drive readonly.
"""

from __future__ import annotations
import os
import re
import io
import json
import base64
import logging
import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Dict, List, Optional
from datetime import datetime

import requests
import qrcode
from jinja2 import Environment, FileSystemLoader, select_autoescape
from weasyprint import HTML, CSS

from google.oauth2 import service_account
from google.auth.transport.requests import AuthorizedSession, Request
from googleapiclient.discovery import build

# ---------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------
# URL do documento Google Docs (hardcoded)
# DOCUMENT_URL = "https://docs.google.com/document/d/1uH48T6uJAeUqeoYvymLQpZ_r8CO5ZZX0ZrK2wt9Cq8Q/edit?tab=t.0"
# DOCUMENT_URL = "https://docs.google.com/document/d/1mQh2krssppvvnBEZRS3JfJpF6atmvj_wxgw3CnQj6nE/edit?tab=t.0"
#DOCUMENT_URL = "https://docs.google.com/document/d/1-dW8bfYVlLbK3roKymkNfVeZz0oP66HCk0JYAta4w9s/edit?tab=t.0#heading=h.y7ygbva7njh0"
#DOCUMENT_URL = "https://docs.google.com/document/d/1ydnL0EV3az72DpAn5ERdAWFqKru7h1uZvFWaoe7Dt6A/edit?tab=t.0"
DOCUMENT_URL = "https://docs.google.com/document/d/1KA3mEr28ZpX48QkUhvRiC0oYHEID0QUGWradkRo6014/edit?tab=t.7iznbhuhmpc#heading=h.1fbvz7kwg518"

DOCS_SCOPE = "https://www.googleapis.com/auth/documents.readonly"
DRIVE_SCOPE = "https://www.googleapis.com/auth/drive.readonly"
SCOPES = [DOCS_SCOPE, DRIVE_SCOPE]

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("labresumos.api")

# ---------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------

def load_credentials() -> Any:
    """Carrega credenciais via ADC ou Service Account JSON."""
    key_path = os.getenv("GOOGLE_APPLICATION_CREDENTIALS")
    if key_path and Path(key_path).exists():
        creds = service_account.Credentials.from_service_account_file(key_path, scopes=SCOPES)
        return creds
    # Fallback para ADC no ambiente (ex.: gcloud auth application-default login)
    from google.auth import default
    creds, _ = default(scopes=SCOPES)
    return creds


def extract_doc_id(source: str) -> str:
    # Padrão 1: /document/d/ID
    m = re.search(r"/document/d/([a-zA-Z0-9-_]+)", source)
    if m:
        return m.group(1)
    
    # Padrão 2: open?id=ID ou &id=ID
    m = re.search(r'[?&]id=([a-zA-Z0-9-_]+)', source)
    if m:
        return m.group(1)
    
    # Se não encontrou, retorna source
    return source


def b64_png(data: bytes) -> str:
    return base64.b64encode(data).decode("utf-8")


def generate_qr_code(data: str) -> str:
    try:
        qr = qrcode.QRCode(version=1, error_correction=qrcode.constants.ERROR_CORRECT_M, box_size=4, border=2)
        qr.add_data(data)
        qr.make(fit=True)
        img = qr.make_image(fill_color="black", back_color="white")
        buf = io.BytesIO()
        img.save(buf, format="PNG")
        return base64.b64encode(buf.getvalue()).decode()
    except Exception as e:
        logger.exception("QR error: %s", e)
        return ""


def generate_line_chart(data: Dict[str, Any]) -> str:
    """Gera gráfico de linha em base64 usando Plotly com identidade visual Lab."""
    try:
        logger.info("📊 Gerando gráfico de linha...")
        chart_start_time = time.time()
        import plotly.graph_objects as go
        import plotly.io as pio
        
        # Cores da identidade visual
        PRIMARY_YELLOW = '#F1CC00'
        PRIMARY_BLUE = '#2A6B9F'
        PRIMARY_DARK = '#333B49'
        BG_LIGHT = '#F3F1E8'
        
        # Dados
        labels = [s['label'] for s in data.get('series', [])]
        values = [float(s['valor']) for s in data.get('series', [])]
        
        # Criar figura
        fig = go.Figure()
        
        # Adicionar linha principal
        fig.add_trace(go.Scatter(
            x=labels,
            y=values,
            mode='lines+markers+text',
            line=dict(color=PRIMARY_BLUE, width=3),
            marker=dict(
                size=12,
                color=PRIMARY_YELLOW,
                line=dict(color=PRIMARY_DARK, width=2)
            ),
            text=[f'{v:.1f}' for v in values],
            textposition='top center',
            textfont=dict(size=11, color=PRIMARY_DARK, family='Figtree, sans-serif'),
            fill='tozeroy',
            fillcolor='rgba(42, 107, 159, 0.1)',
            name=''
        ))
        
        # Layout moderno e limpo
        fig.update_layout(
            title=dict(
                text=data.get('titulo', 'Gráfico'),
                font=dict(size=16, color=PRIMARY_DARK, family='Figtree, sans-serif'),
                x=0.5,
                xanchor='center'
            ),
            plot_bgcolor='white',
            paper_bgcolor=BG_LIGHT,
            margin=dict(l=40, r=40, t=60, b=40),
            height=400,
            showlegend=False,
            xaxis=dict(
                showgrid=False,
                showline=True,
                linecolor=PRIMARY_DARK,
                tickfont=dict(size=10, color=PRIMARY_DARK, family='Figtree, sans-serif')
            ),
            yaxis=dict(
                showgrid=True,
                gridcolor='rgba(51, 59, 73, 0.1)',
                showline=True,
                linecolor=PRIMARY_DARK,
                tickfont=dict(size=10, color=PRIMARY_DARK, family='Figtree, sans-serif')
            )
        )
        
        # Converter para base64
        render_start_time = time.time()
        img_bytes = pio.to_image(fig, format='png', width=800, height=400, scale=2)
        render_time = time.time() - render_start_time
        
        encode_start_time = time.time()
        result = base64.b64encode(img_bytes).decode('utf-8')
        encode_time = time.time() - encode_start_time
        
        total_chart_time = time.time() - chart_start_time
        logger.info(f"✅ Gráfico de linha gerado em {total_chart_time:.2f}s (render: {render_time:.2f}s, encode: {encode_time:.2f}s)")
        return result
        
    except Exception as e:
        logger.warning("❌ Erro ao gerar gráfico de linha: %s", e)
        return ""


def generate_pie_chart(data: Dict[str, Any]) -> str:
    """Gera gráfico de pizza em base64 usando Plotly com identidade visual Lab."""
    try:
        logger.info("📊 Gerando gráfico de pizza...")
        chart_start_time = time.time()
        import plotly.graph_objects as go
        import plotly.io as pio
        
        # Cores da identidade visual
        PRIMARY_YELLOW = '#F1CC00'
        PRIMARY_BLUE = '#2A6B9F'
        PRIMARY_DARK = '#333B49'
        LIGHT_BLUE = '#5B8DB8'
        LIGHT_GRAY = '#8A9199'
        BG_LIGHT = '#F3F1E8'
        
        # Dados
        labels = [s['label'] for s in data.get('series', [])]
        values = [float(s['valor']) for s in data.get('series', [])]
        
        # Paleta de cores personalizada
        colors = [PRIMARY_YELLOW, PRIMARY_BLUE, LIGHT_BLUE, LIGHT_GRAY, PRIMARY_DARK]
        # Repetir cores se necessário
        while len(colors) < len(values):
            colors.extend(colors[:len(values) - len(colors)])
        colors = colors[:len(values)]
        
        # Criar figura
        fig = go.Figure(data=[go.Pie(
            labels=labels,
            values=values,
            hole=0.3,  # Donut style
            marker=dict(
                colors=colors,
                line=dict(color='white', width=2)
            ),
            textinfo='label+percent',
            textposition='outside',
            textfont=dict(
                size=11,
                color=PRIMARY_DARK,
                family='Figtree, sans-serif'
            )
        )])
        
        # Layout moderno
        fig.update_layout(
            title=dict(
                text=data.get('titulo', 'Gráfico de Pizza'),
                font=dict(size=16, color=PRIMARY_DARK, family='Figtree, sans-serif'),
                x=0.5,
                xanchor='center'
            ),
            paper_bgcolor=BG_LIGHT,
            plot_bgcolor='white',
            margin=dict(l=40, r=40, t=60, b=40),
            height=500,
            showlegend=True,
            legend=dict(
                orientation="v",
                yanchor="middle",
                y=0.5,
                xanchor="left",
                x=1.02,
                font=dict(size=10, color=PRIMARY_DARK, family='Figtree, sans-serif')
            )
        )
        
        # Converter para base64
        render_start_time = time.time()
        img_bytes = pio.to_image(fig, format='png', width=800, height=500, scale=2)
        render_time = time.time() - render_start_time
        
        encode_start_time = time.time()
        result = base64.b64encode(img_bytes).decode('utf-8')
        encode_time = time.time() - encode_start_time
        
        total_chart_time = time.time() - chart_start_time
        logger.info(f"✅ Gráfico de pizza gerado em {total_chart_time:.2f}s (render: {render_time:.2f}s, encode: {encode_time:.2f}s)")
        return result
        
    except Exception as e:
        logger.warning("❌ Erro ao gerar gráfico de pizza: %s", e)
        return ""


def generate_bar_chart(data: Dict[str, Any]) -> str:
    """Gera gráfico de barras em base64 usando Plotly com identidade visual Lab."""
    try:
        logger.info("📊 Gerando gráfico de barras...")
        chart_start_time = time.time()
        import plotly.graph_objects as go
        import plotly.io as pio
        
        # Cores da identidade visual
        PRIMARY_YELLOW = '#F1CC00'
        PRIMARY_BLUE = '#2A6B9F'
        PRIMARY_DARK = '#333B49'
        LIGHT_BLUE = '#5B8DB8'
        BG_LIGHT = '#F3F1E8'
        
        # Dados
        categorias = data.get('categorias', [])
        series = data.get('series', [])
        
        if not categorias or not series:
            return ""
        
        colors = [PRIMARY_BLUE, PRIMARY_YELLOW, LIGHT_BLUE, PRIMARY_DARK]
        
        # Criar figura
        fig = go.Figure()
        
        # Adicionar cada série
        for i, serie in enumerate(series):
            valores = serie.get('valores', [])
            if valores and isinstance(valores[0], str):
                # Tentar converter strings para números
                try:
                    valores = [float(v.replace(',', '.')) for v in valores]
                except:
                    valores = list(range(len(valores)))  # fallback
            
            color = colors[i % len(colors)]
            
            fig.add_trace(go.Bar(
                name=serie.get('nome', f'Série {i+1}'),
                x=categorias,
                y=valores,
                marker=dict(
                    color=color,
                    line=dict(color='white', width=1)
                ),
                text=[f'{v:.1f}' if isinstance(v, float) else str(v) for v in valores],
                textposition='outside',
                textfont=dict(
                    size=10,
                    color=PRIMARY_DARK,
                    family='Figtree, sans-serif'
                )
            ))
        
        # Layout moderno
        fig.update_layout(
            title=dict(
                text=data.get('titulo', 'Gráfico de Barras'),
                font=dict(size=16, color=PRIMARY_DARK, family='Figtree, sans-serif'),
                x=0.5,
                xanchor='center'
            ),
            plot_bgcolor='white',
            paper_bgcolor=BG_LIGHT,
            margin=dict(l=40, r=40, t=60, b=40),
            height=400,
            xaxis=dict(
                showgrid=False,
                showline=True,
                linecolor=PRIMARY_DARK,
                tickfont=dict(size=10, color=PRIMARY_DARK, family='Figtree, sans-serif'),
                tickangle=45 if len(max(categorias, key=len)) > 10 else 0
            ),
            yaxis=dict(
                showgrid=True,
                gridcolor='rgba(51, 59, 73, 0.1)',
                showline=True,
                linecolor=PRIMARY_DARK,
                tickfont=dict(size=10, color=PRIMARY_DARK, family='Figtree, sans-serif')
            ),
            barmode='group' if len(series) > 1 else 'relative',
            showlegend=len(series) > 1,
            legend=dict(
                orientation="h",
                yanchor="bottom",
                y=1.02,
                xanchor="center",
                x=0.5,
                font=dict(size=10, color=PRIMARY_DARK, family='Figtree, sans-serif')
            )
        )
        
        # Converter para base64
        render_start_time = time.time()
        img_bytes = pio.to_image(fig, format='png', width=800, height=400, scale=2)
        render_time = time.time() - render_start_time
        
        encode_start_time = time.time()
        result = base64.b64encode(img_bytes).decode('utf-8')
        encode_time = time.time() - encode_start_time
        
        total_chart_time = time.time() - chart_start_time
        logger.info(f"✅ Gráfico de barras gerado em {total_chart_time:.2f}s (render: {render_time:.2f}s, encode: {encode_time:.2f}s)")
        return result
        
    except Exception as e:
        logger.warning("❌ Erro ao gerar gráfico de barras: %s", e)
        return ""


def generate_references_visual(data: Dict[str, Any]) -> str:
    """Gera visualização compacta das referências bibliográficas."""
    try:
        import matplotlib
        matplotlib.use('Agg')
        import matplotlib.pyplot as plt
        import matplotlib.patches as patches
        from matplotlib.patches import FancyBboxPatch
        import textwrap
        
        # Cores da identidade visual
        YELLOW = '#F1CC00'
        DARK = '#333B49'
        BLUE = '#2A6B9F'
        LIGHT_BG = '#F3F1E8'
        
        # Processar referências
        refs = data.get('refs', [])
        if not refs:
            return ""
        
        # Calcular altura dinâmica baseada no número de referências
        num_refs = min(len(refs), 10)  # Máximo 10 referências
        card_height = 0.8
        spacing = 0.1
        title_height = 0.6
        padding = 0.4
        
        total_height = title_height + (num_refs * (card_height + spacing)) + padding
        
        # Criar figura compacta
        fig = plt.figure(figsize=(10, total_height), dpi=120)
        ax = fig.add_subplot(111)
        ax.set_xlim(0, 10)
        ax.set_ylim(0, total_height)
        ax.axis('off')
        
        # Background sutil
        bg_rect = FancyBboxPatch((0.2, 0.1), 9.6, total_height - 0.2,
                                boxstyle="round,pad=0.1",
                                facecolor=LIGHT_BG, alpha=0.3,
                                edgecolor='none')
        ax.add_patch(bg_rect)
        
        # Título compacto com ícone
        title_y = total_height - 0.4
        
        # Ícone de livro pequeno
        book_icon = patches.Rectangle((0.5, title_y - 0.15), 0.3, 0.3,
                                    facecolor=YELLOW, edgecolor=DARK, linewidth=1)
        ax.add_patch(book_icon)
        
        # Linhas do livro
        for i in range(2):
            line = patches.Rectangle((0.55, title_y - 0.05 + i*0.08), 0.2, 0.02,
                                   facecolor=DARK, alpha=0.3)
            ax.add_patch(line)
        
        # Título
        ax.text(1, title_y, '📚 Referências Bibliográficas', fontsize=14, 
               fontweight='bold', color=DARK,
               ha='left', va='center', fontfamily='sans-serif')
        
        # Linha decorativa sob o título
        line_rect = patches.Rectangle((0.5, title_y - 0.3), 9, 0.02,
                                    facecolor=YELLOW, alpha=0.8)
        ax.add_patch(line_rect)
        
        # Processar referências
        y_position = title_y - 0.6
        
        for i, ref in enumerate(refs, 1):
            if i > 10:  # Limitar a 10 referências
                break
                
            # Detectar ano (4 dígitos)
            import re
            year_match = re.search(r'\b(19|20)\d{2}\b', ref)
            year = year_match.group() if year_match else ''
            
            # Card compacto para cada referência
            card = FancyBboxPatch((0.5, y_position - card_height), 9, card_height,
                                 boxstyle="round,pad=0.03",
                                 facecolor='white',
                                 edgecolor=BLUE if i % 2 else DARK,
                                 linewidth=1,
                                 alpha=0.95)
            ax.add_patch(card)
            
            # Número da referência (menor)
            circle = patches.Circle((0.9, y_position - card_height/2), 0.15,
                                   facecolor=YELLOW, edgecolor=DARK, linewidth=1.5)
            ax.add_patch(circle)
            ax.text(0.9, y_position - card_height/2, str(i), 
                   fontsize=9, fontweight='bold', color=DARK,
                   ha='center', va='center')
            
            # Texto da referência (mais compacto)
            wrapped_text = textwrap.fill(ref, width=80)
            lines = wrapped_text.split('\n')
            
            # Primeira linha (autor)
            if lines:
                first_line = lines[0][:70] + ('...' if len(lines[0]) > 70 else '')
                ax.text(1.3, y_position - 0.25, first_line,
                       fontsize=9, fontweight='bold', color=DARK,
                       ha='left', va='center', fontfamily='sans-serif')
            
            # Segunda linha (resto)
            if len(lines) > 1:
                rest_text = ' '.join(lines[1:])[:90] + ('...' if len(' '.join(lines[1:])) > 90 else '')
                ax.text(1.3, y_position - 0.55, rest_text,
                       fontsize=8, color='#666666',
                       ha='left', va='center', fontfamily='sans-serif')
            
            # Ano em badge pequeno
            if year:
                year_box = FancyBboxPatch((8.2, y_position - 0.6), 0.6, 0.25,
                                         boxstyle="round,pad=0.02",
                                         facecolor=YELLOW, alpha=0.4,
                                         edgecolor=DARK, linewidth=0.5)
                ax.add_patch(year_box)
                ax.text(8.5, y_position - 0.47, year,
                       fontsize=7, fontweight='bold', color=DARK,
                       ha='center', va='center')
            
            y_position -= (card_height + spacing)
        
        plt.tight_layout()
        
        # Converter para base64
        buf = io.BytesIO()
        fig.savefig(buf, format='PNG', bbox_inches='tight', 
                   facecolor='white', dpi=120)
        buf.seek(0)
        plt.close(fig)
        
        return base64.b64encode(buf.read()).decode('utf-8')
        
    except Exception as e:
        logger.warning("Erro ao gerar visual de referências: %s", e)
        return ""

# ---------------------------------------------------------------------
# Google APIs client
# ---------------------------------------------------------------------

@dataclass
class DocsClient:
    creds: Any
    docs_service: Any = field(init=False)
    drive_service: Any = field(init=False)
    authed: AuthorizedSession = field(init=False)

    def __post_init__(self):
        self.docs_service = build("docs", "v1", credentials=self.creds, cache_discovery=False)
        self.drive_service = build("drive", "v3", credentials=self.creds, cache_discovery=False)
        self.authed = AuthorizedSession(self.creds)

    def get_document(self, doc_id: str) -> Dict[str, Any]:
        return self.docs_service.documents().get(documentId=doc_id).execute()

    def download_drive_file(self, file_id: str) -> bytes:
        # Para blobs do Drive (não para contentUri temporário)
        logger.info(f"📁 Baixando arquivo do Drive: {file_id}")
        start_time = time.time()
        url = f"https://www.googleapis.com/drive/v3/files/{file_id}?alt=media"
        resp = self.authed.get(url, timeout=15)  # Reduzido de 60s para 15s
        resp.raise_for_status()
        download_time = time.time() - start_time
        logger.info(f"✅ Arquivo baixado em {download_time:.2f}s ({len(resp.content)} bytes)")
        return resp.content

    def fetch_content_uri(self, uri: str) -> bytes:
        # contentUri é temporário e requer header Authorization
        # Tenta refresh de credenciais em caso de 401/403 uma vez
        logger.info(f"🌐 Baixando imagem de: {uri[:50]}...")
        start_time = time.time()
        
        last_resp = None
        for attempt in range(2):
            try:
                resp = self.authed.get(uri, timeout=15)  # Reduzido de 60s para 15s
                last_resp = resp
                if resp.status_code in (401, 403) and attempt == 0:
                    logger.info("🔄 Tentando refresh das credenciais...")
                    try:
                        self.creds.refresh(Request())
                    except Exception as e:
                        logger.warning(f"❌ Falha no refresh: {e}")
                    continue
                resp.raise_for_status()
                download_time = time.time() - start_time
                logger.info(f"✅ Imagem baixada em {download_time:.2f}s ({len(resp.content)} bytes)")
                return resp.content
            except Exception as e:
                logger.warning(f"❌ Tentativa {attempt + 1} falhou: {e}")
                if attempt == 1:  # Última tentativa
                    raise
                time.sleep(1)  # Aguarda 1s antes de tentar novamente
        
        if last_resp is not None:
            last_resp.raise_for_status()
        raise RuntimeError("Falha ao baixar contentUri")

# ---------------------------------------------------------------------
# Parser (Docs JSON -> modelo interno)
# ---------------------------------------------------------------------

CUSTOM_TAGS = {
    # já existentes …
    'grafico_linha': r'\{\{grafico_linha\}\}(.*?)\{\{\/grafico_linha\}\}',
    'grafico_pizza': r'\{\{grafico_pizza\}\}(.*?)\{\{\/grafico_pizza\}\}',
    'grafico_barras': r'\{\{grafico_barras\}\}(.*?)\{\{\/grafico_barras\}\}',
    'lista_aninhada': r'\{\{lista_aninhada\}\}(.*?)\{\{\/lista_aninhada\}\}',
    'citacao': r'\{\{citacao\}\}(.*?)\{\{\/citacao\}\}',
    'formula': r'\{\{formula\}\}(.*?)\{\{\/formula\}\}',
    'fluxograma': r'\{\{fluxograma\}\}(.*?)\{\{\/fluxograma\}\}',
    'codigo': r'\{\{codigo\}\}(.*?)\{\{\/codigo\}\}',
    'questao_multipla': r'\{\{questao_multipla\}\}(.*?)\{\{\/questao_multipla\}\}',
    'referencias': r'\{\{referencias\}\}(.*?)\{\{\/referencias\}\}',
    'qrcode': r'\{\{qrcode\}\}(.*?)\{\{\/qrcode\}\}',
    'barcode': r'\{\{barcode\}\}(.*?)\{\{\/barcode\}\}',

    # NOVAS (presentes no Doc/PDF atual)
    'cabecalho': r'\{\{cabecalho\}\}(.*?)\{\{\/cabecalho\}\}',
    'tags': r'\{\{tags\}\}(.*?)\{\{\/tags\}\}',
    'conceitos': r'\{\{conceitos\}\}(.*?)\{\{\/conceitos\}\}',
    'timeline': r'\{\{timeline\}\}(.*?)\{\{\/timeline\}\}',
    'dica': r'\{\{dica\}\}(.*?)\{\{\/dica\}\}',
    'alerta': r'\{\{alerta\}\}(.*?)\{\{\/alerta\}\}',
    'questao_dissertativa': r'\{\{questao_dissertativa\}\}(.*?)\{\{\/questao_dissertativa\}\}',
    
    # ADICIONAR padrões ausentes para corrigir blocos especiais
    'glossario': r'\{\{glossario\}\}(.*?)\{\{\/glossario\}\}',
    'checklist': r'\{\{checklist\}\}(.*?)\{\{\/checklist\}\}',
    'atencao': r'\{\{atencao\}\}(.*?)\{\{\/atencao\}\}',
    'perigo': r'\{\{perigo\}\}(.*?)\{\{\/perigo\}\}',
    'lei_seca': r'\{\{lei_seca\}\}(.*?)\{\{\/lei_seca\}\}',
    'bem_vindo': r'\{\{bem_vindo\}\}(.*?)\{\{\/bem_vindo\}\}',
}


@dataclass
class ParsedDoc:
    titulo: str
    elementos_ordenados: List[Dict[str, Any]]
    data_geracao: str

class DocsJsonParser:
    def __init__(self, client: DocsClient):
        self.client = client

    def parse(self, doc: Dict[str, Any]) -> ParsedDoc:
        title = doc.get("title", "")
        body = doc.get("body", {}).get("content", [])
        lists_index = doc.get("lists") or {}

        elementos: List[Dict[str, Any]] = []
        text_buffer = []  # vamos acumular texto plano para detecção das {{tags}}

        # Mapa de imagens resolvidas (objectId -> dataURL)
        img_map: Dict[str, str] = {}

        # Pré-carrega inlineObjects para contentUri
        inline_objs = (doc.get("inlineObjects") or {})

        def resolve_inline_image(obj_id: str) -> Optional[str]:
            if obj_id in img_map:
                logger.info(f"📷 Imagem {obj_id} encontrada no cache")
                return img_map[obj_id]
            
            logger.info(f"🖼️ Processando imagem {obj_id}...")
            meta = inline_objs.get(obj_id, {}).get("inlineObjectProperties", {}).get("embeddedObject", {})
            uri = (meta.get("imageProperties") or {}).get("contentUri")
            if not uri:
                logger.warning(f"❌ Sem contentUri para imagem {obj_id}")
                return None
            
            try:
                img_start_time = time.time()
                blob = self.client.fetch_content_uri(uri)
                convert_start_time = time.time()
                dataurl = f"data:image/png;base64,{b64_png(blob)}"
                convert_time = time.time() - convert_start_time
                img_map[obj_id] = dataurl
                total_img_time = time.time() - img_start_time
                logger.info(f"✅ Imagem {obj_id} processada em {total_img_time:.2f}s (download: {total_img_time-convert_time:.2f}s, conversão: {convert_time:.2f}s, {len(blob)} bytes)")
                return dataurl
            except Exception as e:
                logger.warning(f"❌ Falha ao processar imagem {obj_id}: {e}")
                # Criar placeholder visual para imagem não carregada
                placeholder = f'<div class="image-placeholder" style="background: #f0f0f0; border: 2px dashed #ccc; border-radius: 8px; padding: 20px; margin: 10px 0; text-align: center; color: #666; font-style: italic;">📷 Imagem (ID: {obj_id})<br><small>Não foi possível carregar a imagem</small></div>'
                return placeholder

        # Helpers para renderizar runs com estilos
        def render_text_run(tr: Dict[str, Any]) -> str:
            content = tr.get("content", "")
            if not content:
                return ""
            style = tr.get("textStyle") or {}
            
            # Converter quebras de linha para <br> tags HTML
            content = content.replace("\n", "<br>")
            
            # Coletar estilos CSS inline
            css_styles = []
            
            # Processar fontSize
            font_size = style.get("fontSize")
            if font_size and isinstance(font_size, dict):
                magnitude = font_size.get("magnitude")
                unit = font_size.get("unit", "PT")
                if magnitude:
                    if unit == "PT":
                        css_styles.append(f"font-size: {magnitude}pt")
                    else:
                        css_styles.append(f"font-size: {magnitude}{unit.lower()}")
            
            # Processar backgroundColor (destacado/highlight)
            bg_color = style.get("backgroundColor")
            if bg_color and isinstance(bg_color, dict):
                color_info = bg_color.get("color", {})
                rgb_color = color_info.get("rgbColor", {})
                if rgb_color:
                    # Converter de 0-1 para 0-255
                    r = int(rgb_color.get("red", 0) * 255)
                    g = int(rgb_color.get("green", 0) * 255)
                    b = int(rgb_color.get("blue", 0) * 255)
                    css_styles.append(f"background-color: rgb({r}, {g}, {b})")
            
            # Aplicar estilos CSS se existirem
            if css_styles:
                style_attr = "; ".join(css_styles)
                content = f'<span style="{style_attr}">{content}</span>'
            
            # Aplicar formatações de texto (ordem importante para aninhamento correto)
            if style.get("underline"):
                content = f"<u>{content}</u>"
            
            if style.get("italic"):
                content = f"<i>{content}</i>"
                
            if style.get("bold"):
                content = f"<strong>{content}</strong>"
            
            # Aplicar links por último (deve englobar tudo)
            link = style.get("link")
            if link and isinstance(link, dict):
                href = link.get("url")
                if href:
                    content = f'<a href="{href}">{content}</a>'
            
            return content

        def render_paragraph_inline(para: Dict[str, Any]) -> str:
            parts: List[str] = []
            for ce in para.get("elements", []):
                if "inlineObjectElement" in ce:
                    obj_id = ce["inlineObjectElement"].get("inlineObjectId")
                    logger.info(f"Processando inlineObjectElement com ID: {obj_id}")
                    src = resolve_inline_image(obj_id) if obj_id else None
                    if src:
                        if src.startswith('<div'):  # É um placeholder HTML
                            parts.append(src)
                        else:  # É uma imagem base64
                            parts.append(f"<img src=\"{src}\" style=\"max-width: 100%; height: auto; margin: 10px 0;\" />")
                    else:
                        logger.warning(f"Não foi possível resolver imagem {obj_id}")
                tr = ce.get("textRun")
                if tr:
                    parts.append(render_text_run(tr))
            return "".join(parts)

        # Controle de listas aninhadas (ul/ol) baseado em listId/nestingLevel
        list_open_stack: List[str] = []  # valores 'ul' ou 'ol'
        list_buffer: List[str] = []
        current_list_id: Optional[str] = None

        def close_lists_to_level(level: int):
            nonlocal list_open_stack, list_buffer
            while len(list_open_stack) > level:
                tag = list_open_stack.pop()
                list_buffer.append(f"</{tag}>")

        def flush_list_if_any():
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

        # Walk structure
        for el in body:
            # TABELAS
            table = el.get("table")
            if table:
                flush_list_if_any()
                
                # NOVO: Extrair larguras das colunas
                col_widths = []
                table_style = table.get("tableStyle", {})
                col_props = table_style.get("tableColumnProperties", [])
                
                for col in col_props:
                    if col.get("widthType") == "FIXED_WIDTH":
                        width = col.get("width", {})
                        if width.get("unit") == "PT":
                            # Converter PT para porcentagem aproximada (assumindo ~450pt total)
                            pt_value = width.get("magnitude", 0)
                            percentage = (pt_value / 450) * 100
                            col_widths.append(f"{percentage:.1f}%")
                        else:
                            col_widths.append("auto")
                    else:
                        col_widths.append("auto")
                
# Construir HTML da tabela com larguras
                rows_html: List[str] = []
                has_header = False  # NOVO: detectar se primeira linha é cabeçalho

                for row_idx, row in enumerate(table.get("tableRows", [])):
                    cells_html: List[str] = []
                    
                    # NOVO: Verificar se primeira linha parece ser cabeçalho
                    # (todas células têm texto curto, sem números longos)
                    if row_idx == 0:
                        first_row_texts = []
                        for cell in row.get("tableCells", []):
                            for ccontent in cell.get("content", []):
                                cpara = ccontent.get("paragraph")
                                if cpara:
                                    text = "".join([e.get("textRun", {}).get("content", "") 
                                                  for e in cpara.get("elements", [])])
                                    first_row_texts.append(text.strip())
                        
                        # Se todos os textos são curtos e não numéricos, é cabeçalho
                        has_header = all(len(t) < 15 and not t.replace('.','').replace(',','').isdigit() 
                                        for t in first_row_texts if t)
                    
                    for cell_idx, cell in enumerate(row.get("tableCells", [])):
                        cell_parts: List[str] = []
                        for ccontent in cell.get("content", []):
                            cpara = ccontent.get("paragraph")
                            if cpara:
                                cell_parts.append(render_paragraph_inline(cpara))
                        
                        # Capturar alinhamento
                        cell_style = cell.get("tableCellStyle", {})
                        alignment = cell_style.get("contentAlignment", "TOP")
                        align_map = {"TOP": "top", "MIDDLE": "middle", "BOTTOM": "bottom"}
                        valign = align_map.get(alignment, "middle")  # mudou para middle como padrão
                        
                        # Usar th para cabeçalho, td para dados
                        tag = "th" if (row_idx == 0 and has_header) else "td"
                        
                        # CORREÇÃO: Aplicar largura em TODAS as células da primeira linha (seja th ou td)
                        style_parts = [f"vertical-align:{valign}"]
                        
                        # Aplicar largura sempre na primeira linha (cabeçalho ou não)
                        if row_idx == 0 and cell_idx < len(col_widths):
                            style_parts.append(f"width:{col_widths[cell_idx]}")
                        
                        style = f" style='{';'.join(style_parts)}'" if style_parts else ""
                        cells_html.append(f"<{tag}{style}>{''.join(cell_parts)}</{tag}>")
                    
                    # Envolver em thead ou tbody
                    if row_idx == 0 and has_header:
                        rows_html.append(f"<thead><tr>{''.join(cells_html)}</tr></thead><tbody>")
                    elif row_idx == 0 and not has_header:
                        # Se não for cabeçalho, começar tbody direto
                        rows_html.append(f"<tbody><tr>{''.join(cells_html)}</tr>")
                    else:
                        rows_html.append(f"<tr>{''.join(cells_html)}</tr>")

                # Fechar tbody 
                rows_html.append("</tbody>")

                # Montar tabela final
                table_html = f"<table>{''.join(rows_html)}</table>"
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
                # Só criar título se houver conteúdo real (não apenas <br> tags ou espaços vazios)
                clean_text = (paragraph_text or "").replace("<br>", "").strip()
                if clean_text:
                    if text_buffer:
                        elementos.append({"tipo": "texto", "conteudo": "".join(text_buffer).strip()})
                        text_buffer = []
                    hlevel = {"TITLE":1, "HEADING_1":1, "HEADING_2":2, "HEADING_3":3}.get(named, 1)
                    elementos.append({"tipo": "texto", "conteudo": "#"*hlevel + " " + clean_text + "\n"})
                else:
                    # Se não há conteúdo real, tratar como texto normal
                    text_buffer.append(paragraph_text)
            else:
                text_buffer.append(paragraph_text)

        if text_buffer:
            elementos.append({"tipo": "texto", "conteudo": "".join(text_buffer).strip()})
        if 'list_open_stack' in locals() and list_open_stack:
            flush_list_if_any()

        # 2) Quebra elementos de texto em blocos especiais via {{tags}}
        elementos = self._explode_custom_blocks(elementos)

        return ParsedDoc(
            titulo=title,
            elementos_ordenados=elementos,
            data_geracao=datetime.now().strftime('%d/%m/%Y %H:%M'),
        )

    # -----------------
    def _explode_custom_blocks(self, elementos: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        out: List[Dict[str, Any]] = []
        for el in elementos:
            if el.get("tipo") != "texto":
                out.append(el)
                continue
            text = el.get("conteudo", "")
            # Para cada tag customizada, separa e estrutura
            consumed = set()
            for key, pat in CUSTOM_TAGS.items():
                for m in re.finditer(pat, text, flags=re.DOTALL | re.IGNORECASE | re.MULTILINE):
                    consumed.add((m.start(), m.end(), key, m.group(1)))
            if not consumed:
                out.append(el)
                continue
            # Ordena por posição para manter ordem
            parts = sorted(list(consumed), key=lambda x: x[0])
            cursor = 0
            for start, end, key, raw in parts:
                if start > cursor:
                    out.append({"tipo": "texto", "conteudo": text[cursor:start].strip()})
                out.append({"tipo": key, "dados": self._parse_block(key, raw)})
                cursor = end
            if cursor < len(text):
                out.append({"tipo": "texto", "conteudo": text[cursor:].strip()})
        # Limpeza: remove vazios
        return [e for e in out if not (e.get("tipo") == "texto" and not (e.get("conteudo") or "").strip())]

    # Parsers dos blocos (mesma lógica do teu script original)
    def _parse_block(self, key: str, raw: str) -> Any:
        # CORREÇÃO: Limpar tags <br> que foram inseridas pelo render_text_run
        # antes de processar os blocos customizados
        raw = raw.strip().replace('<br>', '\n')
        if key == 'cabecalho':
            data = {}
            for line in raw.splitlines():
                if ':' in line:
                    k, v = line.split(':', 1)
                    data[k.strip()] = v.strip()
            return data
        if key == 'tags':
            return re.findall(r'#(\w+)', raw)
        if key == 'objetivos':
            items = []
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
            out = []
            for line in raw.splitlines():
                if '|' in line:
                    a, b = line.split('|', 1)
                    out.append({'termo': a.strip(), 'definicao': b.strip()})
            return out
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
            items = []
            for line in raw.splitlines():
                if not line.strip():
                    continue
                checked = '[x]' in line.lower()
                line = line.replace('[x]', '').replace('[X]', '').replace('[ ]', '').strip()
                prio = 'Media'
                if '|' in line:
                    a, *rest = [p.strip() for p in line.split('|')]
                    line = a
                    if rest:
                        prio = rest[0]
                items.append({'item': line, 'concluido': checked, 'prioridade': prio})
            return items
        if key == 'timeline':
            out = []
            for line in raw.splitlines():
                if '|' in line:
                    p = [x.strip() for x in line.split('|')]
                    out.append({'data': p[0], 'evento': p[1], 'tipo': p[2] if len(p) > 2 else 'normal'})
            return out
        if key == 'introducao':
            # texto corrido
            return raw
        if key == 'glossario':
            out = []
            for line in raw.splitlines():
                if '|' in line:
                    t, d = line.split('|', 1)
                    if t.strip() and d.strip():
                        out.append({'termo': t.strip(), 'definicao': d.strip()})
            return out
        if key == 'dica':
            return {'texto': raw.strip(), 'class': 'info-box'}
        if key == 'alerta':
            return {'texto': raw.strip(), 'class': 'info-box'}
        if key == 'rodape':
            data = {}
            for line in raw.splitlines():
                if ':' in line:
                    k, v = line.split(':', 1)
                    data[k.strip().lower().replace(' ', '_')] = v.strip()
            return data
        if key == 'atencao':
            # Elemento de ATENÇÃO
            return {
                'texto': raw.strip()
            }
        if key == 'perigo':
            # Elemento de PERIGO
            return {
                'texto': raw.strip()
            }
        if key == 'bem_vindo':
            return {
                'titulo': 'Bem-vindo!',
                'texto': raw.strip(),
                'mostrar_ilustracao': True
            }
        if key == 'lei_seca':
            # Formato: titulo | artigo | texto
            lines = raw.strip().split('\n')
            data = {'titulo': '', 'artigo': '', 'texto': ''}
            for line in lines:
                if '|' in line:
                    parts = line.split('|', 2)
                    if len(parts) >= 2:
                        data['titulo'] = parts[0].strip()
                        data['artigo'] = parts[1].strip()
                        if len(parts) > 2:
                            data['texto'] = parts[2].strip()
                else:
                    data['texto'] += '\n' + line
            return data

        if key in ('grafico_linha', 'grafico_pizza'):
            # titulo: X  /  linhas "Label: valor"
            data = {'titulo': '', 'series': []}
            for line in raw.splitlines():
                line = line.strip()
                if not line: 
                    continue
                if line.lower().startswith('titulo:'):
                    data['titulo'] = line.split(':',1)[1].strip()
                elif ':' in line:
                    l, v = line.split(':', 1)
                    try:
                        val = float(v.strip().replace(',', '.'))
                    except:
                        val = v.strip()
                    data['series'].append({'label': l.strip(), 'valor': val})
            return data

        if key == 'grafico_barras':
            # titulo: X / categorias: a,b,c / serie1: v1,v2...
            data = {'titulo': '', 'categorias': [], 'series': []}
            for line in raw.splitlines():
                line = line.strip()
                if not line: 
                    continue
                if line.lower().startswith('titulo:'):
                    data['titulo'] = line.split(':',1)[1].strip()
                elif line.lower().startswith('categorias:'):
                    cats = line.split(':',1)[1]
                    data['categorias'] = [c.strip() for c in cats.split(',')]
                elif ':' in line:
                    nome, vals = line.split(':',1)
                    nums = [v.strip() for v in vals.split(',')]
                    # tenta float
                    parsed = []
                    for x in nums:
                        try: parsed.append(float(x.replace(',', '.')))
                        except: parsed.append(x)
                    data['series'].append({'nome': nome.strip(), 'valores': parsed})
            return data

        if key == 'tabela_performance':
            # primeira linha cabeçalho com |, demais linhas idem; opcional 'total:' como resumo
            lines = [l.strip() for l in raw.splitlines() if l.strip()]
            headers, rows, total = [], [], None
            for i, line in enumerate(lines):
                if i == 0 and '|' in line:
                    headers = [c.strip() for c in line.split('|')]
                elif line.lower().startswith('total:'):
                    total = [c.strip() for c in line.split(':',1)[1].split('|')]
                elif '|' in line:
                    cells = [c.strip() for c in line.split('|')]
                    rows.append(cells)
            return {'headers': headers, 'rows': rows, 'total': total}

        if key == 'cronograma':
            # linhas com |: Fase | Início | Fim | Responsável | Status
            headers, rows = [], []
            for i, line in enumerate([l for l in raw.splitlines() if l.strip()]):
                if i == 0 and '|' in line and not line.lower().startswith('fase '):
                    headers = [c.strip() for c in line.split('|')]
                elif '|' in line:
                    rows.append([c.strip() for c in line.split('|')])
            if not headers:
                headers = ['Fase', 'Início', 'Fim', 'Responsável', 'Status']
            return {'headers': headers, 'rows': rows}

        if key == 'lista_aninhada':
            # Título em linha sem "-" / itens com "- "
            blocos, atual = [], None
            for line in raw.splitlines():
                s = line.strip()
                if not s: 
                    continue
                if not s.startswith('-'):
                    if atual: blocos.append(atual)
                    atual = {'titulo': s, 'itens': []}
                else:
                    if not atual: 
                        atual = {'titulo': '', 'itens': []}
                    atual['itens'].append(s.lstrip('-').strip())
            if atual: blocos.append(atual)
            return blocos

        if key == 'citacao':
            data = {'texto': '', 'autor': '', 'ano': ''}
            for line in raw.splitlines():
                if ':' in line:
                    k, v = line.split(':',1)
                    data[k.strip().lower()] = v.strip()
            return data

        if key == 'formula':
            data = {'nome':'','formula':'','descricao':''}
            for line in raw.splitlines():
                if ':' in line:
                    k, v = line.split(':',1)
                    data[k.strip().lower()] = v.strip()
            return data

        if key == 'fluxograma':
            # cada linha é um nó; use colchetes do exemplo como rótulo
            nodes = [l.strip() for l in raw.splitlines() if l.strip()]
            return {'nodes': nodes}

        if key == 'codigo':
            data = {'linguagem':'', 'titulo':'', 'descricao':'', 'codigo': ''}
            lines = raw.splitlines()
            codigo_lines = []
            
            # First pass: extract metadata
            for line in lines:
                line_stripped = line.strip()
                if ':' in line_stripped:
                    k, v = line_stripped.split(':', 1)
                    k = k.strip().lower()
                    if k in ('linguagem', 'titulo', 'descricao'):
                        data[k] = v.strip()
                        continue
                # If it's not metadata, add to code lines
                codigo_lines.append(line)
            
            data['codigo'] = '\n'.join(codigo_lines).strip()
            return data

        if key == 'questao_multipla':
            data = {'pergunta':'','alternativas':[], 'resposta':''}
            for line in raw.splitlines():
                s = line.strip()
                if not s: continue
                if s.lower().startswith('pergunta:'):
                    data['pergunta'] = s.split(':',1)[1].strip(); continue
                if s.lower().startswith('resposta:'):
                    data['resposta'] = s.split(':',1)[1].strip(); continue
                # alternativas estilo "A) texto"
                data['alternativas'].append(s)
            return data
        if key == 'topicos':
            # 1. Título / parágrafos / - subitens
            items, cur = [], None
            for line in raw.splitlines():
                s = line.rstrip()
                if not s:
                    continue
                if re.match(r'^\d+\.\s', s):
                    if cur: items.append(cur)
                    cur = {'titulo': re.sub(r'^\d+\.\s*', '', s).strip(), 'texto': [], 'subitens': []}
                elif s.lstrip().startswith('-'):
                    if not cur: cur = {'titulo': '', 'texto': [], 'subitens': []}
                    cur['subitens'].append(s.lstrip('-').strip())
                else:
                    if not cur: cur = {'titulo': '', 'texto': [], 'subitens': []}
                    cur['texto'].append(s.strip())
            if cur: items.append(cur)
            return items
        if key == 'questao_dissertativa':
            # apenas enunciado
            return {'pergunta': raw.strip()}

        if key == 'metricas':
            # linhas "Nome | Valor | Delta"
            rows = []
            for line in raw.splitlines():
                if '|' in line:
                    rows.append([c.strip() for c in line.split('|')])
            return {'rows': rows}

        if key == 'progresso':
            # linhas "Etiqueta: valor"
            itens = []
            for line in raw.splitlines():
                if ':' in line:
                    a,b = line.split(':',1)
                    try: val = float(b.strip().replace(',', '.'))
                    except: val = b.strip()
                    itens.append({'label': a.strip(), 'valor': val})
            return {'items': itens}

        if key == 'referencias':
            refs = [l.strip() for l in raw.splitlines() if l.strip()]
            return {'refs': refs}

        if key == 'links':
            links = []
            for line in raw.splitlines():
                if '|' in line:
                    t, u = line.split('|',1)
                    links.append({'texto': t.strip(), 'url': u.strip()})
            return {'links': links}

        if key == 'qrcode':
            # aceita "url: ..." ou o próprio texto
            url = ''
            for line in raw.splitlines():
                if ':' in line:
                    k,v = line.split(':',1)
                    if k.strip().lower()=='url':
                        url = v.strip()
            return {'url': url or raw.strip()}

        if key == 'barcode':
            codigo = ''
            for line in raw.splitlines():
                if ':' in line:
                    k,v = line.split(':',1)
                    if k.strip().lower() in ('codigo','código'):
                        codigo = v.strip()
            return {'codigo': codigo or raw.strip()}

        return raw


# ---------------------------------------------------------------------
# Templating + PDF
# ---------------------------------------------------------------------

class PDFRenderer:
    def __init__(self, template_dir: Optional[Path] = None, template_name: str = "lab_resumos.html"):
        if template_dir is None:
            # Usa o diretório templates/ local do CloudRun
            template_dir = Path(__file__).parent / "templates"
            if not template_dir.exists():
                raise FileNotFoundError(f"Diretório de templates não encontrado: {template_dir}")
            if not (template_dir / template_name).exists():
                raise FileNotFoundError(f"Template não encontrado: {template_dir / template_name}")
        self.env = Environment(
            loader=FileSystemLoader(str(template_dir)),
            autoescape=select_autoescape(['html', 'xml'])
        )
        self.env.filters['json'] = json.dumps
        self.env.globals['generate_qr_code'] = generate_qr_code
        self.env.globals['generate_line_chart'] = generate_line_chart
        self.env.globals['generate_pie_chart'] = generate_pie_chart
        self.env.globals['generate_bar_chart'] = generate_bar_chart
        self.env.globals['generate_references_visual'] = generate_references_visual
        
        # Ícones para elementos visuais
        ICONS = {
            'dica': '💡',
            'alerta': '⚠️',
            'perigo': '⚠',
            'livro': '📚',
            'check': '✓',
            'objetivos': '🎯',
            'conceitos': '🔍',
            'introducao': '📖',
            'topicos': '📝'
        }
        self.env.globals['ICONS'] = ICONS
        def apply_highlights(text: str) -> str:
            """Detecta e aplica highlights automáticos"""
            # Detecta frases importantes automaticamente
            important_phrases = [
                'primeira grande transformação',
                'principais obstáculos',
                'corrigir defeitos estruturais',
                'mais complexos do mundo',
                'fundamental entender',
                'aspecto crucial',
                'elemento chave',
                'ponto importante',
                'destaque especial'
            ]
            
            for phrase in important_phrases:
                text = text.replace(phrase, f'<mark class="highlight">{phrase}</mark>')
            
            # Detecta textos entre asteriscos duplos para highlight manual
            text = re.sub(r'\*\*(.*?)\*\*', r'<mark class="highlight">\1</mark>', text)
            return text
        
        def process_md(text: str) -> str:
            if not text:
                return ""
            # pular se já parece HTML estruturado (listas/tabelas)
            if any(tag in text for tag in ('<ul', '<ol', '<table', '<thead', '<tbody', '<tr', '<td', '<th')):
                return text
            
            # Aplicar highlights primeiro
            text = apply_highlights(text)
            
            # headings markdown -> html leve
            text = re.sub(r"^### (.+)$",
                          lambda m: f"<div class='h3'>{m.group(1)}</div>",
                          text, flags=re.MULTILINE)
            text = re.sub(r"^## (.+)$",
                          lambda m: f"<div class='h2'>{m.group(1)}</div>",
                          text, flags=re.MULTILINE)
            text = re.sub(r"^# (.+)$",
                          lambda m: f"<div class='h1'>{m.group(1)}</div>",
                          text, flags=re.MULTILINE)
            # bold/italic
            text = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", text)
            text = re.sub(r"\*(.+?)\*", r"<i>\1</i>", text)
            # imagens inline ![img](dataurl)
            text = re.sub(r"!\[img\]\((.*?)\)", r"<img src='\1' />", text)
            # parágrafos
            parts = [p for p in text.split("\n\n") if p.strip()]
            html_parts = [p if p.startswith("<") else f"<div class='p'>{p}</div>" for p in parts]
            return "\n".join(html_parts)
        self.env.filters['markdown'] = process_md
        self.template_name = template_name

    def render_pdf(self, doc: ParsedDoc, output_path: Path) -> Path:
        """
        Renderiza PDF com sumário usando abordagem de dupla passada
        """
        # PASSO 1: Preparar elementos com IDs únicos para os títulos
        toc_entries = []
        chapter_counter = 0
        elementos_com_ids = []
        anchor_map = {}  # Mapeia IDs para títulos limpos
        
        for idx, el in enumerate(doc.elementos_ordenados):
            el_copy = el.copy()
            
            if el.get("tipo") == "texto":
                content = el.get("conteudo", "")
                
                # Ignorar se já é HTML (tabelas, listas, etc)
                if any(tag in content for tag in ['<table', '<ul', '<ol', '<thead', '<tbody']):
                    elementos_com_ids.append(el_copy)
                    continue
                
                # Detectar títulos H1 e H2 no markdown
                lines = content.split('\n')
                new_lines = []
                
                for line_idx, line in enumerate(lines):
                    if line.strip().startswith('#'):
                        heading_match = re.match(r'^(#{1,3})\s+(.+)$', line.strip())
                        if heading_match:
                            level = len(heading_match.group(1))
                            title_text = heading_match.group(2).strip()
                            
                            if level <= 2:  # Apenas H1 e H2 para o sumário
                                # Criar ID único e limpo
                                anchor_id = f"heading-{idx}-{line_idx}"
                                anchor_map[anchor_id] = title_text
                                
                                # Adicionar âncora ANTES do título
                                # IMPORTANTE: usar tag vazia para não afetar layout
                                anchor_html = f'<span id="{anchor_id}"></span>'
                                
                                # Manter o título no formato markdown mas adicionar âncora
                                new_lines.append(anchor_html)
                                new_lines.append(line)
                                
                                if level == 1:
                                    chapter_counter += 1
                                    toc_entries.append({
                                        "level": level,
                                        "title": title_text,
                                        "anchor_id": anchor_id,
                                        "page": None,
                                        "chapter_number": chapter_counter
                                    })
                                elif level == 2:
                                    toc_entries.append({
                                        "level": level,
                                        "title": title_text,
                                        "anchor_id": anchor_id,
                                        "page": None,
                                        "chapter_number": None  # Subtítulos não têm numeração
                                    })
                            else:
                                new_lines.append(line)
                        else:
                            new_lines.append(line)
                    else:
                        new_lines.append(line)
                
                el_copy["conteudo"] = '\n'.join(new_lines)
            
            elementos_com_ids.append(el_copy)
        
        # PASSO 2: Primeira renderização para descobrir números de página
        tpl = self.env.get_template(self.template_name)
        
        # Renderizar SEM sumário primeiro
        html_content_first_pass = tpl.render(
            titulo=doc.titulo,
            elementos_ordenados=elementos_com_ids,
            data_geracao=doc.data_geracao,
            show_toc=False,
            toc_entries=[]
        )
        
        # CSS com configurações de página
        CSS_STR = """
        @page { size: A4; margin: 15mm; }
        @media print { 
            * { print-color-adjust: exact !important; -webkit-print-color-adjust: exact !important; }
        }
        img { max-width: 100%; height: auto; }
        table { break-inside: avoid; }
        """
        
        # Criar documento WeasyPrint
        from weasyprint import HTML, CSS
        html_doc = HTML(string=html_content_first_pass)
        document = html_doc.render(stylesheets=[CSS(string=CSS_STR)])
        
        # PASSO 3: Mapear anchors para páginas
        # IMPORTANTE: O sumário vai adicionar uma página, então compensar
        toc_will_add_pages = 1  # Sumário normalmente adiciona 1 página
        
        for page_index, page in enumerate(document.pages):
            page_num = page_index + 1
            
            # WeasyPrint armazena anchors como dicionário {nome: (x, y)}
            for anchor_name in page.anchors:
                for entry in toc_entries:
                    if entry["anchor_id"] == anchor_name:
                        # Adicionar offset para compensar a página do sumário
                        entry["page"] = page_num + toc_will_add_pages
                        logger.info(f"Âncora {anchor_name} encontrada na página {page_num} (será {entry['page']} com sumário)")
                        break
        
        # PASSO 4: Segunda renderização com sumário completo e links
        html_content_final = tpl.render(
            titulo=doc.titulo,
            elementos_ordenados=elementos_com_ids,
            data_geracao=doc.data_geracao,
            show_toc=True,
            toc_entries=toc_entries
        )
        
        # Salvar HTML final (para debug)
        try:
            html_out = output_path.with_suffix('.html')
            html_out.write_text(html_content_final, encoding='utf-8')
            logger.info(f"HTML salvo: {html_out}")
        except Exception:
            logger.exception("Falha ao salvar HTML gerado")
        
        # PASSO 5: Gerar PDF final com CSS completo
        CSS_STR_FULL = """
        @page { size: A4; margin: 15mm; }
        @page :first { margin-top: 10mm; }
        
        @media print { 
            * { print-color-adjust: exact !important; -webkit-print-color-adjust: exact !important; }
            table, th, td { print-color-adjust: exact !important; }
            .toc-container { page-break-after: always; }
        }
        
        /* Links no sumário */
        .toc-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: baseline;
            width: 100%;
        }
        .toc-link:hover .toc-title {
            color: #2A6B9F;
        }
        
        /* Itálico */
        i, em { font-style: oblique 15deg !important; font-family: Arial, sans-serif !important; }
        td i, td em { font-style: oblique 15deg !important; }
        
        /* Layout geral */
        img { max-width: 100%; height: auto; }
        .pagebreak { break-before: page; }
        .h1, .h2, .h3 { break-inside: avoid; }
        table { break-inside: avoid; }
        
        /* Garantir que âncoras não afetem layout */
        span[id^="heading-"] {
            display: block;
            height: 0;
            margin: 0;
            padding: 0;
            visibility: hidden;
            position: relative;
            top: -20px; /* Ajuste fino para compensar margem superior */
        }
        """
        
        HTML(string=html_content_final).write_pdf(
            str(output_path), 
            stylesheets=[CSS(string=CSS_STR_FULL)]
        )
        
        # Log final
        logger.info("=" * 50)
        logger.info("Sumário gerado com %d entradas:", len(toc_entries))
        for entry in toc_entries:
            logger.info("  %s%s -> Página %s", 
                        "  " * (entry["level"]-1), 
                        entry["title"], 
                        entry["page"] or "não encontrada")
        
        return output_path

# ---------------------------------------------------------------------
# App
# ---------------------------------------------------------------------

class LabResumosAPIApp:
    def __init__(self, template_dir: Optional[Path] = None):
        creds = load_credentials()
        self.client = DocsClient(creds)
        self.parser = DocsJsonParser(self.client)
        self.renderer = PDFRenderer(template_dir=template_dir)

    def process_document(self, source: str, output_dir: Path | None = None, output_name: Optional[str] = None) -> Path:
        total_start_time = time.time()
        logger.info("🚀 Iniciando processamento do documento...")
        
        # 1. Download do documento
        doc_start_time = time.time()
        doc_id = extract_doc_id(source)
        logger.info("📥 Baixando documento JSON: %s", doc_id)
        raw = self.client.get_document(doc_id)
        doc_time = time.time() - doc_start_time
        logger.info("✅ Documento baixado em %.2fs", doc_time)

        # 2. Parsing do documento
        parse_start_time = time.time()
        logger.info("🔍 Iniciando parsing do documento...")
        parsed = self.parser.parse(raw)
        parse_time = time.time() - parse_start_time
        logger.info("✅ Parsing concluído em %.2fs (%d elementos)", parse_time, len(parsed.elementos_ordenados))

        # 3. Preparação de saída
        out_dir = output_dir or (Path.cwd() / 'outputs')
        out_dir.mkdir(parents=True, exist_ok=True)
        if not output_name:
            output_name = f"lab_resumos_api_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
        out_path = out_dir / output_name
        
        # 4. Salvamento de arquivos auxiliares
        save_start_time = time.time()
        logger.info("💾 Salvando arquivos auxiliares...")
        
        # Salva o JSON bruto ao lado do PDF, com mesmo nome base
        try:
            json_out = out_path.with_suffix('.json')
            with open(json_out, 'w', encoding='utf-8') as jf:
                json.dump(raw, jf, ensure_ascii=False, indent=2)
            logger.info("📄 JSON bruto salvo: %s", json_out)
        except Exception:
            logger.exception("❌ Falha ao salvar JSON bruto")
            
        # Salva também o JSON parseado (limpo) para debug/inspeção
        try:
            parsed_out = out_path.with_suffix('.parsed.json')
            with open(parsed_out, 'w', encoding='utf-8') as pf:
                json.dump({
                    'titulo': parsed.titulo,
                    'data_geracao': parsed.data_geracao,
                    'elementos_ordenados': parsed.elementos_ordenados
                }, pf, ensure_ascii=False, indent=2)
            logger.info("📄 JSON parseado salvo: %s", parsed_out)
        except Exception:
            logger.exception("❌ Falha ao salvar JSON parseado")
            
        save_time = time.time() - save_start_time
        logger.info("✅ Arquivos auxiliares salvos em %.2fs", save_time)

        # 5. Geração do PDF
        pdf_start_time = time.time()
        logger.info("📄 Iniciando geração do PDF...")
        self.renderer.render_pdf(parsed, out_path)
        pdf_time = time.time() - pdf_start_time
        logger.info("✅ PDF gerado em %.2fs", pdf_time)
        
        # 6. Resumo final
        total_time = time.time() - total_start_time
        logger.info("🎉 Processamento completo!")
        logger.info("📊 Resumo de tempos:")
        logger.info("   📥 Download: %.2fs", doc_time)
        logger.info("   🔍 Parsing: %.2fs", parse_time)
        logger.info("   💾 Salvamento: %.2fs", save_time)
        logger.info("   📄 PDF: %.2fs", pdf_time)
        logger.info("   ⏱️ Total: %.2fs", total_time)
        logger.info("📁 Arquivo final: %s", out_path)
        
        return out_path

# ---------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------

def main():
    import argparse
    p = argparse.ArgumentParser(description='Lab Resumos – GDocs API JSON -> PDF')
    p.add_argument('-o', '--output', help='nome do PDF', default=None)
    p.add_argument('-d', '--output-dir', default='./outputs')
    p.add_argument('-t', '--template-dir', default=None, help='dir com template Jinja opcional')
    p.add_argument('-v', '--verbose', action='store_true', help='Modo verbose com logs detalhados')
    p.add_argument('--debug', action='store_true', help='Modo debug com logs ainda mais detalhados')
    p.add_argument('--timeout', type=int, default=15, help='Timeout para downloads em segundos (padrão: 15)')
    args = p.parse_args()

    if args.verbose:
        logging.getLogger().setLevel(logging.DEBUG)
    elif args.debug:
        logging.getLogger().setLevel(logging.DEBUG)
        logger.info("🐛 Modo DEBUG ativado - logs detalhados habilitados")
        logger.info(f"⏱️ Timeout configurado para: {args.timeout}s")

    logger.info("🚀 Iniciando Lab Resumos Processor...")
    logger.info(f"📄 Documento: {DOCUMENT_URL}")
    logger.info(f"📁 Diretório de saída: {args.output_dir}")
    
    app = LabResumosAPIApp(template_dir=Path(args.template_dir) if args.template_dir else None)
    out = app.process_document(DOCUMENT_URL, output_dir=Path(args.output_dir), output_name=args.output)
    print(f"✅ PDF: {out}")
    print(f"📄 Documento processado: {DOCUMENT_URL}")

if __name__ == '__main__':
    main()