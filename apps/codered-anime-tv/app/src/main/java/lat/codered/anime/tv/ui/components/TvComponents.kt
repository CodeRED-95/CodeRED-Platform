package lat.codered.anime.tv.ui.components

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.input.key.Key
import androidx.compose.ui.input.key.KeyEvent
import androidx.compose.ui.input.key.KeyEventType
import androidx.compose.ui.input.key.key
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.input.key.type
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import coil3.compose.AsyncImage
import lat.codered.anime.tv.domain.Anime
import lat.codered.anime.tv.domain.Episode
import lat.codered.anime.tv.domain.WatchProgress
import lat.codered.anime.tv.ui.theme.AnimeColors
import lat.codered.anime.tv.ui.theme.AnimeShapes
import lat.codered.anime.tv.ui.theme.AnimeType
import lat.codered.anime.tv.ui.theme.rememberTvFocusState
import lat.codered.anime.tv.ui.theme.requestFocusSoon
import lat.codered.anime.tv.ui.theme.tvFocusScale

/** Reproduce el manejo de tecla OK del mando tal como estaba en la version anterior. */
fun KeyEvent.isSelectRelease(): Boolean {
    return type in setOf(KeyEventType.KeyDown, KeyEventType.KeyUp) && key in setOf(
        Key.DirectionCenter,
        Key.Enter,
        Key.NumPadEnter,
        Key.Spacebar,
        Key.Unknown,
    )
}

/**
 * Encabezado de seccion. Sustituye a las tarjetas con borde que envolvian cada
 * estante: en television el marco extra roba altura y compite con los posters.
 */
@Composable
fun SectionHeader(
    title: String,
    subtitle: String? = null,
    modifier: Modifier = Modifier,
    trailing: @Composable (() -> Unit)? = null,
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Box(
            modifier = Modifier
                .size(width = 4.dp, height = 26.dp)
                .clip(AnimeShapes.Pill)
                .background(
                    Brush.verticalGradient(listOf(AnimeColors.Accent, AnimeColors.AccentDeep)),
                ),
        )
        Column(modifier = Modifier.weight(1f)) {
            Text(title, style = AnimeType.Section, color = AnimeColors.TextPrimary, maxLines = 1, overflow = TextOverflow.Ellipsis)
            subtitle?.let {
                Text(it, style = AnimeType.Meta, color = AnimeColors.TextMuted, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
        }
        trailing?.invoke()
    }
}

/** Contenedor translucido para paginas completas (menu lateral, listados). */
@Composable
fun GlassPanel(
    modifier: Modifier = Modifier,
    padding: Dp = 18.dp,
    shadowElevation: Dp = 0.dp,
    content: @Composable ColumnScope.() -> Unit,
) {
    Surface(
        modifier = modifier,
        shape = AnimeShapes.Panel,
        // Opaco al elevarse: el carril expandido se superpone al contenido.
        color = AnimeColors.Surface.copy(alpha = if (shadowElevation > 0.dp) 0.97f else 0.82f),
        border = BorderStroke(1.dp, AnimeColors.Line),
        shadowElevation = shadowElevation,
        content = {
            Column(modifier = Modifier.padding(padding), content = content)
        },
    )
}

@Composable
fun EmptyState(text: String, modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .clip(AnimeShapes.Card)
            .background(AnimeColors.Surface.copy(alpha = 0.5f))
            .padding(horizontal = 20.dp, vertical = 26.dp),
        contentAlignment = Alignment.CenterStart,
    ) {
        Text(text, style = AnimeType.Body, color = AnimeColors.TextMuted)
    }
}

@Composable
fun Pill(text: String, modifier: Modifier = Modifier, tone: Color = AnimeColors.TextSecondary) {
    Surface(
        modifier = modifier,
        shape = AnimeShapes.Pill,
        color = AnimeColors.SurfaceRaised.copy(alpha = 0.9f),
        border = BorderStroke(1.dp, AnimeColors.Line),
    ) {
        Text(
            text = text,
            style = AnimeType.Meta,
            color = tone,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
        )
    }
}

