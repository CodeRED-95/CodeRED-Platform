<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RucBackupUploadPart extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    protected $table = 'ruc_backup_upload_parts';

    protected $fillable = [
        'upload_id',
        'part_index',
        'filename',
        'size_bytes',
        'checksum_sha256',
        'storage_path',
        'status',
    ];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(RucBackupUpload::class, 'upload_id');
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }
}
