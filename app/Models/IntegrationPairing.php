<?php

namespace App\Models;

use App\Enums\IntegrationPairingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class IntegrationPairing extends Model
{
    protected $fillable = ['pair_uuid', 'provider', 'pair_code', 'encrypted_temporary_secret', 'nonce', 'status', 'integration_id', 'expires_at', 'claimed_at', 'created_by'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'claimed_at' => 'datetime', 'status' => IntegrationPairingStatus::class];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function temporarySecret(): string
    {
        return Crypt::decryptString($this->encrypted_temporary_secret);
    }

    public function expiresAt(): Carbon
    {
        return $this->asDateTime($this->getAttribute('expires_at'));
    }

    public function statusValue(): string
    {
        $status = $this->getAttribute('status');

        return $status instanceof IntegrationPairingStatus ? $status->value : (string) $status;
    }
}
