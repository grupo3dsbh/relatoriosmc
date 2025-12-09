#!/bin/bash
# Script para corrigir permissões após deploy/pull

# Define o usuário do servidor web
WEB_USER="mcaqu3731"

# Diretório base
BASE_DIR="/home/mcaquabeat.hotlead.es/public_html/ranking"

echo "🔧 Corrigindo permissões..."

# Ajusta dono da pasta data e arquivos
sudo chown -R $WEB_USER:$WEB_USER $BASE_DIR/data/
sudo chmod 755 $BASE_DIR/data/
sudo chmod 664 $BASE_DIR/data/*.json

# Ajusta dono das pastas de upload
sudo chown -R $WEB_USER:$WEB_USER $BASE_DIR/uploads/
sudo chmod 755 $BASE_DIR/uploads/
sudo chmod 644 $BASE_DIR/uploads/vendas/*.csv 2>/dev/null || true
sudo chmod 644 $BASE_DIR/uploads/promotores/*.csv 2>/dev/null || true

echo "✅ Permissões corrigidas!"
echo ""
echo "Verificando config.json:"
ls -la $BASE_DIR/data/config.json
