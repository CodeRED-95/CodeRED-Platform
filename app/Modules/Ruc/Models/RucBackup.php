<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de un backup de la tabla ruc_records.
 *
 * `storage_path` es SIEMPRE relativo al disco "local" (ver
 * config/filesystems.php) — nunca una ruta absoluta. Para obtener la ruta
 * real en disco usar siempre absolutePath(), nunca concatenar
 * storage_path('app/...') a mano.
 */
class RucBackup extends Model
{
    /**
     * Extensión de los backups nuevos. Es un dump de pg_dump en formato
     * "custom" (firma "PGDMP"), NO un archivo gzip real, aunque backups
     * creados antes de este sistema se nombraran *.sql.gz. Esos archivos
     * antiguos se siguen sirviendo/restaurando igual — el formato real
     * siempre se determina por contenido (pg_restore --list), nunca por la
     * extensión.
     */
    public const FILE_EXTENSION = 'dump';

    /** @var list<string> extensiones aceptadas al importar un backup manualmente */
    public const ALLOWED_IMPORT_EXTENSIONS = ['dump', 'gz'];

    public const STATUS_CREATING = 'creating';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** Backup creado manualmente (botón "Crear Backup") o importado. */
    public const TYPE_MANUAL = 'manual';

    /** Backup automático del estado actual, tomado justo antes de un restore. */
    public const TYPE_SAFETY = 'safety';

    /** Backup ensamblado a partir de un manifest + partes subidas (RUC Tools). */
    public const TYPE_UPLOADED = 'uploaded';

    protected $table = 'ruc_backups';

    protected $fillable = [
        'name',
        'backup_type',
        'storage_path',
        'file_size_bytes',
        'checksum_sha256',
        'total_records',
        'status',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Ruta absoluta real en el filesystem, resuelta siempre a través del
     * disco "local" configurado (nunca storage_path('app/...') a mano: el
     * root de ese disco cambió entre versiones de Laravel).
     */
    public function absolutePath(): string
    {
        return \Illuminate\Support\Facades\Storage::disk('local')->path($this->storage_path);
    }

    public function fileExists(): bool
    {
        return file_exists($this->absolutePath());
    }

    public function formattedSize(): string
    {
        $bytes = $this->file_size_bytes;
        if (! $bytes) {
            return 'N/D';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
