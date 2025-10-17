"""
Extensão do Lab Resumos com geração de diagramas SVG
Adiciona capacidade de criar diagramas educacionais automaticamente
Integrado com o sistema existente do Lab Resumos
"""

import base64
from typing import Dict, List, Any, Optional, Tuple
from dataclasses import dataclass, field
from enum import Enum
import re
from math import cos, sin
import asyncio
from datetime import datetime
import os
import sys

# Adiciona o diretório raiz ao path para importar config
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Importa o sistema base existente
from scripts.lab_resumos_generator import (
    LabResumosPDFGenerator, 
    MaterialData, 
    TipoMaterial
)

# Importa o gerador de diagramas SVG
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from scripts.svg_diagram_generator import GeradorDiagramaSVG, TipoDiagramaSVG, DiagramaSVG

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

@dataclass
class ElementoDiagrama:
    """Elemento básico de um diagrama"""
    id: str
    texto: str
    tipo: str = "box"  # box, circle, diamond, etc.
    estilo: Dict[str, str] = field(default_factory=dict)
    posicao: Tuple[float, float] = (0, 0)
    dimensoes: Tuple[float, float] = (200, 60)

@dataclass
class ConexaoDiagrama:
    """Conexão entre elementos"""
    origem: str
    destino: str
    texto: Optional[str] = None
    estilo: str = "solid"  # solid, dashed, dotted
    tipo: str = "arrow"  # arrow, line

