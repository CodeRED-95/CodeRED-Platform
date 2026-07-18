<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Actions\ApplyAgencyMoveAction;
use App\Modules\Agencies\Enums\AgencySize;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Livewire\Component;

class Form extends Component
{
    public ?Agency $agency = null;

    public string $mode = 'create';

    public string $code = '';
    public string $name = '';
    public ?string $short_name = null;
    public ?string $slug = null;
    public string $department = '';
    public string $province = '';
    public string $district = '';
    public string $address = '';
    public ?string $reference = null;
    public ?string $phone = null;
    public ?string $secondary_phone = null;
    public ?string $email = null;
    public ?string $schedule = null;
    public ?string $latitude = null;
    public ?string $longitude = null;
    public ?string $map_url = null;
    public array $services = [];
    public ?string $observations = null;
    public string $status = 'under_review';
    public ?string $size = null;
    public bool $is_operations_center = false;
    public string $source = 'manual';
    public ?string $source_reference = null;
    public ?string $source_text = null;
    public string $servicesInput = '';
    public bool $has_moved = false;
    public ?int $moved_to_agency_id = null;
    public ?string $moved_to_address = null;
    public ?string $move_notice = null;
    public ?string $moved_at = null;

    public string $destinationSearch = '';

    public function mount(?Agency $agency = null): void
    {
        $this->agency = $agency;
        $this->mode = $agency ? 'edit' : 'create';

        Gate::authorize($agency ? 'update' : 'create', $agency ?? Agency::class);

        if ($agency) {
            $this->fill([
                'code' => $agency->code,
                'name' => $agency->name,
                'short_name' => $agency->short_name,
                'slug' => $agency->slug,
                'department' => $agency->department,
                'province' => $agency->province,
                'district' => $agency->district,
                'address' => $agency->address,
                'reference' => $agency->reference,
                'phone' => $agency->phone,
                'secondary_phone' => $agency->secondary_phone,
                'email' => $agency->email,
                'schedule' => $agency->schedule,
                'latitude' => $agency->latitude,
                'longitude' => $agency->longitude,
                'map_url' => $agency->map_url,
                'services' => $agency->services ?? [],
                'observations' => $agency->observations,
                'status' => $agency->status?->value ?? $agency->status,
                'size' => $agency->size?->value ?? $agency->size,
                'is_operations_center' => (bool) $agency->is_operations_center,
                'source' => $agency->source,
                'source_reference' => $agency->source_reference,
                'source_text' => $agency->source_text,
                'has_moved' => (bool) $agency->has_moved,
                'moved_to_agency_id' => $agency->moved_to_agency_id,
                'moved_to_address' => $agency->moved_to_address,
                'move_notice' => $agency->move_notice,
                'moved_at' => optional($agency->moved_at)?->toDateString(),
            ]);
            $this->servicesInput = implode(', ', $agency->services ?? []);
        }
    }

    public function save(ApplyAgencyMoveAction $moveAction): void
    {
        $data = $this->validate();

        $payload = $this->normalizePayload($data);

        $agency = DB::transaction(function () use ($payload, $moveAction): Agency {
            if ($this->mode === 'create') {
                $payload['source'] = $payload['source'] ?: 'manual';
                $payload['source_reference'] = $payload['source_reference'] ?: null;
                $payload['data_version'] = 1;
                $agency = Agency::query()->create($payload);
            } else {
                $agency = $this->agency;
                $agency->fill($payload)->save();
            }

            if ($payload['has_moved'] || ($this->agency?->has_moved && ! $payload['has_moved'])) {
                $moveAction->execute(
                    $agency,
                    $payload,
                    auth()->id(),
                    request()->ip(),
                    request()->userAgent()
                );
            }

            return $agency;
        });

        session()->flash('success', $this->mode === 'edit' ? 'Agencia actualizada correctamente.' : 'Agencia creada correctamente.');
        $this->redirectRoute('admin.agencies.show', $agency);
    }

