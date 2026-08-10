# Módulo Shalom Recordar

Área administrativa y API para sincronizar la información capturada por la extensión **Shalom Recordar Extension**.

## Datos almacenados

- Instalación de la extensión por `user_id` + `installation_uuid`.
- Versión de la extensión.
- Metadatos seguros del dispositivo/navegador si la extensión los envía.
- Registros sincronizados con `field`, `value`, `timestamp` y `record_id` opcional.

## Autenticación

La sincronización utiliza `auth:sanctum` con tokens de API existentes. El flujo se separa en:

- `shalom-recordar:bootstrap` para registrar una instalación y emitir su token individual;
- `shalom-recordar:sync` para sincronizar los datos de esa instalación.

## Rutas

- `POST /api/v1/shalom-recordar/installation`
- `POST /api/v1/shalom-recordar/installations/register`
- `POST /api/v1/shalom-recordar/sync`
- `GET /api/v1/shalom-recordar/sync/status`

## Panel

- `/admin/shalom-recordar`
- `/admin/shalom-recordar/users/{user}`
- `/admin/shalom-recordar/installations/{installation}`

## Administración

En el panel administrativo se pueden gestionar sincronizaciones por lote o por instalación:

- eliminar un lote individual;
- eliminar todas las sincronizaciones de una instalación;
- revocar el token de una instalación;
- eliminar una instalación completa sin borrar el usuario;
- eliminar todas las sincronizaciones de un usuario mediante acción explícita.
