<?php

declare(strict_types=1);

namespace App\Modules\Agencies\Models;

use App\Models\User;
use App\Modules\Agencies\Enums\AgencyRestoreStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una ejecución de restauración de copia de agencias.
 *
 * El trabajo real ocurre en RestoreAgencyBackupJob; esta fila es el estado que
 * la interfaz consulta por sondeo, de modo que ninguna petición HTTP espera al
 * proceso.
 */
class AgencyBackupRestore extends Model
{
    public const MODE_MERGE = 'merge';

    public const MODE_REPLACE = 'replace';

    protected $fillable = [
        'uuid', 'agency_backup_id', 'filename', 'disk', 'path', 'checksum_sha256', 'size_bytes',
        'mode', 'status', 'stage', 'progress', 'total_records', 'processed_records',
        'created_records', 'updated_records', 'trashed_records', 'name_histories_restored',
        'safety_backup_id', 'error_message', 'summary', 'created_by', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AgencyRestoreStatus::class,
            'progress' => 'integer',
            'size_bytes' => 'integer',
            'total_records' => 'integer',
            'processed_records' => 'integer',
            'created_records' => 'integer',
            'updated_records' => 'integer',
            'trashed_records' => 'integer',
            'name_histories_restored' => 'integer',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(AgencyBackup::class, 'agency_backup_id');
    }

    public function safetyBackup(): BelongsTo
    {
        return $this->belongsTo(AgencyBackup::class, 'safety_backup_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function isRunning(): bool
    {
        return ! $this->status->isFinished();
    }

    /** @return array<string, string> */
    public static function modes(): array
    {
        return [
            self::MODE_MERGE => 'Combinar (crea y actualiza, no elimina nada)',
            self::MODE_REPLACE => 'Réplica exacta (además envía a la papelera lo que no esté en la copia)',
        ];
    }
}
