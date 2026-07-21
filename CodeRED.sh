#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="${PROJECT_DIR:-$HOME/CodeRED-Platform}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

pause(){ read -r -p "Presiona Enter para continuar..." _; }

run_in_project() {
    [[ -d "$PROJECT_DIR" ]] || { echo "[ERROR] No existe $PROJECT_DIR"; return 1; }
    cd "$PROJECT_DIR"
    "$@"
}

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
    echo "0) Salir"
    echo
    read -r -p "Selecciona una opción: " option

    case "$option" in
        1)
            bash "$SCRIPT_DIR/Install_CodeRED-Platform.sh"
            pause
            ;;
        2)
            bash "$SCRIPT_DIR/Update.sh"
            pause
            ;;
        3)
            run_in_project docker compose ps
            pause
            ;;
        4)
            echo "1) app  2) queue  3) scheduler  4) nginx  5) redis  6) postgres  7) todos"
            read -r -p "Servicio: " s
            case "$s" in
                1) svc=app ;; 2) svc=queue ;; 3) svc=scheduler ;;
                4) svc=nginx ;; 5) svc=redis ;; 6) svc=postgres ;;
                7) svc="" ;; *) echo "Opción inválida"; pause; continue ;;
            esac
            if [[ -n "$svc" ]]; then
                run_in_project docker compose logs -f --tail=200 "$svc"
            else
                run_in_project docker compose logs -f --tail=200
            fi
            ;;
        5)
            echo "Servicios permitidos: app nginx scheduler redis postgres"
            echo "Por seguridad, queue no se reinicia desde este menú."
            read -r -p "Servicio: " svc
            case "$svc" in
                app|nginx|scheduler|redis|postgres)
                    run_in_project docker compose restart "$svc"
                    ;;
                queue)
                    echo "[AVISO] Reinicio de queue bloqueado para evitar cortar importaciones RUC."
                    ;;
                *)
                    echo "Servicio inválido."
                    ;;
            esac
            pause
            ;;
        6)
            run_in_project docker compose exec -T app sh -lc \
                'chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache'
            pause
            ;;
        7)
            run_in_project docker compose exec -T app php artisan migrate --force
            pause
            ;;
        8)
            run_in_project docker compose exec -T app php artisan optimize:clear
            pause
            ;;
        9)
            run_in_project docker compose exec -T app php artisan test
            pause
            ;;
        10)
            ts="$(date +%Y%m%d_%H%M%S)"
            run_in_project mkdir -p storage/manual-backups
            run_in_project cp .env "storage/manual-backups/.env.$ts"
            echo "Backup creado en storage/manual-backups/.env.$ts"
            pause
            ;;
        11)
            run_in_project docker compose exec app sh
            ;;
        12)
            run_in_project docker compose exec -T app php artisan about
            pause
            ;;
        0)
            exit 0
            ;;
        *)
            echo "Opción inválida."
            pause
            ;;
    esac
done
