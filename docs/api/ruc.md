# API RUC v1

`GET /api/v1/ruc/{ruc}` requiere `ruc:consultar`. `GET /api/v1/ruc/{ruc}/representantes` requiere `ruc:representantes`. `GET /api/v1/ruc/buscar` requiere `ruc:buscar`. Un token DNI no obtiene acceso RUC implícitamente. Los tokens móviles nuevos obtienen `ruc:consultar` solo si el usuario tiene el permiso RBAC `ruc.view`.

```bash
curl -X GET "https://platform.codered.lat/api/v1/ruc/20123456789" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TU_TOKEN_RUC"
```

```powershell
Invoke-RestMethod -Uri "https://platform.codered.lat/api/v1/ruc/20123456789" -Headers @{ Accept="application/json"; Authorization="Bearer TU_TOKEN_RUC" }
```

```javascript
const response = await fetch('/api/v1/ruc/20123456789', { headers: { Accept: 'application/json', Authorization: 'Bearer TU_TOKEN_RUC' } });
const data = await response.json();
```

```python
import requests
response = requests.get("https://platform.codered.lat/api/v1/ruc/20123456789", headers={"Accept": "application/json", "Authorization": "Bearer TU_TOKEN_RUC"}, timeout=15)
```

El RUC es una cadena de exactamente 11 dígitos. Respuestas: 401 token inválido, 403 ability insuficiente, 404 no encontrado, 422 formato inválido y 429 límite excedido. Las rutas administrativas de importación no forman parte de la API pública.

## Representantes legales

```bash
curl -X GET "https://platform.codered.lat/api/v1/ruc/20123456789/representantes" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TU_TOKEN_RUC_REPRESENTANTES"
```

```powershell
Invoke-RestMethod -Uri "https://platform.codered.lat/api/v1/ruc/20123456789/representantes" -Headers @{ Accept="application/json"; Authorization="Bearer TU_TOKEN_RUC_REPRESENTANTES" }
```

Respuesta esperada:

```json
{
  "success": true,
  "data": {
    "ruc": "20123456789",
    "representantes": [
      {
        "tipo_documento": "DNI",
        "numero_documento": "12345678",
        "nombre": "APELLIDOS NOMBRES",
        "cargo": "GERENTE GENERAL",
        "fecha_desde": "2020-01-15"
      }
    ]
  },
  "meta": {
    "source": "sunat",
    "cached": false,
    "consulted_at": "2026-09-07T12:00:00Z"
  }
}
```

Errores:

- `401`: token no autenticado o inválido.
- `403`: falta `ruc:representantes`.
- `404`: SUNAT no devolvió representantes para ese RUC.
- `422`: RUC inválido.
- `502`: HTML inválido o respuesta inesperada de SUNAT.
- `503`: SUNAT no está disponible.
- `504`: timeout consultando SUNAT.
