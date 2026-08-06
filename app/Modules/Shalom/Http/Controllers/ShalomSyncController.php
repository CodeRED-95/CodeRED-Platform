<?php

declare(strict_types=1);

namespace App\Modules\Shalom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shalom\Actions\RecibeShalomSyncAction;
use App\Modules\Shalom\Http\Requests\StoreShalomSyncRequest;
use Illuminate\Http\JsonResponse;

class ShalomSyncController extends Controller
{
    /**
     * Recibe sincronización de registros de entregas desde Shalom Recordar
     */
    public function sync(StoreShalomSyncRequest $request): JsonResponse
    {
        $apiKey = $request->attributes->get('shalom_api_key');

        $batchId = (new RecibeShalomSyncAction())->execute(
            $request->validated('records'),
            $request->validated('username'),
            $apiKey,
            $request
        );

        return response()->json([
            'success' => true,
            'batch_id' => $batchId,
            'record_count' => count($request->validated('records')),
        ], 200);
    }
}
