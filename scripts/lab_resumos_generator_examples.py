"""
Lab Resumos - Sistema de Geração de PDF com Conteúdo Rico
Integração completa com APITemplate.io demonstrando todos os tipos de conteúdo possíveis
Integrado com Azure Key Vault para configurações seguras
"""

import httpx
import asyncio
from datetime import datetime
from typing import Dict, List, Any, Optional
import os
import sys
import json
from dataclasses import dataclass
from enum import Enum

# Adiciona o diretório raiz ao path para importar config
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from config.main import initialize_configuration, get_config_value, get_settings

# ================== CONFIGURAÇÃO ==================
class Config:
    """Configurações da API integrada com Azure Key Vault"""
    
    def __init__(self):
        """Inicializa configurações do Azure Key Vault"""
        if not initialize_configuration():
            raise RuntimeError("Falha ao inicializar configuração do Azure Key Vault")
        
        # Busca API key do Azure Key Vault
        self.api_key = get_config_value("template_io_api_key")
        if not self.api_key:
            raise ValueError("API Key do Template.io não encontrada no Azure Key Vault")
        
        self.base_url = "https://rest.apitemplate.io/v2"
        
        # Template ID fornecido
        self.template_id = "61377b23d853d07e"
    
    @property
    def API_KEY(self) -> str:
        """Retorna a API key do Azure Key Vault"""
        return self.api_key
    
    @property
    def BASE_URL(self) -> str:
        """Retorna a URL base da API"""
        return self.base_url
    
    @property
    def TEMPLATE_ID(self) -> str:
        """Retorna o template ID configurado"""
        return self.template_id

# ================== MODELO DE DADOS COMPLETO ==================
@dataclass
class MaterialRicoData:
    """Estrutura de dados completa com todos os tipos de conteúdo"""
    
    # Informações gerais
    titulo_material: str
    subtitulo: str
    nome_curso: str
    numero_modulo: str
    nome_aluno: str
    cpf_aluno: str
    data_geracao: str
    
    # Seções de conteúdo
    
    # Conteúdo principal com diferentes formatos
    # Elementos visuais diversos
    conceitos_chave: List[Dict[str, str]]
    
    # Dados para gráficos
    grafico_linha: Dict[str, Any]
    grafico_pizza: Dict[str, Any]
    grafico_barras: Dict[str, Any]
    
    # Tabelas complexas
    tabela_dados: Dict[str, Any]
    tabela_cronograma: List[Dict[str, str]]
    
    # Listas estruturadas
    lista_verificacao: List[Dict[str, Any]]
    lista_numerada: List[str]
    lista_aninhada: List[Dict[str, Any]]
    
    # Elementos especiais
    citacoes: List[Dict[str, str]]
    glossario: List[Dict[str, str]]
    formulas_matematicas: List[Dict[str, str]]
    
    # Diagramas e fluxogramas (descrições)
    diagrama_fluxo: List[Dict[str, str]]
    linha_tempo: List[Dict[str, str]]
    
    # Caixas de destaque
    dicas: List[str]
    alertas: List[str]
    exemplos_praticos: List[Dict[str, str]]
    
    # Exercícios
    questoes_multipla_escolha: List[Dict[str, Any]]
    questoes_dissertativas: List[str]
    
    # QR Code e códigos
    qr_code_data: str
    codigo_barra: str
    
    # Estatísticas e métricas
    metricas_resumo: List[Dict[str, str]]
    indicadores_progresso: Dict[str, Any]
    
    # Referências e links
    referencias_bibliograficas: List[str]
    links_uteis: List[Dict[str, str]]
    
    # Metadados adicionais
    tags: List[str]
    nivel_dificuldade: str
    tempo_estimado_leitura: str
    versao_documento: str

