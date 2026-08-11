<?php

namespace App\Models;

use App\Models\Concerns\HasRoles;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'is_active',
        'must_change_password',
        'last_login_at',
        'last_login_ip',
        'created_by',
        'updated_by',
        'public_code',
        'telegram_user_id',
        'telegram_chat_id',
        'telegram_username',
        'telegram_linked_integration_uuid',
        'telegram_linked_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'telegram_linked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (! is_string($user->public_code) || ! Str::isUuid($user->public_code)) {
                $user->public_code = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()
            ->where('slug', $role)
            ->exists();
    }

    /**
     * Etiqueta legible del rol principal, para mostrar en el menú de usuario.
     * Prioriza super-admin; si no, el primer rol asignado.
     */
    public function primaryRoleLabel(): string
    {
        if ($this->hasRole('super-admin')) {
            return 'Super Administrador';
        }

        return $this->roles()->orderBy('id')->value('name') ?? 'Sin rol';
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()
            ->whereIn('slug', $roles)
            ->exists();
    }

    public function hasAllPermissions(array $permissions): bool
    {
        return collect($permissions)->every(fn (string $permission) => $this->hasPermission($permission));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by');
    }

    public function shalomRecordarInstallations(): HasMany
    {
        return $this->hasMany(ShalomRecordarInstallation::class);
    }

    public function shalomRecordarRecords(): HasMany
    {
        return $this->hasMany(ShalomRecordarRecord::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', 'suspended');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $term = mb_strtolower($term);

        return $query->where(function (Builder $sub) use ($term): void {
            $sub->whereRaw('unaccent(lower(name)) ILIKE unaccent(?)', ['%'.$term.'%'])
                ->orWhereRaw('unaccent(lower(email)) ILIKE unaccent(?)', ['%'.$term.'%']);
        });
    }

    public function scopeWithRole(Builder $query, string $slug): Builder
    {
        return $query->whereHas('roles', fn ($roles) => $roles->where('slug', $slug));
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
