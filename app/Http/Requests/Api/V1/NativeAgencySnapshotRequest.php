<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class NativeAgencySnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_url' => ['required', 'in:https://sysnewos.shalomcontrol.com/service-order/items'],
            'channel' => ['required', 'in:terrestrial'],
            'origin_name' => ['required', 'string', 'max:255'],
            'captured_at' => ['required', 'date', 'after:'.now()->subMinutes(10)->toISOString(), 'before:'.now()->addMinute()->toISOString()],
            'filter' => ['present', 'nullable', 'string', 'max:0'],
            'complete' => ['required', 'accepted'],
            'end_observed' => ['required', 'accepted'],
            'stable_reads' => ['required', 'integer', 'min:2', 'max:6'],
            'total' => ['required', 'integer', 'max:3000'],
            'entries' => ['required', 'array', 'min:'.config('agency_native_sync.minimum_entries'), 'max:3000'],
            'entries.*.index' => ['required', 'integer', 'min:0'],
            'entries.*.label' => ['required', 'string', 'max:500', 'regex:/^.+, .+, .+ - AG\. .+$/u'],
        ];
    }
}
