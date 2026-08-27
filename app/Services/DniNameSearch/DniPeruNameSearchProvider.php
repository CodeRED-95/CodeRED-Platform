<?php

declare(strict_types=1);

namespace App\Services\DniNameSearch;

use App\Domain\DniNameSearch\Contracts\DniNameSearchProviderInterface;
use App\Domain\DniNameSearch\Data\DniNameMatch;
use App\Domain\DniNameSearch\Data\DniNameSearchResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Throwable;

/**
 * Scraper deliberately limited to the public DNIPERU search form.
 * It discovers the form and its input names from the page instead of hardcoding
 * WordPress/plugin-specific field names. It does not bypass CAPTCHA/WAF/auth.
 */
final class DniPeruNameSearchProvider implements DniNameSearchProviderInterface
{
    public function isEnabled(): bool
    {
        return (bool) config('dni.name_search.providers.dniperu.enabled', false);
    }

    public function search(string $nombres, string $apellidoPaterno, string $apellidoMaterno): DniNameSearchResult
    {
        if (! $this->isEnabled()) {
            return DniNameSearchResult::failed('provider_disabled', 503, 'El proveedor de DNI por nombres está deshabilitado.');
        }

        $url = (string) config('dni.name_search.providers.dniperu.url');
        $timeout = max((int) config('dni.name_search.providers.dniperu.timeout_seconds', 15), 1);
        $connectTimeout = max((int) config('dni.name_search.providers.dniperu.connect_timeout_seconds', 5), 1);
        $retries = max((int) config('dni.name_search.providers.dniperu.retries', 1), 0);

        try {
            $client = Http::accept('text/html,application/xhtml+xml')
                ->withUserAgent((string) config('dni.name_search.providers.dniperu.user_agent'))
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->retry($retries, 500, throw: false);

            $page = $client->get($url);
            if ($page->status() === 429) {
                return DniNameSearchResult::failed('rate_limited', 429, 'El proveedor limitó temporalmente las consultas.');
            }
            if (in_array($page->status(), [403, 401], true)) {
                return DniNameSearchResult::failed('provider_blocked', $page->status(), 'El proveedor no permite realizar la consulta en este momento.');
            }
            if (! $page->successful()) {
                return DniNameSearchResult::failed('provider_unavailable', $page->status());
            }

            $form = $this->discoverForm($page->body());
            if ($form === null) {
                return DniNameSearchResult::failed('provider_parse_error', 502, 'No se pudo identificar el formulario público de consulta.');
            }

            $response = $client->send(
                $form['method'],
                $form['action'],
                $form['method'] === 'GET' ? ['query' => $this->buildPayload($form, $nombres, $apellidoPaterno, $apellidoMaterno)] : ['form_params' => $this->buildPayload($form, $nombres, $apellidoPaterno, $apellidoMaterno)],
            );

            if ($response->status() === 429) {
                return DniNameSearchResult::failed('rate_limited', 429, 'El proveedor limitó temporalmente las consultas.');
            }
            if (in_array($response->status(), [401, 403], true)) {
                return DniNameSearchResult::failed('provider_blocked', $response->status(), 'El proveedor no permite realizar la consulta en este momento.');
            }
            if (! $response->successful()) {
                return DniNameSearchResult::failed('provider_unavailable', $response->status());
            }

            $matches = $this->parseMatches($response->body());

            return $matches === [] ? DniNameSearchResult::notFound($response->status()) : DniNameSearchResult::found($matches, $response->status());
        } catch (ConnectionException) {
            return DniNameSearchResult::failed('provider_timeout', 504, 'El proveedor no respondió a tiempo.');
        } catch (Throwable $e) {
            report($e);

            return DniNameSearchResult::failed('provider_unavailable', 503, 'No fue posible consultar el proveedor.');
        }
    }

    /** @return array{method:string,action:string,inputs:list<array{name:string,value:string,type:string}>,field_names:array{nombres:string,paterno:string,materno:string}}|null */
    private function discoverForm(string $html): ?array
    {
        [$dom, $xpath] = $this->document($html);
        if ($dom === null || $xpath === null) {
            return null;
        }

        foreach ($xpath->query('//form') ?: [] as $form) {
            if (! $form instanceof DOMElement) {
                continue;
            }
            $inputs = [];
            foreach ($xpath->query('.//input', $form) ?: [] as $input) {
                if (! $input instanceof DOMElement) continue;
                $type = strtolower(trim($input->getAttribute('type') ?: 'text'));
                $name = trim($input->getAttribute('name'));
                if ($name === '' || in_array($type, ['submit', 'button', 'reset', 'file'], true)) continue;
                $inputs[] = ['name' => $name, 'value' => $input->getAttribute('value'), 'type' => $type];
            }

            $fieldNames = $this->resolveFieldNames($form, $xpath, $inputs);
            if ($fieldNames === null) continue;

            $action = trim($form->getAttribute('action'));
            $method = strtoupper(trim($form->getAttribute('method') ?: 'GET'));
            if (! in_array($method, ['GET', 'POST'], true)) $method = 'GET';
            if ($action === '') $action = (string) config('dni.name_search.providers.dniperu.url');
            elseif (! str_starts_with($action, 'http')) $action = $this->resolveUrl((string) config('dni.name_search.providers.dniperu.url'), $action);

            return compact('method', 'action', 'inputs', 'fieldNames');
        }

        return null;
    }

