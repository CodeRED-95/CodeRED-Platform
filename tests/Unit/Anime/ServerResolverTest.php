<?php

namespace Tests\Unit\Anime;

use App\Services\Anime\Data\Server;
use App\Services\Anime\Resolving\ServerResolver;
use Tests\TestCase;

final class ServerResolverTest extends TestCase
{
    public function test_orders_servers_by_configured_priority_and_preserves_unknown_order(): void
    {
        config(['anime.server_priority' => ['desu', 'magi']]);

        $servers = [
            new Server(id: 'server-c', name: 'Server C'),
            new Server(id: 'magi', name: 'Magi'),
            new Server(id: 'desu', name: 'Desu'),
            new Server(id: 'server-a', name: 'Server A'),
        ];

        $ordered = app(ServerResolver::class)->ordered($servers);

        self::assertSame(['Desu', 'Magi', 'Server C', 'Server A'], array_map(static fn (Server $server): string => $server->name, $ordered));
    }

    public function test_requested_server_takes_precedence_before_fallback_priority(): void
    {
        config(['anime.server_priority' => ['desu', 'magi']]);

        $servers = [
            new Server(id: 'desu', name: 'Desu'),
            new Server(id: 'magi', name: 'Magi'),
        ];

        $ordered = app(ServerResolver::class)->ordered($servers, 'magi');

        self::assertSame(['Magi', 'Desu'], array_map(static fn (Server $server): string => $server->name, $ordered));
    }
}
