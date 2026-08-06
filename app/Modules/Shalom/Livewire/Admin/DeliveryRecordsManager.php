<?php

declare(strict_types=1);

namespace App\Modules\Shalom\Livewire\Admin;

use App\Modules\Shalom\Models\ShalomDeliveryRecord;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithPagination;

class DeliveryRecordsManager extends Component
{
    use WithPagination;

    public string $search_username = '';

    public ?string $filter_field = null;

    public ?string $date_from = null;

    public ?string $date_to = null;

    public int $per_page = 25;

    public function rules(): array
    {
        return [
            'search_username' => ['nullable', 'string', 'max:255'],
            'filter_field' => ['nullable', 'string', 'in:DNI,CE,RUC,OS,Clave'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['integer', 'min:10', 'max:100'],
        ];
    }

    #[On('search')]
    public function search(): void
    {
        $this->resetPage();
    }

    public function getRecords()
    {
        return ShalomDeliveryRecord::query()
            ->when($this->search_username, fn ($q) => $q->where('username', 'like', '%' . $this->search_username . '%'))
            ->when($this->filter_field, fn ($q) => $q->where('field', $this->filter_field))
            ->when($this->date_from, fn ($q) => $q->whereDate('created_at', '>=', $this->date_from))
            ->when($this->date_to, fn ($q) => $q->whereDate('created_at', '<=', $this->date_to))
            ->latest('created_at')
            ->with(['user', 'apiKey'])
            ->paginate($this->per_page);
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $records = $this->getRecords()->items();

        return response()->streamDownload(function () use ($records): void {
            $csv = fopen('php://output', 'w');

            fputcsv($csv, [
                'ID', 'Username', 'Field', 'Value', 'Timestamp', 'Sync Batch ID',
                'User', 'API Key', 'IP Address', 'Created At',
            ]);

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
                ]);
            }

            fclose($csv);
        }, 'shalom-delivery-records-' . now()->format('Y-m-d-His') . '.csv');
    }

    public function render()
    {
        return view('livewire.shalom.admin.delivery-records-manager', [
            'records' => $this->getRecords(),
        ]);
    }
}
