#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="${PROJECT_DIR:-$HOME/CodeRED-Platform}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

pause(){ read -r -p "Presiona Enter para continuar..." _; }
run_in_project() { [[ -d "$PROJECT_DIR" ]] || { echo "[ERROR] No existe $PROJECT_DIR"; return 1; }; cd "$PROJECT_DIR"; "$@"; }
get_env(){ local key="$1"; grep -E "^${key}=" .env 2>/dev/null | head -n1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/' || true; }
set_env(){ local key="$1" value="$2" tmp; tmp="$(mktemp)"; awk -v k="$key" -v v="$value" 'BEGIN{done=0} index($0,k"=")==1 {print k"="v; done=1; next} {print} END{if(!done) print k"="v}' .env > "$tmp"; mv "$tmp" .env; }
backup_env(){ local ts; ts="$(date +%Y%m%d-%H%M%S)"; cp .env ".env.backup-$ts"; echo "Backup creado: .env.backup-$ts"; }
generate_secret(){ command -v openssl >/dev/null || { echo "[ERROR] openssl no está instalado"; return 1; }; local v; v="$(openssl rand -hex 32)"; [[ "$v" =~ ^[0-9a-f]{64}$ ]] || { echo "[ERROR] secreto inválido generado"; return 1; }; printf '%s' "$v"; }

agent_menu(){
    while true; do
        clear 2>/dev/null || true
        echo "============================================================"
        echo "                 CodeRED Agent"
        echo "============================================================"
        echo "1) Ver estado"
        echo "2) Ver logs"
        echo "3) Reiniciar"
        echo "4) Reconstruir"
        echo "5) Probar healthcheck"
        echo "6) Consultar estado protegido"
        echo "7) Generar código de pairing"
        echo "8) Rotar token de API local"
        echo "9) Rotar clave de cifrado de forma segura"
        echo "10) Volver"
        echo
        read -r -p "Selecciona una opción: " option
        case "$option" in
            1) run_in_project docker compose ps codered-agent; pause ;;
            2) run_in_project docker compose logs -f codered-agent ;;
            3) run_in_project docker compose restart codered-agent; pause ;;
            4) run_in_project docker compose build codered-agent; run_in_project docker compose up -d --force-recreate codered-agent; pause ;;
            5) run_in_project curl --fail http://127.0.0.1:5680/healthz; echo; pause ;;
            6)
                run_in_project bash -lc 'token=$(grep -E "^CODERED_AGENT_LOCAL_API_TOKEN=" .env | head -n1 | cut -d= -f2- | sed -E "s/^\"(.*)\"$/\1/"); if [ -z "$token" ]; then echo "[ERROR] CODERED_AGENT_LOCAL_API_TOKEN no está configurado"; exit 1; fi; curl --fail --silent -H "Authorization: Bearer ${token}" http://127.0.0.1:5680/api/v1/status; echo'
                pause
                ;;
            7) run_in_project docker compose exec -T app php artisan integrations:n8n-pair-code; pause ;;
            8)
                run_in_project bash -lc 'set -Eeuo pipefail; command -v openssl >/dev/null; cp .env ".env.backup-$(date +%Y%m%d-%H%M%S)"; token=$(openssl rand -hex 32); [[ "$token" =~ ^[0-9a-f]{64}$ ]]; tmp=$(mktemp); awk -v k="CODERED_AGENT_LOCAL_API_TOKEN" -v v="$token" '\''BEGIN{done=0} index($0,k"=")==1 {print k"="v; done=1; next} {print} END{if(!done) print k"="v}'\'' .env > "$tmp"; mv "$tmp" .env; unset token; docker compose up -d --force-recreate codered-agent; curl --fail --silent http://127.0.0.1:5680/healthz >/dev/null; echo "Token de API local rotado correctamente. No se mostró el valor."'
                pause
                ;;
            9)
                echo "La rotación de la clave de cifrado requiere migrar integration.enc."
                echo "Use el comando oficial del agente cuando esté disponible."
                echo "Operación bloqueada para evitar dejar ilegible el estado cifrado."
                pause
                ;;
            10) return ;;
            *) echo "Opción inválida."; pause ;;
        esac
    done
}

pause_safe(){ pause; }

while true; do
    clear 2>/dev/null || true
    echo "============================================================"
    echo "              CodeRED Platform Manager"
    echo "============================================================"
    echo "1) Instalar CodeRED Platform"
    echo "2) Actualizar CodeRED Platform"
    echo "3) Ver estado de contenedores"
    echo "4) Ver logs"
    echo "5) Reiniciar un servicio"
    echo "6) Reparar permisos"
    echo "7) Ejecutar migraciones"
    echo "8) Limpiar cachés Laravel"
    echo "9) Ejecutar pruebas"
    echo "10) Backup manual de .env"
    echo "11) Abrir shell del contenedor app"
    echo "12) Información de Laravel"
    echo "13) Sincronizar tabla de ubigeos"
    echo "14) CodeRED Agent"
    echo "0) Salir"
    echo
    read -r -p "Selecciona una opción: " option

    case "$option" in
        1) bash "$SCRIPT_DIR/Install_CodeRED-Platform.sh"; pause ;;
        2) bash "$SCRIPT_DIR/update.sh"; pause ;;
        3) run_in_project docker compose ps; pause ;;
        4)
            echo "1) app  2) queue  3) scheduler  4) nginx  5) redis  6) postgres  7) todos  8) codered-agent"
            read -r -p "Servicio: " s
            case "$s" in 1) svc=app ;; 2) svc=queue ;; 3) svc=scheduler ;; 4) svc=nginx ;; 5) svc=redis ;; 6) svc=postgres ;; 7) svc="" ;; 8) svc=codered-agent ;; *) echo "Opción inválida"; pause; continue ;; esac
            if [[ -n "$svc" ]]; then run_in_project docker compose logs -f --tail=200 "$svc"; else run_in_project docker compose logs -f --tail=200; fi
            ;;
        5)
            echo "Servicios permitidos: app nginx scheduler redis postgres codered-agent"
            echo "Por seguridad, queue no se reinicia desde este menú."
            read -r -p "Servicio: " svc
            case "$svc" in app|nginx|scheduler|redis|postgres|codered-agent) run_in_project docker compose restart "$svc" ;; queue) echo "[AVISO] Reinicio de queue bloqueado para evitar cortar importaciones RUC." ;; *) echo "Servicio inválido." ;; esac
            pause
            ;;
        6) run_in_project docker compose exec -T app sh -lc 'chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache'; pause ;;
        7) run_in_project docker compose exec -T app php artisan migrate --force; pause ;;
        8) run_in_project docker compose exec -T app php artisan optimize:clear; pause ;;
        9) run_in_project docker compose exec -T app php artisan test; pause ;;
        10) ts="$(date +%Y%m%d_%H%M%S)"; run_in_project mkdir -p storage/manual-backups; run_in_project cp .env "storage/manual-backups/.env.$ts"; echo "Backup creado en storage/manual-backups/.env.$ts"; pause ;;
        11) run_in_project docker compose exec app sh ;;
        12) run_in_project docker compose exec -T app php artisan about; pause ;;
        13) run_in_project docker compose exec -T app php artisan ubigeos:sync; pause ;;
        14) run_in_project agent_menu ;;
        0) exit 0 ;;
        *) echo "Opción inválida."; pause ;;
    esac
done