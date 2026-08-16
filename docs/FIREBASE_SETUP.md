# Notificaciones push (Firebase Cloud Messaging)

Cómo CodeRED Platform entrega avisos inmediatos a CodeRED Mobile, dónde vive cada
credencial y qué hacer cuando algo no llega.

La parte del cliente —`google-services.json`, permiso de Android, registro del
token— está en el repositorio de CodeRED Mobile, en su propio
`docs/FIREBASE_SETUP.md`.

## Arquitectura

```
Evento de plataforma (POST /api/v1/declarations)
        │
        ▼
Laravel Notification  ──►  canal database  ──►  tabla notifications  ──►  GET /api/v1/notifications
        │                                        (historial: fuente de verdad)
        └────────────────►  canal fcm       ──►  Firebase  ──►  CodeRED Mobile
                                                 (entrega inmediata)
```

Los dos canales llevan el mismo evento pero no el mismo texto, y eso es
deliberado: **el historial se lee dentro de la app y el push se lee en la
pantalla de bloqueo**, delante de quien tenga el teléfono a la vista. Por eso el
push dice «Tu declaración jurada fue generada correctamente» y no el código del
documento ni la sede.

La distinción importante es cuál manda. El historial en base de datos es la
fuente de verdad; FCM es sólo el timbre. Si Firebase está caído, el aviso sigue
esperando en el centro de notificaciones la próxima vez que el usuario abra la
app. Al revés no: un push perdido no se recupera.

De ahí una regla que se ve en el código de `App\Notifications\Channels\FcmChannel`:
**nunca lanza**. Laravel envía todos los canales de una notificación en el mismo
job; si el canal FCM propagara una excepción, el reintento volvería a ejecutar
`database` y el usuario acabaría con dos filas idénticas. Un push perdido es un
incordio, un historial duplicado es un error de datos.

## Piezas

| Qué | Dónde |
|---|---|
| Librería | `kreait/laravel-firebase` ^7.2 (PHP 8.3 / Laravel 12) |
| Canal | `app/Notifications/Channels/FcmChannel.php` |
| Contenido del push | `toFcm()` de cada notificación, devolviendo `App\Notifications\FcmPush` |
| Dispositivos | tabla `mobile_devices`, modelo `App\Models\MobileDevice` |
| API | `POST /api/v1/mobile/devices`, `DELETE /api/v1/mobile/devices/{id}` |
| Diagnóstico | `php artisan push:diagnose` |

## Credencial de servicio

Permite enviar notificaciones a **todos** los usuarios de la app. No entra en el
repositorio, no entra en la imagen Docker y no se imprime en ninguna salida.

Vive en el host:

```
/home/codered/secrets/firebase-adminsdk.json   (chmod 600)
```

y se monta de sólo lectura en los contenedores que la necesitan —`app`, para
artisan y diagnóstico, y `queue`, que es quien envía de verdad porque la
notificación va en cola:

```yaml
- ${FIREBASE_CREDENTIALS_HOST:-/home/codered/secrets/firebase-adminsdk.json}:/var/www/secrets/firebase-adminsdk.json:ro
```

### Variables

| Variable | Valor | Para qué |
|---|---|---|
| `FIREBASE_CREDENTIALS_HOST` | `/home/codered/secrets/firebase-adminsdk.json` | ruta en el host, la que monta Docker |
| `FIREBASE_CREDENTIALS` | `/var/www/secrets/firebase-adminsdk.json` | ruta dentro del contenedor, la que lee la librería |
| `FIREBASE_PUSH_ENABLED` | `true` | apagar la entrega sin perder el historial |

`update.sh` las rellena si faltan (`ensure_firebase_env`) y **nunca sobrescribe
un valor existente**. Si la credencial no está en el host, avisa y sigue: sin
push la plataforma funciona igual.

### Rotar o reemplazar la credencial

Se genera una clave nueva en la consola de Firebase (Configuración del proyecto →
Cuentas de servicio) y se sustituye el archivo **en el host**:

