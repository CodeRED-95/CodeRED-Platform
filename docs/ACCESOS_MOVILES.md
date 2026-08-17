# Accesos móviles: solicitud y concesión

Cómo alguien sin Consulta RUC o Consulta DNI puede pedirlos desde la app, y cómo
un administrador los concede.

## Los permisos son los reales

No hay permisos nuevos para esto. Se usan los que ya decidían el acceso en toda
la plataforma:

| Acceso | Permiso RBAC | Ability del token |
|---|---|---|
| Consulta RUC | `ruc.view` | `ruc:consultar` |
| Consulta DNI | `dni-records.view` | `dni:consultar` |

Conviene señalarlo porque es fácil suponer `dni.view`: el permiso real de la
consulta de identidad es **`dni-records.view`**.

La comprobación sigue siendo `hasPermission('ruc.view')` en todas partes. En
ningún punto del sistema se decide nada por el nombre de un rol.

## Cómo llega el permiso a una persona

Aquí hubo que elegir, porque `User::hasPermission()` resuelve **únicamente a
través de roles**: no existe una tabla que ligue permisos a usuarios.

Las dos opciones eran añadir esa tabla —y con ella tocar la función de
autorización de la que depende cada política de la aplicación— o usar un rol que
contenga exactamente el permiso y nada más. Se eligió lo segundo: el cambio de
menor alcance sobre la pieza más delicada.

```
usuario  ──┬── viewer        (su rol de siempre, intacto)
           └── acceso-ruc    (sólo contiene ruc.view)
```

De ahí que un usuario quede como «Consulta + Acceso RUC» sin convertirse en
administrador, y que retirarle el acceso sea quitarle ese rol.

`MobileAccessManager` es el único sitio que concede o retira. Conceder es
idempotente. Retirar quita **sólo** el rol de acceso: si el permiso le llega
además por su rol principal, sigue teniéndolo, y eso es correcto — retirar un
acceso móvil no puede desmontar la configuración de roles de nadie. La interfaz
distingue las dos cosas con `granted` y `revocable`.

## Qué se puede pedir

`MobileAccess` es la lista blanca, y es la protección central del sistema. El
endpoint sólo acepta las claves que estén ahí, así que manipular la petición
para pedir `users.delete` no lleva a ninguna parte: se rechaza en la validación,
antes de crear nada, y un administrador nunca llega a ver esa solicitud.

Ampliarla es una decisión explícita que se toma en esa clase.

## Endpoints

**Quien solicita** (ability `mobile`, la que lleva todo token del login móvil):

```
GET  /api/v1/mobile/permission-requests
POST /api/v1/mobile/permission-requests   { permission, reason? }
```

El `GET` devuelve el estado de **todos** los accesos —si los tiene y, si no, en
qué punto está su solicitud—, de modo que la app pinta sus tarjetas con una sola
llamada.

Todo parte de `$user->permissionRequests()`: nadie ve ni toca lo de otro, y no
hay comprobación de propiedad que se pueda olvidar porque la consulta nunca sale
de lo suyo.

**Quien decide** (ability `admin:accesos` + permiso RBAC comprobado en cada
petición):

```
GET  /api/v1/admin/permission-requests?estado=&search=&per_page=
GET  /api/v1/admin/permission-requests/{id}
POST /api/v1/admin/permission-requests/{id}/approve
POST /api/v1/admin/permission-requests/{id}/reject   { motivo? }
```

`permission-requests.view` abre la bandeja; `permission-requests.manage` permite
decidir. Son dos permisos porque son dos cosas distintas: mirar y resolver.

**Sin esperar a una solicitud**, desde la ficha del usuario:

```
POST /api/v1/admin/users/{id}/mobile-access/grant    { permission }
POST /api/v1/admin/users/{id}/mobile-access/revoke   { permission }
```

Exigen `permission-requests.manage`, el mismo permiso que aprobar: conceder a
mano y aprobar una petición son la misma facultad, y separarlas dejaría una
puerta estrecha junto a una ancha.

## No se duplican solicitudes

Dos barreras, y las dos hacen falta.

Una consulta previa resuelve el caso normal —alguien vuelve a pulsar— con un
mensaje claro y sin provocar un error de base de datos. Y un **índice único
parcial** sobre `(user_id, permission) WHERE status = 'pending'` cubre lo que la
consulta no puede ver: dos peticiones simultáneas que la comprueban a la vez,
las dos antes de que ninguna haya insertado.

La inserción va dentro de su propio punto de guardado. Sin él, en PostgreSQL una
violación del índice dejaría abortada la transacción que la envuelva.

## Decidir

Todo ocurre en una transacción con la fila bloqueada, y el orden importa:

```
bloquear la solicitud
   ↓
¿sigue pendiente?  ¿no es la propia?
   ↓
otorgar el permiso real          ← primero esto
   ↓
marcar aprobada, con quién y cuándo
```

Si la asignación fallara, la transacción vuelve atrás y la solicitud sigue
pendiente: **nunca queda una solicitud aprobada cuyo permiso no llegó a
otorgarse**.

El bloqueo resuelve el caso de dos administradores decidiendo a la vez. El
segundo encuentra la solicitud resuelta y recibe un 409 con el motivo exacto, en
lugar de conceder el permiso por segunda vez o pisar la decisión del primero.

Nadie resuelve su propia solicitud, ni con permisos de gestión.

Rechazar no retira nada: simplemente no concede.

## El token no obliga a cerrar sesión

Las abilities se fijaban al iniciar sesión, así que un permiso aprobado después
no llegaba al token: la persona tenía el permiso y su token seguía sin la
ability. La única salida era salir y volver a entrar.

Ahora `GET /api/v1/mobile/me` **recalcula** las abilities del token actual con el
mismo resolver que las emitió. La app ya llamaba ahí para refrescar sus
permisos, así que el token se pone al día en el mismo gesto.

Esto no debilita Sanctum: las abilities no se amplían arbitrariamente, se
derivan de los permisos reales de esa persona en ese instante. Y funciona en las
dos direcciones — si a alguien le retiran un permiso, la ability desaparece en la
siguiente llamada, cosa que antes no ocurría hasta el logout. Es más estricto
que lo que había.

## Avisos

La decisión se notifica por los dos canales que ya existen: `database` para el
centro de notificaciones y `fcm` para el aviso inmediato.

El push dice que hubo decisión y **no lleva el motivo del rechazo**: eso puede
leerse en una pantalla de bloqueo, o delante de otra persona. El motivo completo
está en el historial, que se lee con la app ya abierta.

## Auditoría

Quedan registrados, con quién y cuándo: `permission_requested`,
`permission_approved`, `permission_rejected`, `permission_granted` y
`permission_revoked`. Ningún token en los registros.
