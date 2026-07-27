<?php

namespace App\Models;

use App\Enums\IntegrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class Integration extends Model
{
    protected $fillable = ['integration_uuid', 'provider', 'instance_name', 'instance_url', 'hostname', 'environment', 'version', 'status', 'encrypted_secret', 'ip_allowlist', 'last_seen_at', 'latency_ms', 'uptime', 'running_workflows', 'memory_usage', 'cpu_usage', 'secret_rotated_at', 'created_by'];

    protected function casts(): array
    {
        return ['ip_allowlist' => 'array', 'last_seen_at' => 'datetime', 'secret_rotated_at' => 'datetime', 'status' => IntegrationStatus::class];
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(IntegrationCapability::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(IntegrationService::class);
    }

    public function plugins(): HasMany
    {
        return $this->hasMany(IntegrationPlugin::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class);
    }

    public function secret(): string
    {
        return Crypt::decryptString($this->encrypted_secret);
    }

    public function isOnline(): bool
    {
        return $this->lastSeenAt()?->gt(now()->subMinutes(3)) ?? false;
    }

    public function lastSeenAt(): ?Carbon
    {
        $value = $this->getAttribute('last_seen_at');

        return $value === null ? null : $this->asDateTime($value);
    }

    public function secretRotatedAt(): ?Carbon
    {
        $value = $this->getAttribute('secret_rotated_at');

        return $value === null ? null : $this->asDateTime($value);
    }

    public function statusValue(): string
    {
        $status = $this->getAttribute('status');

        return $status instanceof IntegrationStatus ? $status->value : (string) $status;
    }
}
