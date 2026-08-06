<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RucBackup extends Model
{
    protected $table = 'ruc_backups';

    protected $fillable = [
        'name',
        'backup_type',
        'total_records',
        'file_size_bytes',
        'storage_path',
        'storage_type',
        'compression_type',
        'status',
        'started_at',
        'completed_at',
        'duration_seconds',
        'error_message',
        'checksum_sha256',
        'retention_days',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('expires_at')
            ->orWhere('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Obtener el tamaño formateado
     */
    public function getFormattedSize(): string
    {
        if (!$this->file_size_bytes) {
            return 'N/A';
        }

        $bytes = $this->file_size_bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Marcar como completado
     */
    public function markAsCompleted(int $durationSeconds, ?string $checksum = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'duration_seconds' => $durationSeconds,
            'checksum_sha256' => $checksum,
            'expires_at' => now()->addDays($this->retention_days),
        ]);
    }

    /**
     * Marcar como fallido
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }
}
