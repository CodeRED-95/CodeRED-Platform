<?php

declare(strict_types=1);

namespace App\Services\DniNameSearch;

use App\Domain\DniNameSearch\Contracts\DniNameSearchProviderInterface;
use App\Domain\DniNameSearch\Data\DniNameSearchResult;

final class DniNameSearchService
{
    public function __construct(
        private readonly DniNameSearchProviderInterface $provider,
        private readonly DniNameSearchCacheService $cache,
        private readonly DniNameSearchAvailability $availability,
    ) {}

    public function search(string $nombres, string $paterno, string $materno): DniNameSearchResult
    {
        $nombres = $this->normalize($nombres);
        $paterno = $this->normalize($paterno);
        $materno = $this->normalize($materno);

        // Interruptor maestro de la funcionalidad. Va antes que el del proveedor
        // para que DNI_NAME_SEARCH_ENABLED=false apague la búsqueda completa
        // aunque un proveedor concreto siga habilitado. Los dos predicados salen
        // de DniNameSearchAvailability, que es lo que informa también al panel y
        // al bloque `features` de /auth/me: así ningún cliente ve como
        // disponible algo que este servicio va a rechazar con 503.
        if (! $this->availability->featureEnabled()) {
            return DniNameSearchResult::failed('provider_disabled', 503, 'La búsqueda de DNI por nombres está desactivada.');
        }

        if (! $this->availability->providerEnabled()) {
            return DniNameSearchResult::failed('provider_disabled', 503, 'El proveedor de DNI por nombres no está disponible.');
        }

        if ((bool) config('dni.name_search.cache_enabled', true)) {
            $cached = $this->cache->get($nombres, $paterno, $materno);
            if ($cached !== null) {
                return DniNameSearchResult::found($cached, 200, true);
            }
        }

        $result = $this->provider->search($nombres, $paterno, $materno);
        if ($result->status === 'found' && (bool) config('dni.name_search.cache_enabled', true)) {
            $this->cache->put($nombres, $paterno, $materno, $result->matches);
        }

        return $result;
    }

    private function normalize(string $value): string
    {
        $value = trim(preg_replace('/\\s+/u', ' ', $value) ?? $value);

        return mb_strtoupper($value);
    }
}
