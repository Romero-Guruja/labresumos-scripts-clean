# -*- coding: utf-8 -*-
"""
Exemplo de uso do Sistema de Gráficos Premium Lab Resumos
Demonstra todos os tipos de gráficos disponíveis
"""

import sys
import os
from pathlib import Path

# Adiciona o diretório projeto ao path
project_root = Path(__file__).parent.parent
sys.path.append(str(project_root / "projeto"))

from component_factory import ComponentFactory


def criar_exemplo_barras():
    """Cria um exemplo de gráfico de barras múltiplas séries"""
    factory = ComponentFactory()
    
    # Dados de desempenho por módulo
    bar_data = {
        'titulo': 'Desempenho dos Alunos por Módulo',
        'categorias': ['Direito Constitucional', 'Direito Administrativo', 'Direito Tributário', 'Direito Penal'],
        'series': [
            {'nome': 'Teoria (Aulas)', 'dados': [85, 78, 92, 75]},
            {'nome': 'Exercícios Práticos', 'dados': [72, 85, 88, 80]},
            {'nome': 'Simulados', 'dados': [68, 75, 85, 77]}
        ]
    }
    
    html_output = factory.create_component('grafico:barras', bar_data)
    return html_output


def criar_exemplo_linha():
    """Cria um exemplo de gráfico de linha temporal"""
    factory = ComponentFactory()
    
    # Evolução das notas ao longo do semestre
    line_data = {
        'titulo': 'Evolução da Média Geral do Curso',
        'labels': ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho'],
        'dados': [65, 68, 72, 75, 78, 82],
        'cor': '#2A6B9F'  # Azul celeste Lab Resumos
    }
    
    html_output = factory.create_component('grafico:linha', line_data)
    return html_output


def criar_exemplo_pizza():
    """Cria um exemplo de gráfico de pizza"""
    factory = ComponentFactory()
    
    # Distribuição do tempo de estudo
    pie_data = {
        'titulo': 'Distribuição do Tempo de Estudo Semanal',
        'labels': ['Teoria', 'Exercícios', 'Revisão', 'Simulados'],
        'dados': [40, 30, 20, 10]
    }
    
    html_output = factory.create_component('grafico:pizza', pie_data)
    return html_output


def criar_exemplo_fluxo():
    """Cria um exemplo de diagrama de fluxo"""
    factory = ComponentFactory()
    
    # Processo de estudo
    flow_data = {
        'passos': [
            {'tipo': 'terminal', 'acao': 'Início do Estudo'},
            {'tipo': 'processo', 'acao': 'Leitura do Material Teórico'},
            {'tipo': 'processo', 'acao': 'Resolução de Exercícios'},
            {'tipo': 'decisao', 'acao': 'Entendeu o Conteúdo?'},
            {'tipo': 'processo', 'acao': 'Revisão e Fixação'},
            {'tipo': 'terminal', 'acao': 'Pronto para Avaliação'}
        ]
    }
    
    html_output = factory.create_component('diagrama:fluxo', flow_data)
    return html_output


