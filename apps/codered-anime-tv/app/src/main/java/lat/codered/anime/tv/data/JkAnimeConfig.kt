package lat.codered.anime.tv.data

data class JkAnimeConfig(
    val baseUrl: String = "https://jkanime.net",
    val providerHosts: Set<String> = setOf("jkanime.net", "www.jkanime.net"),
    val streamHosts: Set<String> = setOf(
        "jkanime.net",
        "www.jkanime.net",
        "nika.playmudos.com",
        "playmudos.com",
    ),
    val userAgent: String = "CodeRED-Anime-TV/0.1.0",
    val maxEpisodePages: Int = 120,
    val serverPriority: List<String> = listOf("desu", "magi"),
)
