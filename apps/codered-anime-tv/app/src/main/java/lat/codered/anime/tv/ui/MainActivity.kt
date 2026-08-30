package lat.codered.anime.tv.ui

import android.os.Bundle
import android.content.Intent
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.activity.compose.BackHandler
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
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
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.Favorite
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.List
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.ThumbUp
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.zIndex
import androidx.lifecycle.viewmodel.compose.viewModel
import coil3.compose.AsyncImage
import lat.codered.anime.tv.domain.Anime
import lat.codered.anime.tv.domain.Episode
import lat.codered.anime.tv.domain.WatchProgress
import lat.codered.anime.tv.ui.components.EmptyState
import lat.codered.anime.tv.ui.components.EpisodeRow
import lat.codered.anime.tv.ui.components.GlassPanel
import lat.codered.anime.tv.ui.components.LoadingBadge
import lat.codered.anime.tv.ui.components.Pill
import lat.codered.anime.tv.ui.components.PosterCard
import lat.codered.anime.tv.ui.components.ProgressCard
import lat.codered.anime.tv.ui.components.SectionHeader
import lat.codered.anime.tv.ui.components.TvButton
import lat.codered.anime.tv.ui.components.TvChip
import lat.codered.anime.tv.ui.theme.AmbientBackground
import lat.codered.anime.tv.ui.theme.AmbientGlow
import lat.codered.anime.tv.ui.theme.AnimeColors
import lat.codered.anime.tv.ui.theme.AnimeShapes
import lat.codered.anime.tv.ui.theme.AnimeTvTheme
import lat.codered.anime.tv.ui.theme.AnimeType
import lat.codered.anime.tv.ui.theme.rememberTvFocusState
import lat.codered.anime.tv.ui.theme.tvFocusScale

/** Margen de seguridad para pantallas con overscan. */
private val SafeHorizontal = 44.dp
private val SafeVertical = 26.dp

/** Anchos del menu lateral: contraido deja solo la inicial de cada seccion. */
private val RailCollapsed = 58.dp
private val RailExpanded = 210.dp
private val RailGap = 20.dp

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            AnimeTvTheme {
                AnimeTvApp()
            }
        }
    }
}

private enum class TvSection(val label: String, val icon: ImageVector) {
    Home("Inicio", Icons.Filled.Home),
    Continue("Continuar", Icons.Filled.PlayArrow),
    Favorites("Favoritos", Icons.Filled.Favorite),
    Directory("Directorio", Icons.Filled.List),
    Premieres("Estrenos", Icons.Filled.Star),
    Top("Top", Icons.Filled.ThumbUp),
    Schedule("Programacion", Icons.Filled.DateRange),
    Watched("Vistos", Icons.Filled.CheckCircle),
    History("Historial", Icons.Filled.Refresh),
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
        color = AnimeColors.Base,
    ) {
        Box(modifier = Modifier.fillMaxSize().background(AmbientBackground)) {
            Box(modifier = Modifier.fillMaxSize().background(AmbientGlow))

            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(horizontal = SafeHorizontal, vertical = SafeVertical),
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
                    LoadingBadge(modifier = Modifier.align(Alignment.TopEnd))
                }
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
    // El menu se expande solo cuando el foco entra en el y se contrae al salir,
    // que es como se comportan los carriles de navegacion en Android TV.
    var menuExpanded by remember { mutableStateOf(false) }
    val railWidth by animateDpAsState(
        targetValue = if (menuExpanded) RailExpanded else RailCollapsed,
        animationSpec = tween(durationMillis = 180),
        label = "railWidth",
    )

    Box(modifier = modifier) {
        // El contenido reserva siempre el ancho contraido: al expandirse el menu
        // se superpone en lugar de reflujar la parrilla entera.
        Row(modifier = Modifier.fillMaxSize()) {
            Spacer(Modifier.width(RailCollapsed + RailGap))
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

        SideMenu(
            state = state,
            activeSection = activeSection,
            onSectionSelected = onSectionSelected,
            expanded = menuExpanded,
            modifier = Modifier
                .width(railWidth)
                .fillMaxHeight()
                .zIndex(1f)
                .onFocusChanged { menuExpanded = it.hasFocus },
        )
    }
}

@Composable
private fun SideMenu(
    state: AnimeTvState,
    activeSection: TvSection,
    onSectionSelected: (TvSection) -> Unit,
    expanded: Boolean,
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

    GlassPanel(
        modifier = modifier,
        padding = 10.dp,
        shadowElevation = if (expanded) 26.dp else 0.dp,
    ) {
        BrandMark(expanded = expanded)
        Spacer(Modifier.size(16.dp))
        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            TvSection.values().forEach { section ->
                MenuButton(
                    label = section.label,
                    icon = section.icon,
                    count = counts[section],
                    selected = section == activeSection,
                    expanded = expanded,
                    onClick = { onSectionSelected(section) },
                )
            }
        }
    }
}

