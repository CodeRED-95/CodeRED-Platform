# Autenticación y autorización en CodeRED

CodeRED tiene **dos mecanismos de acceso a la API**, deliberadamente separados.
Comparten el mismo vocabulario de capacidades, pero no el mismo camino de
autorización.

```
IDENTIDAD DE USUARIO                    TOKENS DE API
Platform · Mobile · Desktop             n8n · agentes · bridges · scripts
        │                                       │
  correo + contraseña                   token creado en administración
        │                                       │
  sesión de cliente                     personal access token
  (access corto + refresh)              (kind = integration)
        │                                       │
  permisos RBAC en vivo                 abilities declaradas en el token
```

La regla que las separa: **una persona autoriza por permiso, una integración
autoriza por ability**. Ambas atraviesan el mismo middleware, `access`.

---

## 1. Autenticación de usuario

Es el camino normal de una persona en cualquiera de los tres clientes. No
requiere crear, copiar ni pegar ningún token.

### Login

```
POST /api/v1/auth/login
{
  "email": "usuario@codered.lat",
  "password": "…",
  "application": "platform" | "mobile" | "desktop",
  "device_name": "Galaxy S24",     // opcional
  "platform": "Android 14",         // opcional
  "client_version": "0.17.0"        // opcional
}
```

Respuesta:

```json
{
  "success": true,
  "data": {
    "access_token": "…",
    "token_type": "Bearer",
    "expires_at": "2026-08-16T21:35:35-05:00",
    "expires_in": 900,
    "refresh_token": "…",
    "refresh_token_expires_at": "2026-09-15T21:20:35-05:00",
    "session": { "uuid": "…", "application": "desktop", "device_name": "…" },
    "user": { "id": 16, "name": "…", "email": "…" },
    "roles": ["admin"],
    "permissions": ["desktop.access", "dni-records.view"],
    "applications": ["platform", "desktop"]
  }
}
```

El login falla, con el mismo cuerpo genérico, si las credenciales no coinciden
(422), si la cuenta no está activa (403) o si el usuario no tiene el permiso de
acceso a esa aplicación (403). Esta última comprobación es de backend: ocultar
el botón en el cliente no es seguridad.

### Refresh

```
POST /api/v1/auth/refresh
{ "refresh_token": "…" }
```

El refresh **se rota**: el presentado queda consumido y se devuelve uno nuevo.
Presentar dos veces el mismo refresh se interpreta como robo o clonación y
**cierra la sesión completa**. Un 401 en este endpoint significa siempre "vuelve
a pedir credenciales".

El refresh también falla —y revoca la sesión— si entre medias se desactivó la
cuenta o se retiró el acceso a la aplicación.

### Perfil, sesiones y logout

| Endpoint | Efecto |
|---|---|
| `GET /api/v1/auth/me` | Usuario, roles, permisos y aplicaciones permitidas |
| `GET /api/v1/auth/sessions` | Sesiones activas propias, con `current: true` en la actual |
| `DELETE /api/v1/auth/sessions/{uuid}` | Cierra una sesión concreta |
| `DELETE /api/v1/auth/sessions` | Cierra todas menos la actual |
| `POST /api/v1/auth/logout` | Cierra **sólo** la sesión con la que se llama |

`logout` nunca toca las demás sesiones del usuario ni ninguno de sus tokens de
API.

### Almacenamiento en el cliente

El refresh token es una credencial de larga duración: va en el almacén seguro
del sistema operativo (Keystore/Keychain en Mobile, DPAPI en Desktop), nunca en
un `.json`, `.txt` o `.env` en claro. La contraseña no se guarda jamás después
del login.

---

## 2. Tokens de API (integraciones)

**No han cambiado.** Se siguen creando desde `/admin/api-tokens`, siguen viviendo
en `personal_access_tokens` y siguen autorizando por las abilities declaradas en
el token. Todo token existente conserva su comportamiento: la columna nueva
`kind` nace con el valor `integration`, que es exactamente lo que eran.

Son el mecanismo correcto para n8n, CodeRED Agent, Shalom, Declaración Jurada,
scripts y cualquier cliente que no represente a una persona.

Un token de integración **no puede** usar los endpoints de sesión (`/auth/me`,
`/auth/sessions`): no tiene `profile:read` salvo que se le conceda, y no tiene
sesión que listar.

---

## 3. Autorización

Toda ruta funcional declara la capacidad con su ability:

```php
Route::get('/ruc/{ruc}', RucApiController::class)
    ->middleware(['throttle:ruc-lookup', 'api.audit:ruc', 'access:ruc:consultar']);
```

`App\Http\Middleware\EnsurePermission` (alias `access`) decide cómo comprobarla:

