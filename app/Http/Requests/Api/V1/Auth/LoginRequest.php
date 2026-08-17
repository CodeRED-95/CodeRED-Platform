<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Enums\ClientApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'application' => ['required', 'string', Rule::in(ClientApplication::values())],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:60'],
            'client_version' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function application(): ClientApplication
    {
        return ClientApplication::from((string) $this->input('application'));
    }

    /**
     * @return array{device_name:string|null,platform:string|null,client_version:string|null}
     */
    public function device(): array
    {
        return [
            'device_name' => $this->input('device_name'),
            'platform' => $this->input('platform'),
            'client_version' => $this->input('client_version'),
        ];
    }
}
