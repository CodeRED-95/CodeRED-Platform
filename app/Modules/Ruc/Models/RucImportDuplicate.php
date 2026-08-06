<?php

namespace App\Modules\Ruc\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RucImportDuplicate extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'ruc_import_duplicates';

    protected $fillable = [
        'ruc_import_id',
        'ruc',
        'first_line',
        'duplicate_line',
        'action',
    ];

    protected $casts = [
        'first_line' => 'integer',
        'duplicate_line' => 'integer',
    ];

    /**
     * Relación con RucImport
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(RucImport::class, 'ruc_import_id');
    }

    /**
     * Registra un duplicado
     */
    public static function record(
        int $importId,
        string $ruc,
        int $firstLine,
        int $duplicateLine,
        string $action = 'skipped'
    ): self {
        return self::create([
            'ruc_import_id' => $importId,
            'ruc' => $ruc,
            'first_line' => $firstLine,
            'duplicate_line' => $duplicateLine,
            'action' => $action,
        ]);
    }

    /**
     * Obtiene el label de la acción
     */
    public function getActionLabel(): string
    {
        return match ($this->action) {
            'skipped' => 'Omitido',
            'kept_first' => 'Mantener primero',
            'kept_latest' => 'Mantener más reciente',
            default => $this->action,
        };
    }
}
