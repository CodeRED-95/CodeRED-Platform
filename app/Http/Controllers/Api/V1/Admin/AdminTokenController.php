<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ApiTokenType;
use App\Http\Resources\Api\V1\Admin\AdminTokenResource;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\ApiTokens\ApiTokenGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tokens de API desde CodeRED Mobile.
 *
 * Reutiliza el mismo sistema que el panel web: Sanctum sobre
 * `personal_access_tokens` (modelo ApiToken) y ApiTokenGenerator para la
 * vigencia. No hay un segundo sistema de tokens.
 *
 * Las abilities NO son texto libre: las decide ApiTokenType, igual que en el
 * panel. Así ningún cliente puede pedir una combinación arbitraria.
 */
class AdminTokenController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizeAdmin($request, 'api-tokens.view-any');

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $query = ApiToken::query()->with('tokenable')->latest('id');

        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        // "activo" excluye revocados y vencidos; el resto se ve tal cual.
        if ($request->query('estado') === 'activo') {
            $query->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        }

        return $this->paginated(
            AdminTokenResource::collection($query->paginate($this->perPage($request))->withQueryString())
        );
    }

    /** Catálogo de tipos con las abilities que concede cada uno. */
    public function types(Request $request): JsonResponse
    {
        $user = $this->authorizeAdmin($request, 'api-tokens.view-any');

        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json([
            'success' => true,
            'data' => array_map(fn (ApiTokenType $type): array => [
                'valor' => $type->value,
                'nombre' => $type->label(),
                'descripcion' => $type->description(),
                'abilities' => $type->abilities(),
            ], ApiTokenType::cases()),
            'meta' => [
                'vigencia_minima_dias' => ApiTokenGenerator::MIN_EXPIRES_IN_DAYS,
                'vigencia_maxima_dias' => ApiTokenGenerator::MAX_EXPIRES_IN_DAYS,
                'vigencia_por_defecto_dias' => ApiTokenGenerator::DEFAULT_EXPIRES_IN_DAYS,
            ],
        ]);
    }

    /**
     * Emite un token. El valor plano viaja UNA sola vez, en esta respuesta:
     * la columna guarda un hash del que no se puede volver al original.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->authorizeAdmin($request, 'api-tokens.create-for-users');

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'string', Rule::in(ApiTokenType::values())],
            'vigencia_dias' => [
                'required', 'integer',
                'min:'.ApiTokenGenerator::MIN_EXPIRES_IN_DAYS,
                'max:'.ApiTokenGenerator::MAX_EXPIRES_IN_DAYS,
            ],
            'usuario_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);

        $owner = User::query()->active()->find($data['usuario_id']);

        if ($owner === null) {
            return $this->deny('El usuario indicado no existe o no está activo.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $type = ApiTokenType::from($data['tipo']);
        $created = app(ApiTokenGenerator::class)->create(
            $owner,
            trim($data['nombre']),
            $type->abilities(),
            (int) $data['vigencia_dias'],
        );

        /** @var ApiToken $token */
        $token = ApiToken::query()->findOrFail($created->accessToken->id);
        $token->forceFill([
            'description' => 'Token emitido desde CodeRED Mobile',
            'created_by' => $user->getKey(),
        ])->save();

        // Se registra quién y para quién, nunca el valor: un token en los logs
        // es un token comprometido.
        Log::info('admin_token_created', [
            'token_id' => $token->id,
            'created_by' => $user->getKey(),
            'owner_id' => $owner->getKey(),
            'token_type' => $type->value,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token creado correctamente.',
            'data' => [
                'token' => $created->plainTextToken,
                'aviso' => 'Guarda este token ahora. Por seguridad no podrás volver a verlo completo.',
                'detalle' => (new AdminTokenResource($token->fresh(['tokenable'])))->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->authorizeAdmin($request, 'api-tokens.revoke-any');

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $token = ApiToken::query()->find($id);

        if ($token === null) {
            return $this->deny('El token no existe.', Response::HTTP_NOT_FOUND);
        }

        if ($token->revoked_at !== null) {
            return $this->deny('El token ya estaba revocado.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Revocar es marcar, no borrar: la fila sigue existiendo para que la
        // auditoría de peticiones pasadas conserve a qué token apuntaba.
        $token->forceFill(['revoked_at' => now()])->save();

        Log::info('admin_token_revoked', [
            'token_id' => $token->id,
            'revoked_by' => $user->getKey(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token revocado. Las aplicaciones que lo usaban dejarán de tener acceso.',
        ]);
    }
}