# ================== GERADOR DE PDF RICO ==================
class LabResumosRichPDFGenerator:
    """Gerador de PDFs com conteúdo rico e variado"""
    
    def __init__(self, api_key: str = None):
        """
        Inicializa o gerador
        
        Args:
            api_key: Chave da API do APITemplate.io (opcional, usa Azure Key Vault por padrão)
        """
        try:
            self.config = Config()
            self.api_key = api_key or self.config.API_KEY
            self.base_url = self.config.BASE_URL
            self.template_id = self.config.TEMPLATE_ID
            
            print(f"✅ Configuração inicializada com sucesso")
            print(f"   API Key: {self.api_key[:8]}...{self.api_key[-4:]}")
            print(f"   Template ID: {self.template_id}")
            
        except Exception as e:
            print(f"❌ Erro ao inicializar configuração: {e}")
            raise
    
    async def gerar_pdf_exemplo(self, output_path: str = "lab_resumos_exemplo.pdf") -> Dict[str, Any]:
        """
        Gera um PDF de exemplo com todos os tipos de conteúdo
        
        Args:
            output_path: Caminho para salvar o PDF
            
        Returns:
            Dict com informações do PDF gerado
        """
        # Criar dados de exemplo com conteúdo rico
        dados = self._criar_dados_exemplo_completo()
        
        # Preparar payload
        json_data = self._preparar_dados(dados)
        
        print(f"🔄 Gerando PDF com conteúdo rico...")
        print(f"   Total de elementos: {self._contar_elementos(json_data)}")
        
        # URL da API
        api_url = f"{self.base_url}/create-pdf"
        params = {"template_id": self.template_id}
        
        # Debug: mostra informações da requisição
        print(f"🔍 Debug - Template ID: '{self.template_id}'")
        print(f"🔍 Debug - API Key (início): {self.api_key[:10]}...")
        print(f"🔍 Debug - URL: {api_url}")
        print(f"🔍 Debug - Params: {params}")
        print(f"🔍 Debug - Payload keys: {list(json_data.keys())}")
        
        async with httpx.AsyncClient() as client:
            try:
                response = await client.post(
                    api_url,
                    headers={
                        "X-API-KEY": self.api_key,
                        "Content-Type": "application/json"
                    },
                    params=params,
                    json=json_data,
                    timeout=60.0
                )
                
                # Log da resposta para debug
                print(f"📊 Status Code: {response.status_code}")
                
                if response.status_code == 200:
                    result = response.json()
                    print(f"✅ PDF gerado com sucesso na API")
                    
                    # Baixar o PDF
                    if output_path:
                        print(f"📥 Baixando PDF para {output_path}...")
                        await self._baixar_pdf(result["download_url"], output_path)
                    
                    return {
                        "success": True,
                        "pdf_url": result.get("download_url"),
                        "transaction_ref": result.get("transaction_ref"),
                        "local_path": output_path,
                        "timestamp": datetime.now().isoformat(),
                        "elementos_incluidos": self._listar_elementos_incluidos()
                    }
                else:
                    error_msg = f"Erro na API: {response.status_code}"
                    try:
                        error_details = response.json()
                        error_msg += f" - {error_details}"
                        print(f"🔍 Debug - Resposta completa da API: {error_details}")
                    except:
                        error_msg += f" - {response.text[:200]}"  # Limita o tamanho do erro
                        print(f"🔍 Debug - Resposta raw: {response.text}")
                    
                    print(f"🔍 Debug - Headers da resposta: {dict(response.headers)}")
                    print(f"❌ {error_msg}")
                    return {
                        "success": False,
                        "error": error_msg,
                        "status_code": response.status_code
                    }
                    
            except httpx.TimeoutException:
                print("❌ Timeout na requisição")
                return {
                    "success": False,
                    "error": "Timeout na requisição para a API"
                }
            except Exception as e:
                print(f"❌ Erro inesperado: {e}")
                return {
                    "success": False,
                    "error": str(e)
                }
    
    def _criar_dados_exemplo_completo(self) -> MaterialRicoData:
        """Cria dados de exemplo com todos os tipos de conteúdo"""
        return MaterialRicoData(
            # Informações básicas
            titulo_material="Guia Completo de Análise de Dados",
            subtitulo="Demonstração de Todos os Recursos Visuais",
            nome_curso="Data Science Avançado",
            numero_modulo="1",
            nome_aluno="João Silva",
            cpf_aluno="123.456.789-00",
            data_geracao=datetime.now().strftime("%d/%m/%Y"),
            
            # Introdução
            introducao="""Este material demonstra todos os tipos de conteúdo visual disponíveis 
            no sistema Lab Resumos, incluindo gráficos, tabelas, listas, diagramas e muito mais.""",
            
            # Objetivos de aprendizagem
            objetivos_aprendizagem=[
                "Compreender os fundamentos da análise de dados",
                "Aplicar técnicas estatísticas básicas",
                "Interpretar visualizações de dados",
                "Criar relatórios profissionais",
                "Utilizar ferramentas de Business Intelligence"
            ],
            
            # Tópicos principais com diferentes formatos
            topicos_principais=[
                {
                    "titulo": "1. Introdução à Estatística",
                    "conteudo": "Conceitos fundamentais de média, mediana e moda.",
                    "subtopicos": ["Medidas de tendência central", "Medidas de dispersão", "Distribuições"]
                },
                {
                    "titulo": "2. Visualização de Dados",
                    "conteudo": "Técnicas para representar dados graficamente.",
                    "subtopicos": ["Gráficos de linha", "Gráficos de barra", "Gráficos de pizza"]
                },
                {
                    "titulo": "3. Machine Learning Básico",
                    "conteudo": "Algoritmos fundamentais de aprendizado de máquina.",
                    "subtopicos": ["Regressão linear", "Classificação", "Clustering"]
                }
            ],
            
            # Conceitos-chave
            conceitos_chave=[
                {"termo": "Big Data", "definicao": "Grandes volumes de dados complexos"},
                {"termo": "Data Mining", "definicao": "Processo de descoberta de padrões em dados"},
                {"termo": "KPI", "definicao": "Key Performance Indicator - Indicador chave de desempenho"},
                {"termo": "ETL", "definicao": "Extract, Transform, Load - Processo de integração de dados"},
                {"termo": "Dashboard", "definicao": "Painel visual com métricas e indicadores"},
                {"termo": "API", "definicao": "Interface de Programação de Aplicações"}
            ],
            
            # Quadro comparativo
            quadro_comparativo={
                "headers": ["Característica", "SQL", "NoSQL", "NewSQL"],
                "rows": [
                    {
                        "Característica": "Estrutura",
                        "SQL": "Tabelas relacionais",
                        "NoSQL": "Documentos, grafos, chave-valor",
                        "NewSQL": "Híbrido"
                    },
                    {
                        "Característica": "Escalabilidade",
                        "SQL": "Vertical",
                        "NoSQL": "Horizontal",
                        "NewSQL": "Ambas"
                    },
                    {
                        "Característica": "ACID",
                        "SQL": "Sim",
                        "NoSQL": "Parcial",
                        "NewSQL": "Sim"
                    },
                    {
                        "Característica": "Performance",
                        "SQL": "Boa para consultas complexas",
                        "NoSQL": "Excelente para volume",
                        "NewSQL": "Alta performance"
                    }
                ]
            },
            
            # Dados para gráficos
            grafico_linha={
                "titulo": "Evolução do Desempenho",
                "dados": [65, 72, 78, 85, 91, 88, 95],
                "labels": ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul"],
                "cor": "#F1CC00"
            },
            
            grafico_pizza={
                "titulo": "Distribuição de Tarefas",
                "dados": [35, 25, 20, 15, 5],
                "labels": ["Análise", "Desenvolvimento", "Testes", "Documentação", "Outros"],
                "cores": ["#F1CC00", "#333B49", "#2A6B9F", "#A0DDFC", "#FEEF4C"]
            },
            
            grafico_barras={
                "titulo": "Comparativo de Resultados",
                "categorias": ["Q1", "Q2", "Q3", "Q4"],
                "series": [
                    {"nome": "2023", "dados": [45, 52, 48, 61]},
                    {"nome": "2024", "dados": [58, 65, 72, 78]}
                ]
            },
            
            # Tabela de dados complexa
            tabela_dados={
                "titulo": "Análise de Performance por Região",
                "headers": ["Região", "Meta", "Realizado", "% Atingido", "Status"],
                "rows": [
                    ["Norte", "R$ 100.000", "R$ 95.000", "95%", "✓"],
                    ["Sul", "R$ 150.000", "R$ 165.000", "110%", "✓"],
                    ["Leste", "R$ 120.000", "R$ 108.000", "90%", "⚠"],
                    ["Oeste", "R$ 80.000", "R$ 82.000", "102.5%", "✓"],
                    ["Centro", "R$ 200.000", "R$ 180.000", "90%", "⚠"]
                ],
                "totais": ["Total", "R$ 650.000", "R$ 630.000", "96.9%", "✓"]
            },
            
            # Cronograma
            tabela_cronograma=[
                {"fase": "Planejamento", "inicio": "01/01", "fim": "15/01", "responsavel": "Ana", "status": "Concluído"},
                {"fase": "Desenvolvimento", "inicio": "16/01", "fim": "28/02", "responsavel": "Carlos", "status": "Em andamento"},
                {"fase": "Testes", "inicio": "01/03", "fim": "15/03", "responsavel": "Maria", "status": "Pendente"},
                {"fase": "Implantação", "inicio": "16/03", "fim": "31/03", "responsavel": "João", "status": "Pendente"}
            ],
            
            # Lista de verificação
            lista_verificacao=[
                {"item": "Definir objetivos do projeto", "concluido": True, "prioridade": "Alta"},
                {"item": "Levantar requisitos", "concluido": True, "prioridade": "Alta"},
                {"item": "Criar protótipo", "concluido": True, "prioridade": "Média"},
                {"item": "Validar com stakeholders", "concluido": False, "prioridade": "Alta"},
                {"item": "Documentar processos", "concluido": False, "prioridade": "Média"},
                {"item": "Treinar equipe", "concluido": False, "prioridade": "Baixa"}
            ],
            
            # Lista numerada
            lista_numerada=[
                "Primeiro, identifique o problema a ser resolvido",
                "Colete e prepare os dados necessários",
                "Realize análise exploratória dos dados",
                "Aplique técnicas de modelagem apropriadas",
                "Valide os resultados obtidos",
                "Implemente a solução em produção",
                "Monitore e ajuste conforme necessário"
            ],
            
            # Lista aninhada
            lista_aninhada=[
                {
                    "titulo": "Preparação de Dados",
                    "itens": [
                        "Limpeza de dados",
                        "Tratamento de valores ausentes",
                        "Normalização de variáveis"
                    ]
                },
                {
                    "titulo": "Análise Estatística",
                    "itens": [
                        "Estatística descritiva",
                        "Testes de hipótese",
                        "Análise de correlação"
                    ]
                }
            ],
            
            # Citações
            citacoes=[
                {
                    "texto": "Os dados são o novo petróleo da era digital.",
                    "autor": "Clive Humby",
                    "ano": "2006"
                },
                {
                    "texto": "Sem dados, você é apenas mais uma pessoa com opinião.",
                    "autor": "W. Edwards Deming",
                    "ano": "1982"
                }
            ],
            
            # Glossário
            glossario=[
                {"termo": "Algoritmo", "definicao": "Sequência finita de instruções para resolver um problema"},
                {"termo": "Dataset", "definicao": "Conjunto de dados organizados para análise"},
                {"termo": "Feature", "definicao": "Característica ou atributo usado em modelos de ML"},
                {"termo": "Overfitting", "definicao": "Quando o modelo se ajusta demais aos dados de treino"}
            ],
            
            # Fórmulas matemáticas
            formulas_matematicas=[
                {"nome": "Média Aritmética", "formula": "x̄ = Σx/n", "descricao": "Soma dos valores dividida pela quantidade"},
                {"nome": "Desvio Padrão", "formula": "σ = √(Σ(x-μ)²/N)", "descricao": "Medida de dispersão dos dados"},
                {"nome": "Correlação de Pearson", "formula": "r = Σ((x-x̄)(y-ȳ))/√(Σ(x-x̄)²Σ(y-ȳ)²)", "descricao": "Medida de correlação linear"}
            ],
            
            # Diagrama de fluxo (representação textual)
            diagrama_fluxo=[
                {"passo": "1", "acao": "Início", "tipo": "terminal"},
                {"passo": "2", "acao": "Receber dados", "tipo": "processo"},
                {"passo": "3", "acao": "Dados válidos?", "tipo": "decisao"},
                {"passo": "4a", "acao": "Processar dados", "tipo": "processo"},
                {"passo": "4b", "acao": "Retornar erro", "tipo": "processo"},
                {"passo": "5", "acao": "Fim", "tipo": "terminal"}
            ],
            
            # Linha do tempo
            linha_tempo=[
                {"data": "Jan/2024", "evento": "Kickoff do projeto", "tipo": "marco"},
                {"data": "Fev/2024", "evento": "Conclusão da fase 1", "tipo": "entrega"},
                {"data": "Mar/2024", "evento": "Revisão intermediária", "tipo": "revisao"},
                {"data": "Abr/2024", "evento": "Lançamento beta", "tipo": "marco"},
                {"data": "Mai/2024", "evento": "Go-live", "tipo": "marco"}
            ],
            
            # Dicas
            dicas=[
                "💡 Use gráficos de linha para mostrar tendências ao longo do tempo",
                "💡 Valide sempre seus dados antes de iniciar análises",
                "💡 Documente todas as transformações realizadas nos dados",
                "💡 Mantenha backups regulares de seus datasets"
            ],
            
            # Alertas
            alertas=[
                "⚠️ Cuidado com dados sensíveis - aplique anonimização quando necessário",
                "⚠️ Verifique a qualidade dos dados antes de treinar modelos",
                "⚠️ Sempre valide modelos com dados de teste independentes"
            ],
            
            # Exemplos práticos
            exemplos_praticos=[
                {
                    "titulo": "Análise de Vendas",
                    "descricao": "Use regressão linear para prever vendas futuras baseado em histórico",
                    "codigo": "model = LinearRegression()\nmodel.fit(X_train, y_train)\nprevisao = model.predict(X_test)"
                },
                {
                    "titulo": "Segmentação de Clientes",
                    "descricao": "Aplique K-means para agrupar clientes com comportamento similar",
                    "codigo": "kmeans = KMeans(n_clusters=3)\nkmeans.fit(dados_clientes)\nsegmentos = kmeans.labels_"
                }
            ],
            
            # Questões de múltipla escolha
            questoes_multipla_escolha=[
                {
                    "pergunta": "Qual métrica é mais adequada para avaliar modelos de classificação desbalanceados?",
                    "alternativas": ["A) Acurácia", "B) F1-Score", "C) MSE", "D) R²"],
                    "resposta_correta": "B"
                },
                {
                    "pergunta": "O que é overfitting?",
                    "alternativas": [
                        "A) Modelo muito simples",
                        "B) Modelo que generaliza bem",
                        "C) Modelo muito ajustado aos dados de treino",
                        "D) Modelo com poucos parâmetros"
                    ],
                    "resposta_correta": "C"
                }
            ],
            
            # Questões dissertativas
            questoes_dissertativas=[
                "Explique a diferença entre dados estruturados e não estruturados.",
                "Descreva o processo de validação cruzada e sua importância.",
                "Como você identificaria e trataria outliers em um dataset?"
            ],
            
            # QR Code e código de barras
            qr_code_data="https://lab-resumos.com.br/material/12345",
            codigo_barra="123456789012",
            
            # Métricas resumo
            metricas_resumo=[
                {"metrica": "Taxa de Conclusão", "valor": "87%", "variacao": "+5%"},
                {"metrica": "Tempo Médio", "valor": "45 min", "variacao": "-10%"},
                {"metrica": "Satisfação", "valor": "4.8/5.0", "variacao": "+0.3"},
                {"metrica": "Engajamento", "valor": "92%", "variacao": "+8%"}
            ],
            
            # Indicadores de progresso
            indicadores_progresso={
                "teoria_completa": 85,
                "exercicios_resolvidos": 60,
                "projetos_entregues": 40,
                "certificacao": 0
            },
            
            # Referências bibliográficas
            referencias_bibliograficas=[
                "HASTIE, T.; TIBSHIRANI, R.; FRIEDMAN, J. The Elements of Statistical Learning. 2nd ed. Springer, 2009.",
                "JAMES, G. et al. An Introduction to Statistical Learning. Springer, 2013.",
                "GÉRON, A. Hands-On Machine Learning. 2nd ed. O'Reilly, 2019."
            ],
            
            # Links úteis
            links_uteis=[
                {"titulo": "Documentação Python", "url": "https://docs.python.org"},
                {"titulo": "Scikit-learn", "url": "https://scikit-learn.org"},
                {"titulo": "Pandas Documentation", "url": "https://pandas.pydata.org"}
            ],
            
            # Metadados
            tags=["data-science", "machine-learning", "estatística", "python", "análise-de-dados"],
            nivel_dificuldade="Intermediário",
            tempo_estimado_leitura="45 minutos",
            versao_documento="2.0"
        )
    
    def _preparar_dados(self, dados: MaterialRicoData) -> Dict[str, Any]:
        """Prepara os dados para o template"""
        return {
            # Converte o dataclass para dict
            **dados.__dict__
        }
    
    def _contar_elementos(self, dados: Dict[str, Any]) -> int:
        """Conta o número total de elementos no documento"""
        count = 0
        for key, value in dados.items():
            if isinstance(value, list):
                count += len(value)
            elif isinstance(value, dict):
                count += len(value)
            else:
                count += 1
        return count
    
    def _listar_elementos_incluidos(self) -> List[str]:
        """Lista todos os tipos de elementos incluídos"""
        return [
            "📊 Gráfico de Linha",
            "🥧 Gráfico de Pizza", 
            "📈 Gráfico de Barras",
            "📋 Tabelas Complexas",
            "📅 Cronograma",
            "✅ Lista de Verificação",
            "🔢 Lista Numerada",
            "🌳 Lista Aninhada",
            "💬 Citações",
            "📖 Glossário",
            "🔬 Fórmulas Matemáticas",
            "🔄 Diagrama de Fluxo",
            "📍 Linha do Tempo",
            "💡 Dicas",
            "⚠️ Alertas",
            "📝 Exemplos Práticos",
            "❓ Questões Múltipla Escolha",
            "✍️ Questões Dissertativas",
            "📱 QR Code",
            "📊 Métricas e KPIs",
            "📈 Indicadores de Progresso",
            "📚 Referências Bibliográficas",
            "🔗 Links Úteis",
            "🏷️ Tags e Metadados"
        ]
    
    async def _baixar_pdf(self, url: str, output_path: str):
        """Baixa o PDF gerado"""
        async with httpx.AsyncClient() as client:
            response = await client.get(url)
            
        # Criar diretório se não existir
        os.makedirs(os.path.dirname(output_path) if os.path.dirname(output_path) else ".", exist_ok=True)
        
        with open(output_path, "wb") as f:
            f.write(response.content)
        
        print(f"💾 PDF salvo em: {output_path}")

