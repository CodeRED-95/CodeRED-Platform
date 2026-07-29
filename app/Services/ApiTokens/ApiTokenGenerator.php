<?php

namespace App\Services\ApiTokens;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Laravel\Sanctum\NewAccessToken;

class ApiTokenGenerator
{
    public const DEFAULT_EXPIRES_IN_DAYS = 30;

    public const MIN_EXPIRES_IN_DAYS = 1;

    public const MAX_EXPIRES_IN_DAYS = 365;

    /** @param list<string> $abilities */
    public function create(object $owner, string $name, array $abilities, int $tokenExpiresInDays): NewAccessToken
    {
        return $owner->createToken($name, $abilities, $this->expiresAt($tokenExpiresInDays));
    }

    /** @param list<string> $abilities */
    public function createWithExpiresAt(object $owner, string $name, array $abilities, ?DateTimeInterface $expiresAt): NewAccessToken
    {
        return $owner->createToken($name, $abilities, $expiresAt);
    }

    public function expiresAt(int $tokenExpiresInDays): CarbonImmutable
    {
        $this->assertValidDays($tokenExpiresInDays);

        return CarbonImmutable::instance(now())->addDays($tokenExpiresInDays);
    }

    public function preview(int $tokenExpiresInDays): string
    {
        $expiresAt = $this->expiresAt($tokenExpiresInDays)->timezone(config('app.timezone'));

        return 'Este token expirará el '.$expiresAt->format('d/m/Y').' a las '.$expiresAt->format('H:i').', hora de Lima.';
    }

    public function daysFromLegacyMinutes(int $minutes): int
    {
        return max(self::MIN_EXPIRES_IN_DAYS, min(self::MAX_EXPIRES_IN_DAYS, (int) ceil($minutes / 1440)));
    }

    private function assertValidDays(int $tokenExpiresInDays): void
    {
        if ($tokenExpiresInDays < self::MIN_EXPIRES_IN_DAYS || $tokenExpiresInDays > self::MAX_EXPIRES_IN_DAYS) {
            throw new \InvalidArgumentException('La vigencia del token debe estar entre 1 y 365 días.');
        }
    }
}
