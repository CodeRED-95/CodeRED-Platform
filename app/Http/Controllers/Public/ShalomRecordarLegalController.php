<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ShalomRecordarLegalController extends Controller
{
    /**
     * Muestra la política de privacidad de la extensión "Registro de Actividad Shalom".
     *
     * Esta URL es la que se declara en el panel de Chrome Web Store, por lo que
     * debe responder 200 sin autenticación para cualquier visitante.
     */
    public function privacy(): View
    {
        return view('public.registro-actividad-shalom.privacy', [
            'legalName' => config('codered.legal_name'),
            'legalCountry' => config('codered.legal_country'),
            'supportEmail' => config('codered.support_email'),
            'privacyUpdatedAt' => config('codered.privacy_updated_at'),
        ]);
    }

    /**
     * Muestra la página de soporte de la extensión "Registro de Actividad Shalom".
     */
    public function support(): View
    {
        return view('public.registro-actividad-shalom.support', [
            'legalName' => config('codered.legal_name'),
            'supportEmail' => config('codered.support_email'),
        ]);
    }
}
