package lat.codered.anime.tv.data

import android.content.Context
import lat.codered.anime.tv.domain.Anime
import lat.codered.anime.tv.domain.Episode
import lat.codered.anime.tv.domain.WatchProgress
import org.json.JSONArray
import org.json.JSONObject

class WatchHistoryStore(context: Context) {
    private val prefs = context.getSharedPreferences("codered_anime_history", Context.MODE_PRIVATE)

    /**
     * Una tarjeta por anime: la del capitulo reproducido mas recientemente.
     * Antes cada episodio ocupaba su propia tarjeta y la misma serie aparecia
     * repetida varias veces en "Continuar viendo".
     */
    fun continueWatching(): List<WatchProgress> = readAll()
        .sortedByDescending { it.updatedAt }
        .distinctBy { it.anime.id }
        .take(12)

    fun history(): List<WatchProgress> = readAll().sortedByDescending { it.updatedAt }.take(24)

    fun watchedEpisodes(): List<WatchProgress> = readAll()
        .filter { it.watched }
        .sortedByDescending { it.updatedAt }
        .take(24)

    fun favorites(): List<Anime> {
        val raw = prefs.getString(KEY_FAVORITES, "[]").orEmpty()
        return runCatching {
            val array = JSONArray(raw)
            buildList {
                for (index in 0 until array.length()) {
                    val item = array.optJSONObject(index) ?: continue
                    readAnime(item)?.let(::add)
                }
            }
        }.getOrDefault(emptyList())
    }

    fun isFavorite(animeId: String): Boolean = favorites().any { it.id == animeId }

    fun toggleFavorite(anime: Anime): Boolean {
        val current = favorites().associateBy { it.id }.toMutableMap()
        val isNowFavorite = if (current.containsKey(anime.id)) {
            current.remove(anime.id)
            false
        } else {
            current[anime.id] = anime
            true
        }

        prefs.edit().putString(
            KEY_FAVORITES,
            JSONArray().apply {
                current.values.sortedBy { it.title }.forEach { put(it.toJson()) }
            }.toString(),
        ).apply()

        return isNowFavorite
    }

    fun mostViewed(): List<WatchProgress> = readAll()
        .sortedWith(compareByDescending<WatchProgress> { it.playCount }.thenByDescending { it.updatedAt })
        .take(12)

    fun markPlayed(anime: Anime, episode: Episode) {
        upsert(anime, episode.number, episode.title, incrementPlayCount = true)
    }

    fun markWatched(anime: Anime, episode: Episode) {
        upsert(
            anime = anime,
            episodeNumber = episode.number,
            episodeTitle = episode.title,
            positionMs = 0L,
            durationMs = 0L,
            watched = true,
        )
    }

    fun updateProgress(
        anime: Anime,
        episodeNumber: Int,
        episodeTitle: String,
        positionMs: Long,
        durationMs: Long,
        incrementPlayCount: Boolean = false,
    ) {
        val watched = durationMs > 0L && positionMs >= (durationMs * WATCHED_THRESHOLD).toLong()
        upsert(
            anime = anime,
            episodeNumber = episodeNumber,
            episodeTitle = episodeTitle,
            positionMs = positionMs,
            durationMs = durationMs,
            incrementPlayCount = incrementPlayCount,
            watched = watched,
        )
    }

    private fun upsert(
        anime: Anime,
        episodeNumber: Int,
        episodeTitle: String,
        positionMs: Long = 0L,
        durationMs: Long = 0L,
        incrementPlayCount: Boolean = false,
        watched: Boolean = false,
    ) {
        val current = readAll().associateBy { "${it.anime.id}:${it.episodeNumber}" }.toMutableMap()
        val key = "${anime.id}:$episodeNumber"
        val previous = current[key]
        current[key] = WatchProgress(
            anime = anime,
            episodeNumber = episodeNumber,
            episodeTitle = episodeTitle,
            playCount = (previous?.playCount ?: 0) + if (incrementPlayCount) 1 else 0,
            positionMs = positionMs.coerceAtLeast(0L),
            durationMs = durationMs.coerceAtLeast(0L),
            watched = watched || previous?.watched == true,
            updatedAt = System.currentTimeMillis(),
        )

        val payload = JSONArray()
        current.values.sortedByDescending { it.updatedAt }.take(40).forEach { progress ->
            payload.put(
                JSONObject()
                    .putAnime(progress.anime)
                    .put("episode_number", progress.episodeNumber)
                    .put("episode_title", progress.episodeTitle)
                    .put("play_count", progress.playCount)
                    .put("position_ms", progress.positionMs)
                    .put("duration_ms", progress.durationMs)
                    .put("watched", progress.watched)
                    .put("updated_at", progress.updatedAt),
            )
        }

        prefs.edit().putString(KEY_HISTORY, payload.toString()).apply()
    }

    private fun readAll(): List<WatchProgress> {
        val raw = prefs.getString(KEY_HISTORY, "[]").orEmpty()
        return runCatching {
            val array = JSONArray(raw)
            buildList {
                for (index in 0 until array.length()) {
                    val item = array.optJSONObject(index) ?: continue
                    val anime = readAnime(item) ?: continue
                    add(
                        WatchProgress(
                            anime = anime,
                            episodeNumber = item.optInt("episode_number"),
                            episodeTitle = item.optString("episode_title", "Episodio ${item.optInt("episode_number")}"),
                            playCount = item.optInt("play_count", 1).coerceAtLeast(1),
                            positionMs = item.optLong("position_ms", 0L).coerceAtLeast(0L),
                            durationMs = item.optLong("duration_ms", 0L).coerceAtLeast(0L),
                            watched = item.optBoolean("watched", false),
                            updatedAt = item.optLong("updated_at", 0L),
                        ),
                    )
                }
            }
        }.getOrDefault(emptyList())
    }

    private fun readAnime(item: JSONObject): Anime? {
        val slug = item.optString("slug").takeIf { it.isNotBlank() } ?: return null
        val title = item.optString("title").takeIf { it.isNotBlank() } ?: return null
        return Anime(
            id = item.optString("anime_id", "jkanime:$slug"),
            slug = slug,
            title = title,
            description = item.optString("description").takeIf { it.isNotBlank() },
            posterUrl = item.optString("poster_url").takeIf { it.isNotBlank() },
            episodeCount = item.optInt("episode_count").takeIf { it > 0 },
            status = item.optString("status").takeIf { it.isNotBlank() },
        )
    }

    private fun Anime.toJson(): JSONObject = JSONObject().putAnime(this)

    private fun JSONObject.putAnime(anime: Anime): JSONObject = put("anime_id", anime.id)
        .put("slug", anime.slug)
        .put("title", anime.title)
        .put("description", anime.description)
        .put("poster_url", anime.posterUrl)
        .put("episode_count", anime.episodeCount)
        .put("status", anime.status)

    private companion object {
        const val KEY_HISTORY = "history"
        const val KEY_FAVORITES = "favorites"
        const val WATCHED_THRESHOLD = 0.9
    }
}
