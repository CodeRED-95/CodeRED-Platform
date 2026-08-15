# API RUC v1

`GET /api/v1/ruc/{ruc}` requiere `ruc:consultar`. `GET /api/v1/ruc/buscar` requiere `ruc:buscar`. Un token DNI no obtiene acceso RUC implícitamente. Los tokens móviles nuevos obtienen `ruc:consultar` solo si el usuario tiene el permiso RBAC `ruc.view`.

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
