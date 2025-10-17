"""
Gerador de Diagramas SVG para Lab Resumos
Cria diagramas educacionais em formato SVG/HTML
Baseado no exemplo de diagrama IPTU fornecido
"""

from typing import Dict, List, Any, Optional, Tuple
from dataclasses import dataclass, field
from enum import Enum
import os
import base64
from datetime import datetime


class TipoDiagramaSVG(Enum):
    """Tipos de diagramas SVG disponíveis"""
    IPTU_PRINCIPIOS = "iptu_principios"
    FLUXOGRAMA_SIMPLES = "fluxograma_simples"
    MAPA_CONCEITUAL = "mapa_conceitual"
    ORGANOGRAMA = "organograma"
    TIMELINE = "timeline"


@dataclass
class ElementoSVG:
    """Elemento básico de um diagrama SVG"""
    id: str
    texto: str
    x: float
    y: float
    largura: float = 200
    altura: float = 60
    cor_fundo: str = "white"
    cor_borda: str = "#333"
    cor_texto: str = "#333"
    estilo_especial: str = ""
    tipo: str = "box"  # box, circle, diamond


@dataclass
class ConexaoSVG:
    """Conexão entre elementos do diagrama"""
    origem: str
    destino: str
    tipo: str = "linha"  # linha, seta, pontilhada
    cor: str = "#333"
    largura: float = 2.0
    pontos: List[Tuple[float, float]] = field(default_factory=list)


@dataclass
class DiagramaSVG:
    """Estrutura completa de um diagrama SVG"""
    titulo: str
    largura: float = 900
    altura: float = 400
    elementos: List[ElementoSVG] = field(default_factory=list)
    conexoes: List[ConexaoSVG] = field(default_factory=list)
    cor_fundo: str = "#f0f0f0"
    descricao: str = ""


