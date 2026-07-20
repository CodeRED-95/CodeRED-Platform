<?php

namespace App\Domain\Dni\Data;

final readonly class DniProviderResult
{
    private function __construct(public string $status, public ?DniData $data, public ?int $statusCode) {}

    public static function found(DniData $data, int $statusCode = 200): self
    {
        return new self('found', $data, $statusCode);
    }

    public static function notFound(?int $statusCode = 404): self
    {
        return new self('not_found', null, $statusCode);
    }

    public static function failed(string $status, ?int $statusCode = null): self
    {
        return new self($status, null, $statusCode);
    }
}
