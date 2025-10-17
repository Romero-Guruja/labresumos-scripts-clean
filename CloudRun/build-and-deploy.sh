#!/bin/bash

# LabResumos PDF Generator - Build and Deploy Script
# Versão 2.0 - Com processor.py integrado

set -e  # Sair em caso de erro

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configurações
PROJECT_ID="labresumos-pdf"
REGION="southamerica-east1"
SERVICE_NAME="labresumos-pdf-generator"
REPO_NAME="labresumos-repo"

echo -e "${GREEN}🚀 LabResumos PDF Generator - Deploy Script${NC}"
echo "================================================"

# Verificar se estamos no diretório correto
if [ ! -f "app.py" ] || [ ! -f "processor.py" ]; then
    echo -e "${RED}❌ Erro: app.py ou processor.py não encontrados!${NC}"
    echo "Execute este script do diretório CloudRun/"
    exit 1
fi

# Verificar credenciais
if [ ! -f "service-account-key.json" ]; then
    echo -e "${RED}❌ Erro: service-account-key.json não encontrado!${NC}"
    exit 1
fi

# Configurar projeto GCP
echo -e "${YELLOW}📋 Configurando projeto GCP...${NC}"
gcloud config set project $PROJECT_ID

# Autenticar Docker
echo -e "${YELLOW}🔐 Autenticando Docker...${NC}"
gcloud auth configure-docker $REGION-docker.pkg.dev --quiet

# Tag da imagem com timestamp
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
IMAGE_TAG="$REGION-docker.pkg.dev/$PROJECT_ID/$REPO_NAME/pdf-generator:$TIMESTAMP"
IMAGE_LATEST="$REGION-docker.pkg.dev/$PROJECT_ID/$REPO_NAME/pdf-generator:latest"

echo -e "${YELLOW}🏗️  Building Docker image...${NC}"
echo "Tag: $IMAGE_TAG"

# Build com plataforma correta e sem cache
docker buildx build \
  --platform linux/amd64 \
  --no-cache \
  --tag $IMAGE_TAG \
  --tag $IMAGE_LATEST \
  --push \
  --progress=plain \
  .

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Erro no build da imagem Docker${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Imagem construída e enviada com sucesso${NC}"

# Deploy para Cloud Run
echo -e "${YELLOW}☁️  Fazendo deploy para Cloud Run...${NC}"

gcloud run deploy $SERVICE_NAME \
    --image=$IMAGE_LATEST \
    --platform=managed \
    --region=$REGION \
    --allow-unauthenticated \
    --memory=2Gi \
    --cpu=2 \
    --timeout=300 \
    --max-instances=10 \
    --min-instances=0 \
    --port=8080 \
    --set-env-vars="PYTHONUNBUFFERED=1"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Erro no deploy para Cloud Run${NC}"
    exit 1
fi

# Obter URL do serviço
SERVICE_URL=$(gcloud run services describe $SERVICE_NAME --region=$REGION --format='value(status.url)')

echo -e "${GREEN}✅ Deploy concluído com sucesso!${NC}"
echo "================================================"
echo -e "${GREEN}🌐 URL do serviço: $SERVICE_URL${NC}"

# Salvar URL
echo $SERVICE_URL > service-url.txt

# Testar o serviço
echo -e "${YELLOW}🧪 Testando o serviço...${NC}"

# Teste de health check
echo -e "${YELLOW}1. Health check...${NC}"
HEALTH_RESPONSE=$(curl -s "$SERVICE_URL/health")
echo "$HEALTH_RESPONSE" | python3 -m json.tool

# Teste do endpoint principal
echo -e "${YELLOW}2. Endpoint principal...${NC}"
MAIN_RESPONSE=$(curl -s "$SERVICE_URL/")
echo "$MAIN_RESPONSE" | python3 -m json.tool

# Teste opcional do processamento
echo -e "${YELLOW}3. Deseja testar o processamento de PDF? (s/n)${NC}"
read -r TEST_PDF

if [ "$TEST_PDF" = "s" ]; then
    echo -e "${YELLOW}Testando geração de PDF com documento de teste...${NC}"
    TEST_RESPONSE=$(curl -s "$SERVICE_URL/process-test")
    
    # Verificar se teve sucesso
    if echo "$TEST_RESPONSE" | grep -q '"success": true'; then
        echo -e "${GREEN}✅ Teste de PDF bem-sucedido!${NC}"
        # Extrair tamanho do PDF
        SIZE=$(echo "$TEST_RESPONSE" | grep -o '"size": [0-9]*' | grep -o '[0-9]*')
        echo "Tamanho do PDF gerado: $SIZE bytes"
    else
        echo -e "${RED}❌ Teste de PDF falhou${NC}"
        echo "$TEST_RESPONSE" | python3 -m json.tool
    fi
fi

echo "================================================"
echo -e "${GREEN}🎉 Deploy completo!${NC}"
echo ""
echo "Endpoints disponíveis:"
echo "  GET  $SERVICE_URL/"
echo "  GET  $SERVICE_URL/health"
echo "  POST $SERVICE_URL/generate-pdf"
echo "  POST $SERVICE_URL/generate-pdf-stream"
echo "  POST $SERVICE_URL/validate-document"
echo "  GET  $SERVICE_URL/process-test"
echo ""
echo "Para ver os logs:"
echo "  gcloud run services logs read $SERVICE_NAME --region=$REGION --limit=50"
echo ""