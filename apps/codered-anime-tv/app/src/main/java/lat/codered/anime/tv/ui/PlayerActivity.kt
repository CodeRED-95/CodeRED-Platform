package lat.codered.anime.tv.ui

import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.graphics.drawable.StateListDrawable
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.Gravity
import android.view.KeyEvent
import android.view.View
import android.view.WindowManager
import android.widget.Button
import android.widget.FrameLayout
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.SeekBar
import android.widget.TextView
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.media3.common.MediaItem
import androidx.media3.common.PlaybackException
import androidx.media3.common.Player
import androidx.media3.datasource.DefaultHttpDataSource
import androidx.media3.exoplayer.ExoPlayer
import androidx.media3.exoplayer.source.DefaultMediaSourceFactory
import androidx.media3.session.MediaSession
import androidx.media3.ui.PlayerView
import lat.codered.anime.tv.data.WatchHistoryStore
import lat.codered.anime.tv.domain.Anime
import java.util.Locale

class PlayerActivity : ComponentActivity() {
    private val progressHandler = Handler(Looper.getMainLooper())
    private val controlsHandler = Handler(Looper.getMainLooper())
    private var player: ExoPlayer? = null
    private var mediaSession: MediaSession? = null
    private var topControls: View? = null
    private var bottomControls: View? = null
    private var seekBar: SeekBar? = null
    private var elapsedText: TextView? = null
    private var durationText: TextView? = null
    private var statusText: TextView? = null
    private var playPauseButton: Button? = null
    private var loading: ProgressBar? = null
    private var controlsVisible = true
    private var watchHistoryStore: WatchHistoryStore? = null
    private var playbackAnime: Anime? = null
    private var playbackEpisodeNumber: Int = 0
    private var playbackEpisodeTitle: String = ""
    private var lastProgressSaveAtMs: Long = 0L

    private val hideControlsRunnable = Runnable {
        if (player?.isPlaying == true) {
            setControlsVisible(false)
        }
    }

    private val progressTicker = object : Runnable {
        override fun run() {
            updateProgress()
            progressHandler.postDelayed(this, 1_000)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        window.decorView.systemUiVisibility = (
            View.SYSTEM_UI_FLAG_FULLSCREEN
                or View.SYSTEM_UI_FLAG_HIDE_NAVIGATION
                or View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
                or View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
                or View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
                or View.SYSTEM_UI_FLAG_LAYOUT_STABLE
            )

        val streamUrl = intent.getStringExtra(EXTRA_STREAM_URL)
        if (streamUrl.isNullOrBlank()) {
            closeWithMessage("No se recibio una fuente reproducible.")
            return
        }

        runCatching {
            val exoPlayer = buildPlayer()
            val root = buildPlayerUi(exoPlayer)
            val startPositionMs = intent.getLongExtra(EXTRA_START_POSITION_MS, 0L).coerceAtLeast(0L)

            player = exoPlayer
            watchHistoryStore = WatchHistoryStore(applicationContext)
            playbackAnime = buildPlaybackAnime()
            playbackEpisodeNumber = intent.getIntExtra(EXTRA_EPISODE_NUMBER, 0)
            playbackEpisodeTitle = intent.getStringExtra(EXTRA_EPISODE_TITLE)?.takeIf { it.isNotBlank() }
                ?: intent.getStringExtra(EXTRA_TITLE)?.takeIf { it.isNotBlank() }
                ?: "Episodio"
            mediaSession = runCatching { MediaSession.Builder(this, exoPlayer).build() }.getOrNull()
            setContentView(root)

            exoPlayer.setMediaItem(MediaItem.fromUri(streamUrl))
            exoPlayer.prepare()
            if (startPositionMs > 0L) {
                exoPlayer.seekTo(startPositionMs)
            }
            exoPlayer.playWhenReady = true
            savePlaybackProgress(incrementPlayCount = true)
            progressHandler.post(progressTicker)
            playPauseButton?.requestFocus()
            scheduleControlsAutoHide()
        }.onFailure {
            closeWithMessage("No se pudo iniciar el reproductor.")
        }
    }

    override fun onResume() {
        super.onResume()
        player?.play()
        progressHandler.post(progressTicker)
        scheduleControlsAutoHide()
    }

    override fun onPause() {
        progressHandler.removeCallbacks(progressTicker)
        controlsHandler.removeCallbacks(hideControlsRunnable)
        savePlaybackProgress()
        player?.pause()
        super.onPause()
    }

    override fun onDestroy() {
        progressHandler.removeCallbacks(progressTicker)
        controlsHandler.removeCallbacks(hideControlsRunnable)
        savePlaybackProgress()
        mediaSession?.release()
        player?.release()
        mediaSession = null
        player = null
        super.onDestroy()
    }

    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        val wasHidden = !controlsVisible
        showControlsTemporarily()

        return when (keyCode) {
            KeyEvent.KEYCODE_DPAD_CENTER,
            KeyEvent.KEYCODE_ENTER -> {
                if (wasHidden) {
                    true
                } else {
                    togglePlayback()
                    true
                }
            }
            KeyEvent.KEYCODE_MEDIA_PLAY_PAUSE -> {
                togglePlayback()
                true
            }
            KeyEvent.KEYCODE_DPAD_LEFT -> {
                if (wasHidden) {
                    seekBy(-10_000)
                    true
                } else {
                    super.onKeyDown(keyCode, event)
                }
            }
            KeyEvent.KEYCODE_DPAD_RIGHT -> {
                if (wasHidden) {
                    seekBy(30_000)
                    true
                } else {
                    super.onKeyDown(keyCode, event)
                }
            }
            KeyEvent.KEYCODE_MEDIA_REWIND -> {
                seekBy(-10_000)
                true
            }
            KeyEvent.KEYCODE_MEDIA_FAST_FORWARD -> {
                seekBy(30_000)
                true
            }
            KeyEvent.KEYCODE_DPAD_UP,
            KeyEvent.KEYCODE_DPAD_DOWN -> super.onKeyDown(keyCode, event)
            KeyEvent.KEYCODE_BACK -> {
                finish()
                true
            }
            else -> super.onKeyDown(keyCode, event)
        }
    }

