"""
Pacote principal para o aplicativo Lab Resumos modularizado.

Este pacote contém todas as peças necessárias para carregar um
documento do Google Docs via API, converter sua estrutura em
elementos renderizáveis e gerar uma saída HTML/PDF utilizando
templates Jinja2 separados por tipo de bloco.

Organização dos módulos:

* config.py – constantes e definições globais.
* docs_client.py – cliente para as APIs do Google Docs e Drive.
* parser.py – parses o JSON retornado pela API em uma estrutura
  interna (ParsedDoc) contendo uma lista de elementos ordenados.
* renderer.py – renderiza um ParsedDoc em HTML e depois em PDF.
* app.py – ponto de entrada com CLI, aceita uma URL ou ID de
  documento e gera o PDF. Se nenhum argumento for fornecido,
  utiliza uma URL padrão definida em config.py.
"""

from .app import main, LabResumosAPIApp

__all__ = ["main", "LabResumosAPIApp"]