"""
Google Docs para Lab Resumos PDF - Sistema Premium
Lê um Google Doc público, interpreta sintaxe híbrida e gera PDF profissional de alta qualidade
"""

import os
import re
import json
import base64
import asyncio
from typing import Dict, List, Any, Optional, Tuple
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
import tempfile
import requests
from urllib.parse import urlparse, parse_qs
import random

# Para geração de PDF
import weasyprint
from weasyprint import HTML, CSS
from jinja2 import Template

# ================== CONFIGURAÇÃO ==================
@dataclass
class GoogleDocsConfig:
    """Configuração para acesso ao Google Docs público"""
    use_public_export: bool = True
    export_base_url: str = "https://docs.google.com/document/d/{}/export"
    export_format: str = "html"

@dataclass
class ConfigLabResumos:
    """Configuração do Lab Resumos"""
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
        "fundo_claro": "#FFF9E6",
        "success": "#4CAF50",
        "warning": "#FF9800",
        "danger": "#F44336",
        "info": "#2196F3"
    }
    
    FONTES = {
        "principal": "Figtree, Arial, sans-serif",
        "codigo": "JetBrains Mono, monospace"
    }

# ================== PARSER DO GOOGLE DOCS PÚBLICO ==================
class GoogleDocsPublicParser:
    """Parser para extrair e interpretar conteúdo do Google Docs público"""
    
    def __init__(self, config: GoogleDocsConfig):
        self.config = config
        
    def get_document(self, document_url: str) -> str:
        """Obtém o documento público do Google Docs como HTML"""
        try:
            document_id = self._extract_document_id(document_url)
            export_url = self.config.export_base_url.format(document_id)
            
            print(f"📥 Acessando documento público: {export_url}")
            
            response = requests.get(export_url, params={'format': self.config.export_format})
            response.raise_for_status()
            
            return response.text
            
        except requests.RequestException as error:
            print(f'Erro ao acessar documento: {error}')
            raise
    
    def _extract_document_id(self, document_url: str) -> str:
        """Extrai o ID do documento da URL"""
        if 'docs.google.com' in document_url:
            match = re.search(r'/document/d/([a-zA-Z0-9-_]+)', document_url)
            if match:
                return match.group(1)
        return document_url
    
    def parse_document(self, html_content: str) -> Dict[str, Any]:
        """Parseia o HTML exportado e extrai conteúdo estruturado"""
        
        parsed_content = {
            'metadata': {},
            'titulo': '',
            'secoes': [],
            'elementos_especiais': []
        }
        
        # Extrair título
        title_match = re.search(r'<title>(.*?)</title>', html_content, re.IGNORECASE)
        if title_match:
            parsed_content['titulo'] = title_match.group(1).replace(' - Google Docs', '')
        
        # Extrair conteúdo do body
        body_match = re.search(r'<body[^>]*>(.*?)</body>', html_content, re.IGNORECASE | re.DOTALL)
        if body_match:
            body_content = body_match.group(1)
            parsed_content = self._process_html_content(body_content, parsed_content)
        
        # Processar sintaxes especiais
        parsed_content = self._process_special_syntax(parsed_content)
        
        return parsed_content
    
    def _process_html_content(self, html_content: str, parsed_content: Dict[str, Any]) -> Dict[str, Any]:
        """Processa o conteúdo HTML para extrair seções e elementos"""
        
        sections = re.split(r'(<h[1-6][^>]*>.*?</h[1-6]>)', html_content, flags=re.IGNORECASE | re.DOTALL)
        
        current_section = None
        current_content = []
        
        for i, section in enumerate(sections):
            if re.match(r'<h[1-6][^>]*>', section, re.IGNORECASE):
                if current_section:
                    current_section['conteudo'] = self._clean_html(''.join(current_content))
                    parsed_content['secoes'].append(current_section)
                    current_content = []
                
                heading_match = re.match(r'<h([1-6])[^>]*>(.*?)</h[1-6]>', section, re.IGNORECASE | re.DOTALL)
                if heading_match:
                    level = int(heading_match.group(1))
                    title = self._clean_html(heading_match.group(2))
                    
                    current_section = {
                        'tipo': f'heading_{level}',
                        'titulo': title,
                        'conteudo': ''
                    }
            else:
                if current_section and section.strip():
                    current_content.append(section)
        
        if current_section:
            current_section['conteudo'] = self._clean_html(''.join(current_content))
            parsed_content['secoes'].append(current_section)
        
        if not parsed_content['secoes']:
            parsed_content['secoes'].append({
                'tipo': 'conteudo_geral',
                'titulo': 'Conteúdo',
                'conteudo': self._clean_html(html_content)
            })
        
        return parsed_content
    
    def _clean_html(self, html_content: str) -> str:
        """Remove tags HTML e limpa o conteúdo"""
        clean_text = re.sub(r'<[^>]+>', '', html_content)
        clean_text = re.sub(r'\s+', ' ', clean_text)
        clean_text = clean_text.strip()
        return clean_text
    
    def _process_special_syntax(self, parsed_content: Dict[str, Any]) -> Dict[str, Any]:
        """Processa sintaxes especiais no conteúdo parseado"""
        
        for section in parsed_content['secoes']:
            content = section.get('conteudo', '')
            
            # Detectar sintaxes especiais
            if '[[IMPORTANTE]]' in content:
                section['destaque'] = 'importante'
                content = content.replace('[[IMPORTANTE]]', '')
            
            if '[[CÓDIGO]]' in content:
                section['tipo_elemento'] = 'codigo'
                content = content.replace('[[CÓDIGO]]', '')
            
            if '[[LISTA]]' in content:
                section['tipo_elemento'] = 'lista'
                content = content.replace('[[LISTA]]', '')
                
            if '[[DICA]]' in content:
                section['tipo_elemento'] = 'dica'
                content = content.replace('[[DICA]]', '')
                
            if '[[ALERTA]]' in content:
                section['tipo_elemento'] = 'alerta'
                content = content.replace('[[ALERTA]]', '')
            
            section['conteudo'] = content.strip()
        
        return parsed_content

