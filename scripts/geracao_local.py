"""
Gerador Unificado Lab Resumos - PDF com Diagramas SVG
Gera PDFs educacionais diretamente no Python sem dependências externas de API
Mantém a identidade visual completa do Lab Resumos
"""

import os
import base64
import json
from typing import Dict, List, Any, Optional, Tuple
from dataclasses import dataclass, field, asdict
from enum import Enum
from datetime import datetime
from pathlib import Path
import tempfile
import asyncio
from jinja2 import Template, Environment, FileSystemLoader
import weasyprint
from weasyprint import HTML, CSS
import cairosvg
from io import BytesIO

# ================== CONFIGURAÇÃO ==================
@dataclass
class ConfigLabResumos:
    """Configuração centralizada do sistema"""
    # Cores do Lab Resumos
    CORES = {
        "amarelo": "#F1CC00",
        "preto": "#333B49",
        "azul": "#2A6B9F",
        "azul_claro": "#A0DDFC",
        "amarelo_claro": "#FEEF4C",
        "cinza": "#6C757D",
        "branco": "#FFFFFF",
        "vermelho": "#E74C3C",
        "verde": "#27AE60",
        "bg_light": "#F3F1E8",
        "fundo_claro": "#FFF9E6"
    }
    
    # Tipografia
    FONTES = {
        "principal": "Figtree, Arial, sans-serif",
        "codigo": "JetBrains Mono, monospace"
    }
    
    # Tamanhos de fonte
    TAMANHOS = {
        "titulo": 32,
        "subtitulo": 24,
        "texto": 16,
        "pequeno": 14,
        "micro": 12
    }

# ================== TIPOS DE MATERIAL ==================
class TipoMaterial(Enum):
    """Tipos de materiais disponíveis"""
    RESUMO = "resumo"
    EXERCICIOS = "exercicios"
    SIMULADO = "simulado"
    MAPA_MENTAL = "mapa_mental"
    GUIA_ESTUDO = "guia_estudo"
    APOSTILA = "apostila"

# ================== TIPOS DE DIAGRAMAS ==================
class TipoDiagrama(Enum):
    """Tipos de diagramas disponíveis"""
    FLUXOGRAMA = "fluxograma"
    ORGANOGRAMA = "organograma"
    MAPA_MENTAL = "mapa_mental"
    TIMELINE = "timeline"
    PROCESSO = "processo"
    HIERARQUIA = "hierarquia"
    COMPARATIVO = "comparativo"
    IPTU_PRINCIPIOS = "iptu_principios"

# ================== DATA CLASSES ==================
@dataclass
class ConceitualizacaoItem:
    """Item de conceito"""
    termo: str
    definicao: str

@dataclass
class MaterialData:
    """Dados base para geração de material"""
    # Campos obrigatórios
    titulo_material: str
    subtitulo: str = ""
    nome_curso: str = ""
    numero_modulo: str = "1"
    
    # Informações do aluno
    nome_aluno: str = ""
    cpf_aluno: str = ""
    
    # Conteúdo principal
    introducao: str = ""
    ponto_importante: str = ""
    titulo_secao_principal: str = ""
    conteudo_principal: str = ""
    
    # Metadados
    nivel_dificuldade: str = "Intermediário"
    tempo_estimado_leitura: str = "15 min"
    versao_documento: str = "1.0"
    data_geracao: str = field(default_factory=lambda: datetime.now().strftime('%d/%m/%Y'))
    
    # Tags e categorias
    tags: List[str] = field(default_factory=list)
    
    # Objetivos
    objetivos_aprendizagem: List[str] = field(default_factory=list)
    
    # Conceitos
    conceitos: List[Dict[str, str]] = field(default_factory=list)
    
    # Diagramas
    diagramas: List[Dict[str, Any]] = field(default_factory=list)
    
    def to_dict(self) -> Dict[str, Any]:
        """Converte para dicionário"""
        return asdict(self)

@dataclass
class MaterialDataCompleto(MaterialData):
    """Versão completa com todos os campos possíveis"""
    # Tópicos estruturados
    topicos_principais: List[Dict[str, Any]] = field(default_factory=list)
    
    
    # Gráficos
    grafico_linha: Dict[str, Any] = field(default_factory=dict)
    grafico_pizza: Dict[str, Any] = field(default_factory=dict)
    grafico_barras: Dict[str, Any] = field(default_factory=dict)
    
    # Timeline
    linha_tempo: List[Dict[str, str]] = field(default_factory=list)
    
    # Checklist
    lista_verificacao: List[Dict[str, Any]] = field(default_factory=list)
    
    
    # Dicas e alertas
    dicas: List[str] = field(default_factory=list)
    alertas: List[str] = field(default_factory=list)
    
    # Exemplos práticos
    exemplos_praticos: List[Dict[str, str]] = field(default_factory=list)
    
    # Citações
    citacoes: List[Dict[str, str]] = field(default_factory=list)
    
    # QR Code
    qr_code_data: str = ""
    
    # Referências
    referencias_bibliograficas: List[str] = field(default_factory=list)
    
    
    # Questões
    questoes_multipla_escolha: List[Dict[str, Any]] = field(default_factory=list)
    questoes_dissertativas: List[str] = field(default_factory=list)
    
    # Glossário
    glossario: List[Dict[str, str]] = field(default_factory=list)
    
    # Fórmulas
    formulas_matematicas: List[Dict[str, str]] = field(default_factory=list)
    
    
    # Listas estruturadas
    lista_aninhada: List[Dict[str, Any]] = field(default_factory=list)
    
    # Fluxogramas
    diagrama_fluxo: List[Dict[str, str]] = field(default_factory=list)

