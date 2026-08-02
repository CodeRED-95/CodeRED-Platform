<?php

namespace App\Events;

use App\Models\ApiTokenRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class TokenRequestCreated
{
    use Dispatchable;
    use SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public ApiTokenRequest $tokenRequest,
        public ?string $eventId = null,
    ) {
        $this->eventId ??= (string) Str::uuid();
    }
}