    private fun buildPlayer(): ExoPlayer {
        val requestHeaders = linkedMapOf<String, String>()
        intent.getStringExtra(EXTRA_REFERER)?.takeIf { it.isNotBlank() }?.let { requestHeaders["Referer"] = it }
        intent.getStringExtra(EXTRA_ORIGIN)?.takeIf { it.isNotBlank() }?.let { requestHeaders["Origin"] = it }

        val dataSourceFactory = DefaultHttpDataSource.Factory()
            .setUserAgent("CodeRED-Anime-TV/0.1.0")
            .setDefaultRequestProperties(requestHeaders)

        return ExoPlayer.Builder(this)
            .setSeekBackIncrementMs(10_000)
            .setSeekForwardIncrementMs(30_000)
            .setMediaSourceFactory(DefaultMediaSourceFactory(dataSourceFactory))
            .build()
            .apply {
                addListener(object : Player.Listener {
                    override fun onPlaybackStateChanged(playbackState: Int) {
                        loading?.visibility = if (playbackState == Player.STATE_BUFFERING) View.VISIBLE else View.GONE
                        statusText?.text = when (playbackState) {
                            Player.STATE_BUFFERING -> "Cargando fuente..."
                            Player.STATE_READY -> if (isPlaying) "Reproduciendo" else "Pausado"
                            Player.STATE_ENDED -> "Finalizado"
                            else -> "Preparando"
                        }
                        updatePlayPauseLabel()
                        updateProgress()
                        scheduleControlsAutoHide()
                    }

                    override fun onIsPlayingChanged(isPlaying: Boolean) {
                        statusText?.text = if (isPlaying) "Reproduciendo" else "Pausado"
                        updatePlayPauseLabel()
                        scheduleControlsAutoHide()
                    }

                    override fun onPlayerError(error: PlaybackException) {
                        loading?.visibility = View.GONE
                        statusText?.text = "Fuente no compatible"
                        setControlsVisible(true)
                        Toast.makeText(
                            this@PlayerActivity,
                            "No se pudo reproducir esta fuente.",
                            Toast.LENGTH_LONG,
                        ).show()
                    }
                })
            }
    }

