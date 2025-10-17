#!/usr/bin/env python3
"""
Exemplo de uso dos diagramas SVG do Lab Resumos
Demonstra como gerar diagramas educacionais em formato HTML/SVG
"""

import os
import sys
import asyncio
from datetime import datetime

# Adiciona o diretório raiz ao path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from scripts.lab_resumos_generator_com_diagramas import (
    LabResumosPDFGeneratorComDiagramas,
    exemplo_diagramas_svg_standalone
)
from scripts.svg_diagram_generator import (
    GeradorDiagramaSVG, 
    TipoDiagramaSVG,
    criar_diagrama_iptu,
    salvar_diagrama_iptu_html
)


async def exemplo_basico_svg():
    """Exemplo básico de geração de diagrama SVG"""
    print("🎨 Exemplo Básico - Diagrama IPTU")
    print("=" * 50)
    
    # Método 1: Usando função de conveniência
    print("📊 Gerando diagrama IPTU usando função de conveniência...")
    sucesso = salvar_diagrama_iptu_html("outputs/iptu_basico.html")
    
    if sucesso:
        print("✅ Diagrama salvo em: outputs/iptu_basico.html")
    else:
        print("❌ Erro ao gerar diagrama")
    
    # Método 2: Usando gerador diretamente
    print("\n📊 Gerando diagrama usando gerador direto...")
    gerador = GeradorDiagramaSVG()
    
    # Criar diagrama
    diagrama = gerador.gerar_diagrama(TipoDiagramaSVG.IPTU_PRINCIPIOS, {})
    
    # Salvar como HTML
    sucesso2 = gerador.salvar_html(diagrama, "outputs/iptu_detalhado.html")
    
    if sucesso2:
        print("✅ Diagrama detalhado salvo em: outputs/iptu_detalhado.html")
    else:
        print("❌ Erro ao gerar diagrama detalhado")
    
    return sucesso and sucesso2


async def exemplo_personalizado():
    """Exemplo de diagrama personalizado"""
    print("\n🎨 Exemplo Personalizado - Fluxograma Tributário")
    print("=" * 50)
    
    gerador = GeradorDiagramaSVG()
    
    # Dados para fluxograma personalizado
    dados_fluxograma = {
        "titulo": "Fluxo de Arrecadação Municipal",
        "etapas": [
            {"texto": "Cadastro Imobiliário", "cor": "#e8f4f8"},
            {"texto": "Cálculo do IPTU", "cor": "#fff2e8"},
            {"texto": "Emissão da Guia", "cor": "#f0f8e8"},
            {"texto": "Notificação", "cor": "#f8e8e8"},
            {"texto": "Pagamento ou Protesto", "cor": "#f5e8f8"},
            {"texto": "Baixa ou Execução", "cor": "#e8f8f0"}
        ]
    }
    
    # Gerar diagrama
    diagrama = gerador.gerar_diagrama(TipoDiagramaSVG.FLUXOGRAMA_SIMPLES, dados_fluxograma)
    
    # Salvar
    sucesso = gerador.salvar_html(diagrama, "outputs/fluxograma_personalizado.html")
    
    if sucesso:
        print("✅ Fluxograma personalizado salvo em: outputs/fluxograma_personalizado.html")
    else:
        print("❌ Erro ao gerar fluxograma personalizado")
    
    return sucesso


async def exemplo_mapa_conceitual():
    """Exemplo de mapa conceitual"""
    print("\n🎨 Exemplo Mapa Conceitual - Tributação Municipal")
    print("=" * 50)
    
    gerador = GeradorDiagramaSVG()
    
    # Dados para mapa conceitual
    dados_mapa = {
        "titulo": "Tributação Municipal",
        "conceito_central": "Município",
        "conceitos": [
            {"texto": "IPTU", "cor": "#e8f4f8"},
            {"texto": "ISS", "cor": "#fff2e8"},
            {"texto": "ITBI", "cor": "#f0f8e8"},
            {"texto": "Taxas", "cor": "#f8e8e8"},
            {"texto": "Contribuições", "cor": "#f5e8f8"},
            {"texto": "Multas", "cor": "#e8f8f0"}
        ]
    }
    
    # Gerar diagrama
    diagrama = gerador.gerar_diagrama(TipoDiagramaSVG.MAPA_CONCEITUAL, dados_mapa)
    
    # Salvar
    sucesso = gerador.salvar_html(diagrama, "outputs/mapa_tributacao_municipal.html")
    
    if sucesso:
        print("✅ Mapa conceitual salvo em: outputs/mapa_tributacao_municipal.html")
    else:
        print("❌ Erro ao gerar mapa conceitual")
    
    return sucesso


