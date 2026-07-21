<?php

namespace App\Modules\Ruc\Enums;

enum RucImportStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Validating = 'validating';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function active(): bool
    {
        return in_array($this, [self::Pending, self::Queued, self::Validating, self::Processing], true);
    }
}
