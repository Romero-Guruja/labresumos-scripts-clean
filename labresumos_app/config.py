"""
Configurações globais do aplicativo Lab Resumos.

Aqui concentramos constantes e variáveis de configuração que
poderão ser alteradas futuramente sem precisar editar o código
principal. Por exemplo, definimos os escopos utilizados pelas
APIs do Google, e a URL padrão do documento a ser processado
caso o usuário não forneça um argumento explícito.
"""

from pathlib import Path

# URL padrão do Google Docs de onde serão extraídos os resumos.
# Caso o aplicativo seja executado sem argumentos, será usada esta
# URL para buscar o conteúdo. Mantenha este valor configurável
# aqui para facilitar futuras alterações.
DEFAULT_DOC_URL = (
    "https://docs.google.com/document/d/1uH48T6uJAeUqeoYvymLQpZ_r8CO5ZZX0ZrK2wt9Cq8Q/edit?tab=t.0"
)

# Escopos de acesso às APIs do Google. Para ler um documento do
# Google Docs e baixar imagens do Drive, são necessários os
# escopos de leitura de documentos e leitura de arquivos do Drive.
DOCS_SCOPE = "https://www.googleapis.com/auth/documents.readonly"
DRIVE_SCOPE = "https://www.googleapis.com/auth/drive.readonly"
SCOPES = [DOCS_SCOPE, DRIVE_SCOPE]

# Pasta padrão onde os PDFs e arquivos auxiliares serão gerados.
DEFAULT_OUTPUT_DIR = Path.cwd() / "output"

__all__ = ["DEFAULT_DOC_URL", "SCOPES", "DEFAULT_OUTPUT_DIR"]