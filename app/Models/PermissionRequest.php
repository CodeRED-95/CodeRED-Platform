<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PermissionRequestStatus;
use App\Services\Permissions\MobileAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de acceso a un módulo móvil.
 *
 * @property PermissionRequestStatus $status
 */
class PermissionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'permission',
        'status',
        'reason',
        'requested_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PermissionRequestStatus::class,
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Nombre con el que se presenta el acceso: "Consulta RUC". */
    public function accessLabel(): string
    {
        return MobileAccess::label($this->permission);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PermissionRequestStatus::Pending);
    }
}
