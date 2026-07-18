<?php

namespace App\Modules\Agencies\Data;

use App\Modules\Agencies\Enums\AgencySize;
use App\Modules\Agencies\Enums\AgencyStatus;

class AgencyImportRowData
{
    public function __construct(
        public readonly array $raw,
        public readonly array $normalized,
        public readonly array $warnings,
        public readonly array $errors,
        public readonly bool $valid,
    ) {
    }

    public static function make(array $raw, array $normalized, array $warnings = [], array $errors = []): self
    {
        return new self($raw, $normalized, $warnings, $errors, $errors === []);
    }

    public function toArray(): array
    {
        return [
            ...$this->normalized,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'valid' => $this->valid,
        ];
    }
}
