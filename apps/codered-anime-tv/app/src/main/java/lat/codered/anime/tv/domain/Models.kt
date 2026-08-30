package lat.codered.anime.tv.domain

data class Anime(
    val id: String,
    val slug: String,
    val title: String,
    val description: String? = null,
    val posterUrl: String? = null,
    val episodeCount: Int? = null,
    val status: String? = null,
    val scheduleEpisode: Int? = null,
    val scheduleLabel: String? = null,
    val scheduleCategory: String? = null,
)

data class WatchProgress(
    val anime: Anime,
    val episodeNumber: Int,
    val episodeTitle: String,
    val playCount: Int = 1,
    val positionMs: Long = 0L,
    val durationMs: Long = 0L,
    val watched: Boolean = false,
    val updatedAt: Long = System.currentTimeMillis(),
)

data class HomeShelves(
    val newlyAdded: List<Anime> = emptyList(),
    val recommended: List<Anime> = emptyList(),
    val directory: List<Anime> = emptyList(),
    val schedule: List<Anime> = emptyList(),
    val premieres: List<Anime> = emptyList(),
    val top: List<Anime> = emptyList(),
)

data class Episode(
    val id: String,
    val animeId: String,
    val number: Int,
    val title: String,
    val thumbnailUrl: String? = null,
    val servers: List<Server> = emptyList(),
)

data class Server(
    val id: String,
    val name: String,
    val type: String,
    val url: String,
)

data class Stream(
    val url: String,
    val type: String,
    val format: String,
    val headers: Map<String, String> = emptyMap(),
)

sealed interface AnimeResult<out T> {
    data class Success<T>(val value: T) : AnimeResult<T>
    data class Failure(val message: String, val cause: Throwable? = null) : AnimeResult<Nothing>
}
