package lat.codered.anime.tv.ui

import android.os.Bundle
import android.content.Intent
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.activity.compose.BackHandler
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.focus.focusProperties
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.key.Key
import androidx.compose.ui.input.key.KeyEvent
import androidx.compose.ui.input.key.KeyEventType
import androidx.compose.ui.input.key.key
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.input.key.type
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import coil3.compose.AsyncImage
import lat.codered.anime.tv.domain.Anime
import lat.codered.anime.tv.domain.Episode
import lat.codered.anime.tv.domain.Stream
import lat.codered.anime.tv.domain.WatchProgress

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                AnimeTvApp()
            }
        }
    }
}

@Composable
private fun AnimeTvApp(viewModel: AnimeTvViewModel = viewModel()) {
    val state by viewModel.state.collectAsState()
    val context = LocalContext.current
    val focusManager = LocalFocusManager.current
    val playerLauncher = rememberLauncherForActivityResult(ActivityResultContracts.StartActivityForResult()) {
        viewModel.refreshLocalShelves()
    }

    LaunchedEffect(Unit) {
        focusManager.clearFocus(force = true)
    }

    BackHandler(enabled = state.selectedAnime != null) {
        viewModel.closeDetails()
    }

    LaunchedEffect(state.stream?.url) {
        val stream = state.stream ?: return@LaunchedEffect
        val intent = Intent(context, PlayerActivity::class.java).apply {
            putExtra(PlayerActivity.EXTRA_STREAM_URL, stream.url)
            putExtra(PlayerActivity.EXTRA_TITLE, state.playingEpisode?.title ?: state.selectedAnime?.title ?: "CodeRED Anime TV")
            putExtra(PlayerActivity.EXTRA_REFERER, stream.headers["Referer"])
            putExtra(PlayerActivity.EXTRA_ORIGIN, stream.headers["Origin"])
            putExtra(PlayerActivity.EXTRA_START_POSITION_MS, state.playbackStartPositionMs)
            state.selectedAnime?.let { anime ->
                putExtra(PlayerActivity.EXTRA_ANIME_ID, anime.id)
                putExtra(PlayerActivity.EXTRA_ANIME_SLUG, anime.slug)
                putExtra(PlayerActivity.EXTRA_ANIME_TITLE, anime.title)
                putExtra(PlayerActivity.EXTRA_ANIME_DESCRIPTION, anime.description)
                putExtra(PlayerActivity.EXTRA_ANIME_POSTER_URL, anime.posterUrl)
                putExtra(PlayerActivity.EXTRA_ANIME_EPISODE_COUNT, anime.episodeCount ?: 0)
                putExtra(PlayerActivity.EXTRA_ANIME_STATUS, anime.status)
            }
            state.playingEpisode?.let { episode ->
                putExtra(PlayerActivity.EXTRA_EPISODE_NUMBER, episode.number)
                putExtra(PlayerActivity.EXTRA_EPISODE_TITLE, episode.title)
            }
            addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP)
        }
        runCatching { playerLauncher.launch(intent) }
            .onFailure {
                Toast.makeText(context, "No se pudo abrir el reproductor.", Toast.LENGTH_LONG).show()
                viewModel.reportPlaybackError(it.message ?: "error al iniciar pantalla")
            }
        viewModel.closePlayer()
    }

    Surface(
        modifier = Modifier.fillMaxSize(),
        color = Color(0xFF070A12),
    ) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.radialGradient(
                        colors = listOf(Color(0xFF3D0618), Color(0xFF070A12)),
                        radius = 1100f,
                    ),
                )
                .padding(horizontal = 44.dp, vertical = 34.dp),
        ) {
            if (state.selectedAnime != null) {
                AnimeDetailScreen(
                    state = state,
                    onBack = viewModel::closeDetails,
                    onPlay = viewModel::playEpisode,
                    onMarkWatched = viewModel::markEpisodeWatched,
                    onToggleFavorite = viewModel::toggleSelectedFavorite,
                    modifier = Modifier.fillMaxSize(),
                )
            } else {
                HomeScreen(
                    state = state,
                    viewModel = viewModel,
                    modifier = Modifier.fillMaxSize(),
                )
            }

            if (state.loading) {
                CircularProgressIndicator(
                    modifier = Modifier.align(Alignment.TopEnd),
                    color = Color(0xFFE11D48),
                )
            }

        }
    }
}

