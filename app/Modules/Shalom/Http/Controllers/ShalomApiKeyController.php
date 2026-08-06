<?php

declare(strict_types=1);

namespace App\Modules\Shalom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Shalom\Models\ShalomApiKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShalomApiKeyController extends Controller
{
    /**
     * Listar API keys del usuario actual
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShalomApiKey::class);

        $keys = ShalomApiKey::where('user_id', $request->user()->id ?? null)
            ->select(['id', 'name', 'key_prefix', 'description', 'requests_count', 'last_used_at', 'revoked_at', 'created_at'])
            ->latest('created_at')
            ->paginate(15);

        return response()->json($keys);
    }

    /**
     * Crear nueva API key
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ShalomApiKey::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = ShalomApiKey::createNewKey(
            $validated['name'],
            $request->user(),
            $validated['description'] ?? null
        );

        \Illuminate\Support\Facades\Log::info('Shalom API key created', [
            'user_id' => $request->user()->id,
            'key_id' => $result['id'],
            'name' => $validated['name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key created. Store the plain_key in a secure location (you won\'t see it again)',
            'data' => $result,
        ], 201);
    }

    /**
     * Ver detalles de una API key
     */
    public function show(ShalomApiKey $key): JsonResponse
    {
        $this->authorize('view', $key);

        return response()->json([
            'id' => $key->id,
            'name' => $key->name,
            'key_prefix' => $key->key_prefix,
            'description' => $key->description,
            'requests_count' => $key->requests_count,
            'last_used_at' => $key->last_used_at,
            'revoked_at' => $key->revoked_at,
            'created_at' => $key->created_at,
            'is_active' => !$key->revoked_at,
        ]);
    }

    /**
     * Actualizar API key
     */
    public function update(Request $request, ShalomApiKey $key): JsonResponse
    {
        $this->authorize('update', $key);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $key->update($validated);

        \Illuminate\Support\Facades\Log::info('Shalom API key updated', [
            'key_id' => $key->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key updated',
            'data' => $key->only(['id', 'name', 'key_prefix', 'description']),
        ]);
    }

    /**
     * Revocar API key (la invalida sin eliminar)
     */
    public function revoke(Request $request, ShalomApiKey $key): JsonResponse
    {
        $this->authorize('delete', $key);

        $key->revoke();

        \Illuminate\Support\Facades\Log::info('Shalom API key revoked', [
            'key_id' => $key->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key revoked',
        ]);
    }

    /**
     * Eliminar API key
     */
    public function destroy(Request $request, ShalomApiKey $key): JsonResponse
    {
        $this->authorize('delete', $key);

        $keyId = $key->id;
        $key->delete();

        \Illuminate\Support\Facades\Log::info('Shalom API key deleted', [
            'key_id' => $keyId,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key deleted',
        ]);
    }
}
