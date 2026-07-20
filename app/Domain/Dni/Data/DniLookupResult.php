<?php

namespace App\Domain\Dni\Data;

final readonly class DniLookupResult
{
    private function __construct(public ?DniData $data, public string $status, public string $source, public bool $providerCalled, public ?int $providerStatusCode, public bool $cacheHit, public bool $localDatabaseHit, public ?string $message = null) {}

    public static function found(DniData $data, string $source, bool $providerCalled = false, ?int $providerStatusCode = null): self
    {
        return new self($data, 'found', $source, $providerCalled, $providerStatusCode, $source === 'cache', $source === 'internal');
    }

    public static function notFound(bool $providerCalled = false, ?int $status = null): self
    {
        return new self(null, 'not_found', 'none', $providerCalled, $status, false, false, 'No se encontraron datos para el DNI consultado.');
    }

    public static function unavailable(string $message, ?int $status = null, string $state = 'unavailable'): self
    {
        return new self(null, $state, 'none', $status !== null, $status, false, false, $message);
    }

    public function audit(): array
    {
        return ['source' => $this->source, 'provider_called' => $this->providerCalled, 'provider_status_code' => $this->providerStatusCode, 'cache_hit' => $this->cacheHit, 'local_database_hit' => $this->localDatabaseHit];
    }
}
