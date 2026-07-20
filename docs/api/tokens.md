# Tokens

Solo Super Administrador accede al panel. Puede crear tokens para usuarios activos, ver nombre/propietario/abilities/último uso/expiración, rotar y revocar individualmente o en grupos de hasta 100.

Rotar crea una credencial equivalente y mantiene la anterior activa. Después de comprobar el nuevo token, el administrador debe revocar el anterior. La revocación elimina el hash de Sanctum; el evento seguro permanece en auditoría sin secreto ni hash.

## Abilities por servicio

- `agencias:consultar`: únicamente `/api/v1/agencias`.
- `dni:consultar`: únicamente `/api/v1/dni/{dni}`.
- Ambas deben seleccionarse expresamente para un token combinado.
