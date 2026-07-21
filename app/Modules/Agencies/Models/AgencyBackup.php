<?php

namespace App\Modules\Agencies\Models;

use App\Models\User;
use App\Modules\Agencies\Enums\AgencyBackupStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyBackup extends Model
{
    protected $fillable = ['filename', 'disk', 'path', 'record_count', 'size_bytes', 'checksum_sha256', 'status', 'error_message', 'created_by'];

    protected function casts(): array
    {
        return ['status' => AgencyBackupStatus::class, 'record_count' => 'integer', 'size_bytes' => 'integer'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
