<?php

namespace App\Modules\Agencies\Services;

use App\Core\Audit\AuditLogger;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgencyExportService
{
    public function __construct(private AgencySearchService $searchService, private AuditLogger $audit) {}

    public function download(array $filters, bool $filtered, User $actor): StreamedResponse
    {
        $query = $filtered ? $this->searchService->adminQuery($filters) : Agency::query();
        $count = (clone $query)->count();
        $now = CarbonImmutable::now('America/Lima');
        $filename = 'agencias-'.$now->format('Y-m-d-His').'.json';
        $safeFilters = $filtered ? $this->normalizeFilters($filters) : [];

        $this->audit->log($actor, 'agency_export_created', [], [
            'filename' => $filename,
            'record_count' => $count,
            'filtered' => $filtered,
        ], ['filename', 'record_count', 'filtered']);

        return response()->streamDownload(function () use ($query, $now, $count, $filtered, $safeFilters): void {
            $metadata = [
                'application' => 'CodeRED Platform',
                'format' => 'agency-export',
                'version' => 1,
                'generated_at' => $now->toIso8601String(),
                'timezone' => 'America/Lima',
                'record_count' => $count,
                'filtered' => $filtered,
                'filters' => $safeFilters,
            ];
            echo '{"metadata":'.json_encode($metadata, $this->jsonFlags()).',"agencies":[';
            $first = true;
            foreach ($query->orderBy('id')->lazyById(500) as $agency) {
                echo $first ? '' : ',';
                echo json_encode($this->forExport($agency), $this->jsonFlags());
                $first = false;
            }
            echo ']}';
        }, $filename, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function forExport(Agency $agency): array
    {
        return [
            'id' => $agency->id,
            'external_id' => $agency->external_id,
            'code' => $agency->code,
            'agencia' => $agency->name,
            'nombre_corto' => $agency->short_name,
            'departamento' => $agency->department,
            'provincia' => $agency->province,
            'distrito' => $agency->district,
            'direccion' => $agency->address,
            'referencia' => $agency->reference,
            'telefono' => $agency->phone,
            'telefono_secundario' => $agency->secondary_phone,
            'email' => $agency->email,
            'horario' => $agency->schedule,
            'latitud' => $agency->latitude === null ? null : (float) $agency->latitude,
            'longitud' => $agency->longitude === null ? null : (float) $agency->longitude,
            'link_mapa' => $agency->map_url,
            'servicios' => $agency->services,
            'tamano' => $agency->size?->value,
            'centro_operaciones' => $agency->is_operations_center,
            'estado' => $agency->status?->value,
            'texto_chosen_terrestre' => $agency->texto_chosen_terrestre,
            'texto_chosen_aereo' => $agency->texto_chosen_aereo,
            'trasladada' => $agency->has_moved,
            'trasladada_a_agencia_id' => $agency->moved_to_agency_id,
            'nueva_direccion' => $agency->moved_to_address,
            'aviso_traslado' => $agency->move_notice,
            'fecha_traslado' => $agency->moved_at?->format('Y-m-d'),
            'created_at' => $agency->created_at?->timezone('America/Lima')->toIso8601String(),
            'updated_at' => $agency->updated_at?->timezone('America/Lima')->toIso8601String(),
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        return collect($filters)->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')->all();
    }

    private function jsonFlags(): int
    {
        return JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    }
}
