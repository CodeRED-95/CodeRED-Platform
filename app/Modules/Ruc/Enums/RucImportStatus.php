<?php

namespace App\Modules\Ruc\Enums;

enum RucImportStatus: string
{
    case Pending = 'pending';
    case Registered = 'registered';
    case Queued = 'queued';
    case Validating = 'validating';
    case Processing = 'processing';
    case Paused = 'paused';
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
            self::Pending => 'Preparando',
            self::Registered => 'Registrada',
            self::Queued => 'En cola',
            self::Validating => 'Validando archivo',
            self::Processing => 'Procesando',
            self::Paused => 'Pausada',
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
            self::Cancelled, self::Paused => 'neutral',
            self::Processing, self::Validating => 'info',
            self::Pending, self::Registered, self::Queued => 'neutral',
        };
    }
}
