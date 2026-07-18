# 0002 - Uso de Laravel como framework base

## Estado

Aprobado

## Contexto

El proyecto necesita autenticación web, API versionada, colas, scheduler, middleware, validación, Eloquent y una base empresarial estable.

## Problema

Elegir una plataforma que acelere el desarrollo sin sacrificar mantenibilidad ni seguridad.

## Alternativas consideradas

- FastAPI
- Node.js / NestJS
- Laravel

## Decisión

Usar Laravel 12 como framework principal.

## Justificación

- ofrece autenticación, colas, scheduler y Eloquent integrados
- encaja con el equipo y el stack declarado
- simplifica el desarrollo del panel administrativo
- facilita Livewire y Sanctum

## Consecuencias

- Positivas:
  - menor tiempo de implementación
  - ecosistema maduro
  - estructura estándar conocida
- Negativas:
  - dependencia del ciclo de vida de Laravel

## Referencias

- `composer.json`
- `routes/`
- `app/`

