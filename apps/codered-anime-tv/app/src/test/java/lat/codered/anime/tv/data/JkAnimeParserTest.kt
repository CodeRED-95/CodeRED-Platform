package lat.codered.anime.tv.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class JkAnimeParserTest {
    private val parser = JkAnimeParser()

    @Test
    fun parsesSearchCards() {
        val html = """
            <html><body>
              <div class="anime__item">
                <a href="https://jkanime.net/one-piece/" title="One Piece">
                  <img src="https://cdn.test/one-piece.jpg" />
                </a>
              </div>
            </body></html>
        """.trimIndent()

        val results = parser.parseSearch(html, "https://jkanime.net")

        assertEquals(1, results.size)
        assertEquals("jkanime:one-piece", results.first().id)
        assertEquals("One Piece", results.first().title)
    }

    @Test
    fun parsesEpisodePayloadAndLastPage() {
        val body = """
            {
              "last_page": 2,
              "html": "<a href=\"/one-piece/1175\">One Piece 1175</a><a href=\"/one-piece/1174\">One Piece 1174</a>"
            }
        """.trimIndent()

        val (episodes, lastPage) = parser.parseEpisodes(body, "one-piece")

        assertEquals(2, lastPage)
        assertEquals(listOf(1174, 1175), episodes.map { it.number })
    }

    @Test
    fun parsesCurrentEpisodeJsonPayload() {
        val body = """
            {
              "current_page": 1,
              "data": [
                {"id":76395,"number":1,"title":"Shiguang Dailiren III - 1","image":"jkvideo_demo.jpg"},
                {"id":76396,"number":2,"title":"Shiguang Dailiren III - 2","image":"https:\/\/cdn.test\/episode.jpg"}
              ],
              "first_page_url":"https:\/\/jkanime.net\/ajax\/episodes\/4909\/1?p=1",
              "last_page": 1
            }
        """.trimIndent()

        val (episodes, lastPage) = parser.parseEpisodes(body, "shiguang-dailiren-iii")

        assertEquals(1, lastPage)
        assertEquals(listOf(1, 2), episodes.map { it.number })
        assertEquals("https://cdn.jkdesa.com/assets/images/animes/video/image/jkvideo_demo.jpg", episodes.first().thumbnailUrl)
    }

    @Test
    fun parsesDirectoryEmbeddedJson() {
        val html = """
            <script>
              var animes = {"data":[{"title":"Shiguang Dailiren III","synopsis":"Nueva temporada","image":"https:\/\/cdn.test\/cover.jpg","slug":"shiguang-dailiren-iii","estado":"En emision"}],"first_page_url":"https:\/\/jkanime.net\/directorio?p=1"};
            </script>
        """.trimIndent()

        val results = parser.parseDirectoryAnimes(html, "https://jkanime.net")

        assertEquals(1, results.size)
        assertEquals("jkanime:shiguang-dailiren-iii", results.first().id)
        assertEquals("https://cdn.test/cover.jpg", results.first().posterUrl)
    }

    @Test
    fun parsesRecommendedHomeCards() {
        val html = """
            <div class="p-3 d-flex">
              <div class="custom_thumb_home">
                <a href="https://jkanime.net/one-piece/"><img src="https://cdn.test/one-piece.jpg" alt="One Piece"></a>
              </div>
              <div class="card-body-home"><h5 class="card-title"><a href="https://jkanime.net/one-piece/">One Piece</a></h5></div>
            </div>
        """.trimIndent()

        val results = parser.parseRecommended(html, "https://jkanime.net")

        assertEquals(1, results.size)
        assertEquals("One Piece", results.first().title)
    }

    @Test
    fun rejectsInvalidSlugIds() {
        assertNull(parser.slugFromId("https://169.254.169.254/latest"))
    }

    @Test
    fun extractsDirectStreamFromEmbedHtml() {
        val html = """<script>const source = "https:\/\/nika.playmudos.com\/media\/episode.m3u8?token=demo";</script>"""

        assertEquals(
            "https://nika.playmudos.com/media/episode.m3u8?token=demo",
            parser.firstDirectStreamUrl(html),
        )
    }

    @Test
    fun parsesCurrentJkPlayerServers() {
        val html = """
            <a class="nav-link" data-id="0">Desu</a>
            <a class="nav-link" data-id="1">Magi</a>
            <script>
              video[0] = '<iframe class="player_conte" src="https://jkanime.net/jkplayer/um?e=abc&t=token&op=123" allowfullscreen></iframe>';
              video[1] = '<iframe class="player_conte" src="https://jkanime.net/jkplayer/umv?e=def&t=token&op=123" allowfullscreen></iframe>';
            </script>
        """.trimIndent()

        val servers = parser.parseServers(html, "one-piece", 1175)

        assertEquals(listOf("Desu", "Magi"), servers.map { it.name })
        assertEquals("https://jkanime.net/jkplayer/um?e=abc&t=token&op=123", servers.first().url)
    }
}
