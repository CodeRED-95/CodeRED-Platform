# 0001 - Arquitectura modular del proyecto

## Estado

Aprobado

## Contexto

CodeRED Platform se diseñó para crecer por módulos de negocio independientes, empezando por `Agencies`. El repositorio actual ya separa `app/Core` y `app/Modules`.

## Problema

Se necesita una estructura que permita ampliar el sistema sin mezclar responsabilidades, manteniendo panel, API, importación y lógica de dominio bajo una convención consistente.

## Alternativas consideradas

- Arquitectura monolítica por carpetas técnicas
- Arquitectura hexagonal estricta desde el inicio
- Arquitectura modular por dominio

## Decisión

Adoptar una arquitectura modular por dominio usando `app/Core` para capacidades transversales y `app/Modules` para módulos funcionales.

## Justificación

- Permite aislar el módulo `Agencies`.
- Escala de forma natural hacia futuros módulos.
- Evita capas artificiales innecesarias.
- Es compatible con Laravel y su sistema de autoload.

## Consecuencias

- Positivas:
  - mejor separación funcional
  - menor acoplamiento entre dominios
  - crecimiento ordenado
- Negativas:
  - requiere disciplina de convenciones
  - exige documentar cada módulo

## Referencias

- `app/Core`
- `app/Modules`
- `app/Modules/Agencies`

