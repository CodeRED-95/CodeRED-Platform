package lat.codered.anime.tv.ui

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
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
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.media3.common.MediaItem
import androidx.media3.exoplayer.ExoPlayer
import androidx.media3.ui.PlayerView
import coil3.compose.AsyncImage
import lat.codered.anime.tv.domain.Anime
import lat.codered.anime.tv.domain.Episode
import lat.codered.anime.tv.domain.Stream

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
                .padding(32.dp),
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(22.dp)) {
                Header(state, viewModel)

                state.message?.let {
                    Text(
                        text = it,
                        color = Color(0xFFFFB4C8),
                        fontWeight = FontWeight.SemiBold,
                    )
                }

                Row(horizontalArrangement = Arrangement.spacedBy(24.dp)) {
                    ResultsPanel(
                        results = state.results,
                        selected = state.selectedAnime,
                        onSelect = viewModel::selectAnime,
                        modifier = Modifier.weight(1.1f),
                    )

                    EpisodesPanel(
                        anime = state.selectedAnime,
                        episodes = state.episodes,
                        onPlay = viewModel::playEpisode,
                        modifier = Modifier.weight(0.9f),
                    )
                }

                state.stream?.let { stream ->
                    PlayerPanel(
                        episode = state.playingEpisode,
                        stream = stream,
                        modifier = Modifier
                            .fillMaxWidth()
                            .weight(1f),
                    )
                }
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
private fun Header(state: AnimeTvState, viewModel: AnimeTvViewModel) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(18.dp),
        verticalAlignment = Alignment.Bottom,
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text("CodeRED Anime TV", color = Color.White, style = MaterialTheme.typography.headlineLarge, fontWeight = FontWeight.Black)
            Text("Busqueda directa en JkAnime, sin Jellyfin ni Laravel como intermediario.", color = Color(0xFFA6B0C3))
        }

        OutlinedTextField(
            value = state.query,
            onValueChange = viewModel::updateQuery,
            singleLine = true,
            label = { Text("Buscar anime") },
            modifier = Modifier.width(420.dp),
        )
        Button(onClick = viewModel::search) {
            Text("Buscar")
        }
    }
}

@Composable
private fun ResultsPanel(results: List<Anime>, selected: Anime?, onSelect: (Anime) -> Unit, modifier: Modifier = Modifier) {
    Panel(title = "Resultados", modifier = modifier.height(330.dp)) {
        LazyRow(contentPadding = PaddingValues(6.dp), horizontalArrangement = Arrangement.spacedBy(14.dp)) {
            items(results, key = { it.id }) { anime ->
                AnimeCard(anime = anime, selected = anime.id == selected?.id, onClick = { onSelect(anime) })
            }
        }
    }
}

@Composable
private fun AnimeCard(anime: Anime, selected: Boolean, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .width(190.dp)
            .height(286.dp)
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = if (selected) Color(0xFF243047) else Color(0xFF111827)),
        border = BorderStroke(1.dp, if (selected) Color(0xFFE11D48) else Color(0xFF263246)),
    ) {
        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            AsyncImage(
                model = anime.posterUrl,
                contentDescription = anime.title,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(190.dp)
                    .clip(RoundedCornerShape(14.dp)),
            )
            Text(anime.title, color = Color.White, maxLines = 2, overflow = TextOverflow.Ellipsis, fontWeight = FontWeight.Bold)
            anime.episodeCount?.let { Text("$it episodios", color = Color(0xFFA6B0C3)) }
        }
    }
}

@Composable
private fun EpisodesPanel(anime: Anime?, episodes: List<Episode>, onPlay: (Episode) -> Unit, modifier: Modifier = Modifier) {
    Panel(title = anime?.title ?: "Episodios", modifier = modifier.height(330.dp)) {
        if (episodes.isEmpty()) {
            Text("Selecciona un anime para cargar episodios.", color = Color(0xFFA6B0C3), modifier = Modifier.padding(8.dp))
            return@Panel
        }

        LazyColumn(contentPadding = PaddingValues(6.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            items(episodes, key = { it.id }) { episode ->
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { onPlay(episode) },
                    colors = CardDefaults.cardColors(containerColor = Color(0xFF151D2D)),
                    border = BorderStroke(1.dp, Color(0xFF2E3A51)),
                ) {
                    Text(
                        text = "Episodio ${episode.number}  ${episode.title}",
                        color = Color.White,
                        modifier = Modifier.padding(16.dp),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
        }
    }
}

@Composable
private fun PlayerPanel(episode: Episode?, stream: Stream, modifier: Modifier = Modifier) {
    Panel(title = episode?.let { "Reproduciendo episodio ${it.number}" } ?: "Player", modifier = modifier) {
        val context = LocalContext.current
        val player = remember(stream.url) {
            ExoPlayer.Builder(context).build().apply {
                setMediaItem(MediaItem.fromUri(stream.url))
                prepare()
                playWhenReady = true
            }
        }

        DisposableEffect(player) {
            onDispose { player.release() }
        }

        AndroidView(
            factory = { PlayerView(it).apply { this.player = player } },
            modifier = Modifier
                .fillMaxSize()
                .clip(RoundedCornerShape(18.dp)),
        )
    }
}

@Composable
private fun Panel(title: String, modifier: Modifier = Modifier, content: @Composable Column.() -> Unit) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(containerColor = Color(0xCC0B1220)),
        border = BorderStroke(1.dp, Color(0xFF23304A)),
    ) {
        Column(modifier = Modifier.padding(18.dp)) {
            Text(title, color = Color.White, fontWeight = FontWeight.Black, style = MaterialTheme.typography.titleLarge)
            Spacer(Modifier.size(12.dp))
            content()
        }
    }
}
