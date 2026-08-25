<?php

namespace App\Modules\Agencies\Actions;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Support\AgencySyncFields;
use App\Modules\Agencies\Models\AgencyImportRun;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConfirmAgencyImportRunAction
{
    /** @return array{created:int,updated:int,renamed:int,skipped:int} */
    public function execute(AgencyImportRun $run, int $userId): array
    {
        if ($run->status === 'completed') {
            return ['created' => 0, 'updated' => 0, 'renamed' => 0, 'skipped' => 0];
        }

        if ($run->status !== 'ready_for_review') {
            throw ValidationException::withMessages(['importRun' => 'La ejecución no está lista para confirmar.']);
        }

        $result = ['created' => 0, 'updated' => 0, 'renamed' => 0, 'skipped' => 0];
        $run->update(['status' => 'importing', 'stage' => 'Aplicando cambios', 'progress' => 0, 'error_message' => null]);

        try {
            DB::transaction(function () use ($run, $userId, &$result): void {
                $items = $run->items()->where('selected', true)->orderBy('id')->lockForUpdate()->get();
                $total = max(1, $items->count());

                foreach ($items as $index => $item) {
                    if (in_array($item->action, ['conflict', 'unchanged', 'invalid', 'missing'], true)) {
                        $result['skipped']++;

                        continue;
                    }

                    $incoming = $this->writableData($item->incoming_data ?? []);
                    $agency = $this->matchAgencyForWrite($incoming);

                    if ($item->action === 'create' && ! $agency) {
                        $agency = new Agency;
                        $agency->status = AgencyStatus::UnderReview;
                        $agency->source = 'shalom_sync';
                        $agency->created_by = $userId;
                        $this->fillNonEmpty($agency, $incoming);
                        $agency->save();
                        $item->update(['matched_agency_id' => $agency->id]);
                        $result['created']++;

                        continue;
                    }

                    if (! $agency) {
                        $result['skipped']++;

                        continue;
                    }

                    $oldName = $agency->name;
                    $agency->nameChangeSource = 'shalom_sync';
                    $agency->nameChangeImportRunId = $run->id;
                    $agency->nameChangeUserId = $userId;
                    $this->fillNonEmpty($agency, $incoming);
                    $agency->source = 'shalom_sync';
                    $agency->updated_by = $userId;
                    $agency->save();

                    if ($oldName !== $agency->name) {
                        $result['renamed']++;
                    } else {
                        $result['updated']++;
                    }

                    if (($index + 1) % 25 === 0) {
                        $run->update(['progress' => min(99, (int) floor((($index + 1) / $total) * 100))]);
                    }
                }

                $run->update([
                    'status' => 'completed',
                    'stage' => 'Importación completada',
                    'progress' => 100,
                    'confirmed_by' => $userId,
                    'confirmed_at' => now(),
                    'finished_at' => now(),
                ]);
            }, 3);
        } catch (Throwable $exception) {
            $run->refresh()->update([
                'status' => 'ready_for_review',
                'stage' => 'Error al confirmar; no se aplicaron cambios',
                'error_message' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        return $result;
    }

    private function fillNonEmpty(Agency $agency, array $data): void
    {
        foreach ($data as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $agency->{$field} = $value;
        }
    }

    private function writableData(array $data): array
    {
        return Arr::only($data, AgencySyncFields::FIELDS);
    }

    private function matchAgencyForWrite(array $incoming): ?Agency
    {
        if (! empty($incoming['external_id'])) {
            $agency = Agency::query()->lockForUpdate()->where('external_id', $incoming['external_id'])->first();
            if ($agency) {
                if (! empty($incoming['code']) && filled($agency->code) && $agency->code !== $incoming['code']) {
                    logger()->warning('Conflicto Shalom: external_id y code apuntan a agencias distintas.', [
                        'external_id' => $incoming['external_id'],
                        'code' => $incoming['code'],
                        'matched_agency_id' => $agency->id,
                    ]);
                }

                return $agency;
            }
        }

        if (! empty($incoming['code'])) {
            $agency = Agency::query()->lockForUpdate()->where('code', $incoming['code'])->first();

            // Mismo resguardo que en la vista previa: la abreviatura de Shalom
            // no es unica (PSC vale para PISAC y para PISCO), asi que no puede
            // emparejar con una agencia que ya lleva otro external_id. Sin
            // esto, confirmar sobrescribia una ficha con los datos de otra.
            if ($agency && $this->belongsToAnotherAgency($agency, $incoming['external_id'] ?? null)) {
                return null;
            }

            return $agency;
        }

        return null;
    }

    private function belongsToAnotherAgency(Agency $agency, mixed $incomingExternalId): bool
    {
        if (blank($incomingExternalId) || blank($agency->external_id)) {
            return false;
        }

        return (string) $agency->external_id !== (string) $incomingExternalId;
    }
}
