package lat.codered.anime.tv.data

import lat.codered.anime.tv.domain.Anime
import lat.codered.anime.tv.domain.AnimeResult
import lat.codered.anime.tv.domain.Episode
import lat.codered.anime.tv.domain.HomeShelves
import lat.codered.anime.tv.domain.ScheduleEntry
import lat.codered.anime.tv.domain.Server
import lat.codered.anime.tv.domain.Stream
import okhttp3.Cookie
import okhttp3.CookieJar
import okhttp3.FormBody
import okhttp3.HttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.OkHttpClient
import okhttp3.Request
import java.net.URI
import java.util.concurrent.TimeUnit

class JkAnimeClient(
    private val config: JkAnimeConfig = JkAnimeConfig(),
    private val parser: JkAnimeParser = JkAnimeParser(),
    httpClient: OkHttpClient? = null,
) {
    private val cookieJar = MemoryCookieJar()
    private val client = httpClient ?: OkHttpClient.Builder()
        .connectTimeout(10, TimeUnit.SECONDS)
        .readTimeout(15, TimeUnit.SECONDS)
        .followRedirects(true)
        .cookieJar(cookieJar)
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

    suspend fun getHomeShelves(): AnimeResult<HomeShelves> = io {
        val homeHtml = get(providerUrl("/").toString())
        val directoryHtml = getOrEmpty(providerUrl("/directorio/").toString())
        val topHtml = getOrEmpty(providerUrl("/top").toString())
        val directory = parser.parseDirectoryAnimes(directoryHtml, config.baseUrl).take(24)
        val recommended = parser.parseRecommended(homeHtml, config.baseUrl).take(24)
        val schedule = parser.parseSchedule(homeHtml, config.baseUrl).take(48)
        // Top real de votados. Si la pagina falla se cae al comportamiento
        // anterior para no dejar la seccion vacia.
        val top = parser.parseTop(topHtml, config.baseUrl).ifEmpty { recommended.ifEmpty { directory } }.take(50)
        HomeShelves(
            newlyAdded = directory.take(20),
            recommended = recommended.take(20),
            directory = directory,
            schedule = schedule,
            // "Estrenos" esta desactivado: no se pide /ultimos-episodios.
            premieres = emptyList(),
            top = top,
        )
    }

    suspend fun getWeeklySchedule(): AnimeResult<List<ScheduleEntry>> = io {
        parser.parseWeeklySchedule(get(providerUrl("/horario").toString()), config.baseUrl)
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
        val body = FormBody.Builder().apply {
            if (!csrf.isNullOrBlank()) add("_token", csrf)
        }.build()

        val request = Request.Builder()
            .url(providerUrl("/ajax/episodes/$externalId/$page"))
            .header("User-Agent", config.userAgent)
            .header("Accept", "application/json, text/html")
            .header("Referer", providerUrl("/$slug/").toString())
            .header("X-Requested-With", "XMLHttpRequest")
            .apply {
                if (!csrf.isNullOrBlank()) header("X-CSRF-TOKEN", csrf)
            }
            .post(body)
            .build()

        client.newCall(request).execute().use { response ->
            if (!response.isSuccessful) return emptyList<Episode>() to null
            return parser.parseEpisodes(response.body?.string().orEmpty(), slug)
        }
    }

    private fun resolveServer(server: Server, animeId: String, episode: Int): Stream? {
        if (!isAllowedProviderUrl(server.url) && !isAllowedStreamUrl(server.url)) return null

        val episodeUrl = providerUrl("/${parser.slugFromId(animeId)}/$episode").toString()
        var streamUrl = server.url
        var streamReferer = episodeUrl
        if (!isDirectStreamUrl(streamUrl)) {
            if (!isAllowedProviderUrl(streamUrl)) return null
            val embedUrl = streamUrl
            val html = get(embedUrl, referer = episodeUrl)
            streamUrl = parser.firstDirectStreamUrl(html) ?: return null
            streamReferer = embedUrl
        }

        if (!isAllowedStreamUrl(streamUrl) || !isDirectStreamUrl(streamUrl)) return null
        val path = URI(streamUrl).path.orEmpty()
        val format = path.substringAfterLast('.', missingDelimiterValue = "").lowercase()

        return Stream(
            url = streamUrl,
            type = if (format == "m3u8" || format == "m3u") "hls" else "file",
            format = format,
            headers = playbackHeaders(streamReferer),
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

    private fun playbackHeaders(referer: String): Map<String, String> {
        val origin = runCatching {
            val uri = URI(referer)
            "${uri.scheme}://${uri.host}"
        }.getOrNull()

        return buildMap {
            put("Referer", referer)
            if (!origin.isNullOrBlank()) put("Origin", origin)
        }
    }

    private fun getOrEmpty(url: String, referer: String? = null): String {
        return runCatching { get(url, referer) }.getOrDefault("")
    }

    private fun isAllowedHttpsHost(url: String, hosts: Set<String>): Boolean {
        val uri = runCatching { URI(url) }.getOrNull() ?: return false
        return uri.scheme == "https" && uri.host in hosts
    }

    private fun isDirectStreamUrl(url: String): Boolean = Regex("\\.(m3u8?|mp4)(?:[?#]|$)", RegexOption.IGNORE_CASE).containsMatchIn(url)

    private suspend fun <T> io(block: suspend () -> T): AnimeResult<T> = kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.IO) {
        runCatching { block() }
            .fold(
                onSuccess = { AnimeResult.Success(it) },
                onFailure = { AnimeResult.Failure(it.message ?: "No se pudo consultar JkAnime.", it) },
            )
    }

    private fun String.ensureLeadingSlash(): String = if (startsWith("/")) this else "/$this"

    private class MemoryCookieJar : CookieJar {
        private val cookies = mutableListOf<Cookie>()

        override fun saveFromResponse(url: HttpUrl, cookies: List<Cookie>) {
            this.cookies.removeAll { stored -> cookies.any { it.name == stored.name && it.domain == stored.domain } }
            this.cookies.addAll(cookies)
        }

        override fun loadForRequest(url: HttpUrl): List<Cookie> {
            val now = System.currentTimeMillis()
            cookies.removeAll { it.expiresAt < now }
            return cookies.filter { it.matches(url) }
        }
    }
}
