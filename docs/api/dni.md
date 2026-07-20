# API de consulta DNI

`GET /api/v1/dni/{dni}` requiere un Bearer Token Sanctum con `dni:consultar`. El DNI debe contener exactamente ocho dígitos. Los tokens con solo `agencias:consultar` reciben 403.

```bash
curl --request GET \
  --url 'https://platform.codered.host/api/v1/dni/12345678' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer TOKEN_SOLO_DNI'
```

Los resultados exitosos se guardan en Redis durante `DNI_CACHE_TTL`; los no encontrados usan `DNI_NOT_FOUND_CACHE_TTL`. Fallos temporales nunca se cachean. El proveedor se configura con `DNI_API_URL` y `DNI_API_TOKEN`; el secreto no se registra.

Errores: 401 token ausente, inválido, revocado o expirado; 403 ability insuficiente; 404 DNI no encontrado; 422 formato inválido; 429 límite excedido; 503 proveedor no disponible.
