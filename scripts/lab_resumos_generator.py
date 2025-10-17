"""
Integração Lab Resumos com APITemplate.io
Geração automatizada de materiais didáticos padronizados
Integrado com Azure Key Vault para configurações seguras
"""

import httpx
import asyncio
from datetime import datetime
from typing import Dict, List, Any, Optional
import os
import sys
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
        
        # Template ID fornecido pelo usuário
        self.templates = {
            "resumo_teorico": "61377b23d853d07e",
            "lista_exercicios": "61377b23d853d07e",  # Usando o mesmo template por enquanto
            "apostila_completa": "61377b23d853d07e",
            "flashcards": "61377b23d853d07e"
        }
    
    @property
    def API_KEY(self) -> str:
        """Retorna a API key do Azure Key Vault"""
        return self.api_key
    
    @property
    def BASE_URL(self) -> str:
        """Retorna a URL base da API"""
        return self.base_url
    
    @property
    def TEMPLATES(self) -> Dict[str, str]:
        """Retorna os templates configurados"""
        return self.templates

# ================== MODELOS DE DADOS ==================
@dataclass
class MaterialData:
    """Estrutura de dados para o material"""
    # Informações gerais
    titulo_material: str
    subtitulo: str
    nome_curso: str
    numero_modulo: str
    nome_aluno: str
    cpf_aluno: str
    
    # Conteúdo
    ponto_importante: str
    titulo_secao_principal: str
    conteudo_principal: str
    
    # Conceitos
    conceitos: List[Dict[str, str]]
    
    # Tabela (opcional)
    tabela_headers: Optional[List[str]] = None
    tabela_rows: Optional[List[List[str]]] = None
    tabela_dados: bool = False
    
    # Metadados
    data_geracao: Optional[str] = None
    
    def __post_init__(self):
        """Inicializa data de geração se não fornecida"""
        if not self.data_geracao:
            self.data_geracao = datetime.now().strftime("%d/%m/%Y")
        
        # Ativa tabela se houver dados
        if self.tabela_headers and self.tabela_rows:
            self.tabela_dados = True

class TipoMaterial(Enum):
    """Tipos de materiais disponíveis"""
    RESUMO = "resumo_teorico"
    EXERCICIOS = "lista_exercicios"
    APOSTILA = "apostila_completa"
    FLASHCARDS = "flashcards"

