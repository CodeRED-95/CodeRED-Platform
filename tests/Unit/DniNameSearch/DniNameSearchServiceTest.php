<?php

declare(strict_types=1);

namespace Tests\Unit\DniNameSearch;

use App\Domain\DniNameSearch\Contracts\DniNameSearchProviderInterface;
use App\Domain\DniNameSearch\Data\DniNameMatch;
use App\Domain\DniNameSearch\Data\DniNameSearchResult;
use App\Services\DniNameSearch\DniNameSearchCacheService;
use App\Services\DniNameSearch\DniNameSearchService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Tests\TestCase;

final class DniNameSearchServiceTest extends TestCase
{
    public function test_normalizes_input_and_returns_provider_matches(): void
    {
        $provider = new class implements DniNameSearchProviderInterface {
            public array $received = [];
            public function isEnabled(): bool { return true; }
            public function search(string $nombres, string $paterno, string $materno): DniNameSearchResult
            {
                $this->received = [$nombres, $paterno, $materno];
                return DniNameSearchResult::found([new DniNameMatch('12345678', $nombres, $paterno, $materno)]);
            }
        };

        config(['dni.name_search.enabled' => true, 'dni.name_search.cache_enabled' => false]);
        $service = new DniNameSearchService($provider, new DniNameSearchCacheService(new Repository(new ArrayStore())));
        $result = $service->search('  Juan   Carlos ', 'Pérez', ' Gómez ');

        self::assertSame(['JUAN CARLOS', 'PÉREZ', 'GÓMEZ'], $provider->received);
        self::assertSame('found', $result->status);
        self::assertSame('12345678', $result->matches[0]->dni);
    }

    public function test_cache_hit_is_reported(): void
    {
        $provider = new class implements DniNameSearchProviderInterface {
            public function isEnabled(): bool { return true; }
            public function search(string $nombres, string $paterno, string $materno): DniNameSearchResult
            {
                throw new \RuntimeException('provider should not be called');
            }
        };

        config(['dni.name_search.enabled' => true, 'dni.name_search.cache_enabled' => true, 'dni.name_search.cache_ttl_seconds' => 3600]);
        $cache = new DniNameSearchCacheService(new Repository(new ArrayStore()));
        $cache->put('JUAN', 'PEREZ', 'GOMEZ', [new DniNameMatch('12345678', 'JUAN', 'PEREZ', 'GOMEZ')]);

        $result = (new DniNameSearchService($provider, $cache))->search('Juan', 'Perez', 'Gomez');

        self::assertTrue($result->cacheHit);
        self::assertSame('12345678', $result->matches[0]->dni);
    }

    public function test_master_switch_disables_the_search_even_with_an_enabled_provider(): void
    {
        $provider = new class implements DniNameSearchProviderInterface
        {
            public function isEnabled(): bool
            {
                return true;
            }

            public function search(string $nombres, string $paterno, string $materno): DniNameSearchResult
            {
                throw new \RuntimeException('provider should not be called');
            }
        };

        config(['dni.name_search.enabled' => false, 'dni.name_search.cache_enabled' => false]);
        $service = new DniNameSearchService($provider, new DniNameSearchCacheService(new Repository(new ArrayStore())));

        $result = $service->search('Juan', 'Perez', 'Gomez');

        self::assertSame('provider_disabled', $result->status);
        self::assertSame(503, $result->statusCode);
    }
}