async def exemplo_integrado():
    """Exemplo usando o gerador integrado"""
    print("\n🎨 Exemplo Integrado - Usando LabResumosPDFGeneratorComDiagramas")
    print("=" * 50)
    
    # Inicializar gerador integrado
    generator = LabResumosPDFGeneratorComDiagramas()
    
    # Lista de diagramas para gerar
    diagramas = [
        (
            TipoDiagramaSVG.IPTU_PRINCIPIOS,
            {},
            "outputs/iptu_integrado.html"
        ),
        (
            TipoDiagramaSVG.TIMELINE,
            {
                "titulo": "Cronograma Fiscal Municipal",
                "eventos": [
                    {"data": "Janeiro", "titulo": "Lançamento IPTU", "cor": "#e8f4f8"},
                    {"data": "Fevereiro", "titulo": "Carnês enviados", "cor": "#fff2e8"},
                    {"data": "Março", "titulo": "1ª Parcela", "cor": "#f0f8e8"},
                    {"data": "Junho", "titulo": "Última Parcela", "cor": "#f8e8e8"},
                    {"data": "Julho", "titulo": "Início Cobrança", "cor": "#f5e8f8"}
                ]
            },
            "outputs/cronograma_fiscal.html"
        )
    ]
    
    # Gerar em lote
    resultados = await generator.gerar_lote_diagramas_svg(diagramas)
    
    sucessos = sum(resultados)
    print(f"✅ {sucessos}/{len(diagramas)} diagramas gerados com sucesso")
    
    return sucessos == len(diagramas)


async def exemplo_svg_puro():
    """Exemplo de obtenção de SVG puro (sem HTML)"""
    print("\n🎨 Exemplo SVG Puro - Para embedding")
    print("=" * 50)
    
    generator = LabResumosPDFGeneratorComDiagramas()
    
    # Obter SVG puro do diagrama IPTU
    svg_content = generator.obter_svg_puro(TipoDiagramaSVG.IPTU_PRINCIPIOS, {})
    
    if svg_content:
        # Salvar apenas o SVG
        with open("outputs/iptu_puro.svg", "w", encoding="utf-8") as f:
            f.write(svg_content)
        
        print("✅ SVG puro salvo em: outputs/iptu_puro.svg")
        print(f"📏 Tamanho do SVG: {len(svg_content)} caracteres")
        return True
    else:
        print("❌ Erro ao gerar SVG puro")
        return False


def listar_arquivos_gerados():
    """Lista todos os arquivos SVG/HTML gerados"""
    print("\n📁 Arquivos Gerados:")
    print("=" * 50)
    
    output_dir = "outputs"
    if not os.path.exists(output_dir):
        print("❌ Diretório outputs não encontrado")
        return
    
    arquivos_svg = []
    for arquivo in os.listdir(output_dir):
        if arquivo.endswith(('.html', '.svg')):
            caminho_completo = os.path.join(output_dir, arquivo)
            tamanho = os.path.getsize(caminho_completo)
            arquivos_svg.append((arquivo, tamanho))
    
    if arquivos_svg:
        print(f"📊 {len(arquivos_svg)} arquivos encontrados:")
        for arquivo, tamanho in sorted(arquivos_svg):
            tamanho_kb = tamanho / 1024
            print(f"  📄 {arquivo} ({tamanho_kb:.1f} KB)")
    else:
        print("❌ Nenhum arquivo SVG/HTML encontrado")


async def main():
    """Função principal - executa todos os exemplos"""
    print("🎨 Lab Resumos - Exemplos de Diagramas SVG")
    print("=" * 60)
    print(f"📅 Executado em: {datetime.now().strftime('%d/%m/%Y às %H:%M:%S')}")
    print("=" * 60)
    
    # Criar diretório de outputs
    os.makedirs("outputs", exist_ok=True)
    
    resultados = []
    
    try:
        # Executar exemplos
        print("🚀 Iniciando exemplos...")
        
        resultados.append(await exemplo_basico_svg())
        resultados.append(await exemplo_personalizado())
        resultados.append(await exemplo_mapa_conceitual())
        resultados.append(await exemplo_integrado())
        resultados.append(await exemplo_svg_puro())
        
        # Exemplo completo usando a função já existente
        print("\n🎨 Executando exemplo completo...")
        resultado_completo = await exemplo_diagramas_svg_standalone()
        resultados.append(resultado_completo)
        
    except Exception as e:
        print(f"❌ Erro durante execução: {str(e)}")
        resultados.append(False)
    
    # Listar arquivos gerados
    listar_arquivos_gerados()
    
    # Relatório final
    sucessos = sum(resultados)
    total = len(resultados)
    
    print(f"\n🎯 Relatório Final:")
    print("=" * 50)
    print(f"✅ Sucessos: {sucessos}/{total}")
    print(f"❌ Falhas: {total - sucessos}/{total}")
    print(f"📊 Taxa de sucesso: {(sucessos/total)*100:.1f}%")
    
    if sucessos > 0:
        print(f"\n🎉 Diagramas SVG gerados com sucesso!")
        print(f"📁 Verifique o diretório 'outputs' para ver os resultados")
    else:
        print(f"\n😞 Nenhum diagrama foi gerado. Verifique os logs de erro.")
    
    print("\n" + "=" * 60)
    print("🎨 Lab Resumos SVG Generator - Exemplo concluído!")
    print("=" * 60)


if __name__ == "__main__":
    # Executar exemplos
    asyncio.run(main())
