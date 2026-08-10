# Módulo Shalom Recordar

Área administrativa y API para sincronizar la información capturada por la extensión **Shalom Recordar Extension**.

## Datos almacenados

- Instalación de la extensión por `user_id` + `installation_uuid`.
- Versión de la extensión.
- Metadatos seguros del dispositivo/navegador si la extensión los envía.
- Registros sincronizados con `field`, `value`, `timestamp` y `record_id` opcional.

## Autenticación

La sincronización utiliza `auth:sanctum` con tokens de API existentes y la habilidad `shalom-recordar.manage`.

## Rutas

- `POST /api/v1/shalom-recordar/installation`
- `POST /api/v1/shalom-recordar/sync`

## Panel

- `/admin/shalom-recordar`
- `/admin/shalom-recordar/users/{user}`
- `/admin/shalom-recordar/installations/{installation}`