# ================== GERADOR DE SVG ==================
class GeradorDiagramaSVG:
    """Gera diagramas SVG para materiais educacionais"""
    
    # Cores do Lab Resumos (mantém identidade visual)
    CORES = {
        "amarelo": "#F1CC00",
        "preto": "#333B49",
        "cinza": "#6C757D",
        "branco": "#FFFFFF",
        "vermelho": "#E74C3C",
        "verde": "#27AE60",
        "azul": "#3498DB",
        "fundo_claro": "#FFF9E6"
    }
    
    # Estilos padrão (mantém tipografia Lab Resumos)
    ESTILOS = {
        "fonte_principal": "Figtree, Arial, sans-serif",
        "tamanho_titulo": 24,
        "tamanho_texto": 16,
        "tamanho_pequeno": 14
    }
    
    def __init__(self):
        """Inicializa o gerador de diagramas"""
        self.elementos: List[ElementoDiagrama] = []
        self.conexoes: List[ConexaoDiagrama] = []
        self.largura = 1000
        self.altura = 600


    def gerar_diagrama_iptu(self) -> str:
        """
        Gera o diagrama específico do IPTU
        Exemplo exato do diagrama solicitado
        """
        svg = f"""<svg width="1100" height="400" xmlns="http://www.w3.org/2000/svg">
            <!-- Definições de estilos -->
            <defs>
                <style>
                    .main-text {{ 
                        font-family: {self.ESTILOS['fonte_principal']}; 
                        font-size: 28px; 
                        font-weight: bold; 
                        fill: {self.CORES['preto']};
                    }}
                    .principle-text {{ 
                        font-family: {self.ESTILOS['fonte_principal']}; 
                        font-size: 24px; 
                        fill: {self.CORES['preto']};
                    }}
                    .small-text {{
                        font-family: {self.ESTILOS['fonte_principal']}; 
                        font-size: 20px; 
                        fill: {self.CORES['preto']};
                    }}
                    .red-text {{ 
                        fill: {self.CORES['vermelho']};
                        font-style: italic;
                    }}
                    .box {{
                        fill: {self.CORES['branco']};
                        stroke: {self.CORES['preto']};
                        stroke-width: 2;
                        rx: 15;
                    }}
                    .highlight-box {{
                        fill: {self.CORES['fundo_claro']};
                        stroke: {self.CORES['amarelo']};
                        stroke-width: 3;
                        rx: 15;
                    }}
                    .dashed-box {{
                        fill: {self.CORES['branco']};
                        stroke: {self.CORES['cinza']};
                        stroke-width: 1.5;
                        stroke-dasharray: 8,4;
                        rx: 10;
                    }}
                    .connector {{
                        stroke: {self.CORES['preto']};
                        stroke-width: 2;
                        fill: none;
                    }}
                    .dashed-connector {{
                        stroke: {self.CORES['cinza']};
                        stroke-width: 1.5;
                        stroke-dasharray: 8,4;
                        fill: none;
                    }}
                </style>
                <linearGradient id="bgGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:{self.CORES['branco']};stop-opacity:1" />
                    <stop offset="100%" style="stop-color:{self.CORES['fundo_claro']};stop-opacity:0.3" />
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

            <!-- Caixa Exceção - AJUSTADA -->
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

            <!-- Caixa pontilhada - AJUSTADA -->
            <rect x="770" y="260" width="260" height="80" class="dashed-box"/>
            
            <!-- Texto da caixa pontilhada -->
            <text x="900" y="290" class="small-text" text-anchor="middle">
                As bancas trocam por
            </text>
            <text x="900" y="315" class="small-text" text-anchor="middle">
                "<tspan class="red-text" font-style="italic">alíquota</tspan>"
            </text>

            <!-- Marcador de ponto de conexão (círculo pequeno) -->
            <circle cx="450" cy="180" r="4" fill="{self.CORES['preto']}"/>
            <circle cx="720" cy="280" r="4" fill="{self.CORES['preto']}"/>
            
            <!-- Logo Lab Resumos (marca d'água sutil) -->
            <text x="1050" y="390" style="font-family: {self.ESTILOS['fonte_principal']}; font-size: 10px; fill: {self.CORES['cinza']}; opacity: 0.5;" text-anchor="end">
                Lab Resumos © 2024
            </text>
        </svg>"""
        
        return svg




    def gerar_mapa_mental(self, titulo: str, topicos: Dict[str, List[str]]) -> str:
        """
        Gera um mapa mental
        
        Args:
            titulo: Título central
            topicos: Dict com tópicos principais e subtópicos
        """
        svg_parts = [f'<svg width="{self.largura}" height="{self.altura}" xmlns="http://www.w3.org/2000/svg">']
        
        # Estilos
        svg_parts.append(self._gerar_estilos())
        
        # Centro
        cx, cy = self.largura / 2, self.altura / 2
        svg_parts.append(f'''
            <circle cx="{cx}" cy="{cy}" r="80" fill="{self.CORES['amarelo']}" stroke="{self.CORES['preto']}" stroke-width="3"/>
            <text x="{cx}" y="{cy}" text-anchor="middle" class="main-text">{titulo}</text>
        ''')
        
        # Distribuir tópicos em círculo
        num_topicos = len(topicos)
        angulo_base = 360 / num_topicos if num_topicos > 0 else 0
        
        for i, (topico, subtopicos) in enumerate(topicos.items()):
            angulo = angulo_base * i - 90  # Começar do topo
            rad = angulo * 3.14159 / 180
            
            # Posição do tópico principal
            tx = cx + 200 * cos(rad)
            ty = cy + 200 * sin(rad)
            
            # Linha conectora
            svg_parts.append(f'<line x1="{cx}" y1="{cy}" x2="{tx}" y2="{ty}" class="connector"/>')
            
            # Caixa do tópico
            svg_parts.append(f'''
                <rect x="{tx-75}" y="{ty-25}" width="150" height="50" class="box"/>
                <text x="{tx}" y="{ty+5}" text-anchor="middle" class="principle-text">{topico}</text>
            ''')
            
            # Subtópicos
            for j, subtopico in enumerate(subtopicos[:3]):  # Limitar a 3 subtópicos
                sx = tx + 120
                sy = ty + (j - 1) * 40
                
                svg_parts.append(f'''
                    <line x1="{tx+75}" y1="{ty}" x2="{sx}" y2="{sy}" class="dashed-connector"/>
                    <rect x="{sx}" y="{sy-15}" width="120" height="30" class="dashed-box"/>
                    <text x="{sx+60}" y="{sy+3}" text-anchor="middle" style="font-size: 14px;">{subtopico}</text>
                ''')
        
        svg_parts.append('</svg>')
        return ''.join(svg_parts)
    
    def gerar_fluxograma_processo(self, processo: List[Dict[str, Any]]) -> str:
        """
        Gera um fluxograma de processo
        
        Args:
            processo: Lista de etapas do processo
        """
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
                svg_parts.append(f'''
                    <circle cx="{x}" cy="{y_base}" r="40" fill="{self.CORES['verde'] if tipo == 'inicio' else self.CORES['vermelho']}" 
                            stroke="{self.CORES['preto']}" stroke-width="2"/>
                    <text x="{x}" y="{y_base+5}" text-anchor="middle" fill="white" style="font-weight: bold;">
                        {etapa['texto']}
                    </text>
                ''')
            elif tipo == 'decisao':
                # Losango para decisão
                svg_parts.append(f'''
                    <polygon points="{x},{y_base-50} {x+60},{y_base} {x},{y_base+50} {x-60},{y_base}" 
                             fill="{self.CORES['amarelo']}" stroke="{self.CORES['preto']}" stroke-width="2"/>
                    <text x="{x}" y="{y_base+5}" text-anchor="middle" class="principle-text">
                        {etapa['texto']}
                    </text>
                ''')
            else:
                # Retângulo para processo normal
                svg_parts.append(f'''
                    <rect x="{x-80}" y="{y_base-30}" width="160" height="60" class="box"/>
                    <text x="{x}" y="{y_base+5}" text-anchor="middle" class="principle-text">
                        {etapa['texto']}
                    </text>
                ''')
            
            # Conectar com próxima etapa
            if i < num_etapas - 1:
                x_next = espacamento * (i + 2)
                svg_parts.append(f'''
                    <line x1="{x+80}" y1="{y_base}" x2="{x_next-80}" y2="{y_base}" 
                          marker-end="url(#arrowhead)" class="connector"/>
                ''')
        
        # Adicionar marcador de seta
        svg_parts.append('''
            <defs>
                <marker id="arrowhead" markerWidth="10" markerHeight="7" 
                        refX="10" refY="3.5" orient="auto">
                    <polygon points="0 0, 10 3.5, 0 7" fill="#333B49"/>
                </marker>
            </defs>
        ''')
        
        svg_parts.append('</svg>')
        return ''.join(svg_parts)
    
    def _gerar_estilos(self) -> str:
        """Gera estilos padrão para os diagramas"""
        return f'''
            <defs>
                <style>
                    .main-text {{ 
                        font-family: {self.ESTILOS['fonte_principal']}; 
                        font-size: {self.ESTILOS['tamanho_titulo']}px; 
                        font-weight: bold; 
                        fill: {self.CORES['preto']};
                    }}
                    .principle-text {{ 
                        font-family: {self.ESTILOS['fonte_principal']}; 
                        font-size: {self.ESTILOS['tamanho_texto']}px; 
                        fill: {self.CORES['preto']};
                    }}
                    .box {{
                        fill: {self.CORES['branco']};
                        stroke: {self.CORES['preto']};
                        stroke-width: 2;
                        rx: 10;
                    }}
                    .dashed-box {{
                        fill: {self.CORES['branco']};
                        stroke: {self.CORES['cinza']};
                        stroke-width: 1.5;
                        stroke-dasharray: 8,4;
                        rx: 10;
                    }}
                    .connector {{
                        stroke: {self.CORES['preto']};
                        stroke-width: 2;
                        fill: none;
                    }}
                    .dashed-connector {{
                        stroke: {self.CORES['cinza']};
                        stroke-width: 1.5;
                        stroke-dasharray: 8,4;
                        fill: none;
                    }}
                </style>
            </defs>
        '''
    
    def svg_para_base64(self, svg: str) -> str:
        """
        Converte SVG para base64 para embedding
        
        Args:
            svg: String SVG
            
        Returns:
            String base64
        """
        svg_bytes = svg.encode('utf-8')
        return base64.b64encode(svg_bytes).decode('utf-8')
    
    def svg_para_img_tag(self, svg: str) -> str:
        """
        Cria tag HTML img com SVG embutido
        
        Args:
            svg: String SVG
            
        Returns:
            Tag HTML img
        """
        base64_svg = self.svg_para_base64(svg)
        return f'<img src="data:image/svg+xml;base64,{base64_svg}" alt="Diagrama"/>'

