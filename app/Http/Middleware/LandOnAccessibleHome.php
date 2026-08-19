<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth\AuthenticatedHome;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Evita que la portada sea un muro para quien no tiene el dashboard.
 *
 * `/` es el dashboard, y verlo exige `dashboard.view`. Un usuario con el rol
 * viewer no lo tiene: al iniciar sesión AuthenticatedHome ya lo llevaba al
 * listado de agencias, pero bastaba con pulsar el logotipo o abrir un marcador
 * para acabar en un 403 sin menú desde el que volver a ninguna parte.
 *
 * Aquí se aplica el mismo criterio que en el login, en lugar de duplicarlo: si
 * la portada no es para este usuario, se le lleva a la primera pantalla que sí
 * puede abrir. No concede nada —el dashboard sigue comprobando su permiso—;
 * sólo deja de castigar con un callejón sin salida a quien nunca tuvo esa
 * pantalla.
 */
class LandOnAccessibleHome
{
    public function __construct(private readonly AuthenticatedHome $home) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasPermission('dashboard.view')) {
            $destino = $this->home->route($user);

            // Si el destino fuese la propia portada entraríamos en un bucle.
            // AuthenticatedHome sólo la devuelve a quien tiene el permiso, así
            // que esto no debería ocurrir; comprobarlo cuesta menos que
            // depurar una redirección infinita.
            if (parse_url($destino, PHP_URL_PATH) !== $request->getPathInfo()) {
                // Sin el helper redirect(): Livewire sustituye el Redirector del
                // contenedor en cuanto ha renderizado un componente, y el suyo
                // no devuelve una respuesta HTTP.
                return new RedirectResponse($destino);
            }
        }

        return $next($request);
    }
}
