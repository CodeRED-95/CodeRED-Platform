<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Anime\AnimeEpisodeRequest;
use App\Http\Requests\Api\V1\Anime\AnimeIndexRequest;
use App\Http\Requests\Api\V1\Anime\AnimeShowRequest;
use App\Http\Requests\Api\V1\Anime\AnimeStreamRequest;
use App\Http\Resources\Api\V1\Anime\AnimeResource;
use App\Http\Resources\Api\V1\Anime\EpisodeResource;
use App\Http\Resources\Api\V1\Anime\SeasonResource;
use App\Http\Resources\Api\V1\Anime\ServerResource;
use App\Http\Resources\Api\V1\Anime\StreamResource;
use App\Services\Anime\Catalog\AnimeCatalogService;
use App\Services\Anime\Data\Anime;
use App\Services\Anime\Data\Episode;
use App\Services\Anime\Data\Stream;
use Illuminate\Http\JsonResponse;

final class AnimeController
{
    public function search(AnimeIndexRequest $request, AnimeCatalogService $catalog): JsonResponse
    {
        $results = $catalog->search((string) $request->validated('q'));

        return response()->json([
            'data' => AnimeResource::collection($results)->resolve(),
            'meta' => $this->meta('search', count($results)),
        ]);
    }

    public function show(AnimeShowRequest $request, AnimeCatalogService $catalog, string $id): JsonResponse
    {
        $anime = $catalog->getAnime($id);
        if (! $anime instanceof Anime) {
            return $this->notFound('Anime no encontrado.');
        }

        return response()->json([
            'data' => (new AnimeResource($anime))->resolve(),
            'meta' => $this->meta('metadata'),
        ]);
    }

    public function seasons(AnimeShowRequest $request, AnimeCatalogService $catalog, string $id): JsonResponse
    {
        $seasons = $catalog->getSeasons($id);

        return response()->json([
            'data' => SeasonResource::collection($seasons)->resolve(),
            'meta' => $this->meta('seasons', count($seasons)),
        ]);
    }

    public function episodes(AnimeShowRequest $request, AnimeCatalogService $catalog, string $id): JsonResponse
    {
        $episodes = $catalog->getEpisodes($id, $request->integer('page') ?: null);

        return response()->json([
            'data' => EpisodeResource::collection($episodes)->resolve(),
            'meta' => $this->meta('episodes', count($episodes)),
        ]);
    }

    public function episode(AnimeEpisodeRequest $request, AnimeCatalogService $catalog, string $id, int $episode): JsonResponse
    {
        $episodeData = $catalog->getEpisode($id, $episode);
        if (! $episodeData instanceof Episode) {
            return $this->notFound('Episodio no encontrado.');
        }

        return response()->json([
            'data' => (new EpisodeResource($episodeData))->resolve(),
            'meta' => $this->meta('episode'),
        ]);
    }

    public function servers(AnimeEpisodeRequest $request, AnimeCatalogService $catalog, string $id, int $episode): JsonResponse
    {
        $servers = $catalog->getServers($id, $episode);

        return response()->json([
            'data' => ServerResource::collection($servers)->resolve(),
            'meta' => $this->meta('servers', count($servers)),
        ]);
    }

    public function stream(AnimeStreamRequest $request, AnimeCatalogService $catalog, string $id, int $episode): JsonResponse
    {
        $stream = $catalog->getStream($id, $episode, (string) $request->validated('server'));
        if (! $stream instanceof Stream) {
            return $this->notFound('Stream no disponible para el servidor solicitado.');
        }

        return response()->json([
            'data' => (new StreamResource($stream))->resolve(),
            'meta' => $this->meta('stream'),
        ]);
    }

    private function meta(string $operation, ?int $count = null): array
    {
        return array_filter([
            'provider' => 'codered',
            'operation' => $operation,
            'count' => $count,
        ], static fn ($value): bool => $value !== null);
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => null,
            'meta' => ['provider' => 'codered'],
        ], 404);
    }
}
