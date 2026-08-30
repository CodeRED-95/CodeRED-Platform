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
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items as gridItems
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
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
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

private enum class TvSection(val label: String) {
    Home("Inicio"),
    Continue("Continuar"),
    Favorites("Favoritos"),
    Directory("Directorio"),
    Premieres("Estrenos"),
    Top("Top"),
    Schedule("Programacion"),
    Watched("Vistos"),
    History("Historial"),
}

@Composable
private fun AnimeTvApp(viewModel: AnimeTvViewModel = viewModel()) {
    val state by viewModel.state.collectAsState()
    val context = LocalContext.current
    val focusManager = LocalFocusManager.current
    var activeSection by remember { mutableStateOf(TvSection.Home) }
    var directoryFilter by remember { mutableStateOf("") }
    var directoryStatus by remember { mutableStateOf<String?>(null) }
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
                .padding(horizontal = 28.dp, vertical = 22.dp),
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
                HomeShell(
                    state = state,
                    viewModel = viewModel,
                    activeSection = activeSection,
                    onSectionSelected = { activeSection = it },
                    directoryFilter = directoryFilter,
                    onDirectoryFilterChange = { directoryFilter = it },
                    directoryStatus = directoryStatus,
                    onDirectoryStatusChange = { directoryStatus = it },
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
private fun HomeShell(
    state: AnimeTvState,
    viewModel: AnimeTvViewModel,
    activeSection: TvSection,
    onSectionSelected: (TvSection) -> Unit,
    directoryFilter: String,
    onDirectoryFilterChange: (String) -> Unit,
    directoryStatus: String?,
    onDirectoryStatusChange: (String?) -> Unit,
    modifier: Modifier = Modifier,
) {
    Row(
        modifier = modifier,
        horizontalArrangement = Arrangement.spacedBy(18.dp),
    ) {
        SideMenu(
            state = state,
            activeSection = activeSection,
            onSectionSelected = onSectionSelected,
            modifier = Modifier
                .width(196.dp)
                .fillMaxHeight(),
        )

        SectionContent(
            state = state,
            viewModel = viewModel,
            activeSection = activeSection,
            onSectionSelected = onSectionSelected,
            directoryFilter = directoryFilter,
            onDirectoryFilterChange = onDirectoryFilterChange,
            directoryStatus = directoryStatus,
            onDirectoryStatusChange = onDirectoryStatusChange,
            modifier = Modifier
                .weight(1f)
                .fillMaxHeight(),
        )
    }
}

@Composable
private fun SideMenu(
    state: AnimeTvState,
    activeSection: TvSection,
    onSectionSelected: (TvSection) -> Unit,
    modifier: Modifier = Modifier,
) {
    val counts = mapOf(
        TvSection.Continue to state.continueWatching.size,
        TvSection.Favorites to state.favorites.size,
        TvSection.Directory to directoryPool(state).size,
        TvSection.Premieres to state.premieres.size,
        TvSection.Top to state.top.size,
        TvSection.Schedule to state.schedule.size,
        TvSection.Watched to state.watchedEpisodes.size,
        TvSection.History to state.history.size,
    )

    Panel(title = "CodeRED TV", subtitle = "Anime para mando", modifier = modifier) {
        Column(verticalArrangement = Arrangement.spacedBy(9.dp)) {
            TvSection.values().forEach { section ->
                MenuButton(
                    label = section.label,
                    count = counts[section],
                    selected = section == activeSection,
                    onClick = { onSectionSelected(section) },
                )
            }
        }
    }
}

@Composable
private fun MenuButton(label: String, count: Int?, selected: Boolean, onClick: () -> Unit) {
    val interactionSource = remember { MutableInteractionSource() }
    val focused by interactionSource.collectIsFocusedAsState()
    val active = selected || focused

    Card(
        onClick = onClick,
            modifier = Modifier
                .fillMaxWidth()
            .height(44.dp)
            .graphicsLayer {
                scaleX = if (focused) 1.035f else 1f
                scaleY = if (focused) 1.035f else 1f
            },
        shape = RoundedCornerShape(15.dp),
        colors = CardDefaults.cardColors(containerColor = if (active) Color(0xFF21304D) else Color.Transparent),
        border = BorderStroke(if (active) 2.dp else 1.dp, if (active) Color(0xFFFF2E63) else Color(0xFF253553)),
        interactionSource = interactionSource,
    ) {
        Row(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 14.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                text = label,
                color = Color.White,
                fontSize = 14.sp,
                fontWeight = if (active) FontWeight.Black else FontWeight.SemiBold,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            count?.takeIf { it > 0 }?.let {
                Text(
                    text = it.toString(),
                    color = if (active) Color(0xFFFFB4C8) else Color(0xFFA6B0C3),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                )
            }
        }
    }
}

@Composable
private fun SectionContent(
    state: AnimeTvState,
    viewModel: AnimeTvViewModel,
    activeSection: TvSection,
    onSectionSelected: (TvSection) -> Unit,
    directoryFilter: String,
    onDirectoryFilterChange: (String) -> Unit,
    directoryStatus: String?,
    onDirectoryStatusChange: (String?) -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier,
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Header(
            state = state,
            viewModel = viewModel,
            onSearch = {
                onSectionSelected(TvSection.Directory)
                viewModel.search()
            },
        )

        state.message?.let { message ->
            Text(
                text = message,
                color = Color(0xFFFFB4C8),
                fontWeight = FontWeight.SemiBold,
                fontSize = 14.sp,
            )
        }

        when (activeSection) {
            TvSection.Home -> HomeDashboard(
                state = state,
                viewModel = viewModel,
                modifier = Modifier.weight(1f),
            )
            TvSection.Continue -> ProgressPage(
                title = "Continuar viendo",
                subtitle = "Retoma exactamente donde dejaste cada capitulo.",
                items = state.continueWatching,
                emptyText = "Todavia no hay reproducciones guardadas.",
                onSelect = viewModel::resume,
                badge = { "Episodio ${it.episodeNumber} - ${formatWatchTime(it.positionMs)}" },
                modifier = Modifier.weight(1f),
            )
            TvSection.Favorites -> AnimeGridPanel(
                title = "Animes favoritos",
                subtitle = "Tu lista guardada en este Android TV.",
                items = state.favorites,
                selected = state.selectedAnime,
                onSelect = viewModel::selectAnime,
                modifier = Modifier.weight(1f),
            )
            TvSection.Directory -> DirectoryPanel(
                state = state,
                selected = state.selectedAnime,
                filter = directoryFilter,
                onFilterChange = onDirectoryFilterChange,
                selectedStatus = directoryStatus,
                onStatusChange = onDirectoryStatusChange,
                onSelect = viewModel::selectAnime,
                modifier = Modifier.weight(1f),
            )
            TvSection.Premieres -> AnimeGridPanel(
                title = "Estrenos",
                subtitle = "Ultimos episodios detectados en JkAnime.",
                items = state.premieres,
                selected = state.selectedAnime,
                onSelect = viewModel::selectAnime,
                modifier = Modifier.weight(1f),
            )
            TvSection.Top -> AnimeGridPanel(
                title = "Top",
                subtitle = "Titulos destacados para entrar rapido.",
                items = state.top,
                selected = state.selectedAnime,
                onSelect = viewModel::selectAnime,
                modifier = Modifier.weight(1f),
            )
            TvSection.Schedule -> AnimeGridPanel(
                title = "Programacion",
                subtitle = "Salidas de capitulos anunciadas por JkAnime.",
                items = state.schedule,
                selected = state.selectedAnime,
                onSelect = viewModel::selectAnime,
                modifier = Modifier.weight(1f),
            )
            TvSection.Watched -> ProgressPage(
                title = "Capitulos vistos",
                subtitle = "Episodios marcados como completados.",
                items = state.watchedEpisodes,
                emptyText = "Aun no hay capitulos vistos.",
                onSelect = viewModel::resume,
                badge = { "Visto" },
                modifier = Modifier.weight(1f),
            )
            TvSection.History -> ProgressPage(
                title = "Historial",
                subtitle = "Tus reproducciones recientes.",
                items = state.history,
                emptyText = "El historial aparecera cuando reproduzcas un capitulo.",
                onSelect = viewModel::resume,
                badge = { "Episodio ${it.episodeNumber}" },
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun HomeDashboard(state: AnimeTvState, viewModel: AnimeTvViewModel, modifier: Modifier = Modifier) {
    LazyColumn(
        modifier = modifier,
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        if (state.continueWatching.isNotEmpty()) {
            item {
                ContinueWatchingShelf(
                    items = state.continueWatching,
                    onSelect = viewModel::resume,
                    autoFocusFirst = state.selectedAnime == null && state.stream == null,
                )
            }
        }

        if (state.premieres.isNotEmpty()) {
            item {
                AnimeShelf(
                    title = "Estrenos",
                    subtitle = "Lo mas reciente detectado.",
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
                    subtitle = "Accesos rapidos a destacados.",
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
    }
}

@Composable
private fun ProgressPage(
    title: String,
    subtitle: String,
    items: List<WatchProgress>,
    emptyText: String,
    onSelect: (WatchProgress) -> Unit,
    badge: (WatchProgress) -> String,
    modifier: Modifier = Modifier,
) {
    Panel(title = title, subtitle = subtitle, modifier = modifier) {
        if (items.isEmpty()) {
            EmptyShelf(emptyText)
            return@Panel
        }

        LazyColumn(contentPadding = PaddingValues(4.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            items(items, key = { "${it.anime.id}:${it.episodeNumber}:$title" }) { progress ->
                ProgressCard(progress = progress, onClick = { onSelect(progress) }, badge = badge(progress))
            }
        }
    }
}

@Composable
private fun DirectoryPanel(
    state: AnimeTvState,
    selected: Anime?,
    filter: String,
    onFilterChange: (String) -> Unit,
    selectedStatus: String?,
    onStatusChange: (String?) -> Unit,
    onSelect: (Anime) -> Unit,
    modifier: Modifier = Modifier,
) {
    val allItems = directoryPool(state)
    val statuses = allItems.mapNotNull { it.status?.takeIf(String::isNotBlank) }.distinct().take(5)
    val visibleItems = filterDirectory(allItems, filter, selectedStatus)

    Panel(title = "Directorio de animes", subtitle = "Cuadricula compacta con filtros y busqueda local.", modifier = modifier) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            OutlinedTextField(
                value = filter,
                onValueChange = onFilterChange,
                singleLine = true,
                label = { Text("Filtrar directorio") },
                modifier = Modifier.weight(1f),
            )
            FilterPill("Todos", selectedStatus == null) { onStatusChange(null) }
            statuses.forEach { status ->
                FilterPill(status, selectedStatus == status) { onStatusChange(status) }
            }
        }

        if (state.results.isNotEmpty()) {
            Spacer(Modifier.size(8.dp))
            AnimeGrid(
                items = filterDirectory(state.results, filter, selectedStatus = null),
                selected = selected,
                onSelect = onSelect,
                emptyText = "La busqueda no devolvio resultados visibles.",
                modifier = Modifier
                    .fillMaxWidth()
                    .height(260.dp),
            )
            Spacer(Modifier.size(10.dp))
            Text("Catalogo", color = Color(0xFFFFB4C8), fontWeight = FontWeight.Black, fontSize = 16.sp)
        }

        AnimeGrid(
            items = visibleItems,
            selected = selected,
            onSelect = onSelect,
            emptyText = "No hay animes con ese filtro.",
            modifier = Modifier
                .fillMaxWidth()
                .weight(1f),
        )
    }
}

@Composable
private fun FilterPill(label: String, selected: Boolean, onClick: () -> Unit) {
    Card(
        onClick = onClick,
        shape = RoundedCornerShape(999.dp),
        colors = CardDefaults.cardColors(containerColor = if (selected) Color(0xFFE11D48) else Color(0xFF151D2D)),
        border = BorderStroke(1.dp, if (selected) Color(0xFFFFB4C8) else Color(0xFF2E3A51)),
    ) {
        Text(
            text = label,
            color = Color.White,
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(horizontal = 13.dp, vertical = 9.dp),
        )
    }
}

@Composable
private fun AnimeGridPanel(
    title: String,
    subtitle: String,
    items: List<Anime>,
    selected: Anime?,
    onSelect: (Anime) -> Unit,
    modifier: Modifier = Modifier,
) {
    Panel(title = title, subtitle = subtitle, modifier = modifier) {
        AnimeGrid(
            items = items,
            selected = selected,
            onSelect = onSelect,
            emptyText = "No hay contenido disponible en esta seccion.",
            modifier = Modifier
                .fillMaxWidth()
                .weight(1f),
        )
    }
}

@Composable
private fun AnimeGrid(
    items: List<Anime>,
    selected: Anime?,
    onSelect: (Anime) -> Unit,
    emptyText: String,
    modifier: Modifier = Modifier,
) {
    if (items.isEmpty()) {
        EmptyShelf(emptyText)
        return
    }

    LazyVerticalGrid(
        columns = GridCells.Adaptive(146.dp),
        modifier = modifier,
        contentPadding = PaddingValues(4.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        gridItems(items, key = { it.id }) { anime ->
            CompactAnimeCard(
                anime = anime,
                selected = anime.id == selected?.id,
                onClick = { onSelect(anime) },
            )
        }
    }
}

@Composable
private fun CompactAnimeCard(anime: Anime, selected: Boolean, onClick: () -> Unit) {
    val interactionSource = remember { MutableInteractionSource() }
    val focused by interactionSource.collectIsFocusedAsState()
    val active = selected || focused

    Card(
        onClick = onClick,
        modifier = Modifier
            .fillMaxWidth()
            .height(238.dp)
            .onPreviewKeyEvent { event ->
                if (event.isSelectRelease()) {
                    onClick()
                    true
                } else {
                    false
                }
            }
            .graphicsLayer {
                scaleX = if (focused) 1.045f else 1f
                scaleY = if (focused) 1.045f else 1f
                shadowElevation = if (focused) 14f else 3f
            },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = if (active) Color(0xFF1E2A44) else Color(0xFF101827)),
        border = BorderStroke(if (active) 2.dp else 1.dp, if (active) Color(0xFFFF2E63) else Color(0xFF2B3A55)),
        interactionSource = interactionSource,
    ) {
        Column(modifier = Modifier.padding(9.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(165.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(Color(0xFF182235)),
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
                                0.48f to Color.Transparent,
                                0.86f to Color(0xCC05070D),
                                1f to Color(0xF205070D),
                            ),
                        ),
                )
            }
            Text(
                text = anime.title,
                color = Color.White,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
                fontWeight = FontWeight.Black,
                fontSize = 14.sp,
                lineHeight = 17.sp,
            )
            Text(
                text = anime.episodeCount?.let { "$it eps" } ?: anime.status ?: "Disponible",
                color = if (active) Color(0xFFFFC1D0) else Color(0xFFC3CBE0),
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                fontWeight = FontWeight.SemiBold,
                fontSize = 11.sp,
            )
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
private fun Header(state: AnimeTvState, viewModel: AnimeTvViewModel, onSearch: () -> Unit = viewModel::search) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(16.dp),
        verticalAlignment = Alignment.Bottom,
    ) {
        Column(modifier = Modifier.width(300.dp)) {
            Text(
                "CodeRED Anime TV",
                color = Color.White,
                fontSize = 28.sp,
                lineHeight = 31.sp,
                fontWeight = FontWeight.Black,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(
                "Explora, guarda y reproduce anime desde una interfaz para mando.",
                color = Color(0xFFC3CBE0),
                fontSize = 14.sp,
                lineHeight = 19.sp,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
        }

        OutlinedTextField(
            value = state.query,
            onValueChange = viewModel::updateQuery,
            singleLine = true,
            label = { Text("Buscar anime") },
            modifier = Modifier.weight(1f),
        )
        Button(
            onClick = onSearch,
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
        horizontalArrangement = Arrangement.spacedBy(20.dp),
    ) {
        Column(
            modifier = Modifier
                .width(330.dp)
                .fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            Button(onClick = onBack) {
                Text("Volver")
            }

            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(472.dp)
                    .clip(RoundedCornerShape(22.dp))
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
                    fontSize = 17.sp,
                    modifier = Modifier
                        .align(Alignment.BottomStart)
                        .padding(18.dp),
                )
            }
        }

        LazyColumn(
            modifier = Modifier
                .weight(1f)
                .fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(16.dp),
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
                        fontSize = 15.sp,
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
            fontSize = 15.sp,
            lineHeight = 21.sp,
            maxLines = 4,
            overflow = TextOverflow.Ellipsis,
        )
        Spacer(Modifier.size(10.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            DetailPill(anime.status ?: "Estado no disponible")
            DetailPill(anime.episodeCount?.let { "$it episodios publicados" } ?: "Episodios en vivo")
            DetailPill("Fuente: JkAnime")
        }
        Spacer(Modifier.size(12.dp))
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
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold,
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 7.dp),
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
    Panel(title = title, subtitle = subtitle, modifier = modifier.height(314.dp)) {
        if (items.isEmpty()) {
            EmptyShelf("Cargando animes...")
            return@Panel
        }

        LazyRow(contentPadding = PaddingValues(6.dp), horizontalArrangement = Arrangement.spacedBy(13.dp)) {
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
    Panel(title = "Continuar viendo", subtitle = "Retoma desde el ultimo episodio reproducido.", modifier = modifier.height(218.dp)) {
        LazyRow(contentPadding = PaddingValues(6.dp), horizontalArrangement = Arrangement.spacedBy(13.dp)) {
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
    Panel(title = "Mas vistos", subtitle = "Ranking local segun lo que reproduces en este Android TV.", modifier = modifier.height(218.dp)) {
        LazyRow(contentPadding = PaddingValues(6.dp), horizontalArrangement = Arrangement.spacedBy(13.dp)) {
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
    Panel(title = title, subtitle = subtitle, modifier = modifier.height(218.dp)) {
        LazyRow(contentPadding = PaddingValues(6.dp), horizontalArrangement = Arrangement.spacedBy(13.dp)) {
            items(items, key = { "${it.anime.id}:${it.episodeNumber}:$title" }) { progress ->
                ProgressCard(progress = progress, onClick = { onSelect(progress) }, badge = badge(progress))
            }
        }
    }
}

@Composable
private fun ResultsPanel(results: List<Anime>, selected: Anime?, onSelect: (Anime) -> Unit, modifier: Modifier = Modifier) {
    Panel(title = "Resultados", subtitle = "Selecciona un titulo para cargar episodios.", modifier = modifier.height(314.dp)) {
        LazyRow(contentPadding = PaddingValues(6.dp), horizontalArrangement = Arrangement.spacedBy(13.dp)) {
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
            .width(174.dp)
            .height(246.dp)
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
                scaleX = if (focused) 1.045f else 1f
                scaleY = if (focused) 1.045f else 1f
                shadowElevation = if (focused) 14f else 3f
            },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = if (active) Color(0xFF1E2A44) else Color(0xFF101827)),
        border = BorderStroke(if (active) 2.dp else 1.dp, if (active) Color(0xFFFF2E63) else Color(0xFF2B3A55)),
        interactionSource = interactionSource,
    ) {
        Column(modifier = Modifier.padding(9.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(178.dp)
                    .background(Color(0xFF182235))
                    .clip(RoundedCornerShape(12.dp)),
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
                    fontSize = 14.sp,
                    lineHeight = 17.sp,
                    modifier = Modifier
                        .align(Alignment.BottomStart)
                        .padding(9.dp),
                )
            }
            Text(
                anime.episodeCount?.let { "$it episodios" } ?: anime.status ?: "Disponible",
                color = if (active) Color(0xFFFFC1D0) else Color(0xFFC3CBE0),
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                fontWeight = FontWeight.SemiBold,
                fontSize = 11.sp,
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
            .width(280.dp)
            .height(150.dp)
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
                scaleX = if (focused) 1.035f else 1f
                scaleY = if (focused) 1.035f else 1f
                shadowElevation = if (focused) 12f else 3f
            },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = if (focused) Color(0xFF1E2A44) else Color(0xFF111827)),
        border = BorderStroke(if (focused) 2.dp else 1.dp, if (focused) Color(0xFFFF2E63) else Color(0xFF2E3A51)),
        interactionSource = interactionSource,
    ) {
        Row(modifier = Modifier.padding(10.dp), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            AsyncImage(
                model = progress.anime.posterUrl,
                contentDescription = progress.anime.title,
                contentScale = ContentScale.Crop,
                modifier = Modifier
                    .width(86.dp)
                    .height(128.dp)
                    .background(Color(0xFF182235))
                    .clip(RoundedCornerShape(14.dp)),
            )
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(progress.anime.title, color = Color.White, maxLines = 2, overflow = TextOverflow.Ellipsis, fontWeight = FontWeight.ExtraBold, fontSize = 15.sp, lineHeight = 18.sp)
                Text(badge, color = Color(0xFFFFB4C8), fontWeight = FontWeight.SemiBold, fontSize = 12.sp)
                if (progress.positionMs > 0L) {
                    Text(
                        "Continua en ${formatWatchTime(progress.positionMs)}",
                        color = Color(0xFFD8DFF2),
                        fontWeight = FontWeight.Bold,
                        fontSize = 12.sp,
                    )
                }
                Text(progress.episodeTitle, color = Color(0xFFC3CBE0), maxLines = 2, overflow = TextOverflow.Ellipsis, fontSize = 12.sp, lineHeight = 15.sp)
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
    Panel(title = "Capitulos", subtitle = anime?.let { "Lista detectada para ${it.title}." } ?: "Elige un capitulo para reproducir.", modifier = modifier.height(650.dp)) {
        if (episodes.isEmpty()) {
            EmptyShelf("Cargando capitulos o el proveedor todavia no publico episodios para este anime.")
            return@Panel
        }

        LazyColumn(contentPadding = PaddingValues(5.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
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
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            AsyncImage(
                model = episode.thumbnailUrl,
                contentDescription = episode.title,
                contentScale = ContentScale.Crop,
                modifier = Modifier
                    .width(96.dp)
                    .height(54.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .background(Color(0xFF101827)),
            )
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Episodio ${episode.number}",
                    color = if (focused) Color(0xFFFFB4C8) else Color(0xFFC3CBE0),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                )
                Text(
                    text = episode.title,
                    color = Color.White,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    fontSize = 16.sp,
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
        Column(modifier = Modifier.padding(16.dp)) {
            Text(title, color = Color.White, fontWeight = FontWeight.Black, fontSize = 22.sp, lineHeight = 26.sp)
            subtitle?.let {
                Text(it, color = Color(0xFFC3CBE0), fontSize = 13.sp, lineHeight = 18.sp)
            }
            Spacer(Modifier.size(10.dp))
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

private fun directoryPool(state: AnimeTvState): List<Anime> {
    return (state.directory + state.newlyAdded + state.recommended + state.premieres + state.top + state.favorites)
        .distinctBy { it.id }
        .sortedBy { it.title.lowercase() }
}

private fun filterDirectory(items: List<Anime>, query: String, selectedStatus: String?): List<Anime> {
    val needle = query.trim().lowercase()
    return items.filter { anime ->
        val matchesText = needle.isBlank() ||
            anime.title.lowercase().contains(needle) ||
            anime.slug.lowercase().contains(needle)
        val matchesStatus = selectedStatus == null || anime.status == selectedStatus
        matchesText && matchesStatus
    }
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
