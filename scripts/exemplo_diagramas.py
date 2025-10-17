"""
Exemplo prático de uso da extensão de diagramas SVG
Demonstra como criar materiais educacionais com diagramas integrados
"""

import asyncio
import os
from lab_resumos_generator_com_diagramas import (
    MaterialDataComDiagrama,
    LabResumosPDFGeneratorComDiagramas,
    TipoDiagrama,
    TipoMaterial
)

async def exemplo_direito_tributario():
    """Exemplo completo: Material de Direito Tributário com diagramas"""
    
    print("📚 EXEMPLO: Direito Tributário com Diagramas")
    print("=" * 60)
    
    # Criar material base
    material = MaterialDataComDiagrama(
        titulo_material="Direito Tributário Nacional",
        subtitulo="IPTU - Análise Completa com Diagramas",
        nome_curso="Direito Tributário - Concursos",
        numero_modulo="4",
        nome_aluno="Carlos Eduardo",
        cpf_aluno="111.222.333-44",
        introducao="""
            Este material apresenta uma análise completa do IPTU (Imposto sobre 
            Propriedade Predial e Territorial Urbana), incluindo seus princípios 
            constitucionais, exceções e processo de cobrança, complementado por 
            diagramas visuais para facilitar o aprendizado.
        """,
        ponto_importante="""
            ATENÇÃO: O IPTU possui uma exceção importante ao princípio da 
            noventena quando se trata da fixação da base de cálculo. Esta 
            exceção é frequentemente cobrada em provas de concursos.
        """,
        titulo_secao_principal="Análise dos Princípios Constitucionais",
        conteudo_principal="""
            O IPTU está sujeito aos princípios constitucionais tributários:
            
            1. LEGALIDADE (Art. 150, I, CF): O tributo deve ser instituído por lei.
            
            2. ANTERIORIDADE (Art. 150, III, 'b', CF): O tributo só pode ser 
               cobrado no exercício financeiro seguinte ao de sua instituição.
            
            3. NOVENTENA (Art. 150, III, 'c', CF): Deve decorrer pelo menos 
               90 dias entre a publicação da lei e sua aplicação.
            
            EXCEÇÃO IMPORTANTE: A fixação da base de cálculo do IPTU não se 
            submete ao princípio da noventena, conforme jurisprudência consolidada 
            do STF. As bancas frequentemente substituem "base de cálculo" por 
            "alíquota" nas questões para confundir os candidatos.
        """,
        conceitos=[
            {
                "termo": "IPTU",
                "definicao": "Imposto sobre Propriedade Predial e Territorial Urbana, de competência municipal"
            },
            {
                "termo": "Base de Cálculo",
                "definicao": "Valor venal do imóvel, determinado pela municipalidade"
            },
            {
                "termo": "Alíquota",
                "definicao": "Percentual aplicado sobre a base de cálculo para determinar o valor do tributo"
            },
            {
                "termo": "Legalidade Tributária",
                "definicao": "Princípio que exige lei para instituir ou majorar tributos"
            },
            {
                "termo": "Anterioridade",
                "definicao": "Princípio que impede a cobrança no mesmo exercício da criação/majoração"
            },
            {
                "termo": "Noventena",
                "definicao": "Princípio que exige 90 dias entre a publicação e a aplicação da norma"
            }
        ],
        tabela_headers=["Princípio", "Aplica-se ao IPTU?", "Exceções"],
        tabela_rows=[
            ["Legalidade", "Sim", "Nenhuma"],
            ["Anterioridade", "Sim", "Nenhuma"],
            ["Noventena", "Sim", "Fixação da base de cálculo"],
            ["Isonomia", "Sim", "Progressividade por capacidade contributiva"]
        ]
    )
    
    print("📊 Adicionando diagramas educacionais...")
    
    # 1. Diagrama principal dos princípios do IPTU
    material.adicionar_diagrama(
        tipo=TipoDiagrama.HIERARQUIA,
        dados={'tipo_especifico': 'iptu'},
        titulo="Princípios Constitucionais do IPTU"
    )
    
    # 2. Fluxograma do processo de cobrança
    material.adicionar_diagrama(
        tipo=TipoDiagrama.PROCESSO,
        dados={
            'etapas': [
                {'texto': 'Início', 'tipo': 'inicio'},
                {'texto': 'Lançamento\nde Ofício', 'tipo': 'processo'},
                {'texto': 'Notificação\nao Contribuinte', 'tipo': 'processo'},
                {'texto': 'Pagamento\nEfetuado?', 'tipo': 'decisao'},
                {'texto': 'Inscrição em\nDívida Ativa', 'tipo': 'processo'},
                {'texto': 'Execução\nFiscal', 'tipo': 'processo'},
                {'texto': 'Fim', 'tipo': 'fim'}
            ]
        },
        titulo="Processo de Cobrança do IPTU"
    )
    
    # 3. Mapa mental completo do IPTU
    material.adicionar_diagrama(
        tipo=TipoDiagrama.MAPA_MENTAL,
        dados={
            'titulo': 'IPTU',
            'topicos': {
                'Competência': ['Municipal', 'Distrito Federal', 'Indelegável'],
                'Fato Gerador': ['Propriedade', 'Posse', 'Domínio Útil'],
                'Base Cálculo': ['Valor Venal', 'Avaliação', 'Planta Genérica'],
                'Princípios': ['Legalidade', 'Anterioridade', 'Noventena*'],
                'Alíquotas': ['Progressivas', 'Capacidade', 'Função Social'],
                'Exceções': ['Base Cálculo', 'Não Noventena', 'STF']
            }
        },
        titulo="Mapa Mental Completo - IPTU"
    )
    
    # Gerar o PDF
    print("🔄 Gerando PDF com diagramas...")
    
    generator = LabResumosPDFGeneratorComDiagramas()
    
    # Criar diretório se não existir
    os.makedirs("outputs", exist_ok=True)
    
    resultado = await generator.gerar_material(
        tipo=TipoMaterial.RESUMO,
        dados=material,
        output_path="outputs/direito_tributario_iptu_completo.pdf"
    )
    
    return resultado