# ================== INTEGRAÇÃO COM MATERIAL DATA ==================
@dataclass
class MaterialDataComDiagrama(MaterialData):
    """Extensão do MaterialData com suporte a diagramas"""
    diagramas: List[Dict[str, Any]] = field(default_factory=list)
    
    def adicionar_diagrama(self, tipo: TipoDiagrama, dados: Dict[str, Any], titulo: str = ""):
        """
        Adiciona um diagrama ao material
        
        Args:
            tipo: Tipo do diagrama
            dados: Dados para gerar o diagrama
            titulo: Título opcional do diagrama
        """
        self.diagramas.append({
            "tipo": tipo,
            "dados": dados,
            "titulo": titulo
        })

# ================== EXTENSÃO DO GERADOR PDF ==================
class LabResumosPDFGeneratorComDiagramas(LabResumosPDFGenerator):
    """Versão estendida do gerador com suporte a diagramas"""
    
    def __init__(self, api_key: str = None):
        """Inicializa o gerador estendido"""
        super().__init__(api_key)
        self.gerador_svg = GeradorDiagramaSVG()
        print("🎨 Extensão de diagramas SVG carregada")
    
    def _preparar_dados(self, dados: MaterialData) -> Dict[str, Any]:
        """
        Prepara os dados incluindo diagramas SVG
        
        Args:
            dados: Dados do material (pode incluir diagramas)
            
        Returns:
            Dict formatado para o template
        """
        # Preparação básica
        json_data = super()._preparar_dados(dados)
        
        # Se houver diagramas, processar
        if isinstance(dados, MaterialDataComDiagrama) and dados.diagramas:
            diagramas_processados = []
            
            print(f"🎨 Processando {len(dados.diagramas)} diagramas...")
            
            for i, diagrama_config in enumerate(dados.diagramas, 1):
                tipo = diagrama_config['tipo']
                dados_diagrama = diagrama_config['dados']
                titulo = diagrama_config.get('titulo', '')
                
                print(f"  📊 Diagrama {i}: {tipo.value} - {titulo}")
                
                # Gerar SVG baseado no tipo
                svg = self._gerar_svg_por_tipo(tipo, dados_diagrama)
                
                # Converter para formato embutível
                diagramas_processados.append({
                    'titulo': titulo,
                    'svg': svg,
                    'svg_base64': self.gerador_svg.svg_para_base64(svg),
                    'img_tag': self.gerador_svg.svg_para_img_tag(svg)
                })
            
            json_data['diagramas'] = diagramas_processados
            print(f"✅ {len(diagramas_processados)} diagramas processados e prontos para PDF")
        
        return json_data
    
    def _gerar_svg_por_tipo(self, tipo: TipoDiagrama, dados: Dict[str, Any]) -> str:
        """
        Gera SVG baseado no tipo de diagrama
        
        Args:
            tipo: Tipo do diagrama
            dados: Dados para o diagrama
            
        Returns:
            String SVG
        """
        if tipo == TipoDiagrama.PROCESSO:
            return self.gerador_svg.gerar_fluxograma_processo(dados.get('etapas', []))
        elif tipo == TipoDiagrama.MAPA_MENTAL:
            return self.gerador_svg.gerar_mapa_mental(
                dados.get('titulo', ''),
                dados.get('topicos', {})
            )
        else:
            # Para exemplo específico do IPTU
            if dados.get('tipo_especifico') == 'iptu':
                return self.gerador_svg.gerar_diagrama_iptu()
            
                            # Diagrama genérico - fluxograma simples
                return self.gerador_svg.gerar_fluxograma_processo([
                    {'texto': 'Início', 'tipo': 'inicio'},
                    {'texto': 'Processo', 'tipo': 'processo'},
                    {'texto': 'Fim', 'tipo': 'fim'}
                ])
    
    async def gerar_diagrama_svg_html(self, tipo: TipoDiagramaSVG, dados: Dict[str, Any], 
                                     caminho_arquivo: str) -> bool:
        """
        Gera um diagrama SVG como arquivo HTML standalone
        
        Args:
            tipo: Tipo do diagrama SVG
            dados: Dados para o diagrama
            caminho_arquivo: Caminho onde salvar o arquivo HTML
            
        Returns:
            bool: True se gerou com sucesso
        """
        try:
            print(f"🎨 Gerando diagrama SVG: {tipo.value}")
            
            # Gerar diagrama usando o novo gerador SVG
            diagrama = self.gerador_svg.gerar_diagrama(tipo, dados)
            
            # Salvar como HTML
            sucesso = self.gerador_svg.salvar_html(diagrama, caminho_arquivo)
            
            if sucesso:
                print(f"✅ Diagrama SVG salvo em: {caminho_arquivo}")
            else:
                print(f"❌ Erro ao salvar diagrama SVG")
                
            return sucesso
            
        except Exception as e:
            print(f"❌ Erro ao gerar diagrama SVG: {str(e)}")
            return False
    
    async def gerar_lote_diagramas_svg(self, diagramas: List[Tuple[TipoDiagramaSVG, Dict[str, Any], str]]) -> List[bool]:
        """
        Gera múltiplos diagramas SVG em lote
        
        Args:
            diagramas: Lista de tuplas (tipo, dados, caminho_arquivo)
            
        Returns:
            List[bool]: Lista de sucessos para cada diagrama
        """
        resultados = []
        
        print(f"🎨 Gerando {len(diagramas)} diagramas SVG em lote...")
        
        for i, (tipo, dados, caminho) in enumerate(diagramas, 1):
            print(f"📊 Processando diagrama {i}/{len(diagramas)}: {tipo.value}")
            resultado = await self.gerar_diagrama_svg_html(tipo, dados, caminho)
            resultados.append(resultado)
        
        sucessos = sum(resultados)
        print(f"✅ {sucessos}/{len(diagramas)} diagramas gerados com sucesso")
        
        return resultados
    
    def obter_svg_puro(self, tipo: TipoDiagramaSVG, dados: Dict[str, Any]) -> str:
        """
        Obtém apenas o conteúdo SVG (sem HTML wrapper)
        Útil para embedding em outros documentos
        
        Args:
            tipo: Tipo do diagrama
            dados: Dados para o diagrama
            
        Returns:
            str: Conteúdo SVG puro
        """
        try:
            diagrama = self.gerador_svg.gerar_diagrama(tipo, dados)
            return self.gerador_svg.gerar_svg_puro(diagrama)
        except Exception as e:
            print(f"❌ Erro ao gerar SVG puro: {str(e)}")
            return ""