/** Chip enfocable para filtros. */
@Composable
fun TvChip(label: String, selected: Boolean, modifier: Modifier = Modifier, onClick: () -> Unit) {
    val focus = rememberTvFocusState()
    val active = selected || focus.focused
    val container by animateColorAsState(
        targetValue = when {
            selected -> AnimeColors.AccentDeep
            focus.focused -> AnimeColors.SurfaceFocused
            else -> AnimeColors.SurfaceRaised.copy(alpha = 0.8f)
        },
        animationSpec = tween(140),
        label = "chipContainer",
    )
    val border by animateColorAsState(
        targetValue = if (active) AnimeColors.AccentSoft else AnimeColors.Line,
        animationSpec = tween(140),
        label = "chipBorder",
    )

    Surface(
        onClick = onClick,
        modifier = modifier.tvFocusScale(focus.focused, scale = 1.05f, elevation = 8f),
        shape = AnimeShapes.Pill,
        color = container,
        border = BorderStroke(if (active) 2.dp else 1.dp, border),
        interactionSource = focus.interactionSource,
    ) {
        Text(
            text = label,
            style = AnimeType.Meta,
            color = if (active) Color.White else AnimeColors.TextSecondary,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 10.dp),
        )
    }
}

/** Boton para mando: area amplia, foco evidente y sin el morado de Material. */
@Composable
fun TvButton(
    label: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    primary: Boolean = true,
    autoFocus: Boolean = false,
) {
    val focus = rememberTvFocusState()
    val focusRequester = remember { FocusRequester() }
    val container by animateColorAsState(
        targetValue = when {
            focus.focused -> AnimeColors.Accent
            primary -> AnimeColors.AccentDeep
            else -> AnimeColors.SurfaceRaised
        },
        animationSpec = tween(140),
        label = "buttonContainer",
    )
    val border by animateColorAsState(
        targetValue = if (focus.focused) AnimeColors.AccentSoft else if (primary) Color.Transparent else AnimeColors.Line,
        animationSpec = tween(140),
        label = "buttonBorder",
    )

    LaunchedEffect(autoFocus) {
        if (autoFocus) focusRequester.requestFocusSoon()
    }

    Surface(
        onClick = onClick,
        modifier = modifier
            .focusRequester(focusRequester)
            .tvFocusScale(focus.focused, scale = 1.06f, elevation = 12f),
        shape = AnimeShapes.Control,
        color = container,
        border = BorderStroke(if (focus.focused) 2.dp else 1.dp, border),
        interactionSource = focus.interactionSource,
    ) {
        Text(
            text = label,
            style = AnimeType.CardTitle,
            color = Color.White,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(horizontal = 20.dp, vertical = 13.dp),
        )
    }
}

/** Barra de avance fina, usada en las tarjetas de "continuar viendo". */
@Composable
fun ProgressBarThin(fraction: Float, modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(4.dp)
            .clip(AnimeShapes.Pill)
            .background(Color.White.copy(alpha = 0.16f)),
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth(fraction.coerceIn(0f, 1f))
                .fillMaxHeight()
                .clip(AnimeShapes.Pill)
                .background(Brush.horizontalGradient(listOf(AnimeColors.AccentDeep, AnimeColors.Accent))),
        )
    }
}

/**
 * Tarjeta de poster unificada. Antes convivian dos variantes casi identicas
 * (rejilla y estante) con tipografias y jerarquias distintas.
 */