    private function resolveFieldNames(DOMElement $form, DOMXPath $xpath, array $inputs): ?array
    {
        $all = [];
        foreach ($inputs as $input) {
            $name = $input['name'];
            $all[$name] = strtolower($name);
        }

        $find = function (array $keywords) use ($all): ?string {
            foreach ($all as $name => $haystack) {
                foreach ($keywords as $keyword) {
                    if (str_contains($haystack, $keyword)) return $name;
                }
            }
            return null;
        };

        $nombres = $find(['nombres', 'nombre']);
        $paterno = $find(['apellido_paterno', 'apellido-paterno', 'paterno']);
        $materno = $find(['apellido_materno', 'apellido-materno', 'materno']);
        if ($nombres && $paterno && $materno && count(array_unique([$nombres, $paterno, $materno])) === 3) {
            return ['nombres' => $nombres, 'paterno' => $paterno, 'materno' => $materno];
        }

        $textInputs = array_values(array_filter($inputs, fn (array $i): bool => in_array($i['type'], ['text', 'search'], true)));
        if (count($textInputs) < 3) return null;

        $labels = [];
        foreach ($xpath->query('.//label', $form) ?: [] as $label) {
            $for = trim($label->attributes?->getNamedItem('for')?->nodeValue ?? '');
            $labels[$for] = mb_strtolower(trim($label->textContent));
        }
        $mapped = [];
        foreach ($textInputs as $input) {
            $label = $labels[$input['name']] ?? '';
            $hay = mb_strtolower($input['name'].' '.$label);
            if (str_contains($hay, 'materno')) $mapped['materno'] = $input['name'];
            elseif (str_contains($hay, 'paterno')) $mapped['paterno'] = $input['name'];
            elseif (str_contains($hay, 'nombre')) $mapped['nombres'] = $input['name'];
        }
        if (count($mapped) === 3) return ['nombres' => $mapped['nombres'], 'paterno' => $mapped['paterno'], 'materno' => $mapped['materno']];

        return null;
    }

    private function buildPayload(array $form, string $nombres, string $paterno, string $materno): array
    {
        $payload = [];
        foreach ($form['inputs'] as $input) $payload[$input['name']] = $input['value'];
        $payload[$form['field_names']['nombres']] = $nombres;
        $payload[$form['field_names']['paterno']] = $paterno;
        $payload[$form['field_names']['materno']] = $materno;
        return $payload;
    }

    /** @return list<DniNameMatch> */
    private function parseMatches(string $html): array
    {
        [$dom, $xpath] = $this->document($html);
        if ($dom === null || $xpath === null) return [];

        $matches = [];
        $seen = [];
        $configured = (array) config('dni.name_search.providers.dniperu.result_selectors', []);
        $nodes = [];
        foreach ($configured as $selector) {
            if (str_starts_with((string) $selector, '//')) foreach ($xpath->query($selector) ?: [] as $node) $nodes[] = $node;
        }
        if ($nodes === []) $nodes = iterator_to_array($xpath->query('//table//tr') ?: []);
        foreach ($nodes as $node) {
            $text = preg_replace('/\\s+/u', ' ', trim($node instanceof DOMNode ? $node->textContent : ''));
            if (! is_string($text) || $text === '') continue;
            if (! preg_match('/(?<!\d)(\d{8})(?!\d)/', $text, $m)) continue;
            $dni = $m[1];
            if (isset($seen[$dni])) continue;

            $name = null;
            if ($node instanceof DOMElement) {
                $cells = [];
                foreach ($node->getElementsByTagName('td') as $cell) {
                    $cellText = preg_replace('/\\s+/u', ' ', trim($cell->textContent));
                    if (is_string($cellText) && $cellText !== '') $cells[] = $cellText;
                }
                if (count($cells) >= 4) {
                    $dniIndex = null;
                    foreach ($cells as $index => $cell) {
                        if (preg_match('/(?<!\d)'.preg_quote($dni, '/').' (?!\d)/', $cell) || trim($cell) === $dni) { $dniIndex = $index; break; }
                    }
                    if ($dniIndex !== null) {
                        $remaining = array_values(array_filter($cells, fn (string $cell, int $i): bool => $i !== $dniIndex, ARRAY_FILTER_USE_BOTH));
                        if (count($remaining) >= 3) $name = ['nombres' => $remaining[0], 'paterno' => $remaining[1], 'materno' => $remaining[2]];
                    }
                }
            }
            $name ??= $this->extractNames($text, $dni);
            if ($name === null) continue;
            $seen[$dni] = true;
            $matches[] = new DniNameMatch($dni, $name['nombres'], $name['paterno'], $name['materno']);
        }

        return $matches;
    }

    private function extractNames(string $text, string $dni): ?array
    {
        $withoutDni = trim(str_replace($dni, ' ', $text));
        $parts = preg_split('/\\s{2,}|\\s*\|\\s*|\\s+[-–—]\\s+/u', $withoutDni) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts)));
        if (count($parts) >= 3) {
            return ['nombres' => $parts[0], 'paterno' => $parts[1], 'materno' => $parts[2]];
        }
        return null;
    }

    private function resolveUrl(string $base, string $action): string
    {
        if (str_starts_with($action, '//')) {
            return (parse_url($base, PHP_URL_SCHEME) ?: 'https').':'.$action;
        }
        $parts = parse_url($base);
        $origin = (($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? ''));
        if (isset($parts['port'])) $origin .= ':'.$parts['port'];
        if (str_starts_with($action, '/')) return $origin.$action;
        $path = $parts['path'] ?? '/';
        return $origin.rtrim(str_replace('\\', '/', dirname($path)), '/').'/'.ltrim($action, '/');
    }

    /** @return array{0:?DOMDocument,1:?DOMXPath} */
    private function document(string $html): array
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        return $ok ? [$dom, new DOMXPath($dom)] : [null, null];
    }
}