@Composable
private fun HomeScreen(state: AnimeTvState, viewModel: AnimeTvViewModel, modifier: Modifier = Modifier) {
    LazyColumn(
        verticalArrangement = Arrangement.spacedBy(26.dp),
        modifier = modifier,
    ) {
        item {
            Header(state, viewModel)
        }

        state.message?.let { message ->
            item {
                Text(
                    text = message,
                    color = Color(0xFFFFB4C8),
                    fontWeight = FontWeight.SemiBold,
                )
            }
        }

        if (state.continueWatching.isNotEmpty()) {
            item {
                ContinueWatchingShelf(
                    items = state.continueWatching,
                    onSelect = viewModel::resume,
                    autoFocusFirst = state.selectedAnime == null && state.stream == null,
                )
            }
        }

        if (state.favorites.isNotEmpty()) {
            item {
                AnimeShelf(
                    title = "Animes favoritos",
                    subtitle = "Tus titulos guardados en este Android TV.",
                    items = state.favorites,
                    selected = state.selectedAnime,
                    onSelect = viewModel::selectAnime,
                )
            }
        }

        if (state.watchedEpisodes.isNotEmpty()) {
            item {
                ProgressShelf(
                    title = "Capitulos vistos",
                    subtitle = "Episodios marcados automaticamente al llegar al final.",
                    items = state.watchedEpisodes,
                    onSelect = viewModel::resume,
                    badge = { "Visto" },
                )
            }
        }

        if (state.history.isNotEmpty()) {
            item {
                ProgressShelf(
                    title = "Historial",
                    subtitle = "Tus reproducciones recientes.",
                    items = state.history,
                    onSelect = viewModel::resume,
                    badge = { "Episodio ${it.episodeNumber}" },
                )
            }
        }

        if (state.premieres.isNotEmpty()) {
            item {
                AnimeShelf(
                    title = "Estrenos",
                    subtitle = "Ultimos episodios publicados por JkAnime.",
                    items = state.premieres,
                    selected = state.selectedAnime,
                    onSelect = viewModel::selectAnime,
                )
            }
        }

        if (state.top.isNotEmpty()) {
            item {
                AnimeShelf(
                    title = "Top",
                    subtitle = "Titulos destacados detectados en portada.",
                    items = state.top,
                    selected = state.selectedAnime,
                    onSelect = viewModel::selectAnime,
                )
            }
        }

        item {
            AnimeShelf(
                title = "Nuevos agregados",
                subtitle = "Catalogo reciente detectado en JkAnime.",
                items = state.newlyAdded,
                selected = state.selectedAnime,
                onSelect = viewModel::selectAnime,
                autoFocusFirst = state.continueWatching.isEmpty() && state.selectedAnime == null && state.stream == null,
            )
        }

        if (state.mostViewed.isNotEmpty()) {
            item {
                MostViewedShelf(
                    items = state.mostViewed,
                    onSelect = viewModel::resume,
                )
            }
        }

        if (state.schedule.isNotEmpty()) {
            item {
                AnimeShelf(
                    title = "Programacion",
                    subtitle = "Salidas de capitulos anunciadas por JkAnime.",
                    items = state.schedule,
                    selected = state.selectedAnime,
                    onSelect = viewModel::selectAnime,
                )
            }
        }

        item {
            AnimeShelf(
                title = "Recomendados",
                subtitle = "Sugerencias publicas de la portada.",
                items = state.recommended,
                selected = state.selectedAnime,
                onSelect = viewModel::selectAnime,
            )
        }

        if (state.directory.isNotEmpty()) {
            item {
                AnimeShelf(
                    title = "Directorio de animes",
                    subtitle = "Catalogo general para explorar.",
                    items = state.directory,
                    selected = state.selectedAnime,
                    onSelect = viewModel::selectAnime,
                )
            }
        }

        if (state.results.isNotEmpty()) {
            item {
                ResultsPanel(
                    results = state.results,
                    selected = state.selectedAnime,
                    onSelect = viewModel::selectAnime,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        }
    }
}

@Composable
private fun Header(state: AnimeTvState, viewModel: AnimeTvViewModel) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(24.dp),
        verticalAlignment = Alignment.Bottom,
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                "CodeRED Anime TV",
                color = Color.White,
                fontSize = 40.sp,
                lineHeight = 44.sp,
                fontWeight = FontWeight.Black,
            )
            Text(
                "Portada, busqueda y reproduccion directa para Android TV.",
                color = Color(0xFFC3CBE0),
                fontSize = 18.sp,
                lineHeight = 25.sp,
            )
        }

        OutlinedTextField(
            value = state.query,
            onValueChange = viewModel::updateQuery,
            singleLine = true,
            label = { Text("Buscar anime") },
            modifier = Modifier
                .width(520.dp)
                .focusProperties { canFocus = false },
        )
        Button(
            onClick = viewModel::search,
        ) {
            Text("Buscar")
        }
    }
}