# ================== GERADOR DE DIAGRAMAS SVG ==================
class GeradorDiagramaSVG:
    """Gera diagramas SVG para materiais educacionais"""
    
    def __init__(self):
        self.config = ConfigLabResumos()
        self.largura = 1100
        self.altura = 400
    
    def gerar_diagrama_iptu(self) -> str:
        """Gera o diagrama específico do IPTU"""
        cores = self.config.CORES
        fontes = self.config.FONTES
        
        svg = f"""<svg width="1100" height="400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1100 400">
            <!-- Definições de estilos -->
            <defs>
                <style>
                    .main-text {{ 
                        font-family: {fontes['principal']}; 
                        font-size: 28px; 
                        font-weight: bold; 
                        fill: {cores['preto']};
                    }}
                    .principle-text {{ 
                        font-family: {fontes['principal']}; 
                        font-size: 24px; 
                        fill: {cores['preto']};
                    }}
                    .small-text {{
                        font-family: {fontes['principal']}; 
                        font-size: 20px; 
                        fill: {cores['preto']};
                    }}
                    .red-text {{ 
                        fill: {cores['vermelho']};
                        font-style: italic;
                    }}
                    .box {{
                        fill: {cores['branco']};
                        stroke: {cores['preto']};
                        stroke-width: 2;
                        rx: 15;
                    }}
                    .highlight-box {{
                        fill: {cores['fundo_claro']};
                        stroke: {cores['amarelo']};
                        stroke-width: 3;
                        rx: 15;
                    }}
                    .dashed-box {{
                        fill: {cores['branco']};
                        stroke: {cores['cinza']};
                        stroke-width: 1.5;
                        stroke-dasharray: 8,4;
                        rx: 10;
                    }}
                    .connector {{
                        stroke: {cores['preto']};
                        stroke-width: 2;
                        fill: none;
                    }}
                    .dashed-connector {{
                        stroke: {cores['cinza']};
                        stroke-width: 1.5;
                        stroke-dasharray: 8,4;
                        fill: none;
                    }}
                </style>
                <linearGradient id="bgGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:{cores['branco']};stop-opacity:1" />
                    <stop offset="100%" style="stop-color:{cores['fundo_claro']};stop-opacity:0.3" />
                </linearGradient>
            </defs>

            <!-- Fundo com gradiente sutil Lab Resumos -->
            <rect width="1100" height="400" fill="url(#bgGradient)"/>

            <!-- Texto principal à esquerda -->
            <text x="50" y="180" class="main-text">
                <tspan x="50" dy="0">O IPTU obedece</tspan>
                <tspan x="50" dy="35">aos princípios da:</tspan>
            </text>

            <!-- Linha principal horizontal -->
            <line x1="330" y1="180" x2="450" y2="180" class="connector"/>
            
            <!-- Conectores verticais -->
            <line x1="450" y1="180" x2="450" y2="80" class="connector"/>
            <line x1="450" y1="180" x2="450" y2="280" class="connector"/>
            
            <!-- Conectores horizontais para as caixas -->
            <line x1="450" y1="80" x2="480" y2="80" class="connector"/>
            <line x1="450" y1="180" x2="480" y2="180" class="connector"/>
            <line x1="450" y1="280" x2="480" y2="280" class="connector"/>

            <!-- Caixa Legalidade -->
            <rect x="480" y="50" width="200" height="60" class="box"/>
            <text x="580" y="85" class="principle-text" text-anchor="middle">Legalidade</text>

            <!-- Caixa Anterioridade -->
            <rect x="480" y="150" width="200" height="60" class="box"/>
            <text x="580" y="185" class="principle-text" text-anchor="middle">Anterioridade</text>

            <!-- Caixa Noventena (com destaque) -->
            <rect x="480" y="250" width="200" height="60" class="highlight-box"/>
            <text x="580" y="285" class="principle-text red-text" text-anchor="middle">Noventena</text>

            <!-- Conector para exceção -->
            <line x1="680" y1="280" x2="720" y2="280" class="connector"/>
            <line x1="720" y1="280" x2="720" y2="180" class="connector"/>
            <line x1="720" y1="180" x2="750" y2="180" class="connector"/>

            <!-- Caixa Exceção -->
            <rect x="750" y="140" width="300" height="80" class="box"/>
            
            <!-- Texto "Exceto a fixação da" na primeira linha -->
            <text x="900" y="170" class="small-text" text-anchor="middle">
                <tspan class="red-text" font-style="italic">Exceto</tspan>
                <tspan dx="5"> a fixação da</tspan>
            </text>
            
            <!-- Texto "Base de Cálculo" na segunda linha -->
            <text x="900" y="200" class="small-text" text-anchor="middle">
                <tspan class="red-text" font-style="italic">Base</tspan>
                <tspan dx="5"> de Cálculo</tspan>
            </text>

            <!-- Conector pontilhado -->
            <line x1="900" y1="220" x2="900" y2="260" class="dashed-connector"/>

            <!-- Caixa pontilhada -->
            <rect x="770" y="260" width="260" height="80" class="dashed-box"/>
            
            <!-- Texto da caixa pontilhada -->
            <text x="900" y="290" class="small-text" text-anchor="middle">
                As bancas trocam por
            </text>
            <text x="900" y="315" class="small-text" text-anchor="middle">
                "<tspan class="red-text" font-style="italic">alíquota</tspan>"
            </text>

            <!-- Marcador de ponto de conexão (círculo pequeno) -->
            <circle cx="450" cy="180" r="4" fill="{cores['preto']}"/>
            <circle cx="720" cy="280" r="4" fill="{cores['preto']}"/>
            
            <!-- Logo Lab Resumos (marca d'água sutil) -->
            <text x="1050" y="390" style="font-family: {fontes['principal']}; font-size: 10px; fill: {cores['cinza']}; opacity: 0.5;" text-anchor="end">
                Lab Resumos © 2024
            </text>
        </svg>"""
        
        return svg
    
    def gerar_mapa_mental(self, titulo: str, topicos: Dict[str, List[str]]) -> str:
        """Gera um mapa mental"""
        cores = self.config.CORES
        fontes = self.config.FONTES
        
        svg_parts = [f'<svg width="{self.largura}" height="{self.altura}" xmlns="http://www.w3.org/2000/svg">']
        
        # Estilos
        svg_parts.append(self._gerar_estilos())
        
        # Centro
        cx, cy = self.largura / 2, self.altura / 2
        svg_parts.append(f'''
            <circle cx="{cx}" cy="{cy}" r="80" fill="{cores["amarelo"]}" stroke="{cores["preto"]}" stroke-width="3"/>
            <text x="{cx}" y="{cy}" text-anchor="middle" style="font-family: {fontes["principal"]}; font-size: 24px; font-weight: bold; fill: {cores["preto"]}">{titulo}</text>
        ''')
        
        # Distribuir tópicos em círculo
        import math
        num_topicos = len(topicos)
        angulo_base = 360 / num_topicos if num_topicos > 0 else 0
        
        for i, (topico, subtopicos) in enumerate(topicos.items()):
            angulo = angulo_base * i - 90  # Começar do topo
            rad = math.radians(angulo)
            
            # Posição do tópico principal
            tx = cx + 200 * math.cos(rad)
            ty = cy + 200 * math.sin(rad)
            
            # Linha conectora
            svg_parts.append(f'<line x1="{cx}" y1="{cy}" x2="{tx}" y2="{ty}" stroke="{cores["preto"]}" stroke-width="2"/>')
            
            # Caixa do tópico
            svg_parts.append(f'''
                <rect x="{tx-75}" y="{ty-25}" width="150" height="50" fill="{cores["branco"]}" stroke="{cores["preto"]}" stroke-width="2" rx="10"/>
                <text x="{tx}" y="{ty+5}" text-anchor="middle" style="font-family: {fontes["principal"]}; font-size: 16px; fill: {cores["preto"]}">{topico}</text>
            ''')
            
            # Subtópicos
            for j, subtopico in enumerate(subtopicos[:3]):  # Limitar a 3 subtópicos
                sx = tx + 120
                sy = ty + (j - 1) * 40
                
                svg_parts.append(f'''
                    <line x1="{tx+75}" y1="{ty}" x2="{sx}" y2="{sy}" stroke="{cores["cinza"]}" stroke-width="1.5" stroke-dasharray="8,4"/>
                    <rect x="{sx}" y="{sy-15}" width="120" height="30" fill="{cores["branco"]}" stroke="{cores["cinza"]}" stroke-width="1.5" stroke-dasharray="8,4" rx="10"/>
                    <text x="{sx+60}" y="{sy+3}" text-anchor="middle" style="font-family: {fontes["principal"]}; font-size: 14px; fill: {cores["preto"]}">{subtopico}</text>
                ''')
        
        svg_parts.append('</svg>')
        return ''.join(svg_parts)
    
    def gerar_fluxograma_processo(self, processo: List[Dict[str, Any]]) -> str:
        """Gera um fluxograma de processo"""
        cores = self.config.CORES
        fontes = self.config.FONTES
        
        svg_parts = [f'<svg width="{self.largura}" height="{self.altura}" xmlns="http://www.w3.org/2000/svg">']
        svg_parts.append(self._gerar_estilos())
        
        # Calcular posições
        num_etapas = len(processo)
        espacamento = self.largura / (num_etapas + 1)
        y_base = self.altura / 2
        
        for i, etapa in enumerate(processo):
            x = espacamento * (i + 1)
            
            # Determinar forma baseada no tipo
            tipo = etapa.get('tipo', 'processo')
            
            if tipo == 'inicio' or tipo == 'fim':
                # Círculo para início/fim
                cor = cores["verde"] if tipo == 'inicio' else cores["vermelho"]
                svg_parts.append(f'''
                    <circle cx="{x}" cy="{y_base}" r="40" fill="{cor}" stroke="{cores["preto"]}" stroke-width="2"/>
                    <text x="{x}" y="{y_base+5}" text-anchor="middle" fill="white" style="font-family: {fontes["principal"]}; font-weight: bold;">
                        {etapa["texto"]}
                    </text>
                ''')
            elif tipo == 'decisao':
                # Losango para decisão
                svg_parts.append(f'''
                    <polygon points="{x},{y_base-50} {x+60},{y_base} {x},{y_base+50} {x-60},{y_base}" 
                             fill="{cores["amarelo"]}" stroke="{cores["preto"]}" stroke-width="2"/>
                    <text x="{x}" y="{y_base+5}" text-anchor="middle" style="font-family: {fontes["principal"]}; font-size: 16px; fill: {cores["preto"]}">
                        {etapa["texto"]}
                    </text>
                ''')
            else:
                # Retângulo para processo normal
                svg_parts.append(f'''
                    <rect x="{x-80}" y="{y_base-30}" width="160" height="60" fill="{cores["branco"]}" stroke="{cores["preto"]}" stroke-width="2" rx="10"/>
                    <text x="{x}" y="{y_base+5}" text-anchor="middle" style="font-family: {fontes["principal"]}; font-size: 16px; fill: {cores["preto"]}">
                        {etapa["texto"]}
                    </text>
                ''')
            
            # Conectar com próxima etapa
            if i < num_etapas - 1:
                x_next = espacamento * (i + 2)
                svg_parts.append(f'''
                    <line x1="{x+80}" y1="{y_base}" x2="{x_next-80}" y2="{y_base}" 
                          marker-end="url(#arrowhead)" stroke="{cores["preto"]}" stroke-width="2"/>
                ''')
        
        # Adicionar marcador de seta
        svg_parts.append(f'''
            <defs>
                <marker id="arrowhead" markerWidth="10" markerHeight="7" 
                        refX="10" refY="3.5" orient="auto">
                    <polygon points="0 0, 10 3.5, 0 7" fill="{cores["preto"]}"/>
                </marker>
            </defs>
        ''')
        
        svg_parts.append('</svg>')
        return ''.join(svg_parts)
    
    def _gerar_estilos(self) -> str:
        """Gera estilos padrão para os diagramas"""
        cores = self.config.CORES
        fontes = self.config.FONTES
        
        return f'''
            <defs>
                <style>
                    .main-text {{ 
                        font-family: {fontes['principal']}; 
                        font-size: 24px; 
                        font-weight: bold; 
                        fill: {cores['preto']};
                    }}
                    .principle-text {{ 
                        font-family: {fontes['principal']}; 
                        font-size: 16px; 
                        fill: {cores['preto']};
                    }}
                    .box {{
                        fill: {cores['branco']};
                        stroke: {cores['preto']};
                        stroke-width: 2;
                        rx: 10;
                    }}
                    .dashed-box {{
                        fill: {cores['branco']};
                        stroke: {cores['cinza']};
                        stroke-width: 1.5;
                        stroke-dasharray: 8,4;
                        rx: 10;
                    }}
                    .connector {{
                        stroke: {cores['preto']};
                        stroke-width: 2;
                        fill: none;
                    }}
                    .dashed-connector {{
                        stroke: {cores['cinza']};
                        stroke-width: 1.5;
                        stroke-dasharray: 8,4;
                        fill: none;
                    }}
                </style>
            </defs>
        '''
    
    def svg_para_base64(self, svg: str) -> str:
        """Converte SVG para base64"""
        svg_bytes = svg.encode('utf-8')
        return base64.b64encode(svg_bytes).decode('utf-8')
    
    def gerar_por_tipo(self, tipo: TipoDiagrama, dados: Dict[str, Any]) -> str:
        """Gera diagrama baseado no tipo"""
        if tipo == TipoDiagrama.IPTU_PRINCIPIOS:
            return self.gerar_diagrama_iptu()
        elif tipo == TipoDiagrama.MAPA_MENTAL:
            return self.gerar_mapa_mental(
                dados.get('titulo', 'Mapa Mental'),
                dados.get('topicos', {})
            )
        elif tipo == TipoDiagrama.PROCESSO or tipo == TipoDiagrama.FLUXOGRAMA:
            return self.gerar_fluxograma_processo(
                dados.get('etapas', [])
            )
        else:
            # Diagrama padrão
            return self.gerar_fluxograma_processo([
                {'texto': 'Início', 'tipo': 'inicio'},
                {'texto': 'Processo', 'tipo': 'processo'},
                {'texto': 'Fim', 'tipo': 'fim'}
            ])

