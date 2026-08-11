<?php

namespace App\Http\Controllers\Public;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenRequestType;
use App\Events\TokenRequestCreated;
use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;
use App\Services\ApiTokens\TokenVaultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicTokenRequestController
{
    private TokenVaultService $vault;

    public function __construct(TokenVaultService $vault)
    {
        $this->vault = $vault;
    }

    public function create(Request $request): View
    {
        return view('public.token-requests.create', [
            'source' => $request->query('source', 'public-web'),
            'installationName' => $request->query('installation_name', 'Buscador Shalom Control'),
            'extensionVersion' => $request->query('version', ''),
            'integrationType' => $request->query('integration_type', 'shalom-control-search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'delivery_destination' => $this->normalizeDestination($request->input('delivery_method'), $request->input('delivery_destination')),
            'integration_type' => $request->input('integration_type', 'shalom-control-search'),
            'source' => $request->input('source', 'public-web'),
        ]);

        $data = $request->validate([
            'requester_name' => ['required', 'string', 'min:3', 'max:120'],
            'delivery_method' => ['required', 'string', Rule::in(['whatsapp', 'telegram', 'email'])],
            'delivery_destination' => ['required', 'string', 'max:180', $this->destinationRule($request->input('delivery_method'))],
            'installation_name' => ['required', 'string', 'max:150'],
            'integration_type' => ['required', 'string', 'max:80'],
            'reason' => ['nullable', 'string', 'max:1200'],
            'source' => ['nullable', 'string', 'max:80'],
            'extension_version' => ['nullable', 'string', 'max:40'],
            'installation_uuid' => ['nullable', 'string', 'max:120'],
            'terms' => ['accepted'],
            'website' => ['nullable', 'size:0'],
        ], [
            'delivery_destination.*' => $this->destinationMessage($request->input('delivery_method')),
            'terms.accepted' => 'Debes aceptar la declaración de uso para enviar la solicitud.',
            'website.size' => 'No fue posible enviar la solicitud. Inténtalo nuevamente.',
        ]);

        $destination = (string) $data['delivery_destination'];
        $emailBlindIndex = $data['delivery_method'] === 'email' ? $this->vault->generateBlindIndex($destination) : null;

        if ($emailBlindIndex) {
            $duplicate = ApiTokenRequest::query()
                ->where('status', ApiTokenRequestStatus::Pending)
                ->where('requester_email_blind_index', $emailBlindIndex)
                ->where('application_name', trim((string) $data['installation_name']))
                ->exists();

            if ($duplicate) {
                return back()->withInput($request->except('delivery_destination'))->withErrors([
                    'delivery_destination' => 'Ya existe una solicitud pendiente con este correo para esta instalación.',
                ]);
            }
        }

        $requestUuid = (string) Str::uuid();
        $trackingCode = 'CR-'.strtoupper(Str::random(10));

        $tokenRequest = ApiTokenRequest::query()->create([
            'request_uuid' => $requestUuid,
            'tracking_code' => $trackingCode,
            'request_type' => ApiTokenRequestType::Issuance,

            'requester_name_encrypted' => $this->vault->encrypt(trim((string) $data['requester_name'])),
            'requester_email_blind_index' => $emailBlindIndex,
            'purpose_encrypted' => isset($data['reason']) ? $this->vault->encrypt($data['reason']) : null,

            'application_name' => trim((string) $data['installation_name']),

            'requested_token_name' => trim((string) $data['installation_name']),
            'requested_token_type' => 'agencies',
            'requested_abilities' => ['agencies:read'],
            'token_expires_in_days' => 30,

            'status' => ApiTokenRequestStatus::Pending,
            'requested_ip' => hash('sha256', (string) $request->ip()),
            'request_source' => trim((string) ($data['source'] ?? 'public-web')),
            'requested_at' => now(),

            'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
            'delivery_channel' => $data['delivery_method'],
            'delivery_email' => $data['delivery_method'] === 'email' ? $this->vault->encrypt($destination) : null,
            'delivery_telegram_username' => $data['delivery_method'] === 'telegram' ? $this->vault->encrypt($destination) : null,
            'delivery_whatsapp_number' => $data['delivery_method'] === 'whatsapp' ? $this->vault->encrypt($destination) : null,

            'metadata' => [
                'integration_type' => $data['integration_type'],
                'extension_version' => $data['extension_version'] ?? null,
                'installation_uuid_hash' => filled($data['installation_uuid'] ?? null) ? hash('sha256', (string) $data['installation_uuid']) : null,
                'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
            ],
        ]);

        ApiTokenRequestEvent::query()->create([
            'api_token_request_id' => $tokenRequest->id,
            'event' => 'public_request_created',
            'description' => 'Solicitud pública creada desde formulario web.',
            'metadata' => ['tracking_code' => $trackingCode, 'source' => $tokenRequest->request_source],
            'ip_address' => hash('sha256', (string) $request->ip()),
            'user_agent' => hash('sha256', (string) $request->userAgent()),
            'created_at' => now(),
        ]);

        event(new TokenRequestCreated($tokenRequest));

        return redirect()->route('public.token-requests.create')->with('success', 'Solicitud enviada correctamente.')->with('tracking_code', $trackingCode);
    }

    private function normalizeDestination(mixed $method, mixed $value): string
    {
        $trimmed = is_string($value) ? trim($value) : '';
        if ($trimmed === '') {
            return '';
        }

        return match ($method) {
            'whatsapp' => preg_replace('/[\s()\-]/', '', $trimmed) ?? '',
            'telegram' => '@'.ltrim(preg_replace('#^https?://t\.me/#i', '', $trimmed) ?? '', '@'),
            'email' => mb_strtolower($trimmed),
            default => $trimmed,
        };
    }

    private function destinationRule(mixed $method): string
    {
        return match ($method) {
            'telegram' => 'regex:/^@[A-Za-z0-9_]{5,32}$/',
            'email' => 'email:rfc',
            default => 'regex:/^\+\d{8,15}$/',
        };
    }

    private function destinationMessage(mixed $method): string
    {
        return match ($method) {
            'telegram' => 'El usuario de Telegram no es válido.',
            'email' => 'El correo electrónico no es válido.',
            default => 'El número de WhatsApp no es válido.',
        };
    }

    private function fingerprint(string $method, string $destination, string $installation, string $integration): string
    {
        return hash_hmac('sha256', implode('|', [$method, mb_strtolower($destination), mb_strtolower(trim($installation)), mb_strtolower(trim($integration))]), config('app.key'));
    }

    private function hashValue(string $value): string
    {
        return hash_hmac('sha256', $value, config('app.key'));
    }

    private function maskDestination(string $value): string
    {
        if (str_contains($value, '@') && ! str_starts_with($value, '@')) {
            return ApiTokenRequest::maskEmail($value);
        }

        if (str_starts_with($value, '@')) {
            return ApiTokenRequest::maskTelegram($value);
        }

        return ApiTokenRequest::maskPhone($value);
    }
}
