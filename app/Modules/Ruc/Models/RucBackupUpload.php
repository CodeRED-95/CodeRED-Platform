<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sesión de subida multipart de un backup RUC generado por RUC Tools
 * (manifest.json + partes). `temporary_directory` es relativa al disco
 * "local", igual que RucBackup::storage_path — nunca una ruta absoluta.
 */
class RucBackupUpload extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_ASSEMBLING = 'assembling';

    public const STATUS_VALIDATING = 'validating';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'ruc_backup_uploads';

    protected $fillable = [
        'uuid',
        'user_id',
        'status',
        'original_filename',
        'manifest_json',
        'total_size_bytes',
        'part_size_bytes',
        'total_parts',
        'received_parts',
        'received_bytes',
        'temporary_directory',
        'ruc_backup_id',
        'started_at',
        'completed_at',
        'expires_at',
        'error_message',
    ];

    protected $casts = [
        'manifest_json' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** Se referencia por UUID en las rutas (opaco, no filtra el id secuencial). */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(RucBackup::class, 'ruc_backup_id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(RucBackupUploadPart::class, 'upload_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED], true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function absoluteTemporaryDirectory(): string
    {
        return \Illuminate\Support\Facades\Storage::disk('local')->path($this->temporary_directory);
    }
}
