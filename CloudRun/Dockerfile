FROM python:3.11-slim

# Instalar dependências do sistema para WeasyPrint e outras bibliotecas
RUN apt-get update && apt-get install -y \
    # Dependências do WeasyPrint
    libpango-1.0-0 \
    libpangocairo-1.0-0 \
    libgdk-pixbuf-2.0-0 \
    libffi-dev \
    shared-mime-info \
    # Fontes necessárias
    fonts-liberation \
    fonts-dejavu-core \
    fonts-noto \
    # Dependências do matplotlib
    libfreetype6-dev \
    libpng-dev \
    # Ferramentas úteis
    wget \
    ca-certificates \
    curl \
    # Limpeza
    && rm -rf /var/lib/apt/lists/*

# Definir diretório de trabalho
WORKDIR /app

# Copiar e instalar dependências Python
COPY requirements.txt .
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir -r requirements.txt

# Copiar arquivos da aplicação
COPY app.py /app/app.py
COPY processor.py /app/processor.py
COPY service-account-key.json /app/service-account-key.json

# Copiar diretório de templates
COPY templates /app/templates

# Criar diretórios necessários
RUN mkdir -p /app/.labresumos_tpl /app/outputs

# Variáveis de ambiente
ENV PORT=8080
ENV PYTHONUNBUFFERED=1
ENV GOOGLE_APPLICATION_CREDENTIALS=/app/service-account-key.json
ENV PYTHONDONTWRITEBYTECODE=1

# Configurações do matplotlib para modo headless
ENV MPLBACKEND=Agg

# Expor porta
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:8080/health || exit 1

# Comando para iniciar com gunicorn
CMD ["gunicorn", \
     "--bind", "0.0.0.0:8080", \
     "--workers", "2", \
     "--threads", "8", \
     "--timeout", "300", \
     "--worker-class", "sync", \
     "--worker-tmp-dir", "/dev/shm", \
     "--access-logfile", "-", \
     "--error-logfile", "-", \
     "--log-level", "info", \
     "--chdir", "/app", \
     "app:app"]