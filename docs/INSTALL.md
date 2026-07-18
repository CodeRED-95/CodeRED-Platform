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
git clone <PENDIENTE DE CONFIGURAR>
cd CodeRED Platform
```

## Copiar `.env`

```bash
copy .env.example .env
```

## Levantar Docker

```bash
docker compose up -d --build
```

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

## Problemas frecuentes

| Problema | Solución |
|---|---|
| `docker` no existe | Instalar Docker Desktop o Docker Engine |
| `composer` no existe | Usar `docker compose exec app composer install` |
| `php artisan` falla | Verificar que el contenedor `app` esté activo |
| El frontend no compila | Ejecutar `npm install` y luego `npm run build` dentro del contenedor |
