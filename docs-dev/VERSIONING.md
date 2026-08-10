# Sistema de versionado

CodeRED Platform usa **versionado semántico** con una **fuente única de verdad**.

---

## Fuente única de verdad

La versión se define en **un solo sitio**:

```json
// composer.json
{
    "extra": {
        "version": "3.5.0"
    }
}
```

Nada más la declara. Todo lo que necesita conocerla la deriva de ahí:

| Consumidor | Cómo la obtiene |
|---|---|
| `config/version.php` (`version.current`) | `App\Support\Version::current()` |
| `config/app.php` (`app.version`) | `App\Support\Version::current()` |
| Footer y cabecera del panel | `config('version.current')` |
| `GET /api/v1/version` y header `X-Application-Version` | `config('version.current')` |
| `php artisan app:version` | `config('version.current')` |
| Metadatos de backups RUC | `config('version.current')` |
| `update.sh`, instaladores, CI | `./bin/version.sh` |

`App\Support\Version` lee `composer.json` una sola vez por proceso y cachea el
resultado. Funciona sin contenedor de servicios, porque los archivos de
configuración se evalúan antes de que la aplicación arranque.

### `APP_VERSION` fue retirado (3.5.0)

Antes la versión vivía a la vez en `.env`, `.env.example`, `config/version.php`
y `config/app.php`, y **la copia del `.env` ganaba sobre el código**. Un servidor
con `APP_VERSION=3.2.0` heredado reportaba esa versión aunque el código
desplegado fuese 3.4.0, en el footer, en la API y en los metadatos de backup.

Ahora la variable **no se consulta**. Si sigue en un `.env` antiguo, se ignora y
`./update.sh` la elimina en la siguiente actualización. No hay que editar `.env`
para versionar, y `.env.example` no define la versión a propósito: sería una
segunda fuente de verdad.

---

## Versionado semántico

Formato `MAJOR.MINOR.PATCH`:

- **MAJOR** (`x.0.0`) — Cambios **incompatibles**: se elimina o cambia el
  contrato de un endpoint, se retira una variable de entorno de la que dependían
  las instalaciones, migraciones que exigen intervención manual.
- **MINOR** (`0.x.0`) — **Nueva funcionalidad compatible**: un módulo nuevo, un
  endpoint añadido, una migración que corre sola sin romper nada existente.
- **PATCH** (`0.0.x`) — **Correcciones** compatibles: bugs, rendimiento,
  seguridad, documentación con cambio de comportamiento.

Ejemplos reales del proyecto:

| Cambio | Bump | Resultado |
|---|---|---|
| Corregir validación de RUC | PATCH | `3.4.0` → `3.4.1` |
| Restaurar esquema de `api_token_requests` | MINOR | `3.3.0` → `3.4.0` |
| Eliminar el sistema de importación TXT | MAJOR | `2.x` → `3.0.0` |

---

## Consultar la versión

```bash
# Desde el host, sin levantar Laravel ni entrar en un contenedor
./bin/version.sh
# 3.5.0

# Ruta del archivo que la define
./bin/version.sh --source
# /ruta/al/proyecto/composer.json

# Dentro del contenedor
docker compose exec -T app php artisan app:version

# Por HTTP
curl -s https://platform.codered.lat/api/v1/version
# {"success":true,"data":{"version":"3.5.0","api_version":"v1","environment":"production"}}
```

`bin/version.sh` no depende de PHP ni de `jq`: los usa si están disponibles y,
si no, extrae el valor con `sed`/`grep`. Eso permite llamarlo desde CI o desde
un servidor sin PHP en el `PATH`.

---

## Incrementar la versión

Un solo comando, un solo archivo modificado (más el CHANGELOG):

```bash
docker compose exec -T app php artisan app:bump-version patch --reason="Corregir validación de RUC"
docker compose exec -T app php artisan app:bump-version minor --reason="Nuevo módulo de reportes"
docker compose exec -T app php artisan app:bump-version major --reason="Retirada del endpoint v0"
```

Para ver el resultado sin escribir nada:

```bash
docker compose exec -T app php artisan app:bump-version minor --dry-run
```

El comando:

1. Lee la versión actual de `composer.json > extra.version`.
2. Calcula la siguiente según SemVer y valida el formato.
3. Escribe **solo** `composer.json` (preservando el resto del archivo).
4. Añade una entrada nueva en `CHANGELOG.md`, encima de la última versión.

