# Declaraciones: borrado, copias y datos de prueba

Qué protege cada medida y, sobre todo, qué **no** protege.

## El incidente

El 16/08/2026, limpiando declaraciones creadas para validar la fase de foto del
DNI, se ejecutó a mano:

```sql
DELETE FROM declarations WHERE id BETWEEN 10 AND 20;
```

El rango era una conjetura sobre qué identificadores pertenecían a la prueba.
Dentro cayó la declaración 10, creada por una persona real mientras se trabajaba.
No había copia de seguridad de esta tabla: **no se pudo recuperar**.

Dos cosas fallaron a la vez. Se seleccionaron filas por una suposición, y no
existía red debajo.

## Marca de ejecución de validación

`declarations.validation_run` es un UUID opcional. Una declaración normal lo
tiene a `null`; sólo lo lleva la que se creó expresamente como parte de una
validación contra el entorno real, porque quien la creó lo envió en la petición.

Con eso, limpiar deja de ser una conjetura:

```bash
RUN=$(uuidgen)
# …crear las declaraciones de la validación enviando "validation_run": "$RUN"…

php artisan validation:cleanup "$RUN"            # enumera, no borra
php artisan validation:cleanup "$RUN" --force    # borra sólo esas
```

El comando **no acepta identificadores ni rangos**. Una fila sin la marca no
puede ser alcanzada por él, y las que la llevan sólo pueden haberla recibido al
crearse. Si el argumento no es un UUID válido, se rechaza antes de consultar
nada.

### La regla

No se limpian datos de prueba de producción con `BETWEEN`, con `id >`, ni con
`TRUNCATE`. Sólo por identificadores capturados durante esa misma ejecución, o
por la marca de validación. Cuando la prueba puede vivir en la suite —con
`RefreshDatabase` y `Storage::fake`— no se crea nada en producción y este
mecanismo no hace falta.

## Copia y recuperación

El sistema de copias de la plataforma cubría el padrón RUC y el catálogo de
agencias, los dos volúmenes grandes. Las declaraciones son pocas y por eso
pasaron desapercibidas.

```bash
php artisan declarations:backup                       # ZIP en el disco privado
php artisan declarations:restore <ruta.zip>           # enumera, no restaura
php artisan declarations:restore <ruta.zip> --force   # restaura lo que falte
php artisan declarations:restore <ruta.zip> --id=42 --force
```

El ZIP lleva un `declaraciones.json` con las filas y sus bienes, más los PDFs y
las fotos de DNI. Contiene datos personales: se escribe en el disco **privado**,
nunca en `public`.

La restauración **nunca pisa una declaración existente**. Si el identificador
está ocupado, la salta y lo dice: recuperar un documento perdido no puede
llevarse por delante uno vivo.

La copia se ejecuta a diario desde el planificador. Una copia que nadie ha
probado a restaurar no es una copia, así que el viaje entero —copia, pérdida,
vuelta— está cubierto por `DeclarationSafetyTest`.

## Borrado reversible

`declarations` usa `SoftDeletes`.

Conviene ser preciso sobre lo que aporta: **no habría evitado el incidente**.
Aquel `DELETE` fue SQL directo contra PostgreSQL, y `SoftDeletes` vive en
Eloquent — el SQL crudo lo atraviesa sin enterarse. Quien recupera de ese caso es
la copia de seguridad, no esta medida.

Lo que sí aporta es futuro. Hoy **ningún camino de la aplicación borra una
declaración**: no hay endpoint, ni panel de administración, ni comando. El día
que exista —y en un documento legal es cuestión de tiempo que alguien pida
"anular"— el borrado será reversible por construcción en vez de tener que
acordarse. `restore()` la devuelve, el PDF sigue en su sitio, y el historial del
usuario deja de mostrarla mientras esté dada de baja.

Los registros de validación son la excepción: `validation:cleanup` usa
`forceDelete()`. Son residuo de una prueba, no documentos que alguien pueda
necesitar recuperar.

## Los tests no tocan el almacenamiento real

`Tests\TestCase::setUp` llama a `Storage::fake('local')` para toda la suite,
junto a la comprobación que ya protegía la base de datos.

No es una precaución teórica: se encontraron quince directorios de PDFs en
`storage/app/private/declarations` con identificadores de la base de pruebas,
dejados por tests que ejercitaban endpoints reales. La base estaba protegida; el
disco no.

Falsear el disco en la clase base lo hace imposible por construcción, en lugar de
depender de que cada test nuevo se acuerde de aislarse.
