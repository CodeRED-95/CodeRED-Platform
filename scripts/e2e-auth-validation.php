<?php

/**
 * Validación integral del ecosistema CodeRED contra la instancia real.
 *
 * Se ejecuta dentro del contenedor de la aplicación y llama a la API por HTTP
 * interno, de modo que ejercita la pila completa —rutas, middleware, Sanctum,
 * base de datos— sin depender de la red pública ni chocar con el rate limiting
 * por IP del login.
 *
 * Crea un usuario temporal y lo borra al terminar. No toca ningún dato real.
 */

// El script vive en scripts/, asi que la raiz del proyecto es el directorio
// padre. Con __DIR__ a secas buscaria el autoload dentro de scripts/.
$raiz = dirname(__DIR__);

require $raiz.'/vendor/autoload.php';

$app = require_once $raiz.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ClientSession;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

$fallos = 0;

function ok(string $texto): void
{
    echo "  OK    $texto\n";
}

function fallo(string $texto): void
{
    global $fallos;
    $fallos++;
    echo "  FALLO $texto\n";
}

function comprobar(string $texto, bool $condicion, string $detalle = ''): void
{
    $condicion ? ok($texto) : fallo($texto.($detalle !== '' ? " -> $detalle" : ''));
}

/**
 * Ejecuta una petición HTTP real a través del kernel: mismas rutas, mismo
 * middleware y misma autorización que una llamada desde Mobile o Desktop.
 *
 * @return array{status:int, body:array<string,mixed>}
 */
function api(string $method, string $uri, array $payload = [], ?string $token = null): array
{
    // Todas las peticiones comparten un mismo proceso, asi que el guard conserva
    // el usuario que resolvio la primera vez y un Bearer distinto seria
    // ignorado. En produccion cada peticion arranca limpia; aqui hay que
    // forzarlo o las comprobaciones medirian la sesion equivocada.
    app('auth')->forgetGuards();

    /** @var Illuminate\Contracts\Http\Kernel $kernel */
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);

    $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];

    if ($token !== null) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
    }

    $request = Request::create($uri, $method, [], [], [], $server, $payload === [] ? null : json_encode($payload));

    $response = $kernel->handle($request);

    return [
        'status' => $response->getStatusCode(),
        'body' => json_decode($response->getContent(), true) ?? [],
    ];
}

function login(string $email, string $password, string $application): array
{
    // El limite de intentos es por IP y por correo; una validacion que hace
    // varios accesos seguidos lo agota y el 429 taparia el resultado real.
    //
    // ThrottleRequests no guarda la clave del limitador tal cual: la prefija con
    // el nombre del limitador y le aplica md5. Hay que reproducir esa derivacion
    // o el clear no encuentra nada que limpiar.
    foreach ([
        'auth-login-ip:127.0.0.1',
        'auth-login-user:'.hash_hmac('sha256', mb_strtolower(trim($email)), config('app.key')),
    ] as $clave) {
        RateLimiter::clear(md5('auth-login'.$clave));
    }

    return api('POST', '/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
        'application' => $application,
        'device_name' => 'validacion-'.$application,
        'platform' => 'e2e',
    ]);
}

// ---------------------------------------------------------------- preparación

$email = 'e2e-'.time().'@codered.lat';
$password = 'Validacion-Integral-2026';

$usuario = User::create([
    'name' => 'Validación Integral',
    'email' => $email,
    'password' => Hash::make($password),
    'status' => 'active',
    'is_active' => true,
]);

$rol = Role::firstOrCreate(['slug' => 'e2e-integral'], ['name' => 'Validación Integral']);
$rol->permissions()->sync(
    Permission::whereIn('slug', ['platform.access', 'mobile.access', 'desktop.access', 'dni-records.view', 'ruc.view'])->pluck('id')
);
$usuario->roles()->sync([$rol->id]);
$usuario->refresh();

echo "Usuario temporal: {$email} (id {$usuario->id})\n\n";

// ------------------------------------------------- 1. una cuenta, tres clientes

echo "=== 1. Una sola cuenta CodeRED entra en los tres clientes ===\n";

$sesiones = [];

foreach (['platform', 'mobile', 'desktop'] as $aplicacion) {
    $r = login($email, $password, $aplicacion);
    comprobar("login $aplicacion", $r['status'] === 200, 'HTTP '.$r['status'].' '.json_encode($r['body']));
    $sesiones[$aplicacion] = $r['body']['data'] ?? [];
}

