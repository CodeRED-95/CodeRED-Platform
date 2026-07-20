# ADR 0035: proveedor DNI intercambiable

## Decisión

`DniLookupService` depende de `DniProviderInterface`; `CurrentDniProvider` es configurado por el contenedor. El controlador nunca conoce URLs o credenciales y las pruebas reemplazan la interfaz sin consumir servicios reales.


## Corrección PeruDevs (2026-07-20)

Se adopta `PeruDevsDniProvider` como implementación del contrato. El orquestador consulta primero `dni_records`, después la caché y finalmente PeruDevs. La configuración en base de datos prevalece sobre `.env`; el token externo se cifra y nunca se comparte con Sanctum. Las respuestas crudas no se almacenan.
