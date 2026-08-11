<?php

declare(strict_types=1);

namespace App\Modules\Shalom\Livewire;

use App\Modules\Shalom\Models\ShalomDeliveryRecord;
use Livewire\Attributes\On;
use Livewire\Component;

class DeliveryRecordSearch extends Component
{
    public string $username = '';

    public ?string $field_filter = null;

    /**
     * @var array<int, ShalomDeliveryRecord>
     */
    public array $records = [];

    public bool $searched = false;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'field_filter' => ['nullable', 'string', 'in:DNI,CE,RUC,OS,Clave'],
        ];
    }

    #[On('search')]
    public function search(): void
    {
        $this->validate();

        $this->records = ShalomDeliveryRecord::where('username', $this->username)
            ->when($this->field_filter, fn ($q) => $q->where('field', $this->field_filter))
            ->latest('timestamp')
            ->limit(100)
            ->get()
            ->toArray();

        $this->searched = true;
    }

    public function render()
    {
        return view('livewire.shalom.delivery-record-search');
    }
}
