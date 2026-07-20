# Agencias

`GET /api/v1/agencies` pagina y filtra sin incluir eliminadas. Admite búsqueda, estado, ubicación, tamaño, canales, orden seguro, dirección y hasta ``API_MAX_PER_PAGE`` resultados.

`GET /api/v1/agencies/{code}` resuelve explícitamente mediante Code; no cambia el route model binding web ni depende del ID interno.

El contrato exacto se encuentra en [OpenAPI](../openapi.yaml). `internal_id`, ID externo y Code son identificadores diferentes. `estado` contiene la etiqueta operativa en español y `centro_operaciones` siempre es booleano, mapeado desde la columna interna `is_operations_center`.
