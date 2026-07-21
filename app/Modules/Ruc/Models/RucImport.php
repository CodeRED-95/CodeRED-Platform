<?php

namespace App\Modules\Ruc\Models;

use App\Models\User;
use App\Modules\Ruc\Enums\RucImportStatus;
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
 */
class RucImport extends Model
{
    protected $fillable = ['uuid', 'original_filename', 'stored_filename', 'disk', 'path', 'file_size', 'file_hash', 'status', 'total_rows', 'processed_rows', 'inserted_rows', 'updated_rows', 'ignored_rows', 'invalid_rows', 'failed_rows', 'progress_percentage', 'current_chunk', 'total_chunks', 'encoding', 'delimiter', 'errors_path', 'started_at', 'finished_at', 'failed_at', 'last_heartbeat_at', 'error_message', 'created_by'];

    protected function casts(): array
    {
        return ['status' => RucImportStatus::class, 'progress_percentage' => 'decimal:2', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'failed_at' => 'datetime', 'last_heartbeat_at' => 'datetime'];
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
}