# ================== GERADOR DE PDF ==================
class LabResumosPDFGenerator:
    """Classe principal para gerar PDFs do Lab Resumos"""
    
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
            self.templates = self.config.TEMPLATES
            
            print(f"✅ Configuração inicializada com sucesso")
            print(f"   API Key: {self.api_key[:8]}...{self.api_key[-4:]}")
            print(f"   Template ID: {self.templates['resumo_teorico']}")
            
        except Exception as e:
            print(f"❌ Erro ao inicializar configuração: {e}")
            raise
    
    async def gerar_material(
        self,
        tipo: TipoMaterial,
        dados: MaterialData,
        output_path: str = None
    ) -> Dict[str, Any]:
        """
        Gera um material PDF usando APITemplate.io
        
        Args:
            tipo: Tipo do material a gerar
            dados: Dados do conteúdo
            output_path: Caminho para salvar o PDF (opcional)
            
        Returns:
            Dict com informações do PDF gerado
        """
        # Prepara os dados para o template
        json_data = self._preparar_dados(dados)
        
        # Obtém o ID do template
        template_id = self.templates[tipo.value]
        
        print(f"🔄 Gerando {tipo.value} com template {template_id}...")
        
        # CORREÇÃO 1: Template ID como query parameter, não no path
        api_url = f"{self.base_url}/create"
        
        # CORREÇÃO 2: Estrutura correta do payload para API v2
        # A API v2 espera os dados diretamente, não dentro de um objeto "data"
        payload = json_data  # Usa os dados diretamente
        
        # Se você precisar de configurações adicionais, adicione como parâmetros
        params = {
            "template_id": template_id,
            "output_file_type": "pdf",
            "expiration": 0,  # 0 para armazenar permanentemente
        }
        
        # Debug: mostra informações da requisição
        print(f"🔍 Debug - Template ID: '{template_id}'")
        print(f"🔍 Debug - API Key (início): {self.api_key[:10]}...")
        print(f"🔍 Debug - URL: {api_url}")
        print(f"🔍 Debug - Params: {params}")
        print(f"🔍 Debug - Payload keys: {list(payload.keys())}")
        
        # Faz a requisição para a API
        async with httpx.AsyncClient() as client:
            try:
                response = await client.post(
                    api_url,
                    headers={
                        "X-API-KEY": self.api_key,
                        "Content-Type": "application/json"
                    },
                    params=params,  # Template ID como query parameter
                    json=payload,   # Dados do template
                    timeout=60.0
                )
                
                # Log da resposta para debug
                print(f"📊 Status Code: {response.status_code}")
                
                if response.status_code == 200:
                    result = response.json()
                    print(f"✅ PDF gerado com sucesso na API")
                    
                    # Baixa o PDF se output_path fornecido
                    if output_path:
                        print(f"📥 Baixando PDF para {output_path}...")
                        await self._baixar_pdf(result["download_url"], output_path)
                        result["local_path"] = output_path
                    
                    return {
                        "success": True,
                        "pdf_url": result.get("download_url"),
                        "transaction_ref": result.get("transaction_ref"),
                        "local_path": output_path,
                        "timestamp": datetime.now().isoformat()
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
    
    async def gerar_material_alternativo(
        self,
        tipo: TipoMaterial,
        dados: MaterialData,
        output_path: str = None
    ) -> Dict[str, Any]:
        """
        Método alternativo usando endpoint /create-pdf
        Baseado na documentação PHP da API
        """
        # Prepara os dados para o template
        json_data = self._preparar_dados(dados)
        
        # Obtém o ID do template
        template_id = self.templates[tipo.value]
        
        print(f"🔄 Testando endpoint alternativo para {tipo.value} com template {template_id}...")
        
        # Endpoint alternativo
        api_url = f"{self.base_url}/create-pdf"
        params = {"template_id": template_id}
        
        print(f"🔍 Debug Alternativo - URL: {api_url}")
        print(f"🔍 Debug Alternativo - Params: {params}")
        
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
                
                print(f"📊 Status Code (Alternativo): {response.status_code}")
                
                if response.status_code == 200:
                    result = response.json()
                    print(f"✅ PDF gerado com sucesso usando endpoint alternativo!")
                    
                    if output_path:
                        print(f"📥 Baixando PDF para {output_path}...")
                        await self._baixar_pdf(result["download_url"], output_path)
                        result["local_path"] = output_path
                    
                    return {
                        "success": True,
                        "pdf_url": result.get("download_url"),
                        "transaction_ref": result.get("transaction_ref"),
                        "local_path": output_path,
                        "timestamp": datetime.now().isoformat(),
                        "method": "create-pdf"
                    }
                else:
                    error_msg = f"Erro no endpoint alternativo: {response.status_code}"
                    try:
                        error_details = response.json()
                        error_msg += f" - {error_details}"
                    except:
                        error_msg += f" - {response.text[:200]}"
                    
                    print(f"❌ {error_msg}")
                    return {
                        "success": False,
                        "error": error_msg,
                        "status_code": response.status_code,
                        "method": "create-pdf"
                    }
                    
            except Exception as e:
                print(f"❌ Erro no endpoint alternativo: {e}")
                return {
                    "success": False,
                    "error": str(e),
                    "method": "create-pdf"
                }
    
    def _preparar_dados(self, dados: MaterialData) -> Dict[str, Any]:
        """
        Prepara os dados no formato esperado pelo template
        
        Args:
            dados: Dados do material
            
        Returns:
            Dict formatado para o template
        """
        return {
            "titulo_material": dados.titulo_material,
            "subtitulo": dados.subtitulo,
            "nome_curso": dados.nome_curso,
            "numero_modulo": dados.numero_modulo,
            "data_geracao": dados.data_geracao,
            "nome_aluno": dados.nome_aluno,
            "cpf_aluno": dados.cpf_aluno,
            "ponto_importante": dados.ponto_importante,
            "titulo_secao_principal": dados.titulo_secao_principal,
            "conteudo_principal": dados.conteudo_principal,
            "conceitos": dados.conceitos,
            "tabela_headers": dados.tabela_headers,
            "tabela_rows": dados.tabela_rows,
            "tabela_dados": dados.tabela_dados
        }
    
    async def _baixar_pdf(self, url: str, output_path: str):
        """
        Baixa o PDF gerado
        
        Args:
            url: URL do PDF
            output_path: Caminho local para salvar
        """
        async with httpx.AsyncClient() as client:
            response = await client.get(url)
            
        with open(output_path, "wb") as f:
            f.write(response.content)
        
        print(f"💾 PDF salvo em: {output_path}")
    
    async def gerar_lote(
        self,
        materiais: List[tuple[TipoMaterial, MaterialData, str]]
    ) -> List[Dict[str, Any]]:
        """
        Gera múltiplos materiais em lote
        
        Args:
            materiais: Lista de tuplas (tipo, dados, output_path)
            
        Returns:
            Lista com resultados de cada geração
        """
        print(f"🔄 Iniciando geração em lote de {len(materiais)} materiais...")
        
        tasks = [
            self.gerar_material(tipo, dados, output)
            for tipo, dados, output in materiais
        ]
        
        return await asyncio.gather(*tasks)

# ================== HELPERS ==================
class LabResumosContentBuilder:
    """Helper para construir conteúdo estruturado"""
    
    @staticmethod
    def criar_resumo_portugues() -> MaterialData:
        """Exemplo: Cria um resumo de Língua Portuguesa"""
        return MaterialData(
            titulo_material="Classes de Palavras",
            subtitulo="Fundamentos da Língua Portuguesa",
            nome_curso="Português para Concursos",
            numero_modulo="3",
            nome_aluno="João Silva",
            cpf_aluno="123.456.789-00",
            ponto_importante="""
                As classes de palavras são a base para compreendermos a estrutura 
                da Língua Portuguesa. Elas nos permitem analisar frases tanto do 
                ponto de vista sintático quanto semântico, além de facilitar a 
                interpretação de textos mais complexos.
            """,
            ponto_importante="""
                Existe uma relação próxima entre a classe da palavra e sua função 
                sintática. Por exemplo, a palavra "hoje" pertence à classe dos 
                advérbios de tempo e, em uma oração, exerce a função de adjunto 
                adverbial de tempo.
            """,
            titulo_secao_principal="Classes Variáveis e Invariáveis",
            conteudo_principal="""
                As classes variáveis sofrem flexão de gênero, número, grau, pessoa, 
                tempo ou modo. São elas: substantivo, adjetivo, numeral, pronome e 
                verbo. Já as classes invariáveis não sofrem flexão: advérbio, 
                conjunção, preposição e interjeição.
            """,
            conceitos=[
                {
                    "termo": "Substantivo",
                    "definicao": "Palavra que nomeia seres, coisas, sentimentos"
                },
                {
                    "termo": "Adjetivo",
                    "definicao": "Caracteriza ou qualifica o substantivo"
                },
                {
                    "termo": "Verbo",
                    "definicao": "Expressa ação, estado ou fenômeno"
                },
                {
                    "termo": "Advérbio",
                    "definicao": "Modifica verbo, adjetivo ou outro advérbio"
                }
            ],
            tabela_headers=["Classe", "Variável", "Exemplo"],
            tabela_rows=[
                ["Substantivo", "Sim", "casa, casas"],
                ["Adjetivo", "Sim", "bonito, bonita"],
                ["Advérbio", "Não", "rapidamente"],
                ["Conjunção", "Não", "mas, porém"]
            ]
        )
    
    @staticmethod
    def criar_resumo_direito_tributario() -> MaterialData:
        """Exemplo: Cria um resumo de Direito Tributário"""
        return MaterialData(
            titulo_material="Sistema Tributário Nacional",
            subtitulo="Princípios e Competências",
            nome_curso="Direito Tributário - RFB",
            numero_modulo="1",
            nome_aluno="Maria Santos",
            cpf_aluno="987.654.321-00",
            ponto_importante="""
                O Sistema Tributário Nacional está estruturado na Constituição 
                Federal de 1988, estabelecendo as competências tributárias de 
                cada ente federativo e os princípios que regem a tributação.
            """,
            ponto_importante="""
                A competência tributária é indelegável, mas a capacidade 
                tributária ativa (fiscalização e arrecadação) pode ser 
                delegada a outra pessoa jurídica de direito público.
            """,
            titulo_secao_principal="Princípios Constitucionais Tributários",
            conteudo_principal="""
                Os princípios tributários são limitações ao poder de tributar. 
                Entre os principais, destacam-se: legalidade (art. 150, I), 
                isonomia (art. 150, II), irretroatividade (art. 150, III, 'a'), 
                anterioridade (art. 150, III, 'b') e anterioridade nonagesimal 
                (art. 150, III, 'c').
            """,
            conceitos=[
                {
                    "termo": "Legalidade",
                    "definicao": "Tributo só pode ser criado ou majorado por lei"
                },
                {
                    "termo": "Anterioridade",
                    "definicao": "Tributo só pode ser cobrado no exercício seguinte"
                },
                {
                    "termo": "Isonomia",
                    "definicao": "Tratamento igual aos contribuintes em situação equivalente"
                },
                {
                    "termo": "Capacidade Contributiva",
                    "definicao": "Tributação conforme a capacidade econômica"
                }
            ]
        )

# ================== EXEMPLO DE USO ==================
async def exemplo_uso_completo():
    """Demonstra o uso completo do sistema"""
    
    try:
        # Inicializa o gerador (usa Azure Key Vault automaticamente)
        print("🚀 Inicializando Lab Resumos PDF Generator...")
        generator = LabResumosPDFGenerator()
        
        # Cria o conteúdo
        material_portugues = LabResumosContentBuilder.criar_resumo_portugues()
        material_tributario = LabResumosContentBuilder.criar_resumo_direito_tributario()
        
        # Gera PDF individual
        print("\n" + "="*60)
        print("📚 GERANDO RESUMO DE PORTUGUÊS")
        print("="*60)
        
        resultado_portugues = await generator.gerar_material(
            tipo=TipoMaterial.RESUMO,
            dados=material_portugues,
            output_path=f"outputs/pdf_gerado_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
        )
        
        if resultado_portugues["success"]:
            print(f"✅ PDF gerado com sucesso!")
            print(f"   URL: {resultado_portugues['pdf_url']}")
            print(f"   Salvo em: {resultado_portugues['local_path']}")
        else:
            print(f"❌ Erro: {resultado_portugues['error']}")
            print(f"🔄 Tentando método alternativo...")
            
            # Tenta método alternativo se o principal falhar
            resultado_alternativo = await generator.gerar_material_alternativo(
                tipo=TipoMaterial.RESUMO,
                dados=material_portugues,
                output_path=f"outputs/pdf_gerado_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
            )
            
            if resultado_alternativo["success"]:
                print(f"✅ PDF gerado com sucesso usando método alternativo!")
                print(f"   URL: {resultado_alternativo['pdf_url']}")
                print(f"   Salvo em: {resultado_alternativo['local_path']}")
            else:
                print(f"❌ Erro também no método alternativo: {resultado_alternativo['error']}")
        
        # Gera múltiplos PDFs em lote
        print("\n" + "="*60)
        print("📚 GERANDO LOTE DE MATERIAIS")
        print("="*60)
        
        # Cria diretório de output se não existir
        os.makedirs("outputs", exist_ok=True)
        
        materiais_lote = [
            (TipoMaterial.RESUMO, material_portugues, f"outputs/pdf_gerado_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"),
            (TipoMaterial.RESUMO, material_tributario, f"outputs/pdf_gerado_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf")
        ]
        
        resultados_lote = await generator.gerar_lote(materiais_lote)
        
        for i, resultado in enumerate(resultados_lote, 1):
            if resultado["success"]:
                print(f"✅ Material {i} gerado: {resultado['local_path']}")
            else:
                print(f"❌ Erro no material {i}: {resultado['error']}")
                
    except Exception as e:
        print(f"❌ Erro durante execução: {e}")
        import traceback
        traceback.print_exc()

# ================== EXECUTAR ==================
if __name__ == "__main__":
    print("🎯 Lab Resumos - Sistema de Geração de PDFs")
    print("🔐 Integrado com Azure Key Vault")
    print("🎨 Template ID: 61377b23d853d07e")
    print("=" * 60)
    
    # Executa o exemplo
    asyncio.run(exemplo_uso_completo())
    
    print("\n" + "="*60)
    print("📚 Sistema Lab Resumos - Geração de PDFs")
    print("="*60)
    print("Templates disponíveis:")
    print("  • Resumo Teórico")
    print("  • Lista de Exercícios")
    print("  • Apostila Completa")
    print("  • Flashcards")
    print("\n🎨 Identidade visual aplicada:")
    print("  • Cores: Amarelo (#F1CC00), Preto (#333B49)")
    print("  • Tipografia: Figtree")
    print("  • Logo e elementos visuais do Lab Resumos")
    print("\n🔐 Segurança:")
    print("  • API Key gerenciada pelo Azure Key Vault")
    print("  • Configurações centralizadas e seguras")
    print("  • Logs detalhados de todas as operações")