$desktop = $sesiones['desktop'];
$acceso = $desktop['access_token'] ?? '';
$refresco = $desktop['refresh_token'] ?? '';

// ------------------------------------------------- 2. mismos permisos en todos

echo "\n=== 2. Los mismos roles y permisos en los tres ===\n";

$me = api('GET', '/api/v1/auth/me', [], $acceso);
comprobar('/auth/me responde', $me['status'] === 200, 'HTTP '.$me['status']);
comprobar('permisos presentes', in_array('dni-records.view', $me['body']['data']['permissions'] ?? [], true));
comprobar(
    'las tres aplicaciones permitidas',
    ($me['body']['data']['applications'] ?? []) === ['platform', 'mobile', 'desktop'],
    json_encode($me['body']['data']['applications'] ?? [])
);
comprobar('no se exponen secretos', ! str_contains(json_encode($me['body']), 'password'));

// --------------------------------- 3. consultas sin token manual, por permiso

echo "\n=== 3. Consultas autorizadas por el permiso del usuario, sin token manual ===\n";

$dni = api('GET', '/api/v1/dni/71218478', [], $acceso);
comprobar('DNI autorizado', $dni['status'] !== 403, 'HTTP '.$dni['status']);

$ruc = api('GET', '/api/v1/ruc/20512528458', [], $acceso);
comprobar('RUC autorizado', $ruc['status'] !== 403, 'HTTP '.$ruc['status']);

// ---------------------------------------- 4. retirar permiso corta el acceso

echo "\n=== 4. Retirar un permiso corta el acceso sin renovar nada ===\n";

$rol->permissions()->detach(Permission::where('slug', 'ruc.view')->value('id'));

$ruc = api('GET', '/api/v1/ruc/20512528458', [], $acceso);
comprobar('RUC bloqueado con el MISMO access token', $ruc['status'] === 403, 'HTTP '.$ruc['status']);

$dni = api('GET', '/api/v1/dni/71218478', [], $acceso);
comprobar('DNI sigue autorizado', $dni['status'] !== 403, 'HTTP '.$dni['status']);

// ---------------------------------------------------- 5. refresh con rotación

echo "\n=== 5. El refresh rota y el anterior deja de servir ===\n";

$renovado = api('POST', '/api/v1/auth/refresh', ['refresh_token' => $refresco]);
comprobar('refresh válido', $renovado['status'] === 200, 'HTTP '.$renovado['status'].' '.json_encode($renovado['body']));

$refrescoNuevo = $renovado['body']['data']['refresh_token'] ?? '';
comprobar('el refresh rotó', $refrescoNuevo !== '' && $refrescoNuevo !== $refresco);

$reutilizado = api('POST', '/api/v1/auth/refresh', ['refresh_token' => $refresco]);
comprobar('reutilizar el refresh anterior es rechazado', $reutilizado['status'] === 401, 'HTTP '.$reutilizado['status']);

// ------------------------------------------------------- 6. revocación remota

echo "\n=== 6. Revocación remota desde Platform ===\n";

// La reutilización acaba de cerrar la sesión de Desktop: eso ya es una
// revocación. Se comprueba además la revocación explícita sobre Mobile.
$accesoMobile = $sesiones['mobile']['access_token'] ?? '';
$uuidMobile = $sesiones['mobile']['session']['uuid'] ?? '';

$antes = api('GET', '/api/v1/auth/me', [], $accesoMobile);
comprobar('la sesión de Mobile funciona antes de revocar', $antes['status'] === 200, 'HTTP '.$antes['status']);

$sesionMobile = ClientSession::where('uuid', $uuidMobile)->first();
app(App\Services\Auth\ClientSessionManager::class)->revoke($sesionMobile, null, 'e2e');

$despues = api('GET', '/api/v1/auth/me', [], $accesoMobile);
comprobar('tras revocar deja de servir de inmediato', $despues['status'] === 401, 'HTTP '.$despues['status']);

$refrescoMobile = $sesiones['mobile']['refresh_token'] ?? '';
$reintento = api('POST', '/api/v1/auth/refresh', ['refresh_token' => $refrescoMobile]);
comprobar('y su refresh tampoco sirve', $reintento['status'] === 401, 'HTTP '.$reintento['status']);

// ------------------------------------------------------ 7. usuario desactivado

echo "\n=== 7. Desactivar la cuenta bloquea los tres clientes ===\n";

