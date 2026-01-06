#!/bin/bash

# Cores para output
GREEN='\033[0;32m'
NC='\033[0m'

echo -e "${GREEN}=== Iniciando Configuração do Servidor FlowHedge ===${NC}"

# 1. Atualizar Sistema
echo -e "${GREEN}>>> Atualizando pacotes do sistema...${NC}"
yum update -y
yum install -y git curl unzip

# 2. Instalar Docker
echo -e "${GREEN}>>> Instalando Docker...${NC}"
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh
systemctl start docker
systemctl enable docker

# 3. Instalar Docker Compose
echo -e "${GREEN}>>> Instalando Docker Compose...${NC}"
curl -L "https://github.com/docker/compose/releases/download/v2.24.1/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose
ln -s /usr/local/bin/docker-compose /usr/bin/docker-compose

# 4. Criar pasta do projeto
echo -e "${GREEN}>>> Configurando diretórios...${NC}"
mkdir -p /var/www/flowpage
cd /var/www/flowpage

# 5. Mensagem Final
echo -e "${GREEN}=== Instalação Concluída! ===${NC}"
echo "Agora você pode clonar o repositório ou copiar os arquivos."
docker --version
docker-compose --version
