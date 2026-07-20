# ADR 0033: abilities Sanctum por servicio

## Decisión

Separar `agencias:consultar` y `dni:consultar`. Solo una selección explícita emite ambas. Las abilities autorizan API y los roles continúan autorizando el panel web. Se conservan rutas y `agencies:read` heredadas para clientes existentes.
