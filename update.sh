#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_DIR="$HOME/CodeRED-Platform"

echo "========================================"
echo " Actualizando CodeRED Platform"
echo "========================================"

cd "$PROJECT_DIR"

echo ""
echo "[1/2] Descargando cambios de Git..."

git pull

echo ""
echo "[2/2] Reconstruyendo y levantando contenedores..."

docker compose up -d --build

echo ""
echo "========================================"
echo " CodeRED Platform actualizado correctamente"
echo "========================================"
echo ""