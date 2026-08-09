<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estado persistente de una operación pesada (restore) sobre ruc_records,
 * ejecutada por RestoreRucBackupJob en segundo plano. Es la fuente de
 * verdad para: la UI (polling), el guard contra restores/imports
 * concurrentes, y update.sh (no reiniciar contenedores con un restore
 * "running"). Ver app/Modules/Ruc/Jobs/RestoreRucBackupJob.php.
 */
class RucBackupOperation extends Model
{
    public const TYPE_RESTORE = 'restore';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** Ver docstring de cada stage en RestoreRucBackupJob::handle(). */
    public const STAGE_QUEUED = 'queued';

    public const STAGE_VALIDATING_BACKUP = 'validating_backup';

    public const STAGE_VERIFYING_CHECKSUM = 'verifying_checksum';

    public const STAGE_CREATING_SAFETY_BACKUP = 'creating_safety_backup';

    public const STAGE_VALIDATING_SAFETY_BACKUP = 'validating_safety_backup';

    public const STAGE_PREPARING_RESTORE = 'preparing_restore';

    public const STAGE_RESTORING = 'restoring';

    public const STAGE_VERIFYING_RESTORE = 'verifying_restore';

    public const STAGE_COMPLETED = 'completed';

    public const STAGE_FAILED = 'failed';

    protected $table = 'ruc_backup_operations';

    protected $fillable = [
        'uuid',
        'backup_id',
        'operation_type',
        'status',
        'stage',
        'progress',
        'message',
        'created_by',
        'safety_backup_id',
        'records_before',
        'records_after',
        'started_at',
        'finished_at',
        'duration_seconds',
        'error_message',
    ];

    protected $casts = [
        'progress' => 'integer',
        'records_before' => 'integer',
        'records_after' => 'integer',
        'duration_seconds' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** Se referencia por UUID en la ruta de status (opaco, no filtra el id secuencial). */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(RucBackup::class, 'backup_id');
    }

    public function safetyBackup(): BelongsTo
    {
        return $this->belongsTo(RucBackup::class, 'safety_backup_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    public static function hasActiveRestore(): bool
    {
        return self::query()
            ->where('operation_type', self::TYPE_RESTORE)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_RUNNING])
            ->exists();
    }

    public static function activeRestore(): ?self
    {
        return self::query()
            ->where('operation_type', self::TYPE_RESTORE)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_RUNNING])
            ->latest('id')
            ->first();
    }

    /**
     * Última restauración ya terminada (completed/failed). La UI la usa para
     * mostrar el resultado —incluido error_message— cuando ya no hay ninguna
     * operación activa: sin esto, un restore fallido desaparecería de la
     * pantalla al recargar y el operador no vería nunca por qué falló.
     */
    public static function latestFinishedRestore(): ?self
    {
        return self::query()
            ->where('operation_type', self::TYPE_RESTORE)
            ->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_FAILED])
            ->latest('id')
            ->first();
    }

    /**
     * Forma canónica del estado de una operación.
     *
     * Fuente única compartida por el endpoint JSON de polling
     * (RucBackupController::operationStatus) y por el estado inicial que la
     * vista embebe en Alpine. Antes cada lado construía su propio objeto, así
     * que el primer render no tenía `backup_name` y el nombre del backup
     * aparecía en blanco hasta que llegaba el primer poll.
     *
     * @return array<string, mixed>
     */
    public function toStatusPayload(): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'stage' => $this->stage,
            'progress' => $this->progress ?? 0,
            'message' => $this->message,
            'backup_name' => $this->backup?->name,
            'safety_backup_id' => $this->safety_backup_id,
            'records_before' => $this->records_before,
            'records_after' => $this->records_after,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'error_message' => $this->error_message,
        ];
    }
}