# ================== FUNÇÃO TEMPLATE COMPLETO ==================
def criar_dados_completos_template():
    """
    Cria um dicionário com TODOS os campos esperados pelo template,
    com valores padrão para evitar erros de undefined
    """
    return {
        # Campos básicos (já existentes)
        'titulo_material': '',
        'subtitulo': '',
        'nome_curso': '',
        'numero_modulo': '',
        'data_geracao': datetime.now().strftime('%d/%m/%Y'),
        'nome_aluno': '',
        'cpf_aluno': '',
        'introducao': '',
        'ponto_importante': '',
        'titulo_secao_principal': '',
        'conteudo_principal': '',
        
        # Campos adicionais esperados pelo template
        'nivel_dificuldade': 'Intermediário',
        'tempo_estimado_leitura': '15 min',
        'versao_documento': '1.0',
        
        # Tags
        'tags': ['tributário', 'iptu', 'impostos'],
        
        # Objetivos
        'objetivos_aprendizagem': [
            'Compreender os princípios do IPTU',
            'Identificar as exceções aplicáveis',
            'Aplicar o conhecimento em casos práticos'
        ],
        
        # Tópicos principais
        'topicos_principais': [
            {
                'titulo': 'Princípios Constitucionais',
                'conteudo': 'Os princípios constitucionais aplicáveis ao IPTU',
                'subtopicos': ['Legalidade', 'Anterioridade', 'Noventena']
            }
        ],
        
        # Conceitos (já existe mas renomear)
        'conceitos': [],
        'conceitos_chave': [],  # Versão alternativa do nome
        
        # QUADRO COMPARATIVO - CAMPO FALTANTE CRÍTICO
        'quadro_comparativo': {
            'headers': ['Princípio', 'Aplicação ao IPTU', 'Exceções'],
            'rows': [
                {
                    'Princípio': 'Legalidade',
                    'Aplicação ao IPTU': 'Aplicável integralmente',
                    'Exceções': 'Não há exceções'
                },
                {
                    'Princípio': 'Anterioridade',
                    'Aplicação ao IPTU': 'Aplicável',
                    'Exceções': 'Não há exceções'
                },
                {
                    'Princípio': 'Noventena',
                    'Aplicação ao IPTU': 'Aplicável',
                    'Exceções': 'Base de cálculo'
                }
            ]
        },
        
        # Gráficos
        'grafico_linha': {
            'titulo': 'Evolução da Arrecadação',
            'labels': ['Jan', 'Fev', 'Mar', 'Abr', 'Mai'],
            'dados': [100, 120, 115, 130, 140],
            'cor': '#2A6B9F'
        },
        'grafico_pizza': {
            'titulo': 'Distribuição por Tipo',
            'labels': ['Residencial', 'Comercial', 'Industrial'],
            'dados': [60, 30, 10],
            'cores': ['#F1CC00', '#2A6B9F', '#333B49']
        },
        'grafico_barras': {
            'titulo': 'Comparativo Mensal',
            'categorias': ['Jan', 'Fev', 'Mar'],
            'series': [
                {'nome': '2024', 'dados': [100, 110, 120]},
                {'nome': '2025', 'dados': [120, 130, 140]}
            ]
        },
        
        # Timeline
        'linha_tempo': [
            {'data': '01/01', 'evento': 'Início do exercício fiscal'},
            {'data': '31/03', 'evento': 'Prazo para pagamento à vista'},
            {'data': '31/12', 'evento': 'Encerramento do exercício'}
        ],
        
        # Checklist
        'lista_verificacao': [
            {'item': 'Estudar princípios', 'concluido': True, 'prioridade': 'Alta'},
            {'item': 'Resolver exercícios', 'concluido': False, 'prioridade': 'Media'},
            {'item': 'Revisar exceções', 'concluido': False, 'prioridade': 'Alta'}
        ],
        
        # Progresso
        'indicadores_progresso': {
            'teoria_completa': 75,
            'exercicios_resolvidos': 60,
            'projetos_entregues': 40
        },
        
        # Métricas
        'metricas_resumo': [
            {'metrica': 'Acertos', 'valor': '85%', 'variacao': '+5%'},
            {'metrica': 'Tempo', 'valor': '2h', 'variacao': '-30min'},
            {'metrica': 'Módulos', 'valor': '8/10', 'variacao': '+2'}
        ],
        
        # Dicas e alertas
        'dicas': [
            'Lembre-se: a base de cálculo do IPTU não se submete à noventena',
            'As bancas costumam trocar "base de cálculo" por "alíquota" nas questões'
        ],
        'alertas': [
            'Cuidado com as pegadinhas sobre as exceções do IPTU',
            'Atenção especial aos prazos de anterioridade'
        ],
        
        # Exemplos práticos
        'exemplos_praticos': [
            {
                'titulo': 'Cálculo do IPTU',
                'descricao': 'Como calcular o valor do imposto',
                'codigo': '''valor_venal = 500000  # R$ 500.000,00
aliquota = 0.01  # 1%
iptu = valor_venal * aliquota
print(f"IPTU: R$ {iptu:,.2f}")'''
            }
        ],
        
        # Citações
        'citacoes': [
            {
                'texto': 'O poder de tributar não pode ser exercido de modo a impedir o exercício de direitos fundamentais',
                'autor': 'STF',
                'ano': '2020'
            }
        ],
        
        # QR Code
        'qr_code_data': 'https://labresumos.com/material/iptu-principios',
        
        # Referências
        'referencias_bibliograficas': [
            'ALEXANDRE, Ricardo. Direito Tributário. 14ª ed. Salvador: JusPodivm, 2020.',
            'CARNEIRO, Claudio. Impostos Federais, Estaduais e Municipais. 6ª ed. São Paulo: Saraiva, 2019.'
        ],
        
        # Links úteis
        'links_uteis': [
            {'titulo': 'Portal RFB', 'url': 'https://www.gov.br/receitafederal'},
            {'titulo': 'STF - Jurisprudência', 'url': 'https://portal.stf.jus.br'}
        ],
        
        # Questões
        'questoes_multipla_escolha': [
            {
                'pergunta': 'Qual princípio NÃO se aplica à fixação da base de cálculo do IPTU?',
                'alternativas': [
                    'a) Legalidade',
                    'b) Anterioridade',
                    'c) Noventena',
                    'd) Irretroatividade'
                ],
                'resposta_correta': 'c'
            }
        ],
        'questoes_dissertativas': [
            'Explique a diferença entre base de cálculo e alíquota no contexto do IPTU.'
        ],
        
        # Glossário
        'glossario': [
            {'termo': 'IPTU', 'definicao': 'Imposto sobre Propriedade Predial e Territorial Urbana'},
            {'termo': 'Base de Cálculo', 'definicao': 'Valor venal do imóvel'},
            {'termo': 'Alíquota', 'definicao': 'Percentual aplicado sobre a base de cálculo'}
        ],
        
        # Fórmulas
        'formulas_matematicas': [
            {
                'nome': 'Cálculo do IPTU',
                'formula': 'IPTU = Valor Venal × Alíquota',
                'descricao': 'Fórmula básica para cálculo do imposto'
            }
        ],
        
        # Tabela de dados
        'tabela_dados': {
            'titulo': 'Arrecadação por Região',
            'headers': ['Região', 'Jan', 'Fev', 'Mar', 'Total'],
            'rows': [
                ['Norte', '100', '110', '120', '330'],
                ['Sul', '200', '210', '220', '630'],
                ['Centro', '150', '160', '170', '480']
            ],
            'totais': ['Total', '450', '480', '510', '1440']
        },
        
        # Cronograma
        'tabela_cronograma': [
            {
                'fase': 'Módulo 1',
                'inicio': '01/01',
                'fim': '15/01',
                'responsavel': 'Professor',
                'status': 'Concluído'
            },
            {
                'fase': 'Módulo 2',
                'inicio': '16/01',
                'fim': '31/01',
                'responsavel': 'Professor',
                'status': 'Em andamento'
            }
        ],
        
        # Lista aninhada
        'lista_aninhada': [
            {
                'titulo': 'Impostos Municipais',
                'itens': ['IPTU', 'ISS', 'ITBI']
            },
            {
                'titulo': 'Impostos Estaduais',
                'itens': ['ICMS', 'IPVA', 'ITCMD']
            }
        ],
        
        # Diagrama de fluxo
        'diagrama_fluxo': [
            {'acao': 'Início', 'tipo': 'terminal'},
            {'acao': 'Lançamento do IPTU', 'tipo': 'processo'},
            {'acao': 'Pagou?', 'tipo': 'decisao'},
            {'acao': 'Inscrição em Dívida Ativa', 'tipo': 'processo'},
            {'acao': 'Fim', 'tipo': 'terminal'}
        ],
        
        # Campos de tabela (compatibilidade)
        'tabela_headers': [],
        'tabela_rows': [],
        
        # Diagramas (será preenchido se houver)
        'diagramas': []
    }