@Composable
private fun AnimeDetailScreen(
    state: AnimeTvState,
    onBack: () -> Unit,
    onPlay: (Episode) -> Unit,
    onMarkWatched: (Episode) -> Unit,
    onToggleFavorite: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val anime = state.selectedAnime ?: return
    Row(
        modifier = modifier,
        horizontalArrangement = Arrangement.spacedBy(28.dp),
    ) {
        Column(
            modifier = Modifier
                .width(420.dp)
                .fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(18.dp),
        ) {
            Button(onClick = onBack) {
                Text("Volver")
            }

            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(590.dp)
                    .clip(RoundedCornerShape(28.dp))
                    .background(Color(0xFF111827)),
            ) {
                AsyncImage(
                    model = anime.posterUrl,
                    contentDescription = anime.title,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize(),
                )
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(
                            Brush.verticalGradient(
                                0.2f to Color.Transparent,
                                1f to Color(0xF2070A12),
                            ),
                        ),
                )
                Text(
                    text = anime.episodeCount?.let { "$it episodios" } ?: anime.status ?: "Disponible",
                    color = Color.White,
                    fontWeight = FontWeight.Black,
                    fontSize = 20.sp,
                    modifier = Modifier
                        .align(Alignment.BottomStart)
                        .padding(24.dp),
                )
            }
        }

        LazyColumn(
            modifier = Modifier
                .weight(1f)
                .fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(22.dp),
        ) {
            item {
                DetailHero(
                    anime = anime,
                    isFavorite = state.selectedAnimeIsFavorite,
                    onToggleFavorite = onToggleFavorite,
                )
            }

            state.message?.let { message ->
                item {
                    Text(
                        text = message,
                        color = Color(0xFFFFB4C8),
                        fontWeight = FontWeight.SemiBold,
                        fontSize = 18.sp,
                    )
                }
            }

            item {
                EpisodesPanel(
                    anime = anime,
                    episodes = state.episodes,
                    onPlay = onPlay,
                    onMarkWatched = onMarkWatched,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        }
    }
}