class GeradorDiagramaSVG:
    """
    Gerador de diagramas SVG educacionais
    Cria diagramas no formato HTML com SVG embarcado
    """
    
    def __init__(self):
        self.templates = {
            TipoDiagramaSVG.IPTU_PRINCIPIOS: self._template_iptu_principios,
            TipoDiagramaSVG.FLUXOGRAMA_SIMPLES: self._template_fluxograma_simples,
            TipoDiagramaSVG.MAPA_CONCEITUAL: self._template_mapa_conceitual,
            TipoDiagramaSVG.ORGANOGRAMA: self._template_organograma,
            TipoDiagramaSVG.TIMELINE: self._template_timeline,
        }
    
    def gerar_diagrama(self, tipo: TipoDiagramaSVG, dados: Dict[str, Any]) -> DiagramaSVG:
        """
        Gera um diagrama baseado no tipo e dados fornecidos
        
        Args:
            tipo: Tipo de diagrama a ser gerado
            dados: Dados específicos para o diagrama
            
        Returns:
            DiagramaSVG: Estrutura do diagrama gerado
        """
        if tipo not in self.templates:
            raise ValueError(f"Tipo de diagrama não suportado: {tipo}")
        
        return self.templates[tipo](dados)
    
    def _template_iptu_principios(self, dados: Dict[str, Any]) -> DiagramaSVG:
        """Template para diagrama dos princípios do IPTU (baseado no exemplo fornecido)"""
        diagrama = DiagramaSVG(
            titulo="Princípios do IPTU",
            largura=900,
            altura=400
        )
        
        # Texto principal
        texto_principal = ElementoSVG(
            id="main_text",
            texto="O IPTU obedece\naos princípios da:",
            x=50,
            y=150,
            largura=280,
            altura=70,
            tipo="texto"
        )
        diagrama.elementos.append(texto_principal)
        
        # Princípios
        legalidade = ElementoSVG(
            id="legalidade",
            texto="Legalidade",
            x=480,
            y=50,
            largura=200,
            altura=60
        )
        diagrama.elementos.append(legalidade)
        
        anterioridade = ElementoSVG(
            id="anterioridade", 
            texto="Anterioridade",
            x=480,
            y=150,
            largura=200,
            altura=60
        )
        diagrama.elementos.append(anterioridade)
        
        noventena = ElementoSVG(
            id="noventena",
            texto="Noventena",
            x=480,
            y=250,
            largura=200,
            altura=60,
            cor_fundo="#fff5f5",
            cor_texto="#e74c3c",
            estilo_especial="italic"
        )
        diagrama.elementos.append(noventena)
        
        # Exceção
        excecao = ElementoSVG(
            id="excecao",
            texto="Exceto a fixação da\nBase de Cálculo", 
            x=750,
            y=140,
            largura=280,
            altura=80
        )
        diagrama.elementos.append(excecao)
        
        # Observação das bancas
        observacao = ElementoSVG(
            id="observacao",
            texto="As bancas trocam por\n\"alíquota\"",
            x=760,
            y=260,
            largura=260,
            altura=80,
            estilo_especial="dashed"
        )
        diagrama.elementos.append(observacao)
        
        # Conexões
        # Linha principal horizontal
        conexao_main = ConexaoSVG(
            origem="main_text",
            destino="centro",
            pontos=[(330, 180), (450, 180)]
        )
        diagrama.conexoes.append(conexao_main)
        
        # Conexões para os princípios
        conexao_leg = ConexaoSVG(
            origem="centro",
            destino="legalidade",
            pontos=[(450, 180), (450, 80), (480, 80)]
        )
        diagrama.conexoes.append(conexao_leg)
        
        conexao_ant = ConexaoSVG(
            origem="centro", 
            destino="anterioridade",
            pontos=[(450, 180), (480, 180)]
        )
        diagrama.conexoes.append(conexao_ant)
        
        conexao_nov = ConexaoSVG(
            origem="centro",
            destino="noventena",
            pontos=[(450, 180), (450, 280), (480, 280)]
        )
        diagrama.conexoes.append(conexao_nov)
        
        # Conexão para exceção
        conexao_exc = ConexaoSVG(
            origem="noventena",
            destino="excecao",
            pontos=[(680, 280), (720, 280), (720, 180), (750, 180)]
        )
        diagrama.conexoes.append(conexao_exc)
        
        # Conexão pontilhada para observação
        conexao_obs = ConexaoSVG(
            origem="excecao",
            destino="observacao",
            tipo="pontilhada",
            pontos=[(890, 220), (890, 260)]
        )
        diagrama.conexoes.append(conexao_obs)
        
        return diagrama
    
    def _template_fluxograma_simples(self, dados: Dict[str, Any]) -> DiagramaSVG:
        """Template para fluxograma simples"""
        diagrama = DiagramaSVG(
            titulo=dados.get("titulo", "Fluxograma"),
            largura=800,
            altura=600
        )
        
        etapas = dados.get("etapas", [])
        x_inicial = 100
        y_inicial = 100
        espacamento_y = 120
        
        for i, etapa in enumerate(etapas):
            elemento = ElementoSVG(
                id=f"etapa_{i}",
                texto=etapa.get("texto", f"Etapa {i+1}"),
                x=x_inicial,
                y=y_inicial + (i * espacamento_y),
                largura=250,
                altura=80,
                cor_fundo=etapa.get("cor", "white")
            )
            diagrama.elementos.append(elemento)
            
            # Conectar com a próxima etapa
            if i < len(etapas) - 1:
                conexao = ConexaoSVG(
                    origem=f"etapa_{i}",
                    destino=f"etapa_{i+1}",
                    tipo="seta"
                )
                diagrama.conexoes.append(conexao)
        
        return diagrama
    
    def _template_mapa_conceitual(self, dados: Dict[str, Any]) -> DiagramaSVG:
        """Template para mapa conceitual"""
        diagrama = DiagramaSVG(
            titulo=dados.get("titulo", "Mapa Conceitual"),
            largura=1000,
            altura=600
        )
        
        conceito_central = dados.get("conceito_central", "Conceito Principal")
        conceitos = dados.get("conceitos", [])
        
        # Conceito central
        central = ElementoSVG(
            id="central",
            texto=conceito_central,
            x=400,
            y=250,
            largura=200,
            altura=100,
            cor_fundo="#e8f4f8",
            tipo="circle"
        )
        diagrama.elementos.append(central)
        
        # Conceitos ao redor
        import math
        raio = 200
        angulo_inicial = 0
        incremento_angulo = 2 * math.pi / len(conceitos) if conceitos else 0
        
        for i, conceito in enumerate(conceitos):
            angulo = angulo_inicial + (i * incremento_angulo)
            x = 500 + raio * math.cos(angulo)
            y = 300 + raio * math.sin(angulo)
            
            elemento = ElementoSVG(
                id=f"conceito_{i}",
                texto=conceito.get("texto", f"Conceito {i+1}"),
                x=x - 75,
                y=y - 30,
                largura=150,
                altura=60,
                cor_fundo=conceito.get("cor", "#f0f8ff")
            )
            diagrama.elementos.append(elemento)
            
            # Conectar ao central
            conexao = ConexaoSVG(
                origem="central",
                destino=f"conceito_{i}"
            )
            diagrama.conexoes.append(conexao)
        
        return diagrama
    
    def _template_organograma(self, dados: Dict[str, Any]) -> DiagramaSVG:
        """Template para organograma"""
        diagrama = DiagramaSVG(
            titulo=dados.get("titulo", "Organograma"),
            largura=1000,
            altura=600
        )
        
        # Implementação básica de organograma
        hierarquia = dados.get("hierarquia", {})
        self._adicionar_nivel_organograma(diagrama, hierarquia, 500, 50, 0)
        
        return diagrama
    
    def _template_timeline(self, dados: Dict[str, Any]) -> DiagramaSVG:
        """Template para timeline"""
        diagrama = DiagramaSVG(
            titulo=dados.get("titulo", "Timeline"),
            largura=1200,
            altura=400
        )
        
        eventos = dados.get("eventos", [])
        x_inicial = 100
        y_linha = 200
        espacamento_x = 200
        
        # Linha principal da timeline
        if eventos:
            linha_principal = ConexaoSVG(
                origem="inicio",
                destino="fim",
                pontos=[(x_inicial, y_linha), (x_inicial + len(eventos) * espacamento_x, y_linha)],
                largura=3
            )
            diagrama.conexoes.append(linha_principal)
        
        # Eventos
        for i, evento in enumerate(eventos):
            x_evento = x_inicial + (i * espacamento_x)
            
            # Marco na linha
            marco = ElementoSVG(
                id=f"marco_{i}",
                texto="●",
                x=x_evento - 10,
                y=y_linha - 10,
                largura=20,
                altura=20,
                tipo="circle",
                cor_fundo="#e74c3c"
            )
            diagrama.elementos.append(marco)
            
            # Caixa do evento
            caixa_evento = ElementoSVG(
                id=f"evento_{i}",
                texto=f"{evento.get('data', '')}\n{evento.get('titulo', '')}",
                x=x_evento - 100,
                y=y_linha + 50 if i % 2 == 0 else y_linha - 120,
                largura=200,
                altura=60,
                cor_fundo=evento.get("cor", "#f8f9fa")
            )
            diagrama.elementos.append(caixa_evento)
            
            # Conectar marco ao evento
            conexao = ConexaoSVG(
                origem=f"marco_{i}",
                destino=f"evento_{i}",
                tipo="linha"
            )
            diagrama.conexoes.append(conexao)
        
        return diagrama
    
    def _adicionar_nivel_organograma(self, diagrama: DiagramaSVG, nivel: Dict, x: float, y: float, nivel_num: int):
        """Adiciona um nível ao organograma recursivamente"""
        if not nivel:
            return
        
        titulo = nivel.get("titulo", f"Nível {nivel_num}")
        elemento = ElementoSVG(
            id=f"nivel_{nivel_num}_{x}_{y}",
            texto=titulo,
            x=x - 100,
            y=y,
            largura=200,
            altura=60,
            cor_fundo="#e8f4f8" if nivel_num == 0 else "#f0f8ff"
        )
        diagrama.elementos.append(elemento)
        
        subordinados = nivel.get("subordinados", [])
        if subordinados:
            largura_total = len(subordinados) * 250
            x_inicial = x - (largura_total / 2) + 125
            
            for i, subordinado in enumerate(subordinados):
                x_sub = x_inicial + (i * 250)
                y_sub = y + 120
                
                self._adicionar_nivel_organograma(diagrama, subordinado, x_sub, y_sub, nivel_num + 1)
                
                # Conectar ao subordinado
                conexao = ConexaoSVG(
                    origem=elemento.id,
                    destino=f"nivel_{nivel_num + 1}_{x_sub}_{y_sub}"
                )
                diagrama.conexoes.append(conexao)
    
    def gerar_html(self, diagrama: DiagramaSVG, incluir_descricao: bool = True) -> str:
        """
        Gera o HTML completo com o diagrama SVG
        
        Args:
            diagrama: Estrutura do diagrama
            incluir_descricao: Se deve incluir seção de descrição
            
        Returns:
            str: HTML completo do diagrama
        """
        svg_content = self._gerar_svg(diagrama)
        
        html_template = f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{diagrama.titulo}</title>
    <style>
        body {{
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: {diagrama.cor_fundo};
        }}
        .container {{
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 95vw;
            max-height: 95vh;
            overflow: auto;
        }}
        svg {{
            display: block;
            margin: 0 auto;
        }}
        h1 {{
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }}
        .description {{
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }}
        .description h3 {{
            margin-top: 0;
            color: #2c3e50;
        }}
        .description ul {{
            margin: 10px 0;
        }}
        .description li {{
            margin: 5px 0;
        }}
        .timestamp {{
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 20px;
        }}
    </style>
