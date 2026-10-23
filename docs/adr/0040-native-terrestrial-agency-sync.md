# ADR 0040: reconciliar estados con capturas terrestres y sesión administrativa

- Estado: aceptado
- Fecha: 2026-10-22

## Contexto

El buscador y Shalom Recordar no deben administrar el catálogo. Se requiere una
extensión aparte que compare la lista nativa terrestre de Shalom y active las
presentes/desactive las ausentes con una cuenta administradora de Platform.

## Decisión

El paquete `shalom-agency-sync-extension` captura solo el DOM del selector
nativo, sin seleccionar destinos ni emitir órdenes. Reutiliza las sesiones
oficiales de Platform, con tokens exclusivamente en memoria de sesión Chrome.
Un endpoint administrativo calcula una vista previa ligada a cuenta/sesión y
la aplica transaccionalmente mediante AgencyPolicy, Eloquent y auditoría.

Se preserva el origen. No se crean agencias ni se modifican ambiguas,
trasladadas o ausentes sin referencia terrestre. Se rechazan capturas filtradas
o aéreas y se bloquean anomalías de coincidencia/desactivación. El catálogo se
revalida al aplicar. El modo automático requiere consentimiento explícito y
actúa por nuevas capturas, no mediante un rastreador periódico.

## Consecuencias

No hay un nuevo almacén de contraseñas, una cuenta de servicio privilegiada ni
permisos nuevos. La autenticación y cada cambio son revocables/auditables.
Se necesita tener abierta una sesión válida de Shalom. El DOM no certifica que
el catálogo sea universal para todas las sucursales: antes de automatizar, el
administrador debe revisar una comparación y confirmar que no hay restricciones
por cuenta/origen. Los cambios solo afectan estados globales de Platform.

Contrato y límites: [sincronización nativa](../SHALOM_NATIVE_AGENCY_SYNC.md).