Ya **no** toca `config/version.php` ni `config/app.php`: esos archivos derivan
del valor, así que no hay nada que sincronizar. (En 3.4.0 esa sincronización por
expresión regular llegó a corromper ambos archivos, escribiendo `.4.0')` en vez
de la versión completa, porque `"$1"` seguido de un dígito se interpretaba como
la retrorreferencia `$13`.)

---

## Conventional Commits y el hook

El hook `prepare-commit-msg` detecta el tipo de cambio y **sugiere** el bump; no
lo aplica solo, para que la decisión siga siendo explícita.

```bash
./bin/setup-git-hooks.sh enable    # instalar
./bin/setup-git-hooks.sh status    # ver estado
./bin/setup-git-hooks.sh disable   # desinstalar
```

| Tipo de commit | Sugerencia |
|---|---|
| `feat:` | MINOR |
| `fix:` | PATCH |
| `perf:` | PATCH |
| `BREAKING CHANGE:` | MAJOR |
| `docs:`, `style:`, `refactor:`, `test:`, `chore:` | Sin bump |

### Flujo completo

```bash
# 1. Cambios y commit con tipo
git add app/Modules/Ruc/Services/MyService.php
git commit -m "feat: agregar streaming de archivos RUC"
# El hook sugiere: php artisan app:bump-version minor

# 2. Bump
docker compose exec -T app php artisan app:bump-version minor --reason="Streaming de archivos RUC"

# 3. Revisar y confirmar (solo dos archivos cambian)
git status          # M composer.json   M CHANGELOG.md
git add composer.json CHANGELOG.md
git commit --amend --no-edit

# 4. Publicar
git push origin main
git tag v3.5.0 && git push origin v3.5.0
```

---

## Despliegue

`./update.sh` gestiona la versión sin intervención:

1. Lee la versión **antes** del `git pull` (`read_project_version`).
2. La vuelve a leer **después** e informa del salto: `Versión: 3.4.0 -> 3.5.0`.
3. Elimina `APP_VERSION` de un `.env` heredado (`migrate_legacy_app_version`).
4. Reconstruye la caché de configuración, de modo que `config('version.current')`
   queda fijada al valor nuevo.
5. Verifica que la app dentro del contenedor reporta exactamente la misma
   versión que `composer.json`; si no coinciden, avisa con el comando de
   corrección.

El paso 5 es el que detecta el fallo clásico: contenedor sin recrear o
`config:cache` obsoleta mostrando una versión que ya no es la desplegada.

---

## Compatibilidad con instalaciones existentes

- `composer.json > extra.version` ya existía; ninguna instalación necesita
  archivos nuevos.
- Un `.env` con `APP_VERSION` sigue funcionando: la variable simplemente se
  ignora y se retira en la siguiente actualización.
- La ruta `GET /api/v1/version`, el header `X-Application-Version` y
  `php artisan app:version` mantienen su contrato exacto.

---

## Integración con CI/CD

```yaml
# .github/workflows/release.yml
name: Release
on:
  push:
    branches: [main]

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Leer versión de la fuente de verdad
        run: echo "VERSION=$(./bin/version.sh)" >> "$GITHUB_ENV"

      - name: Crear release si el tag no existe
        run: |
          git fetch --tags
          if ! git rev-parse "v$VERSION" >/dev/null 2>&1; then
            gh release create "v$VERSION" --title "Release $VERSION" --generate-notes
          fi
```

---

## Preguntas frecuentes

**¿Dónde cambio la versión a mano?**
En ningún sitio. Use `app:bump-version`. Si aun así edita `composer.json`
directamente, respete `MAJOR.MINOR.PATCH`: `bin/version.sh` y `Version::current()`
rechazan cualquier valor que no sea SemVer.

**¿Puedo forzar una versión por entorno?**
No, y es deliberado. Esa capacidad (`APP_VERSION`) era la causa de que la app
reportara versiones falsas.

**¿Qué pasa si `composer.json` no se puede leer?**
`Version::current()` devuelve `0.0.0` para que la aplicación arranque en vez de
romperse al cargar la configuración. Un `0.0.0` en el footer o en la API indica
instalación corrupta.

**¿La extensión Chrome comparte esta versión?**
No. `packages/codered-chrome-extension` mantiene su propio ciclo en su
`package.json`/`manifest.json`.

---

## Véase también

- [CHANGELOG.md](../CHANGELOG.md) — Historial de versiones
- [docs/ENVIRONMENT.md](../docs/ENVIRONMENT.md) — Variables de entorno
- [Semantic Versioning](https://semver.org) — Especificación oficial
- [Conventional Commits](https://www.conventionalcommits.org) — Formato de commits
