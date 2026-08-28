<?php

namespace App\Services\Anime\Resolving;

use App\Services\Anime\Data\Server;
use App\Services\Anime\Data\Stream;
use Throwable;

final class StreamResolver
{
    public function __construct(private readonly ServerResolver $servers) {}

    /**
     * @param  list<Server>  $servers
     * @param  callable(Server): (?Stream)  $resolver
     */
    public function firstAvailable(array $servers, callable $resolver, ?string $preferred = null): ?Stream
    {
        foreach ($this->servers->ordered($servers, $preferred) as $server) {
            try {
                $stream = $resolver($server);
            } catch (Throwable) {
                $stream = null;
            }

            if ($stream instanceof Stream) {
                return $stream;
            }
        }

        return null;
    }
}