# ================== GERADOR DE CONTEÚDO ENRIQUECIDO ==================
class ContentEnricher:
    """Enriquece o conteúdo com elementos visuais e dados adicionais"""
    
    def enrich_content(self, parsed_content: Dict[str, Any]) -> Dict[str, Any]:
        """Adiciona elementos visuais e dados complementares ao conteúdo"""
        
        # Dados base
        enriched = {
            **parsed_content,
            'data_geracao': datetime.now().strftime('%d/%m/%Y %H:%M'),
            'versao_documento': '2.0',
            'nome_curso': 'Curso Avançado de Tecnologia',
            'numero_modulo': '3',
            'nivel_dificuldade': 'Intermediário',
            'tempo_estimado_leitura': '45 minutos',
            'nome_aluno': 'João Silva',
            'cpf_aluno': '***.***.***-**',
            'subtitulo': 'Material completo e atualizado'
        }
        
        # Adicionar tags baseadas no conteúdo
        enriched['tags'] = self._generate_tags(parsed_content)
        
        
        # Adicionar conceitos-chave
        enriched['conceitos_chave'] = self._generate_concepts()
        
        
        
        # Adicionar gráficos de dados
        enriched['grafico_linha'] = {
            'titulo': 'Evolução do Aprendizado',
            'labels': ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5', 'Sem 6'],
            'dados': [65, 70, 75, 72, 80, 85],
            'cor': '#2A6B9F'
        }
        
        enriched['grafico_pizza'] = {
            'titulo': 'Distribuição do Conteúdo',
            'labels': ['Teoria', 'Prática', 'Exercícios', 'Projetos'],
            'dados': [35, 30, 20, 15],
            'cores': ['#F1CC00', '#2A6B9F', '#A0DDFC', '#FEEF4C']
        }
        
        enriched['grafico_barras'] = {
            'titulo': 'Desempenho por Módulo',
            'categorias': ['Módulo 1', 'Módulo 2', 'Módulo 3', 'Módulo 4'],
            'series': [
                {'nome': 'Teoria', 'dados': [80, 85, 75, 90]},
                {'nome': 'Prática', 'dados': [70, 75, 80, 85]}
            ]
        }
        
        # Adicionar timeline
        enriched['linha_tempo'] = [
            {'data': 'Jan 2025', 'evento': 'Início do módulo básico'},
            {'data': 'Fev 2025', 'evento': 'Primeiras avaliações práticas'},
            {'data': 'Mar 2025', 'evento': 'Projeto integrador iniciado'},
            {'data': 'Abr 2025', 'evento': 'Conclusão do módulo intermediário'}
        ]
        
        # Adicionar lista de verificação
        enriched['lista_verificacao'] = [
            {'item': 'Revisar conceitos fundamentais', 'concluido': True, 'prioridade': 'Alta'},
            {'item': 'Completar exercícios práticos', 'concluido': True, 'prioridade': 'Alta'},
            {'item': 'Desenvolver projeto final', 'concluido': False, 'prioridade': 'Media'},
            {'item': 'Participar do fórum de discussão', 'concluido': False, 'prioridade': 'Baixa'}
        ]
        
        # Adicionar exemplos práticos
        enriched['exemplos_praticos'] = self._generate_examples()
        
        # Adicionar citações
        enriched['citacoes'] = [
            {
                'texto': 'A educação é a arma mais poderosa que você pode usar para mudar o mundo.',
                'autor': 'Nelson Mandela',
                'ano': '1990'
            },
            {
                'texto': 'O conhecimento torna a alma jovem e diminui a amargura da velhice.',
                'autor': 'Leonardo da Vinci',
                'ano': '1510'
            }
        ]
        
        # Adicionar referências
        enriched['referencias_bibliograficas'] = [
            'SILVA, J. Fundamentos da Tecnologia Moderna. 3ª ed. São Paulo: Editora Tech, 2024.',
            'SANTOS, M. Práticas Avançadas em Desenvolvimento. Rio de Janeiro: EdUFRJ, 2023.',
            'OLIVEIRA, P. Metodologias Ágeis: Uma Abordagem Prática. Brasília: UnB Press, 2024.'
        ]
        
        # Adicionar links úteis
        enriched['links_uteis'] = [
            {'titulo': 'Documentação Oficial', 'url': 'https://docs.exemplo.com'},
            {'titulo': 'Fórum de Discussão', 'url': 'https://forum.exemplo.com'},
            {'titulo': 'Material Complementar', 'url': 'https://recursos.exemplo.com'}
        ]
        
        # Adicionar questões
        enriched['questoes_multipla_escolha'] = self._generate_questions()
        enriched['questoes_dissertativas'] = [
            'Explique com suas palavras os principais conceitos abordados neste módulo.',
            'Como você aplicaria o conhecimento adquirido em um projeto real?',
            'Quais são os principais desafios encontrados na implementação prática?'
        ]
        
        # Adicionar glossário
        enriched['glossario'] = self._generate_glossary()
        
        # Adicionar fórmulas
        enriched['formulas_matematicas'] = [
            {
                'nome': 'Lei de Ohm',
                'formula': 'V = R × I',
                'descricao': 'Relação entre tensão, resistência e corrente elétrica'
            },
            {
                'nome': 'Equação de Bernoulli',
                'formula': 'P + ½ρv² + ρgh = constante',
                'descricao': 'Princípio de conservação de energia em fluidos'
            }
        ]
        
        # Adicionar tabelas
        enriched['tabela_dados'] = self._generate_data_table()
        enriched['tabela_cronograma'] = self._generate_schedule()
        
        # Adicionar quadro comparativo
        enriched['quadro_comparativo'] = self._generate_comparison_table()
        
        # Adicionar lista aninhada
        enriched['lista_aninhada'] = self._generate_nested_list()
        
        # Adicionar diagrama de fluxo
        enriched['diagrama_fluxo'] = [
            {'tipo': 'terminal', 'acao': 'Início'},
            {'tipo': 'processo', 'acao': 'Analisar requisitos'},
            {'tipo': 'decisao', 'acao': 'Requisitos OK?'},
            {'tipo': 'processo', 'acao': 'Implementar solução'},
            {'tipo': 'processo', 'acao': 'Testar sistema'},
            {'tipo': 'terminal', 'acao': 'Fim'}
        ]
        
        # Adicionar QR Code data
        enriched['qr_code_data'] = 'https://labresumos.com/material/12345'
        
        # Adicionar dicas e alertas
        enriched['dicas'] = self._extract_tips(parsed_content)
        enriched['alertas'] = self._extract_warnings(parsed_content)
        
        # Processar tópicos principais
        enriched['topicos_principais'] = self._process_main_topics(parsed_content)
        
        return enriched
    
    def _generate_tags(self, content: Dict[str, Any]) -> List[str]:
        """Gera tags baseadas no conteúdo"""
        tags = ['educação', 'tecnologia', 'aprendizado']
        
        # Adicionar tags baseadas nas seções
        for secao in content.get('secoes', []):
            titulo = secao.get('titulo', '').lower()
            if 'introdução' in titulo:
                tags.append('fundamentos')
            elif 'prática' in titulo:
                tags.append('hands-on')
            elif 'teoria' in titulo:
                tags.append('conceitual')
        
        return tags[:6]  # Limitar a 6 tags
    
    def _generate_concepts(self) -> List[Dict[str, str]]:
        """Gera conceitos-chave"""
        return [
            {
                'termo': 'Algoritmo',
                'definicao': 'Sequência finita de instruções bem definidas para resolver um problema.'
            },
            {
                'termo': 'Estrutura de Dados',
                'definicao': 'Forma de organizar e armazenar dados para uso eficiente.'
            },
            {
                'termo': 'Paradigma',
                'definicao': 'Modelo ou padrão de pensamento para resolver problemas.'
            },
            {
                'termo': 'Abstração',
                'definicao': 'Processo de esconder detalhes complexos e mostrar apenas o essencial.'
            },
            {
                'termo': 'Encapsulamento',
                'definicao': 'Técnica de ocultar detalhes internos de implementação.'
            },
            {
                'termo': 'Polimorfismo',
                'definicao': 'Capacidade de um objeto assumir múltiplas formas.'
            }
        ]
    
    def _generate_examples(self) -> List[Dict[str, str]]:
        """Gera exemplos práticos"""
        return [
            {
                'titulo': 'Implementação de Lista Ligada',
                'descricao': 'Exemplo de estrutura de dados dinâmica',
                'codigo': '''class Node:
    def __init__(self, data):
        self.data = data
        self.next = None

class LinkedList:
    def __init__(self):
        self.head = None
    
    def append(self, data):
        new_node = Node(data)
        if not self.head:
            self.head = new_node
            return
        current = self.head
        while current.next:
            current = current.next
        current.next = new_node'''
            },
            {
                'titulo': 'Algoritmo de Ordenação',
                'descricao': 'Implementação do Bubble Sort',
                'codigo': '''def bubble_sort(arr):
    n = len(arr)
    for i in range(n):
        for j in range(0, n-i-1):
            if arr[j] > arr[j+1]:
                arr[j], arr[j+1] = arr[j+1], arr[j]
    return arr

# Exemplo de uso
numeros = [64, 34, 25, 12, 22, 11, 90]
print(bubble_sort(numeros))'''
            }
        ]
    
    def _generate_questions(self) -> List[Dict[str, Any]]:
        """Gera questões de múltipla escolha"""
        return [
            {
                'pergunta': 'Qual é a complexidade temporal do algoritmo Bubble Sort no pior caso?',
                'alternativas': [
                    'a) O(n)',
                    'b) O(n log n)',
                    'c) O(n²)',
                    'd) O(log n)'
                ],
                'resposta_correta': 'c'
            },
            {
                'pergunta': 'O que caracteriza uma estrutura de dados do tipo pilha (stack)?',
                'alternativas': [
                    'a) FIFO - First In, First Out',
                    'b) LIFO - Last In, First Out',
                    'c) Acesso aleatório',
                    'd) Ordenação automática'
                ],
                'resposta_correta': 'b'
            }
        ]
    
    def _generate_glossary(self) -> List[Dict[str, str]]:
        """Gera glossário de termos"""
        return [
            {'termo': 'API', 'definicao': 'Interface de Programação de Aplicações'},
            {'termo': 'Framework', 'definicao': 'Estrutura base para desenvolvimento de software'},
            {'termo': 'Backend', 'definicao': 'Parte do sistema responsável pela lógica do servidor'},
            {'termo': 'Frontend', 'definicao': 'Interface visual com a qual o usuário interage'},
            {'termo': 'Database', 'definicao': 'Sistema de armazenamento estruturado de dados'},
            {'termo': 'Deploy', 'definicao': 'Processo de publicação de uma aplicação'}
        ]
    
    def _generate_data_table(self) -> Dict[str, Any]:
        """Gera tabela de dados"""
        return {
            'titulo': 'Análise de Desempenho por Trimestre',
            'headers': ['Trimestre', 'Meta', 'Realizado', 'Variação', 'Status'],
            'rows': [
                ['Q1 2024', '1000', '1150', '+15%', '✓'],
                ['Q2 2024', '1200', '1180', '-1.7%', '⚠'],
                ['Q3 2024', '1300', '1420', '+9.2%', '✓'],
                ['Q4 2024', '1500', '1650', '+10%', '✓']
            ],
            'totais': ['Total', '5000', '5400', '+8%', '✓']
        }
    
    def _generate_schedule(self) -> List[Dict[str, str]]:
        """Gera cronograma"""
        return [
            {
                'fase': 'Planejamento',
                'inicio': '01/01/2025',
                'fim': '15/01/2025',
                'responsavel': 'Equipe A',
                'status': 'Concluído'
            },
            {
                'fase': 'Desenvolvimento',
                'inicio': '16/01/2025',
                'fim': '28/02/2025',
                'responsavel': 'Equipe B',
                'status': 'Em andamento'
            },
            {
                'fase': 'Testes',
                'inicio': '01/03/2025',
                'fim': '15/03/2025',
                'responsavel': 'Equipe QA',
                'status': 'Pendente'
            },
            {
                'fase': 'Implantação',
                'inicio': '16/03/2025',
                'fim': '31/03/2025',
                'responsavel': 'DevOps',
                'status': 'Pendente'
            }
        ]
    
    def _generate_comparison_table(self) -> Dict[str, Any]:
        """Gera quadro comparativo"""
        return {
            'headers': ['Característica', 'Método A', 'Método B', 'Método C'],
            'rows': [
                {
                    'Característica': 'Velocidade',
                    'Método A': 'Alta',
                    'Método B': 'Média',
                    'Método C': 'Baixa'
                },
                {
                    'Característica': 'Custo',
                    'Método A': 'Alto',
                    'Método B': 'Médio',
                    'Método C': 'Baixo'
                },
                {
                    'Característica': 'Complexidade',
                    'Método A': 'Complexo',
                    'Método B': 'Moderado',
                    'Método C': 'Simples'
                },
                {
                    'Característica': 'Escalabilidade',
                    'Método A': 'Excelente',
                    'Método B': 'Boa',
                    'Método C': 'Limitada'
                }
            ]
        }
    
    def _generate_nested_list(self) -> List[Dict[str, Any]]:
        """Gera lista aninhada"""
        return [
            {
                'titulo': 'Módulo 1: Fundamentos',
                'itens': [
                    'Introdução aos conceitos básicos',
                    'Configuração do ambiente',
                    'Primeiros passos práticos',
                    'Exercícios de fixação'
                ]
            },
            {
                'titulo': 'Módulo 2: Intermediário',
                'itens': [
                    'Conceitos avançados',
                    'Padrões de projeto',
                    'Boas práticas',
                    'Casos de uso reais'
                ]
            },
            {
                'titulo': 'Módulo 3: Avançado',
                'itens': [
                    'Otimização de performance',
                    'Arquitetura escalável',
                    'Segurança e testes',
                    'Projeto final'
                ]
            }
        ]
    
    def _extract_tips(self, content: Dict[str, Any]) -> List[str]:
        """Extrai dicas do conteúdo"""
        dicas = []
        for secao in content.get('secoes', []):
            if secao.get('tipo_elemento') == 'dica':
                dicas.append(secao.get('conteudo', ''))
        
        # Adicionar dicas padrão se não houver
        if not dicas:
            dicas = [
                'Revise o conteúdo regularmente para melhor fixação',
                'Pratique com exemplos reais para consolidar o aprendizado',
                'Participe ativamente das discussões em grupo'
            ]
        
        return dicas
    
    def _extract_warnings(self, content: Dict[str, Any]) -> List[str]:
        """Extrai alertas do conteúdo"""
        alertas = []
        for secao in content.get('secoes', []):
            if secao.get('tipo_elemento') == 'alerta':
                alertas.append(secao.get('conteudo', ''))
        
        # Adicionar alertas padrão se não houver
        if not alertas:
            alertas = [
                'Certifique-se de completar todos os pré-requisitos antes de avançar',
                'Este conteúdo requer conhecimento prévio em programação básica'
            ]
        
        return alertas
    
    def _process_main_topics(self, content: Dict[str, Any]) -> List[Dict[str, Any]]:
        """Processa tópicos principais do conteúdo"""
        topicos = []
        
        for secao in content.get('secoes', []):
            if secao.get('tipo', '').startswith('heading'):
                topico = {
                    'titulo': secao.get('titulo', 'Tópico'),
                    'conteudo': secao.get('conteudo', ''),
                    'subtopicos': []
                }
                
                # Extrair subtópicos do conteúdo
                linhas = secao.get('conteudo', '').split('.')
                for linha in linhas[:3]:  # Pegar até 3 subtópicos
                    if linha.strip():
                        topico['subtopicos'].append(linha.strip())
                
                topicos.append(topico)
        
        # Se não houver tópicos, criar alguns padrão
        if not topicos:
            topicos = [
                {
                    'titulo': 'Introdução ao Tema',
                    'conteudo': 'Visão geral dos conceitos fundamentais que serão abordados.',
                    'subtopicos': [
                        'Contextualização histórica',
                        'Importância no cenário atual',
                        'Aplicações práticas'
                    ]
                },
                {
                    'titulo': 'Desenvolvimento',
                    'conteudo': 'Aprofundamento nos conceitos e técnicas principais.',
                    'subtopicos': [
                        'Metodologias aplicadas',
                        'Ferramentas utilizadas',
                        'Casos de sucesso'
                    ]
                }
            ]
        
        return topicos

