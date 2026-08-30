package lat.codered.anime.tv.data

import lat.codered.anime.tv.domain.Anime
import lat.codered.anime.tv.domain.Episode
import lat.codered.anime.tv.domain.Server
import org.jsoup.Jsoup
import java.net.URI
import java.util.Locale

class JkAnimeParser {
    fun parseSearch(html: String, baseUrl: String): List<Anime> {
        val document = Jsoup.parse(html, baseUrl)
        val results = linkedMapOf<String, Anime>()

        document.select(".anime__item").forEach { card ->
            val link = card.selectFirst("a[href]") ?: return@forEach
            val slug = slugFromUrl(link.absUrl("href"), baseUrl) ?: return@forEach
            if (slug in results || isNavigationSlug(slug)) return@forEach

            val title = cleanText(
                card.selectFirst(".anime__item__text h5 a, h5 a, a[title]")?.attr("title")
                    ?: card.selectFirst(".anime__item__text h5 a, h5 a")?.text()
                    ?: link.attr("title")
                    ?: link.text(),
            )
            if (title.isBlank()) return@forEach

            results[slug] = Anime(
                id = "jkanime:$slug",
                slug = slug,
                title = title,
                posterUrl = card.selectFirst("img")?.absUrl("src")?.ifBlank { null },
            )
        }

        document.select("a[href]").forEach { link ->
            val slug = slugFromUrl(link.absUrl("href"), baseUrl) ?: return@forEach
            if (slug in results || isNavigationSlug(slug)) return@forEach
            if (!looksLikeSearchResult(link.parent()?.className().orEmpty(), link.attr("class"))) return@forEach

            val image = link.selectFirst("img")
            val title = cleanText(link.attr("title").ifBlank { image?.attr("alt").orEmpty() }.ifBlank { link.text() })
            if (title.isBlank()) return@forEach

            results[slug] = Anime(
                id = "jkanime:$slug",
                slug = slug,
                title = title,
                posterUrl = image?.absUrl("src")?.ifBlank { null },
            )
        }

        return results.values.toList()
    }

    fun parseAnime(html: String, slug: String, baseUrl: String): Anime? {
        val document = Jsoup.parse(html, baseUrl)
        val title = cleanText(
            document.selectFirst("meta[property=og:title]")?.attr("content")
                ?: document.title(),
        ).replace(Regex("\\s+-\\s+anime.*$", RegexOption.IGNORE_CASE), "")

        if (title.isBlank()) return null

        return Anime(
            id = "jkanime:$slug",
            slug = slug,
            title = title,
            description = cleanText(document.selectFirst("meta[name=description]")?.attr("content").orEmpty()).ifBlank { null },
            posterUrl = document.selectFirst("meta[property=og:image]")?.attr("content")?.ifBlank { null },
            episodeCount = firstIntAfterLabel(html, "Episodios"),
            status = Regex("data-status=[\"']([^\"']+)[\"']", RegexOption.IGNORE_CASE)
                .find(html)?.groupValues?.get(1)?.let(::cleanText),
        )
    }

    fun parseDirectoryAnimes(html: String, baseUrl: String): List<Anime> {
        val json = extractJsObject(html, "var animes")
            ?: return parseSearch(html, baseUrl)

        val data = Regex("\"data\"\\s*:\\s*\\[(.*?)]\\s*,\\s*\"first_page_url\"", setOf(RegexOption.DOT_MATCHES_ALL))
            .find(json)
            ?.groupValues
            ?.get(1)
            ?: return emptyList()

        return splitJsonObjects(data).mapNotNull { item ->
            val slug = jsonString(item, "slug") ?: return@mapNotNull null
            val title = jsonString(item, "title") ?: return@mapNotNull null
            Anime(
                id = "jkanime:$slug",
                slug = slug,
                title = title,
                description = jsonString(item, "synopsis"),
                posterUrl = jsonString(item, "image"),
                status = jsonString(item, "estado") ?: jsonString(item, "status"),
            )
        }
    }

    fun parseRecommended(html: String, baseUrl: String): List<Anime> {
        val document = Jsoup.parse(html, baseUrl)
        val results = linkedMapOf<String, Anime>()

        document.select(".custom_thumb_home a[href], .hero__items a[href]").forEach { link ->
            val href = link.absUrl("href")
            val slug = slugFromUrl(href, baseUrl) ?: return@forEach
            if (slug in results || isNavigationSlug(slug)) return@forEach

            val container = link.parents().firstOrNull { parent ->
                parent.selectFirst(".card-title a, h5, img[alt]") != null
            }
            val image = container?.selectFirst("img") ?: link.selectFirst("img")
            val title = cleanText(
                container?.selectFirst(".card-title a")?.text()
                    ?: image?.attr("alt")
                    ?: link.attr("title")
                    ?: link.text(),
            )
            if (title.isBlank()) return@forEach

            results[slug] = Anime(
                id = "jkanime:$slug",
                slug = slug,
                title = title,
                posterUrl = image?.absUrl("src")?.ifBlank { image?.attr("data-animepic") }?.ifBlank { null },
            )
        }

        return results.values.toList()
    }

