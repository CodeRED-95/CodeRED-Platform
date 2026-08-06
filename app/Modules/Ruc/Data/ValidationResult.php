<?php

namespace App\Modules\Ruc\Data;

class ValidationResult
{
    public bool $valid = false;
    public array $errors = [];
    public array $warnings = [];

    public ?string $ruc = null;
    public array $data = [];
    public bool $isDuplicate = false;
    public int $firstOccurrence = 0;

    public function addError(string $error): self
    {
        $this->errors[] = $error;
        return $this;
    }

    public function addWarning(string $warning): self
    {
        $this->warnings[] = $warning;
        return $this;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }
}