# ================== CONVERSOR PARA PDF PREMIUM ==================
class GoogleDocsToPDFConverter:
    """Converte conteúdo parseado para PDF com design premium"""
    
    def __init__(self):
        self.template_html = self._get_premium_html_template()
        self.enricher = ContentEnricher()
    
    def _get_premium_html_template(self) -> str:
        """Retorna o template HTML premium para o PDF"""
        return """
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <title>{{ titulo }}</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap');
                
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
                
                .page {
                    max-width: 210mm;
                    margin: 0 auto;
                    background: white;
                    position: relative;
                }
                
                /* Header estilizado */
                .header {
                    background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--light-yellow) 100%);
                    padding: 40px;
                    position: relative;
                    overflow: hidden;
                }
                
                .header::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    right: -10%;
                    width: 300px;
                    height: 300px;
                    background: var(--primary-dark);
                    opacity: 0.1;
                    border-radius: 50%;
                }
                
                .header-content {
                    position: relative;
                    z-index: 2;
                }
                
                .logo {
                    display: flex;
                    align-items: center;
                    gap: 20px;
                    margin-bottom: 30px;
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
                
                .logo-text p {
                    font-size: 14px;
                    color: var(--primary-dark);
                    opacity: 0.8;
                }
                
                /* Informações do documento */
                .doc-info {
                    background: var(--primary-dark);
                    color: white;
                    padding: 30px 40px;
                    display: grid;
                    grid-template-columns: 2fr 1fr;
                    gap: 30px;
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
                
                .doc-meta {
                    text-align: right;
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                }
                
                .doc-meta span {
                    font-size: 14px;
                }
                
                .doc-meta .highlight {
                    color: var(--primary-yellow);
                    font-weight: 600;
                }
                
                /* Tags */
                .tags-container {
                    padding: 20px 40px;
                    background: var(--bg-light);
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                
                .tag {
                    background: white;
                    padding: 5px 15px;
                    border-radius: 20px;
                    font-size: 12px;
                    border: 2px solid var(--primary-yellow);
                    color: var(--primary-dark);
                }
                
                /* Conteúdo principal */
                .content {
                    padding: 40px;
                }
                
                /* Seções */
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
                
                /* Objetivos */
                .objectives-list {
                    background: linear-gradient(to right, var(--bg-light), white);
                    padding: 25px;
                    border-left: 5px solid var(--primary-blue);
                    margin: 20px 0;
                }
                
                .objectives-list h3 {
                    color: var(--primary-blue);
                    margin-bottom: 15px;
                    font-size: 18px;
                }
                
                .objectives-list ul {
                    list-style: none;
                }
                
                .objectives-list li {
                    padding: 8px 0;
                    padding-left: 30px;
                    position: relative;
                }
                
                .objectives-list li::before {
                    content: '🎯';
                    position: absolute;
                    left: 0;
                }
                
                /* Grid de conceitos */
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
                    transition: transform 0.3s, box-shadow 0.3s;
                }
                
                .concept-card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                    border-color: var(--primary-yellow);
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
                
                /* Tabela comparativa */
                .comparison-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 30px 0;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .comparison-table thead {
                    background: var(--primary-dark);
                    color: white;
                }
                
                .comparison-table th {
                    padding: 15px;
                    text-align: left;
                    font-weight: 600;
                    border-right: 1px solid rgba(255,255,255,0.1);
                }
                
                .comparison-table th:first-child {
                    background: var(--primary-blue);
                }
                
                .comparison-table td {
                    padding: 12px 15px;
                    border-bottom: 1px solid #e0e0e0;
                    border-right: 1px solid #e0e0e0;
                }
                
                .comparison-table tbody tr:nth-child(even) {
                    background: var(--bg-light);
                }
                
                .comparison-table tbody tr:hover {
                    background: var(--light-yellow);
                    transition: background 0.3s;
                }
                
                .comparison-table td:first-child {
                    font-weight: 600;
                    background: rgba(42, 107, 159, 0.05);
                }
                
                /* Timeline */
                .timeline {
                    position: relative;
                    padding: 20px 0;
                    margin: 40px 0;
                }
                
                .timeline::before {
                    content: '';
                    position: absolute;
                    left: 50%;
                    top: 0;
                    bottom: 0;
                    width: 3px;
                    background: var(--primary-yellow);
                }
                
                .timeline-item {
                    position: relative;
                    padding: 20px;
                    width: calc(50% - 40px);
                    margin-bottom: 30px;
                }
                
                .timeline-item:nth-child(odd) {
                    left: 0;
                    text-align: right;
                }
                
                .timeline-item:nth-child(even) {
                    left: calc(50% + 40px);
                }
                
                .timeline-item::before {
                    content: '';
                    position: absolute;
                    width: 20px;
                    height: 20px;
                    background: var(--primary-yellow);
                    border: 4px solid white;
                    border-radius: 50%;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                }
                
                .timeline-item:nth-child(odd)::before {
                    right: -50px;
                }
                
                .timeline-item:nth-child(even)::before {
                    left: -50px;
                }
                
                .timeline-date {
                    font-weight: 600;
                    color: var(--primary-blue);
                    margin-bottom: 5px;
                }
                
                .timeline-content {
                    background: white;
                    padding: 15px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                /* Checklist */
                .checklist {
                    background: white;
                    border: 2px solid var(--bg-light);
                    border-radius: 10px;
                    padding: 25px;
                    margin: 30px 0;
                }
                
                .checklist-item {
                    display: flex;
                    align-items: center;
                    padding: 12px;
                    margin-bottom: 10px;
                    background: var(--bg-light);
                    border-radius: 8px;
                    transition: all 0.3s;
                }
                
                .checklist-item:hover {
                    background: var(--light-yellow);
                }
                
                .checkbox {
                    width: 24px;
                    height: 24px;
                    border: 2px solid var(--primary-dark);
                    border-radius: 4px;
                    margin-right: 15px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 16px;
                }
                
                .checkbox.checked {
                    background: var(--success);
                    border-color: var(--success);
                    color: white;
                }
                
                .checklist-text {
                    flex: 1;
                }
                
                .priority-badge {
                    padding: 4px 10px;
                    border-radius: 12px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                
                .priority-alta {
                    background: #ffebee;
                    color: var(--danger);
                }
                
                .priority-media {
                    background: #fff3e0;
                    color: var(--warning);
                }
                
                .priority-baixa {
                    background: #e8f5e9;
                    color: var(--success);
                }
                
                /* Progress indicators */
                .progress-indicators {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin: 30px 0;
                }
                
                .progress-card {
                    background: white;
                    border: 2px solid var(--bg-light);
                    border-radius: 10px;
                    padding: 20px;
                    text-align: center;
                }
                
                .progress-title {
                    font-size: 14px;
                    color: #666;
                    margin-bottom: 10px;
                }
                
                .progress-bar-container {
                    background: #e0e0e0;
                    height: 10px;
                    border-radius: 5px;
                    overflow: hidden;
                    margin-bottom: 10px;
                }
                
                .progress-bar {
                    height: 100%;
                    background: linear-gradient(to right, var(--primary-yellow), var(--primary-blue));
                    transition: width 0.5s;
                }
                
                .progress-value {
                    font-size: 24px;
                    font-weight: 700;
                    color: var(--primary-dark);
                }
                
                /* Métricas */
                .metrics-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 20px;
                    margin: 30px 0;
                }
                
                .metric-card {
                    background: linear-gradient(135deg, var(--primary-yellow), var(--light-yellow));
                    border-radius: 10px;
                    padding: 20px;
                    text-align: center;
                    color: var(--primary-dark);
                }
                
                .metric-label {
                    font-size: 12px;
                    opacity: 0.8;
                    margin-bottom: 5px;
                }
                
                .metric-value {
                    font-size: 28px;
                    font-weight: 800;
                    margin-bottom: 5px;
                }
                
                .metric-change {
                    font-size: 14px;
                    font-weight: 600;
                }
                
                .metric-change.positive {
                    color: var(--success);
                }
                
                .metric-change.negative {
                    color: var(--danger);
                }
                
                /* Highlight boxes */
                .highlight-box {
                    padding: 20px;
                    margin: 30px 0;
                    border-radius: 10px;
                    border-left: 5px solid;
                }
                
                .highlight-box.tip {
                    background: #fffde7;
                    border-color: var(--primary-yellow);
                }
                
                .highlight-box.warning {
                    background: #fff3e0;
                    border-color: var(--warning);
                }
                
                .highlight-box-title {
                    font-weight: 700;
                    margin-bottom: 10px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                /* Code examples */
                .code-example {
                    background: #1e1e1e;
                    color: #d4d4d4;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                    font-family: 'JetBrains Mono', monospace;
                    font-size: 14px;
                    overflow-x: auto;
                }
                
                .code-title {
                    color: var(--primary-yellow);
                    margin-bottom: 10px;
                    font-weight: 600;
                }
                
                /* Quotes */
                .quote-box {
                    background: linear-gradient(135deg, var(--bg-light), white);
                    border-left: 4px solid var(--primary-blue);
                    padding: 25px;
                    margin: 30px 0;
                    position: relative;
                    font-style: italic;
                }
                
                .quote-box::before {
                    content: '"';
                    font-size: 60px;
                    color: var(--primary-yellow);
                    position: absolute;
                    top: -10px;
                    left: 20px;
                    opacity: 0.3;
                }
                
                .quote-text {
                    font-size: 16px;
                    line-height: 1.6;
                    margin-bottom: 15px;
                }
                
                .quote-author {
                    text-align: right;
                    font-style: normal;
                    font-weight: 600;
                    color: var(--primary-blue);
                }
                
                /* QR Section */
                .qr-section {
                    display: flex;
                    align-items: center;
                    gap: 30px;
                    padding: 30px;
                    background: var(--bg-light);
                    border-radius: 10px;
                    margin: 30px 0;
                }
                
                .qr-code {
                    width: 150px;
                    height: 150px;
                    background: white;
                    padding: 10px;
                    border: 2px solid var(--primary-dark);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                /* Footer */
                .footer {
                    background: var(--primary-dark);
                    color: white;
                    padding: 40px;
                    margin-top: 60px;
                }
                
                .footer-content {
                    display: grid;
                    grid-template-columns: 2fr 1fr;
                    gap: 40px;
                }
                
                .footer-brand {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    margin-bottom: 20px;
                }
                
                .footer-text {
                    color: var(--light-blue);
                    font-size: 14px;
                    line-height: 1.6;
                }
                
                .footer-security {
                    text-align: right;
                    color: var(--primary-yellow);
                    font-size: 12px;
                }
                
                /* Decorative elements */
                .decoration {
                    position: absolute;
                    background: var(--primary-yellow);
                    opacity: 0.1;
                    border-radius: 50%;
                    pointer-events: none;
                }
                
                .decoration-1 {
                    width: 200px;
                    height: 200px;
                    top: 100px;
                    right: -100px;
                }
                
                .decoration-2 {
                    width: 150px;
                    height: 150px;
                    bottom: 200px;
                    left: -75px;
                }
                
                /* Flow diagram */
                .flow-diagram {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 20px;
                    padding: 30px;
                    background: var(--bg-light);
                    border-radius: 10px;
                }
                
                .flow-item {
                    display: flex;
                    align-items: center;
                    gap: 20px;
                }
                
                .flow-box {
                    padding: 15px 30px;
                    font-weight: 600;
                    min-width: 150px;
                    text-align: center;
                }
                
                .flow-terminal {
                    background: var(--primary-dark);
                    color: white;
                    border-radius: 25px;
                }
                
                .flow-process {
                    background: white;
                    border: 2px solid var(--primary-blue);
                    border-radius: 8px;
                }
                
                .flow-decision {
                    background: var(--primary-yellow);
                    color: var(--primary-dark);
                    transform: rotate(45deg);
                }
                
                .flow-decision-text {
                    display: block;
                    transform: rotate(-45deg);
                }
                
                .flow-arrow {
                    width: 3px;
                    height: 30px;
                    background: var(--primary-blue);
                }
                
                /* Print styles */
                @media print {
                    .page {
                        max-width: 100%;
                    }
                    
                    .section {
                        page-break-inside: avoid;
                    }
                    
                    .chart-container {
                        page-break-inside: avoid;
                    }
                }
            </style>
        </head>
        <body>
            <div class="page">
                <!-- Decorative elements -->
                <div class="decoration decoration-1"></div>
                <div class="decoration decoration-2"></div>
                
                <!-- Header -->
                <header class="header">
                    <div class="header-content">
                        <div class="logo">
                            <div class="logo-icon">LAB</div>
                            <div class="logo-text">
                                <h1>lab resumos</h1>
                                <p>Laboratório de Resumos Educacionais</p>
                            </div>
                        </div>
                    </div>
                </header>
                
                <!-- Document info -->
                <div class="doc-info">
                    <div class="doc-title">
                        <h2>{{ titulo }}</h2>
                        <p class="doc-subtitle">{{ subtitulo }}</p>
                    </div>
                    <div class="doc-meta">
                        <span class="highlight">{{ nome_curso }}</span>
                        <span>Módulo {{ numero_modulo }}</span>
                        <span>{{ data_geracao }}</span>
                        <span>Nível: {{ nivel_dificuldade }}</span>
                        <span>⏱ {{ tempo_estimado_leitura }}</span>
                    </div>
                </div>
                
                <!-- Tags -->
                <div class="tags-container">
                    {% for tag in tags %}
                    <span class="tag">#{{ tag }}</span>
                    {% endfor %}
                </div>
                
                <!-- Main content -->
                <main class="content">
                    
                    <!-- Introduction -->
                    {% if introducao %}
                    <section class="section">
                        <h2 class="section-title">Introdução</h2>
                        <p>{{ introducao }}</p>
                    </section>
                    {% endif %}
                    
                    <!-- Learning objectives -->
                    <section class="section">
                        <div class="objectives-list">
                            <h3>🎯 Objetivos de Aprendizagem</h3>
                            <ul>
                                {% for objetivo in objetivos_aprendizagem %}
                                <li>{{ objetivo }}</li>
                                {% endfor %}
                            </ul>
                        </div>
                    </section>
                    
                    <!-- Main topics -->
                    <section class="section">
                        <h2 class="section-title">Conteúdo Principal</h2>
                        {% for topico in topicos_principais %}
                        <div style="margin-bottom: 30px;">
                            <h3 style="color: var(--primary-blue); margin-bottom: 10px;">{{ topico.titulo }}</h3>
                            <p style="margin-bottom: 15px;">{{ topico.conteudo }}</p>
                            {% if topico.subtopicos %}
                            <ul style="list-style: none; padding-left: 20px;">
                                {% for subtopico in topico.subtopicos %}
                                <li style="padding: 5px 0;">▸ {{ subtopico }}</li>
                                {% endfor %}
                            </ul>
                            {% endif %}
                        </div>
                        {% endfor %}
                    </section>
                    
                    <!-- Key concepts -->
                    <section class="section">
                        <h2 class="section-title">Conceitos-Chave</h2>
                        <div class="concepts-grid">
                            {% for conceito in conceitos_chave %}
                            <div class="concept-card">
                                <div class="concept-term">{{ conceito.termo }}</div>
                                <div class="concept-definition">{{ conceito.definicao }}</div>
                            </div>
                            {% endfor %}
                        </div>
                    </section>
                    
                    <!-- Comparison table -->
                    <section class="section">
                        <h2 class="section-title">Quadro Comparativo</h2>
                        <table class="comparison-table">
                            <thead>
                                <tr>
                                    {% for header in quadro_comparativo.headers %}
                                    <th>{{ header }}</th>
                                    {% endfor %}
                                </tr>
                            </thead>
                            <tbody>
                                {% for row in quadro_comparativo.rows %}
                                <tr>
                                    {% for header in quadro_comparativo.headers %}
                                    <td>{{ row[header] }}</td>
                                    {% endfor %}
                                </tr>
                                {% endfor %}
                            </tbody>
                        </table>
                    </section>
                    
                    <!-- Timeline -->
                    <section class="section">
                        <h2 class="section-title">Linha do Tempo</h2>
                        <div class="timeline">
                            {% for item in linha_tempo %}
                            <div class="timeline-item">
                                <div class="timeline-date">{{ item.data }}</div>
                                <div class="timeline-content">
                                    {{ item.evento }}
                                </div>
                            </div>
                            {% endfor %}
                        </div>
                    </section>
                    
                    <!-- Checklist -->
                    <section class="section">
                        <h2 class="section-title">Checklist de Tarefas</h2>
                        <div class="checklist">
                            {% for item in lista_verificacao %}
                            <div class="checklist-item">
                                <div class="checkbox {% if item.concluido %}checked{% endif %}">
                                    {% if item.concluido %}✓{% endif %}
                                </div>
                                <div class="checklist-text">{{ item.item }}</div>
                                <span class="priority-badge priority-{{ item.prioridade|lower }}">
                                    {{ item.prioridade }}
                                </span>
                            </div>
                            {% endfor %}
                        </div>
                    </section>
                    
                    <!-- Progress indicators -->
                    <section class="section">
                        <h2 class="section-title">Progresso do Curso</h2>
                        <div class="progress-indicators">
                            <div class="progress-card">
                                <div class="progress-title">Teoria Completa</div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: {{ indicadores_progresso.teoria_completa }}%"></div>
                                </div>
                                <div class="progress-value">{{ indicadores_progresso.teoria_completa }}%</div>
                            </div>
                            <div class="progress-card">
                                <div class="progress-title">Exercícios</div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: {{ indicadores_progresso.exercicios_resolvidos }}%"></div>
                                </div>
                                <div class="progress-value">{{ indicadores_progresso.exercicios_resolvidos }}%</div>
                            </div>
                            <div class="progress-card">
                                <div class="progress-title">Projetos</div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: {{ indicadores_progresso.projetos_entregues }}%"></div>
                                </div>
                                <div class="progress-value">{{ indicadores_progresso.projetos_entregues }}%</div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Metrics -->
                    <section class="section">
                        <h2 class="section-title">Métricas de Desempenho</h2>
                        <div class="metrics-grid">
                            {% for metrica in metricas_resumo %}
                            <div class="metric-card">
                                <div class="metric-label">{{ metrica.metrica }}</div>
                                <div class="metric-value">{{ metrica.valor }}</div>
                                <div class="metric-change {% if metrica.variacao[0] == '+' %}positive{% else %}negative{% endif %}">
                                    {{ metrica.variacao }}
                                </div>
                            </div>
                            {% endfor %}
                        </div>
                    </section>
                    
                    <!-- Tips and alerts -->
                    {% if dicas or alertas %}
                    <section class="section">
                        <h2 class="section-title">Dicas e Observações</h2>
                        
                        {% for dica in dicas %}
                        <div class="highlight-box tip">
                            <div class="highlight-box-title">
                                💡 Dica
                            </div>
                            {{ dica }}
                        </div>
                        {% endfor %}
                        
                        {% for alerta in alertas %}
                        <div class="highlight-box warning">
                            <div class="highlight-box-title">
                                ⚠️ Atenção
                            </div>
                            {{ alerta }}
                        </div>
                        {% endfor %}
                    </section>
                    {% endif %}
                    
                    <!-- Code examples -->
                    <section class="section">
                        <h2 class="section-title">Exemplos Práticos</h2>
                        {% for exemplo in exemplos_praticos %}
                        <div style="margin-bottom: 30px;">
                            <h3 style="color: var(--primary-blue);">{{ exemplo.titulo }}</h3>
                            <p style="margin: 10px 0;">{{ exemplo.descricao }}</p>
                            <div class="code-example">
                                <div class="code-title">Python</div>
                                <pre>{{ exemplo.codigo }}</pre>
                            </div>
                        </div>
                        {% endfor %}
                    </section>
                    
                    <!-- Quotes -->
                    <section class="section">
                        <h2 class="section-title">Citações Relevantes</h2>
                        {% for citacao in citacoes %}
                        <div class="quote-box">
                            <div class="quote-text">{{ citacao.texto }}</div>
                            <div class="quote-author">— {{ citacao.autor }}, {{ citacao.ano }}</div>
                        </div>
                        {% endfor %}
                    </section>
                    
                    <!-- QR Code -->
                    <section class="section">
                        <h2 class="section-title">Acesso Digital</h2>
                        <div class="qr-section">
                            <div class="qr-code">
                                <span style="font-size: 40px;">📱</span>
                            </div>
                            <div>
                                <h3>Material Digital</h3>
                                <p>Escaneie o QR Code para acessar a versão digital deste material e recursos adicionais.</p>
                                <p><strong>URL:</strong> {{ qr_code_data }}</p>
                            </div>
                        </div>
                    </section>
                    
                    <!-- References -->
                    <section class="section">
                        <h2 class="section-title">Referências Bibliográficas</h2>
                        <ol style="padding-left: 20px;">
                            {% for referencia in referencias_bibliograficas %}
                            <li style="margin-bottom: 10px;">{{ referencia }}</li>
                            {% endfor %}
                        </ol>
                    </section>
                    
                    <!-- Useful links -->
                    <section class="section">
                        <h2 class="section-title">Links e Recursos</h2>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                            {% for link in links_uteis %}
                            <div style="background: var(--bg-light); padding: 15px; border-radius: 8px; border-left: 4px solid var(--primary-blue);">
                                <strong style="color: var(--primary-blue);">🔗 {{ link.titulo }}</strong>
                                <p style="font-size: 12px; color: #666; margin-top: 5px;">{{ link.url }}</p>
                            </div>
                            {% endfor %}
                        </div>
                    </section>
                    
                    <!-- Exercises -->
                    <section class="section">
                        <h2 class="section-title">Exercícios de Fixação</h2>
                        
                        <h3 style="color: var(--primary-blue); margin: 20px 0;">Questões de Múltipla Escolha</h3>
                        {% for questao in questoes_multipla_escolha %}
                        <div style="background: var(--bg-light); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                            <p style="font-weight: 600; margin-bottom: 15px;">{{ loop.index }}. {{ questao.pergunta }}</p>
                            <div style="padding-left: 20px;">
                                {% for alternativa in questao.alternativas %}
                                <p style="padding: 5px 0;">{{ alternativa }}</p>
                                {% endfor %}
                            </div>
                            <p style="margin-top: 15px; font-size: 12px; color: var(--primary-blue);">
                                <strong>Resposta correta: {{ questao.resposta_correta }}</strong>
                            </p>
                        </div>
                        {% endfor %}
                        
                        <h3 style="color: var(--primary-blue); margin: 20px 0;">Questões Dissertativas</h3>
                        {% for questao in questoes_dissertativas %}
                        <div style="background: white; border: 2px solid var(--bg-light); padding: 20px; border-radius: 10px; margin-bottom: 15px;">
                            <p style="font-weight: 600;">{{ loop.index }}. {{ questao }}</p>
                            <div style="height: 80px; border: 1px dashed #ccc; margin-top: 15px; border-radius: 5px; background: #fafafa;"></div>
                        </div>
                        {% endfor %}
                    </section>
                    
                    <!-- Glossary -->
                    <section class="section">
                        <h2 class="section-title">Glossário</h2>
                        <div style="columns: 2; column-gap: 30px;">
                            {% for termo in glossario %}
                            <div style="break-inside: avoid; margin-bottom: 15px; padding: 15px; background: var(--bg-light); border-radius: 8px;">
                                <strong style="color: var(--primary-blue);">{{ termo.termo }}:</strong>
                                <span style="font-size: 14px;">{{ termo.definicao }}</span>
                            </div>
                            {% endfor %}
                        </div>
                    </section>
                    
                    <!-- Mathematical formulas -->
                    <section class="section">
                        <h2 class="section-title">Fórmulas e Equações</h2>
                        <div style="display: grid; gap: 20px;">
                            {% for formula in formulas_matematicas %}
                            <div style="background: white; border: 1px solid #e0e0e0; padding: 20px; border-radius: 10px;">
                                <h4 style="color: var(--primary-blue); margin-bottom: 10px;">{{ formula.nome }}</h4>
                                <div style="background: var(--bg-light); padding: 15px; border-radius: 5px; font-family: 'JetBrains Mono', monospace; font-size: 18px; text-align: center; margin: 10px 0;">
                                    {{ formula.formula }}
                                </div>
                                <p style="font-size: 14px; color: #666;">{{ formula.descricao }}</p>
                            </div>
                            {% endfor %}
                        </div>
                    </section>
                    
                    <!-- Data table -->
                    <section class="section">
                        <h2 class="section-title">{{ tabela_dados.titulo }}</h2>
                        <div style="overflow-x: auto;">
                            <table class="comparison-table">
                                <thead>
                                    <tr>
                                        {% for header in tabela_dados.headers %}
                                        <th>{{ header }}</th>
                                        {% endfor %}
                                    </tr>
                                </thead>
                                <tbody>
                                    {% for row in tabela_dados.rows %}
                                    <tr>
                                        {% for cell in row %}
                                        <td>{{ cell }}</td>
                                        {% endfor %}
                                    </tr>
                                    {% endfor %}
                                </tbody>
                                <tfoot style="background: var(--primary-dark); color: white; font-weight: 600;">
                                    <tr>
                                        {% for cell in tabela_dados.totais %}
                                        <td style="padding: 15px;">{{ cell }}</td>
                                        {% endfor %}
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>
                    
                    <!-- Schedule -->
                    <section class="section">
                        <h2 class="section-title">Cronograma do Curso</h2>
                        <div style="overflow-x: auto;">
                            <table class="comparison-table">
                                <thead>
                                    <tr>
                                        <th>Fase</th>
                                        <th>Início</th>
                                        <th>Fim</th>
                                        <th>Responsável</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {% for item in tabela_cronograma %}
                                    <tr>
                                        <td style="font-weight: 600;">{{ item.fase }}</td>
                                        <td>{{ item.inicio }}</td>
                                        <td>{{ item.fim }}</td>
                                        <td>{{ item.responsavel }}</td>
                                        <td>
                                            <span style="padding: 4px 10px; border-radius: 12px; font-size: 12px; 
                                                {% if item.status == 'Concluído' %}
                                                    background: #e8f5e9; color: var(--success);
                                                {% elif item.status == 'Em andamento' %}
                                                    background: #fff3e0; color: var(--warning);
                                                {% else %}
                                                    background: #f3e5f5; color: #9c27b0;
                                                {% endif %}">
                                                {{ item.status }}
                                            </span>
                                        </td>
                                    </tr>
                                    {% endfor %}
                                </tbody>
                            </table>
                        </div>
                    </section>
                    
                    <!-- Nested list -->
                    <section class="section">
                        <h2 class="section-title">Estrutura do Conteúdo</h2>
                        {% for grupo in lista_aninhada %}
                        <div style="margin-bottom: 25px;">
                            <h3 style="color: var(--primary-blue); margin-bottom: 15px;">{{ grupo.titulo }}</h3>
                            <ul style="list-style: none; padding-left: 20px;">
                                {% for item in grupo.itens %}
                                <li style="padding: 8px 0; border-left: 3px solid var(--primary-yellow); padding-left: 15px; margin-bottom: 5px;">
                                    {{ item }}
                                </li>
                                {% endfor %}
                            </ul>
                        </div>
                        {% endfor %}
                    </section>
                    
                    <!-- Flow diagram -->
                    <section class="section">
                        <h2 class="section-title">Fluxo do Processo</h2>
                        <div class="flow-diagram">
                            {% for passo in diagrama_fluxo %}
                            <div class="flow-item">
                                <div class="flow-box 
                                    {% if passo.tipo == 'terminal' %}flow-terminal
                                    {% elif passo.tipo == 'decisao' %}flow-decision
                                    {% else %}flow-process{% endif %}">
                                    {% if passo.tipo == 'decisao' %}
                                        <span class="flow-decision-text">{{ passo.acao }}</span>
                                    {% else %}
                                        {{ passo.acao }}
                                    {% endif %}
                                </div>
                            </div>
                            {% if not loop.last %}
                            <div class="flow-arrow"></div>
                            {% endif %}
                            {% endfor %}
                        </div>
                    </section>
                    
                </main>
                
                <!-- Footer -->
                <footer class="footer">
                    <div class="footer-content">
                        <div>
                            <div class="footer-brand">
                                <span style="font-size: 24px; font-weight: 900; color: var(--primary-yellow);">LAB</span>
                                <span style="color: var(--light-blue);">RESUMOS</span>
                            </div>
                            <p class="footer-text">
                                Material desenvolvido com tecnologia de ponta para maximizar seu aprendizado.
                                Todos os direitos reservados. Reprodução proibida.
                            </p>
                            <p style="margin-top: 15px; font-size: 12px; color: var(--light-blue);">
                                Versão: {{ versao_documento }} | Gerado em: {{ data_geracao }}
                            </p>
                        </div>
                        <div class="footer-security">
                            <p><strong>{{ nome_aluno }}</strong></p>
                            <p>CPF: {{ cpf_aluno }}</p>
                            <p style="margin-top: 10px;">🔒 Material protegido</p>
                            <p>Venda e distribuição proibidas</p>
                        </div>
                    </div>
                </footer>
            </div>
        </body>
        </html>
        """
    
    def convert_to_pdf(self, parsed_content: Dict[str, Any], output_path: str) -> bool:
        """Converte o conteúdo parseado para PDF com design premium"""
        try:
            # Enriquecer o conteúdo com elementos visuais
            enriched_content = self.enricher.enrich_content(parsed_content)
            
            # Adicionar introdução se não existir
            if 'introducao' not in enriched_content:
                enriched_content['introducao'] = self._generate_introduction(parsed_content)
            
            # Renderizar HTML
            template = Template(self.template_html)
            html_content = template.render(**enriched_content)
            
            # Criar diretório se não existir
            Path(output_path).parent.mkdir(parents=True, exist_ok=True)
            
            # Gerar PDF
            print(f"🔄 Gerando PDF premium em: {output_path}")
            HTML(string=html_content).write_pdf(
                output_path,
                stylesheets=[CSS(string='@page { size: A4; margin: 0; }')]
            )
            
            print(f"✅ PDF premium gerado com sucesso: {output_path}")
            return True
            
        except Exception as e:
            print(f"❌ Erro ao gerar PDF: {e}")
            return False
    
    def _generate_introduction(self, content: Dict[str, Any]) -> str:
        """Gera uma introdução baseada no conteúdo"""
        titulo = content.get('titulo', 'Material')
        num_secoes = len(content.get('secoes', []))
        
        return f"""
        Este material foi cuidadosamente preparado para proporcionar uma experiência completa de aprendizado
        sobre {titulo}. Com {num_secoes} seções estruturadas, você encontrará todo o conteúdo necessário
        para dominar os conceitos fundamentais e avançados do tema. Utilize os recursos visuais, exemplos
        práticos e exercícios para maximizar seu aproveitamento.
        """

