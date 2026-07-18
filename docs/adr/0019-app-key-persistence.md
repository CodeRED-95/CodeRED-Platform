# 0019. Persistencia de `APP_KEY`

**Estado:** Aceptado

## Contexto

Laravel usa `APP_KEY` para cifrado, sesiones y diversos mecanismos de seguridad.

## Problema

Si `APP_KEY` se regenera en cada arranque o en cada instalación, se invalidan sesiones, datos cifrados y tokens relacionados.

## Alternativas consideradas

- Regenerar `APP_KEY` automáticamente en cada despliegue.
- Permitir una clave vacía y autogenerarla sin control.
- Generar la clave solo si está vacía y mantenerla persistente en `.env`.

## Decisión

`APP_KEY` solo se genera cuando está vacía. Después debe persistir en el archivo `.env` del entorno.

## Justificación

- Evita invalidaciones accidentales.
- Hace reproducible la instalación.
- Mantiene la seguridad criptográfica de Laravel.

## Consecuencias

- El flujo de instalación debe comprobar si la clave ya existe.
- La documentación debe recordar que la clave no debe regenerarse si el entorno ya está inicializado.

## Referencias

- [docs/INSTALL.md](../INSTALL.md)
- [docs/TROUBLESHOOTING.md](../TROUBLESHOOTING.md)
