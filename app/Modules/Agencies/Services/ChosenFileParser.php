<?php

namespace App\Modules\Agencies\Services;

class ChosenFileParser
{
    public function __invoke(string $fileContent): array
    {
        $entries = $this->extractEntries($fileContent);
        $result = [];

        foreach ($entries as $entry) {
            $text = $this->cleanText($entry);

            if ($text === '' || str_starts_with($text, 'Destino:')) {
                continue;
            }

            if (! preg_match('/^(\d+)\s*-\s*(.+)$/u', $text, $matches)) {
                continue;
            }

            $externalId = (int) $matches[1];
            $type = 'terrestre';

            if (preg_match('/-\s*AEREO\s*$/iu', $text)) {
                $type = 'aereo';
            }

            $result[$externalId] ??= [
                'external_id' => $externalId,
                'texto_chosen_terrestre' => null,
                'texto_chosen_aereo' => null,
            ];

            $key = $type === 'aereo' ? 'texto_chosen_aereo' : 'texto_chosen_terrestre';
            $result[$externalId][$key] ??= $text;
        }

        return array_values($result);
    }

    /** @return array<int, string> */
    private function extractEntries(string $fileContent): array
    {
        $decoded = html_entity_decode($fileContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $json = json_decode($decoded, true);

        if (is_array($json)) {
            return array_values(array_filter(array_map(
                fn ($item) => is_scalar($item) ? (string) $item : null,
                $json
            )));
        }

        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/isu', $decoded, $matches)) {
            return $matches[1];
        }

        return preg_split('/\R/u', $decoded) ?: [];
    }

    private function cleanText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    }
}
