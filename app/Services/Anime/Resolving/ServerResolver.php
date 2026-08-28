<?php

namespace App\Services\Anime\Resolving;

use App\Services\Anime\Data\Server;
use Illuminate\Support\Str;

final class ServerResolver
{
    /**
     * @param  list<Server>  $servers
     * @return list<Server>
     */
    public function ordered(array $servers, ?string $preferred = null): array
    {
        $preferred = $this->normalize($preferred);
        $priority = $this->priorityMap();

        $indexed = array_map(
            static fn (Server $server, int $index): array => ['server' => $server, 'index' => $index],
            $servers,
            array_keys($servers),
        );

        usort($indexed, function (array $left, array $right) use ($preferred, $priority): int {
            $leftServer = $left['server'];
            $rightServer = $right['server'];

            $leftPreferred = $preferred !== null && $this->matches($leftServer, $preferred);
            $rightPreferred = $preferred !== null && $this->matches($rightServer, $preferred);

            if ($leftPreferred !== $rightPreferred) {
                return $leftPreferred ? -1 : 1;
            }

            $leftRank = $priority[$this->normalize($leftServer->name)] ?? PHP_INT_MAX;
            $rightRank = $priority[$this->normalize($rightServer->name)] ?? PHP_INT_MAX;

            return $leftRank <=> $rightRank ?: $left['index'] <=> $right['index'];
        });

        return array_values(array_map(static fn (array $item): Server => $item['server'], $indexed));
    }

    private function matches(Server $server, string $needle): bool
    {
        return $this->normalize($server->id) === $needle || $this->normalize($server->name) === $needle;
    }

    /** @return array<string, int> */
    private function priorityMap(): array
    {
        $priority = (array) config('anime.server_priority', []);

        return collect($priority)
            ->mapWithKeys(fn (string $server, int $index): array => [$this->normalize($server) => $index])
            ->all();
    }

    private function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::of($value)->lower()->slug()->toString();
    }
}