</head>
<body>
    <div class="container">
        <h1>{diagrama.titulo}</h1>
        {svg_content}
        
        {self._gerar_descricao_html(diagrama) if incluir_descricao else ''}
        
        <div class="timestamp">
            Diagrama gerado em {datetime.now().strftime('%d/%m/%Y às %H:%M')} | Lab Resumos SVG Generator
        </div>
    </div>
</body>
</html>"""
        
        return html_template
    
    def _gerar_svg(self, diagrama: DiagramaSVG) -> str:
        """Gera o conteúdo SVG do diagrama"""
        svg_elements = []
        
        # Cabeçalho SVG
        svg_elements.append(f'<svg width="{diagrama.largura}" height="{diagrama.altura}" xmlns="http://www.w3.org/2000/svg">')
        
        # Definições de estilos
        svg_elements.append(self._gerar_estilos_svg())
        
        # Elementos do diagrama
        for elemento in diagrama.elementos:
            svg_elements.append(self._gerar_elemento_svg(elemento))
        
        # Conexões
        for conexao in diagrama.conexoes:
            svg_elements.append(self._gerar_conexao_svg(conexao))
        
        # Pontos de conexão (círculos pequenos)
        pontos_conexao = self._extrair_pontos_conexao(diagrama)
        for ponto in pontos_conexao:
            svg_elements.append(f'<circle cx="{ponto[0]}" cy="{ponto[1]}" r="4" fill="#333"/>')
        
        svg_elements.append('</svg>')
        
        return '\n'.join(svg_elements)
    
    def _gerar_estilos_svg(self) -> str:
        """Gera as definições de estilos CSS para o SVG"""
        return """
        <defs>
            <style>
                .main-text { 
                    font-family: Arial, sans-serif; 
                    font-size: 28px; 
                    font-weight: bold; 
                    fill: #2c3e50;
                }
                .principle-text { 
                    font-family: Arial, sans-serif; 
                    font-size: 24px; 
                    fill: #333;
                }
                .small-text {
                    font-family: Arial, sans-serif; 
                    font-size: 18px; 
                    fill: #333;
                }
                .red-text { 
                    fill: #e74c3c;
                    font-style: italic;
                }
                .box {
                    fill: white;
                    stroke: #333;
                    stroke-width: 2;
                    rx: 15;
                }
                .dashed-box {
                    fill: white;
                    stroke: #999;
                    stroke-width: 1.5;
                    stroke-dasharray: 8,4;
                    rx: 10;
                }
                .circle {
                    fill: white;
                    stroke: #333;
                    stroke-width: 2;
                }
                .connector {
                    stroke: #333;
                    stroke-width: 2;
                    fill: none;
                }
                .dashed-connector {
                    stroke: #999;
                    stroke-width: 1.5;
                    stroke-dasharray: 8,4;
                    fill: none;
                }
            </style>
        </defs>"""
    
    def _gerar_elemento_svg(self, elemento: ElementoSVG) -> str:
        """Gera o SVG para um elemento individual"""
        if elemento.tipo == "texto":
            # Texto simples (sem caixa)
            linhas = elemento.texto.split('\n')
            svg_text = f'<text x="{elemento.x}" y="{elemento.y}" class="main-text">'
            for i, linha in enumerate(linhas):
                svg_text += f'<tspan x="{elemento.x}" dy="{35 if i > 0 else 0}">{linha}</tspan>'
            svg_text += '</text>'
            return svg_text
        
        elif elemento.tipo == "circle":
            # Círculo
            centro_x = elemento.x + elemento.largura / 2
            centro_y = elemento.y + elemento.altura / 2
            raio = min(elemento.largura, elemento.altura) / 2
            
            circle_svg = f'<circle cx="{centro_x}" cy="{centro_y}" r="{raio}" '
            circle_svg += f'fill="{elemento.cor_fundo}" stroke="{elemento.cor_borda}" stroke-width="2"/>'
            
            # Texto no centro
            text_svg = f'<text x="{centro_x}" y="{centro_y + 8}" class="principle-text" text-anchor="middle" fill="{elemento.cor_texto}">'
            linhas = elemento.texto.split('\n')
            for i, linha in enumerate(linhas):
                y_offset = (i - len(linhas)/2 + 0.5) * 25
                text_svg += f'<tspan x="{centro_x}" dy="{y_offset if i == 0 else 25}">{linha}</tspan>'
            text_svg += '</text>'
            
            return circle_svg + '\n' + text_svg
        
        else:
            # Caixa retangular (padrão)
            classe_box = "dashed-box" if "dashed" in elemento.estilo_especial else "box"
            cor_fundo = elemento.cor_fundo
            
            # Caixa
            box_svg = f'<rect x="{elemento.x}" y="{elemento.y}" width="{elemento.largura}" height="{elemento.altura}" '
            box_svg += f'class="{classe_box}" style="fill: {cor_fundo};"/>'
            
            # Texto
            centro_x = elemento.x + elemento.largura / 2
            centro_y = elemento.y + elemento.altura / 2
            
            classe_texto = "principle-text"
            if "red" in elemento.cor_texto or elemento.cor_texto == "#e74c3c":
                classe_texto += " red-text"
            
            text_svg = f'<text x="{centro_x}" y="{centro_y + 8}" class="{classe_texto}" text-anchor="middle" fill="{elemento.cor_texto}">'
            
            linhas = elemento.texto.split('\n')
            for i, linha in enumerate(linhas):
                y_offset = (i - len(linhas)/2 + 0.5) * 25
                text_svg += f'<tspan x="{centro_x}" dy="{y_offset if i == 0 else 25}">{linha}</tspan>'
            
            text_svg += '</text>'
            
            return box_svg + '\n' + text_svg
    
    def _gerar_conexao_svg(self, conexao: ConexaoSVG) -> str:
        """Gera o SVG para uma conexão"""
        if not conexao.pontos:
            return ""
        
        classe = "dashed-connector" if conexao.tipo == "pontilhada" else "connector"
        
        # Gerar path com os pontos
        path_data = f"M {conexao.pontos[0][0]} {conexao.pontos[0][1]}"
        for ponto in conexao.pontos[1:]:
            path_data += f" L {ponto[0]} {ponto[1]}"
        
        path_svg = f'<path d="{path_data}" class="{classe}" stroke="{conexao.cor}" stroke-width="{conexao.largura}"/>'
        
        # Adicionar seta se necessário
        if conexao.tipo == "seta":
            ultimo_ponto = conexao.pontos[-1]
            penultimo_ponto = conexao.pontos[-2] if len(conexao.pontos) > 1 else conexao.pontos[-1]
            
            # Calcular direção da seta
            dx = ultimo_ponto[0] - penultimo_ponto[0]
            dy = ultimo_ponto[1] - penultimo_ponto[1]
            
            # Normalizar
            import math
            length = math.sqrt(dx*dx + dy*dy)
            if length > 0:
                dx /= length
                dy /= length
            
            # Pontos da seta
            arrow_size = 10
            arrow_p1 = (ultimo_ponto[0] - arrow_size * dx - arrow_size * dy * 0.5,
                       ultimo_ponto[1] - arrow_size * dy + arrow_size * dx * 0.5)
            arrow_p2 = (ultimo_ponto[0] - arrow_size * dx + arrow_size * dy * 0.5,
                       ultimo_ponto[1] - arrow_size * dy - arrow_size * dx * 0.5)
            
            arrow_svg = f'<polygon points="{ultimo_ponto[0]},{ultimo_ponto[1]} {arrow_p1[0]},{arrow_p1[1]} {arrow_p2[0]},{arrow_p2[1]}" fill="{conexao.cor}"/>'
            path_svg += '\n' + arrow_svg
        
        return path_svg
    
    def _extrair_pontos_conexao(self, diagrama: DiagramaSVG) -> List[Tuple[float, float]]:
        """Extrai pontos importantes para marcar com círculos"""
        pontos = []
        
        # Para o diagrama IPTU, adicionar pontos específicos
        if diagrama.titulo == "Princípios do IPTU":
            pontos.extend([
                (450, 180),  # Ponto central
                (720, 280)   # Ponto de bifurcação para exceção
            ])
        
        return pontos
    
    def _gerar_descricao_html(self, diagrama: DiagramaSVG) -> str:
        """Gera a seção de descrição do diagrama"""
        if not diagrama.descricao:
            # Descrição padrão baseada no tipo
            if diagrama.titulo == "Princípios do IPTU":
                descricao = """
                <div class="description">
                    <h3>Diagrama SVG Educacional</h3>
                    <p>Este diagrama foi criado com SVG puro, permitindo:</p>
                    <ul>
                        <li>Posicionamento exato de cada elemento</li>
                        <li>Cores precisas (#e74c3c para vermelho, #fff5f5 para fundo)</li>
                        <li>Tipografia controlada</li>
                        <li>Linhas pontilhadas onde necessário</li>
                        <li>Exportação em qualquer resolução sem perda</li>
                        <li>Integração perfeita com materiais didáticos</li>
                    </ul>
                    <p><strong>Conteúdo:</strong> Visualização dos princípios constitucionais que regem o IPTU, destacando a exceção da noventena para fixação da base de cálculo.</p>
                </div>"""
            else:
                descricao = f"""
                <div class="description">
                    <h3>Diagrama Gerado por IA</h3>
                    <p>Diagrama educacional criado automaticamente pelo Lab Resumos.</p>
                    <p><strong>Título:</strong> {diagrama.titulo}</p>
                    <p><strong>Elementos:</strong> {len(diagrama.elementos)} elementos visuais</p>
                    <p><strong>Conexões:</strong> {len(diagrama.conexoes)} conexões lógicas</p>
                </div>"""
        else:
            descricao = f'<div class="description">{diagrama.descricao}</div>'
        
        return descricao
    
    def salvar_html(self, diagrama: DiagramaSVG, caminho_arquivo: str, incluir_descricao: bool = True) -> bool:
        """
        Salva o diagrama como arquivo HTML
        
        Args:
            diagrama: Estrutura do diagrama
            caminho_arquivo: Caminho onde salvar o arquivo
            incluir_descricao: Se deve incluir seção de descrição
            
        Returns:
            bool: True se salvou com sucesso
        """
        try:
            html_content = self.gerar_html(diagrama, incluir_descricao)
            
            # Garantir que o diretório existe
            os.makedirs(os.path.dirname(caminho_arquivo), exist_ok=True)
            
            with open(caminho_arquivo, 'w', encoding='utf-8') as f:
                f.write(html_content)
            
            return True
        except Exception as e:
            print(f"Erro ao salvar HTML: {e}")
            return False
    
    def gerar_svg_puro(self, diagrama: DiagramaSVG) -> str:
        """
        Gera apenas o conteúdo SVG (sem HTML)
        Útil para embedding em outros documentos
        """
        return self._gerar_svg(diagrama)


# ================== FUNÇÕES DE CONVENIÊNCIA ==================

def criar_diagrama_iptu(dados_personalizados: Optional[Dict[str, Any]] = None) -> DiagramaSVG:
    """
    Função de conveniência para criar o diagrama IPTU
    
    Args:
        dados_personalizados: Dados específicos (opcional)
        
    Returns:
        DiagramaSVG: Diagrama dos princípios do IPTU
    """
    gerador = GeradorDiagramaSVG()
    dados = dados_personalizados or {}
    return gerador.gerar_diagrama(TipoDiagramaSVG.IPTU_PRINCIPIOS, dados)


def salvar_diagrama_iptu_html(caminho_arquivo: str, dados_personalizados: Optional[Dict[str, Any]] = None) -> bool:
    """
    Função de conveniência para salvar o diagrama IPTU como HTML
    
    Args:
        caminho_arquivo: Onde salvar o arquivo
        dados_personalizados: Dados específicos (opcional)
        
    Returns:
        bool: True se salvou com sucesso
    """
    diagrama = criar_diagrama_iptu(dados_personalizados)
    gerador = GeradorDiagramaSVG()
    return gerador.salvar_html(diagrama, caminho_arquivo)


# ================== EXEMPLO DE USO ==================

if __name__ == "__main__":
    # Exemplo de uso básico
    gerador = GeradorDiagramaSVG()
    
    # Criar diagrama IPTU
    diagrama_iptu = gerador.gerar_diagrama(TipoDiagramaSVG.IPTU_PRINCIPIOS, {})
    
    # Salvar como HTML
    caminho_output = "outputs/diagrama_iptu_svg.html"
    sucesso = gerador.salvar_html(diagrama_iptu, caminho_output)
    
    if sucesso:
        print(f"Diagrama IPTU salvo em: {caminho_output}")
    else:
        print("Erro ao salvar diagrama")
    
    # Exemplo de fluxograma
    dados_fluxograma = {
        "titulo": "Processo de Cobrança do IPTU",
        "etapas": [
            {"texto": "Lançamento do IPTU", "cor": "#e8f4f8"},
            {"texto": "Notificação ao Contribuinte", "cor": "#fff2e8"},
            {"texto": "Prazo para Pagamento", "cor": "#f0f8e8"},
            {"texto": "Cobrança Administrativa", "cor": "#f8e8e8"},
            {"texto": "Execução Fiscal", "cor": "#f5e8f8"}
        ]
    }
    
    diagrama_fluxo = gerador.gerar_diagrama(TipoDiagramaSVG.FLUXOGRAMA_SIMPLES, dados_fluxograma)
    gerador.salvar_html(diagrama_fluxo, "outputs/fluxograma_iptu.html")
    
    print("Exemplos de diagramas gerados!")
