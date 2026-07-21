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

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Queued => 'En cola',
            self::Validating => 'Validando archivo',
            self::Processing => 'Procesando',
            self::Completed => 'Completada',
            self::CompletedWithErrors => 'Completada con errores',
            self::Failed => 'Fallida',
            self::Cancelled => 'Cancelada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::CompletedWithErrors => 'warning',
            self::Failed => 'danger',
            self::Cancelled => 'neutral',
            self::Processing, self::Validating => 'info',
            self::Pending, self::Queued => 'neutral',
        };
    }
}