async def exemplo_portugues_classes_palavras():
    """Exemplo: Material de Português com diagramas"""
    
    print("\n📚 EXEMPLO: Português - Classes de Palavras")
    print("=" * 60)
    
    material = MaterialDataComDiagrama(
        titulo_material="Morfologia da Língua Portuguesa",
        subtitulo="Classes de Palavras - Análise Visual",
        nome_curso="Português para Concursos",
        numero_modulo="2",
        nome_aluno="Maria Silva",
        cpf_aluno="555.666.777-88",
        introducao="""
            As classes de palavras constituem a base da morfologia portuguesa. 
            Este material apresenta uma abordagem visual e sistemática para 
            compreender a classificação e características de cada classe.
        """,
        ponto_importante="""
            DICA IMPORTANTE: A diferenciação entre classes variáveis e 
            invariáveis é fundamental para análise morfológica e é 
            frequentemente cobrada em concursos públicos.
        """,
        titulo_secao_principal="Classificação das Classes de Palavras",
        conteudo_principal="""
            As classes de palavras dividem-se em:
            
            VARIÁVEIS (sofrem flexão):
            • Substantivo: nomeia seres e coisas
            • Adjetivo: caracteriza o substantivo
            • Pronome: substitui ou acompanha o substantivo
            • Numeral: expressa quantidade ou ordem
            • Verbo: indica ação, estado ou fenômeno
            
            INVARIÁVEIS (não sofrem flexão):
            • Advérbio: modifica verbo, adjetivo ou outro advérbio
            • Preposição: estabelece relação entre termos
            • Conjunção: conecta orações ou termos
            • Interjeição: expressa emoção ou sentimento
        """,
        conceitos=[
            {"termo": "Morfologia", "definicao": "Estudo da estrutura e formação das palavras"},
            {"termo": "Flexão", "definicao": "Variação que as palavras sofrem (gênero, número, etc.)"},
            {"termo": "Classe Variável", "definicao": "Palavras que sofrem flexão"},
            {"termo": "Classe Invariável", "definicao": "Palavras que não sofrem flexão"}
        ]
    )
    
    # Mapa mental das classes de palavras
    material.adicionar_diagrama(
        tipo=TipoDiagrama.MAPA_MENTAL,
        dados={
            'titulo': 'Classes de Palavras',
            'topicos': {
                'Variáveis': ['Substantivo', 'Adjetivo', 'Pronome', 'Verbo'],
                'Invariáveis': ['Advérbio', 'Preposição', 'Conjunção'],
                'Flexões': ['Gênero', 'Número', 'Grau'],
                'Funções': ['Sintática', 'Semântica', 'Morfológica']
            }
        },
        titulo="Classificação Geral das Classes"
    )
    
    # Fluxograma de análise morfológica
    material.adicionar_diagrama(
        tipo=TipoDiagrama.PROCESSO,
        dados={
            'etapas': [
                {'texto': 'Palavra', 'tipo': 'inicio'},
                {'texto': 'Identifica\nFunção', 'tipo': 'processo'},
                {'texto': 'Sofre\nFlexão?', 'tipo': 'decisao'},
                {'texto': 'Classe\nVariável', 'tipo': 'processo'},
                {'texto': 'Classe\nInvariável', 'tipo': 'processo'},
                {'texto': 'Análise\nCompleta', 'tipo': 'fim'}
            ]
        },
        titulo="Processo de Análise Morfológica"
    )
    
    generator = LabResumosPDFGeneratorComDiagramas()
    
    resultado = await generator.gerar_material(
        tipo=TipoMaterial.RESUMO,
        dados=material,
        output_path="outputs/portugues_classes_palavras.pdf"
    )
    
    return resultado

