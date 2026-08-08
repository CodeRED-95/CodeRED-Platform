<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Http\Controllers;

use App\Modules\Ruc\Models\RucBackupUpload;
use App\Modules\Ruc\Services\RucBackupMultipartUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Recepción de backups RUC multipart (manifest.json + *.partNNNN de
 * packages/ruc-tools). A diferencia de RucBackupController (formularios
 * HTML tradicionales), estos endpoints SÍ se consumen vía JavaScript
 * (XMLHttpRequest) porque el objetivo explícito es subir cada parte en un
 * request HTTP separado (~90 MiB cada uno) para nunca chocar con el límite
 * de Cloudflare — eso es estructuralmente imposible de lograr con un
 * <form> tradicional. El CSRF se mantiene vía header X-CSRF-TOKEN (mismo
 * token de la sesión, leído del <meta name="csrf-token"> del layout).
 */
class RucBackupMultipartUploadController
{
    public function __construct(private readonly RucBackupMultipartUploadService $service)
    {
    }

    /**
     * Crea la sesión de subida a partir del manifest.json (ya parseado por
     * el frontend y enviado como JSON, no como archivo).
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('ruc.backup.create');

        $manifest = $request->input('manifest');
        if (! is_array($manifest)) {
            return response()->json(['message' => 'El manifest debe enviarse como JSON.'], 422);
        }

        try {
            $upload = $this->service->createSession($manifest, $request->user());

            return response()->json([
                'upload_uuid' => $upload->uuid,
                'total_parts' => $upload->total_parts,
                'already_uploaded_parts' => [],
            ], 201);
        } catch (\Throwable $e) {
            Log::warning('RUC multipart session rejected', ['error' => $e->getMessage(), 'user_id' => $request->user()?->id]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, RucBackupUpload $upload): JsonResponse
    {
        Gate::authorize('ruc.backup.create');

        if ((int) $upload->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return response()->json($this->service->status($upload));
    }

    public function uploadPart(Request $request, RucBackupUpload $upload, int $index): JsonResponse
    {
        Gate::authorize('ruc.backup.create');

        $request->validate([
            'part' => 'required|file',
        ]);

        try {
            $part = $this->service->receivePart($upload, $index, $request->file('part'), $request->user());

            return response()->json([
                'index' => $part->part_index,
                'status' => $part->status,
                'upload_status' => $upload->fresh()->status,
                'ruc_backup_id' => $upload->fresh()->ruc_backup_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('RUC multipart part rejected', [
                'upload_id' => $upload->id,
                'index' => $index,
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, RucBackupUpload $upload): JsonResponse
    {
        Gate::authorize('ruc.backup.create');

        try {
            $this->service->cancel($upload, $request->user());

            return response()->json(['status' => 'cancelled']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }
}