@Composable
private fun DetailHero(anime: Anime, isFavorite: Boolean, onToggleFavorite: () -> Unit) {
    Panel(
        title = anime.title,
        subtitle = "Selecciona un capitulo para reproducirlo en pantalla completa.",
        modifier = Modifier.fillMaxWidth(),
    ) {
        Text(
            text = anime.description?.takeIf { it.isNotBlank() } ?: "Metadata detectada desde JkAnime. La lista de episodios se carga en tiempo real desde el proveedor.",
            color = Color(0xFFD8DFF2),
            fontSize = 18.sp,
            lineHeight = 26.sp,
            maxLines = 5,
            overflow = TextOverflow.Ellipsis,
        )
        Spacer(Modifier.size(14.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            DetailPill(anime.status ?: "Estado no disponible")
            DetailPill(anime.episodeCount?.let { "$it episodios publicados" } ?: "Episodios en vivo")
            DetailPill("Fuente: JkAnime")
        }
        Spacer(Modifier.size(18.dp))
        Button(onClick = onToggleFavorite) {
            Text(if (isFavorite) "Quitar de favoritos" else "Agregar a favoritos")
        }
    }
}

@Composable
private fun DetailPill(text: String) {
    Surface(
        color = Color(0xFF1C2740),
        shape = RoundedCornerShape(999.dp),
        border = BorderStroke(1.dp, Color(0xFF35486D)),
    ) {
        Text(
            text = text,
            color = Color(0xFFC3CBE0),
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
        )
    }
}

@Composable
private fun AnimeShelf(
    title: String,
    subtitle: String,
    items: List<Anime>,
    selected: Anime?,
    onSelect: (Anime) -> Unit,
    modifier: Modifier = Modifier,
    autoFocusFirst: Boolean = false,
) {
    Panel(title = title, subtitle = subtitle, modifier = modifier.height(408.dp)) {
        if (items.isEmpty()) {
            EmptyShelf("Cargando animes...")
            return@Panel
        }

        LazyRow(contentPadding = PaddingValues(8.dp), horizontalArrangement = Arrangement.spacedBy(18.dp)) {
            items(items, key = { it.id }) { anime ->
                AnimeCard(
                    anime = anime,
                    selected = anime.id == selected?.id,
                    autoFocus = autoFocusFirst && anime.id == items.firstOrNull()?.id,
                    onClick = { onSelect(anime) },
                )
            }
        }
    }
}

@Composable
private fun ContinueWatchingShelf(
    items: List<WatchProgress>,
    onSelect: (WatchProgress) -> Unit,
    modifier: Modifier = Modifier,
    autoFocusFirst: Boolean = false,
) {
    Panel(title = "Continuar viendo", subtitle = "Retoma desde el ultimo episodio reproducido.", modifier = modifier.height(270.dp)) {
        LazyRow(contentPadding = PaddingValues(8.dp), horizontalArrangement = Arrangement.spacedBy(18.dp)) {
            items(items, key = { "${it.anime.id}:${it.episodeNumber}" }) { progress ->
                ProgressCard(
                    progress = progress,
                    autoFocus = autoFocusFirst && progress == items.firstOrNull(),
                    onClick = { onSelect(progress) },
                )
            }
        }
    }
}

@Composable
private fun MostViewedShelf(items: List<WatchProgress>, onSelect: (WatchProgress) -> Unit, modifier: Modifier = Modifier) {
    Panel(title = "Mas vistos", subtitle = "Ranking local segun lo que reproduces en este Android TV.", modifier = modifier.height(270.dp)) {
        LazyRow(contentPadding = PaddingValues(8.dp), horizontalArrangement = Arrangement.spacedBy(18.dp)) {
            items(items, key = { "${it.anime.id}:${it.episodeNumber}:views" }) { progress ->
                ProgressCard(progress = progress, onClick = { onSelect(progress) }, badge = "${progress.playCount} vistas")
            }
        }
    }
}

@Composable
private fun ProgressShelf(
    title: String,
    subtitle: String,
    items: List<WatchProgress>,
    onSelect: (WatchProgress) -> Unit,
    badge: (WatchProgress) -> String,
    modifier: Modifier = Modifier,
) {
    Panel(title = title, subtitle = subtitle, modifier = modifier.height(270.dp)) {
        LazyRow(contentPadding = PaddingValues(8.dp), horizontalArrangement = Arrangement.spacedBy(18.dp)) {
            items(items, key = { "${it.anime.id}:${it.episodeNumber}:$title" }) { progress ->
                ProgressCard(progress = progress, onClick = { onSelect(progress) }, badge = badge(progress))
            }
        }
    }
}

@Composable
private fun ResultsPanel(results: List<Anime>, selected: Anime?, onSelect: (Anime) -> Unit, modifier: Modifier = Modifier) {
    Panel(title = "Resultados", subtitle = "Selecciona un titulo para cargar episodios.", modifier = modifier.height(408.dp)) {
        LazyRow(contentPadding = PaddingValues(8.dp), horizontalArrangement = Arrangement.spacedBy(18.dp)) {
            items(results, key = { it.id }) { anime ->
                AnimeCard(anime = anime, selected = anime.id == selected?.id, onClick = { onSelect(anime) })
            }
        }
    }
}

@Composable
private fun AnimeCard(anime: Anime, selected: Boolean, autoFocus: Boolean = false, onClick: () -> Unit) {
    val interactionSource = remember { MutableInteractionSource() }
    val focusRequester = remember { FocusRequester() }
    val focused by interactionSource.collectIsFocusedAsState()
    val active = selected || focused

    LaunchedEffect(autoFocus, anime.id) {
        if (autoFocus) focusRequester.requestFocus()
    }

    Card(
        onClick = onClick,
        modifier = Modifier
            .width(232.dp)
            .height(332.dp)
            .focusRequester(focusRequester)
            .onPreviewKeyEvent { event ->
                if (event.isSelectRelease()) {
                    onClick()
                    true
                } else {
                    false
                }
            }
            .graphicsLayer {
                scaleX = if (focused) 1.05f else 1f
                scaleY = if (focused) 1.05f else 1f
                shadowElevation = if (focused) 18f else 4f
            },
        shape = RoundedCornerShape(18.dp),
        colors = CardDefaults.cardColors(containerColor = if (active) Color(0xFF1E2A44) else Color(0xFF101827)),
        border = BorderStroke(if (active) 2.dp else 1.dp, if (active) Color(0xFFFF2E63) else Color(0xFF2B3A55)),
        interactionSource = interactionSource,
    ) {
        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(11.dp)) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(252.dp)
                    .background(Color(0xFF182235))
                    .clip(RoundedCornerShape(14.dp)),
            ) {
                AsyncImage(
                    model = anime.posterUrl,
                    contentDescription = anime.title,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize(),
                )
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(
                            Brush.verticalGradient(
                                0.45f to Color.Transparent,
                                0.78f to Color(0xCC05070D),
                                1f to Color(0xF205070D),
                            ),
                        ),
                )
                Text(
                    anime.title,
                    color = Color.White,
                    maxLines = 3,
                    overflow = TextOverflow.Ellipsis,
                    fontWeight = FontWeight.ExtraBold,
                    fontSize = 18.sp,
                    lineHeight = 21.sp,
                    modifier = Modifier
                        .align(Alignment.BottomStart)
                        .padding(12.dp),
                )
            }
            Text(
                anime.episodeCount?.let { "$it episodios" } ?: anime.status ?: "Disponible",
                color = if (active) Color(0xFFFFC1D0) else Color(0xFFC3CBE0),
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                fontWeight = FontWeight.SemiBold,
                fontSize = 13.sp,
            )
        }
    }
}

