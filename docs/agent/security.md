# Seguridad

El agente guarda `/data/integration.enc` con AES-256-GCM y permisos 0600. El directorio usa 0700. Los logs son JSON sanitizados y no incluyen secretos, Pair Code, Authorization, firmas ni tokens.
