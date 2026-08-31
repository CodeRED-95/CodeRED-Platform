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

/** Una pagina del directorio, con lo necesario para paginar. */
data class DirectoryPage(
    val items: List<Anime> = emptyList(),
    val page: Int = 1,
    val lastPage: Int = 1,
    val total: Int = 0,
)

/**
 * Filtros del directorio de JkAnime. Cada campo viaja como parametro de la
 * consulta; null significa "sin filtro".
 */
data class DirectoryFilters(
    val genre: String? = null,
    val type: String? = null,
    val status: String? = null,
    val year: String? = null,
    val sort: String? = null,
) {
    val activeCount: Int
        get() = listOfNotNull(genre, type, status, year, sort).size
}

/** Opciones que ofrece la propia pagina del proveedor. */
object DirectoryCatalog {
    /** Pares valor-etiqueta; el valor null es "sin filtro". */
    val sorts: List<Pair<String?, String>> = listOf(
        null to "Por fecha",
        "nombre" to "Por nombre",
        "popularidad" to "Por popularidad",
    )

    val statuses: List<Pair<String?, String>> = listOf(
        null to "Todos",
        "emision" to "En emision",
        "finalizados" to "Finalizado",
        "estrenos" to "Por estrenar",
    )

    val types: List<Pair<String?, String>> = listOf(
        null to "Todos",
        "animes" to "Animes",
        "peliculas" to "Peliculas",
        "especiales" to "Especiales",
        "ovas" to "Ovas",
        "onas" to "Onas",
    )

    val genres: List<Pair<String?, String>> = listOf(
        null to "Todos",
        "accion" to "Accion",
        "aventura" to "Aventura",
        "artes-marciales" to "Artes marciales",
        "autos" to "Autos",
        "colegial" to "Colegial",
        "comedia" to "Comedia",
        "cosas-de-la-vida" to "Cosas de la vida",
        "demonios" to "Demonios",
        "deportes" to "Deportes",
        "drama" to "Drama",
        "ecchi" to "Ecchi",
        "fantasia" to "Fantasia",
        "harem" to "Harem",
        "historico" to "Historico",
        "isekai" to "Isekai",
        "josei" to "Josei",
        "juegos" to "Juegos",
        "latino" to "Latino",
        "magia" to "Magia",
        "mecha" to "Mecha",
        "militar" to "Militar",
        "misterio" to "Misterio",
        "musica" to "Musica",
        "nios" to "Ninos",
        "parodia" to "Parodia",
        "policial" to "Policial",
        "psicologico" to "Psicologico",
        "romance" to "Romance",
        "samurai" to "Samurai",
        "sci-fi" to "Sci-fi",
        "seinen" to "Seinen",
        "shoujo" to "Shoujo",
        "shounen" to "Shounen",
        "sobrenatural" to "Sobrenatural",
        "space" to "Espacial",
        "super-poderes" to "Superpoderes",
        "terror" to "Terror",
        "thriller" to "Thriller",
        "vampiros" to "Vampiros",
    )

    /** Anos recientes; el proveedor acepta cualquiera de su lista. */
    val years: List<Pair<String?, String>> = listOf(null to "Todos") +
        (2026 downTo 2012).map { it.toString() to it.toString() }
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
