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
    val topRank: Int? = null,
    val topVotes: Int? = null,
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

/** Una emision del horario semanal de JkAnime (pagina /horario). */
data class ScheduleEntry(
    val anime: Anime,
    val day: ScheduleDay,
    val lastEpisode: Int? = null,
    val relativeTime: String? = null,
)

enum class ScheduleDay(val label: String) {
    Monday("Lunes"),
    Tuesday("Martes"),
    Wednesday("Miercoles"),
    Thursday("Jueves"),
    Friday("Viernes"),
    Saturday("Sabado"),
    Sunday("Domingo");

    companion object {
        /** Acepta el texto del proveedor con o sin acentos ("Miercoles"/"Miercoles"). */
        fun fromLabel(value: String): ScheduleDay? {
            val needle = value.trim().lowercase()
                .replace('\u00e1', 'a')
                .replace('\u00e9', 'e')
                .replace('\u00ed', 'i')
                .replace('\u00f3', 'o')
                .replace('\u00fa', 'u')
            return entries.firstOrNull { it.label.lowercase() == needle }
        }
    }
}

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
