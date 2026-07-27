# Auditoría técnica CodeRED Platform - 2026-07-27

## Alcance

Se revisaron Laravel, rutas API/web, migraciones de integraciones, modelos, scripts shell, Docker Compose, CodeRED Agent, paquete n8n, documentación y exposición accidental de secretos mediante busquedas sobre `shared_secret`, `api_key`, `token`, `password`, `secret`, `Authorization`, `X-CodeRED-Signature`, `plainTextToken`, `encrypted_secret`, `CODERED_AGENT_ENCRYPTION_KEY` y `CODERED_AGENT_LOCAL_API_TOKEN` excluyendo dependencias instaladas.

## Hallazgos y correcciones

| Severidad | Archivo | Problema | Impacto | Corrección aplicada | Pendiente | Recomendación |
|---|---|---|---|---|---|---|
| High | `.env.example` | Los secretos del agente tenían un placeholder textual que podía copiarse como valor real. | Instalaciones con claves débiles o repetidas. | Secretos vacíos y comentarios con `openssl rand -hex 32`. | Ninguno. | Mantener secretos fuera de repositorio. |
| High | `docker-compose.yml` | `CODERED_PLATFORM_URL` y `CODERED_AGENT_PUBLIC_URL` estaban hardcodeadas. | Deploys con otro dominio podían emparejar contra URLs incorrectas. | Se usan variables de `.env`. | Ninguno. | Validar `docker compose config` en despliegue. |
| High | `packages/codered-agent/src/server/routes.ts` | `/v1/challenge` respondía sin validar HMAC de la plataforma. | Un actor de red podía obtener respuestas firmadas de challenge si alcanzaba el agente. | Validación de integration UUID, timestamp, nonce, protocolo y firma HMAC. | Migrar nonces a Redis en una fase posterior. | Mantener el agente tras firewall/Cloudflare. |
| High | `packages/codered-agent/src/storage/EncryptedFileStorage.ts` | `integration.enc` se escribía directamente. | Riesgo de archivo corrupto ante corte durante escritura. | Escritura atómica con archivo temporal, rename y permisos `0600`. | Backup automático previo a rotación de clave. | Implementar comando oficial de migración de clave. |
| High | `packages/n8n-nodes-codered` | El modo Legacy podía exponer `shared_secret` en outputs. | Secreto visible en ejecuciones n8n. | El pairing Legacy queda redacted y el modo Agent evita recibir secretos en n8n. | Rotar secretos ya expuestos. | Migrar workflows al agente. |
| Medium | `Install_CodeRED-Platform.sh` | No configuraba CodeRED Agent ni generaba secretos seguros. | Instalación incompleta y manual. | Flujo interactivo del agente, generación segura y healthcheck. | Probar en host con Docker real. | Ejecutar en staging antes de producción. |
| Medium | `update.sh` | No agregaba variables nuevas del agente ni verificaba su salud. | Actualizaciones no reproducibles. | Script por 10 etapas, backup, detección de cambios, cachés y healthcheck condicional. | Probar con un remoto Git real. | Mantener fast-forward para despliegues. |
| Medium | `CodeRED.sh` | No tenía operaciones administrativas del agente. | Operación manual dispersa. | Submenú de estado, logs, pairing, healthcheck y rotación de token local. | Comando oficial para rotar clave de cifrado. | No rotar la clave manualmente. |
| Medium | `routes/web.php` | Rutas administrativas sensibles usan `auth`; algunas dependen de autorización dentro del componente. | Mayor carga sobre componentes Livewire. | Documentado como pendiente, no se cambió para no romper flujos. | Revisar middleware `can` por pantalla. | Centralizar policies/gates en rutas. |
| Low | `public/vendor/livewire` | La búsqueda de secretos encuentra referencias normales a CSRF/token en vendor publicado. | Falsos positivos. | Sin cambio. | Evaluar si conviene no versionar assets vendor publicados. | Excluir vendor publicado de auditorías futuras. |

## Verificaciones específicas

- `normalizeUbigeoCode()` existe en `app/Livewire/Admin/Agencies/Form.php`.
- `ubigeo_code` se normaliza como texto de seis dígitos y se resuelve por `ubigeos.codigo`.
- `ubigeo_id` se mantiene como FK interna separada del código oficial.
- `Install_CodeRED-Platform.sh`, `update.sh` y `CodeRED.sh` no imprimen los secretos generados.
- `.gitignore` protege `.env`, archivos cifrados, llaves y datos persistentes del agente/n8n.

## Pendientes controlados

- Implementar migración segura de `integration.enc` para rotar `CODERED_AGENT_ENCRYPTION_KEY` sin pérdida.
- Sustituir caché de nonces en memoria del agente por Redis cuando haya múltiples réplicas.
- Ejecutar pruebas Docker completas en un entorno con Docker disponible.
- Completar middleware `can` explícito en rutas administrativas donde hoy la autorización vive dentro de Livewire.
## Resultados de verificación

- `composer lint`: correcto después de formatear `CreateN8nPairingCommand`.
- `composer analyse`: correcto, 0 errores PHPStan.
- `composer audit`: sin advisories conocidos.
- `php artisan route:list --path=api/v1/integrations`: 18 rutas de integración disponibles, incluyendo aliases genéricos y compatibilidad `/integrations/n8n/*`.
- `php artisan migrate:status`: migraciones de tokens e integraciones `2026_07_27_000001` a `000004` aparecen pendientes en esta base local; deben aplicarse con `php artisan migrate --force` o mediante `./update.sh`.
- `php artisan test`: 307 pruebas correctas y 13 fallos existentes en módulo Agencies/API por contratos de `centro_operaciones`, `classification_category`, normalización de distrito y texto de ubicación. No se corrigieron en esta fase para no mezclar el cambio de agente con una refactorización funcional de Agencies.
- CodeRED Agent: `npm run typecheck`, `npm run lint`, `npm run test` y `npm run build` correctos.
- CodeRED Agent `npm audit`: 5 vulnerabilidades high transitivas de tooling ESLint (`brace-expansion`/`minimatch`); el fix disponible requiere `npm audit fix --force` con cambio rompedor potencial.
- n8n node: `npm run build` y `npm test` correctos.
- n8n node `npm audit`: 1 moderate y 4 high transitivas desde `n8n-workflow`, `lodash`, `form-data` y `uuid`; requiere actualización coordinada con compatibilidad n8n 2.x.
- Docker: no disponible en este entorno (`docker: command not found`), por lo que no se pudo ejecutar `docker compose build codered-agent` ni healthcheck real de contenedor.