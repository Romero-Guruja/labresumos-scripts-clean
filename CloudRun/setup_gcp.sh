#!/bin/bash

# =====================================
# Script de Setup do Google Cloud Run
# LabResumos PDF Generator
# =====================================

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== LabResumos PDF Generator - Setup GCP ===${NC}"

# 1. Configurações do Projeto
echo -e "\n${YELLOW}Passo 1: Configurar Projeto${NC}"
echo "Digite o ID do seu projeto GCP (ex: labresumos-pdf):"
read PROJECT_ID

echo "Digite a região preferida (ex: us-central1, southamerica-east1):"
read REGION

# Setar projeto
gcloud config set project $PROJECT_ID

# 2. Habilitar APIs necessárias
echo -e "\n${YELLOW}Passo 2: Habilitando APIs necessárias...${NC}"
gcloud services enable run.googleapis.com \
    cloudbuild.googleapis.com \
    artifactregistry.googleapis.com \
    docs.googleapis.com \
    drive.googleapis.com \
    secretmanager.googleapis.com

# 3. Criar Service Account
echo -e "\n${YELLOW}Passo 3: Criando Service Account...${NC}"
SERVICE_ACCOUNT_NAME="labresumos-pdf-sa"
SERVICE_ACCOUNT_EMAIL="${SERVICE_ACCOUNT_NAME}@${PROJECT_ID}.iam.gserviceaccount.com"

gcloud iam service-accounts create $SERVICE_ACCOUNT_NAME \
    --display-name="LabResumos PDF Generator Service Account"

# 4. Adicionar permissões à Service Account
echo -e "\n${YELLOW}Passo 4: Configurando permissões...${NC}"

# Permissões para Cloud Run
gcloud projects add-iam-policy-binding $PROJECT_ID \
    --member="serviceAccount:${SERVICE_ACCOUNT_EMAIL}" \
    --role="roles/run.invoker"

# Permissões para Google Docs e Drive
gcloud projects add-iam-policy-binding $PROJECT_ID \
    --member="serviceAccount:${SERVICE_ACCOUNT_EMAIL}" \
    --role="roles/drive.viewer"

# 5. Criar chave da Service Account
echo -e "\n${YELLOW}Passo 5: Gerando chave da Service Account...${NC}"
gcloud iam service-accounts keys create service-account-key.json \
    --iam-account=$SERVICE_ACCOUNT_EMAIL

echo -e "${GREEN}✓ Chave salva em: service-account-key.json${NC}"

# 6. Criar repositório no Artifact Registry
echo -e "\n${YELLOW}Passo 6: Criando repositório de containers...${NC}"
gcloud artifacts repositories create labresumos-repo \
    --repository-format=docker \
    --location=$REGION \
    --description="LabResumos PDF Generator Docker images"

# 7. Configurar Docker para usar Artifact Registry
echo -e "\n${YELLOW}Passo 7: Configurando Docker...${NC}"
gcloud auth configure-docker ${REGION}-docker.pkg.dev

# 8. Criar Secret Manager para credenciais
echo -e "\n${YELLOW}Passo 8: Configurando Secret Manager...${NC}"

# Criar secret para a service account key
gcloud secrets create labresumos-sa-key \
    --data-file=service-account-key.json \
    --replication-policy="automatic"

# Dar permissão ao Cloud Run para acessar o secret
gcloud secrets add-iam-policy-binding labresumos-sa-key \
    --member="serviceAccount:${SERVICE_ACCOUNT_EMAIL}" \
    --role="roles/secretmanager.secretAccessor"

# 9. Criar arquivo de configuração
echo -e "\n${YELLOW}Passo 9: Criando arquivo de configuração...${NC}"

cat > deploy-config.env << EOF
PROJECT_ID=$PROJECT_ID
REGION=$REGION
SERVICE_ACCOUNT_EMAIL=$SERVICE_ACCOUNT_EMAIL
IMAGE_NAME=${REGION}-docker.pkg.dev/${PROJECT_ID}/labresumos-repo/pdf-generator
EOF

echo -e "${GREEN}✓ Configuração salva em: deploy-config.env${NC}"

# 10. Instruções finais
echo -e "\n${GREEN}========================================${NC}"
echo -e "${GREEN}Setup inicial concluído!${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "\nPróximos passos:"
echo -e "1. Revise o arquivo ${YELLOW}service-account-key.json${NC}"
echo -e "2. Execute ${YELLOW}./build-and-deploy.sh${NC} para fazer deploy"
echo -e "3. A Service Account criada é: ${YELLOW}${SERVICE_ACCOUNT_EMAIL}${NC}"
echo -e "\n${RED}IMPORTANTE:${NC}"
echo -e "- Mantenha o arquivo service-account-key.json seguro!"
echo -e "- Adicione service-account-key.json ao .gitignore"
echo -e "- Compartilhe os documentos do Google Docs com: ${YELLOW}${SERVICE_ACCOUNT_EMAIL}${NC}"