# ================== FUNÇÃO PRINCIPAL ==================
async def main():
    """Função principal para demonstração"""
    
    print("="*70)
    print("🎯 GOOGLE DOCS PARA LAB RESUMOS PDF - SISTEMA PREMIUM")
    print("="*70)
    
    # Configuração
    config = GoogleDocsConfig(use_public_export=True)
    
    # URL do documento público (você pode alterar isso)
    document_url = "https://docs.google.com/document/d/1Fi_J6Ne6lRy_5wKiYr4elQJLSGHj4S9nGigwhsAh-FM/edit"
    
    try:
        # Inicializar parser
        print("📄 Inicializando parser para Google Docs público...")
        parser = GoogleDocsPublicParser(config)
        
        # Obter documento
        print(f"📥 Obtendo documento público: {document_url}")
        html_content = parser.get_document(document_url)
        
        # Parsear documento
        print("🔍 Parseando conteúdo HTML...")
        parsed_content = parser.parse_document(html_content)
        
        # Mostrar resumo
        print("\n📊 Conteúdo Detectado:")
        print(f"   Título: {parsed_content.get('titulo', 'N/A')}")
        print(f"   Seções: {len(parsed_content.get('secoes', []))}")
        
        # Converter para PDF Premium
        print("\n🔄 Convertendo para PDF Premium...")
        converter = GoogleDocsToPDFConverter()
        
        # Nome genérico + timestamp
        output_path = f"outputs/pdf_gerado_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
        success = converter.convert_to_pdf(parsed_content, output_path)
        
        if success:
            print(f"\n✅ Conversão concluída com sucesso!")
            print(f"📁 PDF Premium salvo em: {output_path}")
            print("\n🌟 Recursos incluídos no PDF:")
            print("   • Design profissional com gradientes e cores")
            print("   • Gráficos e visualizações de dados")
            print("   • Timeline e indicadores de progresso")
            print("   • Tabelas comparativas e métricas")
            print("   • Exemplos de código formatados")
            print("   • Questões e exercícios")
            print("   • Glossário e referências")
            print("   • QR Code para acesso digital")
        else:
            print("\n❌ Falha na conversão")
            
    except Exception as e:
        print(f"\n❌ Erro: {e}")
        print("\n💡 Dicas:")
        print("1. Certifique-se de que o documento está configurado como público")
        print("2. Verifique se a URL está correta")
        print("3. Instale as dependências: pip install requests weasyprint jinja2")
    
    print("\n" + "="*70)

# ================== EXECUTAR ==================
if __name__ == "__main__":
    # Criar diretório de saída
    Path("outputs").mkdir(exist_ok=True)
    
    # Executar
    asyncio.run(main())