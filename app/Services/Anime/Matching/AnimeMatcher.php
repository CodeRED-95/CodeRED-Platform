<?php

namespace App\Services\Anime\Matching;

use App\Services\Anime\Data\Anime;
use App\Services\Anime\Data\Metadata;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class AnimeMatcher
{
    public function bestMatch(Anime $source, array $candidates, float $threshold = 0.72): ?Anime
    {
        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            if (! $candidate instanceof Anime) {
                continue;
            }

            $score = $this->score($source, $candidate);
            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $bestScore >= $threshold ? $best : null;
    }

    public function score(Anime $source, Anime $candidate): float
    {
        if ($this->sharesExternalId($source, $candidate)) {
            return 1.0;
        }

        if ($source->slug !== '' && $source->slug === $candidate->slug) {
            return 0.96;
        }

        $sourceTitles = $this->normalizedTitles($source);
        $candidateTitles = $this->normalizedTitles($candidate);
        $bestTitleScore = 0.0;

        foreach ($sourceTitles as $sourceTitle) {
            foreach ($candidateTitles as $candidateTitle) {
                $bestTitleScore = max($bestTitleScore, $this->titleSimilarity($sourceTitle, $candidateTitle));
            }
        }

        $yearBonus = $source->year !== null && $candidate->year !== null && $source->year === $candidate->year ? 0.04 : 0.0;
        $episodeBonus = $source->episodes !== null && $candidate->episodes !== null && $source->episodes === $candidate->episodes ? 0.03 : 0.0;

        return min(1.0, $bestTitleScore + $yearBonus + $episodeBonus);
    }

    private function sharesExternalId(Anime $source, Anime $candidate): bool
    {
        $sourceIds = $source->metadata instanceof Metadata ? $source->metadata->externalIds : [];
        $candidateIds = $candidate->metadata instanceof Metadata ? $candidate->metadata->externalIds : [];

        foreach ($sourceIds as $provider => $sourceId) {
            if (($candidateIds[$provider] ?? null) !== null && (string) $candidateIds[$provider] === (string) $sourceId) {
                return true;
            }
        }

        return false;
    }

    private function normalizedTitles(Anime $anime): array
    {
        $titles = [
            $anime->title,
            $anime->slug,
            ...array_values($anime->titles),
            ...array_values($anime->metadata instanceof Metadata ? $anime->metadata->titles : []),
            ...Arr::wrap($anime->metadata instanceof Metadata ? $anime->metadata->synonyms : []),
        ];

        return collect($titles)
            ->filter(fn ($title): bool => is_string($title))
            ->map(fn (string $title): string => $this->normalizeTitle($title))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeTitle(string $title): string
    {
        $normalized = Str::lower(Str::ascii($title));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\b(the|a|an|tv|season|temporada)\b/', ' ', $normalized) ?? '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
    }

    private function titleSimilarity(string $left, string $right): float
    {
        if ($left === $right) {
            return 0.94;
        }

        if ($left === '' || $right === '') {
            return 0.0;
        }

        similar_text($left, $right, $percent);
        $levenshtein = levenshtein($left, $right);
        $length = max(strlen($left), strlen($right), 1);
        $levenshteinScore = max(0.0, 1 - ($levenshtein / $length));

        return max($percent / 100, $levenshteinScore);
    }
}