# ================== CLASSE CORRIGIDA ==================
class LabResumosPDFGeneratorComDiagramasCorrigido(LabResumosPDFGeneratorComDiagramas):
    """Versão corrigida que garante todos os campos do template"""
    
    def _preparar_dados(self, dados: MaterialData) -> Dict[str, Any]:
        """
        Prepara os dados garantindo que TODOS os campos esperados pelo template existam
        """
        # Começar com todos os campos padrão
        json_data = criar_dados_completos_template()
        
        # Sobrescrever com os dados fornecidos
        if hasattr(dados, 'titulo_material'):
            json_data['titulo_material'] = dados.titulo_material
        if hasattr(dados, 'subtitulo'):
            json_data['subtitulo'] = dados.subtitulo
        if hasattr(dados, 'nome_curso'):
            json_data['nome_curso'] = dados.nome_curso
        if hasattr(dados, 'numero_modulo'):
            json_data['numero_modulo'] = dados.numero_modulo
        if hasattr(dados, 'nome_aluno'):
            json_data['nome_aluno'] = dados.nome_aluno
        if hasattr(dados, 'cpf_aluno'):
            json_data['cpf_aluno'] = dados.cpf_aluno
        if hasattr(dados, 'introducao'):
            json_data['introducao'] = dados.introducao
        if hasattr(dados, 'ponto_importante'):
            json_data['ponto_importante'] = dados.ponto_importante
        if hasattr(dados, 'titulo_secao_principal'):
            json_data['titulo_secao_principal'] = dados.titulo_secao_principal
        if hasattr(dados, 'conteudo_principal'):
            json_data['conteudo_principal'] = dados.conteudo_principal
        
        # Processar conceitos
        if hasattr(dados, 'conceitos') and dados.conceitos:
            json_data['conceitos'] = dados.conceitos
            json_data['conceitos_chave'] = dados.conceitos
            # Também adicionar ao glossário se necessário
            if not json_data['glossario']:
                json_data['glossario'] = dados.conceitos
        
        # Processar diagramas se for MaterialDataComDiagrama
        if isinstance(dados, MaterialDataComDiagrama) and dados.diagramas:
            diagramas_processados = []
            
            print(f"🎨 Processando {len(dados.diagramas)} diagramas...")
            
            for i, diagrama_config in enumerate(dados.diagramas, 1):
                tipo = diagrama_config['tipo']
                dados_diagrama = diagrama_config['dados']
                titulo = diagrama_config.get('titulo', '')
                
                print(f"  📊 Diagrama {i}: {tipo.value} - {titulo}")
                
                # Gerar SVG baseado no tipo
                svg = self._gerar_svg_por_tipo(tipo, dados_diagrama)
                
                # Converter para formato embutível
                diagramas_processados.append({
                    'titulo': titulo,
                    'svg': svg,
                    'svg_base64': self.gerador_svg.svg_para_base64(svg),
                    'img_tag': self.gerador_svg.svg_para_img_tag(svg)
                })
            
            json_data['diagramas'] = diagramas_processados
            print(f"✅ {len(diagramas_processados)} diagramas processados e prontos para PDF")
        
        return json_data


