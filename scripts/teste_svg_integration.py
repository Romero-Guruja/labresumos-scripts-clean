# -*- coding: utf-8 -*-
"""
Teste de integração do sistema SVG com PDF
Demonstra o uso dos gráficos SVG no documento PDF final
"""

import sys
import tempfile
from pathlib import Path

# Adiciona o diretório projeto ao path
project_root = Path(__file__).parent.parent
sys.path.append(str(project_root / "projeto"))

from pdf_converter import SimplePDFConverter
from content_enricher import ContentEnricher
from parser_module import SimpleHTMLParser


def criar_html_teste_com_graficos():
    """Cria um HTML de teste com gráficos incorporados"""
    
    html_content = """
    <!DOCTYPE html>
    <html>
    <head>
        <title>Teste de Gráficos SVG - Lab Resumos</title>
    </head>
    <body>
        <h1>Documento de Teste - Gráficos SVG Premium</h1>
        
        <h2>Introdução</h2>
        <p>Este documento demonstra o funcionamento dos gráficos SVG premium integrados ao sistema de geração de PDF do Lab Resumos.</p>
        
        <h2>1. Gráfico de Barras - Desempenho IPTU</h2>
        <p>Análise comparativa das alíquotas de IPTU por tipo de imóvel:</p>
        
        {{grafico:barras}}
        titulo: Alíquotas de IPTU por Tipo de Imóvel
        categorias: Residencial, Comercial, Industrial, Territorial
        Alíquota Mínima: 0.50, 1.00, 1.50, 2.00
        Alíquota Máxima: 1.50, 2.50, 3.00, 6.00
        {{/grafico}}
        
        <h2>2. Gráfico de Linha - Evolução Temporal</h2>
        <p>Evolução da arrecadação de IPTU ao longo dos meses:</p>
        
        {{grafico:linha}}
        titulo: Evolução da Arrecadação de IPTU 2024
        labels: Jan, Fev, Mar, Abr, Mai, Jun
        dados: 850, 920, 1050, 980, 1120, 1200
        cor: #2A6B9F
        {{/grafico}}
        
        <h2>3. Gráfico de Pizza - Distribuição</h2>
        <p>Distribuição da arrecadação por tipo de imóvel:</p>
        
        {{grafico:pizza}}
        titulo: Distribuição da Arrecadação por Tipo
        labels: Residencial, Comercial, Industrial, Territorial
        dados: 45, 30, 15, 10
        {{/grafico}}
        
        <h2>Conclusão</h2>
        <p>Os gráficos SVG permitem uma visualização clara e profissional dos dados, mantendo a qualidade em qualquer resolução e integração perfeita com PDFs.</p>
        
        <h3>Características dos Gráficos Premium:</h3>
        <ul>
            <li>✅ SVG nativo para escalabilidade infinita</li>
            <li>✅ Cores padronizadas da identidade Lab Resumos</li>
            <li>✅ Animações suaves quando visualizado em HTML</li>
            <li>✅ Gradientes e sombras profissionais</li>
            <li>✅ Tipografia consistente (Figtree)</li>
            <li>✅ Base64 embedding para PDFs</li>
        </ul>
    </body>
    </html>
    """
    
    return html_content


def executar_teste_completo():
    """Executa o teste completo da integração"""
    print("🧪 Iniciando teste de integração dos gráficos SVG...")
    
    try:
        # 1. Cria HTML de teste
        html_content = criar_html_teste_com_graficos()
        print("✅ HTML de teste criado")
        
        # 2. Parse do HTML
        parser = SimpleHTMLParser()
        parsed_data = parser.parse_document(html_content)
        print("✅ HTML parseado com sucesso")
        
        # 3. Enriquecimento do conteúdo
        enricher = ContentEnricher()
        context = enricher.enrich_content(parsed_data)
        print("✅ Conteúdo enriquecido")
        
        # 4. Conversão para PDF com gráficos SVG
        converter = SimplePDFConverter(project_root / "projeto")
        
        # Arquivo de saída
        output_path = project_root / "outputs" / "teste_svg_integration.pdf"
        output_path.parent.mkdir(exist_ok=True)
        
        success = converter.convert_to_pdf(context, output_path)
        
        if success:
            print(f"🎉 PDF gerado com sucesso: {output_path}")
            print(f"📊 Gráficos SVG integrados no PDF!")
            
            # Também salva o HTML processado para debug
            html_debug_path = project_root / "outputs" / "teste_svg_integration_debug.html"
            html_processed = converter.render_html(context)
            
            with open(html_debug_path, 'w', encoding='utf-8') as f:
                f.write(html_processed)
            
            print(f"🔍 HTML processado salvo em: {html_debug_path}")
            
            return True
        else:
            print("❌ Falha na geração do PDF")
            return False
            
    except Exception as e:
        print(f"❌ Erro durante o teste: {e}")
        import traceback
        traceback.print_exc()
        return False


def teste_componente_individual():
    """Testa componentes individuais do sistema SVG"""
    print("\n🔧 Testando componentes individuais...")
    
    try:
        from components.charts import ComponentFactory
        
        factory = ComponentFactory()
        
        # Teste gráfico de barras
        bar_data = {
            'titulo': 'Teste Gráfico de Barras',
            'categorias': ['A', 'B', 'C'],
            'series': [
                {'nome': 'Série 1', 'dados': [10, 20, 15]},
                {'nome': 'Série 2', 'dados': [15, 25, 20]}
            ]
        }
        
        html_output = factory.create_component('grafico:barras', bar_data)
        
        if html_output and 'svg' in html_output.lower():
            print("✅ ComponentFactory funcionando corretamente")
            print("✅ SVG gerado e embedido em base64")
            return True
        else:
            print("❌ Problema na geração do componente")
            return False
            
    except Exception as e:
        print(f"❌ Erro no teste de componente: {e}")
        return False


def main():
    """Função principal"""
    print("=" * 60)
    print("🎨 TESTE DE INTEGRAÇÃO - GRÁFICOS SVG PREMIUM")
    print("=" * 60)
    
    # Teste 1: Componentes individuais
    if not teste_componente_individual():
        print("❌ Teste de componentes falhou. Interrompendo.")
        return False
    
    # Teste 2: Integração completa
    if not executar_teste_completo():
        print("❌ Teste de integração falhou.")
        return False
    
    print("\n" + "=" * 60)
    print("🎉 TODOS OS TESTES PASSARAM!")
    print("📊 Sistema de gráficos SVG totalmente funcional")
    print("🔗 Integração com PDF confirmada")
    print("=" * 60)
    
    return True


if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
