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

    // Valores exclusivos del pipeline de rollback V3 (RucRollbackHandler /
    // RucImportStatusV3). El modelo RucImport castea la columna "status" a
    // ESTE enum sin importar qué subsistema (v2 legacy o v3) la escribió, así
    // que estos casos deben existir aquí también o el cast revienta con
    // ValueError en cuanto alguien lee ->status en un import v3.
    case CompletedWithWarnings = 'completed_with_warnings';
    case RollbackRequested = 'rollback_requested';
    case RollingBack = 'rolling_back';
    case RolledBack = 'rolled_back';

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
            self::CompletedWithWarnings => 'Completada con advertencias',
            self::RollbackRequested => 'Rollback solicitado',
            self::RollingBack => 'Revirtiendo',
            self::RolledBack => 'Revertida',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::CompletedWithErrors, self::CompletedWithWarnings => 'warning',
            self::Failed => 'danger',
            self::Cancelled, self::Paused, self::RolledBack => 'neutral',
            self::Processing, self::Validating, self::RollingBack => 'info',
            self::Pending, self::Registered, self::Queued, self::RollbackRequested => 'neutral',
        };
    }
}
