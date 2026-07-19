# Changelog

## 2026-07-19 — Unificación visual, fase 1

- Se migraron formularios de agencias, importación, usuarios, login, layout y página
  404 a los componentes y tokens semánticos del CodeRED Design System.
- Se unificaron controles, validaciones, tarjetas, encabezados, acciones y estados de
  carga sin modificar contratos Livewire ni lógica de negocio.
- Se añadieron verificaciones contra estilos claros heredados y JavaScript inline.

## 2026-07-18 — Selector accesible de estados

- Se reemplazó el selector nativo de estado del formulario de agencias por un
  combobox Blade, Alpine y Livewire accesible, con panel oscuro, iconos y navegación
  completa por teclado.
- Se extendió el listbox personalizado a filtros, tamaño, fuente, estrategia, estado
  inicial y gestión de usuarios, eliminando `select` y `option` de todas las vistas.

Todas las versiones siguen `Keep a Changelog`.

## [Unreleased]

### Added

- Entorno reproducible con Dev Containers, configuración versionada de VS Code y verificadores `verify.sh`/`verify.ps1`.
- Script Composer `check` para ejecutar Pint, PHPStan y PHPUnit dentro del contenedor PHP.

- Módulo administrativo de usuarios con Livewire, Policy, reglas de seguridad y pantallas de detalle.
- Pantalla de cambio obligatorio de contraseña para cuentas marcadas por administración.
- Documentación específica para usuarios, estados y reglas críticas.
- Documentación modular del proyecto
- `AGENTS.md` como guía oficial para IA
- Carpeta `docs/adr` con decisiones arquitectónicas
- Módulo `Agencias Shalom` con panel administrativo, vista pública, detalle e importación
- Snapshot compacto para extensión y API pública de agencias
- Dashboard con estadísticas básicas del módulo
- CodeRED Design System con componentes Blade, tokens y página interna de referencia
- Login con traducciones en español y sincronización explícita de campos Livewire
- Login migrado a autenticación tradicional por sesión con `POST /login` para eliminar dependencia de Livewire en la pantalla de acceso
- Página `/admin/design-system` convertida en componente Livewire con layout administrativo
- Script de instalación reforzado con verificación del manifest actual de Vite

### Changed

- README principal convertido en portada
- Estructura documental centralizada en `docs/`

### Fixed

- PHPStan/Larastan nivel 5 estabilizado en cero errores sin baseline ni reglas de ignore.
- Errores reales corregidos en importación de Agencias, health de colas, filtro de usuarios, Resources, configuración cacheable y pruebas tautológicas.

- CRUD manual de Agencias estabilizado con normalización previa, procedencia protegida, validación de traslados, relaciones completas y cobertura de búsqueda/filtros.

- Login y sesiones reforzados con estado autoritativo, expulsión de cuentas bloqueadas y cambio obligatorio de contraseña protegido por middleware.
- Cobertura Feature ampliada para login, logout, CSRF, sesiones, recordatorio, roles y validaciones.

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
- Eliminada la duplicación de Alpine al dejar que Livewire 3 cargue la única instancia activa
- Estrategia de autorización reorientada a Gates y Policies sin sobrescribir `User::can()`
- Corrección del acceso al módulo Agencies mediante `Gate::before` con bypass de `super-admin`
- Mapeo de abilities del módulo Agencies a permisos reales para que `viewAny`, `create`, `update` e importación respeten Policies y accesos operativos
- Roles, permisos y asignación del administrador reorganizados con `RolesAndPermissionsSeeder`
- Factories modulares explicadas con `newFactory()` y seeders separados por responsabilidad
- Bootstrap automático del contenedor aplicado al arranque para evitar pasos manuales de Artisan
- Rediseño del layout administrativo, login, dashboard y vistas clave con el CodeRED Design System

### Removed

- Ninguno