@Composable
fun PosterCard(
    anime: Anime,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    width: Dp? = 160.dp,
    posterHeight: Dp = 202.dp,
    autoFocus: Boolean = false,
) {
    val focus = rememberTvFocusState()
    val focusRequester = remember { FocusRequester() }
    val active = selected || focus.focused
    val border by animateColorAsState(
        targetValue = when {
            focus.focused -> AnimeColors.Accent
            selected -> AnimeColors.AccentSoft.copy(alpha = 0.7f)
            else -> Color.Transparent
        },
        animationSpec = tween(140),
        label = "posterBorder",
    )

    LaunchedEffect(autoFocus, anime.id) {
        if (autoFocus) focusRequester.requestFocusSoon()
    }

    Column(
        modifier = modifier
            .let { if (width != null) it.width(width) else it.fillMaxWidth() }
            .focusRequester(focusRequester)
            .tvFocusScale(focus.focused, scale = 1.07f, elevation = 20f),
        verticalArrangement = Arrangement.spacedBy(9.dp),
    ) {
        Surface(
            onClick = onClick,
            modifier = Modifier
                .fillMaxWidth()
                .height(posterHeight)
                .onPreviewKeyEvent { event ->
                    if (event.isSelectRelease()) {
                        onClick()
                        true
                    } else {
                        false
                    }
                },
            shape = AnimeShapes.Poster,
            color = AnimeColors.SurfaceRaised,
            border = BorderStroke(if (active) 2.dp else 1.dp, if (active) border else AnimeColors.Line),
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
                                0.45f to Color.Transparent,
                                0.82f to AnimeColors.ScrimSoft,
                                1f to AnimeColors.ScrimStrong,
                            ),
                        ),
                )
                anime.episodeCount?.let { count ->
                    Box(
                        modifier = Modifier
                            .align(Alignment.TopEnd)
                            .padding(8.dp)
                            .clip(AnimeShapes.Pill)
                            .background(AnimeColors.ScrimStrong.copy(alpha = 0.85f))
                            .padding(horizontal = 9.dp, vertical = 4.dp),
                    ) {
                        Text("$count EP", style = AnimeType.Label, color = AnimeColors.AccentSoft)
                    }
                }
                if (focus.focused) {
                    Box(
                        modifier = Modifier
                            .align(Alignment.BottomStart)
                            .padding(10.dp)
                            .clip(AnimeShapes.Pill)
                            .background(AnimeColors.Accent)
                            .padding(horizontal = 12.dp, vertical = 5.dp),
                    ) {
                        Text("VER", style = AnimeType.Label, color = Color.White)
                    }
                }
            }
        }

        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                text = anime.title,
                style = AnimeType.CardTitle,
                color = if (active) Color.White else AnimeColors.TextPrimary,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
            Text(
                text = anime.status?.takeIf { it.isNotBlank() } ?: "Disponible",
                style = AnimeType.Label,
                color = if (active) AnimeColors.AccentSoft else AnimeColors.TextMuted,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
    }
}

