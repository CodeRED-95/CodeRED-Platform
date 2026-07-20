# ADR 0036: privacidad de auditoría API

## Decisión

`api_request_logs` registra cliente, token, servicio, endpoint, estado y duración. Para DNI conserva SHA-256 del identificador, nunca el número ni el Bearer Token. Authorization y payloads del proveedor quedan fuera de auditoría.
