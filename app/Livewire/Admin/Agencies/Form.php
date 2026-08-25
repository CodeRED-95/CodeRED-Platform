<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Actions\ApplyAgencyMoveAction;
use App\Modules\Agencies\Enums\AgencySize;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Ruc\Models\Ubigeo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?Agency $agency = null;

    public string $mode = 'create';

    public ?int $external_id = null;

    public ?string $code = null;

    public string $name = '';

    public ?string $old_name = null;

    public ?string $short_name = null;

    public ?string $slug = null;

    public ?string $department = null;

    public ?string $province = null;

    public ?string $district = null;

    public ?string $address = null;

    public ?string $reference = null;

    public ?string $phone = null;

    public ?string $secondary_phone = null;

    public ?string $email = null;

    public ?string $schedule = null;

    public ?string $schedule_general = null;

    public ?string $schedule_sunday = null;

    public ?string $latitude = null;

    public ?string $longitude = null;

    public ?int $ubigeo_id = null;

    public ?string $ubigeo_code = null;

    public ?string $map_url = null;

    public array $services = [];

    public ?string $observations = null;

    public string $status = 'under_review';

    public ?string $size = null;

    public ?string $classification_category = null;

    public ?string $classification_sends_category = null;

    public ?string $classification_receives_category = null;

    public bool $is_operations_center = false;

    public ?string $source = 'manual';

    public ?string $source_reference = null;

    public ?string $source_text = null;

    public ?string $place = null;

    public ?string $texto_chosen_terrestre = null;

    public ?string $texto_chosen_aereo = null;

    public string $servicesInput = '';

    public bool $has_moved = false;

    public ?int $moved_to_agency_id = null;

    public ?string $moved_to_address = null;

    public ?string $move_notice = null;

    public ?string $moved_at = null;

    public string $destinationSearch = '';

    public function mount(?Agency $agency = null): void
    {
        if ($agency !== null && ! $agency->exists) {
            $agency = null;
        }

        $this->agency = $agency;
        $this->mode = $agency ? 'edit' : 'create';

        Gate::authorize($agency ? 'update' : 'create', $agency ?? Agency::class);

        if ($agency) {
            $this->fill([
                'external_id' => $agency->external_id,
                'code' => $agency->code,
                'name' => $agency->name,
                'old_name' => $agency->old_name,
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
                'schedule_general' => $agency->schedule_general,
                'schedule_sunday' => $agency->schedule_sunday,
                'latitude' => $agency->latitude,
                'longitude' => $agency->longitude,
                'ubigeo_id' => $agency->ubigeo_id,
                'ubigeo_code' => $this->resolveUbigeoCode($agency),
                'map_url' => $agency->map_url,
                'services' => $agency->services ?? [],
                'observations' => $agency->observations,
                'status' => $agency->status->value,
                'size' => $agency->size?->value,
                'classification_category' => $agency->classification_category,
                'classification_sends_category' => $agency->classification_sends_category,
                'classification_receives_category' => $agency->classification_receives_category,
                'is_operations_center' => (bool) $agency->is_operations_center,
                'source' => $agency->source,
                'source_reference' => $agency->source_reference,
                'source_text' => $agency->source_text,
                'place' => $agency->place,
                'texto_chosen_terrestre' => $agency->texto_chosen_terrestre,
                'texto_chosen_aereo' => $agency->texto_chosen_aereo,
                'has_moved' => (bool) $agency->has_moved,
                'moved_to_agency_id' => $agency->moved_to_agency_id,
                'moved_to_address' => $agency->moved_to_address,
                'move_notice' => $agency->move_notice,
                'moved_at' => optional($agency->moved_at)?->toDateString(),
            ]);
            $this->servicesInput = implode(', ', $agency->services ?? []);
        } else {
            $this->classification_category = $this->classification_category ?? 'PEQUEÑA';
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['department', 'province', 'district', 'name'], true)) {
            $this->refreshPlace();
        }
    }

    public function updatedUbigeoCode(): void
    {
        $this->syncUbigeo();
    }

    public function updatedClassificationCategory(): void
    {
        $this->is_operations_center = $this->normalizedOperationsCenterValue();
    }

    public function updatedIsOperationsCenter(): void
    {
        if ($this->is_operations_center) {
            $this->classification_category = 'GRANDE / CO';

            return;
        }

        if ($this->normalizedOperationsCenterValue()) {
            $this->classification_category = 'PEQUEÑA';
        }
    }

    public function updatedDepartment(): void
    {
        $this->refreshPlace();
    }

    public function updatedProvince(): void
    {
        $this->refreshPlace();
    }

    public function updatedDistrict(): void
    {
        $this->refreshPlace();
    }

    public function updatedName(): void
    {
        $this->refreshPlace();
    }

    /**
     * Vacia el nombre anterior de la agencia.
     *
     * El campo es de solo lectura porque lo escribe el flujo de renombrado, no
     * una persona; este boton es la unica forma de retirarlo cuando ya no
     * aporta —y de paso deja de usarse como alias en las busquedas—. Se limita
     * a super-admin, la misma condicion que ya filtraba `old_name` al guardar.
     *
     * No escribe en la base: el cambio se consolida al guardar la agencia, para
     * que siga pasando por la validacion y la autorizacion del formulario.
     */
    public function clearOldName(): void
    {
        Gate::authorize('update', $this->agency ?? Agency::class);

        if (! auth()->user()?->isSuperAdmin()) {
            $this->addError('old_name', 'Solo un super administrador puede retirar el nombre anterior.');

            return;
        }

        $this->old_name = null;
        $this->dispatch('toast', type: 'success', message: 'Nombre anterior retirado. Guarda la agencia para aplicarlo.');
    }

    public function syncUbigeo(): void
    {
        $ubigeo = $this->resolveUbigeo();

        if (! $ubigeo) {
            $this->addError('ubigeo_code', 'La agencia no tiene un código de ubigeo válido.');

            return;
        }

        $this->ubigeo_id = $ubigeo->id;
        $this->department = $ubigeo->departamento;
        $this->province = $ubigeo->provincia;
        $this->district = $ubigeo->distrito;
        $this->refreshPlace();
    }

    private function refreshPlace(): void
    {
        $parts = array_values(array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : $value, [
            $this->department,
            $this->province,
            $this->district,
            $this->name,
        ]), static fn ($value) => filled($value)));

        $this->place = implode(' / ', $parts);
    }

    private function resolveUbigeo(): ?Ubigeo
    {
        if ($this->ubigeo_id) {
            $ubigeo = Ubigeo::query()->find($this->ubigeo_id);
            if ($ubigeo) {
                return $ubigeo;
            }
        }

        return $this->resolveUbigeoByCode($this->ubigeo_code);
    }

    private function normalizedOperationsCenterValue(): bool
    {
        return mb_strtoupper(trim((string) $this->classification_category), 'UTF-8') === 'GRANDE / CO';
    }

    private function resolveUbigeoCode(?Agency $agency): ?string
    {
        if ($agency && filled($agency->ubigeo_id)) {
            $ubigeo = Ubigeo::query()->find($agency->ubigeo_id);

            if ($ubigeo) {
                return $ubigeo->codigo;
            }
        }

        return null;
    }

    private function resolveUbigeoByCode(?string $code): ?Ubigeo
    {
        $normalized = $this->normalizeUbigeoCode($code);
        if ($normalized === null) {
            return null;
        }

        return Ubigeo::query()->where('codigo', $normalized)->first();
    }

    private function normalizeUbigeoCode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\D+/', '', trim((string) $value));

        if ($value === '') {
            return null;
        }

        return strlen($value) === 6 ? $value : null;
    }

    public function save(ApplyAgencyMoveAction $moveAction): void
    {
        Gate::authorize($this->agency ? 'update' : 'create', $this->agency ?? Agency::class);

        $this->normalizeInput();
        $data = $this->validate();

        $payload = $this->normalizePayload($data);
        $wasMoved = (bool) $this->agency?->has_moved;

        $agency = DB::transaction(function () use ($payload, $moveAction, $wasMoved): Agency {
            if ($this->mode === 'create') {
                $payload['source'] = 'manual';
                $payload['source_reference'] = null;
                $payload['source_text'] = null;
                $payload['data_version'] = 1;
                $agency = Agency::query()->create($payload);
            } else {
                $agency = $this->agency;
                unset($payload['source'], $payload['source_reference'], $payload['source_text']);
                $agency->fill($payload)->save();
            }

            if ($payload['has_moved'] || $wasMoved) {
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

    /** @return Collection<int, Agency> */
    public function getDestinationOptionsProperty(): Collection
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
            'external_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('agencies', 'external_id')->ignore($this->agency?->id),
            ],
            // Sin unicidad: la abreviatura de Shalom se repite entre agencias
            // (PSC vale para PISAC y para PISCO). La identidad es external_id.
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'old_name' => ['nullable', 'string', 'max:255'],
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
            'schedule_general' => ['nullable', 'string', 'max:255'],
            'schedule_sunday' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'map_url' => ['nullable', 'string', 'max:2048'],
            'services' => ['array'],
            'services.*' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string'],
            'servicesInput' => ['nullable', 'string'],
            'status' => [
                'required',
                Rule::in(array_map(fn (AgencyStatus $case) => $case->value, AgencyStatus::cases())),
                Rule::notIn($this->has_moved ? [] : [AgencyStatus::Moved->value]),
            ],
            'size' => ['nullable', Rule::in(array_map(fn (AgencySize $case) => $case->value, AgencySize::cases()))],
            'ubigeo_id' => ['nullable', 'integer', 'exists:ubigeos,id'],
            'ubigeo_code' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && trim((string) $value) !== '' && $this->normalizeUbigeoCode($value) === null) {
                    $fail('El código oficial de ubigeo debe tener exactamente seis dígitos.');
                }
            }],
            'classification_category' => ['required', 'string', 'max:255'],
            'classification_sends_category' => ['nullable', 'string', 'max:255'],
            'classification_receives_category' => ['nullable', 'string', 'max:255'],
            'is_operations_center' => ['boolean'],
            'source' => ['required', 'string', 'max:100'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'source_text' => ['nullable', 'string'],
            'place' => ['nullable', 'string', 'max:1000'],
            'texto_chosen_terrestre' => ['nullable', 'string', 'max:10000'],
            'texto_chosen_aereo' => ['nullable', 'string', 'max:10000'],
            'has_moved' => ['boolean'],
            'moved_to_agency_id' => [
                Rule::requiredIf($this->has_moved && blank($this->moved_to_address)),
                'nullable',
                Rule::exists('agencies', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', AgencyStatus::Active->value),
            ],
            'moved_to_address' => [
                Rule::requiredIf($this->has_moved && $this->moved_to_agency_id === null),
                'nullable',
                'string',
            ],
            'move_notice' => ['nullable', 'string'],
            'moved_at' => ['nullable', 'date'],
        ];
    }

    private function normalizeInput(): void
    {
        $this->code = filled($this->code) ? strtoupper(trim((string) $this->code)) : null;
        $this->slug = filled($this->slug) ? Str::slug((string) $this->slug) : null;
        $this->email = filled($this->email) ? mb_strtolower(trim((string) $this->email)) : null;
    }

    private function normalizePayload(array $data): array
    {
        $payload = $data;
        foreach (['code', 'name', 'old_name', 'short_name', 'department', 'province', 'district', 'phone', 'secondary_phone', 'email', 'reference', 'schedule', 'schedule_general', 'schedule_sunday', 'map_url', 'observations', 'source_text', 'texto_chosen_terrestre', 'texto_chosen_aereo', 'moved_to_address', 'move_notice', 'place', 'classification_category', 'classification_sends_category', 'classification_receives_category', 'ubigeo_code'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $payload[$field] = trim(preg_replace('/\s+/u', ' ', $payload[$field]));
                $payload[$field] = $payload[$field] === '' ? null : $payload[$field];
            }
        }

        if (! auth()->user()?->isSuperAdmin()) {
            unset($payload['old_name']);
        }

        unset($payload['map_url']);

        $payload['code'] = strtoupper((string) $payload['code']);
        $payload['name'] = trim((string) preg_replace('/\s+/u', ' ', $payload['name']));
        $payload['slug'] = $payload['slug'] ?: Str::slug($payload['name']);
        $payload['size'] = filled($payload['size'] ?? null) ? $payload['size'] : null;
        if ($payload['is_operations_center']) {
            $payload['classification_category'] = 'GRANDE / CO';
        }
        $payload['email'] = $payload['email'] ? mb_strtolower($payload['email']) : null;
        $payload['services'] = array_values(array_filter(array_map(
            fn ($service) => is_string($service) ? trim(preg_replace('/\s+/u', ' ', $service)) : null,
            preg_split('/[,\n]/', (string) ($payload['servicesInput'] ?? ''))
        )));
        unset($payload['servicesInput']);
        $payload['is_operations_center'] = $this->normalizedOperationsCenterValue();
        $payload['ubigeo_code'] = $this->normalizeUbigeoCode($payload['ubigeo_code'] ?? null);
        if ($payload['ubigeo_code'] !== null) {
            $payload['ubigeo_id'] = Ubigeo::query()->where('codigo', $payload['ubigeo_code'])->value('id');
        }
        unset($payload['ubigeo_code']);
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
            'destinations' => $this->getDestinationOptionsProperty(),
        ])->layout('layouts.app', ['pageTitle' => $this->mode === 'edit' ? 'Editar agencia' : 'Nueva agencia']);
    }
}
