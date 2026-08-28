<?php

namespace Tests\Feature;

use App\Services\Anime\Models\AnimeEpisode;
use App\Services\Anime\Models\AnimeExternalId;
use App\Services\Anime\Models\AnimeMetadata;
use App\Services\Anime\Models\AnimeRecord;
use App\Services\Anime\Models\AnimeSeason;
use App\Services\Anime\Models\EpisodeServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AnimeDatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_anime_database_tables_exist_with_required_columns(): void
    {
        self::assertTrue(Schema::hasTable('anime'));
        self::assertTrue(Schema::hasColumns('anime', [
            'id',
            'title',
            'slug',
            'description',
            'poster_url',
            'banner_url',
            'year',
            'status',
        ]));

        self::assertTrue(Schema::hasTable('anime_external_ids'));
        self::assertTrue(Schema::hasColumns('anime_external_ids', [
            'anime_id',
            'provider',
            'external_id',
            'external_slug',
        ]));

        self::assertTrue(Schema::hasTable('seasons'));
        self::assertTrue(Schema::hasTable('episodes'));
        self::assertTrue(Schema::hasTable('episode_servers'));
        self::assertTrue(Schema::hasTable('anime_metadata'));
        self::assertTrue(Schema::hasTable('provider_cache'));
    }

    public function test_anime_models_persist_relationships(): void
    {
        $anime = AnimeRecord::query()->create([
            'title' => 'One Piece',
            'slug' => 'one-piece',
            'description' => 'Piratas y aventuras.',
            'poster_url' => 'https://img.test/one-piece.jpg',
            'banner_url' => 'https://img.test/one-piece-banner.jpg',
            'year' => 1999,
            'status' => 'releasing',
        ]);
        $externalId = AnimeExternalId::query()->create([
            'anime_id' => $anime->id,
            'provider' => 'anilist',
            'external_id' => '21',
            'external_slug' => 'one-piece',
        ]);
        $season = AnimeSeason::query()->create([
            'anime_id' => $anime->id,
            'number' => 1,
            'title' => 'Temporada 1',
            'year' => 1999,
        ]);
        $episode = AnimeEpisode::query()->create([
            'anime_id' => $anime->id,
            'season_id' => $season->id,
            'number' => 1,
            'title' => 'Yo soy Luffy',
            'language' => 'sub',
        ]);
        $server = EpisodeServer::query()->create([
            'episode_id' => $episode->id,
            'provider' => 'jkanime',
            'server_id' => 'desu',
            'name' => 'Desu',
            'type' => 'stream',
            'language' => 'sub',
            'priority' => 10,
        ]);
        AnimeMetadata::query()->create([
            'anime_id' => $anime->id,
            'provider' => 'anilist',
            'external_id' => '21',
            'titles' => ['romaji' => 'ONE PIECE'],
            'synonyms' => ['OP'],
            'genres' => ['Adventure'],
            'studios' => [['id' => 18, 'name' => 'Toei Animation']],
            'relations' => [],
            'characters' => [],
            'payload' => ['source' => 'fixture'],
            'synced_at' => now(),
        ]);

        $metadata = $anime->metadata()->first();

        self::assertInstanceOf(AnimeMetadata::class, $metadata);
        self::assertSame($externalId->getKey(), $anime->externalIds()->value('id'));
        self::assertSame($season->getKey(), $anime->seasons()->value('id'));
        self::assertSame($episode->getKey(), $season->episodes()->value('id'));
        self::assertSame($server->getKey(), $episode->servers()->value('id'));
        self::assertSame('ONE PIECE', $metadata->titles['romaji'] ?? null);
    }
}
