# Auditoría

## Alcance

CodeRED Platform registra automáticamente cambios sobre Usuarios y Agencias.

Cada evento conserva:

- acción realizada;
- usuario responsable, cuando existe una sesión autenticada;
- fecha y hora con segundos;
- dirección IP y agente de usuario cuando están disponibles;
- valores anteriores y nuevos;
- lista de campos modificados.

## Usuarios

`UserObserver` registra creación, actualización, papelera, restauración y eliminación
definitiva en `activity_logs`. Los cambios de roles se registran explícitamente como
`roles_updated`, porque una sincronización de la tabla pivote no dispara eventos del
modelo `User`.

`created_by` y `updated_by` se asignan automáticamente desde la sesión autenticada.
El historial solo se consulta y muestra con `users.view_activity`.

## Agencias

`AgencyObserver` registra creación, actualización, papelera y restauración en
`agency_change_logs`. Los traslados conservan además sus eventos de dominio.

`created_by` y `updated_by` se asignan automáticamente desde la sesión autenticada.
El historial solo se consulta y muestra con `agencies.view_history`.

La eliminación definitiva activa `cascadeOnDelete` sobre el historial de la agencia;
no se crean eventos huérfanos después de borrar el registro principal.

## Protección de secretos

`AuditLogger` elimina de valores anteriores y nuevos:

- `password` y `password_confirmation`;
- `remember_token`;
- tokens de API o sesión conocidos.

Cuando cambia una credencial se registra únicamente el marcador `credentials`, nunca
el hash ni el valor secreto.

## Interfaz

`x-ui.audit-entry` presenta el evento, responsable, fecha, IP, agente y diferencias.
Los cambios se comunican con texto y no dependen solo del color.
