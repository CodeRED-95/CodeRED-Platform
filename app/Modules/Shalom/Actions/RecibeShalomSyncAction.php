<?php

declare(strict_types=1);

namespace App\Modules\Shalom\Actions;

use App\Modules\Shalom\Models\ShalomApiKey;
use App\Modules\Shalom\Models\ShalomDeliveryRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecibeShalomSyncAction
{
    private ?ShalomApiKey $apiKey = null;

    private ?Request $request = null;

    /**
     * Recibe y almacena registros de sincronización de Shalom Recordar
     *
     * @param  array<array-key, array{field: string, value: string, timestamp: string}>  $records
     * @param  string  $username  Usuario de la extensión
     * @param  ShalomApiKey|null  $apiKey  API key que autenticó la solicitud
     * @param  Request|null  $request  Solicitud HTTP (para auditoría)
     * @return string Batch ID de la sincronización
     *
     * @throws ValidationException
     */
    public function execute(array $records, string $username, ?ShalomApiKey $apiKey = null, ?Request $request = null): string
    {
        $this->apiKey = $apiKey;
        $this->request = $request;
        $syncBatchId = Str::uuid()->toString();

        // Validar que cada registro tiene campos requeridos
        foreach ($records as $record) {
            if (! isset($record['field'], $record['value'], $record['timestamp'])) {
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
                    'user_id' => $this->apiKey?->user_id,
                    'shalom_api_key_id' => $this->apiKey?->id,
                    'ip_address' => $this->request?->ip(),
                    'user_agent' => $this->request?->userAgent(),
                ]);
            }
        });

        // Log auditoría
        Log::info('Shalom sync received', [
            'username' => $username,
            'batch_id' => $syncBatchId,
            'record_count' => count($records),
            'api_key_id' => $this->apiKey?->id,
            'user_id' => $this->apiKey?->user_id,
            'ip_address' => $this->request?->ip(),
        ]);

        return $syncBatchId;
    }
}
