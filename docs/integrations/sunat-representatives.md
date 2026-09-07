# Consulta de representantes legales SUNAT

La API consulta exclusivamente la vista pública de representantes legales de SUNAT. Los datos generales del contribuyente continúan saliendo del padrón RUC local y este módulo no modifica ese flujo.

## Flujo HTTP verificado

El 7 de septiembre de 2026 se verificó el flujo vigente desde el contenedor Docker de CodeRED Platform:

1. `GET https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/FrameCriterioBusquedaWeb.jsp`
   devuelve el formulario y crea las cookies `ITMRCONSRUCSESSION` y `TS01fda901`.
2. `POST /cl-ti-itmrconsruc/jcrS00Alias`, conservando esas cookies, con `accion=consPorRuc`, `nroRuc`, `token`, `contexto=ti-it`, `modo=1` y `search1`.
3. El HTML de resultado contiene `formRepLeg`, con `accion=getRepLeg`, `desRuc` y `nroRuc`.
4. `POST /cl-ti-itmrconsruc/jcrS00Alias`, conservando la misma sesión, con `accion=getRepLeg`, `contexto`, `modo`, `desRuc` y `nroRuc` devuelve la tabla de representantes.

El token usado por la página pública se genera localmente en el JavaScript de SUNAT como una cadena aleatoria de 52 caracteres; no se observó una llamada adicional del navegador para obtenerlo. Por eso el cliente actual usa Laravel HTTP/Guzzle y no requiere Playwright.

La respuesta de representantes observada contiene los encabezados `Documento`, `Nro. Documento`, `Nombre`, `Cargo` y `Fecha Desde`. El parser solo conserva esos cinco campos.

## Configuración

```dotenv
SUNAT_REPRESENTATIVES_TIMEOUT=20
SUNAT_REPRESENTATIVES_CONNECT_TIMEOUT=10
SUNAT_REPRESENTATIVES_CACHE_TTL=86400
SUNAT_REPRESENTATIVES_RATE_LIMIT=20
SUNAT_REPRESENTATIVES_USER_AGENT="Mozilla/5.0 CodeRED Platform"
```

No se almacenan representantes como datos permanentes del RUC; solo se usa la caché configurada.