# ================== FUNÇÃO PRINCIPAL ==================
async def main():
    """Função principal para gerar o PDF de exemplo"""
    
    print("="*60)
    print("🎯 LAB RESUMOS - GERADOR DE PDF COM CONTEÚDO RICO")
    print("="*60)
    print()
    
    # Criar o gerador
    generator = LabResumosRichPDFGenerator()
    
    # Informações sobre o que será gerado
    print("📋 Este PDF de exemplo incluirá:")
    for elemento in generator._listar_elementos_incluidos():
        print(f"   {elemento}")
    
    print()
    print("="*60)
    
    # Gerar o PDF
    resultado = await generator.gerar_pdf_exemplo(f"outputs/pdf_gerado_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf")
    
    if resultado["success"]:
        print()
        print("✅ PDF GERADO COM SUCESSO!")
        print(f"   📁 Arquivo: {resultado['local_path']}")
        print(f"   🌐 URL: {resultado['pdf_url']}")
        print(f"   ⏰ Timestamp: {resultado['timestamp']}")
        print()
        print("📊 Elementos incluídos no PDF:")
        for elemento in resultado['elementos_incluidos']:
            print(f"   {elemento}")
    else:
        print()
        print("❌ ERRO NA GERAÇÃO DO PDF")
        print(f"   Erro: {resultado['error']}")
        if 'details' in resultado:
            print(f"   Detalhes: {resultado['details']}")
    
    print()
    print("="*60)

# ================== EXECUTAR ==================
if __name__ == "__main__":
    print("\n🚀 Iniciando geração de PDF com conteúdo rico...\n")
    
    # Verificar se a pasta outputs existe
    os.makedirs("outputs", exist_ok=True)
    
    # Executar
    asyncio.run(main())
    
    print("\n✨ Processo concluído!")