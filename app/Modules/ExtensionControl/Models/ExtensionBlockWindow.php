<?php

declare(strict_types=1);

namespace App\Modules\ExtensionControl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtensionBlockWindow extends Model
{
    protected $table = 'extension_block_windows';

    protected $fillable = [
        'extension_block_rule_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ExtensionBlockRule::class, 'extension_block_rule_id');
    }
}
