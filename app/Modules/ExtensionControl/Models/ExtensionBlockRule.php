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
