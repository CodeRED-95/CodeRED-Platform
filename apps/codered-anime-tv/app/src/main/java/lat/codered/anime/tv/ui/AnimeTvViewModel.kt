package lat.codered.anime.tv.ui

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import lat.codered.anime.tv.data.JkAnimeClient
import lat.codered.anime.tv.data.LocalCast
import lat.codered.anime.tv.data.LocalCastClient
import lat.codered.anime.tv.data.WatchHistoryStore
import lat.codered.anime.tv.domain.Anime
import lat.codered.anime.tv.domain.AnimeResult
import lat.codered.anime.tv.domain.Episode
import lat.codered.anime.tv.domain.ScheduleEntry
import lat.codered.anime.tv.domain.Stream
import lat.codered.anime.tv.domain.WatchProgress
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

data class AnimeTvState(
    val query: String = "",
    val loading: Boolean = false,
    val message: String? = null,
    val results: List<Anime> = emptyList(),
    val selectedAnime: Anime? = null,
    val episodes: List<Episode> = emptyList(),
    val playingEpisode: Episode? = null,
    val stream: Stream? = null,
    val playbackStartPositionMs: Long = 0L,
    val continueWatching: List<WatchProgress> = emptyList(),
    val history: List<WatchProgress> = emptyList(),
    val watchedEpisodes: List<WatchProgress> = emptyList(),
    val favorites: List<Anime> = emptyList(),
    val newlyAdded: List<Anime> = emptyList(),
    val mostViewed: List<WatchProgress> = emptyList(),
    val recommended: List<Anime> = emptyList(),
    val directory: List<Anime> = emptyList(),
    val schedule: List<Anime> = emptyList(),
    val weeklySchedule: List<ScheduleEntry> = emptyList(),
    val weeklyScheduleLoading: Boolean = false,
    val premieres: List<Anime> = emptyList(),
    val top: List<Anime> = emptyList(),
    val selectedAnimeIsFavorite: Boolean = false,
    val castReceivers: List<LocalCast.Receiver> = emptyList(),
    val connectedReceiver: LocalCast.Receiver? = null,
)

