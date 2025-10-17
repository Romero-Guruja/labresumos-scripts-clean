"""
Configuração do projeto LabResumos com suporte a Azure Key Vault.
Este módulo gerencia a configuração de variáveis de ambiente e secrets do Azure Key Vault.
"""

import os
import logging
from typing import Optional, Dict, Any
from functools import lru_cache
from pydantic_settings import BaseSettings
from pydantic import Field, validator

# Azure Key Vault imports
from azure.identity import ClientSecretCredential
from azure.keyvault.secrets import SecretClient
from azure.core.exceptions import AzureError

# Configuração de logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


class AzureKeyVaultConfig:
    """Configuração para conexão com Azure Key Vault."""
    
    def __init__(self):
        self.vault_url = "https://labresumos-cofre.vault.azure.net/"
        self.container = "materiais-moodle"
        
        # Mapeamento de secrets do Key Vault para variáveis locais
        self.vault_secrets = {
            # Azure Storage
            "BLOB-CONNECT-STR": "azure_storage_connection_string",
            
            # Database
            "DB-SERVER": "db_server",
            "DB-NAME": "db_name", 
            "DB-UID": "db_uid",
            "DB-PASSWORD": "db_password",
            
            # DRM
            "DRM-SALT": "drm_salt",
            
            # SendGrid
            "API-KEY-SENDGRID": "sendgrid_api_key",
            
            # Template.io
            "API-KEY-TEMPLATE-IO": "template_io_api_key"
        }
    
    def get_secret_name(self, local_var: str) -> Optional[str]:
        """Retorna o nome do secret no Key Vault baseado na variável local."""
        for secret_name, local_name in self.vault_secrets.items():
            if local_name == local_var:
                return secret_name
        return None


class Settings(BaseSettings):
    """Configurações principais do projeto."""
    
    # Configurações Azure (obrigatórias)
    azure_tenant_id: str = Field(..., env="AZURE_TENANT_ID_LR")
    azure_client_id: str = Field(..., env="AZURE_CLIENT_ID_LR")
    azure_client_secret: str = Field(..., env="AZURE_CLIENT_SECRET_LR")
    
    # Configuração do Key Vault
    use_azure_key_vault: bool = Field(default=True, env="USE_AZURE_KEY_VAULT")
    
    # Azure Storage
    azure_storage_connection_string: Optional[str] = Field(
        default=None, 
        validation_alias="AZURE_STORAGE_CONNECTION_STRING"
    )
    
    # Database
    db_server: Optional[str] = Field(default=None, env="DB_SERVER")
    db_name: Optional[str] = Field(default=None, env="DB_NAME")
    db_uid: Optional[str] = Field(default=None, env="DB_UID")
    db_password: Optional[str] = Field(default=None, env="DB_PASSWORD")
    
    # DRM
    drm_salt: Optional[str] = Field(default=None, env="DRM_SALT")
    
    # SendGrid
    sendgrid_api_key: Optional[str] = Field(default=None, env="SENDGRID_API_KEY")
    
    # Template.io API Key - virá do Key Vault
    template_io_api_key: Optional[str] = Field(
        default=None, 
        validation_alias="TEMPLATE_IO_API_KEY"
    )
    
    @validator('azure_tenant_id', 'azure_client_id', 'azure_client_secret')
    def validate_azure_credentials(cls, v):
        """Valida se as credenciais Azure estão configuradas."""
        if not v:
            raise ValueError("Credenciais Azure são obrigatórias")
        return v
    
    class Config:
        env_file = ".env"
        case_sensitive = False


