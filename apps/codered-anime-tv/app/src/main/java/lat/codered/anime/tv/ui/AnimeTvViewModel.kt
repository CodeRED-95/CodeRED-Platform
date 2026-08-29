package lat.codered.anime.tv.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import lat.codered.anime.tv.data.JkAnimeClient
import lat.codered.anime.tv.domain.Anime
import lat.codered.anime.tv.domain.AnimeResult
import lat.codered.anime.tv.domain.Episode
import lat.codered.anime.tv.domain.Stream
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
)

class AnimeTvViewModel(
    private val client: JkAnimeClient = JkAnimeClient(),
) : ViewModel() {
    private val _state = MutableStateFlow(AnimeTvState())
    val state: StateFlow<AnimeTvState> = _state.asStateFlow()

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

    fun playEpisode(episode: Episode) {
        val anime = state.value.selectedAnime ?: return
        viewModelScope.launch {
            _state.update { it.copy(loading = true, message = "Resolviendo episodio ${episode.number}...", playingEpisode = episode, stream = null) }
            when (val result = client.getStream(anime.id, episode.number, preferredServer = "desu")) {
                is AnimeResult.Success -> _state.update {
                    it.copy(
                        loading = false,
                        stream = result.value,
                        message = if (result.value == null) "No se encontro una fuente compatible." else null,
                    )
                }
                is AnimeResult.Failure -> _state.update { it.copy(loading = false, message = result.message) }
            }
        }
    }
}
