# 0005 - Uso de Docker Compose

## Estado

Aprobado

## Contexto

El proyecto define servicios separados para aplicación, web, PostgreSQL, Redis, cola y scheduler.

## Problema

Necesitamos un entorno reproducible para desarrollo y despliegue local.

## Alternativas consideradas

- Instalación directa en el host
- Docker Compose
- Kubernetes

## Decisión

Usar Docker Compose para orquestar el entorno local.

## Justificación

- reproduce el stack completo de forma simple
- separa responsabilidades por servicio
- permite persistir PostgreSQL y Redis con volúmenes

## Consecuencias

- Positivas:
  - entorno coherente entre desarrolladores
  - despliegue local más predecible
- Negativas:
  - exige Docker instalado

## Referencias

- `docker-compose.yml`
- `docker/php/Dockerfile`
- `docker/nginx/default.conf`

