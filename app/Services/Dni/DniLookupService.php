<?php

namespace App\Services\Dni;

use App\Domain\Dni\Contracts\DniProviderInterface;
use App\Domain\Dni\Data\DniLookupResult;
use App\Domain\Dni\Repositories\DniRepository;
use App\Jobs\RefreshDniRecordJob;

class DniLookupService
{
    public function __construct(
        private readonly DniRepository $repository,
        private readonly DniCacheService $cache,
        private readonly DniProviderInterface $provider,
        private readonly DniSettingsService $settings,
    ) {}

    public function find(string $dni): DniLookupResult
    {
        $local = $this->repository->findByDni($dni);
        if ($local !== null) {
            if ($local->needsProviderRefresh($this->settings->refreshAfterDays())) {
                RefreshDniRecordJob::dispatchAfterResponse($dni);
            }

            return DniLookupResult::found($this->repository->toData($local), 'internal');
        }

        $cached = $this->cache->get($dni);
        if ($cached !== null) {
            return DniLookupResult::found($cached, 'cache');
        }
        if ($this->cache->isNotFound($dni)) {
            return DniLookupResult::notFound();
        }

        if (! $this->provider->isEnabled()) {
            return DniLookupResult::unavailable('El proveedor externo de DNI no está configurado.');
        }

        $external = $this->provider->find($dni);
        if ($external->status === 'not_found') {
            $this->cache->rememberNotFound($dni);

            return DniLookupResult::notFound(true, $external->statusCode);
        }
        if ($external->status !== 'found' || $external->data === null) {
            $state = $external->status === 'invalid_response' ? 'invalid_response' : 'unavailable';

            return DniLookupResult::unavailable(
                $state === 'invalid_response' ? 'El proveedor externo devolvió una respuesta inválida.' : 'El proveedor externo de DNI no está disponible temporalmente.',
                $external->statusCode,
                $state,
            );
        }

        $data = $external->data;
        if ($this->settings->persistResults()) {
            $data = $this->repository->toData($this->repository->updateOrCreateFromProvider($data));
        }
        $this->cache->put($data);

        return DniLookupResult::found($data, 'perudevs', true, $external->statusCode);
    }
}