# ================== EXEMPLO DE USO COMPLETO ==================
async def exemplo_com_diagramas():
    """Demonstra o uso com diagramas SVG integrados"""
    
    print("🎨 Gerando material com diagramas SVG...")
    print("=" * 60)
    
    # Criar material com diagrama do IPTU
    material_tributario = MaterialDataComDiagrama(
        titulo_material="Sistema Tributário Nacional",
        subtitulo="IPTU - Princípios e Exceções",
        nome_curso="Direito Tributário - RFB",
        numero_modulo="2",
        nome_aluno="Ana Costa",
        cpf_aluno="456.789.123-00",
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
        """,
        conceitos=[
            {
                "termo": "IPTU",
                "definicao": "Imposto sobre Propriedade Predial e Territorial Urbana"
            },
            {
                "termo": "Base de Cálculo",
                "definicao": "Valor venal do imóvel"
            },
            {
                "termo": "Alíquota",
                "definicao": "Percentual aplicado sobre a base de cálculo"
            }
        ]
    )
    
    print("📊 Adicionando diagramas ao material...")
    
    # Adicionar o diagrama do IPTU
    material_tributario.adicionar_diagrama(
        tipo=TipoDiagrama.HIERARQUIA,
        dados={'tipo_especifico': 'iptu'},
        titulo="Princípios do IPTU e suas Exceções"
    )
    
    # Adicionar um fluxograma do processo de cobrança
    material_tributario.adicionar_diagrama(
        tipo=TipoDiagrama.PROCESSO,
        dados={
            'etapas': [
                {'texto': 'Início', 'tipo': 'inicio'},
                {'texto': 'Lançamento', 'tipo': 'processo'},
                {'texto': 'Notificação', 'tipo': 'processo'},
                {'texto': 'Pagou?', 'tipo': 'decisao'},
                {'texto': 'Inscrição DA', 'tipo': 'processo'},
                {'texto': 'Fim', 'tipo': 'fim'}
            ]
        },
        titulo="Processo de Cobrança do IPTU"
    )
    
    # Adicionar mapa mental
    material_tributario.adicionar_diagrama(
        tipo=TipoDiagrama.MAPA_MENTAL,
        dados={
            'titulo': 'IPTU',
            'topicos': {
                'Princípios': ['Legalidade', 'Anterioridade', 'Noventena'],
                'Exceções': ['Base de Cálculo', 'Progressividade'],
                'Competência': ['Municipal', 'DF'],
                'Fato Gerador': ['Propriedade', 'Posse', 'Domínio Útil']
            }
        },
        titulo="Mapa Mental - IPTU"
    )
    
    # Gerar o PDF com diagramas
    print("🔄 Inicializando gerador com suporte a diagramas...")
    generator = LabResumosPDFGeneratorComDiagramas()
    
    # Criar diretório de output se não existir
    os.makedirs("outputs", exist_ok=True)
    
    print("📄 Gerando PDF com diagramas integrados...")
    from datetime import datetime
    resultado = await generator.gerar_material(
        tipo=TipoMaterial.RESUMO,
        dados=material_tributario,
        output_path=f"outputs/pdf_gerado_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
    )
    
    print("\n" + "=" * 60)
    if resultado["success"]:
        print(f"✅ PDF com diagramas gerado com sucesso!")
        print(f"   📁 Arquivo: {resultado['local_path']}")
        print(f"   🌐 URL: {resultado['pdf_url']}")
        print(f"   📊 Diagramas incluídos: 3")
        print(f"      • Diagrama dos Princípios do IPTU")
        print(f"      • Fluxograma do Processo de Cobrança")
        print(f"      • Mapa Mental do IPTU")
        print(f"   ⏰ Gerado em: {resultado['timestamp']}")
    else:
        print(f"❌ Erro: {resultado['error']}")
        if 'status_code' in resultado:
            print(f"   📊 Código HTTP: {resultado['status_code']}")
    print("=" * 60)


async def exemplo_com_diagramas_corrigido():
    """Demonstra o uso com diagramas SVG integrados - VERSÃO CORRIGIDA"""
    
    print("🎨 Gerando material com diagramas SVG...")
    print("=" * 60)
    
    # Criar material com diagrama do IPTU
    material_tributario = MaterialDataComDiagrama(
        titulo_material="Sistema Tributário Nacional",
        subtitulo="IPTU - Princípios e Exceções",
        nome_curso="Direito Tributário - RFB",
        numero_modulo="2",
        nome_aluno="Ana Costa",
        cpf_aluno="456.789.123-00",
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
        """,
        conceitos=[
            {
                "termo": "IPTU",
                "definicao": "Imposto sobre Propriedade Predial e Territorial Urbana"
            },
            {
                "termo": "Base de Cálculo",
                "definicao": "Valor venal do imóvel"
            },
            {
                "termo": "Alíquota",
                "definicao": "Percentual aplicado sobre a base de cálculo"
            }
        ]
    )
    
    print("📊 Adicionando diagramas ao material...")
    
    # Adicionar o diagrama do IPTU
    material_tributario.adicionar_diagrama(
        tipo=TipoDiagrama.HIERARQUIA,
        dados={'tipo_especifico': 'iptu'},
        titulo="Princípios do IPTU e suas Exceções"
    )
    
    # Adicionar um fluxograma do processo de cobrança
    material_tributario.adicionar_diagrama(
        tipo=TipoDiagrama.PROCESSO,
        dados={
            'etapas': [
                {'texto': 'Início', 'tipo': 'inicio'},
                {'texto': 'Lançamento', 'tipo': 'processo'},
                {'texto': 'Notificação', 'tipo': 'processo'},
                {'texto': 'Pagou?', 'tipo': 'decisao'},
                {'texto': 'Inscrição DA', 'tipo': 'processo'},
                {'texto': 'Fim', 'tipo': 'fim'}
            ]
        },
        titulo="Processo de Cobrança do IPTU"
    )
    
    # Adicionar mapa mental
    material_tributario.adicionar_diagrama(
        tipo=TipoDiagrama.MAPA_MENTAL,
        dados={
            'titulo': 'IPTU',
            'topicos': {
                'Princípios': ['Legalidade', 'Anterioridade', 'Noventena'],
                'Exceções': ['Base de Cálculo', 'Progressividade'],
                'Competência': ['Municipal', 'DF'],
                'Fato Gerador': ['Propriedade', 'Posse', 'Domínio Útil']
            }
        },
        titulo="Mapa Mental - IPTU"
    )
    
    # USAR A VERSÃO CORRIGIDA DO GERADOR
    print("🔄 Inicializando gerador CORRIGIDO com suporte a diagramas...")
    generator = LabResumosPDFGeneratorComDiagramasCorrigido()  # <-- MUDANÇA AQUI
    
    # Criar diretório de output se não existir
    os.makedirs("outputs", exist_ok=True)
    
    print("📄 Gerando PDF com diagramas integrados e todos os campos...")
    resultado = await generator.gerar_material(
        tipo=TipoMaterial.RESUMO,
        dados=material_tributario,
        output_path=f"outputs/pdf_gerado_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
    )
    
    print("\n" + "=" * 60)
    if resultado["success"]:
        print(f"✅ PDF com diagramas gerado com sucesso!")
        print(f"   📁 Arquivo: {resultado['local_path']}")
        print(f"   🌐 URL: {resultado['pdf_url']}")
        print(f"   📊 Diagramas incluídos: 3")
        print(f"      • Diagrama dos Princípios do IPTU")
        print(f"      • Fluxograma do Processo de Cobrança")
        print(f"      • Mapa Mental do IPTU")
        print(f"   ⏰ Gerado em: {resultado['timestamp']}")
    else:
        print(f"❌ Erro: {resultado['error']}")
        if 'status_code' in resultado:
            print(f"   📊 Código HTTP: {resultado['status_code']}")
    print("=" * 60)

