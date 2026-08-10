<?php

declare(strict_types=1);

namespace App\Modules\Agencies\Enums;

enum AgencyRestoreStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En cola',
            self::Processing => 'Restaurando',
            self::Completed => 'Completada',
            self::Failed => 'Fallida',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Processing => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
