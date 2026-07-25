# Estructura del módulo Agencias

## Estructura principal

La entidad `Agency` usa como campos principales `external_id`, `code`, `name`, `old_name`, `place`, `zone`, ubicación administrativa, dirección, coordenadas, horarios, clasificación, Chosen, estado y traslado. Las columnas heredadas se conservan para compatibilidad durante esta primera etapa.

`place` se genera automáticamente con `department / province / district / name`. `map_url` se genera exclusivamente dentro de CodeRED Platform cuando ambas coordenadas son válidas.

## Nombres anteriores

`old_name` contiene el nombre inmediatamente anterior. La tabla `agency_name_histories` conserva la secuencia completa, origen, ejecución de importación, usuario, fecha y metadatos. La comparación ignora tildes, mayúsculas, puntuación irrelevante y espacios repetidos.

## API

La API devuelve objetos anidados `schedule` y `classification`, además de `chosen_terrestre`, `chosen_aereo` y `old_name`. Se mantienen aliases heredados durante la transición para no romper la extensión Chrome.

## Columnas heredadas conservadas

`short_name`, `slug`, `reference`, teléfonos, correo, `schedule`, `services`, `observations`, `source*`, `size`, `category`, `is_operations_center`, auditoría y campos de traslado anteriores. No deben eliminarse hasta inventariar todos los consumidores y publicar una migración posterior específica.

## Revisión manual

Antes de retirar columnas antiguas se debe revisar `short_name` frente a `code`, `size/category` frente a `classification_category`, y todos los consumidores de `source_text` y `schedule`.
