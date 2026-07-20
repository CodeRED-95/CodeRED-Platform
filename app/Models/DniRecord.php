<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class DniRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'dni',
        'nombre_completo',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'genero',
        'fecha_nacimiento',
        'codigo_verificacion',
        'source',
        'provider_reference',
        'last_verified_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (preg_match('/^\d{8}$/', (string) $record->dni) !== 1) {
                throw ValidationException::withMessages(['dni' => 'El DNI debe contener exactamente ocho dígitos.']);
            }
        });
    }

    public function needsProviderRefresh(int $afterDays): bool
    {
        if ($this->source !== 'perudevs') {
            return false;
        }

        $verifiedAt = $this->getRawOriginal('last_verified_at');

        return ! is_string($verifiedAt) || CarbonImmutable::parse($verifiedAt)->lt(now()->subDays($afterDays));
    }

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date:Y-m-d', 'last_verified_at' => 'datetime'];
    }
}
