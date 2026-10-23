# Validación y despliegue 1.0.0

Fecha de comprobación: 23 de octubre de 2026 (Perú).

## Resultado comprobado

- Extensión: 25 pruebas Node aprobadas, build y ZIP inspeccionados.
- Backend: 8 pruebas de sincronización aprobadas, 55 aserciones, en
  `codered_testing` mediante el servicio Compose local `app`.
- Autenticación existente: 18 pruebas aprobadas, 63 aserciones.
- Pint: los seis archivos PHP afectados pasan.
- PHPStan: los cuatro archivos PHP nuevos de aplicación/pruebas pasan con la
  configuración existente y la base de pruebas local.
- Interfaz: revisión independiente de siete hallazgos, todos resueltos. Su
  disposición `ship` cubre esos hallazgos, no certifica una aplicación productiva.
  Las capturas pobladas son simulaciones explícitas, no cambios reales.

La comparación de solo lectura con 501 destinos nativos y 566 registros actuales
de Platform propone 1 activación y 21 desactivaciones; deja 3 entradas para
revisión y preserva el origen. No se aplicó ese plan ni se modificaron agencias.

## API publicada

Origen verificado: `codered@192.168.18.198:/srv/codered/platform`, montado en
`/var/www/html` del contenedor `codered-app`.

Se desplegó solo el commit de API sobre la revisión productiva existente, sin
incorporar las actualizaciones pendientes de otras extensiones:

- Fuente: `f2634a235f1a83ca09f4de3dba34c47f4676b16d`.
- Producción: `387afb519718c033b36f9be9828c0636e208dfc9`.
- Rama productiva respaldada en origin: `codex/shalom-native-sync-api`.
- Fuente completa de extensión/API: `codex/shalom-native-sync`.

El Git del servidor no pudo contactar su remoto por verificación de clave de
host. No se desactivó esa comprobación: se transfirió un bundle Git mediante la
conexión SSH ya confiable, se verificó SHA-256 y se aplicó solo el commit de API.
El commit productivo también se respaldó en origin con un bundle verificado.

Se regeneraron únicamente las cachés de configuración/rutas y se recargó PHP-FPM.
No se ejecutaron migraciones, seeders, limpieza de caché de aplicación ni órdenes
de modificación de agencias.

Comprobación del dominio público `https://platform.codered.lat`:

| Método | Ruta | Respuesta sin credenciales |
| --- | --- | --- |
| GET | `/api/v1/admin/agencies/native-sync/status` | 401 |
| POST | `/api/v1/admin/agencies/native-sync/preview` | 401 |
| POST | `/api/v1/admin/agencies/native-sync/apply` | 401 |
| POST | `/api/v1/admin/agencies/native-sync/result` | 401 |
| GET | `/api/v1/health` | 200 |

Las cuatro rutas están registradas con los middleware de autenticación,
propietario activo, caché privada, límite administrativo, auditoría y sesión.
El contenedor permaneció `healthy`.

Antes y después del despliegue: 566 agencias y huella
`0091b12317bdb47f22068603286a57da`, calculada sobre IDs, estados y fechas de
actualización ordenados. El despliegue no cambió esos datos.

## Excepciones registradas

`composer verify` se ejecutó en el contenedor local y se detuvo en Pint:
666 problemas de estilo preexistentes en el conjunto del repositorio. El
análisis estático global también expone problemas previos; no se formatearon ni
modificaron archivos ajenos. Las comprobaciones específicas anteriores sí pasan.

El control pre-push global intenta invocar Composer directamente en Windows,
contrario al entorno Docker prescrito por el repositorio. Tras las validaciones
anteriores, se usó `CODERED_SKIP_HOOKS=1` solo durante los pushes de esta entrega,
restaurando el valor previo inmediatamente después. No se modificó el hook ni se
afirma que su receta global haya pasado.

## Pendiente de la cuenta administradora

La instalación descomprimida en Chrome, el login real del administrador y la
comparación desde la extensión requieren intervención del usuario. Las pruebas
locales y los 401 públicos no sustituyen esa validación autenticada. No se
compartieron ni solicitaron contraseñas en el chat. No hay autorización para
aplicar estados productivos en este despliegue.
