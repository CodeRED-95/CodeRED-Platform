<?php

namespace App\Modules\Reniec\Models;

use App\Models\User;
use App\Modules\Reniec\Enums\ReniecImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $original_filename
 * @property string $disk
 * @property string $source_path
 * @property int $file_size
 * @property string $file_hash
 * @property ReniecImportStatus $status
 * @property string $strategy
 * @property int $current_byte_offset
 * @property int $current_line_number
 * @property int $valid_rows
 * @property int $invalid_rows
 * @property int $last_completed_chunk
 * @property int $processed_rows
 * @property int $inserted_rows
 * @property int $updated_rows
 * @property float $rows_per_second
 * @property Carbon|null $started_at
 * @property Carbon|null $cancel_requested_at
 * @property Carbon|null $paused_at
 * @property Carbon|null $last_heartbeat_at
 */
class ReniecImport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => ReniecImportStatus::class, 'metadata' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'failed_at' => 'datetime', 'cancel_requested_at' => 'datetime', 'cancelled_at' => 'datetime', 'paused_at' => 'datetime', 'resumed_at' => 'datetime', 'last_heartbeat_at' => 'datetime'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
