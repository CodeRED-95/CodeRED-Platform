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
}
