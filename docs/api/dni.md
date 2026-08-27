# API DNI v1

Última actualización: 27/08/2026.

## Consulta

`GET /api/v1/dni/{dni}` requiere `dni:consultar`. El DNI es string de ocho dígitos, conserva ceros iniciales, la fecha usa `YYYY-MM-DD` y la edad se calcula dinámicamente. Los tokens móviles nuevos obtienen `dni:consultar` solo si el usuario tiene el permiso RBAC correspondiente.

### cURL

```bash
curl --request GET \
  --url 'https://platform.codered.lat/api/v1/dni/12345678' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer TOKEN_DNI'
```

### JavaScript

```javascript
const response = await fetch('https://platform.codered.lat/api/v1/dni/12345678', {
  headers: { Accept: 'application/json', Authorization: 'Bearer TOKEN_DNI' },
});
const data = await response.json();
```

### PHP

```php
$response = Http::withToken('TOKEN_DNI')
    ->acceptJson()
    ->get('https://platform.codered.lat/api/v1/dni/12345678');
$data = $response->json();
```

### Python

```python
import requests
response = requests.get(
    "https://platform.codered.lat/api/v1/dni/12345678",
    headers={"Accept": "application/json", "Authorization": "Bearer TOKEN_DNI"},
    timeout=15,
)
data = response.json()
```

La respuesta pública mantiene la misma estructura sin importar si CodeRED resolvió la consulta internamente, desde caché o mediante su proveedor privado. PeruDevs y su API key no forman parte de la autenticación del consumidor.

Errores: 401, 403, 404, 422, 429, 502 y 503. Consulta [errores](errors.md), [autenticación](authentication.md) y la colección [Postman](../postman/CodeRED-Platform-API.postman_collection.json).

## Búsqueda por nombres

`GET /api/v1/dni/name-search` requiere `dni:nombre` — **no** `dni:consultar`: son
abilities distintas y un token puede tener una sin la otra. Los tres parámetros
son obligatorios y admiten solo letras, espacios, apóstrofos y guiones:
`nombres`, `apellido_paterno`, `apellido_materno`.

El resultado es **referencial**. Procede de un formulario público de terceros
(DNIPERU), no de RENIEC, y así lo declara la propia respuesta con
`meta.official = false` y `meta.referential = true`. Para datos oficiales use la
consulta por número de la sección anterior.

Llega desactivada de fábrica. Requiere `DNI_NAME_SEARCH_ENABLED=true` **y**
`DNI_NAME_SEARCH_DNIPERU_ENABLED=true`: el primero es el interruptor maestro de
la función y el segundo el del proveedor concreto, de modo que apagar cualquiera
de los dos devuelve 503. Con la función apagada el endpoint sigue existiendo y
respondiendo 503, no 404.

```bash
curl --request GET \
  --url 'https://platform.codered.lat/api/v1/dni/name-search?nombres=JUAN%20CARLOS&apellido_paterno=PEREZ&apellido_materno=GOMEZ' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer TOKEN_DNI_NOMBRE'
```

```json
{
  "success": true,
  "data": [
    {
      "dni": "12345678",
      "nombres": "JUAN CARLOS",
      "apellido_paterno": "PEREZ",
      "apellido_materno": "GOMEZ",
      "full_name": "JUAN CARLOS PEREZ GOMEZ"
    }
  ],
  "meta": { "provider": "dniperu", "official": false, "referential": true, "count": 1 }
}
```

Los resultados se cachean 24 h por combinación de nombres
(`DNI_NAME_SEARCH_CACHE_TTL`) y el límite propio es de 10 peticiones por minuto
(`DNI_NAME_SEARCH_RATE_LIMIT_PER_MINUTE`), independiente del de `/dni/{dni}`.

La auditoría guarda un **hash SHA-256** de `NOMBRES|PATERNO|MATERNO` en
mayúsculas, nunca los nombres en claro, siguiendo el mismo criterio que ya se
aplica al DNI y al RUC.

Errores propios: 404 sin coincidencias, 429 si el proveedor limita, y 503 si la
función está apagada, el proveedor bloquea o no responde. Detalle de la
configuración y del módulo del panel en
[DNI_NAME_SEARCH.md](../DNI_NAME_SEARCH.md).
