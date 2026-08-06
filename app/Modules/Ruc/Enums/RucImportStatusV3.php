<?php

namespace App\Modules\Ruc\Enums;

enum RucImportStatusV3: string
{
    // Flujo normal
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';

    // Variantes
    case CompletedWithWarnings = 'completed_with_warnings';
    case CompletedWithErrors = 'completed_with_errors';

    // Alteraciones
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    // Fallos
    case Failed = 'failed';

    // Rollback
    case RollbackRequested = 'rollback_requested';
    case RollingBack = 'rolling_back';
    case RolledBack = 'rolled_back';

    public function active(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Processing,
            self::Paused,
            self::RollbackRequested,
            self::RollingBack,
        ], true);
    }

    public function completed(): bool
    {
        return in_array($this, [
            self::Completed,
            self::CompletedWithWarnings,
            self::CompletedWithErrors,
            self::Cancelled,
            self::Failed,
            self::RolledBack,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Preparando',
            self::Processing => 'Procesando',
            self::Completed => 'Completada',
            self::CompletedWithWarnings => 'Completada con advertencias',
            self::CompletedWithErrors => 'Completada con errores',
            self::Paused => 'Pausada',
            self::Cancelled => 'Cancelada',
            self::Failed => 'Fallida',
            self::RollbackRequested => 'Rollback solicitado',
            self::RollingBack => 'Revirtiendo',
            self::RolledBack => 'Revertida',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::CompletedWithWarnings => 'warning',
            self::CompletedWithErrors => 'warning',
            self::Failed => 'danger',
            self::Cancelled, self::Paused, self::RolledBack => 'neutral',
            self::Processing, self::RollingBack => 'info',
            self::Pending, self::RollbackRequested => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Processing => 'cog',
            self::Completed => 'check-circle',
            self::CompletedWithWarnings => 'exclamation-circle',
            self::CompletedWithErrors => 'x-circle',
            self::Paused => 'pause-circle',
            self::Cancelled => 'ban',
            self::Failed => 'alert-circle',
            self::RollbackRequested => 'arrow-left',
            self::RollingBack => 'arrow-left-circle',
            self::RolledBack => 'check-circle',
        };
    }
}
