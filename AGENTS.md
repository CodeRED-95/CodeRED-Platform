# AGENTS.md

Guía oficial para cualquier IA que trabaje sobre CodeRED Platform.

## Propósito

Este proyecto es una plataforma Laravel modular para administración y consulta de agencias de Shalom. La arquitectura, la documentación y las decisiones técnicas deben mantenerse sincronizadas.

## Arquitectura actual

| Capa | Estado |
|---|---|
| `app/Core` | Preparada para capacidades transversales |
| `app/Modules/Agencies` | Módulo funcional principal |
| `routes/api.php` | API base y rutas versionadas |
| `routes/web.php` | Panel administrativo y web pública |
| `docker-compose.yml` | Orquestación local con PHP, Nginx, PostgreSQL, Redis, queue y scheduler |

## Convenciones

- Código y documentación en español cuando se trate de negocio o interfaz.
- Nombres técnicos estables en inglés cuando formen parte del ecosistema Laravel.
- Modularidad por dominio, no por capas artificiales.
- Validación con `FormRequest`.
- Lógica de negocio en `Action` o `Service` solo cuando aporte separación real.
- Eloquent y Query Builder antes que abstracciones innecesarias.

## Nomenclatura

| Elemento | Convención |
|---|---|
| Módulos | `app/Modules/<Modulo>` |
| Actions | Operaciones explícitas de negocio |
| Services | Lógica reutilizable no trivial |
| Resources | Respuesta API |
| Requests | Validación y autorización |
| Enums | Estados y catálogos cerrados |

## Buenas prácticas

- Mantener `README.md` como portada.
- Mantener `docs/` como documentación viva.
- Actualizar ADR cuando exista una decisión arquitectónica nueva o cambie una existente.
- Mantener la API documentada si cambian rutas, respuestas o permisos.
- Mantener `ENVIRONMENT.md` si aparece o cambia una variable de entorno.
- Mantener `IMPORTER.md` si cambia el origen o transformación del Gist.
- Mantener `INSTALL.md` si cambia el flujo de arranque, build o validación.
- Mantener `AUTHORIZATION.md` si cambia el flujo de Gates, Policies o helpers de autorización.
- Mantener `DESIGN_SYSTEM.md` si cambian tokens, branding o componentes Blade compartidos.
- Mantener `SEEDERS.md` si cambia la orquestación o estructura de seeders.
- Mantener la lógica de bootstrap del entrypoint documentada si cambia la inicialización automática.
- No duplicar lógica entre panel, API e importador.

## Qué NO debe hacer una IA

- No usar `migrate:fresh`.
- No borrar datos reales.
- No eliminar volúmenes Docker.
- No modificar migraciones antiguas ya ejecutadas.
- No inventar endpoints, variables o contenedores.
- No cambiar una decisión arquitectónica sin revisar si existe ADR relacionado.
- No considerar una tarea terminada sin actualizar documentación afectada.
- No usar `777` como solución final para permisos.
- No usar `www-data` como usuario de ejecución final si el proyecto está estandarizado en `www`.
- No forzar PHP-FPM completo como usuario no privilegiado; el master debe poder iniciar como root y delegar workers al pool.
- No olvidar `safe.directory` para Git cuando el repo esté montado como bind mount dentro del contenedor.
- No olvidar que `composer.lock` debe persistir y versionarse; no usar `composer update` sin una razón comprobada.
- No olvidar que `package-lock.json` debe persistir y versionarse; la primera instalación puede usar `npm install`, pero las siguientes deben usar `npm ci`.
- No crear colores hexadecimales dispersos si existe un token semántico del CodeRED Design System.
- No crear interfaces nuevas sin reutilizar primero el CodeRED Design System.
- No usar `REDIS_PASSWORD=null`; si Redis no tiene contraseña, el valor debe ir vacío.
- No sobrescribir `User::can()` ni otros métodos internos de `Authenticatable`.
- No mover el bootstrap inicial a comandos manuales si el entrypoint ya lo resuelve.
- No dejar credenciales de ejemplo en seeders ni variables reales en `.env.example`.

## Qué debe ejecutar antes de finalizar

| Cambio | Verificaciones mínimas |
|---|---|
| Backend | Pruebas relacionadas, Pint, PHPStan si existe |
| Frontend | Build o pruebas frontend si hubo cambios en UI |
| Docker | Verificar compose y contenedores si cambió infraestructura |
| API | Verificar rutas y respuestas si cambió el contrato |
| Importador | Probar transformación y duplicados si cambió el flujo |
| Arquitectura | Actualizar ADR, `README`, `CHANGELOG` y docs relacionadas |
| Infraestructura | Verificar permisos, usuario de ejecución, extensión `redis` y compatibilidad de `APP_URL` |

## Política de sincronización documental

Toda modificación importante en arquitectura, API, importación, configuración o seguridad debe actualizar:

- `README.md` si aplica
- la documentación específica en `docs/`
- el ADR correspondiente
- `docs/CHANGELOG.md` si corresponde

## Revisión previa recomendada

1. Inspeccionar el estado real del repositorio.
2. Verificar rutas, migraciones y configuración.
3. Identificar archivos impactados.
4. Implementar el cambio.
5. Ejecutar pruebas relacionadas.
6. Ejecutar formateador y análisis estático si están disponibles.
7. Actualizar documentación.

## Módulo Agencies

- Mantener el flujo de `Agencies Shalom` centralizado en Actions y Services.
- No sobrescribir la lógica interna de Laravel para autorización.
- No romper el contrato de API pública ni el snapshot de la extensión.
- No reimportar el Gist sobreescribiendo campos manuales de traslado.
- Si el módulo vuelve a devolver 403, revisar `RolesAndPermissionsSeeder`, `AdminSeeder`, `Gate::before` y `AgencyPolicy` antes de tocar Livewire.

## Design System

Toda nueva interfaz debe reutilizar el CodeRED Design System antes de crear estilos personalizados.
No introducir colores hexadecimales sueltos cuando exista un token semántico equivalente.
