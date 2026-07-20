# API DNI y respaldo PeruDevs

`GET /api/v1/dni/{dni}` requiere Bearer Sanctum con `dni:consultar`. Consulta primero `dni_records`, luego caché negativa y finalmente PeruDevs.

PeruDevs se invoca mediante GET a la URL configurable con los parámetros `document` y `key`. La API key privada nunca es un token Sanctum, no se envía como Bearer y no aparece en logs o respuestas.

El payload `estado/mensaje/resultado` se valida estrictamente. `resultado.id` debe coincidir con el DNI solicitado. `fecha_nacimiento` se convierte de `DD/MM/YYYY` a `YYYY-MM-DD`; la edad se calcula en cada respuesta.

Errores: 401 credencial inválida, 403 ability ausente, 404 no encontrado, 422 DNI inválido, 429 límite local, 502 respuesta externa inválida y 503 proveedor no configurado o no disponible.

Administración: `/admin/settings/dni`, exclusiva de Super Administrador. La API key se cifra, un campo vacío la conserva y eliminarla requiere confirmación.

```bash
curl --request GET \
  --url 'https://platform.codered.host/api/v1/dni/12345678' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer TOKEN_DNI'
```

Consulta [DNI_LEGACY_MIGRATION.md](../DNI_LEGACY_MIGRATION.md) para migrar desde `dni-api`.
