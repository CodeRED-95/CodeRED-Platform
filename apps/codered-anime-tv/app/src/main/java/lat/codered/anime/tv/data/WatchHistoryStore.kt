package lat.codered.anime.tv.data

import android.content.Context
import lat.codered.anime.tv.domain.Anime
import lat.codered.anime.tv.domain.Episode
import lat.codered.anime.tv.domain.WatchProgress
import org.json.JSONArray
import org.json.JSONObject

class WatchHistoryStore(context: Context) {
    private val prefs = context.getSharedPreferences("codered_anime_history", Context.MODE_PRIVATE)

    fun continueWatching(): List<WatchProgress> = readAll().sortedByDescending { it.updatedAt }.take(12)

    fun mostViewed(): List<WatchProgress> = readAll()
        .sortedWith(compareByDescending<WatchProgress> { it.playCount }.thenByDescending { it.updatedAt })
        .take(12)

    fun markPlayed(anime: Anime, episode: Episode) {
        val current = readAll().associateBy { "${it.anime.id}:${it.episodeNumber}" }.toMutableMap()
        val key = "${anime.id}:${episode.number}"
        val previous = current[key]
        current[key] = WatchProgress(
            anime = anime,
            episodeNumber = episode.number,
            episodeTitle = episode.title,
            playCount = (previous?.playCount ?: 0) + 1,
            updatedAt = System.currentTimeMillis(),
        )

        val payload = JSONArray()
        current.values.sortedByDescending { it.updatedAt }.take(40).forEach { progress ->
            payload.put(
                JSONObject()
                    .put("anime_id", progress.anime.id)
                    .put("slug", progress.anime.slug)
                    .put("title", progress.anime.title)
                    .put("description", progress.anime.description)
                    .put("poster_url", progress.anime.posterUrl)
                    .put("episode_count", progress.anime.episodeCount)
                    .put("status", progress.anime.status)
                    .put("episode_number", progress.episodeNumber)
                    .put("episode_title", progress.episodeTitle)
                    .put("play_count", progress.playCount)
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
                    val slug = item.optString("slug").takeIf { it.isNotBlank() } ?: continue
                    val title = item.optString("title").takeIf { it.isNotBlank() } ?: continue
                    add(
                        WatchProgress(
                            anime = Anime(
                                id = item.optString("anime_id", "jkanime:$slug"),
                                slug = slug,
                                title = title,
                                description = item.optString("description").takeIf { it.isNotBlank() },
                                posterUrl = item.optString("poster_url").takeIf { it.isNotBlank() },
                                episodeCount = item.optInt("episode_count").takeIf { it > 0 },
                                status = item.optString("status").takeIf { it.isNotBlank() },
                            ),
                            episodeNumber = item.optInt("episode_number"),
                            episodeTitle = item.optString("episode_title", "Episodio ${item.optInt("episode_number")}"),
                            playCount = item.optInt("play_count", 1).coerceAtLeast(1),
                            updatedAt = item.optLong("updated_at", 0L),
                        ),
                    )
                }
            }
        }.getOrDefault(emptyList())
    }

    private companion object {
        const val KEY_HISTORY = "history"
    }
}
