# API DNI y respaldo PeruDevs

## Contrato público

`GET /api/v1/dni/{dni}` requiere `Authorization: Bearer` y la ability `dni:consultar`. El DNI debe ser una cadena de ocho dígitos. El token Sanctum del consumidor nunca se reutiliza frente a PeruDevs.

El orden obligatorio es:

1. `dni_records`, fuente principal.
2. caché Redis positiva o negativa.
3. PeruDevs, únicamente si no hay dato local ni caché.

Una respuesta externa válida se normaliza y, si `persist_results` está activo, se guarda con `source=perudevs`. Por ello la siguiente consulta se resuelve localmente. No se conserva la respuesta cruda del proveedor.

## Respuestas

- `200`: registro encontrado.
- `404`: no existe localmente ni en PeruDevs.
- `422`: formato inválido.
- `429`: límite propio del consumidor excedido.
- `502`: respuesta válida HTTP pero contrato/JSON externo inválido.
- `503`: proveedor desactivado, sin credenciales, inaccesible o con error controlado.

La respuesta incluye actualmente `meta.source` con `internal`, `cache` o `perudevs` para trazabilidad. Nunca incluye credenciales ni mensajes internos del proveedor.

## Configuración administrativa

Solo Super Administrador, mediante permisos `settings.dni.*`, accede a `/admin/settings/dni`. La base de datos tiene prioridad sobre `.env`. El token se cifra con Laravel Crypt, se presenta únicamente enmascarado y un campo vacío conserva el valor previo. Eliminarlo requiere una acción explícita.

La pantalla permite guardar, probar conexión sin persistir el DNI de prueba y limpiar la caché mediante cambio de generación. La estrategia de actualización vigente es no refrescar automáticamente registros locales; `refresh_after_days` queda preparado para una futura actualización explícita o en cola.

## PeruDevs

El portal público observado usa la base `https://service.fitcoders.com/enty` y la ruta `/v1/entity/dni/complete`. Ambas son configurables. El normalizador admite variantes verificadas mediante fixtures, pero el contrato externo puede cambiar; las pruebas siempre usan `Http::fake()` y no realizan consultas reales.

Ejemplo:

```bash
curl --request GET \
  --url 'https://platform.codered.host/api/v1/dni/12345678' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer TOKEN_SOLO_DNI'
```