def gerar_documento_exemplo():
    """Gera um documento HTML completo com todos os exemplos"""
    
    # Cria os gráficos
    grafico_barras = criar_exemplo_barras()
    grafico_linha = criar_exemplo_linha()
    grafico_pizza = criar_exemplo_pizza()
    diagrama_fluxo = criar_exemplo_fluxo()
    
    # Template HTML completo
    html_template = f"""
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Lab Resumos - Gráficos Premium</title>
        <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body {{
                font-family: 'Figtree', sans-serif;
                line-height: 1.6;
                margin: 0;
                padding: 20px;
                background: linear-gradient(135deg, #F3F1E8, #ffffff);
                color: #333B49;
            }}
            
            .container {{
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                border-radius: 15px;
                padding: 40px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }}
            
            .header {{
                text-align: center;
                margin-bottom: 40px;
                padding-bottom: 20px;
                border-bottom: 3px solid #F1CC00;
            }}
            
            .header h1 {{
                color: #2A6B9F;
                font-size: 2.5em;
                margin: 0;
                font-weight: 700;
            }}
            
            .header p {{
                color: #6C757D;
                font-size: 1.2em;
                margin: 10px 0 0 0;
            }}
            
            .section {{
                margin: 40px 0;
            }}
            
            .section h2 {{
                color: #333B49;
                font-size: 1.8em;
                margin-bottom: 20px;
                padding-left: 15px;
                border-left: 4px solid #F1CC00;
            }}
            
            .grid {{
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
                margin: 30px 0;
            }}
            
            @media (max-width: 768px) {{
                .grid {{
                    grid-template-columns: 1fr;
                }}
            }}
            
            .footer {{
                text-align: center;
                margin-top: 50px;
                padding-top: 20px;
                border-top: 2px solid #F3F1E8;
                color: #6C757D;
            }}
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Lab Resumos</h1>
                <p>Sistema de Gráficos Premium v1.7</p>
            </div>
            
            <div class="section">
                <h2>Gráfico de Barras Múltiplas Séries</h2>
                <p>Demonstra o desempenho comparativo entre diferentes tipos de atividades acadêmicas.</p>
                {grafico_barras}
            </div>
            
            <div class="grid">
                <div class="section">
                    <h2>Gráfico de Linha Temporal</h2>
                    <p>Mostra a evolução das médias ao longo do tempo.</p>
                    {grafico_linha}
                </div>
                
                <div class="section">
                    <h2>Gráfico de Pizza</h2>
                    <p>Visualiza a distribuição proporcional do tempo de estudo.</p>
                    {grafico_pizza}
                </div>
            </div>
            
            <div class="section">
                <h2>Diagrama de Fluxo</h2>
                <p>Representa o processo metodológico de estudo recomendado.</p>
                {diagrama_fluxo}
            </div>
            
            <div class="footer">
                <p><strong>Lab Resumos</strong> - Material Didático de Alta Qualidade</p>
                <p>Gráficos gerados automaticamente com identidade visual padronizada</p>
            </div>
        </div>
    </body>
    </html>
    """
    
    return html_template


def main():
    """Função principal - gera o documento de exemplo"""
    print("🎨 Gerando exemplos dos Gráficos Premium Lab Resumos...")
    
    try:
        # Gera o documento HTML
        documento_html = gerar_documento_exemplo()
        
        # Salva o arquivo
        output_path = project_root / "outputs" / "exemplo_graficos_premium.html"
        output_path.parent.mkdir(exist_ok=True)
        
        with open(output_path, 'w', encoding='utf-8') as f:
            f.write(documento_html)
        
        print(f"✅ Documento gerado com sucesso: {output_path}")
        print(f"📂 Abra o arquivo no navegador para visualizar os gráficos premium!")
        
        # Também gera exemplos individuais para debug
        factory = ComponentFactory()
        
        print("\n🔍 Gerando exemplos individuais...")
        
        exemplos = {
            'barras': criar_exemplo_barras(),
            'linha': criar_exemplo_linha(),
            'pizza': criar_exemplo_pizza(),
            'fluxo': criar_exemplo_fluxo()
        }
        
        for nome, html in exemplos.items():
            debug_path = project_root / "outputs" / f"debug_{nome}.html"
            with open(debug_path, 'w', encoding='utf-8') as f:
                f.write(f"""
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Debug {nome}</title>
                    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
                    <style>body {{ font-family: 'Figtree', sans-serif; padding: 20px; }}</style>
                </head>
                <body>
                    {html}
                </body>
                </html>
                """)
            print(f"  📝 {nome}: {debug_path}")
        
        print(f"\n🎉 Processo concluído! {len(exemplos) + 1} arquivos gerados.")
        
    except Exception as e:
        print(f"❌ Erro ao gerar exemplos: {e}")
        return False
    
    return True


if __name__ == "__main__":
    main()
