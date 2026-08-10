<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\ShalomRecordar\Http\Requests\LoginShalomRecordarRequest;
use App\Modules\ShalomRecordar\Services\ShalomRecordarSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ShalomRecordarAuthController extends Controller
{
    public function login(LoginShalomRecordarRequest $request, ShalomRecordarSyncService $service): JsonResponse
    {
        $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim((string) $request->input('email')))])
            ->first();

        if (! $user instanceof User || ! Hash::check((string) $request->input('password'), $user->password) || ! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $result = $service->registerInstallation($user, $request->validated(), $request);
        $installation = $result['installation'];

        return response()->json([
            'success' => true,
            'data' => [
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => $user->roles()->pluck('slug')->values()->all()],
                'installation_uuid' => $installation->installation_uuid,
                'extension_version' => $installation->extension_version,
                'sync_token' => $result['token'],
                'abilities' => ['shalom-recordar:sync', 'shalom-recordar:read-own'],
            ],
        ]);
    }
}