| Quién llama | Cómo se autoriza |
|---|---|
| Sesión de usuario (`kind = session`) o sesión web | Permiso RBAC equivalente, **consultado en cada petición** |
| Token de integración | `tokenCan(ability)`, comportamiento histórico |
| `ApiClient` | `tokenCan(ability)`, comportamiento histórico |

La correspondencia ability ↔ permiso vive en un único sitio,
`App\Services\Auth\AbilityPermissionMap`. Una ability no mapeada **deniega**: se
falla cerrado, nunca abierto.

### Consecuencia práctica

Los permisos **no se congelan en el token**. Si un administrador retira
`ruc.view`, el acceso a RUC desaparece en la siguiente petición desde Platform,
Mobile y Desktop, sin renovar ni regenerar nada. Si lo concede, funciona igual de
rápido.

Por eso el cliente puede cachear `/auth/me` para construir su interfaz, pero la
autoridad real es siempre el backend.

---

## 4. Permisos de acceso por aplicación

| Permiso | Habilita |
|---|---|
| `platform.access` | Entrar a CodeRED Platform |
| `mobile.access` | Entrar a CodeRED Mobile |
| `desktop.access` | Entrar a CodeRED Desktop |

Se administran como cualquier otro permiso, desde los roles del usuario. La
migración que los introduce los concede a **todos los roles existentes**, para no
dejar fuera a quien ya usaba Mobile en producción; a partir de ahí se recortan
desde administración.

---

## 5. Revocación y ciclo de vida

| Situación | Efecto |
|---|---|
| Cerrar sesión desde el cliente | Sólo esa sesión |
| Cerrar sesión desde Platform | Esa sesión deja de responder de inmediato |
| Reutilización de un refresh | Se cierra la sesión completa |
| Usuario desactivado | Login, refresh y peticiones bloqueados |
| Permiso retirado | Deja de funcionar en la siguiente petición |
| Cambio de contraseña | Revoca las sesiones de cliente; los tokens de API **no** se tocan |

La revocación no espera a que caduque el access token: `EnsurePermission`
comprueba que la sesión siga viva en cada petición.

---

## 6. Auditoría

Los eventos se registran en `activity_logs` con la acción como identificador:

```
auth.login.success   auth.login.failed    auth.login.denied
auth.refresh         auth.refresh.reuse_detected
auth.logout          auth.session.revoked auth.password.changed
```

Se registran IP, user agent, aplicación y el **UUID público** de la sesión.
Nunca se registran contraseñas, access tokens, refresh tokens ni tokens de API.

---

## 7. Límites de peticiones

| Limitador | Alcance |
|---|---|
| `auth-login` | 10/min por IP **y** 5/min por correo (hash HMAC) |
| `auth-refresh` | 30/min por IP |

El doble límite del login cubre las dos formas de fuerza bruta: muchas cuentas
desde una IP, y muchas IPs contra una cuenta.

---

## 8. Configuración

```
CLIENT_SESSION_ACCESS_TTL_MINUTES=15
CLIENT_SESSION_REFRESH_TTL_DAYS=30
CLIENT_SESSION_MAX_PER_APP=5
CLIENT_SESSION_REVOKE_ON_PASSWORD_CHANGE=true
```

---

## 9. Compatibilidad y migración

`/api/v1/mobile/login`, `/mobile/me` y `/mobile/logout` **siguen funcionando sin
cambios** mientras los clientes publicados se actualizan. Emiten tokens de
integración con abilities, como siempre, y las rutas los siguen aceptando por la
rama de ability del middleware.

Orden de retirada:

1. Platform expone `/auth/*` junto a `/mobile/*` — **hecho** (v4.22.0).
2. Administración de sesiones y de accesos por aplicación — **hecho** (v4.23.0).
3. Mobile migra a `/auth/*` — **hecho** (v0.17.0).
4. Desktop migra a `/auth/*` — **hecho** (v1.3.0).
5. Se retira `/mobile/*` cuando no queden clientes antiguos en uso — pendiente.

El paso 5 no tiene fecha a propósito: mientras haya instalaciones de Mobile
anteriores a 0.17.0 en manos de usuarios, retirar el contrato antiguo las dejaría
sin acceso. Se comprueba mirando las versiones de cliente que aparecen en el
inventario de sesiones.

### Validación integral

`scripts/e2e-auth-validation.php` ejercita el ecosistema completo contra la
instancia real: una cuenta entrando en los tres clientes, permisos compartidos,
retirada de permiso en caliente, rotación y reutilización de refresh, revocación
remota, cuenta desactivada, acceso por aplicación y compatibilidad de los tokens
de API. Crea un usuario temporal y lo elimina al terminar.

```bash
docker compose exec -T app php scripts/e2e-auth-validation.php
```

Llama a la API por el kernel HTTP interno en lugar de por la red pública: así
recorre las mismas rutas, middleware y autorización que un cliente real sin
depender de Cloudflare ni agotar el límite de intentos de login.

Los tokens de API permanecen en todas las fases.
