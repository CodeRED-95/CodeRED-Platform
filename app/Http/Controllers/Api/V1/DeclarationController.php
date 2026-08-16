<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreDeclarationRequest;
use App\Http\Resources\Api\V1\DeclarationResource;
use App\Models\Declaration;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use App\Notifications\DeclarationGenerated;
use App\Services\Declarations\DeclarationPdfBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * API oficial de Declaración Jurada Shalom.
 *
 * El documento se genera aquí, en el servidor, a partir de datos persistidos: la
 * app móvil sólo recoge el formulario y consume el resultado. Sustituye a la
 * generación con jsPDF que vivía en el paquete React, que pasa a ser un cliente más.
 */
class DeclarationController
{
    /** Permiso que habilita el uso del módulo; el mismo que usa la app React. */
    private const PERMISSION = 'declaracion-jurada.view';

    /** Permiso que permite ver declaraciones de otras personas. */
    private const PERMISSION_MANAGE = 'declaracion-jurada.manage';

    private const DISK = 'local';

    /** Historial del usuario. Nunca devuelve el PDF, sólo su disponibilidad. */
    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizeUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $query = Declaration::query()->with('items')->latest('id');

        // Sin permiso administrativo, cada quien ve únicamente lo suyo.
        if (! $user->hasPermission(self::PERMISSION_MANAGE)) {
            $query->where('user_id', $user->getKey());
        }

        $perPage = min(max((int) $request->integer('per_page', 20), 1), (int) config('api.max_per_page'));

        $response = DeclarationResource::collection($query->paginate($perPage)->withQueryString())->response();
        $payload = $response->getData(true);
        $payload['success'] = true;
        $response->setData($payload);

