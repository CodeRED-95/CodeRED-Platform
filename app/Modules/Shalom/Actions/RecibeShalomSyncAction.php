<?php

declare(strict_types=1);

namespace App\Modules\Shalom\Actions;

use App\Modules\Shalom\Models\ShalomDeliveryRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecibeShalomSyncAction
{
    /**
     * Recibe y almacena registros de sincronización de Shalom Recordar
     *
     * @param array<array-key, array{field: string, value: string, timestamp: string}> $records
     * @param string $username Usuario de la extensión
     * @return string Batch ID de la sincronización
     * @throws ValidationException
     */
    public function execute(array $records, string $username): string
    {
        $syncBatchId = Str::uuid()->toString();

        // Validar que cada registro tiene campos requeridos
        foreach ($records as $record) {
            if (!isset($record['field'], $record['value'], $record['timestamp'])) {
                throw ValidationException::withMessages([
                    'records' => 'Each record must have field, value, and timestamp',
                ]);
            }
        }

        // Guardar en BD de forma transaccional
        DB::transaction(function () use ($records, $username, $syncBatchId): void {
            foreach ($records as $record) {
                ShalomDeliveryRecord::create([
                    'username' => $username,
                    'field' => $record['field'],
                    'value' => $record['value'],
                    'timestamp' => $record['timestamp'],
                    'sync_batch_id' => $syncBatchId,
                ]);
            }
        });

        // Log auditoría
        \Log::info('Shalom sync received', [
            'username' => $username,
            'batch_id' => $syncBatchId,
            'record_count' => count($records),
        ]);

        return $syncBatchId;
    }
}
