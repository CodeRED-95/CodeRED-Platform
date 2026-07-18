<?php

namespace App\Modules\Agencies\Models;

use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Observers\AgencyObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Agency extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'short_name', 'slug', 'department', 'province', 'district',
        'address', 'reference', 'phone', 'secondary_phone', 'email', 'schedule',
        'latitude', 'longitude', 'services', 'observations', 'status', 'source',
        'source_reference', 'source_text', 'map_url', 'size', 'is_operations_center',
        'has_moved', 'moved_to_agency_id', 'moved_to_address', 'move_notice', 'moved_at',
        'data_version', 'last_verified_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'last_verified_at' => 'datetime',
            'status' => AgencyStatus::class,
            'is_operations_center' => 'boolean',
            'has_moved' => 'boolean',
            'moved_at' => 'date',
        ];
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
            $agency->slug = $agency->slug ?: Str::slug($agency->name);

            if ($agency->has_moved && $agency->status !== AgencyStatus::Moved) {
                $agency->status = AgencyStatus::Moved;
            }

            if (! $agency->has_moved && $agency->status === AgencyStatus::Moved) {
                $agency->status = AgencyStatus::UnderReview;
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function movedToAgency(): BelongsTo
    {
        return $this->belongsTo(self::class, 'moved_to_agency_id');
    }

    public function movedFromAgencies(): HasMany
    {
        return $this->hasMany(self::class, 'moved_to_agency_id');
    }
}
