# Changelog

Todas las versiones siguen `Keep a Changelog`.

## [Unreleased]

### Added

- Documentación modular del proyecto
- `AGENTS.md` como guía oficial para IA
- Carpeta `docs/adr` con decisiones arquitectónicas
- Módulo `Agencias Shalom` con panel administrativo, vista pública, detalle e importación
- Snapshot compacto para extensión y API pública de agencias
- Dashboard con estadísticas básicas del módulo

### Changed

- README principal convertido en portada
- Estructura documental centralizada en `docs/`

### Fixed

- Valores con espacios documentados con comillas en `.env`
- Referencia de puerto sincronizada con `8090`
- Reglas de permisos y usuario `www` documentadas
- Solución documental para `Class "Redis" not found`
- Explicación arquitectónica de PHP-FPM master root y workers `www`
- Solución permanente para Git Safe Directory en `/var/www/html`
- Corrección documental sobre la persistencia de `composer.lock`
- Flujo documentado para generar `public/build/manifest.json` con `npm run build`
- Corrección del prefijo API para evitar `api/api/v1`
- Estrategia documentada para usar `DB_*` como fuente de PostgreSQL
- Explicación de cómo sincronizar credenciales de PostgreSQL cuando existe un volumen inicializado
- Inclusión del comando `health:redis` para verificar Redis sin Tinker
- Corrección técnica de la migración `000009` para eliminar la restricción UNIQUE como constraint y crear un índice único parcial
- Flujo frontend documentado para generar `package-lock.json` con `npm install` en el primer inicio y usar `npm ci` en instalaciones posteriores
- Redis configurado sin `AUTH` cuando el servidor no utiliza contraseña
- Estrategia de autorización reorientada a Gates y Policies sin sobrescribir `User::can()`
- Corrección del acceso al módulo Agencies mediante `Gate::before` con bypass de `super-admin`
- Roles, permisos y asignación del administrador reorganizados con `RolesAndPermissionsSeeder`
- Factories modulares explicadas con `newFactory()` y seeders separados por responsabilidad
- Bootstrap automático del contenedor aplicado al arranque para evitar pasos manuales de Artisan
- Factories modulares explicadas con `newFactory()` y seeders separados por responsabilidad

### Removed

- Ninguno
