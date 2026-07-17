<?php

namespace App\Core\Shared\Support;

final class JsonResponse
{
    public static function ok(array $data, int $status = 200)
    {
        return response()->json($data, $status);
    }
}