```bash
install -m 600 /ruta/de/la/nueva.json /home/codered/secrets/firebase-adminsdk.json
docker compose restart app queue
```

No hace falta reconstruir imágenes ni tocar el `.env`: la ruta no cambia. La
clave anterior se revoca desde la consola una vez comprobado que `push:diagnose`
sigue en verde.

## Dispositivos

`mobile_devices` guarda lo mínimo para poder entregar: usuario, plataforma,
token, nombre comercial del aparato y última actividad. **Nada de IMEI, número de
serie, Android ID ni huella de hardware**: un identificador que sobrevive a la
desinstalación convierte la tabla en un rastreador y para enviar un push no hace
falta.

El token va cifrado (`encrypted`, con `APP_KEY`) y a su lado viaja un SHA-256 que
es quien lleva el índice único. Eso permite reconocer el mismo dispositivo sin
poder leer el token desde la base.

Ese índice es único **a nivel global**, no por usuario, y esa es la decisión que
más consecuencias tiene: un token identifica una instalación concreta de la app,
así que sólo puede pertenecer a una persona a la vez. Cuando alguien inicia
sesión en un teléfono donde ya había otra cuenta, el registro cambia de dueño y
el usuario anterior deja de recibir avisos ahí —incluso si su cierre de sesión
nunca llegó a completarse por falta de red—.

### El token no sale de la base

No aparece en las respuestas de la API, ni en los logs, ni en la auditoría, ni en
las excepciones, ni en ninguna pantalla administrativa. `AuditApiRequest` nunca
ha almacenado el cuerpo de las peticiones —sólo endpoint, método y estado—, y hay
una prueba que lo fija (`MobileDeviceTest::test_la_auditoria_no_almacena_el_token_en_ninguna_columna`)
para que nadie lo cambie sin darse cuenta.

### Tokens caducados

Cuando Firebase responde que un token es desconocido o inválido, el canal borra
ese dispositivo en el momento. Sin eso, un teléfono desinstalado seguiría
recibiendo intentos indefinidamente.

## Desarrollo

Sin credencial, `FIREBASE_PUSH_ENABLED=false` deja el sistema entero funcionando
salvo el timbre: las notificaciones se guardan, se listan y se marcan como
leídas. Es lo razonable en un entorno local.

Las pruebas no llegan nunca a Firebase: `PushNotificationTest` sustituye el
contrato `Kreait\Firebase\Contract\Messaging` por un doble.

## Producción

Nada especial más allá de tener la credencial en su sitio y la cola viva. La
notificación es `ShouldQueue` y sale por la cola `default`, que atiende el
servicio `queue`. Con el worker parado el aviso se guarda igualmente en el
historial y el push llega cuando la cola vuelve.

## Diagnóstico

```bash
docker compose exec app php artisan push:diagnose
```

Comprueba de una vez el interruptor, la presencia y los permisos del archivo de
credenciales, cuántos dispositivos hay registrados y —con una llamada real a
Google que no envía nada— que la credencial se acepta y hay salida a Internet.
No imprime ningún token ni ningún dato de la credencial.

### Si un push no llega

1. **`push:diagnose` en rojo** → la credencial: ruta, permisos o proyecto.
2. **Cero dispositivos** → el teléfono no se ha registrado. Suele ser el permiso
   de Android denegado; se ve en el Perfil de la app.
3. **Diagnóstico en verde y cero dispositivos para ese usuario** → el registro
   pertenece a otra cuenta. Es lo correcto si alguien más inició sesión en ese
   teléfono.
4. **Todo en verde y sigue sin llegar** → mirar `fcm_send_failed` y
   `fcm_tokens_pruned` en los logs de `queue`. Ninguno de los dos imprime tokens.
5. **La app estaba forzada a detenerse** (Ajustes → Forzar detención) → Android
   no entrega FCM a un paquete en ese estado hasta que el usuario lo abre a mano.
   No es un fallo y no tiene arreglo desde el servidor.