# ================== EXECUTAR VERSÃO CORRIGIDA ==================
if __name__ == "__main__":
    print("🎯 Lab Resumos - Sistema Estendido com Diagramas SVG")
    print("🔐 Integrado com Azure Key Vault")
    print("🎨 Suporte completo a diagramas educacionais")
    print("✅ VERSÃO CORRIGIDA - Com todos os campos do template")
    print("=" * 60)
    
    # Executa o exemplo CORRIGIDO
    asyncio.run(exemplo_com_diagramas_corrigido())
    
    print("\n" + "="*60)
    print("📚 Sistema Lab Resumos - Versão com Diagramas")
    print("="*60)
    print("Funcionalidades disponíveis:")
    print("  • Geração de PDFs tradicionais")
    print("  • Diagramas SVG integrados")
    print("  • Diagramas SVG standalone (HTML)")
    print("  • Fluxogramas de processo")
    print("  • Mapas mentais")
    print("  • Diagramas hierárquicos")
    print("  • Organogramas e timelines")
    print("  • Diagramas educacionais personalizados")
    print("\n🎨 Identidade visual mantida:")
    print("  • Cores: Amarelo (#F1CC00), Preto (#333B49)")
    print("  • Tipografia: Figtree")
    print("  • Elementos visuais do Lab Resumos")
    print("  • Diagramas SVG com alta qualidade")
    print("\n🔐 Segurança:")
    print("  • API Key gerenciada pelo Azure Key Vault")
    print("  • Configurações centralizadas e seguras")
    print("  • Logs detalhados de todas as operações")


