# ADR 0035: proveedor DNI intercambiable

## Decisión

`DniLookupService` depende de `DniProviderInterface`; `CurrentDniProvider` es configurado por el contenedor. El controlador nunca conoce URLs o credenciales y las pruebas reemplazan la interfaz sin consumir servicios reales.