/** Tarjeta horizontal de progreso, con miniatura, etiqueta y barra de avance. */
@Composable
fun ProgressCard(
    progress: WatchProgress,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    badge: String = "Episodio ${progress.episodeNumber}",
    caption: String? = null,
    width: Dp? = 296.dp,
    autoFocus: Boolean = false,
) {
    val focus = rememberTvFocusState()
    val focusRequester = remember { FocusRequester() }
    val container by animateColorAsState(
        targetValue = if (focus.focused) AnimeColors.SurfaceFocused else AnimeColors.Surface,
        animationSpec = tween(140),
        label = "progressContainer",
    )
    val border by animateColorAsState(
        targetValue = if (focus.focused) AnimeColors.Accent else AnimeColors.Line,
        animationSpec = tween(140),
        label = "progressBorder",
    )
    val fraction = if (progress.durationMs > 0L) {
        progress.positionMs.toFloat() / progress.durationMs.toFloat()
    } else {
        0f
    }

    LaunchedEffect(autoFocus, progress.anime.id, progress.episodeNumber) {
        if (autoFocus) focusRequester.requestFocusSoon()
    }

    Surface(
        onClick = onClick,
        modifier = modifier
            .let { if (width != null) it.width(width) else it.fillMaxWidth() }
            .height(140.dp)
            .focusRequester(focusRequester)
            .onPreviewKeyEvent { event ->
                if (event.isSelectRelease()) {
                    onClick()
                    true
                } else {
                    false
                }
            }
            .tvFocusScale(focus.focused, scale = 1.04f, elevation = 16f),
        shape = AnimeShapes.Card,
        color = container,
        border = BorderStroke(if (focus.focused) 2.dp else 1.dp, border),
        interactionSource = focus.interactionSource,
    ) {
        Row(
            modifier = Modifier.padding(12.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Box(
                modifier = Modifier
                    .width(78.dp)
                    .fillMaxHeight()
                    .clip(AnimeShapes.Poster)
                    .background(AnimeColors.SurfaceRaised),
            ) {
                AsyncImage(
                    model = progress.anime.posterUrl,
                    contentDescription = progress.anime.title,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize(),
                )
            }
            Column(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxHeight(),
                verticalArrangement = Arrangement.spacedBy(5.dp),
            ) {
                Text(
                    text = progress.anime.title,
                    style = AnimeType.CardTitle,
                    color = Color.White,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
                Text(text = badge, style = AnimeType.Label, color = AnimeColors.AccentSoft, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(
                    text = progress.episodeTitle,
                    style = AnimeType.Meta,
                    color = AnimeColors.TextMuted,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
                Spacer(modifier = Modifier.weight(1f))
                caption?.let {
                    Text(it, style = AnimeType.Label, color = AnimeColors.TextSecondary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
                if (fraction > 0f) {
                    ProgressBarThin(fraction)
                }
            }
        }
    }
}

/** Fila de episodio: numero destacado, miniatura y acciones alineadas a la derecha. */
@Composable
fun EpisodeRow(
    episode: Episode,
    onClick: () -> Unit,
    onMarkWatched: () -> Unit,
    modifier: Modifier = Modifier,
    autoFocus: Boolean = false,
) {
    val focus = rememberTvFocusState()
    val focusRequester = remember { FocusRequester() }
    val container by animateColorAsState(
        targetValue = if (focus.focused) AnimeColors.SurfaceFocused else AnimeColors.Surface.copy(alpha = 0.85f),
        animationSpec = tween(140),
        label = "episodeContainer",
    )
    val border by animateColorAsState(
        targetValue = if (focus.focused) AnimeColors.Accent else AnimeColors.Line,
        animationSpec = tween(140),
        label = "episodeBorder",
    )

    LaunchedEffect(autoFocus, episode.id) {
        if (autoFocus) focusRequester.requestFocusSoon()
    }

    Surface(
        onClick = onClick,
        modifier = modifier
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
            .tvFocusScale(focus.focused, scale = 1.015f, elevation = 10f),
        shape = AnimeShapes.Card,
        color = container,
        border = BorderStroke(if (focus.focused) 2.dp else 1.dp, border),
        interactionSource = focus.interactionSource,
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 11.dp),
            horizontalArrangement = Arrangement.spacedBy(14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                modifier = Modifier
                    .size(width = 46.dp, height = 46.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(
                        if (focus.focused) {
                            Brush.verticalGradient(listOf(AnimeColors.Accent, AnimeColors.AccentDeep))
                        } else {
                            Brush.verticalGradient(listOf(AnimeColors.SurfaceRaised, AnimeColors.SurfaceRaised))
                        },
                    ),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    text = episode.number.toString(),
                    style = AnimeType.CardTitle,
                    color = if (focus.focused) Color.White else AnimeColors.TextSecondary,
                )
            }
            Box(
                modifier = Modifier
                    .width(96.dp)
                    .height(54.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .background(AnimeColors.SurfaceRaised),
            ) {
                AsyncImage(
                    model = episode.thumbnailUrl,
                    contentDescription = episode.title,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize(),
                )
            }
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    text = "EPISODIO ${episode.number}",
                    style = AnimeType.Label,
                    color = if (focus.focused) AnimeColors.AccentSoft else AnimeColors.TextMuted,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                Text(
                    text = episode.title,
                    style = AnimeType.CardTitle,
                    color = Color.White,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {
                TvButton(label = "Reproducir", onClick = onClick)
                TvButton(label = "Visto", onClick = onMarkWatched, primary = false)
            }
        }
    }
}

/** Indicador de carga discreto, en lugar del circulo suelto sobre el contenido. */
@Composable
fun LoadingBadge(modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier,
        shape = AnimeShapes.Pill,
        color = AnimeColors.Surface.copy(alpha = 0.92f),
        border = BorderStroke(1.dp, AnimeColors.Accent.copy(alpha = 0.5f)),
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 10.dp),
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            CircularProgressIndicator(
                modifier = Modifier.size(16.dp),
                color = AnimeColors.Accent,
                strokeWidth = 2.dp,
            )
            Text("Cargando", style = AnimeType.Label, color = AnimeColors.TextSecondary)
        }
    }
}
