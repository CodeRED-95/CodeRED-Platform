<?php

namespace App\Modules\Agencies\Models;

use App\Models\User;
use App\Modules\Agencies\Observers\AgencyObserver;
use App\Modules\Agencies\Enums\AgencyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Agency extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'short_name', 'slug', 'department', 'province', 'district',
        'address', 'reference', 'phone', 'secondary_phone', 'email', 'schedule',
        'latitude', 'longitude', 'services', 'observations', 'status', 'source',
        'source_reference', 'data_version', 'last_verified_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'last_verified_at' => 'datetime',
            'status' => AgencyStatus::class,
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
}
