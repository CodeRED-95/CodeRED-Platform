<?php

declare(strict_types=1);

namespace App\Modules\ExtensionControl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtensionBlockRuleHost extends Model
{
    protected $table = 'extension_block_rule_hosts';

    protected $fillable = ['extension_block_rule_id', 'host_pattern', 'path_pattern'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ExtensionBlockRule::class, 'extension_block_rule_id');
    }
}