@Composable
private fun BrandMark(expanded: Boolean) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Box(
            modifier = Modifier
                .size(34.dp)
                .clip(RoundedCornerShape(11.dp))
                .background(Brush.verticalGradient(listOf(AnimeColors.Accent, AnimeColors.AccentDeep))),
            contentAlignment = Alignment.Center,
        ) {
            Text("CR", style = AnimeType.Label, color = Color.White)
        }
        if (expanded) {
            Column {
                Text("CodeRED", style = AnimeType.CardTitle, color = Color.White, maxLines = 1)
                Text("ANIME TV", style = AnimeType.Label, color = AnimeColors.AccentSoft, maxLines = 1)
            }
        }
    }
}

@Composable
private fun MenuButton(
    label: String,
    icon: ImageVector,
    count: Int?,
    selected: Boolean,
    expanded: Boolean,
    onClick: () -> Unit,
) {
    val focus = rememberTvFocusState()
    val active = selected || focus.focused
    val container by animateColorAsState(
        targetValue = when {
            focus.focused -> AnimeColors.SurfaceFocused
            selected -> AnimeColors.SurfaceRaised.copy(alpha = 0.85f)
            else -> Color.Transparent
        },
        animationSpec = tween(140),
        label = "menuContainer",
    )
    val border by animateColorAsState(
        targetValue = if (focus.focused) AnimeColors.Accent else Color.Transparent,
        animationSpec = tween(140),
        label = "menuBorder",
    )
    val iconTint by animateColorAsState(
        targetValue = when {
            focus.focused -> Color.White
            selected -> AnimeColors.Accent
            else -> AnimeColors.TextMuted
        },
        animationSpec = tween(140),
        label = "menuIconTint",
    )

    Surface(
        onClick = onClick,
        modifier = Modifier
            .fillMaxWidth()
            .height(42.dp)
            .tvFocusScale(focus.focused, scale = 1.03f, elevation = 8f),
        shape = AnimeShapes.Control,
        color = container,
        border = BorderStroke(if (focus.focused) 2.dp else 1.dp, border),
        interactionSource = focus.interactionSource,
    ) {
        if (expanded) {
            Row(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(start = 12.dp, end = 12.dp),
                horizontalArrangement = Arrangement.spacedBy(11.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = iconTint,
                    modifier = Modifier.size(19.dp),
                )
                Text(
                    text = label,
                    color = if (active) Color.White else AnimeColors.TextSecondary,
                    style = AnimeType.CardTitle,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f),
                )
                count?.takeIf { it > 0 }?.let {
                    Text(
                        text = it.toString(),
                        color = if (active) AnimeColors.AccentSoft else AnimeColors.TextMuted,
                        style = AnimeType.Label,
                    )
                }
            }
        } else {
            // Contraido: el icono es lo unico legible a distancia, con un
            // subrayado de acento cuando la seccion esta activa.
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Icon(
                    imageVector = icon,
                    contentDescription = label,
                    tint = iconTint,
                    modifier = Modifier.size(21.dp),
                )
                if (selected) {
                    Box(
                        modifier = Modifier
                            .align(Alignment.BottomCenter)
                            .padding(bottom = 4.dp)
                            .size(width = 14.dp, height = 2.dp)
                            .clip(AnimeShapes.Pill)
                            .background(AnimeColors.Accent),
                    )
                }
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
        verticalArrangement = Arrangement.spacedBy(16.dp),
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
            MessageBanner(message)
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
                badge = { "Episodio ${it.episodeNumber}" },
                caption = { "Continua en ${formatWatchTime(it.positionMs)}" },
                modifier = Modifier.weight(1f),
            )
            TvSection.Favorites -> AnimeGridPage(
                title = "Animes favoritos",
                subtitle = "Tu lista guardada en este Android TV.",
                items = state.favorites,
                selected = state.selectedAnime,
                onSelect = viewModel::selectAnime,
                modifier = Modifier.weight(1f),
            )
            TvSection.Directory -> DirectoryPage(
                state = state,
                selected = state.selectedAnime,
                filter = directoryFilter,
                onFilterChange = onDirectoryFilterChange,
                selectedStatus = directoryStatus,
                onStatusChange = onDirectoryStatusChange,
                onSelect = viewModel::selectAnime,
                modifier = Modifier.weight(1f),
            )
            TvSection.Premieres -> AnimeGridPage(
                title = "Estrenos",
                subtitle = "Ultimos episodios detectados en JkAnime.",
                items = state.premieres,
                selected = state.selectedAnime,
                onSelect = viewModel::selectAnime,
                modifier = Modifier.weight(1f),
            )
            TvSection.Top -> AnimeGridPage(
                title = "Top",
                subtitle = "Titulos destacados para entrar rapido.",
                items = state.top,
                selected = state.selectedAnime,
                onSelect = viewModel::selectAnime,
                modifier = Modifier.weight(1f),
            )
            TvSection.Schedule -> SchedulePage(
                title = "Programacion",
                subtitle = "La seccion Programacion de la portada de JkAnime.",
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
                caption = { null },
                modifier = Modifier.weight(1f),
            )
            TvSection.History -> ProgressPage(
                title = "Historial",
                subtitle = "Tus reproducciones recientes.",
                items = state.history,
                emptyText = "El historial aparecera cuando reproduzcas un capitulo.",
                onSelect = viewModel::resume,
                badge = { "Episodio ${it.episodeNumber}" },
                caption = { "${it.playCount} reproducciones" },
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun MessageBanner(message: String) {
    Surface(
        shape = AnimeShapes.Control,
        color = AnimeColors.AccentDeep.copy(alpha = 0.18f),
        border = BorderStroke(1.dp, AnimeColors.Accent.copy(alpha = 0.45f)),
    ) {
        Text(
            text = message,
            style = AnimeType.Meta,
            color = AnimeColors.AccentSoft,
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 10.dp),
        )
    }
}

@Composable
private fun HomeDashboard(state: AnimeTvState, viewModel: AnimeTvViewModel, modifier: Modifier = Modifier) {
    val featured = state.premieres.firstOrNull() ?: state.top.firstOrNull() ?: state.newlyAdded.firstOrNull()

    LazyColumn(
        modifier = modifier,
        verticalArrangement = Arrangement.spacedBy(20.dp),
    ) {
        featured?.let { anime ->
            item {
                FeaturedHero(
                    anime = anime,
                    onSelect = { viewModel.selectAnime(anime) },
                    autoFocus = state.continueWatching.isEmpty() && state.selectedAnime == null && state.stream == null,
                )
            }
        }

        if (state.continueWatching.isNotEmpty()) {
            item {
                ProgressShelf(
                    title = "Continuar viendo",
                    subtitle = "Retoma desde el ultimo episodio reproducido.",
                    items = state.continueWatching,
                    onSelect = viewModel::resume,
                    badge = { "Episodio ${it.episodeNumber}" },
                    caption = { "Continua en ${formatWatchTime(it.positionMs)}" },
                    autoFocusFirst = state.selectedAnime == null && state.stream == null,
                )
            }
        }

        val recentSchedule = state.schedule.todayOrYesterdaySchedule()
        if (recentSchedule.isNotEmpty()) {
            item {
                HomeScheduleShelf(
                    items = recentSchedule,
                    selected = state.selectedAnime,
                    onSelect = viewModel::selectAnime,
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
            )
        }

        if (state.mostViewed.isNotEmpty()) {
            item {
                ProgressShelf(
                    title = "Mas vistos",
                    subtitle = "Ranking local segun lo que reproduces en este Android TV.",
                    items = state.mostViewed,
                    onSelect = viewModel::resume,
                    badge = { "Episodio ${it.episodeNumber}" },
                    caption = { "${it.playCount} vistas" },
                )
            }
        }

        if (state.recommended.isNotEmpty()) {
            item {
                AnimeShelf(
                    title = "Recomendados",
                    subtitle = "Sugerencias publicas de la portada.",
                    items = state.recommended,
                    selected = state.selectedAnime,
                    onSelect = viewModel::selectAnime,
                )
            }
        }
    }
}

@Composable
private fun HomeScheduleShelf(items: List<Anime>, selected: Anime?, onSelect: (Anime) -> Unit) {
    Column(modifier = Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        SectionHeader(
            title = "Programacion reciente",
            subtitle = "Capitulos publicados hoy y ayer en JkAnime.",
        )

        LazyRow(
            contentPadding = PaddingValues(horizontal = 4.dp, vertical = 6.dp),
            horizontalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            items(items, key = { "${it.slug}:${it.scheduleEpisode}:${it.scheduleLabel}:home" }) { anime ->
                ScheduleCard(
                    anime = anime,
                    selected = anime.id == selected?.id,
                    onClick = { onSelect(anime) },
                    modifier = Modifier.width(268.dp),
                )
            }
        }
    }
}

/** Banner destacado: da un punto de entrada visual y llena el vacio superior. */
@Composable
private fun FeaturedHero(anime: Anime, onSelect: () -> Unit, autoFocus: Boolean = false) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(168.dp)
            .clip(AnimeShapes.Panel)
            .background(AnimeColors.Surface),
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
                    Brush.horizontalGradient(
                        0f to AnimeColors.ScrimStrong,
                        0.55f to AnimeColors.ScrimSoft,
                        1f to Color.Transparent,
                    ),
                ),
        )
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.verticalGradient(
                        0f to Color.Transparent,
                        1f to AnimeColors.ScrimStrong,
                    ),
                ),
        )
        Column(
            modifier = Modifier
                .align(Alignment.CenterStart)
                .fillMaxWidth(0.62f)
                .padding(horizontal = 26.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            Text("DESTACADO", style = AnimeType.Label, color = AnimeColors.AccentSoft)
            Text(
                text = anime.title,
                style = AnimeType.Display,
                color = Color.White,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
            anime.description?.takeIf { it.isNotBlank() }?.let {
                Text(
                    text = it,
                    style = AnimeType.Meta,
                    color = AnimeColors.TextSecondary,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {
                TvButton(label = "Ver capitulos", onClick = onSelect, autoFocus = autoFocus)
                anime.episodeCount?.let { Pill("$it episodios") }
                anime.status?.takeIf { it.isNotBlank() }?.let { Pill(it) }
            }
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
    caption: (WatchProgress) -> String?,
    modifier: Modifier = Modifier,
) {
    Column(modifier = modifier, verticalArrangement = Arrangement.spacedBy(14.dp)) {
        SectionHeader(title = title, subtitle = subtitle)

        if (items.isEmpty()) {
            EmptyState(emptyText)
            return@Column
        }

        LazyVerticalGrid(
            columns = GridCells.Adaptive(340.dp),
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(bottom = 12.dp),
            horizontalArrangement = Arrangement.spacedBy(14.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            gridItems(items, key = { "${it.anime.id}:${it.episodeNumber}:$title" }) { progress ->
                ProgressCard(
                    progress = progress,
                    onClick = { onSelect(progress) },
                    badge = badge(progress),
                    caption = caption(progress),
                    width = null,
                )
            }
        }
    }
}

@Composable
private fun DirectoryPage(
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

    Column(modifier = modifier, verticalArrangement = Arrangement.spacedBy(14.dp)) {
        SectionHeader(
            title = "Directorio de animes",
            subtitle = "${visibleItems.size} titulos disponibles con los filtros actuales.",
        )

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            OutlinedTextField(
                value = filter,
                onValueChange = onFilterChange,
                singleLine = true,
                label = { Text("Filtrar directorio") },
                colors = tvTextFieldColors(),
                shape = AnimeShapes.Control,
                modifier = Modifier.width(280.dp),
            )
            TvChip("Todos", selectedStatus == null) { onStatusChange(null) }
            statuses.forEach { status ->
                TvChip(status, selectedStatus == status) { onStatusChange(status) }
            }
        }

        if (state.results.isNotEmpty()) {
            SectionHeader(title = "Resultados de busqueda", subtitle = "Coincidencias devueltas por el proveedor.")
            AnimeGrid(
                items = filterDirectory(state.results, filter, selectedStatus = null),
                selected = selected,
                onSelect = onSelect,
                emptyText = "La busqueda no devolvio resultados visibles.",
                modifier = Modifier
                    .fillMaxWidth()
                    .height(300.dp),
            )
            SectionHeader(title = "Catalogo", subtitle = "Todo lo detectado en esta sesion.")
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
private fun AnimeGridPage(
    title: String,
    subtitle: String,
    items: List<Anime>,
    selected: Anime?,
    onSelect: (Anime) -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(modifier = modifier, verticalArrangement = Arrangement.spacedBy(14.dp)) {
        SectionHeader(title = title, subtitle = subtitle)
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
private fun SchedulePage(
    title: String,
    subtitle: String,
    items: List<Anime>,
    selected: Anime?,
    onSelect: (Anime) -> Unit,
    modifier: Modifier = Modifier,
) {
    var category by remember { mutableStateOf<String?>(null) }
    val categories = items.mapNotNull { it.scheduleCategory?.takeIf(String::isNotBlank) }.distinct()
    val visibleItems = items.filter { category == null || it.scheduleCategory == category }

    Column(modifier = modifier, verticalArrangement = Arrangement.spacedBy(14.dp)) {
        SectionHeader(title = title, subtitle = subtitle)

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            TvChip("Todos", selected = category == null) { category = null }
            categories.forEach { value ->
                TvChip(value, selected = category == value) { category = value }
            }
        }

        if (visibleItems.isEmpty()) {
            EmptyState("No hay capitulos publicados en esta categoria.", modifier = Modifier.fillMaxWidth())
            return@Column
        }

        LazyVerticalGrid(
            columns = GridCells.Adaptive(260.dp),
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(bottom = 12.dp),
            horizontalArrangement = Arrangement.spacedBy(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            gridItems(visibleItems, key = { "${it.slug}:${it.scheduleEpisode}:${it.scheduleCategory}" }) { anime ->
                ScheduleCard(
                    anime = anime,
                    selected = anime.id == selected?.id,
                    onClick = { onSelect(anime) },
                )
            }
        }
    }
}

@Composable
private fun ScheduleCard(anime: Anime, selected: Boolean, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val focus = rememberTvFocusState()
    val active = selected || focus.focused

    Surface(
        onClick = onClick,
        modifier = modifier
            .fillMaxWidth()
            .height(150.dp)
            .tvFocusScale(focus.focused, scale = 1.045f, elevation = 16f),
        shape = AnimeShapes.Card,
        color = AnimeColors.Surface,
        border = BorderStroke(if (active) 2.dp else 1.dp, if (active) AnimeColors.Accent else AnimeColors.Line),
        interactionSource = focus.interactionSource,
    ) {
        Box(modifier = Modifier.fillMaxSize()) {
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
                            0.15f to Color.Transparent,
                            0.72f to AnimeColors.ScrimSoft,
                            1f to AnimeColors.ScrimStrong,
                        ),
                    ),
            )
            Row(
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(8.dp),
                horizontalArrangement = Arrangement.spacedBy(6.dp),
            ) {
                anime.scheduleEpisode?.let { Badge("Ep $it", AnimeColors.Accent) }
                anime.scheduleLabel?.takeIf { it.isNotBlank() }?.let { Badge(it, AnimeColors.SurfaceRaised.copy(alpha = 0.92f)) }
            }
            Column(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(14.dp),
                verticalArrangement = Arrangement.spacedBy(5.dp),
            ) {
                Text(
                    text = anime.title,
                    style = AnimeType.CardTitle,
                    color = Color.White,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
                Text(
                    text = anime.scheduleCategory ?: "Programacion",
                    style = AnimeType.Label,
                    color = if (active) AnimeColors.AccentSoft else AnimeColors.TextSecondary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}

@Composable
private fun Badge(text: String, color: Color) {
    Surface(
        shape = AnimeShapes.Pill,
        color = color,
    ) {
        Text(
            text = text,
            style = AnimeType.Label,
            color = Color.White,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(horizontal = 9.dp, vertical = 5.dp),
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
        EmptyState(emptyText, modifier = modifier)
        return
    }

    LazyVerticalGrid(
        columns = GridCells.Adaptive(150.dp),
        modifier = modifier,
        contentPadding = PaddingValues(bottom = 12.dp),
        horizontalArrangement = Arrangement.spacedBy(16.dp),
        verticalArrangement = Arrangement.spacedBy(18.dp),
    ) {
        gridItems(items, key = { it.id }) { anime ->
            PosterCard(
                anime = anime,
                selected = anime.id == selected?.id,
                onClick = { onSelect(anime) },
                width = null,
                posterHeight = 200.dp,
            )
        }
    }
}

@Composable
private fun Header(state: AnimeTvState, viewModel: AnimeTvViewModel, onSearch: () -> Unit = viewModel::search) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(18.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                "CodeRED Anime TV",
                color = Color.White,
                style = AnimeType.Display,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(
                "Explora, guarda y reproduce anime desde una interfaz para mando.",
                color = AnimeColors.TextMuted,
                style = AnimeType.Meta,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }

        OutlinedTextField(
            value = state.query,
            onValueChange = viewModel::updateQuery,
            singleLine = true,
            label = { Text("Buscar anime") },
            colors = tvTextFieldColors(),
            shape = AnimeShapes.Control,
            modifier = Modifier.width(300.dp),
        )
        TvButton(label = "Buscar", onClick = onSearch)
    }
}

@Composable
private fun tvTextFieldColors() = OutlinedTextFieldDefaults.colors(
    focusedTextColor = Color.White,
    unfocusedTextColor = AnimeColors.TextPrimary,
    focusedContainerColor = AnimeColors.SurfaceRaised.copy(alpha = 0.9f),
    unfocusedContainerColor = AnimeColors.Surface.copy(alpha = 0.7f),
    cursorColor = AnimeColors.Accent,
    focusedBorderColor = AnimeColors.Accent,
    unfocusedBorderColor = AnimeColors.Line,
    focusedLabelColor = AnimeColors.AccentSoft,
    unfocusedLabelColor = AnimeColors.TextMuted,
)

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
        horizontalArrangement = Arrangement.spacedBy(24.dp),
    ) {
        Column(
            modifier = Modifier
                .width(300.dp)
                .fillMaxHeight(),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            TvButton(label = "Volver", onClick = onBack, primary = false, modifier = Modifier.fillMaxWidth())

            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .weight(1f)
                    .clip(AnimeShapes.Panel)
                    .background(AnimeColors.Surface),
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
                                0.35f to Color.Transparent,
                                1f to AnimeColors.ScrimStrong,
                            ),
                        ),
                )
                Column(
                    modifier = Modifier
                        .align(Alignment.BottomStart)
                        .padding(18.dp),
                    verticalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    Text(
                        text = anime.episodeCount?.let { "$it episodios" } ?: anime.status ?: "Disponible",
                        color = Color.White,
                        style = AnimeType.Title,
                    )
                    Text("Fuente JkAnime", style = AnimeType.Label, color = AnimeColors.AccentSoft)
                }
            }

            TvButton(
                label = if (state.selectedAnimeIsFavorite) "Quitar de favoritos" else "Agregar a favoritos",
                onClick = onToggleFavorite,
                primary = state.selectedAnimeIsFavorite.not(),
                modifier = Modifier.fillMaxWidth(),
            )
        }

        Column(
            modifier = Modifier
                .weight(1f)
                .fillMaxHeight(),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            DetailHero(anime = anime)

            state.message?.let { message ->
                MessageBanner(message)
            }

            SectionHeader(
                title = "Capitulos",
                subtitle = if (state.episodes.isEmpty()) {
                    "Cargando lista desde el proveedor."
                } else {
                    "${state.episodes.size} capitulos detectados."
                },
            )

            if (state.episodes.isEmpty()) {
                EmptyState("Cargando capitulos o el proveedor todavia no publico episodios para este anime.")
            } else {
                LazyColumn(
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(1f),
                    contentPadding = PaddingValues(bottom = 12.dp),
                    verticalArrangement = Arrangement.spacedBy(9.dp),
                ) {
                    items(state.episodes, key = { it.id }) { episode ->
                        EpisodeRow(
                            episode = episode,
                            autoFocus = episode.id == state.episodes.firstOrNull()?.id,
                            onClick = { onPlay(episode) },
                            onMarkWatched = { onMarkWatched(episode) },
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun DetailHero(anime: Anime) {
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(
            text = anime.title,
            style = AnimeType.Display,
            color = Color.White,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
        )
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Pill(anime.status?.takeIf { it.isNotBlank() } ?: "Estado no disponible", tone = AnimeColors.AccentSoft)
            Pill(anime.episodeCount?.let { "$it episodios publicados" } ?: "Episodios en vivo")
            Pill("JkAnime")
        }
        Text(
            text = anime.description?.takeIf { it.isNotBlank() }
                ?: "Metadata detectada desde JkAnime. La lista de episodios se carga en tiempo real desde el proveedor.",
            color = AnimeColors.TextSecondary,
            style = AnimeType.Body,
            maxLines = 3,
            overflow = TextOverflow.Ellipsis,
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
    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        SectionHeader(title = title, subtitle = subtitle)

        if (items.isEmpty()) {
            EmptyState("Cargando animes...")
            return@Column
        }

        LazyRow(
            contentPadding = PaddingValues(horizontal = 4.dp, vertical = 6.dp),
            horizontalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            items(items, key = { it.id }) { anime ->
                PosterCard(
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
private fun ProgressShelf(
    title: String,
    subtitle: String,
    items: List<WatchProgress>,
    onSelect: (WatchProgress) -> Unit,
    badge: (WatchProgress) -> String,
    caption: (WatchProgress) -> String?,
    modifier: Modifier = Modifier,
    autoFocusFirst: Boolean = false,
) {
    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        SectionHeader(title = title, subtitle = subtitle)

        LazyRow(
            contentPadding = PaddingValues(horizontal = 4.dp, vertical = 6.dp),
            horizontalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            items(items, key = { "${it.anime.id}:${it.episodeNumber}:$title" }) { progress ->
                ProgressCard(
                    progress = progress,
                    autoFocus = autoFocusFirst && progress == items.firstOrNull(),
                    onClick = { onSelect(progress) },
                    badge = badge(progress),
                    caption = caption(progress),
                )
            }
        }
    }
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

private fun List<Anime>.todayOrYesterdaySchedule(): List<Anime> {
    return filter { anime ->
        anime.scheduleLabel.equals("Hoy", ignoreCase = true) ||
            anime.scheduleLabel.equals("Ayer", ignoreCase = true)
    }.take(16)
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
