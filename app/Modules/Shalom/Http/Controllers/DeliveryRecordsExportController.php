<?php

declare(strict_types=1);

namespace App\Modules\Shalom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shalom\Models\ShalomDeliveryRecord;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryRecordsExportController extends Controller
{
    /**
     * Exporta registros de entregas a CSV
     */
    public function csv(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', ShalomDeliveryRecord::class);

        $validated = $request->validate([
            'username' => ['nullable', 'string', 'max:255'],
            'field' => ['nullable', 'string', 'in:DNI,CE,RUC,OS,Clave'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $query = ShalomDeliveryRecord::query()
            ->when($validated['username'] ?? null, fn ($q, $username) => $q->where('username', 'like', '%' . $username . '%'))
            ->when($validated['field'] ?? null, fn ($q, $field) => $q->where('field', $field))
            ->when($validated['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($validated['user_id'] ?? null, fn ($q, $userId) => $q->where('user_id', $userId))
            ->with(['user', 'apiKey'])
            ->latest('created_at');

        $fileName = 'shalom-delivery-records-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $csv = fopen('php://output', 'w');

            // Encabezados
            fputcsv($csv, [
                'ID',
                'Username',
                'Field',
                'Value',
                'Timestamp',
                'Sync Batch ID',
                'User Email',
                'API Key Prefix',
                'IP Address',
                'Created At',
                'Updated At',
            ]);

            // Datos
            $query->chunk(500, function ($records) use ($csv): void {
                foreach ($records as $record) {
                    fputcsv($csv, [
                        $record->id,
                        $record->username,
                        $record->field,
                        $record->value,
                        $record->timestamp?->format('Y-m-d H:i:s'),
                        $record->sync_batch_id,
                        $record->user?->email ?? 'N/A',
                        $record->apiKey?->key_prefix ?? 'N/A',
                        $record->ip_address ?? 'N/A',
                        $record->created_at?->format('Y-m-d H:i:s'),
                        $record->updated_at?->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($csv);
        }, $fileName);
    }
}
