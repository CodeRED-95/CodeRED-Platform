<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class JellyfinIntegrationTest extends TestCase
{
    public function test_jellyfin_integration_scaffolds_plugin_configuration_channel_and_client(): void
    {
        $root = base_path('integrations/jellyfin');

        self::assertFileExists($root.'/CodeRED.Plugin.Anime.sln');
        self::assertFileExists($root.'/CodeRED.Plugin.Anime/Plugin.cs');
        self::assertFileExists($root.'/CodeRED.Plugin.Anime/Configuration/PluginConfiguration.cs');
        self::assertFileExists($root.'/CodeRED.Plugin.Anime/Api/CodeRedAnimeClient.cs');
        self::assertFileExists($root.'/CodeRED.Plugin.Anime/Channels/CodeRedAnimeChannel.cs');
        self::assertFileExists($root.'/scripts/build.sh');
        self::assertFileExists($root.'/scripts/install.sh');
    }

    public function test_jellyfin_plugin_depends_only_on_codered_anime_api_contract(): void
    {
        $root = base_path('integrations/jellyfin');
        $contents = collect(File::allFiles($root))
            ->reject(fn ($file): bool => in_array($file->getExtension(), ['md'], true))
            ->map(fn ($file): string => File::get($file->getPathname()))
            ->implode("\n");

        self::assertStringContainsString('/api/v1/anime', File::get($root.'/README.md'));
        self::assertStringContainsString('anime:read', File::get($root.'/README.md'));
        self::assertStringContainsString('GetStreamAsync', $contents);
        self::assertStringContainsString('GetEpisodesAsync', $contents);
        self::assertStringNotContainsString('jkanime', strtolower($contents));
    }

    public function test_jellyfin_documentation_explains_installation_and_playback_flow(): void
    {
        $documentation = File::get(base_path('docs/anime/jellyfin.md'));

        self::assertStringContainsString('GET /api/v1/anime/{id}/episodes', $documentation);
        self::assertStringContainsString('GET /api/v1/anime/{id}/episodes/{episode}/stream?server={preferred}', $documentation);
        self::assertStringContainsString('JELLYFIN_PLUGIN_DIR', $documentation);
        self::assertStringContainsString('dotnet', $documentation);
    }
}
