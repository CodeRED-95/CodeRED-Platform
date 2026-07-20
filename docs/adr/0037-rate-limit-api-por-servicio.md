# ADR 0037: rate limiting independiente

## Decisión

`api-agencias` y `api-dni` usan buckets separados por ID de token. El consumo de un servicio no reduce la cuota del otro; las peticiones sin token se agrupan por servicio e IP.