    fun parseEpisodes(body: String, slug: String): Pair<List<Episode>, Int?> {
        val lastPage = Regex("\"last_page\"\\s*:\\s*(\\d+)").find(body)?.groupValues?.get(1)?.toIntOrNull()
        val jsonEpisodes = parseEpisodeJsonData(body, slug)
        if (jsonEpisodes.isNotEmpty()) {
            return jsonEpisodes to lastPage
        }

        val html = Regex("\"html\"\\s*:\\s*\"((?:\\\\.|[^\"])*)\"", setOf(RegexOption.DOT_MATCHES_ALL))
            .find(body)
            ?.groupValues
            ?.get(1)
            ?.let(::unescapeJsonString)
            ?: body

        val document = Jsoup.parse(html)
        val episodes = linkedMapOf<Int, Episode>()
        document.select("a[href]").forEach { link ->
            val number = Regex("/${Regex.escape(slug)}/(\\d+)/?", RegexOption.IGNORE_CASE)
                .find(link.attr("href"))
                ?.groupValues
                ?.get(1)
                ?.toIntOrNull()
                ?: return@forEach

            episodes[number] = Episode(
                id = "jkanime:$slug:$number",
                animeId = "jkanime:$slug",
                number = number,
                title = cleanText(link.text()).ifBlank { "Episodio $number" },
                thumbnailUrl = link.selectFirst("img")?.attr("src")?.ifBlank { null },
            )
        }

        return episodes.values.sortedBy { it.number } to lastPage
    }

    private fun parseEpisodeJsonData(body: String, slug: String): List<Episode> {
        val data = Regex("\"data\"\\s*:\\s*\\[(.*?)]\\s*,\\s*\"first_page_url\"", setOf(RegexOption.DOT_MATCHES_ALL))
            .find(body)
            ?.groupValues
            ?.get(1)
            ?: return emptyList()

        return splitJsonObjects(data).mapNotNull { item ->
            val number = Regex("\"number\"\\s*:\\s*(\\d+)")
                .find(item)
                ?.groupValues
                ?.get(1)
                ?.toIntOrNull()
                ?: return@mapNotNull null
            val image = jsonString(item, "image")
            Episode(
                id = "jkanime:$slug:$number",
                animeId = "jkanime:$slug",
                number = number,
                title = jsonString(item, "title") ?: "Episodio $number",
                thumbnailUrl = image?.let(::episodeImageUrl),
            )
        }.sortedBy { it.number }
    }

    fun parseEpisode(html: String, slug: String, episode: Int): Episode {
        val document = Jsoup.parse(html)
        val title = cleanText(document.title())
            .replace(Regex("\\s+Sub\\s+Español.*$", RegexOption.IGNORE_CASE), "")
            .ifBlank { "Episodio $episode" }

        return Episode(
            id = "jkanime:$slug:$episode",
            animeId = "jkanime:$slug",
            number = episode,
            title = title,
            servers = parseServers(html, slug, episode),
        )
    }

    fun parseServers(html: String, slug: String, episode: Int): List<Server> {
        val names = Regex("data-id=[\"'](\\d+)[\"'][^>]*>([^<]+)</a>", RegexOption.IGNORE_CASE)
            .findAll(html)
            .associate { it.groupValues[1].toInt() to cleanText(it.groupValues[2]) }

        return Regex("video\\[(\\d+)]\\s*=\\s*['\"](.+?)['\"]\\s*;", setOf(RegexOption.DOT_MATCHES_ALL))
            .findAll(html)
            .mapNotNull { match ->
                val index = match.groupValues[1].toInt()
                val iframeHtml = match.groupValues[2].replace("\\/", "/")
                val src = Regex("<iframe[^>]+src=[\"']([^\"']+)[\"']", RegexOption.IGNORE_CASE)
                    .find(iframeHtml)
                    ?.groupValues
                    ?.get(1)
                    ?: return@mapNotNull null
                val name = names[index] ?: serverNameFromUrl(src, index)
                Server(
                    id = "jkanime:$slug:$episode:${slugify(name).ifBlank { "server-$index" }}",
                    name = name,
                    type = if (isDirectStreamUrl(src)) "stream" else "embed",
                    url = src,
                )
            }
            .toList()
    }

    fun externalAnimeId(html: String): String? {
        return Regex("/ajax/episodes/(\\d+)/").find(html)?.groupValues?.get(1)
            ?: Regex("data-anime=[\"'](\\d+)[\"']", RegexOption.IGNORE_CASE).find(html)?.groupValues?.get(1)
    }

    fun csrfToken(html: String): String? {
        return Regex("<meta[^>]+name=[\"']csrf-token[\"'][^>]+content=[\"']([^\"']+)[\"']", RegexOption.IGNORE_CASE)
            .find(html)
            ?.groupValues
            ?.get(1)
    }

