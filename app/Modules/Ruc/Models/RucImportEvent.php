<?php

namespace App\Modules\Ruc\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RucImportEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ruc_import_id',
        'event_type',
        'data',
        'created_by',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'data' => 'json',
        'created_at' => 'datetime',
    ];

    /**
     * Relación con RucImport
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(RucImport::class, 'ruc_import_id');
    }

    /**
     * Relación con User (quien inició el evento)
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Registra un evento de importación
     */
    public static function record(
        RucImport $import,
        string $eventType,
        array $data = [],
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'ruc_import_id' => $import->id,
            'event_type' => $eventType,
            'data' => $data,
            'created_by' => $user?->id,
            'ip_address' => $ip ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Obtiene el label del tipo de evento
     */
    public function getEventLabel(): string
    {
        return match ($this->event_type) {
            'import.started' => 'Importación iniciada',
            'import.checkpoint' => 'Checkpoint',
            'import.paused' => 'Importación pausada',
            'import.resumed' => 'Importación reanudada',
            'import.cancelled' => 'Importación cancelada',
            'import.completed' => 'Importación completada',
            'import.failed' => 'Importación fallida',
            'import.rollback_requested' => 'Rollback solicitado',
            'import.rollback_started' => 'Rollback iniciado',
            'import.rollback_completed' => 'Rollback completado',
            default => $this->event_type,
        };
    }
}