class AnimeTvViewModel(application: Application) : AndroidViewModel(application) {
    private val client = JkAnimeClient()
    private val historyStore = WatchHistoryStore(application.applicationContext)
    private val castClient = LocalCastClient(application.applicationContext)

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
                        directory = result.value.directory,
                        schedule = result.value.schedule,
                        premieres = result.value.premieres,
                        top = result.value.top,
                    )
                }
                is AnimeResult.Failure -> _state.update {
                    it.copy(loading = false, message = "No se pudo cargar la portada: ${result.message}")
                }
            }
        }
    }

    /**
     * Carga perezosa del horario: es una peticion extra a /horario y solo hace
     * falta cuando el usuario abre la seccion.
     */
    fun loadWeeklySchedule(force: Boolean = false) {
        val current = state.value
        if (current.weeklyScheduleLoading) return
        if (!force && current.weeklySchedule.isNotEmpty()) return

        viewModelScope.launch {
            _state.update { it.copy(weeklyScheduleLoading = true) }
            when (val result = client.getWeeklySchedule()) {
                is AnimeResult.Success -> _state.update {
                    it.copy(
                        weeklyScheduleLoading = false,
                        weeklySchedule = result.value,
                        message = if (result.value.isEmpty()) "El horario no devolvio emisiones." else it.message,
                    )
                }
                is AnimeResult.Failure -> _state.update {
                    it.copy(weeklyScheduleLoading = false, message = result.message)
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
                    selectedAnimeIsFavorite = historyStore.isFavorite(anime.id),
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
                        selectedAnimeIsFavorite = historyStore.isFavorite(detailedAnime.id),
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
                selectedAnimeIsFavorite = false,
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
                    selectedAnimeIsFavorite = historyStore.isFavorite(progress.anime.id),
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
                            selectedAnimeIsFavorite = historyStore.isFavorite(detailedAnime.id),
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

    /**
     * Abre la ficha del anime y reproduce un capitulo concreto. Lo usa el
     * banner de capitulos recientes, que conoce el numero pero no la lista.
     */
    fun playEpisodeNumber(anime: Anime, episodeNumber: Int) {
        viewModelScope.launch {
            _state.update {
                it.copy(
                    loading = true,
                    message = "Preparando ${anime.title} episodio $episodeNumber...",
                    selectedAnime = anime,
                    selectedAnimeIsFavorite = historyStore.isFavorite(anime.id),
                    episodes = emptyList(),
                    stream = null,
                    playingEpisode = null,
                    playbackStartPositionMs = 0L,
                )
            }

            val detailedAnime = when (val detail = client.getAnime(anime.id)) {
                is AnimeResult.Success -> detail.value ?: anime
                is AnimeResult.Failure -> anime
            }

            when (val result = client.getEpisodes(anime.id)) {
                is AnimeResult.Success -> {
                    val episode = result.value.firstOrNull { it.number == episodeNumber }
                        ?: result.value.maxByOrNull { it.number }
                    _state.update {
                        it.copy(
                            loading = false,
                            selectedAnime = detailedAnime,
                            selectedAnimeIsFavorite = historyStore.isFavorite(detailedAnime.id),
                            episodes = result.value,
                            message = if (episode == null) "No se encontro el capitulo $episodeNumber." else null,
                        )
                    }
                    episode?.let { playEpisode(it) }
                }
                is AnimeResult.Failure -> _state.update {
                    it.copy(loading = false, selectedAnime = detailedAnime, message = result.message)
                }
            }
        }
    }

    /** Empieza a buscar televisores CodeRED en la red local. */
    fun startCastDiscovery() {
        castClient.startDiscovery(
            onFound = { receiver ->
                _state.update { current ->
                    if (current.castReceivers.any { it.host == receiver.host && it.port == receiver.port }) {
                        current
                    } else {
                        current.copy(castReceivers = current.castReceivers + receiver)
                    }
                }
            },
            onLost = { name ->
                _state.update { current ->
                    current.copy(
                        castReceivers = current.castReceivers.filterNot { it.name == name },
                        connectedReceiver = current.connectedReceiver?.takeIf { it.name != name },
                    )
                }
            },
        )
    }

    fun stopCastDiscovery() {
        castClient.stopDiscovery()
    }

    fun toggleCastReceiver(receiver: LocalCast.Receiver?) {
        _state.update { current ->
            val next = if (current.connectedReceiver == receiver) null else receiver
            current.copy(
                connectedReceiver = next,
                message = next?.let { "Conectado a ${it.name}." },
            )
        }
    }

    /**
     * Manda el capitulo a la television conectada. Devuelve false si no habia
     * receptor, para que la pantalla reproduzca en local.
     */
    fun sendToTelevision(anime: Anime, episodeNumber: Int): Boolean {
        val receiver = state.value.connectedReceiver ?: return false
        _state.update { it.copy(message = "Enviando a ${receiver.name}...") }
        viewModelScope.launch {
            val ok = withContext(Dispatchers.IO) {
                castClient.send(
                    receiver,
                    LocalCast.PlayRequest(
                        animeId = anime.id,
                        slug = anime.slug,
                        title = anime.title,
                        posterUrl = anime.posterUrl,
                        episodeNumber = episodeNumber,
                    ),
                )
            }
            _state.update {
                it.copy(
                    message = if (ok) {
                        "Capitulo $episodeNumber enviado a ${receiver.name}."
                    } else {
                        "No se pudo contactar con ${receiver.name}."
                    },
                )
            }
        }
        return true
    }

    override fun onCleared() {
        castClient.stopDiscovery()
        super.onCleared()
    }

    fun toggleSelectedFavorite() {
        val anime = state.value.selectedAnime ?: return
        val isFavorite = historyStore.toggleFavorite(anime)
        _state.update {
            it.copy(
                selectedAnimeIsFavorite = isFavorite,
                favorites = historyStore.favorites(),
                message = if (isFavorite) "${anime.title} agregado a favoritos." else "${anime.title} quitado de favoritos.",
            )
        }
    }

    fun markEpisodeWatched(episode: Episode) {
        val anime = state.value.selectedAnime ?: return
        historyStore.markWatched(anime, episode)
        refreshLocalShelves()
        _state.update { it.copy(message = "Episodio ${episode.number} marcado como visto.") }
    }

    fun refreshLocalShelves() {
        _state.update {
            it.copy(
                continueWatching = historyStore.continueWatching(),
                history = historyStore.history(),
                watchedEpisodes = historyStore.watchedEpisodes(),
                favorites = historyStore.favorites(),
                mostViewed = historyStore.mostViewed(),
            )
        }
    }
}
