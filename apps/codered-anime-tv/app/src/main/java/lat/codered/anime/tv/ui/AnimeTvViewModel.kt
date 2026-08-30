package lat.codered.anime.tv.ui

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import lat.codered.anime.tv.data.JkAnimeClient
import lat.codered.anime.tv.data.WatchHistoryStore
import lat.codered.anime.tv.domain.Anime
import lat.codered.anime.tv.domain.AnimeResult
import lat.codered.anime.tv.domain.Episode
import lat.codered.anime.tv.domain.Stream
import lat.codered.anime.tv.domain.WatchProgress
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class AnimeTvState(
    val query: String = "one piece",
    val loading: Boolean = false,
    val message: String? = null,
    val results: List<Anime> = emptyList(),
    val selectedAnime: Anime? = null,
    val episodes: List<Episode> = emptyList(),
    val playingEpisode: Episode? = null,
    val stream: Stream? = null,
    val playbackStartPositionMs: Long = 0L,
    val continueWatching: List<WatchProgress> = emptyList(),
    val newlyAdded: List<Anime> = emptyList(),
    val mostViewed: List<WatchProgress> = emptyList(),
    val recommended: List<Anime> = emptyList(),
)

class AnimeTvViewModel(application: Application) : AndroidViewModel(application) {
    private val client = JkAnimeClient()
    private val historyStore = WatchHistoryStore(application.applicationContext)

    private val _state = MutableStateFlow(AnimeTvState())
    val state: StateFlow<AnimeTvState> = _state.asStateFlow()

    init {
        refreshLocalShelves()
        loadHome()
    }

    fun updateQuery(query: String) {
        _state.update { it.copy(query = query) }
    }

    fun search() {
        val query = state.value.query.trim()
        if (query.length < 2) {
            _state.update { it.copy(message = "Escribe al menos 2 caracteres.") }
            return
        }

        viewModelScope.launch {
            _state.update { it.copy(loading = true, message = "Buscando $query...", stream = null, playingEpisode = null) }
            when (val result = client.search(query)) {
                is AnimeResult.Success -> _state.update {
                    it.copy(
                        loading = false,
                        message = if (result.value.isEmpty()) "No se encontraron resultados reproducibles." else null,
                        results = result.value,
                        selectedAnime = null,
                        episodes = emptyList(),
                    )
                }
                is AnimeResult.Failure -> _state.update { it.copy(loading = false, message = result.message) }
            }
        }
    }

    fun loadHome() {
        viewModelScope.launch {
            _state.update { it.copy(loading = true, message = "Cargando portada...") }
            when (val result = client.getHomeShelves()) {
                is AnimeResult.Success -> _state.update {
                    it.copy(
                        loading = false,
                        message = null,
                        newlyAdded = result.value.newlyAdded,
                        recommended = result.value.recommended,
                    )
                }
                is AnimeResult.Failure -> _state.update {
                    it.copy(loading = false, message = "No se pudo cargar la portada: ${result.message}")
                }
            }
        }
    }

    fun selectAnime(anime: Anime) {
        viewModelScope.launch {
            _state.update {
                it.copy(
                    loading = true,
                    message = "Cargando episodios de ${anime.title}...",
                    selectedAnime = anime,
                    episodes = emptyList(),
                    stream = null,
                    playingEpisode = null,
                )
            }

            val detailedAnime = when (val detail = client.getAnime(anime.id)) {
                is AnimeResult.Success -> detail.value ?: anime
                is AnimeResult.Failure -> anime
            }

            when (val result = client.getEpisodes(anime.id)) {
                is AnimeResult.Success -> _state.update {
                    it.copy(
                        loading = false,
                        selectedAnime = detailedAnime,
                        episodes = result.value,
                        message = if (result.value.isEmpty()) "No se encontraron episodios." else null,
                    )
                }
                is AnimeResult.Failure -> _state.update {
                    it.copy(loading = false, selectedAnime = detailedAnime, message = result.message)
                }
            }
        }
    }

    fun playEpisode(episode: Episode, startPositionMs: Long = 0L) {
        val anime = state.value.selectedAnime ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(
                    loading = true,
                    message = "Resolviendo episodio ${episode.number}...",
                    playingEpisode = episode,
                    stream = null,
                    playbackStartPositionMs = startPositionMs.coerceAtLeast(0L),
                )
            }
            when (val result = client.getStream(anime.id, episode.number, preferredServer = "desu")) {
                is AnimeResult.Success -> {
                    _state.update {
                        it.copy(
                            loading = false,
                            stream = result.value,
                            message = if (result.value == null) "No se encontro una fuente compatible." else null,
                        )
                    }
                    refreshLocalShelves()
                }
                is AnimeResult.Failure -> _state.update { it.copy(loading = false, message = result.message) }
            }
        }
    }

    fun closePlayer() {
        _state.update { it.copy(stream = null, playingEpisode = null, playbackStartPositionMs = 0L, message = null) }
        refreshLocalShelves()
    }

    fun closeDetails() {
        _state.update {
            it.copy(
                selectedAnime = null,
                episodes = emptyList(),
                stream = null,
                playingEpisode = null,
                playbackStartPositionMs = 0L,
                message = null,
            )
        }
    }

    fun reportPlaybackError(message: String) {
        _state.update { it.copy(message = "No se pudo abrir el reproductor: $message") }
    }

    fun resume(progress: WatchProgress) {
        viewModelScope.launch {
            _state.update {
                it.copy(
                    loading = true,
                    message = "Preparando ${progress.anime.title} episodio ${progress.episodeNumber}...",
                    selectedAnime = progress.anime,
                    episodes = emptyList(),
                    stream = null,
                    playingEpisode = null,
                    playbackStartPositionMs = progress.positionMs,
                )
            }

            val detailedAnime = when (val detail = client.getAnime(progress.anime.id)) {
                is AnimeResult.Success -> detail.value ?: progress.anime
                is AnimeResult.Failure -> progress.anime
            }

            when (val result = client.getEpisodes(progress.anime.id)) {
                is AnimeResult.Success -> {
                    val episode = result.value.firstOrNull { it.number == progress.episodeNumber }
                    _state.update {
                        it.copy(
                            loading = false,
                            selectedAnime = detailedAnime,
                            episodes = result.value,
                            message = if (episode == null) "No se encontro el episodio guardado." else null,
                        )
                    }
                    episode?.let { playEpisode(it, progress.positionMs) }
                }
                is AnimeResult.Failure -> _state.update {
                    it.copy(loading = false, selectedAnime = detailedAnime, message = result.message)
                }
            }
        }
    }

    fun refreshLocalShelves() {
        _state.update {
            it.copy(
                continueWatching = historyStore.continueWatching(),
                mostViewed = historyStore.mostViewed(),
            )
        }
    }
}