    fun firstDirectStreamUrl(html: String): String? {
        return Regex("https?:(?:\\\\/\\\\/|//)[^\"'<>\\s]+?\\.(?:m3u8?|m3u|mp4)(?:\\?[^\"'<>\\s]*)?", RegexOption.IGNORE_CASE)
            .find(html)
            ?.value
            ?.replace("\\/", "/")
            ?.replace("\\u0026", "&")
    }

    fun slugFromId(id: String): String? {
        val slug = id.removePrefix("jkanime:").trim('/')
        return slug.takeIf { Regex("^[a-z0-9][a-z0-9-]{0,120}$").matches(it) }
    }

    private fun slugFromUrl(url: String, baseUrl: String): String? {
        val baseHost = URI(baseUrl).host ?: return null
        val uri = runCatching { URI(url) }.getOrNull() ?: return null
        if (uri.host != baseHost && uri.host != "www.$baseHost") return null
        val parts = uri.path.trim('/').split('/').filter { it.isNotBlank() }
        if (parts.size != 1) return null
        return slugFromId(parts.first())
    }

    private fun looksLikeSearchResult(parentClass: String, linkClass: String): Boolean {
        val haystack = "$parentClass $linkClass".lowercase(Locale.ROOT)
        return listOf("anime", "item", "card", "result", "poster").any { haystack.contains(it) }
    }

    private fun isNavigationSlug(slug: String): Boolean {
        return slug in setOf("directorio", "programacion", "ultimos-episodios", "peliculas", "ovas", "especiales")
    }

    private fun firstIntAfterLabel(html: String, label: String): Int? {
        return Regex("<span>\\s*${Regex.escape(label)}\\s*:\\s*</span>\\s*(\\d+)", RegexOption.IGNORE_CASE)
            .find(html)
            ?.groupValues
            ?.get(1)
            ?.toIntOrNull()
    }

    private fun serverNameFromUrl(url: String, index: Int): String {
        return runCatching { URI(url).path.substringAfterLast('/').ifBlank { "Opcion ${index + 1}" } }
            .getOrDefault("Opcion ${index + 1}")
            .uppercase(Locale.ROOT)
    }

    private fun isDirectStreamUrl(url: String): Boolean = Regex("\\.(m3u8?|mp4)(?:[?#]|$)", RegexOption.IGNORE_CASE).containsMatchIn(url)

    private fun episodeImageUrl(value: String): String {
        if (value.startsWith("http://") || value.startsWith("https://")) return value
        return "https://cdn.jkdesa.com/assets/images/animes/video/image/$value"
    }

    private fun splitJsonObjects(value: String): List<String> {
        val objects = mutableListOf<String>()
        var depth = 0
        var start = -1
        var inString = false
        var escaped = false

        value.forEachIndexed { index, char ->
            when {
                escaped -> escaped = false
                char == '\\' && inString -> escaped = true
                char == '"' -> inString = !inString
                !inString && char == '{' -> {
                    if (depth == 0) start = index
                    depth++
                }
                !inString && char == '}' -> {
                    depth--
                    if (depth == 0 && start >= 0) objects += value.substring(start, index + 1)
                }
            }
        }

        return objects
    }

    private fun extractJsObject(html: String, marker: String): String? {
        val markerIndex = html.indexOf(marker)
        if (markerIndex < 0) return null

        val start = html.indexOf('{', markerIndex)
        if (start < 0) return null

        var depth = 0
        var inString = false
        var escaped = false
        for (index in start until html.length) {
            val char = html[index]
            when {
                escaped -> escaped = false
                char == '\\' && inString -> escaped = true
                char == '"' -> inString = !inString
                !inString && char == '{' -> depth++
                !inString && char == '}' -> {
                    depth--
                    if (depth == 0) return html.substring(start, index + 1)
                }
            }
        }

        return null
    }

    private fun jsonString(json: String, key: String): String? {
        return Regex("\"${Regex.escape(key)}\"\\s*:\\s*\"((?:\\\\.|[^\"])*)\"", setOf(RegexOption.DOT_MATCHES_ALL))
            .find(json)
            ?.groupValues
            ?.get(1)
            ?.let(::unescapeJsonString)
            ?.let(::cleanText)
            ?.ifBlank { null }
    }

    private fun unescapeJsonString(value: String): String {
        return value
            .replace("\\/", "/")
            .replace("\\n", "\n")
            .replace("\\r", "\r")
            .replace("\\t", "\t")
            .replace("\\\"", "\"")
            .replace("\\\\", "\\")
            .replace(Regex("\\\\u([0-9a-fA-F]{4})")) { match ->
                match.groupValues[1].toInt(16).toChar().toString()
            }
    }

    private fun slugify(value: String): String = value.lowercase(Locale.ROOT)
        .replace(Regex("[^a-z0-9]+"), "-")
        .trim('-')

    private fun cleanText(value: String): String = value
        .replace(Regex("\\s+"), " ")
        .trim()
}
