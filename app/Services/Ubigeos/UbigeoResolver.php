<?php

namespace App\Services\Ubigeos;

use App\Modules\Ruc\Models\Ubigeo;

final class UbigeoResolver
{
    public function findByCode(?string $code): ?Ubigeo
    {
        $code = $this->normalizeCode($code);

        if ($code === null) {
            return null;
        }

        return Ubigeo::query()->where('codigo', $code)->first();
    }

    public function normalizeCode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return null;
        }

        return str_pad($digits, 6, '0', STR_PAD_LEFT);
    }
}