async def main():
    """Executa todos os exemplos"""
    
    print("🎯 Lab Resumos - Exemplos com Diagramas SVG")
    print("🎨 Demonstrando integração completa")
    print("=" * 80)
    
    try:
        # Exemplo 1: Direito Tributário
        resultado1 = await exemplo_direito_tributario()
        
        if resultado1["success"]:
            print(f"\n✅ SUCESSO - Direito Tributário:")
            print(f"   📁 {resultado1['local_path']}")
        else:
            print(f"\n❌ ERRO - Direito Tributário: {resultado1['error']}")
        
        # Exemplo 2: Português
        resultado2 = await exemplo_portugues_classes_palavras()
        
        if resultado2["success"]:
            print(f"\n✅ SUCESSO - Português:")
            print(f"   📁 {resultado2['local_path']}")
        else:
            print(f"\n❌ ERRO - Português: {resultado2['error']}")
            
        print("\n" + "=" * 80)
        print("📊 RESUMO DOS EXEMPLOS GERADOS:")
        print("=" * 80)
        
        if resultado1["success"]:
            print("✅ Direito Tributário (IPTU)")
            print("   • Diagrama dos Princípios Constitucionais")
            print("   • Fluxograma do Processo de Cobrança")
            print("   • Mapa Mental Completo do IPTU")
            
        if resultado2["success"]:
            print("✅ Português (Classes de Palavras)")
            print("   • Mapa Mental das Classes")
            print("   • Fluxograma de Análise Morfológica")
            
        print(f"\n🎨 Características dos Diagramas:")
        print(f"   • Identidade visual Lab Resumos mantida")
        print(f"   • Formato SVG escalável e de alta qualidade")
        print(f"   • Integração direta nos PDFs")
        print(f"   • Cores: Amarelo (#F1CC00) e Preto (#333B49)")
        print(f"   • Tipografia: Figtree")
        
    except Exception as e:
        print(f"❌ Erro durante execução dos exemplos: {e}")
        import traceback
        traceback.print_exc()

if __name__ == "__main__":
    asyncio.run(main())
