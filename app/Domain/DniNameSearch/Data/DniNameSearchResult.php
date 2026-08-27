<?php

declare(strict_types=1);

namespace App\Domain\DniNameSearch\Data;

final readonly class DniNameSearchResult
{
    /** @param list<DniNameMatch> $matches */
    private function __construct(
        public string $status,
        public array $matches,
        public ?int $statusCode,
        public ?string $message,
        public bool $cacheHit = false,
    ) {}

    /** @param list<DniNameMatch> $matches */
    public static function found(array $matches, ?int $statusCode = 200, bool $cacheHit = false): self
    {
        return new self('found', $matches, $statusCode, null, $cacheHit);
    }

    public static function notFound(?int $statusCode = 200): self
    {
        return new self('not_found', [], $statusCode, 'No se encontraron coincidencias.');
    }

    public static function failed(string $status, ?int $statusCode = null, ?string $message = null): self
    {
        return new self($status, [], $statusCode, $message);
    }
}
