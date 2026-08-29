package lat.codered.anime.tv.data

import lat.codered.anime.tv.domain.Anime
import lat.codered.anime.tv.domain.Episode
import lat.codered.anime.tv.domain.Server
import org.jsoup.Jsoup
import org.jsoup.nodes.Document
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

    fun parseEpisodes(body: String, slug: String): Pair<List<Episode>, Int?> {
        val lastPage = Regex("\"last_page\"\\s*:\\s*(\\d+)").find(body)?.groupValues?.get(1)?.toIntOrNull()
        val html = Regex("\"html\"\\s*:\\s*\"(.*?)\"", setOf(RegexOption.DOT_MATCHES_ALL))
            .find(body)
            ?.groupValues
            ?.get(1)
            ?.replace("\\/", "/")
            ?.replace("\\\"", "\"")
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

    private fun slugify(value: String): String = value.lowercase(Locale.ROOT)
        .replace(Regex("[^a-z0-9]+"), "-")
        .trim('-')

    private fun cleanText(value: String): String = value
        .replace(Regex("\\s+"), " ")
        .trim()
}
