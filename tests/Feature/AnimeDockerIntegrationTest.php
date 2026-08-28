<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class AnimeDockerIntegrationTest extends TestCase
{
    public function test_anime_reuses_existing_laravel_docker_services_without_duplicate_containers(): void
    {
        $compose = File::get(base_path('docker-compose.yml'));

        self::assertStringContainsString('postgres:', $compose);
        self::assertStringContainsString('redis:', $compose);
        self::assertStringContainsString('nginx:', $compose);
        self::assertStringContainsString('app:', $compose);
        self::assertStringContainsString('scheduler:', $compose);
        self::assertStringContainsString('--queue=anime,agency-imports,default', $compose);
        self::assertStringNotContainsString('codered-anime:', $compose);
        self::assertStringNotContainsString('codered-anime-worker:', $compose);
        self::assertStringNotContainsString('codered-anime-scheduler:', $compose);
    }

    public function test_anime_docker_configuration_documents_redis_postgres_nginx_and_queue_reuse(): void
    {
        $documentation = File::get(base_path('docs/DOCKER.md'));

        self::assertStringContainsString('CodeRED Anime', $documentation);
        self::assertStringContainsString('no tiene contenedor propio', $documentation);
        self::assertStringContainsString('`postgres`', $documentation);
        self::assertStringContainsString('`redis`', $documentation);
        self::assertStringContainsString('`nginx`', $documentation);
        self::assertStringContainsString('cola `anime`', $documentation);
    }

    public function test_env_example_keeps_anime_runtime_variables_for_compose_environment(): void
    {
        $env = File::get(base_path('.env.example'));

        foreach ([
            'ANIME_ENABLED=true',
            'ANIME_CACHE_STORE=redis',
            'ANIME_CACHE_MIRROR_DATABASE=true',
            'ANIME_SERVER_PRIORITY=desu,magi',
            'ANIME_REQUEST_TIMEOUT=15',
            'ANIME_CONNECT_TIMEOUT=10',
            'JKANIME_BASE_URL=https://jkanime.net',
            'ANILIST_BASE_URL=https://graphql.anilist.co',
        ] as $expected) {
            self::assertStringContainsString($expected, $env);
        }
    }
}
