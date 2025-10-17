"""
Cliente para as APIs Google Docs e Drive.

Este módulo fornece a classe ``DocsClient``, responsável por
autenticar utilizando Application Default Credentials ou uma
service account, e expor métodos para carregar o JSON de um
documento do Google Docs, bem como baixar blobs de imagens
referenciadas no documento via Drive.
"""

from __future__ import annotations

import os
import logging
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Dict, Optional

from google.oauth2 import service_account
from google.auth.transport.requests import AuthorizedSession, Request
from googleapiclient.discovery import build

from .config import SCOPES

logger = logging.getLogger(__name__)


def load_credentials() -> Any:
    """Carrega credenciais via ADC ou Service Account JSON.

    A função primeiro procura a variável de ambiente
    ``GOOGLE_APPLICATION_CREDENTIALS``. Caso exista e o arquivo
    indicado esteja presente, utiliza como service account. Caso
    contrário, delega para a detecção automática (ADC) via
    ``google.auth.default``, que por sua vez pode utilizar as
    credenciais obtidas via ``gcloud auth application-default login``.
    """
    key_path = os.getenv("GOOGLE_APPLICATION_CREDENTIALS")
    if key_path and Path(key_path).exists():
        creds = service_account.Credentials.from_service_account_file(
            key_path, scopes=SCOPES
        )
        return creds
    # Fallback para ADC no ambiente (ex.: gcloud auth application-default login)
    from google.auth import default

    creds, _ = default(scopes=SCOPES)
    return creds


@dataclass
class DocsClient:
    """Cliente para as APIs do Google Docs e Drive.

    Esta classe inicializa os serviços necessários de forma
    preguiçosa (lazy), apenas quando utilizados. Além disso,
    encapsula a autenticação e fornece métodos simples para
    recuperar um documento e baixar imagens.
    """

    creds: Any
    docs_service: Any = field(init=False)
    drive_service: Any = field(init=False)
    authed: AuthorizedSession = field(init=False)

    def __post_init__(self) -> None:
        self.docs_service = build(
            "docs", "v1", credentials=self.creds, cache_discovery=False
        )
        self.drive_service = build(
            "drive", "v3", credentials=self.creds, cache_discovery=False
        )
        self.authed = AuthorizedSession(self.creds)

    def get_document(self, doc_id: str) -> Dict[str, Any]:
        """Recupera o JSON completo de um documento do Google Docs.

        :param doc_id: ID do documento (porção entre ``/d/`` e ``/edit``).
        :return: dicionário representando o JSON retornado pela API.
        """
        return self.docs_service.documents().get(documentId=doc_id).execute()

    def download_drive_file(self, file_id: str) -> bytes:
        """Baixa o conteúdo de um arquivo do Drive a partir do seu ID.

        Destinado a blobs (como imagens) que sejam referenciadas via
        ``objectId`` em um documento. Não deve ser utilizado para
        ``contentUri`` temporário.

        :param file_id: ID do arquivo no Google Drive.
        :return: bytes com o conteúdo do arquivo.
        """
        url = f"https://www.googleapis.com/drive/v3/files/{file_id}?alt=media"
        resp = self.authed.get(url, timeout=60)
        resp.raise_for_status()
        return resp.content

    def fetch_content_uri(self, uri: str) -> bytes:
        """Baixa um blob através de um contentUri temporário.

        A API do Google Docs retorna ``contentUri`` para imagens
        embutidas. Estes URIs são temporários e necessitam de
        autenticação. Em caso de erro de autorização (401 ou 403),
        a função tenta atualizar as credenciais uma vez antes de
        falhar definitivamente.

        :param uri: URL temporária retornada pela API para a imagem.
        :return: bytes com o conteúdo da imagem.
        """
        last_resp: Optional[Any] = None
        for attempt in range(2):
            resp = self.authed.get(uri, timeout=60)
            last_resp = resp
            if resp.status_code in (401, 403) and attempt == 0:
                try:
                    self.creds.refresh(Request())
                except Exception:
                    pass
                continue
            resp.raise_for_status()
            return resp.content
        if last_resp is not None:
            last_resp.raise_for_status()
        raise RuntimeError("Falha ao baixar contentUri")