class AzureKeyVaultManager:
    """Gerenciador para operações com Azure Key Vault."""
    
    def __init__(self, settings: Settings):
        self.settings = settings
        self.config = AzureKeyVaultConfig()
        self._secrets_cache: Dict[str, str] = {}
        
    def _get_azure_credentials(self) -> Dict[str, str]:
        """Retorna as credenciais Azure para autenticação."""
        # Prioriza variáveis de ambiente, com fallback para settings
        tenant_id = os.getenv("AZURE_TENANT_ID_LR") or self.settings.azure_tenant_id
        client_id = os.getenv("AZURE_CLIENT_ID_LR") or self.settings.azure_client_id
        client_secret = os.getenv("AZURE_CLIENT_SECRET_LR") or self.settings.azure_client_secret
        
        logger.info(f"Usando Tenant ID: {tenant_id}")
        logger.info(f"Usando Client ID: {client_id}")
        
        return {
            "tenant_id": tenant_id,
            "client_id": client_id,
            "client_secret": client_secret
        }
    
    def _authenticate_with_azure(self) -> bool:
        """Autentica com Azure usando as credenciais configuradas."""
        try:
            credentials = self._get_azure_credentials()
            logger.info("Autenticando com Azure...")
            
            # Implementação real com Azure Identity
            self.credential = ClientSecretCredential(
                tenant_id=credentials["tenant_id"],
                client_id=credentials["client_id"],
                client_secret=credentials["client_secret"],
                additionally_allowed_tenants=["*"]  # Permite qualquer tenant
            )
            
            # Criar cliente do Key Vault
            self.secret_client = SecretClient(
                vault_url=self.config.vault_url,
                credential=self.credential
            )
            
            logger.info("Autenticação com Azure bem-sucedida")
            return True
            
        except Exception as e:
            logger.error(f"Erro na autenticação com Azure: {e}")
            return False
    
    def _get_secret_from_vault(self, secret_name: str) -> Optional[str]:
        """Recupera um secret específico do Azure Key Vault."""
        try:
            if not self._authenticate_with_azure():
                return None
            
            logger.info(f"Recuperando secret '{secret_name}' do Key Vault...")
            
            # Implementação real com Azure Key Vault
            secret = self.secret_client.get_secret(secret_name)
            secret_value = secret.value
            
            logger.info(f"Secret '{secret_name}' recuperado com sucesso")
            return secret_value
            
        except AzureError as e:
            logger.error(f"Erro do Azure ao recuperar secret '{secret_name}': {e}")
            return None
        except Exception as e:
            logger.error(f"Erro inesperado ao recuperar secret '{secret_name}': {e}")
            return None
    
    def load_secrets_from_vault(self) -> bool:
        """Carrega todos os secrets do Azure Key Vault."""
        if not self.settings.use_azure_key_vault:
            logger.info("Azure Key Vault desabilitado")
            return False
        
        try:
            logger.info("Iniciando carregamento de secrets do Azure Key Vault...")
            
            for vault_secret_name, local_var_name in self.config.vault_secrets.items():
                secret_value = self._get_secret_from_vault(vault_secret_name)
                
                if secret_value:
                    self._secrets_cache[local_var_name] = secret_value
                    # Define a variável de ambiente correspondente
                    os.environ[local_var_name.upper()] = secret_value
                    logger.info(f"Secret '{vault_secret_name}' carregado para '{local_var_name}'")
                else:
                    logger.warning(f"Falha ao carregar secret '{vault_secret_name}'")
            
            logger.info(f"Carregamento concluído. {len(self._secrets_cache)} secrets carregados")
            return True
            
        except Exception as e:
            logger.error(f"Erro ao carregar secrets do Key Vault: {e}")
            return False
    
    def get_secret(self, secret_name: str) -> Optional[str]:
        """Retorna um secret específico do cache."""
        return self._secrets_cache.get(secret_name)
    
    def refresh_secrets(self) -> bool:
        """Atualiza o cache de secrets do Key Vault."""
        logger.info("Atualizando cache de secrets...")
        self._secrets_cache.clear()
        return self.load_secrets_from_vault()
    
    def test_connection(self) -> bool:
        """Testa a conectividade com o Azure Key Vault."""
        try:
            logger.info("Testando conectividade com Azure Key Vault...")
            
            if not self._authenticate_with_azure():
                logger.error("Falha na autenticação com Azure")
                return False
            
            # Tenta recuperar um secret de teste para verificar conectividade
            test_secret = self._get_secret_from_vault("API-KEY-TEMPLATE-IO")
            
            if test_secret:
                logger.info("✅ Conectividade com Azure Key Vault testada com sucesso")
                return True
            else:
                logger.error("❌ Falha ao recuperar secret de teste")
                return False
                
        except Exception as e:
            logger.error(f"Erro ao testar conectividade: {e}")
            return False


