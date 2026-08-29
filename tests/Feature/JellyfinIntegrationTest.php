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
        self::assertStringContainsString('&playable=1', $contents);
        self::assertStringNotContainsString('jkanime', strtolower($contents));
    }

    public function test_jellyfin_channel_uses_current_media_info_contract(): void
    {
        $channel = File::get(base_path('integrations/jellyfin/CodeRED.Plugin.Anime/Channels/CodeRedAnimeChannel.cs'));
        $registrator = File::get(base_path('integrations/jellyfin/CodeRED.Plugin.Anime/ServiceRegistrator.cs'));

        self::assertStringContainsString('InternalChannelFeatures GetChannelFeatures()', $channel);
        self::assertStringContainsString('ChannelParentalRating ParentalRating', $channel);
        self::assertStringContainsString('Task<IEnumerable<MediaSourceInfo>> GetChannelItemMediaInfo', $channel);
        self::assertStringNotContainsString('ChannelMediaInfo', $channel);
        self::assertStringContainsString('MediaBrowser.Controller.Plugins', $registrator);
        self::assertStringContainsString('RegisterServices(IServiceCollection serviceCollection, IServerApplicationHost applicationHost)', $registrator);
        self::assertStringContainsString('AddSingleton<IChannel, CodeRedAnimeChannel>', $registrator);
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
