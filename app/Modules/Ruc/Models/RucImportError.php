<?php

namespace App\Modules\Ruc\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $line_number
 * @property string $reason
 * @property string|null $line_preview
 */
class RucImportError extends Model
{
    public $timestamps = false;

    protected $fillable = ['ruc_import_id', 'line_number', 'reason', 'line_preview', 'created_at'];

    public function import(): BelongsTo
    {
        return $this->belongsTo(RucImport::class, 'ruc_import_id');
    }
}
