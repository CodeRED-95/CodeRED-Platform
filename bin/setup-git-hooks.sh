#!/bin/bash
# Setup Git Hooks para versionado automático
# Uso: ./bin/setup-git-hooks.sh [enable|disable]

set -e

HOOKS_DIR=".git/hooks"
HOOK_NAME="prepare-commit-msg"
HOOK_SOURCE="bin/git-hooks/$HOOK_NAME"
HOOK_DEST="$HOOKS_DIR/$HOOK_NAME"
ENABLE_FILE="$HOOKS_DIR/enable-version-bump"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Verificar si estamos en un repo git
if [[ ! -d ".git" ]]; then
    echo -e "${RED}Error: no es un repositorio git${NC}"
    exit 1
fi

ACTION="${1:-enable}"

case "$ACTION" in
    enable)
        echo -e "${BLUE}Instalando git hooks...${NC}"

        # Copiar hook
        mkdir -p "$HOOKS_DIR"
        if [[ -f "$HOOK_SOURCE" ]]; then
            cp "$HOOK_SOURCE" "$HOOK_DEST"
            chmod +x "$HOOK_DEST"
            echo -e "${GREEN}✓ Hook instalado: $HOOK_DEST${NC}"
        else
            echo -e "${RED}Error: archivo $HOOK_SOURCE no encontrado${NC}"
            exit 1
        fi

        # Crear archivo de activación
        touch "$ENABLE_FILE"
        echo -e "${GREEN}✓ Versionado automático habilitado${NC}"

        echo ""
        echo -e "${YELLOW}Próximos pasos:${NC}"
        echo "1. Usa commits con formato convencional:"
        echo -e "   ${BLUE}git commit -m 'feat: nueva característica'${NC}"
        echo -e "   ${BLUE}git commit -m 'fix: corregir bug'${NC}"
        echo -e "   ${BLUE}git commit -m 'BREAKING CHANGE: cambio incompatible'${NC}"
        echo ""
        echo "2. El hook detectará automáticamente el tipo y sugerirá:"
        echo -e "   ${BLUE}php artisan app:bump-version {major|minor|patch}${NC}"
        echo ""
        echo "3. Desactiva con: ./bin/setup-git-hooks.sh disable"
        ;;

    disable)
        echo -e "${BLUE}Desinstalando git hooks...${NC}"

        if [[ -f "$HOOK_DEST" ]]; then
            rm "$HOOK_DEST"
            echo -e "${GREEN}✓ Hook removido${NC}"
        fi

        if [[ -f "$ENABLE_FILE" ]]; then
            rm "$ENABLE_FILE"
            echo -e "${GREEN}✓ Versionado automático deshabilitado${NC}"
        fi
        ;;

    status)
        if [[ -f "$ENABLE_FILE" ]]; then
            echo -e "${GREEN}✓ Versionado automático: HABILITADO${NC}"
        else
            echo -e "${YELLOW}✗ Versionado automático: DESHABILITADO${NC}"
        fi
        ;;

    *)
        echo "Uso: $0 {enable|disable|status}"
        exit 1
        ;;
esac
