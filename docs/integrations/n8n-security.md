# Seguridad n8n CodeRED

Firma canonical:

```text
HTTP_METHOD + "\n" + REQUEST_PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + SHA256_RAW_BODY
```

Headers: `X-CodeRED-Integration`, `X-CodeRED-Timestamp`, `X-CodeRED-Nonce`, `X-CodeRED-Signature`, `X-CodeRED-Protocol-Version`. CodeRED usa Redis/cache para nonces, tolerancia de 5 minutos y HMAC SHA-256.

Los workflows que recuperan tokens deben desactivar guardado de ejecuciones exitosas, no usar pin data, no loguear bodies sensibles y confirmar entrega inmediatamente.
