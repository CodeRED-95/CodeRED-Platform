# Tokens API y servicios independientes

Solo Super Administrador accede a **Tokens API**. Primero crea un cliente y después genera un token con Solo Agencias (`agencias:consultar`), Solo DNI (`dni:consultar`) o ambas abilities. El valor completo aparece una sola vez; Sanctum conserva únicamente su hash. La revocación afecta solo la credencial seleccionada.

Agencias: `GET /api/v1/agencias` y `GET /api/v1/agencias/{id}`. Admite `agencia`, `departamento`, `provincia`, `distrito`, `tamano`, `co`, `estado`, `per_page` y `page`.

```bash
curl --url 'https://platform.codered.host/api/v1/agencias?departamento=Tacna' --header 'Accept: application/json' --header 'Authorization: Bearer TOKEN_SOLO_AGENCIAS'
```

DNI: consulta [api/dni.md](api/dni.md). Para rotar, crea una credencial nueva, actualiza el consumidor y revoca únicamente la anterior.
