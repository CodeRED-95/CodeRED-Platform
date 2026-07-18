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

Si el proyecto ya dispone de `package-lock.json`, la instalación frontend debe ejecutarse con `npm ci` en lugar de `npm install`.

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

Este paso genera `public/build/manifest.json` y `public/build/assets/`. Si no se ejecuta, la página de login puede fallar con `Vite manifest not found`.

## Primer inicio

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

Flujo recomendado de primer inicio:

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan storage:link
docker compose exec app npm run build
```

## Notas de instalación

- Si `.env` contiene valores con espacios, deben ir entre comillas.
- El proyecto debe escribirse con el usuario interno `www`.
- El proceso master de PHP-FPM debe iniciar como root; los workers corren como `www`.
- Git Safe Directory se configura automáticamente para `/var/www/html` durante el build y el entrypoint.
- `composer.lock` no existe actualmente en el árbol del repositorio; debe generarse con `composer install` y versionarse para que futuras instalaciones usen el lockfile.

## Problemas frecuentes

| Problema | Solución |
|---|---|
| `docker` no existe | Instalar Docker Desktop o Docker Engine |
| `composer` no existe | Usar `docker compose exec app composer install` |
| `php artisan` falla | Verificar que el contenedor `app` esté activo |
| El frontend no compila | Ejecutar `npm install` y luego `npm run build` dentro del contenedor |
