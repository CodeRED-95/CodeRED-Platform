<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class ApiDocumentationSpecController
{
    public function __invoke(): Response
    {
        abort_unless((bool) config('api.docs_enabled'), 404);

        return response((string) file_get_contents(base_path('docs/openapi.yaml')), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="codered-openapi.yaml"',
        ]);
    }
}