    public function getDestinationOptionsProperty()
    {
        return Agency::query()
            ->active()
            ->whereNull('deleted_at')
            ->when($this->agency?->exists, fn ($query) => $query->whereKeyNot($this->agency->id))
            ->when($this->destinationSearch !== '', function ($query): void {
                $search = mb_strtolower(trim($this->destinationSearch));
                $query->where(function ($sub) use ($search): void {
                    foreach (['code', 'name', 'department', 'province', 'district'] as $field) {
                        $sub->orWhereRaw("unaccent(lower($field)) ILIKE unaccent(?)", ['%'.$search.'%']);
                    }
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'code', 'name', 'department', 'province', 'district', 'address']);
    }

    public function getSelectedDestinationProperty(): ?Agency
    {
        if (! $this->moved_to_agency_id) {
            return null;
        }

        return Agency::query()
            ->withTrashed()
            ->select(['id', 'code', 'name', 'department', 'province', 'district', 'address'])
            ->find($this->moved_to_agency_id);
    }

    public function selectDestination(?int $agencyId): void
    {
        $this->moved_to_agency_id = $agencyId;

        if ($agencyId === null) {
            $this->destinationSearch = '';
        }
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('agencies', 'code')->ignore($this->agency?->id)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('agencies', 'slug')->ignore($this->agency?->id)->whereNull('deleted_at'),
            ],
            'department' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'reference' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:255'],
            'secondary_phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'schedule' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'map_url' => ['nullable', 'url', 'max:2048'],
            'services' => ['array'],
            'services.*' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string'],
            'servicesInput' => ['nullable', 'string'],
            'status' => ['required', 'in:'.implode(',', array_map(fn ($case) => $case->value, AgencyStatus::cases()))],
            'size' => ['nullable', 'in:'.implode(',', array_map(fn ($case) => $case->value, AgencySize::cases()))],
            'is_operations_center' => ['boolean'],
            'source' => ['required', 'string', 'max:100'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'source_text' => ['nullable', 'string'],
            'has_moved' => ['boolean'],
            'moved_to_agency_id' => [
                'nullable',
                Rule::exists('agencies', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', AgencyStatus::Active->value),
            ],
            'moved_to_address' => ['nullable', 'string'],
            'move_notice' => ['nullable', 'string'],
            'moved_at' => ['nullable', 'date'],
        ];
    }

    private function normalizePayload(array $data): array
    {
        $payload = $data;
        foreach (['code', 'name', 'short_name', 'department', 'province', 'district', 'phone', 'secondary_phone', 'email', 'reference', 'schedule', 'map_url', 'observations', 'source_text', 'moved_to_address', 'move_notice'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $payload[$field] = trim(preg_replace('/\s+/u', ' ', $payload[$field]));
                $payload[$field] = $payload[$field] === '' ? null : $payload[$field];
            }
        }

        $payload['code'] = strtoupper((string) $payload['code']);
        $payload['name'] = trim((string) preg_replace('/\s+/u', ' ', $payload['name']));
        $payload['slug'] = $payload['slug'] ?: Str::slug($payload['name']);
        $payload['email'] = $payload['email'] ? mb_strtolower($payload['email']) : null;
        $payload['services'] = array_values(array_filter(array_map(
            fn ($service) => is_string($service) ? trim(preg_replace('/\s+/u', ' ', $service)) : null,
            preg_split('/[,\n]/', (string) ($payload['servicesInput'] ?? ''))
        )));
        unset($payload['servicesInput']);
        $payload['is_operations_center'] = (bool) ($payload['is_operations_center'] ?? false);
        $payload['has_moved'] = (bool) ($payload['has_moved'] ?? false);

        if (! $payload['has_moved']) {
            $payload['moved_to_agency_id'] = null;
            $payload['moved_to_address'] = null;
            $payload['move_notice'] = $payload['move_notice'] ?: null;
            $payload['moved_at'] = $payload['moved_at'] ?: null;
        }

        return $payload;
    }

    public function render()
    {
        return view('livewire.admin.agencies.form', [
            'statuses' => AgencyStatus::options(),
            'sizes' => AgencySize::options(),
            'destinations' => $this->destinationOptions,
        ])->layout('layouts.app', ['pageTitle' => $this->mode === 'edit' ? 'Editar agencia' : 'Nueva agencia']);
    }
}
