package lat.codered.anime.tv.data

import lat.codered.anime.tv.domain.Anime
import lat.codered.anime.tv.domain.AnimeResult
import lat.codered.anime.tv.domain.Episode
import lat.codered.anime.tv.domain.Server
import lat.codered.anime.tv.domain.Stream
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.net.URI
import java.util.concurrent.TimeUnit

class JkAnimeClient(
    private val config: JkAnimeConfig = JkAnimeConfig(),
    private val parser: JkAnimeParser = JkAnimeParser(),
    httpClient: OkHttpClient? = null,
) {
    private val client = httpClient ?: OkHttpClient.Builder()
        .connectTimeout(10, TimeUnit.SECONDS)
        .readTimeout(15, TimeUnit.SECONDS)
        .followRedirects(true)
        .build()

    suspend fun search(query: String): AnimeResult<List<Anime>> = io {
        val cleanQuery = query.trim()
        if (cleanQuery.length < 2) return@io emptyList()

        val url = providerUrl("/buscar").newBuilder()
            .addQueryParameter("q", cleanQuery)
            .build()
        parser.parseSearch(get(url.toString()), config.baseUrl)
    }

    suspend fun getAnime(id: String): AnimeResult<Anime?> = io {
        val slug = parser.slugFromId(id) ?: return@io null
        parser.parseAnime(get(providerUrl("/$slug/").toString()), slug, config.baseUrl)
    }

    suspend fun getEpisodes(animeId: String): AnimeResult<List<Episode>> = io {
        val slug = parser.slugFromId(animeId) ?: return@io emptyList()
        val animeHtml = get(providerUrl("/$slug/").toString())
        val externalId = parser.externalAnimeId(animeHtml) ?: return@io emptyList()
        val csrf = parser.csrfToken(animeHtml)

        val firstPage = fetchEpisodePage(slug, externalId, 1, csrf)
        val episodes = firstPage.first.associateBy { it.number }.toMutableMap()
        val lastPage = firstPage.second?.coerceAtMost(config.maxEpisodePages) ?: 1

        for (page in 2..lastPage) {
            fetchEpisodePage(slug, externalId, page, csrf).first.forEach { episode ->
                episodes[episode.number] = episode
            }
        }

        episodes.values.sortedBy { it.number }
    }

    suspend fun getServers(animeId: String, episode: Int): AnimeResult<List<Server>> = io {
        val slug = parser.slugFromId(animeId) ?: return@io emptyList()
        parser.parseEpisode(get(providerUrl("/$slug/$episode").toString()), slug, episode).servers
    }

    suspend fun getStream(animeId: String, episode: Int, preferredServer: String? = null): AnimeResult<Stream?> = io {
        val servers = when (val result = getServers(animeId, episode)) {
            is AnimeResult.Success -> result.value
            is AnimeResult.Failure -> throw result.cause ?: IllegalStateException(result.message)
        }

        val ordered = orderServers(servers, preferredServer)
        for (server in ordered) {
            resolveServer(server, animeId, episode)?.let { return@io it }
        }

        null
    }

    private fun fetchEpisodePage(slug: String, externalId: String, page: Int, csrf: String?): Pair<List<Episode>, Int?> {
        val request = Request.Builder()
            .url(providerUrl("/ajax/episodes/$externalId/$page"))
            .header("User-Agent", config.userAgent)
            .header("Accept", "application/json, text/html")
            .header("Referer", providerUrl("/$slug/").toString())
            .header("X-Requested-With", "XMLHttpRequest")
            .apply {
                if (!csrf.isNullOrBlank()) header("X-CSRF-TOKEN", csrf)
            }
            .post(ByteArray(0).toRequestBody(null))
            .build()

        client.newCall(request).execute().use { response ->
            if (!response.isSuccessful) return emptyList<Episode>() to null
            return parser.parseEpisodes(response.body?.string().orEmpty(), slug)
        }
    }

    private fun resolveServer(server: Server, animeId: String, episode: Int): Stream? {
        if (!isAllowedProviderUrl(server.url) && !isAllowedStreamUrl(server.url)) return null

        var streamUrl = server.url
        if (!isDirectStreamUrl(streamUrl)) {
            if (!isAllowedProviderUrl(streamUrl)) return null
            val html = get(streamUrl, referer = providerUrl("/${parser.slugFromId(animeId)}/$episode").toString())
            streamUrl = parser.firstDirectStreamUrl(html) ?: return null
        }

        if (!isAllowedStreamUrl(streamUrl) || !isDirectStreamUrl(streamUrl)) return null
        val path = URI(streamUrl).path.orEmpty()
        val format = path.substringAfterLast('.', missingDelimiterValue = "").lowercase()

        return Stream(
            url = streamUrl,
            type = if (format == "m3u8" || format == "m3u") "hls" else "file",
            format = format,
        )
    }

    private fun orderServers(servers: List<Server>, preferredServer: String?): List<Server> {
        val priority = listOfNotNull(preferredServer?.lowercase()).plus(config.serverPriority)
        return servers.sortedBy { server ->
            val index = priority.indexOfFirst { server.id.lowercase().contains(it) || server.name.lowercase() == it }
            if (index >= 0) index else priority.size + servers.indexOf(server)
        }
    }

    private fun get(url: String, referer: String? = null): String {
        val request = Request.Builder()
            .url(url)
            .header("User-Agent", config.userAgent)
            .header("Accept", "text/html,application/xhtml+xml,application/json")
            .apply {
                if (!referer.isNullOrBlank()) header("Referer", referer)
            }
            .build()

        client.newCall(request).execute().use { response ->
            if (!response.isSuccessful) error("JkAnime responded with HTTP ${response.code}")
            return response.body?.string().orEmpty()
        }
    }

    private fun providerUrl(path: String) = config.baseUrl.trimEnd('/').toHttpUrl().newBuilder()
        .encodedPath(path.ensureLeadingSlash())
        .build()
        .also { require(isAllowedProviderUrl(it.toString())) { "JkAnime URL fuera de allowlist." } }

    private fun isAllowedProviderUrl(url: String): Boolean = isAllowedHttpsHost(url, config.providerHosts)

    private fun isAllowedStreamUrl(url: String): Boolean = isAllowedHttpsHost(url, config.streamHosts)

    private fun isAllowedHttpsHost(url: String, hosts: Set<String>): Boolean {
        val uri = runCatching { URI(url) }.getOrNull() ?: return false
        return uri.scheme == "https" && uri.host in hosts
    }

    private fun isDirectStreamUrl(url: String): Boolean = Regex("\\.(m3u8?|mp4)(?:[?#]|$)", RegexOption.IGNORE_CASE).containsMatchIn(url)

    private suspend fun <T> io(block: () -> T): AnimeResult<T> = kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.IO) {
        runCatching(block)
            .fold(
                onSuccess = { AnimeResult.Success(it) },
                onFailure = { AnimeResult.Failure(it.message ?: "No se pudo consultar JkAnime.", it) },
            )
    }

    private fun String.ensureLeadingSlash(): String = if (startsWith("/")) this else "/$this"
}