        return $response;
    }

    /**
     * Ubicación completa de la agencia, tal como debe leerse en el documento.
     *
     * Se arma con las columnas del catálogo —departamento, provincia, distrito
     * y nombre— y se omite en silencio lo que falte, para que una agencia sin
     * distrito no acabe imprimiendo separadores vacíos.
     */
    private static function sedeFor(Agency $agency): string
    {
        $partes = array_filter([
            $agency->department,
            $agency->province,
            $agency->district,
            $agency->name,
        ], static fn (?string $parte): bool => is_string($parte) && trim($parte) !== '');

        $sede = implode(' / ', array_map(static fn (string $parte): string => trim($parte), $partes));

        return mb_substr($sede, 0, 255);
    }

    public function store(StoreDeclarationRequest $request, DeclarationPdfBuilder $builder): JsonResponse
    {
        $user = $this->authorizeUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $data = $request->validated();

        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($data['agency_id']);

        $declaration = DB::transaction(function () use ($data, $user, $agency): Declaration {
            $declaration = Declaration::query()->create([
                'user_id' => $user->getKey(),
                'agency_id' => $agency->getKey(),
                'remitente_dni' => $data['remitente_dni'],
                'remitente_nombre' => $data['remitente_nombre'],
                'remitente_telefono' => $data['remitente_telefono'] ?? null,
                'destinatario_dni' => $data['destinatario_dni'] ?? null,
                'destinatario_nombre' => $data['destinatario_nombre'] ?? null,
                'destinatario_telefono' => $data['destinatario_telefono'] ?? null,
                // La sede la fija el servidor desde el catálogo, no el cliente:
                // queda congelada para que el documento no cambie si la agencia
                // se renombra o se traslada más adelante.
                //
                // Es la ubicación completa, no sólo el nombre. "AV EJERCITO" no
                // dice dónde recoger el paquete; "PIURA / PIURA / CASTILLA / AV
                // TACNA" sí. Se compone de las columnas estructuradas de la
                // agencia, nunca troceando texto.
                'sede_destino' => self::sedeFor($agency),
                'motivo_envio' => $data['motivo_envio'] ?? null,
            ]);

            foreach (array_values($data['items'] ?? []) as $position => $item) {
                $declaration->items()->create([
                    'cantidad' => $item['cantidad'] ?? null,
                    'descripcion' => $item['descripcion'],
                    'position' => $position,
                ]);
            }

            return $declaration;
        });

        $declaration->load('items');

        // La foto se guarda en el disco privado, junto al PDF, y no en el
        // cuerpo de la declaracion: es un dato sensible que solo sirve para
        // componer el documento. Se conserva porque el endpoint de descarga
        // regenera el PDF cuando falta, y sin ella no se podria reconstruir la
        // version apaisada.
        if ($request->hasFile('foto_dni')) {
            $foto = $request->file('foto_dni');
            $ruta = $foto->storeAs(
                sprintf('declarations/%d', $declaration->getKey()),
                'dni.'.$foto->getClientOriginalExtension(),
                self::DISK
            );

            if (is_string($ruta) && $ruta !== '') {
                $declaration->forceFill(['foto_dni_path' => $ruta])->save();
            }
        }

        // Si el PDF no se puede escribir, la declaración sigue siendo válida: queda
        // sin archivo y el endpoint de descarga lo regenera cuando se pida.
        try {
            $path = sprintf('declarations/%d/%s', $declaration->getKey(), $declaration->pdfFileName());
            Storage::disk(self::DISK)->put($path, $builder->build($declaration));

            $declaration->forceFill([
                'pdf_path' => $path,
                'pdf_generated_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            Log::warning('declaration_pdf_generation_failed', [
                'declaration_id' => $declaration->getKey(),
                'reason' => $exception->getMessage(),
            ]);
        }

        // El aviso va en cola: el documento ya está emitido y el cliente no
        // debe esperar por él. Si la cola estuviera caída, la declaración sigue
        // siendo válida y visible en el historial.
        $user->notify(new DeclarationGenerated($declaration));

        // Sin datos personales en el log: sólo quién y qué se creó.
        Log::info('declaration_created', [
            'declaration_id' => $declaration->getKey(),
            'user_id' => $user->getKey(),
            'agency_id' => $agency->getKey(),
        ]);

        return (new DeclarationResource($declaration))
            ->additional(['message' => 'Declaración generada correctamente.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $declaration = $this->findAuthorized($request, $id);

        if ($declaration instanceof JsonResponse) {
            return $declaration;
        }

        return (new DeclarationResource($declaration->load('items')))->response();
    }

    /** Entrega el PDF oficial. El archivo nunca se sirve desde una URL pública. */
    public function pdf(Request $request, int $id, DeclarationPdfBuilder $builder)
    {
        $declaration = $this->findAuthorized($request, $id);

        if ($declaration instanceof JsonResponse) {
            return $declaration;
        }

        $disk = Storage::disk(self::DISK);

        // Si el archivo se perdió, se regenera: la fila es la fuente de verdad.
        if (! $declaration->hasPdf() || ! $disk->exists((string) $declaration->pdf_path)) {
            $path = sprintf('declarations/%d/%s', $declaration->getKey(), $declaration->pdfFileName());
            $disk->put($path, $builder->build($declaration->load('items')));
            $declaration->forceFill(['pdf_path' => $path, 'pdf_generated_at' => now()])->save();
        }

        return response()->streamDownload(
            fn () => print $disk->get((string) $declaration->pdf_path),
            $declaration->pdfFileName(),
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Comprueba el permiso RBAC además de la ability del token, que ya validó el
     * middleware. Son dos ejes distintos y ambos deben cumplirse.
     *
     * Una declaración siempre pertenece a una persona, nunca a un servicio: por
     * eso, cuando quien autentica es un ApiClient que delega identidad (el
     * bridge Node de packages/shalom-declaracion-jurada, vía X-CodeRED-User-Id),
     * el actor efectivo es el usuario delegado que ResolveDelegatedUser ya
     * validó —existe, está activo y tiene el permiso de delegación—. Sin esa
     * cabecera, un token de servicio no tiene ningún historial propio que
     * consultar y la petición se rechaza.
     */
    private function authorizeUser(Request $request): User|JsonResponse
    {
        $user = $request->attributes->get('delegated_user') ?? $request->user();

        if (! $user instanceof User) {
            return $this->deny('No autenticado.', Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->hasPermission(self::PERMISSION)) {
            return $this->deny('No tienes permiso para usar Declaración Jurada.', Response::HTTP_FORBIDDEN);
        }

        return $user;
    }

    private function deny(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    /** Evita IDOR: sin permiso administrativo sólo se accede a lo propio. */
    private function findAuthorized(Request $request, int $id): Declaration|JsonResponse
    {
        $user = $this->authorizeUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $declaration = Declaration::query()->find($id);

        if ($declaration === null) {
            return $this->deny('La declaración no existe.', Response::HTTP_NOT_FOUND);
        }

        if ($declaration->user_id !== $user->getKey() && ! $user->hasPermission(self::PERMISSION_MANAGE)) {
            return $this->deny('Esta declaración pertenece a otro usuario.', Response::HTTP_FORBIDDEN);
        }

        return $declaration;
    }
}
