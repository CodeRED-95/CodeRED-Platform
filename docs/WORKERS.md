# Workers de larga duracion

Como paran los procesos de larga duracion de CodeRED Platform y por que estan
configurados asi.

## El problema que resuelve esta configuracion

Un `docker compose up -d` podia dejar la plataforma sin servir trafico durante
minutos. La cadena era esta:

1. La imagen no incluia la extension **`pcntl`**.
2. Sin `pcntl`, `Worker::supportsAsyncSignals()` devuelve falso y Laravel **no
   instala manejador de SIGTERM** en `queue:work`.
3. El worker corria ademas como **PID 1**, y el kernel descarta las senales sin
   manejador dirigidas a PID 1.
4. Docker esperaba el `stop_grace_period` entero -2 h en `queue`, 25 h en
   `queue-ruc-backups`- antes de recurrir a SIGKILL.
5. Mientras tanto `app` quedaba en estado `Created`, sin PHP-FPM detras de
   nginx.

Ninguno de los tres primeros puntos por separado causaba el bloqueo: sin PID 1
el kernel habria matado el proceso al instante, y con `pcntl` Laravel habria
salido ordenadamente. Hacia falta la combinacion.

## Que hay ahora

**`pcntl` en la imagen.** Laravel instala su manejador y, al recibir SIGTERM,
deja de tomar trabajos nuevos, termina el que tenga entre manos y sale. La
extension queda **desactivada en el pool de PHP-FPM** (`docker/php/fpm/www.conf`):
el servidor web no necesita control de procesos y no tiene por que ganarlo.

**`init: true` en los tres workers.** PID 1 pasa a ser `docker-init`, que
reenvia las senales al proceso real y recoge los subprocesos que quedan. Con eso
desaparece el caso especial del kernel para PID 1.

**El scheduler usa `schedule:work`.** El bucle `while true; do schedule:run;
sleep 60; done` no servia: `sh` espera a que termine su hijo en primer plano
antes de atender la senal, asi que retenia el SIGTERM hasta 60 segundos. El
bucle propio de Laravel es un proceso PHP que responde de inmediato.

## Grace periods

No son cifras redondas: salen del trabajo real mas largo de cada cola.

| Servicio | Antes | Ahora | Por que |
|---|---|---|---|
| `queue` | 2 h | **10 min** | El trabajo mas largo es la sincronizacion Shalom, con techo declarado de 240 s y dependiente de un extractor HTTP con timeout de 180 s. La restauracion de agencias declara 3600 s por precaucion frente a Cloudflare, pero opera sobre 545 filas y tarda segundos. |
| `queue-ruc-backups` | 25 h | **20 min** | La restauracion del padron completo. Con 18.3 M de filas, `docs-ruc/BACKUP_FORMAT.md` proyecta ~3.5 min de COPY y ~5-6 min de indices: unos 10 minutos. El doble deja margen sin volver a un numero que no correspondia a ningun trabajo real. |
| `scheduler` | 10 s (defecto) | **30 s** | `schedule:work` solo despacha trabajos a las colas. |

Con las colas vacias -el caso habitual- el apagado es inmediato y estos margenes
no se usan. Solo importan cuando hay un trabajo largo de verdad en curso, y
entonces sirven exactamente para lo que estan: dejarlo terminar.

Medido tras el cambio:

| Servicio | `docker compose restart` |
|---|---|
| `queue` | 613 ms |
| `queue-ruc-backups` | 614 ms |
| `scheduler` | 812 ms |

## Procedimiento seguro de reinicio

```bash
# Un worker suelto. --no-deps es imprescindible: sin el, recrear un worker
# arrastra a `app` -del que depende- y lo deja parado mientras tanto.
docker compose up -d --no-deps queue
```

`update.sh` hace lo mismo por fases: avisa a los workers con `queue:restart`,
levanta primero todo lo que sirve trafico y recrea los workers al final.

`queue:restart` es **cooperativo**: escribe en Redis el instante a partir del
cual los workers deben salir en cuanto acaben. Adelanta la parada, pero no
sustituye a las senales de Docker; si Redis no responde, el despliegue sigue y
Docker para los workers con SIGTERM.

## Healthchecks

Los workers **no llevan healthcheck a proposito**. Comprobar que el proceso
existe no distingue un worker sano de uno bloqueado, y marcarlo `healthy` por
eso seria peor que no tener nada: daria una garantia falsa. Un healthcheck util
exigiria que el worker publicase un latido propio, y eso es codigo de
aplicacion, no configuracion de Docker. Mientras no exista, el estado real se
mira en los logs y en la profundidad de las colas.

## Recrear un contenedor no debe romper nginx

nginx resuelve los destinos de `proxy_pass` y `fastcgi_pass` **una sola vez**,
al arrancar sus workers, cuando el destino es un nombre literal. Si el
contenedor de destino se recrea y Docker le asigna otra IP, nginx sigue hablando
con la anterior y responde 502 hasta que alguien lo recarga a mano.

Ocurrio el 16/08/2026 con `declaracion.codered.lat` despues de recrear
contenedores: el contenedor estaba sano y servia en su puerto, pero nginx
apuntaba a una IP que ya no era suya.

Por eso los destinos van ahora en variables, con el DNS interno de Docker:

```nginx
resolver 127.0.0.11 valid=10s ipv6=off;

set $declaracion_upstream http://declaracion-jurada:3000;
proxy_pass $declaracion_upstream;
```

Con el destino en una variable, nginx consulta el DNS en cada peticion y respeta
el TTL, asi que un contenedor recreado se recoge solo.

Si aun asi aparece un 502, el diagnostico son dos ordenes:

```bash
docker compose logs --tail=20 nginx | grep upstream    # a que IP intenta ir
docker inspect $(docker compose ps -q <servicio>) --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}'
```

Si no coinciden y el destino sigue siendo un literal, `nginx -s reload` lo
arregla en el momento.
