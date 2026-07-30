<?php

namespace App\Services\ApiTokens;

use App\Models\ApiTokenRequest;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Str;

class TelegramRequesterLinker
{
    public function ensurePublicCode(User $user): string
    {
        if (is_string($user->public_code) && Str::isUuid($user->public_code)) {
            return $user->public_code;
        }

        $user->forceFill(['public_code' => (string) Str::uuid()])->save();

        return (string) $user->public_code;
    }

    public function linkFromRequest(ApiTokenRequest $request, User $user, ?Integration $integration = null): void
    {
        $this->ensurePublicCode($user);

        if (blank($request->telegram_user_id) || blank($request->telegram_chat_id)) {
            return;
        }

        $metadata = $request->metadata ?? [];
        $integrationUuid = $integration !== null
            ? $integration->integration_uuid
            : ($metadata['integration_uuid'] ?? null);

        $user->forceFill([
            'telegram_user_id' => (string) $request->telegram_user_id,
            'telegram_chat_id' => (string) $request->telegram_chat_id,
            'telegram_username' => $request->telegram_username,
            'telegram_linked_integration_uuid' => is_string($integrationUuid) && Str::isUuid($integrationUuid) ? $integrationUuid : null,
            'telegram_linked_at' => now(),
        ])->save();
    }
}