async def exemplo_diagramas_svg_standalone():
    """Demonstra geração de diagramas SVG como arquivos HTML standalone"""
    
    print("🎨 Gerando Diagramas SVG Standalone...")
    print("=" * 60)
    
    # Inicializar gerador
    generator = LabResumosPDFGeneratorComDiagramas()
    
    # Criar diretório de output se não existir
    os.makedirs("outputs", exist_ok=True)
    
    # Lista de diagramas para gerar
    diagramas_para_gerar = [
        # Diagrama IPTU (baseado no exemplo fornecido)
        (
            TipoDiagramaSVG.IPTU_PRINCIPIOS,
            {},
            "outputs/diagrama_iptu_principios.html"
        ),
        
        # Fluxograma simples
        (
            TipoDiagramaSVG.FLUXOGRAMA_SIMPLES,
            {
                "titulo": "Processo de Cobrança do IPTU",
                "etapas": [
                    {"texto": "Lançamento do IPTU", "cor": "#e8f4f8"},
                    {"texto": "Notificação ao Contribuinte", "cor": "#fff2e8"},
                    {"texto": "Prazo para Pagamento", "cor": "#f0f8e8"},
                    {"texto": "Cobrança Administrativa", "cor": "#f8e8e8"},
                    {"texto": "Execução Fiscal", "cor": "#f5e8f8"}
                ]
            },
            "outputs/fluxograma_cobranca_iptu.html"
        ),
        
        # Mapa conceitual
        (
            TipoDiagramaSVG.MAPA_CONCEITUAL,
            {
                "titulo": "Direito Tributário",
                "conceito_central": "IPTU",
                "conceitos": [
                    {"texto": "Legalidade", "cor": "#e8f4f8"},
                    {"texto": "Anterioridade", "cor": "#fff2e8"},
                    {"texto": "Noventena", "cor": "#f8e8e8"},
                    {"texto": "Base de Cálculo", "cor": "#f0f8e8"},
                    {"texto": "Alíquota", "cor": "#f5e8f8"},
                    {"texto": "Competência Municipal", "cor": "#e8f8f0"}
                ]
            },
            "outputs/mapa_conceitual_iptu.html"
        ),
        
        # Organograma
        (
            TipoDiagramaSVG.ORGANOGRAMA,
            {
                "titulo": "Estrutura Tributária Municipal",
                "hierarquia": {
                    "titulo": "Prefeitura",
                    "subordinados": [
                        {
                            "titulo": "Secretaria de Finanças",
                            "subordinados": [
                                {"titulo": "Dept. Tributário"},
                                {"titulo": "Dept. Arrecadação"}
                            ]
                        },
                        {
                            "titulo": "Procuradoria",
                            "subordinados": [
                                {"titulo": "Execução Fiscal"}
                            ]
                        }
                    ]
                }
            },
            "outputs/organograma_tributario.html"
        ),
        
        # Timeline
        (
            TipoDiagramaSVG.TIMELINE,
            {
                "titulo": "Cronologia do IPTU",
                "eventos": [
                    {"data": "Jan", "titulo": "Lançamento", "cor": "#e8f4f8"},
                    {"data": "Fev", "titulo": "Notificação", "cor": "#fff2e8"},
                    {"data": "Mar", "titulo": "Vencimento", "cor": "#f0f8e8"},
                    {"data": "Abr", "titulo": "Cobrança", "cor": "#f8e8e8"},
                    {"data": "Mai", "titulo": "Execução", "cor": "#f5e8f8"}
                ]
            },
            "outputs/timeline_iptu.html"
        )
    ]
    
    # Gerar todos os diagramas em lote
    print(f"🔄 Gerando {len(diagramas_para_gerar)} diagramas SVG...")
    resultados = await generator.gerar_lote_diagramas_svg(diagramas_para_gerar)
    
    # Relatório de resultados
    sucessos = sum(resultados)
    print(f"\n📊 Relatório de Geração:")
    print(f"✅ Sucessos: {sucessos}/{len(diagramas_para_gerar)}")
    print(f"❌ Falhas: {len(diagramas_para_gerar) - sucessos}/{len(diagramas_para_gerar)}")
    
    if sucessos > 0:
        print(f"\n📁 Arquivos gerados em:")
        for i, (sucesso, (_, _, caminho)) in enumerate(zip(resultados, diagramas_para_gerar)):
            status = "✅" if sucesso else "❌"
            print(f"  {status} {caminho}")
    
    print(f"\n🎯 Exemplo de uso dos diagramas SVG concluído!")
    return sucessos == len(diagramas_para_gerar)
