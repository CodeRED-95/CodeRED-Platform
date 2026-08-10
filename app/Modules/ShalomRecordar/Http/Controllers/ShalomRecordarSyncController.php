<?php

declare(strict_types=1);

namespace App\Modules\ShalomRecordar\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ShalomRecordar\Http\Requests\LoginShalomRecordarRequest;
use App\Modules\ShalomRecordar\Http\Requests\RegisterShalomRecordarInstallationRequest;
use App\Modules\ShalomRecordar\Http\Requests\StatusShalomRecordarRequest;
use App\Modules\ShalomRecordar\Http\Requests\SyncShalomRecordarRequest;
use Illuminate\Support\Facades\Auth;
use App\Modules\ShalomRecordar\Services\ShalomRecordarSyncService;
use Illuminate\Http\JsonResponse;

class ShalomRecordarSyncController extends Controller
{
    public function register(RegisterShalomRecordarInstallationRequest $request, ShalomRecordarSyncService $service): JsonResponse
    {
        $result = $service->registerInstallation($request->user(), $request->validated(), $request);
        $installation = $result['installation'];

        return response()->json([
            'success' => true,
            'data' => [
                'installation_uuid' => $installation->installation_uuid,
                'extension_version' => $installation->extension_version,
                'sync_token' => $result['token'],
                'last_synced_at' => $installation->last_synced_at?->toISOString(),
            ],
        ]);
    }

    public function login(LoginShalomRecordarRequest $request, ShalomRecordarSyncService $service): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return response()->json(['success' => false, 'message' => 'Credenciales inválidas.'], 401);
        }

        $user = $request->user();
        abort_unless($user !== null && $user->isActive(), 403);

        $payload = $request->validated();
        $result = $service->registerInstallation($user, $payload, $request);
        $installation = $result['installation'];

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'installation_uuid' => $installation->installation_uuid,
                'extension_version' => $installation->extension_version,
                'sync_token' => $result['token'],
                'abilities' => ['shalom-recordar:sync', 'shalom-recordar:read-own'],
            ],
        ]);
    }

    public function sync(SyncShalomRecordarRequest $request, ShalomRecordarSyncService $service): JsonResponse
    {
        $installation = $service->upsertInstallation($request->user(), $request->validated(), $request);
        $result = $service->syncRecords($request->user(), $installation, $request->validated('records'));

        return response()->json([
            'success' => true,
            'data' => [
                'installation_uuid' => $installation->installation_uuid,
                'created' => $result['created'],
                'updated' => $result['updated'],
                'cursor' => $result['cursor'],
                'last_synced_at' => $installation->last_synced_at?->toISOString(),
            ],
        ]);
    }

    public function status(StatusShalomRecordarRequest $request, ShalomRecordarSyncService $service): JsonResponse
    {
        $installation = $service->upsertInstallation($request->user(), $request->validated(), $request);

        return response()->json([
            'success' => true,
            'data' => [
                'installation_uuid' => $installation->installation_uuid,
                'last_synced_at' => $installation->last_synced_at?->toISOString(),
                'last_seen_at' => $installation->last_seen_at?->toISOString(),
                'cursor' => $installation->last_sync_cursor,
            ],
        ]);
    }
}
