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
- CodeRED Design System con componentes Blade, tokens semánticos y branding unificado.
- Login, dashboard y panel administrativo unificados bajo un layout oscuro y responsivo.
- PHP-FPM ejecuta el master como root y los workers como `www`.
- Git Safe Directory se configura automáticamente para `/var/www/html`.
- `composer.lock` debe existir y versionarse para instalaciones reproducibles.
- `package-lock.json` debe existir y versionarse para instalaciones frontend reproducibles.
- PostgreSQL se inicializa desde las variables `DB_*` del archivo `.env`.

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
5. Recuerda que la URL pública local actual es `http://localhost:8090`; en la LAN puede ser `http://192.168.18.124:8090`.
6. El bootstrap de Laravel se ejecuta automáticamente al iniciar los contenedores.

## Capturas

PENDIENTE DE CONFIGURAR

## Documentación

- [Guía para IAs](AGENTS.md)
- [Instalación](docs/INSTALL.md)
- [Entorno](docs/ENVIRONMENT.md)
- [Docker](docs/DOCKER.md)
- [Base de datos](docs/DATABASE.md)
- [Seeders](docs/SEEDERS.md)
- [API](docs/API.md)
- [Agencies](docs/AGENCIES.md)
- [Importador](docs/IMPORTER.md)
- [Redis](docs/REDIS.md)
- [Autorización](docs/AUTHORIZATION.md)
- [Design System](docs/DESIGN_SYSTEM.md)
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

## Estado actual

- El módulo **Agencias Shalom** ya incluye panel administrativo, detalle, importación y vista pública.
- La documentación viva se mantiene en `/docs`.
- Las decisiones técnicas relevantes se registran en `/docs/adr`.
