# Instalación

## Requisitos

| Requisito | Valor |
|---|---|
| Docker | Requerido |
| Docker Compose | Requerido |
| Git | Requerido para clonar el repositorio |

## Instalación

```bash
git clone https://github.com/CodeRED-95/CodeRED-Platform.git
cd CodeRED-Platform
cp .env.example .env
docker compose up -d --build
```

Después de levantar los contenedores, el proyecto completa automáticamente su bootstrap:

- genera `APP_KEY` solo si está vacía;
- limpia la caché de configuración cuando corresponde;
- ejecuta migraciones con `--force`;
- ejecuta seeders con `--force`;
- crea `storage:link` si falta;
- compila el frontend si no existe `public/build/manifest.json`;
- instala dependencias PHP o frontend solo si faltan los artefactos esperados.

No debes ejecutar manualmente:

- `config:clear`
- `optimize:clear`
- `migrate`
- `db:seed`
- `storage:link`
- `key:generate`

## Variables importantes

### Aplicación

| Variable | Descripción | Ejemplo |
|---|---|---|
| `APP_URL` | URL pública del proyecto. Debe coincidir con el puerto expuesto por Nginx. | `http://localhost:8090` |
| `APP_TIMEZONE` | Zona horaria de la aplicación. | `America/Lima` |
| `APP_LOCALE` | Idioma principal. | `es` |

### Base de datos

| Variable | Descripción | Ejemplo |
|---|---|---|
| `DB_DATABASE` | Nombre de la base PostgreSQL. | `codered` |
| `DB_USERNAME` | Usuario PostgreSQL. | `codered` |
| `DB_PASSWORD` | Contraseña PostgreSQL. Debe coincidir con la inicialización del volumen. | `PENDIENTE DE CONFIGURAR` |

### Redis

| Variable | Descripción | Ejemplo |
|---|---|---|
| `REDIS_CLIENT` | Cliente Redis. | `phpredis` |
| `REDIS_HOST` | Host del contenedor Redis. | `redis` |
| `REDIS_USERNAME` | Usuario Redis. Si Redis no usa autenticación, debe quedar vacío. | vacío |
| `REDIS_PASSWORD` | Contraseña Redis. Si Redis no usa autenticación, debe quedar vacío. | vacío |
| `REDIS_DB` | Base lógica para uso general. | `0` |
| `REDIS_CACHE_DB` | Base lógica para caché. | `1` |

### Administración de desarrollo

| Variable | Descripción | Ejemplo |
|---|---|---|
| `DEV_ADMIN_NAME` | Nombre del usuario administrador de desarrollo. | `Administrador Dev` |
| `DEV_ADMIN_EMAIL` | Correo del administrador de desarrollo. | `admin@codered.local` |
| `DEV_ADMIN_PASSWORD` | Contraseña del administrador de desarrollo. | `CHANGE_THIS_BEFORE_SEEDING` |

## Primer inicio

En una instalación limpia, el contenedor `app` ejecuta el bootstrap automáticamente al arrancar. El flujo interno se encarga de:

1. preparar directorios escribibles;
2. instalar dependencias faltantes si aplica;
3. compilar frontend si falta el manifest;
4. generar la clave de la aplicación si está vacía;
5. limpiar cachés de configuración;
6. ejecutar migraciones;
7. ejecutar seeders;
8. crear el enlace de `storage`;
9. dejar el dashboard y el login listos para uso.

## Actualización

```bash
git pull
docker compose up -d --build
```

Si cambian variables de entorno relevantes, reinicia los contenedores con el mismo comando para que el bootstrap vuelva a aplicar migraciones, seeders y limpieza de caché de forma idempotente.

## Desarrollo

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app npm run dev
```

Durante desarrollo, `npm run dev` sirve para recompilar assets en caliente. Para despliegue o verificaciones de login usa `npm run build`.

## Producción

- Mantener `APP_DEBUG=false`.
- Verificar credenciales reales de PostgreSQL y Redis.
- Compilar frontend con `npm run build`.
- No exponer secretos en `.env.example`.

## Solución de problemas

| Problema | Solución |
|---|---|
| `Credenciales inválidas` al iniciar sesión después de una instalación limpia | Verificar que el bootstrap del contenedor terminó y que el seed del administrador se ejecutó |
| `Vite manifest not found` | Ejecutar `docker compose up -d --build` para regenerar `public/build/manifest.json` |
| `ERR AUTH` en Redis | Vaciar `REDIS_PASSWORD` si Redis no usa contraseña y reiniciar contenedores |
| PostgreSQL no autentica | Sincronizar `DB_PASSWORD` con el rol existente o revisar el volumen inicializado |

## Verificación

```bash
docker compose ps
docker compose exec app php artisan about
docker compose exec app php artisan migrate:status
curl http://localhost:8090/api/v1/health
curl -I http://localhost:8090/login
```

## Notas

- `composer.lock` y `package-lock.json` deben versionarse.
- Los valores con espacios en `.env` deben escribirse entre comillas.
- Redis local sin contraseña debe usar valores vacíos, no la cadena `null`.
