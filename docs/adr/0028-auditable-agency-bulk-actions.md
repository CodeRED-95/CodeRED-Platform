# 0028. Acciones masivas de agencias auditables

## Contexto

El módulo ya dispone de Soft Delete, Policies y auditoría por observers. Las operaciones masivas no deben eludir estos contratos ni incluir registros ocultos.

## Decisión

Limitar cada solicitud a 100 IDs, seleccionar solo la página visible, limpiar la selección al cambiar la consulta y ejecutar Actions transaccionales en chunks de 25. Cada modelo se autoriza y guarda/elimina mediante Eloquent para conservar `AgencyObserver`. La activación solo admite `under_review` y la eliminación masiva solo usa Soft Delete.

## Consecuencias

Existe una escritura por modelo para preservar auditoría, acotada a 100 elementos. Cualquier excepción revierte modelos y logs. Operaciones mayores requerirán un Job explícito futuro.