@lru_cache()
def get_settings() -> Settings:
    """Retorna as configurações do projeto (com cache)."""
    return Settings()


@lru_cache()
def get_key_vault_manager() -> AzureKeyVaultManager:
    """Retorna o gerenciador do Key Vault (com cache)."""
    settings = get_settings()
    return AzureKeyVaultManager(settings)


def initialize_configuration() -> bool:
    """Inicializa a configuração do projeto carregando secrets do Key Vault."""
    try:
        logger.info("Inicializando configuração do projeto...")
        
        # Carrega configurações básicas
        settings = get_settings()
        logger.info("Configurações básicas carregadas")
        
        # Carrega secrets do Key Vault se habilitado
        if settings.use_azure_key_vault:
            key_vault_manager = get_key_vault_manager()
            
            # Testa conectividade primeiro
            logger.info("Testando conectividade com Azure Key Vault...")
            if not key_vault_manager.test_connection():
                logger.error("❌ Falha na conectividade com Azure Key Vault")
                return False
            
            # Carrega os secrets
            if not key_vault_manager.load_secrets_from_vault():
                logger.error("Falha ao carregar secrets do Key Vault")
                return False
        else:
            logger.info("Azure Key Vault desabilitado - usando variáveis de ambiente")
        
        logger.info("Configuração inicializada com sucesso")
        return True
        
    except Exception as e:
        logger.error(f"Erro ao inicializar configuração: {e}")
        return False


def get_config_value(key: str) -> Optional[str]:
    """Retorna o valor de uma configuração específica."""
    try:
        # Primeiro tenta buscar do Key Vault
        key_vault_manager = get_key_vault_manager()
        secret_value = key_vault_manager.get_secret(key)
        
        if secret_value:
            return secret_value
        
        # Se não encontrar no Key Vault, busca das variáveis de ambiente
        return os.getenv(key.upper())
        
    except Exception as e:
        logger.error(f"Erro ao buscar configuração '{key}': {e}")
        return None


# Exemplo de uso
if __name__ == "__main__":
    print("=== LabResumos Configuration ===")
    
    # Inicializa a configuração
    if initialize_configuration():
        print("✅ Configuração inicializada com sucesso")
        
        # Exemplo de como acessar configurações
        settings = get_settings()
        print(f"Tenant ID: {settings.azure_tenant_id}")
        print(f"Client ID: {settings.azure_client_id}")
        print(f"Use Key Vault: {settings.use_azure_key_vault}")
        
        # Exemplo de como acessar secrets
        template_api_key = get_config_value("template_io_api_key")
        if template_api_key:
            print(f"Template.io API Key: {template_api_key[:10]}...")
        else:
            print("Template.io API Key: Não encontrada")
        
        # Mostra status dos secrets carregados
        key_vault_manager = get_key_vault_manager()
        if settings.use_azure_key_vault:
            print(f"\n📊 Status dos Secrets:")
            for secret_name, local_name in key_vault_manager.config.vault_secrets.items():
                secret_value = key_vault_manager.get_secret(local_name)
                if secret_value:
                    print(f"   ✅ {secret_name}: {secret_value[:8]}...{secret_value[-4:]}")
                else:
                    print(f"   ❌ {secret_name}: Não carregado")
            
    else:
        print("❌ Falha ao inicializar configuração")
        exit(1)
