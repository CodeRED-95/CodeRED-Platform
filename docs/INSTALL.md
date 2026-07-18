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
git clone https://github.com/CodeRED-95/CodeRED-Platform.git
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
En la red local puede responder también en `http://192.168.18.124:8090` si el host publica ese puerto.

## Instalar dependencias

```bash
docker compose exec app composer install
docker compose exec app npm ci
```

Si todavía no existe `package-lock.json`, la instalación frontend puede ejecutarse con `npm install`, pero el estado actual del proyecto debe preferir `npm ci`.

## Generar clave de aplicación

```bash
docker compose exec app php artisan key:generate
```

La clave `APP_KEY` solo debe generarse si está vacía. No se regenera en cada arranque.

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
docker compose exec app npm ci
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan storage:link
docker compose exec app npm run build
```

## Notas de instalación

- Si `.env` contiene valores con espacios, deben ir entre comillas.
- `APP_URL` local debe coincidir con el puerto real expuesto por Nginx.
- El proyecto debe escribirse con el usuario interno `www`.
- El proceso master de PHP-FPM debe iniciar como root; los workers corren como `www`.
- Git Safe Directory se configura automáticamente para `/var/www/html` durante el build y el entrypoint.
- `composer.lock` debe existir, persistir y versionarse para instalaciones reproducibles.
- PostgreSQL se inicializa desde `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`.
- Si el volumen de PostgreSQL ya estaba inicializado, la contraseña interna puede requerir sincronización manual sin borrar el volumen.

## Problemas frecuentes

| Problema | Solución |
|---|---|
| `docker` no existe | Instalar Docker Desktop o Docker Engine |
| `composer` no existe | Usar `docker compose exec app composer install` |
| `php artisan` falla | Verificar que el contenedor `app` esté activo |
| El frontend no compila | Ejecutar `npm ci` o `npm install` según exista `package-lock.json`, y luego `npm run build` dentro del contenedor |
