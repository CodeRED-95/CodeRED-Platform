# 0027. Identidad externa y transición de textos Chosen

## Contexto

El ID del JSON se derivaba a Code y `source_reference`; `texto_chosen` se conservaba como `source_text`. La extensión necesita distinguir terrestre y aéreo sin cambiar relaciones ni URLs.

## Decisión

Mantener `agencies.id` como PK y Code como identificador de rutas. Añadir `external_id` nullable con unicidad parcial, más `texto_chosen_terrestre` y `texto_chosen_aereo`. Conservar `source_text` y exponer `texto_chosen` deprecated con fallback terrestre, aéreo y original. Rechazar importaciones cuando external_id, Code y referencia resuelvan agencias diferentes.

## Consecuencias

La extensión puede migrar gradualmente. El rollback pierde valores nuevos, pero no destruye `source_text`. La eliminación del campo heredado requiere otra decisión y una migración futura.
