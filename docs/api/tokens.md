# Tokens

Solo Super Administrador accede al panel. Puede crear tokens para usuarios activos, ver nombre/propietario/abilities/último uso/expiración, rotar y revocar individualmente o en grupos de hasta 100.

Rotar crea una credencial equivalente y mantiene la anterior activa. Después de comprobar el nuevo token, el administrador debe revocar el anterior. La revocación elimina el hash de Sanctum; el evento seguro permanece en auditoría sin secreto ni hash.

## Abilities por servicio

- `agencias:consultar`: únicamente `/api/v1/agencias`.
- `dni:consultar`: únicamente `/api/v1/dni/{dni}`.
- `dni:nombre`: únicamente `/api/v1/dni/name-search`. No la implica
  `dni:consultar` ni al revés; en una sesión de usuario ambas se resuelven
  contra el mismo permiso RBAC `dni-records.view`.
- `ruc:consultar`: únicamente `/api/v1/ruc/{ruc}`.
- `ruc:buscar`: únicamente `/api/v1/ruc/buscar`.
- Cada una debe seleccionarse expresamente para un token combinado.
