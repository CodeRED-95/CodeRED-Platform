<?php

declare(strict_types=1);

namespace App\Services\DniNameSearch;

use App\Domain\DniNameSearch\Contracts\DniNameSearchProviderInterface;

/**
 * Fuente única de "¿se puede buscar DNI por nombres ahora mismo?".
 *
 * Hacen falta las dos banderas: el interruptor maestro de la función y el del
 * proveedor concreto. Estaba calculado por separado en el servicio, en el
 * componente del panel y ahora también lo necesitan los clientes, así que se
 * centraliza aquí antes de que las tres copias empiecen a discrepar.
 *
 * Se mantienen los dos predicados sueltos además del combinado porque el
 * servicio distingue ambos casos en su mensaje de error, y esa distinción es
 * útil para diagnosticar: no es lo mismo "nadie activó la función" que "la
 * función está activa pero el proveedor no".
 */
final class DniNameSearchAvailability
{
    public function __construct(private readonly DniNameSearchProviderInterface $provider) {}

    public function featureEnabled(): bool
    {
        return (bool) config('dni.name_search.enabled', false);
    }

    public function providerEnabled(): bool
    {
        return $this->provider->isEnabled();
    }

    public function enabled(): bool
    {
        return $this->featureEnabled() && $this->providerEnabled();
    }
}