@Composable
private fun ProgressCard(
    progress: WatchProgress,
    onClick: () -> Unit,
    badge: String = "Episodio ${progress.episodeNumber}",
    autoFocus: Boolean = false,
) {
    val interactionSource = remember { MutableInteractionSource() }
    val focusRequester = remember { FocusRequester() }
    val focused by interactionSource.collectIsFocusedAsState()

    LaunchedEffect(autoFocus, progress.anime.id, progress.episodeNumber) {
        if (autoFocus) focusRequester.requestFocus()
    }

    Card(
        onClick = onClick,
        modifier = Modifier
            .width(330.dp)
            .height(190.dp)
            .focusRequester(focusRequester)
            .onPreviewKeyEvent { event ->
                if (event.isSelectRelease()) {
                    onClick()
                    true
                } else {
                    false
                }
            }
            .graphicsLayer {
                scaleX = if (focused) 1.04f else 1f
                scaleY = if (focused) 1.04f else 1f
                shadowElevation = if (focused) 16f else 4f
            },
        shape = RoundedCornerShape(18.dp),
        colors = CardDefaults.cardColors(containerColor = if (focused) Color(0xFF1E2A44) else Color(0xFF111827)),
        border = BorderStroke(if (focused) 2.dp else 1.dp, if (focused) Color(0xFFFF2E63) else Color(0xFF2E3A51)),
        interactionSource = interactionSource,
    ) {
        Row(modifier = Modifier.padding(12.dp), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            AsyncImage(
                model = progress.anime.posterUrl,
                contentDescription = progress.anime.title,
                contentScale = ContentScale.Crop,
                modifier = Modifier
                    .width(106.dp)
                    .height(158.dp)
                    .background(Color(0xFF182235))
                    .clip(RoundedCornerShape(14.dp)),
            )
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(progress.anime.title, color = Color.White, maxLines = 2, overflow = TextOverflow.Ellipsis, fontWeight = FontWeight.ExtraBold, fontSize = 18.sp, lineHeight = 22.sp)
                Text(badge, color = Color(0xFFFFB4C8), fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
                if (progress.positionMs > 0L) {
                    Text(
                        "Continua en ${formatWatchTime(progress.positionMs)}",
                        color = Color(0xFFD8DFF2),
                        fontWeight = FontWeight.Bold,
                        fontSize = 14.sp,
                    )
                }
                Text(progress.episodeTitle, color = Color(0xFFC3CBE0), maxLines = 2, overflow = TextOverflow.Ellipsis, fontSize = 14.sp, lineHeight = 18.sp)
            }
        }
    }
}

