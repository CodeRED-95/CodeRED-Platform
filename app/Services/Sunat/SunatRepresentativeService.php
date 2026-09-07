<?php

namespace App\Services\Sunat;

use App\DTO\SunatRepresentativeData;
use App\Exceptions\SunatRepresentativeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SunatRepresentativeService
{
    public function __construct(
        private readonly SunatRepresentativeClient $client,
        private readonly SunatRepresentativeParser $parser,
    ) {}

    /**
     * @return array{data: array<int, SunatRepresentativeData>|null, cached: bool, source: string, consulted_at: string}
     */
    public function find(string $ruc): array
    {
        Log::info('sunat_representatives.requested', ['ruc' => $ruc]);

        $key = $this->cacheKey($ruc);
        if ((bool) config('sunat.representatives.cache_enabled') && Cache::has($key)) {
            Log::info('sunat_representatives.cache_hit', ['ruc' => $ruc]);

            return [
                'data' => Cache::get($key),
                'cached' => true,
                'source' => 'sunat',
                'consulted_at' => now()->toISOString(),
            ];
        }

        Log::info('sunat_representatives.cache_miss', ['ruc' => $ruc]);

        try {
            $html = $this->client->fetch($ruc);
            $data = $this->parser->parse($html);
        } catch (ConnectionException $e) {
            Log::warning('sunat_representatives.failed', ['ruc' => $ruc, 'type' => 'timeout-or-connection', 'message' => $e->getMessage()]);
            throw $e;
        } catch (RequestException $e) {
            Log::warning('sunat_representatives.failed', ['ruc' => $ruc, 'type' => 'http-error', 'message' => $e->getMessage()]);
            throw $e;
        } catch (SunatRepresentativeException $e) {
            Log::warning('sunat_representatives.failed', ['ruc' => $ruc, 'type' => 'invalid-html', 'message' => $e->getMessage()]);
            throw $e;
        } catch (Throwable $e) {
            Log::warning('sunat_representatives.failed', ['ruc' => $ruc, 'type' => 'unexpected', 'message' => $e->getMessage()]);
            throw new SunatRepresentativeException('No fue posible consultar SUNAT.', 0, $e);
        }

        if ($data === []) {
            Log::info('sunat_representatives.not_found', ['ruc' => $ruc]);
            return ['data' => null, 'cached' => false, 'source' => 'sunat', 'consulted_at' => now()->toISOString()];
        }

        if ((bool) config('sunat.representatives.cache_enabled')) {
            Cache::put($key, $data, max(1, (int) config('sunat.representatives.cache_ttl')));
        }

        Log::info('sunat_representatives.success', ['ruc' => $ruc, 'count' => count($data)]);

        return [
            'data' => $data,
            'cached' => false,
            'source' => 'sunat',
            'consulted_at' => now()->toISOString(),
        ];
    }

    private function cacheKey(string $ruc): string
    {
        return 'sunat:representatives:'.$ruc;
    }
}
