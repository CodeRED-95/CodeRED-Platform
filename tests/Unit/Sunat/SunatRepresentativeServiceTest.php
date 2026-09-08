<?php

declare(strict_types=1);

namespace Tests\Unit\Sunat;

use App\DTO\SunatRepresentativeData;
use App\Services\Sunat\SunatRepresentativeClient;
use App\Services\Sunat\SunatRepresentativeParser;
use App\Services\Sunat\SunatRepresentativeService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class SunatRepresentativeServiceTest extends TestCase
{
    public function test_cache_miss_consulta_cliente_y_guarda_resultado(): void
    {
        Cache::flush();
        config(['sunat.representatives.cache_enabled' => true, 'sunat.representatives.cache_ttl' => 3600]);

        /** @var SunatRepresentativeClient&Mockery\MockInterface $client */
        $client = Mockery::mock(SunatRepresentativeClient::class);
        // @phpstan-ignore-next-line
        $client->shouldReceive('fetch')->once()->andReturn('<html></html>');

        /** @var SunatRepresentativeParser&Mockery\MockInterface $parser */
        $parser = Mockery::mock(SunatRepresentativeParser::class);
        // @phpstan-ignore-next-line
        $parser->shouldReceive('parse')->once()->andReturn([
            new SunatRepresentativeData('DNI', '12345678', 'NOMBRE', 'GERENTE', '2020-01-15'),
        ]);

        $service = new SunatRepresentativeService($client, $parser);
        $result = $service->find('20512528458');

        $this->assertFalse($result['cached']);
        $this->assertSame('sunat', $result['source']);
        $this->assertCount(1, $result['data']);
        $this->assertTrue(Cache::has('sunat:representatives:20512528458'));
    }

    public function test_cache_hit_evita_consulta_sunat(): void
    {
        Cache::put('sunat:representatives:20512528458', [
            new SunatRepresentativeData('DNI', '12345678', 'NOMBRE', 'GERENTE', '2020-01-15'),
        ], 3600);

        /** @var SunatRepresentativeClient&Mockery\MockInterface $client */
        $client = Mockery::mock(SunatRepresentativeClient::class);
        $client->shouldNotReceive('fetch');

        /** @var SunatRepresentativeParser&Mockery\MockInterface $parser */
        $parser = Mockery::mock(SunatRepresentativeParser::class);
        $parser->shouldNotReceive('parse');

        $service = new SunatRepresentativeService($client, $parser);
        $result = $service->find('20512528458');

        $this->assertTrue($result['cached']);
        $this->assertCount(1, $result['data']);
    }
}
