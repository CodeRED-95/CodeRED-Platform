<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Valida un destino de redireccion externo antes de usarlo.
 *
 * CodeRED Platform es el punto de login unico de todo el ecosistema
 * (Store, Mobile web, Desktop web, futuras apps). Cuando una de esas apps
 * redirige aqui a un usuario sin sesion, le pasa a donde debe volver como
 * parametro `?redirect=`. Sin validar ese valor, un enlace malicioso con
 * `?redirect=https://sitio-falso.com` podria usar el login legitimo de
 * Platform como trampolin de phishing (open redirect) despues de una
 * autenticacion real.
 *
 * Por eso solo se acepta un destino si su host es exactamente uno de los
 * dominios del ecosistema o un subdominio suyo, y el esquema es http/https.
 * Cualquier otra cosa (dominio ajeno, `javascript:`, dato malformado)
 * devuelve null y quien llama debe usar su destino por defecto.
 */
final class TrustedRedirect
{
    /** @var array<int, string> */
    private const ALLOWED_APEX_DOMAINS = ['codered.lat', 'codered.host'];

    public static function resolve(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'], $parts['scheme'])) {
            return null;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);

        foreach (self::ALLOWED_APEX_DOMAINS as $apex) {
            if ($host === $apex || str_ends_with($host, '.'.$apex)) {
                return $url;
            }
        }

        return null;
    }
}
