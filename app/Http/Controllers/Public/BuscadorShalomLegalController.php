<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class BuscadorShalomLegalController extends Controller
{
    /**
     * Muestra la página de política de privacidad para la extensión Buscador Shalom.
     */
    public function privacy(): View
    {
        return view('public.buscador-shalom.privacy', [
            'legalName' => config('codered.legal_name'),
            'supportEmail' => config('codered.support_email'),
            'privacyUpdatedAt' => config('codered.privacy_updated_at'),
        ]);
    }

    /**
     * Muestra la página de soporte para la extensión Buscador Shalom.
     */
    public function support(): View
    {
        return view('public.buscador-shalom.support', [
            'supportEmail' => config('codered.support_email'),
        ]);
    }
}