# ================== GERADOR DE PDF ==================
class LabResumosPDFGenerator:
    """Gerador principal de PDFs do Lab Resumos"""
    
    def __init__(self):
        """Inicializa o gerador"""
        self.config = ConfigLabResumos()
        self.gerador_svg = GeradorDiagramaSVG()
        self.template_html = self._carregar_template()
        print("🎨 Sistema Lab Resumos inicializado")
        print("📄 Gerador de PDF direto configurado")
    
    def _carregar_template(self) -> str:
        """Carrega o template HTML"""
        # Template HTML incorporado (versão simplificada do original)
        return '''<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ titulo_material }} - Lab Resumos</title>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;600;700;800;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-yellow: #F1CC00;
            --primary-dark: #333B49;
            --primary-blue: #2A6B9F;
            --light-blue: #A0DDFC;
            --light-yellow: #FEEF4C;
            --bg-light: #F3F1E8;
            --success: #4CAF50;
            --warning: #FF9800;
            --danger: #F44336;
            --info: #2196F3;
        }
        
        body {
            font-family: 'Figtree', sans-serif;
            line-height: 1.6;
            color: var(--primary-dark);
            background: white;
        }
        
        @page {
            size: A4;
            margin: 20mm;
        }
        
        .page {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--light-yellow) 100%);
            padding: 40px;
            page-break-inside: avoid;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-dark);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            color: var(--primary-yellow);
            font-size: 24px;
        }
        
        .logo-text h1 {
            font-size: 32px;
            font-weight: 900;
            color: var(--primary-dark);
        }
        
        /* Informações do documento */
        .doc-info {
            background: var(--primary-dark);
            color: white;
            padding: 30px 40px;
            page-break-inside: avoid;
        }
        
        .doc-title h2 {
            font-size: 28px;
            color: var(--primary-yellow);
            margin-bottom: 10px;
        }
        
        .doc-subtitle {
            font-size: 16px;
            color: var(--light-blue);
        }
        
        /* Conteúdo */
        .content {
            padding: 40px;
        }
        
        .section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--primary-yellow);
            display: inline-block;
        }
        
        /* Conceitos */
        .concepts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .concept-card {
            background: white;
            border: 2px solid var(--bg-light);
            border-radius: 10px;
            padding: 20px;
        }
        
        .concept-term {
            font-weight: 700;
            color: var(--primary-blue);
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .concept-definition {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }
        
        /* Tabelas */
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            page-break-inside: avoid;
        }
        
        .comparison-table thead {
            background: var(--primary-dark);
            color: white;
        }
        
        .comparison-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .comparison-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .comparison-table tbody tr:nth-child(even) {
            background: var(--bg-light);
        }
        
        /* Diagramas */
        .diagram-container {
            margin: 30px 0;
            text-align: center;
            page-break-inside: avoid;
        }
        
        .diagram-title {
            color: var(--primary-blue);
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .diagram-svg {
            max-width: 100%;
            height: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        /* Footer */
        .footer {
            background: var(--primary-dark);
            color: white;
            padding: 40px;
            margin-top: 60px;
            page-break-inside: avoid;
        }
        
        .footer-text {
            color: var(--light-blue);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header -->
        <header class="header">
            <div class="logo">
                <div class="logo-icon">LAB</div>
                <div class="logo-text">
                    <h1>lab resumos</h1>
                </div>
            </div>
        </header>
        
        <!-- Informações do documento -->
        <div class="doc-info">
            <div class="doc-title">
                <h2>{{ titulo_material }}</h2>
                <p class="doc-subtitle">{{ subtitulo }}</p>
            </div>
            <div style="margin-top: 20px;">
                <span>{{ nome_curso }} - Módulo {{ numero_modulo }}</span><br>
                <span>{{ data_geracao }}</span>
            </div>
        </div>
        
        <!-- Conteúdo principal -->
        <main class="content">
            
            
            <!-- Ponto Importante -->
            {% if ponto_importante %}
            <section class="section">
                <div style="background: #fffde7; border-left: 5px solid var(--primary-yellow); padding: 20px; border-radius: 10px;">
                    <h3 style="color: var(--primary-dark); margin-bottom: 10px;">💡 Ponto Importante</h3>
                    <p>{{ ponto_importante }}</p>
                </div>
            </section>
            {% endif %}
            
            <!-- Conteúdo Principal -->
            {% if conteudo_principal %}
            <section class="section">
                <h2 class="section-title">{{ titulo_secao_principal or "Conteúdo Principal" }}</h2>
                <p>{{ conteudo_principal }}</p>
            </section>
            {% endif %}
            
            <!-- Diagramas SVG -->
            {% if diagramas %}
            <section class="section">
                <h2 class="section-title">Diagramas Ilustrativos</h2>
                {% for diagrama in diagramas %}
                <div class="diagram-container">
                    {% if diagrama.titulo %}
                    <h3 class="diagram-title">{{ diagrama.titulo }}</h3>
                    {% endif %}
                    <img src="data:image/svg+xml;base64,{{ diagrama.svg_base64 }}" 
                         alt="{{ diagrama.titulo }}" 
                         class="diagram-svg">
                </div>
                {% endfor %}
            </section>
            {% endif %}
            
            <!-- Conceitos -->
            {% if conceitos %}
            <section class="section">
                <h2 class="section-title">Conceitos-Chave</h2>
                <div class="concepts-grid">
                    {% for conceito in conceitos %}
                    <div class="concept-card">
                        <div class="concept-term">{{ conceito.termo }}</div>
                        <div class="concept-definition">{{ conceito.definicao }}</div>
                    </div>
                    {% endfor %}
                </div>
            </section>
            {% endif %}
            
            
            <!-- Dicas -->
            {% if dicas %}
            <section class="section">
                <h2 class="section-title">Dicas</h2>
                {% for dica in dicas %}
                <div style="background: #fffde7; border-left: 5px solid var(--primary-yellow); padding: 15px; margin-bottom: 15px; border-radius: 10px;">
                    <p>💡 {{ dica }}</p>
                </div>
                {% endfor %}
            </section>
            {% endif %}
            
            <!-- Alertas -->
            {% if alertas %}
            <section class="section">
                <h2 class="section-title">Atenção</h2>
                {% for alerta in alertas %}
                <div style="background: #fff3e0; border-left: 5px solid #FF9800; padding: 15px; margin-bottom: 15px; border-radius: 10px;">
                    <p>⚠️ {{ alerta }}</p>
                </div>
                {% endfor %}
            </section>
            {% endif %}
            
        </main>
        
        <!-- Footer -->
        <footer class="footer">
            <div>
                <p class="footer-text">
                    Material desenvolvido com tecnologia de ponta para maximizar seu aprendizado.<br>
                    Todos os direitos reservados. Reprodução proibida.
                </p>
                <p style="margin-top: 15px; font-size: 12px; color: var(--light-blue);">
                    {% if nome_aluno %}{{ nome_aluno }}{% endif %}
                    {% if cpf_aluno %} - CPF: {{ cpf_aluno }}{% endif %}<br>
                    Versão: {{ versao_documento }} | Gerado em: {{ data_geracao }}
                </p>
            </div>
        </footer>
    </div>
</body>
</html>'''
    
    def _preparar_dados(self, dados: MaterialData) -> Dict[str, Any]:
        """Prepara os dados para o template"""
        # Converter dataclass para dict
        json_data = dados.to_dict()
        
        # Processar diagramas se houver
        if 'diagramas' in json_data and json_data['diagramas']:
            diagramas_processados = []
            
            for diagrama_config in json_data['diagramas']:
                if isinstance(diagrama_config, dict):
                    tipo = diagrama_config.get('tipo')
                    dados_diagrama = diagrama_config.get('dados', {})
                    titulo = diagrama_config.get('titulo', '')
                    
                    # Converter tipo se for enum
                    if hasattr(tipo, 'value'):
                        tipo = TipoDiagrama(tipo.value)
                    elif isinstance(tipo, str):
                        tipo = TipoDiagrama(tipo)
                    
                    # Gerar SVG
                    svg = self.gerador_svg.gerar_por_tipo(tipo, dados_diagrama)
                    
                    # Converter para base64
                    diagramas_processados.append({
                        'titulo': titulo,
                        'svg': svg,
                        'svg_base64': self.gerador_svg.svg_para_base64(svg)
                    })
            
            json_data['diagramas'] = diagramas_processados
        
        # Garantir que todos os campos existam
        campos_padrao = {
            'titulo_material': '',
            'subtitulo': '',
            'nome_curso': '',
            'numero_modulo': '1',
            'nome_aluno': '',
            'cpf_aluno': '',
            'introducao': '',
            'ponto_importante': '',
            'titulo_secao_principal': 'Conteúdo Principal',
            'conteudo_principal': '',
            'data_geracao': datetime.now().strftime('%d/%m/%Y'),
            'versao_documento': '1.0',
            'conceitos': [],
            'diagramas': [],
            'quadro_comparativo': {},
            'dicas': [],
            'alertas': []
        }
        
        # Mesclar com valores padrão
        for key, default_value in campos_padrao.items():
            if key not in json_data or json_data[key] is None:
                json_data[key] = default_value
        
        return json_data
    
    async def gerar_pdf(self, dados: MaterialData, caminho_saida: str) -> Dict[str, Any]:
        """
        Gera o PDF diretamente sem usar API externa
        
        Args:
            dados: Dados do material
            caminho_saida: Caminho onde salvar o PDF
            
        Returns:
            Dict com informações sobre o resultado
        """
        try:
            print(f"📄 Gerando PDF: {caminho_saida}")
            
            # Preparar dados
            dados_template = self._preparar_dados(dados)
            
            # Renderizar template
            from jinja2 import Template
            template = Template(self.template_html)
            html_content = template.render(**dados_template)
            
            # Criar diretório se não existir
            Path(caminho_saida).parent.mkdir(parents=True, exist_ok=True)
            
            # Gerar PDF com WeasyPrint
            print("🔄 Convertendo HTML para PDF...")
            
            # Configurar WeasyPrint
            html = HTML(string=html_content)
            
            # Gerar PDF
            html.write_pdf(
                target=caminho_saida,
                stylesheets=[CSS(string='@page { size: A4; margin: 20mm; }')]
            )
            
            # Verificar se foi gerado
            if Path(caminho_saida).exists():
                tamanho = Path(caminho_saida).stat().st_size
                print(f"✅ PDF gerado com sucesso! Tamanho: {tamanho:,} bytes")
                
                return {
                    'success': True,
                    'caminho': caminho_saida,
                    'tamanho': tamanho,
                    'timestamp': datetime.now().isoformat()
                }
            else:
                raise Exception("PDF não foi criado")
                
        except Exception as e:
            print(f"❌ Erro ao gerar PDF: {str(e)}")
            return {
                'success': False,
                'error': str(e),
                'timestamp': datetime.now().isoformat()
            }
    
    def gerar_pdf_sync(self, dados: MaterialData, caminho_saida: str) -> Dict[str, Any]:
        """Versão síncrona do gerador de PDF"""
        return asyncio.run(self.gerar_pdf(dados, caminho_saida))

