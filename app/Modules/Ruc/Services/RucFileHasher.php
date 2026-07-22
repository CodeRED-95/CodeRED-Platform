<?php

namespace App\Modules\Ruc\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class RucFileHasher
{
    public function sha256(string $diskName, string $path): string
    {
        $stream = Storage::disk($diskName)->readStream($path);
        if (! is_resource($stream)) {
            throw ValidationException::withMessages(['incomingFiles' => 'No se pudo leer el archivo para calcular su huella.']);
        }
        $context = hash_init('sha256');
        try {
            hash_update_stream($context, $stream);
        } finally {
            fclose($stream);
        }

        return hash_final($context);
    }
}
