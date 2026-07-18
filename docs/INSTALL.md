# Instalación

## Requisitos

| Requisito | Valor |
|---|---|
| Docker | Requerido |
| Docker Compose | Requerido |
| Node.js | 20+ recomendado por el proyecto |
| PHP local | No requerido si se usa Docker |
| Composer local | No requerido si se usa Docker |

## Clonar proyecto

```bash
git clone <URL_DEL_REPOSITORIO>
cd CodeRED-Platform
```

## Copiar `.env`

```bash
cp .env.example .env
```

## Levantar Docker

```bash
docker compose up -d --build
```

## Puerto de acceso

La configuración actual expone Nginx en `http://localhost:8090`.

## Instalar dependencias

```bash
docker compose exec app composer install
docker compose exec app npm install
```

## Generar clave de aplicación

```bash
docker compose exec app php artisan key:generate
```

## Migraciones

```bash
docker compose exec app php artisan migrate
```

## Seeders

```bash
docker compose exec app php artisan db:seed
```

## Compilar frontend

```bash
docker compose exec app npm run build
```

## Primer inicio

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

## Notas de instalación

- Si `.env` contiene valores con espacios, deben ir entre comillas.
- El proyecto debe escribirse con el usuario interno `www`.
- El proceso master de PHP-FPM debe iniciar como root; los workers corren como `www`.
- `composer.lock` debe versionarse cuando el entorno permita generarlo correctamente.

## Problemas frecuentes

| Problema | Solución |
|---|---|
| `docker` no existe | Instalar Docker Desktop o Docker Engine |
| `composer` no existe | Usar `docker compose exec app composer install` |
| `php artisan` falla | Verificar que el contenedor `app` esté activo |
| El frontend no compila | Ejecutar `npm install` y luego `npm run build` dentro del contenedor |
