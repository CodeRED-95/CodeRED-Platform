<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NativeAgencyComparison
{
    public function normalize(string $value): string
    {
        return (string) preg_replace('/\s+/u', ' ', Str::upper(Str::ascii(trim($value))));
    }

    public function revision(Collection $agencies): string
    {
        return hash('sha256', $agencies->map(fn ($agency) => [
            $agency->id, $agency->name, $agency->old_name, $agency->department,
            $agency->province, $agency->district, $agency->status->value,
            $agency->has_moved, $agency->texto_chosen_terrestre, $agency->updated_at?->toISOString(),
        ])->toJson());
    }

    public function compare(array $snapshot, Collection $agencies): array
    {
        $index = [];
        $origins = [];
        foreach ($agencies as $agency) {
            $location = implode(', ', [$agency->department, $agency->province, $agency->district]);
            foreach (array_filter([$agency->name, $agency->old_name]) as $name) {
                $index[$this->normalize($location.' - AG. '.$name)][$agency->id] = $agency;
                if ($this->normalize($name) === $this->normalize($snapshot['origin_name'])) {
                    $origins[$agency->id] = $agency;
                }
            }
        }

        $blocked = [];
        if (count($origins) !== 1) {
            $blocked[] = 'No se identificó una única agencia de origen. Revisa el origen antes de sincronizar.';
        }
        $protected = array_fill_keys(array_keys($origins), true);
        $seen = [];
        $matched = [];
        $review = [];
        $changes = [];
        foreach ($snapshot['entries'] as $entry) {
            $key = $this->normalize($entry['label']);
            $candidates = $index[$key] ?? [];
            if (isset($seen[$key])) {
                $blocked[] = 'La captura contiene agencias duplicadas.';
            }
            $seen[$key] = true;
            if (count($candidates) !== 1) {
                foreach ($candidates as $candidate) {
                    $protected[$candidate->id] = true;
                }
                $review[] = ['label' => $entry['label'], 'reason' => $candidates ? 'Coincidencia ambigua' : 'No existe en Platform'];

                continue;
            }
            $agency = reset($candidates);
            if (isset($matched[$agency->id])) {
                $blocked[] = 'Dos destinos nativos apuntan a la misma agencia de Platform. Requiere revisión.';
            }
            $matched[$agency->id] = true;
            if ($agency->has_moved || $agency->status === AgencyStatus::Moved) {
                $review[] = ['label' => $entry['label'], 'reason' => 'Agencia trasladada; requiere revisión'];

                continue;
            }
            if (! isset($protected[$agency->id]) && $agency->status !== AgencyStatus::Active) {
                $changes[] = $this->change($agency, AgencyStatus::Active);
            }
        }

        foreach ($agencies as $agency) {
            if ($agency->status === AgencyStatus::Active && ! $agency->has_moved
                && ! isset($matched[$agency->id]) && ! isset($protected[$agency->id])) {
                if (blank($agency->texto_chosen_terrestre)) {
                    $review[] = ['label' => $agency->name, 'reason' => 'Ausente y sin referencia terrestre en Platform; no se modifica'];

                    continue;
                }
                $changes[] = $this->change($agency, AgencyStatus::Inactive);
            }
        }
        $changes = array_values(array_filter($changes, fn ($change) => ! isset($protected[$change['id']])));
        $deactivate = count(array_filter($changes, fn ($change) => $change['to'] === 'inactive'));
        $active = $agencies->filter(fn ($agency) => $agency->status === AgencyStatus::Active && filled($agency->texto_chosen_terrestre))->count();
        if ($deactivate / max(1, $active) > (float) config('agency_native_sync.maximum_deactivation_ratio')) {
            $blocked[] = 'Se desactivaría más del 10 % del catálogo activo. Revisa si la captura está incompleta.';
        }
        if (count($matched) / max(1, count($snapshot['entries'])) < 0.80) {
            $blocked[] = 'Menos del 80 % de la captura coincide con Platform. Requiere revisión manual.';
        }

        return [
            'revision' => $this->revision($agencies),
            'snapshot_hash' => hash('sha256', json_encode([$snapshot['origin_name'], $snapshot['entries']])),
            'captured_at' => $snapshot['captured_at'],
            'origin' => count($origins) === 1 ? reset($origins)->name : $snapshot['origin_name'],
            'native_count' => count($snapshot['entries']),
            'matched_count' => count($matched),
            'activate_count' => count($changes) - $deactivate,
            'deactivate_count' => $deactivate,
            'changes' => $changes,
            'review' => $review,
            'blocked_reasons' => array_values(array_unique($blocked)),
            'can_apply' => $blocked === [],
        ];
    }

    private function change(Agency $agency, AgencyStatus $target): array
    {
        return [
            'id' => $agency->id, 'name' => $agency->getAttribute('name'),
            'location' => implode(', ', [$agency->getAttribute('department'), $agency->getAttribute('province'), $agency->getAttribute('district')]),
            'from' => $agency->status->value, 'to' => $target->value,
        ];
    }
}