@Composable
private fun EpisodesPanel(
    anime: Anime?,
    episodes: List<Episode>,
    onPlay: (Episode) -> Unit,
    onMarkWatched: (Episode) -> Unit,
    modifier: Modifier = Modifier,
) {
    Panel(title = "Capitulos", subtitle = anime?.let { "Lista detectada para ${it.title}." } ?: "Elige un capitulo para reproducir.", modifier = modifier.height(760.dp)) {
        if (episodes.isEmpty()) {
            EmptyShelf("Cargando capitulos o el proveedor todavia no publico episodios para este anime.")
            return@Panel
        }

        LazyColumn(contentPadding = PaddingValues(6.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            items(episodes, key = { it.id }) { episode ->
                EpisodeCard(
                    episode = episode,
                    autoFocus = episode.id == episodes.firstOrNull()?.id,
                    onClick = { onPlay(episode) },
                    onMarkWatched = { onMarkWatched(episode) },
                )
            }
        }
    }
}

@Composable
private fun EpisodeCard(episode: Episode, autoFocus: Boolean = false, onClick: () -> Unit, onMarkWatched: () -> Unit) {
    val interactionSource = remember { MutableInteractionSource() }
    val focusRequester = remember { FocusRequester() }
    val focused by interactionSource.collectIsFocusedAsState()

    LaunchedEffect(autoFocus, episode.id) {
        if (autoFocus) focusRequester.requestFocus()
    }

    Card(
        onClick = onClick,
        modifier = Modifier
            .fillMaxWidth()
            .focusRequester(focusRequester)
            .onPreviewKeyEvent { event ->
                if (event.isSelectRelease()) {
                    onClick()
                    true
                } else {
                    false
                }
            }
            .graphicsLayer {
                scaleX = if (focused) 1.01f else 1f
                scaleY = if (focused) 1.01f else 1f
            },
        colors = CardDefaults.cardColors(containerColor = if (focused) Color(0xFF22304A) else Color(0xFF151D2D)),
        shape = RoundedCornerShape(14.dp),
        border = BorderStroke(if (focused) 2.dp else 1.dp, if (focused) Color(0xFFFF2E63) else Color(0xFF2E3A51)),
        interactionSource = interactionSource,
    ) {
        Row(
            modifier = Modifier.padding(12.dp),
            horizontalArrangement = Arrangement.spacedBy(14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            AsyncImage(
                model = episode.thumbnailUrl,
                contentDescription = episode.title,
                contentScale = ContentScale.Crop,
                modifier = Modifier
                    .width(120.dp)
                    .height(68.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .background(Color(0xFF101827)),
            )
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Episodio ${episode.number}",
                    color = if (focused) Color(0xFFFFB4C8) else Color(0xFFC3CBE0),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                )
                Text(
                    text = episode.title,
                    color = Color.White,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    fontSize = 19.sp,
                    fontWeight = if (focused) FontWeight.Black else FontWeight.SemiBold,
                )
            }
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {
                Button(onClick = onClick) {
                    Text("Reproducir")
                }
                Button(onClick = onMarkWatched) {
                    Text("Visto")
                }
            }
        }
    }
}

@Composable
private fun EmptyShelf(text: String) {
    Text(text, color = Color(0xFFA6B0C3), modifier = Modifier.padding(8.dp))
}

@Composable
private fun Panel(title: String, subtitle: String? = null, modifier: Modifier = Modifier, content: @Composable ColumnScope.() -> Unit) {
    Card(
        modifier = modifier,
        shape = RoundedCornerShape(22.dp),
        colors = CardDefaults.cardColors(containerColor = Color(0xE60B1220)),
        border = BorderStroke(1.dp, Color(0xFF2B3E61)),
    ) {
        Column(modifier = Modifier.padding(20.dp)) {
            Text(title, color = Color.White, fontWeight = FontWeight.Black, fontSize = 27.sp, lineHeight = 31.sp)
            subtitle?.let {
                Text(it, color = Color(0xFFC3CBE0), fontSize = 15.sp, lineHeight = 20.sp)
            }
            Spacer(Modifier.size(14.dp))
            content()
        }
    }
}

private fun KeyEvent.isSelectRelease(): Boolean {
    return type in setOf(KeyEventType.KeyDown, KeyEventType.KeyUp) && key in setOf(
        Key.DirectionCenter,
        Key.Enter,
        Key.NumPadEnter,
        Key.Spacebar,
        Key.Unknown,
    )
}

private fun formatWatchTime(milliseconds: Long): String {
    val totalSeconds = (milliseconds / 1_000).coerceAtLeast(0)
    val hours = totalSeconds / 3_600
    val minutes = (totalSeconds % 3_600) / 60
    val seconds = totalSeconds % 60
    return if (hours > 0) {
        "%d:%02d:%02d".format(hours, minutes, seconds)
    } else {
        "%d:%02d".format(minutes, seconds)
    }
}
