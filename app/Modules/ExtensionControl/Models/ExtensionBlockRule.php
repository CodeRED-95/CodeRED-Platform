<?php

declare(strict_types=1);

namespace App\Modules\ExtensionControl\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtensionBlockRule extends Model
{
    protected $table = 'extension_block_rules';

    protected $fillable = [
        'label',
        'host_pattern',
        'path_pattern',
        'window_mode',
        'timezone',
        'is_active',
        'sort_order',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function hosts(): HasMany
    {
        return $this->hasMany(ExtensionBlockRuleHost::class, 'extension_block_rule_id')->orderBy('id');
    }

    /**
     * Dominios de la regla. `host_pattern` sigue siendo el primero por
     * compatibilidad con la extension 2.4.0, que solo entiende una cadena.
     *
     * @return array<int, string>
     */
    public function hostPatterns(): array
    {
        $patterns = $this->relationLoaded('hosts') || $this->exists
            ? $this->hosts->pluck('host_pattern')->all()
            : [];

        if ($patterns === [] && is_string($this->host_pattern) && $this->host_pattern !== '') {
            $patterns = [$this->host_pattern];
        }

        return array_values(array_unique($patterns));
    }

    /**
     * Destinos efectivos: dominio + ruta. Un destino sin ruta propia hereda la
     * de la regla, que es como se comportaban todos antes de permitir rutas
     * por destino.
     *
     * @return array<int, array{host_pattern: string, path_pattern: string}>
     */
    public function destinations(): array
    {
        $fallbackPath = (string) ($this->path_pattern ?: '/*');

        if ($this->exists && $this->hosts->isNotEmpty()) {
            return $this->hosts
                ->map(fn ($host): array => [
                    'host_pattern' => (string) $host->host_pattern,
                    'path_pattern' => (string) ($host->path_pattern ?: $fallbackPath),
                ])
                ->values()
                ->all();
        }

        return is_string($this->host_pattern) && $this->host_pattern !== ''
            ? [['host_pattern' => $this->host_pattern, 'path_pattern' => $fallbackPath]]
            : [];
    }

    public function windows(): HasMany
    {
        return $this->hasMany(ExtensionBlockWindow::class, 'extension_block_rule_id')->orderBy('day_of_week')->orderBy('start_time');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
