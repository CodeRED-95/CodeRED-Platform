<?php

namespace App\Modules\Ruc\Models;

use App\Models\User;
use App\Modules\Ruc\Enums\RucImportStatus;
use Database\Factories\RucImportFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property RucImportStatus $status
 * @property string $uuid
 * @property string $disk
 * @property string $path
 * @property int $processed_rows
 * @property int $inserted_rows
 * @property int $ignored_rows
 * @property int $invalid_rows
 * @property int $total_rows
 * @property string $progress_percentage
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $last_heartbeat_at
 * @property Carbon|null $cancel_requested_at
 * @property string $queue_name
 * @property string|null $job_uuid
 * @property string|null $last_message
 * @property string $original_filename
 * @property string $file_hash
 * @property string $encoding
 * @property string $delimiter
 * @property int $resolved_ubigeo_rows
 * @property int $unknown_ubigeo_rows
 * @property int $address_rows
 * @property int $current_byte_offset
 * @property int $current_line_number
 * @property int $last_completed_chunk
 * @property Carbon|null $failed_at
 * @property string|null $error_message
 */
class RucImport extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return RucImportFactory::new();
    }

    protected $fillable = [
        'uuid',
        'original_filename',
        'stored_filename',
        'disk',
        'path',
        'file_size',
        'file_hash',
        'status',
        'total_rows',
        'processed_rows',
        'inserted_rows',
        'updated_rows',
        'ignored_rows',
        'invalid_rows',
        'failed_rows',
        'resolved_ubigeo_rows',
        'unknown_ubigeo_rows',
        'progress_percentage',
        'current_chunk',
        'total_chunks',
        'encoding',
        'delimiter',
        'errors_path',
        'started_at',
        'finished_at',
        'failed_at',
        'last_heartbeat_at',
        'error_message',
        'queue_name',
        'job_uuid',
        'last_message',
        'cancel_requested_at',
        'created_by',
        'current_byte_offset',
        'current_line_number',
        'last_completed_chunk',
        'address_rows',
        'strategy',
        'archive_path',
        // Campos v3 nuevos
        'valid_lines',
        'skipped_lines',
        'warning_lines',
        'duplicate_records',
        'skipped_records',
        'paused_at',
        'cancelled_at',
        'checkpoint_line',
        'checkpoint_byte_offset',
        'checkpoint_timestamp',
        'merge_strategy',
        'skip_duplicates',
        'skip_unknown_ubigeo',
        'max_errors_allowed',
        'rollback_requested_at',
        'rollback_started_at',
        'rollback_completed_at',
        'rollback_reason',
        'last_error',
        'last_warning',
        'status_message',
        'memory_peak_mb',
        'duration_seconds',
        'lines_per_second',
        'estimated_time_left',
    ];

    protected function casts(): array
    {
        return ['status' => RucImportStatus::class, 'progress_percentage' => 'decimal:2', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'failed_at' => 'datetime', 'last_heartbeat_at' => 'datetime', 'cancel_requested_at' => 'datetime'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** @return HasMany<RucImportError, $this> */
    public function errors(): HasMany
    {
        return $this->hasMany(RucImportError::class);
    }

    /** @return HasMany<RucImportEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(RucImportEvent::class)->orderBy('created_at', 'desc');
    }

    /** @return HasMany<RucImportDuplicate, $this> */
    public function duplicates(): HasMany
    {
        return $this->hasMany(RucImportDuplicate::class);
    }

    /**
     * Registra un evento de importación
     */
    public function recordEvent(
        string $eventType,
        array $data = [],
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): RucImportEvent {
        return RucImportEvent::record($this, $eventType, $data, $user, $ip, $userAgent);
    }

    /**
     * Calcula el progreso en porcentaje
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_lines < 1) {
            return 0.0;
        }

        return min(100.0, ($this->processed_lines / $this->total_lines) * 100);
    }

    /**
     * Obtiene el ETA en segundos
     */
    public function getEstimatedTimeLeft(): ?int
    {
        if ($this->lines_per_second === null || $this->lines_per_second <= 0) {
            return null;
        }
        $remaining = $this->total_lines - $this->processed_lines;
        if ($remaining <= 0) {
            return 0;
        }

        return (int) ($remaining / $this->lines_per_second);
    }

    /**
     * ¿Se puede resumir?
     */
    public function canResume(): bool
    {
        // $this->status es un enum (cast), no un string: compararlo con
        // ->value (string) via === siempre daba false.
        return $this->status?->value === RucImportStatus::Paused->value;
    }

    /**
     * ¿Se puede cancelar?
     */
    public function canCancel(): bool
    {
        return in_array($this->status?->value, [
            RucImportStatus::Processing->value,
            RucImportStatus::Paused->value,
        ], true);
    }

    /**
     * ¿Se puede hacer rollback?
     */
    public function canRollback(): bool
    {
        return in_array($this->status?->value, [
            RucImportStatus::Completed->value,
            RucImportStatus::CompletedWithErrors->value,
        ], true) && $this->inserted_rows > 0;
    }

    /**
     * Solicita cancelación
     */
    public function requestCancellation(?User $user = null, ?string $reason = null): void
    {
        $this->update([
            'cancel_requested_at' => now(),
            'status_message' => $reason ?? 'Cancelación solicitada por usuario',
        ]);

        $this->recordEvent('import.cancelled', [
            'reason' => $reason,
            'requested_by' => $user?->id,
        ], $user);
    }

    /**
     * Solicita rollback
     */
    public function requestRollback(?User $user = null, ?string $reason = null): void
    {
        $this->update([
            'rollback_requested_at' => now(),
            'status_message' => $reason ?? 'Rollback solicitado',
        ]);

        $this->recordEvent('import.rollback_requested', [
            'reason' => $reason,
            'requested_by' => $user?->id,
        ], $user);
    }
}