# ================== FUNÇÕES AUXILIARES ==================
def criar_material_exemplo() -> MaterialDataCompleto:
    """Cria um material de exemplo com todos os campos"""
    return MaterialDataCompleto(
        # Informações básicas
        titulo_material="Sistema Tributário Nacional",
        subtitulo="IPTU - Princípios e Exceções",
        nome_curso="Direito Tributário - RFB",
        numero_modulo="2",
        nome_aluno="Ana Costa",
        cpf_aluno="456.789.123-00",
        
        # Conteúdo principal
        introducao="""
            O IPTU é um imposto de competência municipal que incide sobre 
            a propriedade predial e territorial urbana. Está sujeito aos 
            princípios constitucionais tributários com importantes exceções.
        """,
        ponto_importante="""
            A alteração da base de cálculo do IPTU não se submete ao 
            princípio da noventena, mas deve respeitar a anterioridade.
        """,
        titulo_secao_principal="Princípios Aplicáveis ao IPTU",
        conteudo_principal="""
            O IPTU obedece aos princípios da legalidade, anterioridade e 
            noventena (anterioridade nonagesimal). Contudo, há uma exceção 
            importante: a fixação da base de cálculo não está sujeita à 
            noventena, apenas à anterioridade anual.
            
            Esta exceção é frequentemente cobrada em concursos públicos,
            e as bancas costumam confundir os candidatos trocando
            "base de cálculo" por "alíquota" nas questões.
        """,
        
        # Metadados
        tags=['tributário', 'iptu', 'impostos', 'municipal'],
        nivel_dificuldade="Intermediário",
        tempo_estimado_leitura="15 min",
        
        # Objetivos
        objetivos_aprendizagem=[
            "Compreender os princípios constitucionais aplicáveis ao IPTU",
            "Identificar as exceções à aplicação dos princípios",
            "Diferenciar base de cálculo de alíquota",
            "Aplicar o conhecimento em questões de concurso"
        ],
        
        # Conceitos
        conceitos=[
            {"termo": "IPTU", "definicao": "Imposto sobre Propriedade Predial e Territorial Urbana"},
            {"termo": "Base de Cálculo", "definicao": "Valor venal do imóvel sobre o qual incide o imposto"},
            {"termo": "Alíquota", "definicao": "Percentual aplicado sobre a base de cálculo"},
            {"termo": "Noventena", "definicao": "Princípio da anterioridade nonagesimal (90 dias)"}
        ],
        
        # Quadro comparativo
        quadro_comparativo={
            'headers': ['Princípio', 'Aplicação ao IPTU', 'Exceções'],
            'rows': [
                {
                    'Princípio': 'Legalidade',
                    'Aplicação ao IPTU': 'Aplicável integralmente',
                    'Exceções': 'Não há exceções'
                },
                {
                    'Princípio': 'Anterioridade',
                    'Aplicação ao IPTU': 'Aplicável integralmente',
                    'Exceções': 'Não há exceções'
                },
                {
                    'Princípio': 'Noventena',
                    'Aplicação ao IPTU': 'Aplicável',
                    'Exceções': 'Base de cálculo'
                }
            ]
        },
        
        # Dicas e alertas
        dicas=[
            "Lembre-se: a base de cálculo do IPTU não se submete à noventena",
            "As bancas costumam trocar 'base de cálculo' por 'alíquota' nas questões",
            "A progressividade do IPTU pode ser fiscal ou extrafiscal"
        ],
        alertas=[
            "Cuidado com as pegadinhas sobre as exceções do IPTU",
            "Atenção especial aos prazos de anterioridade",
            "Não confunda IPTU com ITBI ou ITR"
        ],
        
        # Diagramas
        diagramas=[
            {
                'tipo': TipoDiagrama.IPTU_PRINCIPIOS,
                'dados': {},
                'titulo': 'Princípios do IPTU e suas Exceções'
            },
            {
                'tipo': TipoDiagrama.PROCESSO,
                'dados': {
                    'etapas': [
                        {'texto': 'Início', 'tipo': 'inicio'},
                        {'texto': 'Lançamento', 'tipo': 'processo'},
                        {'texto': 'Notificação', 'tipo': 'processo'},
                        {'texto': 'Pagou?', 'tipo': 'decisao'},
                        {'texto': 'Inscrição DA', 'tipo': 'processo'},
                        {'texto': 'Fim', 'tipo': 'fim'}
                    ]
                },
                'titulo': 'Processo de Cobrança do IPTU'
            },
            {
                'tipo': TipoDiagrama.MAPA_MENTAL,
                'dados': {
                    'titulo': 'IPTU',
                    'topicos': {
                        'Princípios': ['Legalidade', 'Anterioridade', 'Noventena'],
                        'Exceções': ['Base de Cálculo', 'Progressividade'],
                        'Competência': ['Municipal', 'DF'],
                        'Fato Gerador': ['Propriedade', 'Posse', 'Domínio Útil']
                    }
                },
                'titulo': 'Mapa Mental - IPTU'
            }
        ]
    )

