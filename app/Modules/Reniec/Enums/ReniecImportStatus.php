<?php

namespace App\Modules\Reniec\Enums;

enum ReniecImportStatus: string
{
    case Pending = 'pending';
    case Registered = 'registered';
    case Validating = 'validating';
    case Queued = 'queued';
    case Preparing = 'preparing';
    case Processing = 'processing';
    case Merging = 'merging';
    case Analyzing = 'analyzing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Paused = 'paused';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Stalled = 'stalled';

    public function active(): bool
    {
        return in_array($this, [self::Validating, self::Queued, self::Preparing, self::Processing, self::Merging, self::Analyzing, self::Cancelling], true);
    }
}