$sesionPlatform = $sesiones['platform']['access_token'] ?? '';
$usuario->forceFill(['status' => 'inactive', 'is_active' => false])->save();

$bloqueada = api('GET', '/api/v1/auth/me', [], $sesionPlatform);
comprobar('la sesión abierta queda bloqueada', $bloqueada['status'] === 401, 'HTTP '.$bloqueada['status']);

$nuevoLogin = login($email, $password, 'mobile');
comprobar('el login queda bloqueado', $nuevoLogin['status'] === 403, 'HTTP '.$nuevoLogin['status']);

// ------------------------------------- 8. acceso por aplicación, individualmente

echo "\n=== 8. El acceso por aplicación se controla por separado ===\n";

$usuario->forceFill(['status' => 'active', 'is_active' => true])->save();
$rol->permissions()->detach(Permission::where('slug', 'desktop.access')->value('id'));

$sinDesktop = login($email, $password, 'desktop');
comprobar('sin desktop.access no entra en Desktop', $sinDesktop['status'] === 403, 'HTTP '.$sinDesktop['status']);
comprobar(
    'y el mensaje lo explica',
    str_contains($sinDesktop['body']['message'] ?? '', 'CodeRED Desktop'),
    $sinDesktop['body']['message'] ?? ''
);

$conMobile = login($email, $password, 'mobile');
comprobar('pero sigue entrando en Mobile', $conMobile['status'] === 200, 'HTTP '.$conMobile['status']);

// ------------------------------------------- 9. tokens de API sin cambio alguno

echo "\n=== 9. Compatibilidad: los tokens de API siguen funcionando ===\n";

$integracion = $usuario->createToken('validacion-n8n', ['ruc:consultar']);
$plano = $integracion->plainTextToken;

// El valor lo pone la base de datos, asi que hay que releer la fila: el modelo
// recien creado en memoria todavia no lo tiene.
$kind = $integracion->accessToken->fresh()->kind;
comprobar('el token nace como integración', $kind === 'integration', (string) $kind);

// Autoriza por ability aunque el usuario NO tenga el permiso RBAC de RUC:
// es exactamente el caso de n8n y del resto de integraciones.
$rucToken = api('GET', '/api/v1/ruc/20512528458', [], $plano);
comprobar('autoriza por ability, no por RBAC', $rucToken['status'] !== 403, 'HTTP '.$rucToken['status']);

$dniToken = api('GET', '/api/v1/dni/71218478', [], $plano);
comprobar('y sigue sin poder lo que no declara', $dniToken['status'] === 403, 'HTTP '.$dniToken['status']);

echo "\n=== 10. El contrato antiguo sigue en pie para clientes publicados ===\n";

$legado = api('POST', '/api/v1/mobile/login', ['email' => $email, 'password' => $password, 'device_name' => 'legacy']);
comprobar('/mobile/login responde', in_array($legado['status'], [200, 422], true), 'HTTP '.$legado['status']);

$tokenLegado = $legado['body']['data']['token'] ?? null;

if ($tokenLegado !== null) {
    $legadoMe = api('GET', '/api/v1/mobile/me', [], $tokenLegado);
    comprobar('/mobile/me responde con el token antiguo', $legadoMe['status'] === 200, 'HTTP '.$legadoMe['status']);
}

// -------------------------------------------------------------------- limpieza

echo "\n=== Limpieza ===\n";

ClientSession::where('user_id', $usuario->id)->delete();
Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $usuario->id)->delete();
$usuario->roles()->detach();

// El observador de usuarios escribe una entrada de auditoria al borrar, y esa
// entrada referencia al usuario que ya no existe. Se limpian primero sus
// registros y se borra la fila sin pasar por el modelo.
Illuminate\Support\Facades\DB::table('activity_logs')->where('user_id', $usuario->id)->delete();
Illuminate\Support\Facades\DB::table('activity_logs')
    ->where('auditable_type', App\Models\User::class)
    ->where('auditable_id', $usuario->id)
    ->delete();
Illuminate\Support\Facades\DB::table('users')->where('id', $usuario->id)->delete();
Role::where('slug', 'e2e-integral')->delete();

echo "Usuario temporal eliminado.\n";

echo "\n".($fallos === 0 ? 'RESULTADO: todo en verde' : "RESULTADO: {$fallos} fallo(s)")."\n";

exit($fallos === 0 ? 0 : 1);
