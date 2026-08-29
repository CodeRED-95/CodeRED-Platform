<?php

namespace App\Livewire\Admin\Anime;

use App\Services\Anime\Catalog\AnimeCatalogService;
use App\Services\Anime\Data\Anime;
use App\Services\Anime\Data\Episode;
use App\Services\Anime\Data\Server;
use App\Services\Anime\Data\Stream;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Throwable;

final class Index extends Component
{
    public string $query = '';

    /** @var list<array<string, mixed>> */
    public array $results = [];

    /** @var array<string, mixed>|null */
    public ?array $anime = null;

    /** @var list<array<string, mixed>> */
    public array $episodes = [];

    /** @var array<string, mixed>|null */
    public ?array $episode = null;

    /** @var list<array<string, mixed>> */
    public array $servers = [];

    /** @var array<string, mixed>|null */
    public ?array $stream = null;

    public ?string $selectedServer = null;

    public ?string $errorMessage = null;

    public bool $searched = false;

    public function mount(): void
    {
        abort_unless((bool) config('anime.enabled'), 404);
    }

    public function search(AnimeCatalogService $catalog): void
    {
        $this->validate([
            'query' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\pL\pN\s.\':!?&,+()\-]+$/u'],
        ], [
            'query.regex' => 'Busca por titulo, no por URL ni por parametros externos.',
        ]);

        if (! RateLimiter::attempt('admin-anime-search:'.auth()->id(), 20, fn (): true => true, 60)) {
            $this->setError('Se supero el limite temporal del buscador administrativo.');

            return;
        }

        $this->resetSelection();
        $this->searched = true;

        try {
            $this->results = array_map(
                static fn (Anime $anime): array => $anime->toArray(),
                $catalog->search(trim($this->query)),
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->setError('No se pudo completar la busqueda de anime.');
        }
    }

    public function selectAnime(string $id, AnimeCatalogService $catalog): void
    {
        if (! $this->safeAnimeId($id)) {
            $this->setError('El identificador de anime no es valido.');

            return;
        }

        $this->reset(['episode', 'servers', 'stream', 'selectedServer', 'errorMessage']);

        try {
            $anime = $catalog->getAnime($id);
            if (! $anime instanceof Anime) {
                $this->setError('No se encontro metadata para el anime seleccionado.');

                return;
            }

            $this->anime = $anime->toArray();
            $this->episodes = array_map(
                static fn (Episode $episode): array => $episode->toArray(),
                $catalog->getEpisodes($anime->id),
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->setError('No se pudieron cargar los episodios del anime.');
        }
    }

    public function selectEpisode(int $number, AnimeCatalogService $catalog): void
    {
        if ($this->anime === null || $number < 1 || $number > 10000) {
            $this->setError('El episodio seleccionado no es valido.');

            return;
        }

        $animeId = (string) $this->anime['id'];
        $this->reset(['episode', 'servers', 'stream', 'selectedServer', 'errorMessage']);

        try {
            $episode = $catalog->getEpisode($animeId, $number);
            if (! $episode instanceof Episode) {
                $this->setError('No se encontro el episodio seleccionado.');

                return;
            }

            $servers = $catalog->getServers($animeId, $number);
            $this->episode = $episode->toArray();
            $this->servers = array_map(static fn (Server $server): array => $server->toArray(), $servers);
            $this->selectedServer = $this->servers[0]['id'] ?? null;
        } catch (Throwable $exception) {
            report($exception);
            $this->setError('No se pudieron resolver los servidores del episodio.');
        }
    }

    public function selectServer(string $server): void
    {
        if (! $this->safeServerId($server)) {
            $this->setError('El servidor seleccionado no es valido.');

            return;
        }

        $this->selectedServer = $server;
        $this->stream = null;
        $this->errorMessage = null;
    }

    public function resolveStream(AnimeCatalogService $catalog): void
    {
        if ($this->anime === null || $this->episode === null || $this->selectedServer === null) {
            $this->setError('Selecciona un anime, episodio y servidor antes de resolver el stream.');

            return;
        }

        try {
            $stream = $catalog->getStream((string) $this->anime['id'], (int) $this->episode['number'], $this->selectedServer);
            if (! $stream instanceof Stream) {
                $this->setError('No se encontro una fuente reproducible para este servidor.');

                return;
            }

            $this->stream = $stream->toArray();
            $this->errorMessage = null;
        } catch (Throwable $exception) {
            report($exception);
            $this->setError('No se pudo resolver la fuente de reproduccion.');
        }
    }

    public function clear(): void
    {
        $this->reset(['query', 'results', 'anime', 'episodes', 'episode', 'servers', 'stream', 'selectedServer', 'errorMessage', 'searched']);
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.admin.anime.index')
            ->layout('layouts.app', ['pageTitle' => 'CodeRED Anime']);
    }

    private function resetSelection(): void
    {
        $this->reset(['results', 'anime', 'episodes', 'episode', 'servers', 'stream', 'selectedServer', 'errorMessage']);
    }

    private function setError(string $message): void
    {
        $this->errorMessage = $message;
        $this->stream = null;
    }

    private function safeAnimeId(string $id): bool
    {
        return preg_match('/^(jkanime:[a-z0-9][a-z0-9\-]{0,120}|anilist:[1-9][0-9]{0,9}|[1-9][0-9]{0,9})$/', $id) === 1;
    }

    private function safeServerId(string $server): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9:._\-]{0,180}$/i', $server) === 1;
    }
}
