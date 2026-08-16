# CodeRED Platform

CodeRED Platform es el centro de control modular para la administración y consulta
de agencias Shalom, padrón RUC y datos DNI, con emisión de tokens de API (Sanctum)
e integraciones empresariales. Está construido con **Laravel 12**, **Livewire 3**,
**PostgreSQL 16**, **Redis 7** y **Docker Compose**, e integra **n8n** y el
**CodeRED Agent** para orquestar automatizaciones sin exponer secretos en los
workflows.

- **Dominio productivo:** `platform.codered.lat`
- **Versión:** ver `composer.json > extra.version` (fuente única) o `./bin/version.sh`

---

## Qué incluye

- **Agencias Shalom** — panel administrativo y vista pública; sincronización Shalom
  y copias de seguridad con restauración portable.
- **Padrón RUC** — consulta por panel y API, administrado con backup/restore en
  segundo plano.
- **DNI** — consulta de identidad con proveedor intercambiable.
- **Tokens de API** — solicitudes con aprobación administrativa, abilities Sanctum,
  rate limiting y auditoría.
- **Integraciones** — n8n vía CodeRED Agent (Pairing, Discovery, Heartbeat,
  Challenge/Response, Capability Registry) y extensiones de navegador.
- **API REST v1** documentada en `/docs` (agencias, RUC, DNI, Shalom Recordar y CodeRED Mobile).

---

## Arquitectura

CodeRED Platform conserva la autoridad de usuarios, permisos, tokens, auditoría y
registro de capacidades. El **CodeRED Agent** mantiene la conexión persistente, el
estado cifrado local y la comunicación firmada; **n8n** y otros conectores lo
consumen como cliente local, sin ver el `shared_secret`.

```text
codered-nginx ── codered-app ── codered-postgres / codered-redis
                    │
    codered-queue · codered-queue-ruc-backups · codered-scheduler
                    │
codered-n8n ── codered-agent ── CodeRED Platform
shalom-extractor (red interna, sin puertos publicados)
```

Detalle en [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) y las decisiones de diseño
en [docs/adr/README.md](docs/adr/README.md).

---

## Requisitos

- Git, Docker Engine y Docker Compose v2.
- OpenSSL para generar secretos.
- DNS público para `APP_URL` (y `CODERED_AGENT_PUBLIC_URL` si se expone el agente).
- PostgreSQL y Redis se proveen como servicios del compose.
- Puertos por defecto: `8090` (Nginx) y `127.0.0.1:5680` (CodeRED Agent).

El desarrollo se hace sobre el host por VS Code Remote SSH, ejecutando los comandos
con `docker compose exec -T app ...`.

---

## Instalación

```bash
git clone https://github.com/CodeRED-95/CodeRED-Platform.git
cd CodeRED-Platform
chmod +x Install_CodeRED-Platform.sh
./Install_CodeRED-Platform.sh
```

El instalador configura Laravel, PostgreSQL, Redis, el administrador inicial y,
opcionalmente, el CodeRED Agent y n8n (generando secretos con `openssl rand -hex 32`
sin mostrarlos). Guía completa en [docs/INSTALL.md](docs/INSTALL.md).

## Actualización

```bash
./update.sh
```

Respalda el `.env`, aplica `git pull --ff-only`, añade variables nuevas sin
sobrescribir secretos, reconstruye solo los servicios necesarios y ejecuta
migraciones y cachés sin borrar volúmenes. Ver
[docs/DEPLOYMENT_SAFE.md](docs/DEPLOYMENT_SAFE.md).
La guía operativa de recuperación ante desastres está en
[docs/DISASTER_RECOVERY.md](docs/DISASTER_RECOVERY.md).

## Docker

```bash
docker compose up -d          # levantar
docker compose ps             # estado
docker compose logs -f app    # logs
```

Servicios: `app`, `nginx`, `postgres`, `redis`, `queue`, `queue-ruc-backups`,
`scheduler`, `codered-agent`, `codered-n8n` y `shalom-extractor`. Referencia en
[docs/DOCKER.md](docs/DOCKER.md) y variables en
[docs/ENVIRONMENT.md](docs/ENVIRONMENT.md).

---

## Versionado

La versión sigue **SemVer** y su **fuente única** es `composer.json > extra.version`.
De ahí la derivan `config/version.php`, `config/app.php`, el footer del panel,
`GET /api/v1/version`, el header `X-Application-Version` y los scripts. **No se usa
`APP_VERSION` en `.env`** (retirado en 3.5.0).

```bash
./bin/version.sh                                     # consultar (host)
docker compose exec -T app php artisan app:version   # consultar (contenedor)
docker compose exec -T app php artisan app:bump-version {patch|minor|major}
```

Detalle en [docs-dev/VERSIONING.md](docs-dev/VERSIONING.md).

---

## Estructura del repositorio