    private fun buildPlayerUi(player: ExoPlayer): FrameLayout {
        val title = intent.getStringExtra(EXTRA_TITLE)?.takeIf { it.isNotBlank() } ?: "CodeRED Anime TV"
        val root = FrameLayout(this).apply {
            setBackgroundColor(Color.BLACK)
            keepScreenOn = true
            isFocusable = true
            isFocusableInTouchMode = true
        }

        root.addView(PlayerView(this).apply {
            this.player = player
            useController = false
            layoutParams = FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                FrameLayout.LayoutParams.MATCH_PARENT,
            )
        })

        topControls = topOverlay(title)
        bottomControls = bottomControls()
        root.addView(topControls)
        root.addView(bottomControls)

        loading = ProgressBar(this).apply {
            isIndeterminate = true
            visibility = View.VISIBLE
            layoutParams = FrameLayout.LayoutParams(dp(72), dp(72), Gravity.CENTER)
        }
        root.addView(loading)

        return root
    }

    private fun topOverlay(title: String): View {
        return LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(dp(56), dp(34), dp(56), dp(26))
            background = verticalScrim(0xF2000000.toInt(), 0x00000000)
            layoutParams = FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                dp(180),
                Gravity.TOP,
            )

            addView(TextView(this@PlayerActivity).apply {
                text = title
                setTextColor(Color.WHITE)
                textSize = 29f
                typeface = Typeface.DEFAULT_BOLD
                maxLines = 2
                layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
            })

            addView(controlButton("Cerrar") { finish() })
        }
    }

    private fun bottomControls(): View {
        return LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(56), dp(54), dp(56), dp(48))
            background = verticalScrim(0x00000000, 0xF2000000.toInt())
            layoutParams = FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                dp(350),
                Gravity.BOTTOM,
            )

            statusText = TextView(this@PlayerActivity).apply {
                text = "Preparando"
                setTextColor(0xFFFFB4C8.toInt())
                textSize = 16f
                typeface = Typeface.DEFAULT_BOLD
            }
            addView(statusText)

            val timeline = LinearLayout(this@PlayerActivity).apply {
                orientation = LinearLayout.HORIZONTAL
                gravity = Gravity.CENTER_VERTICAL
                setPadding(0, dp(16), 0, dp(22))
            }
            elapsedText = timeLabel("0:00")
            durationText = timeLabel("--:--")
            seekBar = SeekBar(this@PlayerActivity).apply {
                max = 1_000
                progress = 0
                layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
                setOnSeekBarChangeListener(object : SeekBar.OnSeekBarChangeListener {
                    override fun onProgressChanged(seekBar: SeekBar?, progress: Int, fromUser: Boolean) {
                        if (!fromUser) return
                        val duration = this@PlayerActivity.player?.duration?.takeIf { it > 0 } ?: return
                        this@PlayerActivity.player?.seekTo(duration * progress / 1_000)
                    }

                    override fun onStartTrackingTouch(seekBar: SeekBar?) = Unit
                    override fun onStopTrackingTouch(seekBar: SeekBar?) = Unit
                })
            }

            timeline.addView(elapsedText)
            timeline.addView(seekBar)
            timeline.addView(durationText)
            addView(timeline)

            val buttons = LinearLayout(this@PlayerActivity).apply {
                orientation = LinearLayout.HORIZONTAL
                gravity = Gravity.CENTER
                dividerPadding = dp(10)
            }
            buttons.addView(controlButton("-10s") { seekBy(-10_000) })
            playPauseButton = controlButton("Pausa") { togglePlayback() }
            buttons.addView(playPauseButton)
            buttons.addView(controlButton("+30s") { seekBy(30_000) })
            buttons.addView(controlButton("Inicio") { this@PlayerActivity.player?.seekTo(0) })
            addView(buttons)
        }
    }

    private fun controlButton(label: String, action: () -> Unit): Button {
        return Button(this).apply {
            text = label
            textSize = 17f
            isAllCaps = false
            setTextColor(Color.WHITE)
            typeface = Typeface.DEFAULT_BOLD
            minWidth = dp(156)
            minHeight = dp(64)
            background = buttonBackground(false)
            stateListAnimator = null
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.WRAP_CONTENT,
                dp(64),
            ).apply {
                marginStart = dp(8)
                marginEnd = dp(8)
            }
            setOnFocusChangeListener { view, hasFocus ->
                view.animate()
                    .scaleX(if (hasFocus) 1.08f else 1f)
                    .scaleY(if (hasFocus) 1.08f else 1f)
                    .setDuration(140)
                    .start()
                view.background = buttonBackground(hasFocus)
            }
            setOnClickListener {
                showControlsTemporarily()
                action()
            }
        }
    }

    private fun timeLabel(value: String): TextView {
        return TextView(this).apply {
            text = value
            setTextColor(0xFFD8DFF2.toInt())
            textSize = 15f
            typeface = Typeface.MONOSPACE
            gravity = Gravity.CENTER
            layoutParams = LinearLayout.LayoutParams(dp(82), LinearLayout.LayoutParams.WRAP_CONTENT)
        }
    }

    private fun togglePlayback() {
        player?.let {
            if (it.isPlaying) it.pause() else it.play()
            updatePlayPauseLabel()
            updateProgress()
            scheduleControlsAutoHide()
        }
    }

    private fun seekBy(deltaMs: Long) {
        player?.let {
            val duration = it.duration.takeIf { value -> value > 0 } ?: Long.MAX_VALUE
            val target = (it.currentPosition + deltaMs).coerceAtLeast(0).coerceAtMost(duration)
            it.seekTo(target)
            updateProgress()
        }
    }

    private fun closeWithMessage(message: String) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show()
        finish()
    }

    private fun showControlsTemporarily() {
        setControlsVisible(true)
        scheduleControlsAutoHide()
    }

    private fun scheduleControlsAutoHide() {
        controlsHandler.removeCallbacks(hideControlsRunnable)
        if (player?.isPlaying == true) {
            controlsHandler.postDelayed(hideControlsRunnable, CONTROLS_HIDE_DELAY_MS)
        } else {
            setControlsVisible(true)
        }
    }

    private fun setControlsVisible(visible: Boolean) {
        if (controlsVisible == visible) return
        controlsVisible = visible
        listOfNotNull(topControls, bottomControls).forEach { controls ->
            controls.animate().cancel()
            controls.visibility = View.VISIBLE
            controls.animate()
                .alpha(if (visible) 1f else 0f)
                .translationY(if (visible) 0f else if (controls == topControls) -dp(20).toFloat() else dp(24).toFloat())
                .setDuration(if (visible) 160 else 260)
                .withEndAction {
                    if (!visible) {
                        controls.visibility = View.INVISIBLE
                    }
                }
                .start()
        }
        if (visible) {
            playPauseButton?.requestFocus()
        }
    }

    private fun updatePlayPauseLabel() {
        playPauseButton?.text = if (player?.isPlaying == true) "Pausa" else "Play"
    }

    private fun updateProgress() {
        val player = player ?: return
        val duration = player.duration.takeIf { it > 0 } ?: 0
        val position = player.currentPosition.coerceAtLeast(0)
        elapsedText?.text = formatTime(position)
        durationText?.text = if (duration > 0) formatTime(duration) else "--:--"
        seekBar?.progress = if (duration > 0) ((position * 1_000) / duration).toInt().coerceIn(0, 1_000) else 0
        if (position - lastProgressSaveAtMs >= PROGRESS_SAVE_INTERVAL_MS) {
            savePlaybackProgress()
        }
    }

    private fun verticalScrim(startColor: Int, endColor: Int): GradientDrawable {
        return GradientDrawable(
            GradientDrawable.Orientation.TOP_BOTTOM,
            intArrayOf(startColor, endColor),
        )
    }

    private fun buttonBackground(focused: Boolean): StateListDrawable {
        val fill = if (focused) 0xFFE11D48.toInt() else 0xA6141A24.toInt()
        val stroke = if (focused) 0xFFFFB4C8.toInt() else 0x66FFFFFF
        return StateListDrawable().apply {
            addState(
                intArrayOf(android.R.attr.state_focused),
                roundedDrawable(0xFFE11D48.toInt(), 0xFFFFB4C8.toInt()),
            )
            addState(intArrayOf(), roundedDrawable(fill, stroke))
        }
    }

    private fun roundedDrawable(fillColor: Int, strokeColor: Int): GradientDrawable {
        return GradientDrawable().apply {
            shape = GradientDrawable.RECTANGLE
            cornerRadius = dp(18).toFloat()
            setColor(fillColor)
            setStroke(dp(1), strokeColor)
        }
    }

    private fun formatTime(milliseconds: Long): String {
        val totalSeconds = milliseconds / 1_000
        val hours = totalSeconds / 3_600
        val minutes = (totalSeconds % 3_600) / 60
        val seconds = totalSeconds % 60
        return if (hours > 0) {
            String.format(Locale.ROOT, "%d:%02d:%02d", hours, minutes, seconds)
        } else {
            String.format(Locale.ROOT, "%d:%02d", minutes, seconds)
        }
    }

    private fun dp(value: Int): Int = (value * resources.displayMetrics.density).toInt()

    private fun buildPlaybackAnime(): Anime? {
        val slug = intent.getStringExtra(EXTRA_ANIME_SLUG)?.takeIf { it.isNotBlank() } ?: return null
        val title = intent.getStringExtra(EXTRA_ANIME_TITLE)?.takeIf { it.isNotBlank() } ?: return null
        return Anime(
            id = intent.getStringExtra(EXTRA_ANIME_ID)?.takeIf { it.isNotBlank() } ?: "jkanime:$slug",
            slug = slug,
            title = title,
            description = intent.getStringExtra(EXTRA_ANIME_DESCRIPTION)?.takeIf { it.isNotBlank() },
            posterUrl = intent.getStringExtra(EXTRA_ANIME_POSTER_URL)?.takeIf { it.isNotBlank() },
            episodeCount = intent.getIntExtra(EXTRA_ANIME_EPISODE_COUNT, 0).takeIf { it > 0 },
            status = intent.getStringExtra(EXTRA_ANIME_STATUS)?.takeIf { it.isNotBlank() },
        )
    }

    private fun savePlaybackProgress(incrementPlayCount: Boolean = false) {
        val anime = playbackAnime ?: return
        val episodeNumber = playbackEpisodeNumber.takeIf { it > 0 } ?: return
        val currentPlayer = player ?: return
        val position = currentPlayer.currentPosition.coerceAtLeast(0L)
        val duration = currentPlayer.duration.takeIf { it > 0 } ?: 0L
        lastProgressSaveAtMs = position
        watchHistoryStore?.updateProgress(
            anime = anime,
            episodeNumber = episodeNumber,
            episodeTitle = playbackEpisodeTitle,
            positionMs = position,
            durationMs = duration,
            incrementPlayCount = incrementPlayCount,
        )
        setResult(RESULT_OK)
    }

    companion object {
        private const val CONTROLS_HIDE_DELAY_MS = 4_000L
        private const val PROGRESS_SAVE_INTERVAL_MS = 5_000L
        const val EXTRA_STREAM_URL = "lat.codered.anime.tv.extra.STREAM_URL"
        const val EXTRA_TITLE = "lat.codered.anime.tv.extra.TITLE"
        const val EXTRA_REFERER = "lat.codered.anime.tv.extra.REFERER"
        const val EXTRA_ORIGIN = "lat.codered.anime.tv.extra.ORIGIN"
        const val EXTRA_START_POSITION_MS = "lat.codered.anime.tv.extra.START_POSITION_MS"
        const val EXTRA_ANIME_ID = "lat.codered.anime.tv.extra.ANIME_ID"
        const val EXTRA_ANIME_SLUG = "lat.codered.anime.tv.extra.ANIME_SLUG"
        const val EXTRA_ANIME_TITLE = "lat.codered.anime.tv.extra.ANIME_TITLE"
        const val EXTRA_ANIME_DESCRIPTION = "lat.codered.anime.tv.extra.ANIME_DESCRIPTION"
        const val EXTRA_ANIME_POSTER_URL = "lat.codered.anime.tv.extra.ANIME_POSTER_URL"
        const val EXTRA_ANIME_EPISODE_COUNT = "lat.codered.anime.tv.extra.ANIME_EPISODE_COUNT"
        const val EXTRA_ANIME_STATUS = "lat.codered.anime.tv.extra.ANIME_STATUS"
        const val EXTRA_EPISODE_NUMBER = "lat.codered.anime.tv.extra.EPISODE_NUMBER"
        const val EXTRA_EPISODE_TITLE = "lat.codered.anime.tv.extra.EPISODE_TITLE"
    }
}
