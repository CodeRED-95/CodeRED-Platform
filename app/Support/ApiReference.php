<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fuente de datos de la documentación pública de la API (`/docs`).
 *
 * Refleja los endpoints REALES declarados en routes/api.php. No inventa rutas:
 * cada entrada corresponde a un endpoint existente con su método, ruta,
 * ability/permiso y ejemplos. La vista solo la recorre y la pinta con el Design
 * System; toda la información vive aquí para mantenerla en un único sitio.
 */
final class ApiReference
{
    public const BASE_URL = 'https://platform.codered.lat/api/v1';

    /**
     * Códigos de error comunes a toda la API.
     *
     * @return array<int, array{code: string, tone: string, title: string, description: string}>
     */
    public static function commonErrors(): array
    {
        return [
            ['code' => '400', 'tone' => 'warning', 'title' => 'Solicitud inválida', 'description' => 'La petición está mal formada o falta información obligatoria.'],
            ['code' => '401', 'tone' => 'danger', 'title' => 'No autenticado', 'description' => 'Falta el token Bearer o es inválido, expiró o fue revocado.'],
            ['code' => '403', 'tone' => 'danger', 'title' => 'Sin permiso', 'description' => 'El token es válido pero no incluye la ability requerida por el endpoint.'],
            ['code' => '404', 'tone' => 'neutral', 'title' => 'No encontrado', 'description' => 'El recurso solicitado no existe o el identificador es incorrecto.'],
            ['code' => '422', 'tone' => 'warning', 'title' => 'Validación fallida', 'description' => 'Los parámetros no pasaron validación. El cuerpo detalla los campos en `errors`.'],
            ['code' => '429', 'tone' => 'warning', 'title' => 'Límite de peticiones', 'description' => 'Se superó el límite por minuto del token. Espera antes de reintentar.'],
            ['code' => '500', 'tone' => 'danger', 'title' => 'Error interno', 'description' => 'Fallo inesperado del servidor. Reintenta más tarde o contacta a soporte.'],
        ];
    }

