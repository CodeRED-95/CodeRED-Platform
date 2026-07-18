# CodeRED Platform

Base técnica modular en Laravel para administración y consulta de agencias de Shalom.

## Características principales

- Arquitectura modular preparada para nuevos dominios.
- Autenticación web y API versionada.
- PostgreSQL como base de datos principal.
- Redis para caché, colas y versión global.
- Livewire, TailwindCSS y AlpineJS para la interfaz.
- Docker Compose con servicios separados para aplicación, web, base de datos, caché, cola y scheduler.
- Módulo `Agencies` con soporte para importación desde GitHub Gist, snapshot público y agencias trasladadas.

## Arquitectura resumida

- `app/Core`: utilidades transversales.
- `app/Modules`: módulos de negocio.
- `app/Modules/Agencies`: módulo principal de agencias.
- `routes/api.php`: API base y rutas versionadas.
- `routes/web.php`: panel y web pública.
- `database/migrations`: esquema relacional.
- `docker/`: configuración de contenedores.

## Tecnologías utilizadas

- Laravel 12
- PHP 8.2+
- PostgreSQL 16
- Redis 7
- Nginx 1.27
- Livewire 3
- TailwindCSS 3
- AlpineJS 3
- Laravel Sanctum 4

## Instalación rápida

1. Revisa [docs/INSTALL.md](docs/INSTALL.md).
2. Configura variables en [docs/ENVIRONMENT.md](docs/ENVIRONMENT.md).
3. Levanta Docker según [docs/DOCKER.md](docs/DOCKER.md).
4. Revisa el modelo de datos en [docs/DATABASE.md](docs/DATABASE.md).
5. Recuerda que la URL pública actual es `http://localhost:8090`.

## Capturas

PENDIENTE DE CONFIGURAR

## Documentación

- [Guía para IAs](AGENTS.md)
- [Instalación](docs/INSTALL.md)
- [Entorno](docs/ENVIRONMENT.md)
- [Docker](docs/DOCKER.md)
- [Base de datos](docs/DATABASE.md)
- [API](docs/API.md)
- [Agencies](docs/AGENCIES.md)
- [Importador](docs/IMPORTER.md)
- [Arquitectura](docs/ARCHITECTURE.md)
- [Desarrollo](docs/DEVELOPMENT.md)
- [Testing](docs/TESTING.md)
- [Seguridad](docs/SECURITY.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [Contribución](docs/CONTRIBUTING.md)
- [Roadmap](docs/ROADMAP.md)
- [Changelog](docs/CHANGELOG.md)
- [FAQ](docs/FAQ.md)
- [ADR](docs/adr/README.md)

## Roadmap resumido

- Core y autenticación base.
- Módulo `Agencies`.
- Importación desde GitHub Gist.
- Web pública y panel administrativo.
- Exportación, caché y snapshot compacto.
- Próximos módulos: DNI, RUC, Clientes, Trabajadores, Reportes, Estadísticas, Chrome Extension y App móvil.

## Licencia

Proprietary
