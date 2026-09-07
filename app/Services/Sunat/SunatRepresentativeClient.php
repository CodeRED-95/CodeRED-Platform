<?php

namespace App\Services\Sunat;

use App\Exceptions\SunatRepresentativeException;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use DOMDocument;
use DOMXPath;

class SunatRepresentativeClient
{
    /** Devuelve el HTML de la vista exclusiva de representantes legales. */
    public function fetch(string $ruc): string
    {
        $baseUrl = (string) config('sunat.representatives.url');
        $cookies = new CookieJar();
        $headers = [
            'User-Agent' => (string) config('sunat.representatives.user_agent'),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'es-PE,es;q=0.9,en;q=0.8',
        ];

        try {
            $http = Http::withHeaders($headers)
                ->timeout((int) config('sunat.representatives.timeout'))
                ->connectTimeout((int) config('sunat.representatives.connect_timeout'))
                ->withOptions([
                    'allow_redirects' => true,
                    'cookies' => $cookies,
                    'version' => 1.1,
                    'force_ip_resolve' => 'v4',
                ]);

            $landing = $http->get($baseUrl);
            $landing->throw();

            if ($this->looksLikeNotFound($landing->body())) {
                return '<p>No se encontraron representantes legales.</p>';
            }

            $result = $http->asForm()->post($this->resolveAction($baseUrl, 'jcrS00Alias'), [
                'accion' => 'consPorRuc',
                'razSoc' => '',
                'nroRuc' => $ruc,
                'nrodoc' => '',
                'token' => Str::random(52),
                'contexto' => 'ti-it',
                'modo' => '1',
                'search1' => $ruc,
            ]);
            $result->throw();

            if ($this->looksLikeNotFound($result->body())) {
                return '<p>No se encontraron representantes legales.</p>';
            }

            $representativeForm = $this->representativeForm($result->body());
            $representatives = $http->asForm()->post($this->resolveAction($baseUrl, $representativeForm['action']), [
                'accion' => 'getRepLeg',
                'contexto' => $representativeForm['contexto'] ?: 'ti-it',
                'modo' => $representativeForm['modo'] ?: '1',
                'desRuc' => $representativeForm['desRuc'],
                'nroRuc' => $ruc,
            ]);
            $representatives->throw();

            return $representatives->body();
        } catch (ConnectionException $e) {
            throw $e;
        } catch (RequestException $e) {
            throw $e;
        }
    }

    /** @return array{action: string, contexto: string, modo: string, desRuc: string} */
    private function representativeForm(string $html): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $form = $xpath->query('//form[@name="formRepLeg"]')->item(0);

        if ($form === null) {
            throw new SunatRepresentativeException('SUNAT no devolvió el formulario de representantes legales.');
        }

        $value = static function (string $name) use ($xpath, $form): string {
            return trim((string) $xpath->evaluate('string(.//input[@name="'.$name.'"]/@value)', $form));
        };

        return [
            'action' => trim((string) $form->attributes?->getNamedItem('action')?->nodeValue) ?: 'jcrS00Alias',
            'contexto' => $value('contexto'),
            'modo' => $value('modo'),
            'desRuc' => html_entity_decode($value('desRuc'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ];
    }

    private function resolveAction(string $baseUrl, string $action): string
    {
        if (str_starts_with($action, 'http://') || str_starts_with($action, 'https://')) {
            return $action;
        }

        if (str_starts_with($action, '/')) {
            $parts = parse_url($baseUrl);
            $origin = (($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? ''));

            return $origin.$action;
        }

        return rtrim((string) preg_replace('~/[^/]+$~', '', $baseUrl), '/').'/'.ltrim($action, '/');
    }

    private function looksLikeNotFound(string $html): bool
    {
        $text = mb_strtolower(strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return str_contains($text, 'no existe')
            || str_contains($text, 'no se encuentra registrado')
            || str_contains($text, 'no se encontraron representantes');
    }
}
