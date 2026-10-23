<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Audit\AuditLogger;
use App\Enums\ClientApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\NativeAgencySnapshotRequest;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Services\NativeAgencyComparison;
use App\Services\Auth\ClientSessionManager;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class NativeAgencySyncController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $this->administrator($request);

        return response()->json(['success' => true, 'data' => [
            'user' => ['id' => $user->id, 'name' => $user->getAttribute('name'), 'email' => $user->getAttribute('email')],
            'minimum_entries' => config('agency_native_sync.minimum_entries'),
            'maximum_deactivation_ratio' => config('agency_native_sync.maximum_deactivation_ratio'),
        ]]);
    }

    public function preview(NativeAgencySnapshotRequest $request, NativeAgencyComparison $comparison): JsonResponse
    {
        $user = $this->administrator($request);
        $snapshot = $request->validated();
        if ($snapshot['total'] !== count($snapshot['entries'])
            || array_column($snapshot['entries'], 'index') !== range(0, $snapshot['total'] - 1)) {
            throw ValidationException::withMessages(['entries' => 'La lista nativa debe estar completa y conservar todos sus índices consecutivos.']);
        }
        $plan = $comparison->compare($snapshot, Agency::query()->orderBy('id')->get());
        $plan['preview_id'] = (string) Str::uuid();
        $expires = now()->addMinutes((int) config('agency_native_sync.preview_minutes'));
        $plan['expires_at'] = $expires->toISOString();
        Cache::put($this->key($plan['preview_id']), [
            'user_id' => $user->id,
            'session' => $request->attributes->get('client_session')->uuid,
            'plan' => $plan,
        ], $expires);

        return response()->json(['success' => true, 'data' => $plan]);
    }

    public function apply(Request $request, NativeAgencyComparison $comparison): JsonResponse
    {
        $user = $this->administrator($request);
        $input = $request->validate(['preview_id' => ['required', 'uuid']]);
        $key = $this->key($input['preview_id']);

        return Cache::lock($key.':lock', 60)->block(5, function () use ($key, $request, $user, $comparison): JsonResponse {
            $cached = Cache::get($key);
            $this->guard(is_array($cached), 409, 'La comparación caducó. Captura y compara nuevamente.');
            $this->guard($cached['user_id'] === $user->id
                && $cached['session'] === $request->attributes->get('client_session')->uuid, 403);
            if (isset($cached['receipt'])) {
                return response()->json(['success' => true, 'data' => $cached['receipt']]);
            }
            $plan = $cached['plan'];
            $this->guard($plan['can_apply'], 422, 'La comparación está bloqueada. Revisa las advertencias.');
            $receipt = DB::transaction(function () use ($plan, $comparison, $user): array {
                $agencies = Agency::query()->orderBy('id')->lockForUpdate()->get();
                $this->guard(hash_equals($plan['revision'], $comparison->revision($agencies)), 409, 'El catálogo cambió. Compara nuevamente.');
                foreach ($plan['changes'] as $change) {
                    $agency = $agencies->firstWhere('id', $change['id']);
                    Gate::authorize('manageStatus', $agency);
                    $agency->status = AgencyStatus::from($change['to']);
                    $agency->save();
                    app(AuditLogger::class)->log($agency, 'native_sync', ['status' => $change['from']], [
                        'status' => $change['to'], 'preview_id' => $plan['preview_id'],
                        'snapshot_hash' => $plan['snapshot_hash'], 'channel' => 'terrestrial',
                        'origin' => $plan['origin'], 'captured_at' => $plan['captured_at'],
                    ], ['status']);
                }

                return [
                    'preview_id' => $plan['preview_id'], 'applied_at' => now()->toISOString(),
                    'snapshot_hash' => $plan['snapshot_hash'], 'user_id' => $user->id,
                    'activate_count' => $plan['activate_count'], 'deactivate_count' => $plan['deactivate_count'],
                    'review_count' => count($plan['review']), 'origin' => $plan['origin'],
                ];
            });
            $cached['receipt'] = $receipt;
            Cache::put($key, $cached, now()->addMinutes(10));

            return response()->json(['success' => true, 'data' => $receipt]);
        });
    }

    public function result(Request $request): JsonResponse
    {
        $user = $this->administrator($request);
        $input = $request->validate(['preview_id' => ['required', 'uuid']]);
        $cached = Cache::get($this->key($input['preview_id']));
        $this->guard(is_array($cached), 409, 'La operación ya no está disponible en caché. Verifica sus estados y auditoría en Platform antes de continuar.');
        $this->guard($cached['user_id'] === $user->id
            && $cached['session'] === $request->attributes->get('client_session')->uuid, 403);

        return response()->json(['success' => true, 'data' => [
            'receipt' => $cached['receipt'] ?? null,
            'pending' => ! isset($cached['receipt']),
        ]]);
    }

    private function administrator(Request $request): User
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $session = $request->attributes->get('client_session');
        $this->guard($user instanceof User && $token instanceof PersonalAccessToken
            && $token->getAttribute('kind') === ClientSessionManager::KIND_SESSION && $session !== null
            && $session->application === ClientApplication::Platform
            && ($user->hasRole('admin') || $user->hasRole('super-admin'))
            && $user->hasPermission('agencies.view') && $user->hasPermission('agencies.manage_status'), 403,
            'Se requiere una sesión de administrador de Platform con permisos para consultar y cambiar estados.');

        return $user;
    }

    private function key(string $id): string
    {
        return 'agencies:native-sync:'.$id;
    }

    private function guard(bool $condition, int $status, string $message = 'No tienes autorización para esta operación.'): void
    {
        if (! $condition) {
            throw new HttpResponseException(response()->json(['success' => false, 'message' => $message], $status));
        }
    }
}