    /**
     * Secciones de la documentación, en orden de navegación.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sections(): array
    {
        $rate = (int) config('api.rate_limit_per_minute', 60);
        $maxPerPage = (int) config('api.max_per_page', 100);
        $rucRate = (int) config('ruc.rate_limit_per_minute', 30);

        return [
            self::introduction($rate, $maxPerPage),
            self::authentication(),
            self::tokens(),
            self::agencies($maxPerPage),
            self::ruc($rucRate),
            self::dni(),
            self::shalomRecordar(),
            self::integrations(),
            self::errors(),
        ];
    }

    private static function introduction(int $rate, int $maxPerPage): array
    {
        return [
            'id' => 'introduccion',
            'title' => 'Introducción',
            'icon' => '◆',
            'description' => 'CodeRED Platform expone una API REST versionada (v1) para consultar agencias Shalom, RUC, DNI y gestionar integraciones. Las respuestas son JSON con codificación UTF-8.',
            'notes' => [
                ['tone' => 'info', 'title' => 'Base URL', 'body' => self::BASE_URL],
                ['tone' => 'neutral', 'title' => 'Límite de peticiones', 'body' => $rate.' peticiones por minuto y token (algunos módulos tienen su propio límite).'],
                ['tone' => 'neutral', 'title' => 'Paginación', 'body' => 'Máximo '.$maxPerPage.' registros por página en los listados.'],
            ],
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/v1/health',
                    'title' => 'Estado del servicio',
                    'ability' => null,
                    'auth' => false,
                    'description' => 'Comprueba que la API responde. No requiere autenticación.',
                    'params' => [],
                    'request' => 'curl -s https://platform.codered.lat/api/v1/health',
                    'response' => self::json(['status' => 'ok', 'timestamp' => '2026-08-10T12:00:00Z']),
                    'errors' => ['500'],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/version',
                    'title' => 'Versión de la plataforma',
                    'ability' => null,
                    'auth' => false,
                    'description' => 'Devuelve la versión desplegada y la versión de la API. No requiere autenticación.',
                    'params' => [],
                    'request' => 'curl -s https://platform.codered.lat/api/v1/version',
                    'response' => self::json(['success' => true, 'data' => ['version' => '4.5.3', 'api_version' => 'v1', 'environment' => 'production']]),
                    'errors' => ['500'],
                ],
            ],
        ];
    }

    private static function authentication(): array
    {
        return [
            'id' => 'autenticacion',
            'title' => 'Autenticación',
            'icon' => '⚿',
            'description' => 'La API usa Laravel Sanctum con tokens Bearer. Cada endpoint protegido exige una ability concreta; el token solo puede llamar a lo que sus abilities permiten.',
            'notes' => [
                ['tone' => 'brand', 'title' => 'Cabecera', 'body' => 'Authorization: Bearer <tu-token>'],
                ['tone' => 'neutral', 'title' => 'Abilities', 'body' => 'Cada token lleva una lista de abilities (p. ej. agencies:read, ruc:consultar). Un token de administrador puede llevar `*`.'],
                ['tone' => 'warning', 'title' => 'Expiración y revocación', 'body' => 'Los tokens caducan según su fecha de expiración y pueden revocarse desde el panel. Un token caducado o revocado responde 401.'],
                ['tone' => 'danger', 'title' => '401 vs 403', 'body' => '401 = token ausente, inválido, expirado o revocado. 403 = token válido pero sin la ability requerida.'],
            ],
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/v1/me',
                    'title' => 'Identidad del token',
                    'ability' => 'profile:read',
                    'auth' => true,
                    'description' => 'Devuelve el usuario y las abilities asociadas al token con el que se autentica la petición. Útil para verificar credenciales.',
                    'params' => [],
                    'request' => self::curl('GET', '/me'),
                    'response' => self::json(['success' => true, 'data' => ['id' => 12, 'name' => 'Operaciones', 'abilities' => ['agencies:read', 'ruc:consultar']]]),
                    'errors' => ['401', '403'],
                ],
            ],
        ];
    }

    private static function tokens(): array
    {
        return [
            'id' => 'tokens',
            'title' => 'Tokens',
            'icon' => '◇',
            'description' => 'Flujo público de solicitud de tokens de API y rotación de un token existente. La emisión final del token la aprueba un administrador.',
            'notes' => [
                ['tone' => 'info', 'title' => 'Solicitud pública', 'body' => 'La creación de una solicitud no requiere token; queda pendiente de aprobación y entrega segura.'],
            ],
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/v1/token-requests',
                    'title' => 'Solicitar un token',
                    'ability' => null,
                    'auth' => false,
                    'description' => 'Crea una solicitud de token que un administrador revisará y aprobará. Devuelve un identificador de seguimiento.',
                    'params' => [
                        ['name' => 'requester_name', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Nombre de quien solicita.'],
                        ['name' => 'requested_abilities', 'in' => 'body', 'type' => 'array', 'required' => true, 'description' => 'Abilities solicitadas, p. ej. ["agencies:read"].'],
                    ],
                    'request' => self::curl('POST', '/token-requests', ['requester_name' => 'Ada Lovelace', 'requested_abilities' => ['agencies:read']], false),
                    'response' => self::json(['success' => true, 'data' => ['request_uuid' => '3b1c…', 'status' => 'pending']]),
                    'errors' => ['422', '429'],
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/v1/token-requests/rotation',
                    'title' => 'Rotar un token',
                    'ability' => null,
                    'auth' => true,
                    'description' => 'Solicita la rotación del token con el que se autentica: se emite un reemplazo y el anterior se revoca al confirmarse.',
                    'params' => [],
                    'request' => self::curl('POST', '/token-requests/rotation'),
                    'response' => self::json(['success' => true, 'data' => ['request_uuid' => '9f2a…', 'status' => 'pending']]),
                    'errors' => ['401', '429'],
                ],
            ],
        ];
    }

    private static function agencies(int $maxPerPage): array
    {
        return [
            'id' => 'agencias',
            'title' => 'Agencias',
            'icon' => '◎',
            'description' => 'Catálogo de agencias Shalom: listado, búsqueda, cambios incrementales y detalle. Todos los endpoints requieren la ability agencies:read (o agencias:consultar en el contrato legado).',
            'notes' => [
                ['tone' => 'neutral', 'title' => 'Solo lectura', 'body' => 'Los endpoints de agencias no modifican datos.'],
                ['tone' => 'neutral', 'title' => 'Sincronización', 'body' => 'Usa /agencies/changes con cursor para replicar cambios de forma incremental.'],
            ],
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/v1/agencies',
                    'title' => 'Listar agencias',
                    'ability' => 'agencies:read',
                    'auth' => true,
                    'description' => 'Lista agencias con paginación por cursor.',
                    'params' => [
                        ['name' => 'per_page', 'in' => 'query', 'type' => 'integer', 'required' => false, 'description' => 'Registros por página (máximo '.$maxPerPage.').'],
                        ['name' => 'cursor', 'in' => 'query', 'type' => 'string', 'required' => false, 'description' => 'Cursor de paginación devuelto en meta.next_cursor.'],
                    ],
                    'request' => self::curl('GET', '/agencies?per_page=2'),
                    'response' => self::json([
                        'data' => [[
                            'code' => 'CHH', 'agencia' => 'CHACHAPOYAS CO DOS DE MAYO',
                            'departamento' => 'AMAZONAS', 'provincia' => 'CHACHAPOYAS', 'distrito' => 'CHACHAPOYAS',
                            'estado' => 'Activa', 'centro_operaciones' => false,
                        ]],
                        'meta' => ['next_cursor' => 'eyJpZCI6M30', 'per_page' => 2],
                    ]),
                    'errors' => ['401', '403', '429'],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/agencies/search',
                    'title' => 'Buscar agencias',
                    'ability' => 'agencies:read',
                    'auth' => true,
                    'description' => 'Busca agencias por texto (code, nombre o identificador).',
                    'params' => [
                        ['name' => 'q', 'in' => 'query', 'type' => 'string', 'required' => true, 'description' => 'Término de búsqueda.'],
                    ],
                    'request' => self::curl('GET', '/agencies/search?q=chachapoyas'),
                    'response' => self::json(['data' => [['code' => 'CHH', 'agencia' => 'CHACHAPOYAS CO DOS DE MAYO']]]),
                    'errors' => ['401', '403', '422', '429'],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/agencies/changes',
                    'title' => 'Cambios incrementales',
                    'ability' => 'agencies:read',
                    'auth' => true,
                    'description' => 'Devuelve las agencias creadas, actualizadas o eliminadas desde un cursor, para replicación incremental.',
                    'params' => [
                        ['name' => 'cursor', 'in' => 'query', 'type' => 'string', 'required' => false, 'description' => 'Cursor de la última sincronización.'],
                    ],
                    'request' => self::curl('GET', '/agencies/changes'),
                    'response' => self::json(['data' => [['operation' => 'upsert', 'code' => 'CHH']], 'meta' => ['next_cursor' => 'eyJpZCI6NX0']]),
                    'errors' => ['401', '403', '429'],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/agencies/{code}',
                    'title' => 'Detalle de agencia',
                    'ability' => 'agencies:read',
                    'auth' => true,
                    'description' => 'Devuelve una agencia por su code.',
                    'params' => [
                        ['name' => 'code', 'in' => 'path', 'type' => 'string', 'required' => true, 'description' => 'Código de la agencia.'],
                    ],
                    'request' => self::curl('GET', '/agencies/CHH'),
                    'response' => self::json(['data' => ['code' => 'CHH', 'agencia' => 'CHACHAPOYAS CO DOS DE MAYO', 'estado' => 'Activa']]),
                    'errors' => ['401', '403', '404', '429'],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/catalog/metadata',
                    'title' => 'Metadatos del catálogo',
                    'ability' => 'agencies:read',
                    'auth' => true,
                    'description' => 'Devuelve metadatos del catálogo (versión de datos, totales, valores de filtro).',
                    'params' => [],
                    'request' => self::curl('GET', '/catalog/metadata'),
                    'response' => self::json(['data' => ['data_version' => 128, 'total' => 544]]),
                    'errors' => ['401', '403', '429'],
                ],
            ],
        ];
    }

    private static function ruc(int $rucRate): array
    {
        return [
            'id' => 'ruc',
            'title' => 'RUC',
            'icon' => '▦',
            'description' => 'Consulta del padrón RUC por número exacto o por búsqueda. Requiere abilities ruc:consultar o ruc:buscar. Tiene su propio límite de '.$rucRate.' peticiones por minuto.',
            'notes' => [],
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/v1/ruc/{ruc}',
                    'title' => 'Consultar RUC',
                    'ability' => 'ruc:consultar',
                    'auth' => true,
                    'description' => 'Devuelve los datos del contribuyente por su RUC de 11 dígitos.',
                    'params' => [
                        ['name' => 'ruc', 'in' => 'path', 'type' => 'string', 'required' => true, 'description' => 'RUC de 11 dígitos.'],
                    ],
                    'request' => self::curl('GET', '/ruc/20123456789'),
                    'response' => self::json(['success' => true, 'data' => ['ruc' => '20123456789', 'razon_social' => 'EMPRESA DEMO S.A.C.', 'estado' => 'ACTIVO', 'condicion' => 'HABIDO']]),
                    'errors' => ['401', '403', '404', '429'],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/ruc/buscar',
                    'title' => 'Buscar RUC',
                    'ability' => 'ruc:buscar',
                    'auth' => true,
                    'description' => 'Busca contribuyentes por razón social u otros criterios.',
                    'params' => [
                        ['name' => 'q', 'in' => 'query', 'type' => 'string', 'required' => true, 'description' => 'Término de búsqueda.'],
                    ],
                    'request' => self::curl('GET', '/ruc/buscar?q=empresa%20demo'),
                    'response' => self::json(['success' => true, 'data' => [['ruc' => '20123456789', 'razon_social' => 'EMPRESA DEMO S.A.C.']]]),
                    'errors' => ['401', '403', '422', '429'],
                ],
            ],
        ];
    }

    private static function dni(): array
    {
        return [
            'id' => 'dni',
            'title' => 'DNI',
            'icon' => '⌕',
            'description' => 'Consulta de datos de identidad por DNI. Requiere la ability dni:consultar.',
            'notes' => [],
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/v1/dni/{dni}',
                    'title' => 'Consultar DNI',
                    'ability' => 'dni:consultar',
                    'auth' => true,
                    'description' => 'Devuelve nombres y apellidos asociados a un DNI de 8 dígitos.',
                    'params' => [
                        ['name' => 'dni', 'in' => 'path', 'type' => 'string', 'required' => true, 'description' => 'DNI de 8 dígitos.'],
                    ],
                    'request' => self::curl('GET', '/dni/12345678'),
                    'response' => self::json(['success' => true, 'data' => ['dni' => '12345678', 'nombres' => 'ADA', 'apellido_paterno' => 'LOVELACE', 'apellido_materno' => 'BYRON', 'nombre_completo' => 'ADA LOVELACE BYRON']]),
                    'errors' => ['401', '403', '404', '429'],
                ],
            ],
        ];
    }

    private static function shalomRecordar(): array
    {
        return [
            'id' => 'shalom-recordar',
            'title' => 'Shalom Recordar',
            'icon' => '◫',
            'description' => 'Endpoints que usa la extensión Shalom Recordar: inicio de sesión, sincronización de registros, estado y cierre de sesión. El login emite un token por instalación.',
            'notes' => [
                ['tone' => 'info', 'title' => 'Token por instalación', 'body' => 'El login devuelve un sync_token ligado a la instalación; los demás endpoints lo usan como Bearer.'],
            ],
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/v1/shalom-recordar/auth/login',
                    'title' => 'Iniciar sesión',
                    'ability' => null,
                    'auth' => false,
                    'description' => 'Valida credenciales y registra la instalación, devolviendo un token de sincronización. La contraseña no se almacena.',
                    'params' => [
                        ['name' => 'email', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Correo del usuario.'],
                        ['name' => 'password', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Contraseña.'],
                        ['name' => 'installation_uuid', 'in' => 'body', 'type' => 'uuid', 'required' => true, 'description' => 'Identificador de la instalación.'],
                    ],
                    'request' => self::curl('POST', '/shalom-recordar/auth/login', ['email' => 'usuario@correo.lat', 'password' => '••••••', 'installation_uuid' => '550e8400-…'], false),
                    'response' => self::json(['success' => true, 'data' => ['user' => ['id' => 12, 'name' => 'Victor'], 'sync_token' => '<token>', 'abilities' => ['shalom-recordar:sync']]]),
                    'errors' => ['401', '422', '429'],
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/v1/shalom-recordar/sync',
                    'title' => 'Sincronizar registros',
                    'ability' => 'shalom-recordar:sync',
                    'auth' => true,
                    'description' => 'Envía un lote de registros capturados por la extensión.',
                    'params' => [
                        ['name' => 'installation_uuid', 'in' => 'body', 'type' => 'uuid', 'required' => true, 'description' => 'Instalación que sincroniza.'],
                        ['name' => 'records', 'in' => 'body', 'type' => 'array', 'required' => true, 'description' => 'Registros con field, value y timestamp (Y-m-d\\TH:i:s\\Z).'],
                    ],
                    'request' => self::curl('POST', '/shalom-recordar/sync', ['installation_uuid' => '550e8400-…', 'records' => [['field' => 'DNI', 'value' => '12345678', 'timestamp' => '2026-08-10T12:00:00Z']]]),
                    'response' => self::json(['success' => true, 'data' => ['created' => 1, 'updated' => 0]]),
                    'errors' => ['401', '403', '422', '429'],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/shalom-recordar/sync/status',
                    'title' => 'Estado de la sesión',
                    'ability' => 'shalom-recordar:sync',
                    'auth' => true,
                    'description' => 'Devuelve el usuario del token, la instalación y el número de registros sincronizados.',
                    'params' => [],
                    'request' => self::curl('GET', '/shalom-recordar/sync/status'),
                    'response' => self::json(['success' => true, 'data' => ['user' => ['id' => 12, 'email' => 'usuario@correo.lat'], 'records_count' => 42]]),
                    'errors' => ['401', '403', '429'],
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/v1/shalom-recordar/auth/logout',
                    'title' => 'Cerrar sesión',
                    'ability' => 'shalom-recordar:sync',
                    'auth' => true,
                    'description' => 'Revoca el token en uso. No borra los registros ya sincronizados.',
                    'params' => [],
                    'request' => self::curl('POST', '/shalom-recordar/auth/logout'),
                    'response' => self::json(['success' => true, 'message' => 'Sesión cerrada correctamente.']),
                    'errors' => ['401', '429'],
                ],
            ],
        ];
    }

    private static function integrations(): array
    {
        return [
            'id' => 'integraciones',
            'title' => 'Integraciones',
            'icon' => '⇄',
            'description' => 'Endpoints para integraciones máquina a máquina (CodeRED Agent / n8n) y para la extensión Chrome. La mayoría se autentican con firma de integración, no con token de usuario.',
            'notes' => [
                ['tone' => 'warning', 'title' => 'Firma de integración', 'body' => 'Los endpoints /integrations/* validan una firma HMAC del emparejamiento; no usan Bearer de usuario.'],
            ],
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/v1/integrations/n8n/pair',
                    'title' => 'Emparejar integración',
                    'ability' => null,
                    'auth' => false,
                    'description' => 'Inicia el emparejamiento de una integración n8n con la plataforma.',
                    'params' => [
                        ['name' => 'code', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Código de emparejamiento generado en el panel.'],
                    ],
                    'request' => self::curl('POST', '/integrations/n8n/pair', ['code' => 'PAIR-XXXX'], false),
                    'response' => self::json(['success' => true, 'data' => ['integration_uuid' => 'a1b2…', 'status' => 'paired']]),
                    'errors' => ['401', '422', '429'],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/extension/chrome/config',
                    'title' => 'Configuración de la extensión',
                    'ability' => null,
                    'auth' => false,
                    'description' => 'Devuelve la configuración pública que la extensión Chrome necesita para operar.',
                    'params' => [],
                    'request' => 'curl -s https://platform.codered.lat/api/v1/extension/chrome/config',
                    'response' => self::json(['success' => true, 'data' => ['min_version' => '2.7.0', 'features' => ['sync' => true]]]),
                    'errors' => ['429'],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/extension/chrome/block-rules',
                    'title' => 'Reglas de bloqueo horario',
                    'ability' => null,
                    'auth' => true,
                    'description' => 'Horarios de bloqueo configurados en el panel. Cualquier token válido puede leerlas: la configuración es global para todas las instalaciones.',
                    'params' => [],
                    'request' => self::curl('GET', '/extension/chrome/block-rules'),
                    'response' => self::json(['success' => true, 'data' => [
                        'version' => 'a1b2c3…',
                        'generated_at' => '2026-08-24T08:00:00-05:00',
                        'rules' => [[
                            'id' => 1,
                            'label' => 'Service Order',
                            'host_pattern' => 'sysnewos.shalomcontrol.com',
                            'host_patterns' => ['sysnewos.shalomcontrol.com', 'sysprovincia2.shalomcontrol.com'],
                            'destinations' => [
                                ['host_pattern' => 'sysnewos.shalomcontrol.com', 'path_pattern' => '/service-order'],
                                ['host_pattern' => 'sysprovincia2.shalomcontrol.com', 'path_pattern' => '/ordenservicio/listar'],
                            ],
                            'path_pattern' => '/service-order',
                            'window_mode' => 'allowed',
                            'timezone' => 'America/Lima',
                            'windows' => [['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '20:05']],
                        ]],
                    ]]),
                    'errors' => ['401', '429'],
                ],
            ],
        ];
    }

    private static function errors(): array
    {
        return [
            'id' => 'errores',
            'title' => 'Errores comunes',
            'icon' => '⚠',
            'description' => 'Todas las respuestas de error usan JSON. Los errores de validación (422) incluyen un objeto `errors` con los campos que fallaron.',
            'error_table' => self::commonErrors(),
            'notes' => [],
            'endpoints' => [],
            'examples' => [
                ['title' => 'Respuesta 401', 'code' => self::json(['message' => 'Unauthenticated.'])],
                ['title' => 'Respuesta 403', 'code' => self::json(['message' => 'This action is unauthorized.'])],
                ['title' => 'Respuesta 422', 'code' => self::json(['message' => 'Los datos proporcionados no son válidos.', 'errors' => ['q' => ['El campo q es obligatorio.']]])],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function curl(string $method, string $path, array $body = [], bool $auth = true): string
    {
        $lines = ['curl -s -X '.$method.' \\', '  '.self::BASE_URL.$path.' \\'];

        if ($auth) {
            $lines[] = "  -H 'Authorization: Bearer <tu-token>' \\";
        }

        $lines[] = "  -H 'Accept: application/json'".($body !== [] ? ' \\' : '');

        if ($body !== []) {
            $lines[] = "  -H 'Content-Type: application/json' \\";
            $lines[] = "  -d '".json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."'";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function json(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