# ================== MAIN ==================
async def main():
    """Função principal para demonstração"""
    print("=" * 70)
    print("🎯 LAB RESUMOS - GERADOR UNIFICADO DE PDF")
    print("=" * 70)
    print("📚 Sistema completo de geração de materiais educacionais")
    print("🎨 Com suporte a diagramas SVG integrados")
    print("📄 Geração de PDF direto no Python (sem APIs externas)")
    print("=" * 70)
    
    # Criar material de exemplo
    print("\n📝 Criando material de exemplo...")
    material = criar_material_exemplo()
    
    # Inicializar gerador
    print("🔧 Inicializando gerador de PDF...")
    gerador = LabResumosPDFGenerator()
    
    # Criar diretório de saída
    output_dir = Path("outputs")
    output_dir.mkdir(exist_ok=True)
    
    # Gerar PDF
    from datetime import datetime
    caminho_pdf = output_dir / f"pdf_gerado_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
    print(f"\n🚀 Gerando PDF em: {caminho_pdf}")
    
    resultado = await gerador.gerar_pdf(material, str(caminho_pdf))
    
    # Mostrar resultado
    print("\n" + "=" * 70)
    if resultado['success']:
        print("✅ PDF GERADO COM SUCESSO!")
        print(f"📁 Arquivo: {resultado['caminho']}")
        print(f"📊 Tamanho: {resultado['tamanho']:,} bytes")
        print(f"⏰ Timestamp: {resultado['timestamp']}")
        print("\n📋 Conteúdo incluído:")
        print("   • Diagrama dos Princípios do IPTU")
        print("   • Fluxograma do Processo de Cobrança")
        print("   • Mapa Mental do IPTU")
        print("   • Quadro Comparativo")
        print("   • Conceitos-Chave")
        print("   • Dicas e Alertas")
    else:
        print("❌ ERRO AO GERAR PDF")
        print(f"💥 Erro: {resultado['error']}")
    
    print("=" * 70)
    
    # Gerar mais exemplos
    print("\n📚 Gerando exemplos adicionais...")
    
    # Material simples
    material_simples = MaterialData(
        titulo_material="Resumo Básico",
        subtitulo="Exemplo Simplificado",
        introducao="Este é um exemplo básico de material.",
        conteudo_principal="Conteúdo principal do material de estudo."
    )
    
    resultado_simples = await gerador.gerar_pdf(
        material_simples, 
        str(output_dir / f"pdf_gerado_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf")
    )
    
    if resultado_simples['success']:
        print("✅ Material simples gerado")
    
    print("\n🎉 Processo concluído!")
    print("=" * 70)

if __name__ == "__main__":
    # Executar função principal
    asyncio.run(main())