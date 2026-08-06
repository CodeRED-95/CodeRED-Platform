<?php

namespace App\Modules\Ruc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Services\RucImportOrchestrator;
use App\Modules\Ruc\Services\RucProgressTracker;
use App\Modules\Ruc\Services\RucRollbackHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class RucImportControllerV3 extends Controller
{
    /**
     * POST /admin/ruc/imports
     * Crear nueva importación
     */
    public function store(
        Request $request,
        RucImportOrchestrator $orchestrator
    ): JsonResponse {
        $this->authorize('create', RucImport::class);

        $validated = $request->validate([
            'file' => 'required|file|mimes:txt',
            'merge_strategy' => 'in:insert,insert_update,replace',
            'skip_duplicates' => 'boolean',
            'skip_unknown_ubigeo' => 'boolean',
        ]);

        try {
            $import = $orchestrator->initiateFromUpload(
                $request->file('file'),
                auth()->user(),
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Importación iniciada',
                'data' => [
                    'id' => $import->id,
                    'uuid' => $import->uuid,
                    'status' => $import->status,
                ],
            ], 202);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /admin/ruc/imports
     * Listar importaciones
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RucImport::class);

        $query = RucImport::query()
            ->with('createdBy', 'events')
            ->latest();

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('uuid', 'like', $search)
                    ->orWhere('original_filename', 'like', $search);
            });
        }

        $imports = $query->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $imports,
        ]);
    }

    /**
     * GET /admin/ruc/imports/{id}
     * Obtener detalles de importación
     */
    public function show(RucImport $import, RucProgressTracker $tracker): JsonResponse
    {
        $this->authorize('view', $import);

        return response()->json([
            'success' => true,
            'data' => array_merge(
                $import->toArray(),
                [
                    'progress' => $tracker->getProgressJson($import),
                    'events_count' => $import->events()->count(),
                    'errors_count' => $import->errors()->count(),
                    'duplicates_count' => $import->duplicates()->count(),
                ]
            ),
        ]);
    }

    /**
     * POST /admin/ruc/imports/{id}/pause
     * Pausar importación
     */
    public function pause(RucImport $import): JsonResponse
    {
        $this->authorize('pause', $import);

        if (!$import->canCancel()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede pausar esta importación',
            ], 422);
        }

        $import->update(['status' => 'paused', 'paused_at' => now()]);
        $import->recordEvent('import.paused', [], auth()->user());

        return response()->json([
            'success' => true,
            'message' => 'Importación pausada',
            'data' => $import->fresh(),
        ]);
    }

    /**
     * POST /admin/ruc/imports/{id}/resume
     * Reanudar importación pausada
     */
    public function resume(RucImport $import): JsonResponse
    {
        $this->authorize('resume', $import);

        if (!$import->canResume()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta importación no está pausada',
            ], 422);
        }

        // Despachar nuevo job
        \App\Modules\Ruc\Jobs\ProcessRucImportJobV3::dispatch($import->id)
            ->onQueue(config('ruc.import.queue', 'ruc-imports'));

        $import->update(['status' => 'processing']);
        $import->recordEvent('import.resumed', [], auth()->user());

        return response()->json([
            'success' => true,
            'message' => 'Importación reanudada',
            'data' => $import->fresh(),
        ]);
    }

    /**
     * POST /admin/ruc/imports/{id}/cancel
     * Cancelar importación
     */
    public function cancel(RucImport $import, Request $request): JsonResponse
    {
        $this->authorize('cancel', $import);

        if (!$import->canCancel()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cancelar esta importación',
            ], 422);
        }

        $import->requestCancellation(
            auth()->user(),
            $request->input('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Cancelación solicitada',
            'data' => $import->fresh(),
        ]);
    }

    /**
     * POST /admin/ruc/imports/{id}/rollback
     * Revertir importación
     */
    public function rollback(
        RucImport $import,
        Request $request,
        RucRollbackHandler $rollbackHandler
    ): JsonResponse {
        $this->authorize('rollback', $import);

        if (!$import->canRollback()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta importación no puede ser revertida',
            ], 422);
        }

        $result = $rollbackHandler->rollback(
            $import,
            auth()->user(),
            $request->input('reason')
        );

        if (!$result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->message,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result->message,
            'data' => [
                'records_deleted' => $result->recordsDeleted,
            ],
        ]);
    }

    /**
     * GET /admin/ruc/imports/{id}/progress
     * Obtener progreso en tiempo real
     */
    public function progress(RucImport $import, RucProgressTracker $tracker): JsonResponse
    {
        $this->authorize('view', $import);

        return response()->json([
            'success' => true,
            'data' => $tracker->getProgressJson($import),
        ]);
    }

    /**
     * GET /admin/ruc/imports/{id}/errors
     * Descargar CSV de errores
     */
    public function downloadErrors(RucImport $import)
    {
        $this->authorize('viewErrors', $import);

        $errors = $import->errors()
            ->orderBy('line_number')
            ->cursor();

        $filename = "ruc-import-{$import->uuid}-errors.csv";

        return response()->streamDownload(function () use ($errors) {
            $fp = fopen('php://output', 'w');

            // Header
            fputcsv($fp, ['Línea', 'Categoría', 'Código', 'Motivo']);

            // Data
            foreach ($errors as $error) {
                fputcsv($fp, [
                    $error->line_number,
                    $error->error_category ?? 'unknown',
                    $error->error_code ?? 'UNKNOWN',
                    $error->reason,
                ]);
            }

            fclose($fp);
        }, $filename);
    }
}
