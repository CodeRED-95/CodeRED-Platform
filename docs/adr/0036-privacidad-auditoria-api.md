# ADR 0036: privacidad de auditoría API

## Decisión

`api_request_logs` registra cliente, token, servicio, endpoint, estado y duración. Para DNI conserva SHA-256 del identificador, nunca el número ni el Bearer Token. Authorization y payloads del proveedor quedan fuera de auditoría.


## Trazabilidad DNI (2026-07-20)

La auditoría incorpora origen, llamada al proveedor, estado externo, hit de caché y hit local. Solo se conserva el hash SHA-256 del DNI como identificador de consumo. No se registran el Bearer del consumidor, el token de PeruDevs ni la respuesta completa del proveedor.