```text
app/                     Código de la aplicación (Modules/, Livewire/, Http/, …)
config/                  Configuración de Laravel
database/                Migraciones, seeders y factories
docs/                    Documentación detallada (ver índice abajo)
docs-dev/                Versionado y guías de desarrollo
docs-ruc/                Rendimiento y formato de backups RUC
packages/                Agente, extensiones y nodo n8n (versionado propio)
routes/                  Rutas web y API
resources/               Vistas Blade, JS y CSS
Install_CodeRED-Platform.sh · update.sh · CodeRED.sh   Scripts operativos
bin/version.sh           Lectura de la versión desde el host
```

## Módulos principales

- **Ruc** — padrón, consulta y backup/restore. Ver [docs/RUC_MODULE.md](docs/RUC_MODULE.md).
- **Agencies** — agencias Shalom, sincronización y backup/restore. Ver [docs/AGENCIES.md](docs/AGENCIES.md).
- **Shalom** — extractor y sincronización. Ver [app/Modules/Shalom/README.md](app/Modules/Shalom/README.md).
- **ShalomRecordar** — API de la extensión de captura. Ver [app/Modules/ShalomRecordar/README.md](app/Modules/ShalomRecordar/README.md).

## Packages y extensiones

Cada uno mantiene su propio versionado y documentación:

| Paquete | Descripción | Versión |
|---|---|---|
| [packages/codered-agent](packages/codered-agent/README.md) | Daemon de integración (Pairing, Discovery, Heartbeat) | 1.0.0 |
| [packages/n8n-nodes-codered](packages/n8n-nodes-codered/README.md) | Nodo n8n de CodeRED | 1.1.0 |
| [packages/codered-chrome-extension](packages/codered-chrome-extension/README.md) | Extensión "Buscador Shalom Control" | 2.3.5 |
| [packages/shalom-recordar-extension](packages/shalom-recordar-extension/README.md) | Extensión "Shalom Recordar" | 2.7.16 |
| [packages/ruc-tools](packages/ruc-tools/README.md) | Herramienta CLI local para backups RUC | 2.3.0 |

---

## Desarrollo

```bash
docker compose exec -T app composer test     # pruebas
docker compose exec -T app composer lint      # formato (Pint)
docker compose exec -T app composer analyse   # análisis estático (PHPStan)
```

> Nunca ejecutes la suite con la configuración cacheada: `php artisan config:clear`
> antes de correr tests (los tests apuntarían a la base de desarrollo). El
> `tests/bootstrap.php` lo aborta como salvaguarda.

Guías: [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) · [docs/TESTING.md](docs/TESTING.md)
· [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md).

---

## Documentación adicional

Índice completo en
[docs/DOCUMENTATION_STRUCTURE.md](docs/DOCUMENTATION_STRUCTURE.md).

**Inicio y operación**
- [Instalación](docs/INSTALL.md) · [Guía de desarrollo](docs/DEVELOPMENT.md)
- [Docker](docs/DOCKER.md) · [Entorno y variables](docs/ENVIRONMENT.md)
- [Despliegue seguro](docs/DEPLOYMENT_SAFE.md) - [Workers de larga duracion](docs/WORKERS.md) - [Declaraciones: borrado y copias](docs/DECLARACIONES_SEGURIDAD.md) · [Solución de problemas](docs/TROUBLESHOOTING.md)

**Arquitectura y datos**
- [Arquitectura](docs/ARCHITECTURE.md) · [Base de datos](docs/DATABASE.md) · [Seeders](docs/SEEDERS.md)
- [Decisiones de diseño (ADR)](docs/adr/README.md) · [Design System](docs/DESIGN_SYSTEM.md)

**Seguridad**
- [Seguridad](docs/SECURITY.md) · [Autorización](docs/AUTHORIZATION.md) · [Auditoría](docs/AUDIT.md)

**API**
- [Guía de APIs](docs/API.md) · [Autenticación](docs/api/authentication.md)
- [Agencias](docs/api/agencies.md) · [RUC](docs/api/ruc.md) · [DNI](docs/api/dni.md)
- [Tokens](docs/api/tokens.md) · [Errores](docs/api/errors.md) · [Sincronización](docs/api/synchronization.md)

**RUC**
- [Módulo RUC](docs/RUC_MODULE.md) · [Sistema de backup/restore](app/Modules/Ruc/BACKUP_SYSTEM.md)
- [Rendimiento](docs-ruc/PERFORMANCE.md) · [Formato de backup](docs-ruc/BACKUP_FORMAT.md)

**Integraciones**
- [CodeRED Agent: arquitectura](docs/agent/architecture.md) · [instalación](docs/agent/installation.md) · [seguridad](docs/agent/security.md)
- [Protocolo de integración](docs/integrations/protocol.md) · [Conector n8n](docs/integrations/n8n-connector.md)

---

## Licencia

Uso interno de CodeRED. Consulta [docs/SECURITY.md](docs/SECURITY.md) para el
manejo responsable de secretos y datos.
