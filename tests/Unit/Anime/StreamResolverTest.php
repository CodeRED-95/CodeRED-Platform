<?php

namespace Tests\Unit\Anime;

use App\Services\Anime\Data\Server;
use App\Services\Anime\Data\Stream;
use App\Services\Anime\Resolving\StreamResolver;
use RuntimeException;
use Tests\TestCase;

final class StreamResolverTest extends TestCase
{
    public function test_returns_first_available_stream_after_priority_fallback(): void
    {
        config(['anime.server_priority' => ['desu', 'magi']]);

        $attempts = [];
        $stream = app(StreamResolver::class)->firstAvailable([
            new Server(id: 'server-c', name: 'Server C'),
            new Server(id: 'desu', name: 'Desu'),
            new Server(id: 'magi', name: 'Magi'),
        ], function (Server $server) use (&$attempts): ?Stream {
            $attempts[] = $server->name;

            return $server->name === 'Magi'
                ? new Stream(url: 'https://jkanime.test/media/one-piece.m3u8', type: 'hls', format: 'm3u8')
                : null;
        });

        self::assertSame(['Desu', 'Magi'], $attempts);
        self::assertSame('https://jkanime.test/media/one-piece.m3u8', $stream?->url);
    }

    public function test_resolver_catches_failed_server_attempts_without_looping(): void
    {
        config(['anime.server_priority' => ['desu', 'magi']]);

        $attempts = 0;
        $stream = app(StreamResolver::class)->firstAvailable([
            new Server(id: 'desu', name: 'Desu'),
            new Server(id: 'magi', name: 'Magi'),
        ], function () use (&$attempts): ?Stream {
            $attempts++;
            throw new RuntimeException('Servidor temporalmente no disponible.');
        });

        self::assertNull($stream);
        self::assertSame(2, $attempts);
    }
}
