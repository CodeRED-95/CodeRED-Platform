<?php

declare(strict_types=1);

namespace App\Modules\ExtensionControl\Services;

use App\Modules\ExtensionControl\Models\ExtensionBlockRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ExtensionBlockRuleService
{
    private const CACHE_KEY = 'extension:block-rules:payload';

    private const CACHE_TTL_SECONDS = 300;

    /**
     * Reglas activas tal y como las consume la extension, con una version
     * derivada del contenido para que el cliente detecte cambios sin
     * descargar todo el conjunto.
     *
     * @return array{version: string, generated_at: string, rules: array<int, array<string, mixed>>}
     */
    public function payload(): array
    {
        /** @var array{version: string, generated_at: string, rules: array<int, array<string, mixed>>} $payload */
        $payload = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $rules = ExtensionBlockRule::query()
                ->active()
                ->ordered()
                ->with(['windows', 'hosts'])
                ->get()
                ->map(fn (ExtensionBlockRule $rule): array => $this->serializeRule($rule))
                ->all();

            return [
                'version' => hash('sha256', (string) json_encode($rules)),
                'generated_at' => Carbon::now()->toIso8601String(),
                'rules' => $rules,
            ];
        });

        return $payload;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRule(ExtensionBlockRule $rule): array
    {
        $hostPatterns = $rule->hostPatterns();

        return [
            'id' => $rule->getKey(),
            'label' => $rule->label,
            // `host_pattern` (singular) se mantiene para la extension 2.4.0,
            // que solo entiende un dominio por regla y descarta las que no lo
            // traigan. Desde 2.5.0 se lee `host_patterns`.
            'host_pattern' => $hostPatterns[0] ?? $rule->host_pattern,
            'host_patterns' => $hostPatterns,
            'path_pattern' => $rule->path_pattern,
            'window_mode' => $rule->window_mode,
            'timezone' => $rule->timezone,
            'windows' => $rule->windows
                ->map(fn ($window): array => [
                    'day_of_week' => (int) $window->day_of_week,
                    'start_time' => substr((string) $window->start_time, 0, 5),
                    'end_time' => substr((string) $window->end_time, 0, 5),
                ])
                ->values()
                ->all(),
        ];
    }
}
