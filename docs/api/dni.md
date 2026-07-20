# API DNI v1

Última actualización: 20/07/2026.

## Consulta

`GET /api/v1/dni/{dni}` requiere `dni:consultar`. El DNI es string de ocho dígitos, conserva ceros iniciales, la fecha usa `YYYY-MM-DD` y la edad se calcula dinámicamente.

### cURL

```bash
curl --request GET \
  --url 'https://platform.codered.host/api/v1/dni/12345678' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer TOKEN_DNI'
```

### JavaScript

```javascript
const response = await fetch('https://platform.codered.host/api/v1/dni/12345678', {
  headers: { Accept: 'application/json', Authorization: 'Bearer TOKEN_DNI' },
});
const data = await response.json();
```

### PHP

```php
$response = Http::withToken('TOKEN_DNI')
    ->acceptJson()
    ->get('https://platform.codered.host/api/v1/dni/12345678');
$data = $response->json();
```

### Python

```python
import requests
response = requests.get(
    "https://platform.codered.host/api/v1/dni/12345678",
    headers={"Accept": "application/json", "Authorization": "Bearer TOKEN_DNI"},
    timeout=15,
)
data = response.json()
```

La respuesta pública mantiene la misma estructura sin importar si CodeRED resolvió la consulta internamente, desde caché o mediante su proveedor privado. PeruDevs y su API key no forman parte de la autenticación del consumidor.

Errores: 401, 403, 404, 422, 429, 502 y 503. Consulta [errores](errors.md), [autenticación](authentication.md) y la colección [Postman](../postman/CodeRED-Platform-API.postman_collection.json).
