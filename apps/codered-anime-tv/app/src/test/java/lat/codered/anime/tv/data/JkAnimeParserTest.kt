package lat.codered.anime.tv.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import lat.codered.anime.tv.domain.ScheduleDay
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
    fun parsesHomeScheduleTabs() {
        val html = """
            <div class="tab-content">
              <div class="tab-pane fade show active" id="animes">
                <div class="card">
                  <a href="https://jkanime.net/digimon-beatbreak/45/">
                    <div class="d-thumb">
                      <img src="https://cdn.test/jkvideo.jpg" data-animepic="https://cdn.test/digimon.jpg" alt="Digimon Beatbreak - 45">
                      <div class="badges badges-top">
                        <span class="badge badge-primary">Ep 45</span>
                        <span class="badge ml-2 badge-secondary">Hoy</span>
                      </div>
                    </div>
                    <h5 class="strlimit card-title">Digimon Beatbreak</h5>
                  </a>
                </div>
              </div>
              <div class="tab-pane fade" id="donghuas">
                <div class="card">
                  <a href="https://jkanime.net/douluo-dalu-ii-jueshi-tangmen/147/">
                    <img src="https://cdn.test/douluo-video.jpg" alt="Douluo Dalu II: Jueshi Tangmen - 147">
                    <div class="badges badges-top"><span class="badge badge-primary">Ep 147</span><span class="badge badge-secondary">Jueves 27</span></div>
                    <h5 class="strlimit card-title">Douluo Dalu II: Jueshi Tangmen</h5>
                  </a>
                </div>
              </div>
            </div>
        """.trimIndent()

        val results = parser.parseSchedule(html, "https://jkanime.net")

        assertEquals(2, results.size)
        assertEquals("jkanime:digimon-beatbreak", results.first().id)
        assertEquals(45, results.first().scheduleEpisode)
        assertEquals("Hoy", results.first().scheduleLabel)
        assertEquals("Animes", results.first().scheduleCategory)
        assertEquals("Donghuas", results.last().scheduleCategory)
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

    @Test
    fun parsesWeeklyScheduleByDay() {
        // Estructura real de jkanime.net/horario: un bloque por dia mas un
        // bloque extra de busqueda que no corresponde a ningun dia.
        val html = """
            <html><body>
              <div class="box semana">
                <h2><i class="ti ti-calendar-clock"></i> Miércoles</h2>
                <div class="cajas">
                  <div title="one piece" class="box img">
                    <div class="boxx">
                      <a href="https://jkanime.net/one-piece/">
                        <img title="One Piece" src="https://cdn.test/one-piece.jpg">
                      </a>
                      <a class="shadowTitle" href="https://jkanime.net/one-piece/"><h3>One Pie...</h3></a>
                    </div>
                    <div class="last">
                      <a href="https://jkanime.net/one-piece/">
                        <span>Último capítulo: 1175 </span>
                        <time>hace 5 horas </time>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
              <div class="box semana">
                <h2>Buscar anime</h2>
                <div class="cajas"></div>
              </div>
            </body></html>
        """.trimIndent()

        val entries = parser.parseWeeklySchedule(html, "https://jkanime.net")

        assertEquals(1, entries.size)
        val entry = entries.first()
        assertEquals(ScheduleDay.Wednesday, entry.day)
        assertEquals("jkanime:one-piece", entry.anime.id)
        // El h3 llega recortado; se toma el title completo de la imagen.
        assertEquals("One Piece", entry.anime.title)
        assertEquals(1175, entry.lastEpisode)
        assertEquals("hace 5 horas", entry.relativeTime)
    }

    @Test
    fun parsesSearchCardsWithoutTitleAttributeAndIgnoresMenuLinks() {
        // Marcado real de /buscar: el enlace no lleva atributo title, la portada
        // va en data-setbg y el menu del sitio usa clases con "item".
        val html = """
            <html><body>
              <div class="anime__item">
                <a href="https://jkanime.net/one-piece/">
                  <div class="g-0 anime__item__pic set-bg" data-setbg="https://cdn.test/one-piece.jpg"></div>
                </a>
                <div class="anime__item__text">
                  <ul><li>En emision</li><li class="anime">Serie</li></ul>
                  <h5><a href="https://jkanime.net/one-piece/">One Piece</a></h5>
                </div>
              </div>
              <div class="fullmenu_container d-bg">
                <a class="mobile-bottom-nav__item" href="https://jkanime.net/historial">Historial</a>
                <a class="mobile-bottom-nav__item" href="https://jkanime.net/comunidad">Comunidad</a>
              </div>
            </body></html>
        """.trimIndent()

        val results = parser.parseSearch(html, "https://jkanime.net")

        assertEquals(1, results.size)
        val anime = results.first()
        assertEquals("jkanime:one-piece", anime.id)
        assertEquals("One Piece", anime.title)
        assertEquals("https://cdn.test/one-piece.jpg", anime.posterUrl)
        assertEquals("En emision", anime.status)
    }
}
