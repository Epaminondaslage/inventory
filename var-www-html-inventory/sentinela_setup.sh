#!/bin/bash

echo "=============================================="
echo " SENTINELA - SETUP COMPLETO DE INFRA"
echo "=============================================="

API_BASE="/var/www/html/api"
INVENTORY_PATH="/var/www/html/inventory"
GROUP_NAME="sentinela"

# ==============================
# 1️⃣ Criar grupo
# ==============================
if ! getent group $GROUP_NAME > /dev/null 2>&1; then
    echo "Criando grupo $GROUP_NAME..."
    sudo groupadd $GROUP_NAME
else
    echo "Grupo $GROUP_NAME já existe."
fi

# ==============================
# 2️⃣ Adicionar usuários se existirem
# ==============================
USERS=("planeta" "epaminondas" "popo" "www-data")

for USER in "${USERS[@]}"; do
    if id "$USER" &>/dev/null; then
        echo "Adicionando $USER ao grupo $GROUP_NAME..."
        sudo usermod -aG $GROUP_NAME $USER
    fi
done

# ==============================
# 3️⃣ Criar estrutura API
# ==============================

echo "Criando estrutura de diretórios..."

sudo mkdir -p $API_BASE/inventory
sudo mkdir -p $API_BASE/inventory_central

# ==============================
# 4️⃣ Criar estrutura painel
# ==============================

sudo mkdir -p $INVENTORY_PATH/css
sudo mkdir -p $INVENTORY_PATH/img

# ==============================
# 5️⃣ Ajustar dono/grupo
# ==============================

echo "Aplicando owner www-data:sentinela..."

sudo chown -R www-data:$GROUP_NAME $API_BASE
sudo chown -R www-data:$GROUP_NAME $INVENTORY_PATH

# ==============================
# 6️⃣ Permissões
# ==============================

echo "Aplicando permissões..."

# Diretórios → 770
sudo find $API_BASE -type d -exec chmod 770 {} \;
sudo find $INVENTORY_PATH -type d -exec chmod 770 {} \;

# PHP → 660
sudo find $API_BASE -type f -name "*.php" -exec chmod 660 {} \;
sudo find $INVENTORY_PATH -type f -name "*.php" -exec chmod 660 {} \;

# SH → 770
sudo find $API_BASE -type f -name "*.sh" -exec chmod 770 {} \;

# CSS / IMG → 660
sudo find $INVENTORY_PATH -type f -name "*.css" -exec chmod 660 {} \;
sudo find $INVENTORY_PATH -type f -name "*.jpg" -exec chmod 660 {} \;
sudo find $INVENTORY_PATH -type f -name "*.png" -exec chmod 660 {} \;

echo "----------------------------------------------"
echo "Estrutura criada e permissões aplicadas."
echo "Servidor configurado como:"
echo " - Agente (/api/inventory)"
echo " - Coletor (/api/inventory_central)"
echo " - Painel (/inventory)"
echo ""
echo "IMPORTANTE: Faça logout/login para atualizar grupos."
echo "----------------------------------------------"