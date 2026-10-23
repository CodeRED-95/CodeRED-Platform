# Sincronización de estados desde la lista nativa terrestre

Cliente: [extensión independiente](../packages/shalom-agency-sync-extension/README.md).
La lista de destinos terrestres mostrada por Shalom a una sesión y origen
concretos es la fuente de comparación; Platform conserva autenticación,
permisos, aplicación transaccional y auditoría.

## Autenticación

Usa `/api/v1/auth/login` con `application=platform`, correo, contraseña y
`device_name=Sincronizador de agencias Shalom`. No se emiten tokens técnicos ni
se crea un sistema paralelo de usuarios. La renovación y cierre usan
`/api/v1/auth/refresh` y `/api/v1/auth/logout`.

Todos los endpoints siguientes exigen Bearer de una sesión oficial activa de
Platform, usuario activo, rol `super-admin` o administrativo heredado `admin`,
y permisos `agencies.view` y `agencies.manage_status`. `platform.access` se
revalida en el middleware. No se admiten sesiones web, tokens de integración ni
sesiones de otra aplicación aunque el usuario sea administrador.

## Endpoints

| Método | Ruta bajo `/api/v1/admin/agencies/native-sync` | Resultado |
|---|---|---|
| GET | `/status` | Usuario autorizado y límites de captura |
| POST | `/preview` | Comparación sin modificar agencias |
| POST | `/apply` | Aplica un `preview_id` de la misma cuenta y sesión |
| POST | `/result` | Consulta el recibo del `preview_id` sin repetir cambios |

Respuesta normal: `{ "success": true, "data": { ... } }`. Errores de validación:
422 con `message` y `errors`; autenticación 401; permisos 403; comparación
caducada o catálogo cambiado 409; comparación bloqueada 422; rate limit 429.

`/preview` recibe este esquema (el ejemplo abreviado no constituye una captura
válida: deben enviarse al menos 100 entradas reales consecutivas):

```json
{
  "source_url": "https://sysnewos.shalomcontrol.com/service-order/items",
  "channel": "terrestrial",
  "origin_name": "AV. ARIAS ARAGUEZ",
  "captured_at": "2026-10-22T20:00:00Z",
  "filter": "",
  "complete": true,
  "end_observed": true,
  "stable_reads": 2,
  "total": 1,
  "entries": [
    {"index": 0, "label": "AMAZONAS, BAGUA, BAGUA - AG. BAGUA CAPITAL"}
  ]
}
```

La fecha no puede tener más de 10 minutos ni estar más de un minuto en el
futuro. `total` debe coincidir con la longitud y los índices deben ser 0..N−1.
El servidor no consulta URLs proporcionadas por el cliente ni ejecuta HTML.

El plan contiene `preview_id`, `expires_at`, `snapshot_hash`, `revision`,
`origin`, `native_count`, `matched_count`, `activate_count`, `deactivate_count`,
`changes` (id, nombre, ubicación, estado anterior y propuesto), `review`,
`blocked_reasons` y `can_apply`. Caduca en 10 minutos y está ligado a usuario y
sesión; el cliente no puede decidir IDs ni estados al aplicar.

`/apply` recibe solo `{ "preview_id": "UUID" }`. Revalida permisos, bloquea
la operación y las filas del catálogo, comprueba su revisión y guarda estados
mediante Eloquent/AgencyPolicy. Cualquier fallo revierte la transacción. Cada
cambio se registra en `agency_change_logs`, el catálogo incremental y
`activity_logs` (`native_sync`, hash de captura, origen y número de operación).
El recibo incluye fecha, usuario, origen y conteos; repetir el mismo UUID
devuelve el mismo recibo durante su vigencia en caché.

Si se pierde la respuesta al aplicar, la extensión apaga la automatización,
marca el resultado pendiente y bloquea nuevas comparaciones. `/result` consulta
el recibo sin volver a aplicar. Si no hay recibo, se debe comprobar auditoría y
estados en Platform antes de descartar manualmente el pendiente.

## Reglas de comparación

Se normalizan ubicación completa y nombre con ASCII, mayúsculas y espacios.
Se permite nombre anterior cuando no resulta ambiguo. No se usa `data-index`
como ID de Platform o Shalom. El origen debe tener una coincidencia única.

- Presente e identificada: activa, salvo origen protegido o agencia trasladada.
- Ausente activa con referencia terrestre: inactiva, salvo origen protegido o
  candidata a una coincidencia ambigua.
- Ausente sin referencia terrestre: revisión sin modificar (incluidas aéreas).
- Desconocida, ambigua o trasladada: revisión sin alta ni cambio automático.

No se actualiza ningún otro campo de negocio. Se bloquea el plan si la captura
contiene duplicados, coincide menos del 80 %, no se reconoce un origen único o
la desactivación supera el 10 % de las agencias terrestres activas.
Los límites están en `config/agency_native_sync.php`.

## Operación y reversión

El modo automático pertenece a la sesión del navegador y está apagado de inicio.
No hay tarea programada ni credencial guardada en archivos. Solo una nueva
captura completa inicia la automatización. Un cambio de cuenta elimina el plan
y el consentimiento automático anterior.

Las capturas solo prueban la lista disponible para esa cuenta/origen. El DOM no
ofrece un manifiesto firmado ni un total externo contra el cual certificar su
completitud. Revisa el primer resultado; no habilites cambios globales si Shalom
restringe los destinos para esa cuenta. La protección porcentual es una barrera
operativa, no una prueba de completitud.

Para revertir estados, identifica la operación `native_sync` en la auditoría y
restaura manualmente los estados anteriores desde el panel con permiso
`agencies.manage_status`, verificando que no haya cambios posteriores. No borres
agencias ni ejecutes restauraciones destructivas del catálogo.
