<?php

namespace App\Modules\Agencies\Models;

use App\Models\User;
use App\Modules\Agencies\Enums\AgencySize;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Observers\AgencyObserver;
use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property AgencyStatus $status
 * @property AgencySize|null $size
 */
class Agency extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'external_id', 'code', 'name', 'short_name', 'slug', 'department', 'province', 'district',
        'address', 'reference', 'phone', 'secondary_phone', 'email', 'schedule',
        'latitude', 'longitude', 'services', 'observations', 'status', 'source',
        'source_reference', 'source_text', 'texto_chosen_terrestre', 'texto_chosen_aereo', 'map_url', 'size', 'is_operations_center',
        'has_moved', 'moved_to_agency_id', 'moved_to_address', 'move_notice', 'moved_at',
        'data_version', 'last_verified_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'external_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'last_verified_at' => 'datetime',
            'status' => AgencyStatus::class,
            'size' => AgencySize::class,
            'is_operations_center' => 'boolean',
            'has_moved' => 'boolean',
            'moved_at' => 'date',
        ];
    }

    protected static function newFactory(): Factory
    {
        return AgencyFactory::new();
    }

    protected static function booted(): void
    {
        static::observe(AgencyObserver::class);

        static::saving(function (self $agency): void {
            $agency->code = strtoupper(trim((string) $agency->code));
            foreach (['name', 'department', 'province', 'district', 'phone', 'email'] as $field) {
                if ($agency->$field !== null) {
                    $agency->$field = preg_replace('/\s+/u', ' ', trim((string) $agency->$field));
                }
            }
            $desiredSlug = $agency->slug ?: Str::slug($agency->name);
            $slugExists = static::query()
                ->when($agency->getKey(), fn (Builder $query) => $query->whereKeyNot($agency->getKey()))
                ->where('slug', $desiredSlug)
                ->exists();

            if ($agency->slug === null || $agency->slug === '' || $slugExists) {
                $agency->slug = self::makeUniqueSlug($desiredSlug, $agency->code ?: null, $agency->getKey());
            }

            if ($agency->has_moved && $agency->status !== AgencyStatus::Moved) {
                $agency->status = AgencyStatus::Moved;
            }

            if (! $agency->has_moved && $agency->status === AgencyStatus::Moved) {
                $agency->status = AgencyStatus::UnderReview;
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AgencyStatus::Active->value);
    }

    public function scopePublicVisible(Builder $query): Builder
    {
        return $query
            ->where('status', AgencyStatus::Active->value)
            ->where('has_moved', false);
    }

    public function scopeOperationsCenters(Builder $query): Builder
    {
        return $query->where('is_operations_center', true);
    }

    public function scopeMoved(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('has_moved', true)->orWhere('status', AgencyStatus::Moved->value);
        });
    }

    public function scopeByLocation(Builder $query, ?string $department = null, ?string $province = null, ?string $district = null): Builder
    {
        return $query
            ->when($department, fn (Builder $query) => $query->where('department', $department))
            ->when($province, fn (Builder $query) => $query->where('province', $province))
            ->when($district, fn (Builder $query) => $query->where('district', $district));
    }

    public function scopeSearch(Builder $query, ?string $term = null): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $term = mb_strtolower($term);

        return $query->where(function (Builder $sub) use ($term): void {
            if (ctype_digit($term)) {
                $sub->orWhere('external_id', (int) $term);
            }

            foreach (['code', 'name', 'short_name', 'department', 'province', 'district', 'address', 'reference', 'texto_chosen_terrestre', 'texto_chosen_aereo'] as $field) {
                $sub->orWhereRaw("unaccent(lower($field)) ILIKE unaccent(?)", ['%'.$term.'%']);
            }
        });
    }

    public function legacyChosenText(): ?string
    {
        return $this->texto_chosen_terrestre ?? $this->texto_chosen_aereo;
    }

    public function statusLabel(): string
    {
        return $this->status instanceof AgencyStatus ? $this->status->label() : (string) $this->status;
    }

    public function sizeLabel(): ?string
    {
        return $this->size instanceof AgencySize ? $this->size->label() : null;
    }

    private static function makeUniqueSlug(string $name, ?string $suffix = null, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $candidate = $base;
        $counter = 1;

        while (static::query()
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $suffix
                ? $base.'-'.$suffix.($counter > 1 ? '-'.$counter : '')
                : $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    /** @return HasMany<AgencyChangeLog, $this> */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(AgencyChangeLog::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<Agency, $this> */
    public function movedToAgency(): BelongsTo
    {
        return $this->belongsTo(self::class, 'moved_to_agency_id');
    }

    /** @return HasMany<Agency, $this> */
    public function movedFromAgencies(): HasMany
    {
        return $this->hasMany(self::class, 'moved_to_agency_id');
    }
}
