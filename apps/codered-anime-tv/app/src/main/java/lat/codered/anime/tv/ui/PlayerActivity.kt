package lat.codered.anime.tv.ui

import android.content.Intent
import android.content.res.ColorStateList
import android.content.res.Configuration
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.ColorFilter
import android.graphics.Paint
import android.graphics.Path
import android.graphics.PixelFormat
import android.graphics.RectF
import android.graphics.Typeface
import android.graphics.drawable.ClipDrawable
import android.graphics.drawable.Drawable
import android.graphics.drawable.GradientDrawable
import android.graphics.drawable.LayerDrawable
import android.graphics.drawable.StateListDrawable
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.GestureDetector
import android.view.Gravity
import android.view.KeyEvent
import android.view.MotionEvent
import android.view.View
import android.view.WindowManager
import android.widget.Button
import android.widget.FrameLayout
import android.widget.ImageButton
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

// Paleta compartida con la interfaz Compose (ui/theme/AnimeTheme.kt).
private val PlayerAccent = 0xFFFF2E63.toInt()
private val PlayerAccentDeep = 0xFFE11D48.toInt()
private val PlayerAccentSoft = 0xFFFFB4C8.toInt()
private val PlayerSurface = 0xCC0D1524.toInt()
private val PlayerLine = 0x33FFFFFF
private val PlayerTextSecondary = 0xFFB4BED4.toInt()

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
    private var playPauseButton: ImageButton? = null
    private var loading: ProgressBar? = null
    private var controlsVisible = true
    // Control que tenia el foco al ocultarse la barra, para devolverlo al volver.
    private var lastFocusedControl: View? = null
    private var watchHistoryStore: WatchHistoryStore? = null
    private var playbackAnime: Anime? = null
    private var playbackEpisodeNumber: Int = 0
    private var playbackEpisodeTitle: String = ""
    private var lastProgressSaveAtMs: Long = 0L
    private var hasPreviousEpisode: Boolean = false
    private var hasNextEpisode: Boolean = false
    // Salto de capitulo solicitado. Viaja en el resultado de la actividad porque
    // resolver la fuente del episodio es tarea del ViewModel, no del reproductor.
    private var pendingEpisodeDelta: Int = 0
    private var autoAdvanceTriggered: Boolean = false

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
        enableCodeRedImmersiveMode()

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
        enableCodeRedImmersiveMode()
        player?.play()
        progressHandler.post(progressTicker)
        scheduleControlsAutoHide()
    }

    override fun onWindowFocusChanged(hasFocus: Boolean) {
        super.onWindowFocusChanged(hasFocus)
        if (hasFocus) enableCodeRedImmersiveMode()
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
            KeyEvent.KEYCODE_MEDIA_NEXT,
            KeyEvent.KEYCODE_MEDIA_SKIP_FORWARD -> {
                if (hasNextEpisode) goToEpisode(1)
                true
            }
            KeyEvent.KEYCODE_MEDIA_PREVIOUS,
            KeyEvent.KEYCODE_MEDIA_SKIP_BACKWARD -> {
                if (hasPreviousEpisode) goToEpisode(-1)
                true
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
                        if (playbackState == Player.STATE_ENDED) {
                            maybeAutoAdvance()
                        }
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
        hasPreviousEpisode = intent.getBooleanExtra(EXTRA_HAS_PREVIOUS, false)
        hasNextEpisode = intent.getBooleanExtra(EXTRA_HAS_NEXT, false)
        val title = intent.getStringExtra(EXTRA_TITLE)?.takeIf { it.isNotBlank() } ?: "CodeRED Anime TV"
        val root = FrameLayout(this).apply {
            setBackgroundColor(Color.BLACK)
            keepScreenOn = true
            isFocusable = true
            isFocusableInTouchMode = true
        }

        // Sin esto, en un movil los controles se ocultan a los pocos segundos y
        // no hay forma de recuperarlos: no hay teclas que disparen onKeyDown.
        val gestures = GestureDetector(
            this,
            object : GestureDetector.SimpleOnGestureListener() {
                override fun onDown(e: MotionEvent): Boolean = true

                override fun onSingleTapConfirmed(e: MotionEvent): Boolean {
                    if (controlsVisible) setControlsVisible(false) else showControlsTemporarily()
                    return true
                }

                override fun onDoubleTap(e: MotionEvent): Boolean {
                    // Mitad derecha adelanta, mitad izquierda retrocede.
                    if (e.x > root.width / 2f) seekBy(30_000) else seekBy(-10_000)
                    showControlsTemporarily()
                    return true
                }
            },
        )
        root.isClickable = true
        root.setOnTouchListener { view, event ->
            view.performClick()
            gestures.onTouchEvent(event)
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
            indeterminateTintList = ColorStateList.valueOf(PlayerAccent)
            layoutParams = FrameLayout.LayoutParams(dp(64), dp(64), Gravity.CENTER)
        }
        root.addView(loading)

        return root
    }

    private fun topOverlay(title: String): View {
        val animeTitle = intent.getStringExtra(EXTRA_ANIME_TITLE)?.takeIf { it.isNotBlank() }
        val episodeNumber = intent.getIntExtra(EXTRA_EPISODE_NUMBER, 0).takeIf { it > 0 }
        val breadcrumb = listOfNotNull(animeTitle, episodeNumber?.let { "EPISODIO " + it })
            .joinToString("   /   ")
            .ifBlank { "CODERED ANIME TV" }

        return LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(sdp(52), sdp(30), sdp(52), sdp(26))
            background = scrim(0xF7040610.toInt(), 0x8C040610.toInt(), 0x00000000)
            layoutParams = FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                sdp(168),
                Gravity.TOP,
            )

            // Jerarquia en dos niveles: contexto arriba, titulo del capitulo abajo.
            addView(
                LinearLayout(this@PlayerActivity).apply {
                    orientation = LinearLayout.VERTICAL
                    layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f)

                    addView(TextView(this@PlayerActivity).apply {
                        text = breadcrumb
                        setTextColor(PlayerAccentSoft)
                        textSize = sSp(13f)
                        letterSpacing = 0.14f
                        typeface = Typeface.DEFAULT_BOLD
                        maxLines = 1
                    })
                    addView(TextView(this@PlayerActivity).apply {
                        text = title
                        setTextColor(Color.WHITE)
                        textSize = sSp(27f)
                        typeface = Typeface.DEFAULT_BOLD
                        maxLines = 2
                        setPadding(0, dp(6), 0, 0)
                    })
                },
            )

            addView(controlButton("Cerrar") { finish() })
        }
    }

    private fun bottomControls(): View {
        return LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(sdp(52), sdp(44), sdp(52), sdp(40))
            background = scrim(0x00000000, 0x99040610.toInt(), 0xF7040610.toInt())
            layoutParams = FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                sdp(316),
                Gravity.BOTTOM,
            )

            statusText = TextView(this@PlayerActivity).apply {
                text = "Preparando"
                setTextColor(PlayerAccentSoft)
                textSize = sSp(13f)
                letterSpacing = 0.14f
                typeface = Typeface.DEFAULT_BOLD
                background = roundedDrawable(PlayerSurface, PlayerLine)
                setPadding(dp(14), dp(7), dp(14), dp(7))
                layoutParams = LinearLayout.LayoutParams(
                    LinearLayout.LayoutParams.WRAP_CONTENT,
                    LinearLayout.LayoutParams.WRAP_CONTENT,
                )
            }
            addView(statusText)

            val timeline = LinearLayout(this@PlayerActivity).apply {
                orientation = LinearLayout.HORIZONTAL
                gravity = Gravity.CENTER_VERTICAL
                setPadding(0, dp(20), 0, dp(18))
            }
            elapsedText = timeLabel("0:00")
            durationText = timeLabel("--:--")
            seekBar = SeekBar(this@PlayerActivity).apply {
                max = 1_000
                progress = 0
                // Barra propia: pista redondeada, tramo cargado en gris y avance
                // en degradado de marca. La SeekBar del sistema se perdia sobre
                // la imagen y su pulgar tapaba el fotograma.
                progressDrawable = seekTrack()
                thumb = seekThumb()
                splitTrack = false
                minimumHeight = sdp(34)
                setPadding(sdp(12), sdp(14), sdp(12), sdp(14))
                layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f).apply {
                    marginStart = dp(10)
                    marginEnd = dp(10)
                }
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
            }
            if (hasPreviousEpisode) {
                buttons.addView(
                    iconButton(glyphSkip(previous = true), size = sdp(62), primary = false, label = "Capitulo anterior") {
                        goToEpisode(-1)
                    },
                )
            }
            buttons.addView(controlButton("− 10 s") { seekBy(-10_000) })
            // El play/pausa es la accion principal: circulo grande con glifo
            // dibujado, en vez de una etiqueta mas dentro de una fila plana.
            playPauseButton = iconButton(glyphPause(), label = "Pausa") { togglePlayback() }
            buttons.addView(playPauseButton)
            buttons.addView(controlButton("+ 30 s") { seekBy(30_000) })
            if (hasNextEpisode) {
                buttons.addView(
                    iconButton(glyphSkip(previous = false), size = sdp(62), primary = false, label = "Capitulo siguiente") {
                        goToEpisode(1)
                    },
                )
            }
            buttons.addView(controlButton("Reiniciar") { this@PlayerActivity.player?.seekTo(0) })
            addView(buttons)
        }
    }

    /** Pista de la barra de progreso: fondo, buffer y avance con degradado. */
    private fun seekTrack(): LayerDrawable {
        val height = dp(6)
        val radius = height / 2f

        val track = GradientDrawable().apply {
            shape = GradientDrawable.RECTANGLE
            cornerRadius = radius
            setColor(0x33FFFFFF)
            setSize(0, height)
        }
        val buffered = ClipDrawable(
            GradientDrawable().apply {
                shape = GradientDrawable.RECTANGLE
                cornerRadius = radius
                setColor(0x59FFFFFF)
                setSize(0, height)
            },
            Gravity.START,
            ClipDrawable.HORIZONTAL,
        )
        val played = ClipDrawable(
            GradientDrawable(
                GradientDrawable.Orientation.LEFT_RIGHT,
                intArrayOf(PlayerAccentDeep, PlayerAccent),
            ).apply {
                shape = GradientDrawable.RECTANGLE
                cornerRadius = radius
                setSize(0, height)
            },
            Gravity.START,
            ClipDrawable.HORIZONTAL,
        )

        return LayerDrawable(arrayOf<Drawable>(track, buffered, played)).apply {
            setId(0, android.R.id.background)
            setId(1, android.R.id.secondaryProgress)
            setId(2, android.R.id.progress)
        }
    }

    /** Pulgar discreto que crece cuando la barra tiene el foco del mando. */
    private fun seekThumb(): StateListDrawable {
        fun dot(size: Int, ring: Int) = GradientDrawable().apply {
            shape = GradientDrawable.OVAL
            setColor(PlayerAccent)
            setStroke(ring, 0x66FFFFFF)
            setSize(size, size)
        }
        return StateListDrawable().apply {
            addState(intArrayOf(android.R.attr.state_focused), dot(dp(20), dp(3)))
            addState(intArrayOf(android.R.attr.state_pressed), dot(dp(20), dp(3)))
            addState(intArrayOf(), dot(dp(12), 0))
        }
    }

    private fun iconButton(
        icon: Drawable,
        size: Int = sdp(78),
        primary: Boolean = true,
        label: String? = null,
        action: () -> Unit,
    ): ImageButton {
        return ImageButton(this).apply {
            setImageDrawable(icon)
            scaleType = android.widget.ImageView.ScaleType.CENTER
            contentDescription = label
            background = circleBackground(focused = false, primary = primary)
            stateListAnimator = null
            layoutParams = LinearLayout.LayoutParams(size, size).apply {
                marginStart = sdp(12)
                marginEnd = sdp(12)
            }
            setOnFocusChangeListener { view, hasFocus ->
                view.animate()
                    .scaleX(if (hasFocus) 1.1f else 1f)
                    .scaleY(if (hasFocus) 1.1f else 1f)
                    .setDuration(140)
                    .start()
                view.background = circleBackground(hasFocus, primary)
            }
            setOnClickListener {
                showControlsTemporarily()
                action()
            }
        }
    }

    private fun circleBackground(focused: Boolean, primary: Boolean = true): StateListDrawable {
        fun circle(fill: Int, stroke: Int, width: Int) = GradientDrawable().apply {
            shape = GradientDrawable.OVAL
            setColor(fill)
            setStroke(dp(width), stroke)
        }
        val resting = if (primary) circle(PlayerAccentDeep, PlayerLine, 1) else circle(PlayerSurface, PlayerLine, 1)
        return StateListDrawable().apply {
            addState(intArrayOf(android.R.attr.state_focused), circle(PlayerAccent, PlayerAccentSoft, 2))
            addState(intArrayOf(), if (focused) circle(PlayerAccent, PlayerAccentSoft, 2) else resting)
        }
    }

    /**
     * Al terminar el capitulo encadena con el siguiente. La bandera evita que un
     * segundo STATE_ENDED (por ejemplo tras un seek al final) dispare dos saltos.
     */
    private fun maybeAutoAdvance() {
        if (autoAdvanceTriggered || !hasNextEpisode) return
        autoAdvanceTriggered = true
        statusText?.text = "Siguiente capitulo..."
        setControlsVisible(true)
        goToEpisode(1)
    }

    /** Cierra el reproductor pidiendo a la pantalla anterior el capitulo contiguo. */
    private fun goToEpisode(delta: Int) {
        pendingEpisodeDelta = delta
        savePlaybackProgress()
        setResult(RESULT_OK, resultData())
        finish()
    }

    private fun resultData(): Intent = Intent().putExtra(EXTRA_RESULT_EPISODE_DELTA, pendingEpisodeDelta)

    private fun glyphPlay(): Drawable = GlyphDrawable(GlyphDrawable.Kind.PLAY, Color.WHITE, sdp(30))

    private fun glyphPause(): Drawable = GlyphDrawable(GlyphDrawable.Kind.PAUSE, Color.WHITE, sdp(28))

    private fun glyphSkip(previous: Boolean): Drawable = GlyphDrawable(
        if (previous) GlyphDrawable.Kind.PREVIOUS else GlyphDrawable.Kind.NEXT,
        Color.WHITE,
        sdp(22),
    )

    private fun controlButton(label: String, primary: Boolean = false, action: () -> Unit): Button {
        return Button(this).apply {
            text = label
            textSize = sSp(16f)
            isAllCaps = false
            setTextColor(Color.WHITE)
            typeface = Typeface.DEFAULT_BOLD
            minWidth = sdp(122)
            minHeight = sdp(60)
            background = buttonBackground(focused = false, primary = primary)
            stateListAnimator = null
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.WRAP_CONTENT,
                sdp(60),
            ).apply {
                marginStart = sdp(7)
                marginEnd = sdp(7)
            }
            setOnFocusChangeListener { view, hasFocus ->
                view.animate()
                    .scaleX(if (hasFocus) 1.08f else 1f)
                    .scaleY(if (hasFocus) 1.08f else 1f)
                    .setDuration(140)
                    .start()
                view.background = buttonBackground(hasFocus, primary)
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
            setTextColor(PlayerTextSecondary)
            textSize = sSp(14f)
            typeface = Typeface.MONOSPACE
            gravity = Gravity.CENTER
            layoutParams = LinearLayout.LayoutParams(sdp(78), LinearLayout.LayoutParams.WRAP_CONTENT)
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
        if (!visible) {
            lastFocusedControl = bottomControls?.findFocus() ?: topControls?.findFocus()
        }
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
            // Con seis controles en fila, devolver siempre el foco al play/pausa
            // obligaba a rehacer el recorrido cada vez que la barra se ocultaba.
            val target = lastFocusedControl?.takeIf { it.isFocusable } ?: playPauseButton
            target?.requestFocus()
        }
    }

    private fun updatePlayPauseLabel() {
        playPauseButton?.setImageDrawable(if (player?.isPlaying == true) glyphPause() else glyphPlay())
        playPauseButton?.contentDescription = if (player?.isPlaying == true) "Pausa" else "Reproducir"
    }

    private fun updateProgress() {
        val player = player ?: return
        val duration = player.duration.takeIf { it > 0 } ?: 0
        val position = player.currentPosition.coerceAtLeast(0)
        elapsedText?.text = formatTime(position)
        durationText?.text = if (duration > 0) formatTime(duration) else "--:--"
        seekBar?.progress = if (duration > 0) ((position * 1_000) / duration).toInt().coerceIn(0, 1_000) else 0
        // Tramo ya descargado, para que se note cuando el buffer va justo.
        seekBar?.secondaryProgress = if (duration > 0) {
            ((player.bufferedPosition * 1_000) / duration).toInt().coerceIn(0, 1_000)
        } else {
            0
        }
        if (position - lastProgressSaveAtMs >= PROGRESS_SAVE_INTERVAL_MS) {
            savePlaybackProgress()
        }
    }

    private fun scrim(vararg colors: Int): GradientDrawable {
        return GradientDrawable(GradientDrawable.Orientation.TOP_BOTTOM, colors)
    }

    private fun buttonBackground(focused: Boolean, primary: Boolean = false): StateListDrawable {
        val fill = when {
            focused -> PlayerAccent
            primary -> PlayerAccentDeep
            else -> PlayerSurface
        }
        val stroke = if (focused) PlayerAccentSoft else PlayerLine
        return StateListDrawable().apply {
            addState(
                intArrayOf(android.R.attr.state_focused),
                roundedDrawable(PlayerAccent, PlayerAccentSoft, strokeWidth = 2),
            )
            addState(intArrayOf(), roundedDrawable(fill, stroke))
        }
    }

    private fun roundedDrawable(fillColor: Int, strokeColor: Int, strokeWidth: Int = 1): GradientDrawable {
        return GradientDrawable().apply {
            shape = GradientDrawable.RECTANGLE
            cornerRadius = dp(16).toFloat()
            setColor(fillColor)
            setStroke(dp(strokeWidth), strokeColor)
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

    /**
     * Los controles nacieron con medidas de television. En un telefono ese
     * tamano se come el fotograma, asi que todo se reduce un 30%.
     */
    private val compactUi: Boolean by lazy {
        val uiMode = resources.configuration.uiMode and Configuration.UI_MODE_TYPE_MASK
        uiMode != Configuration.UI_MODE_TYPE_TELEVISION &&
            resources.configuration.smallestScreenWidthDp < 600
    }

    /** dp escalado segun la forma del dispositivo. */
    private fun sdp(value: Int): Int = dp(if (compactUi) (value * 0.7f).toInt() else value)

    /** Tamano de texto escalado. */
    private fun sSp(value: Float): Float = if (compactUi) value * 0.78f else value

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
        setResult(RESULT_OK, resultData())
    }

    companion object {
        private const val CONTROLS_HIDE_DELAY_MS = 6_000L
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
        const val EXTRA_HAS_PREVIOUS = "lat.codered.anime.tv.extra.HAS_PREVIOUS"
        const val EXTRA_HAS_NEXT = "lat.codered.anime.tv.extra.HAS_NEXT"
        const val EXTRA_RESULT_EPISODE_DELTA = "lat.codered.anime.tv.extra.RESULT_EPISODE_DELTA"
        const val EXTRA_EPISODE_NUMBER = "lat.codered.anime.tv.extra.EPISODE_NUMBER"
        const val EXTRA_EPISODE_TITLE = "lat.codered.anime.tv.extra.EPISODE_TITLE"
    }
}

/**
 * Glifos de reproduccion dibujados a mano: evita depender de fuentes con
 * simbolos multimedia, que no estan garantizados en todos los Android TV.
 */
private class GlyphDrawable(
    private val kind: Kind,
    private val tint: Int,
    private val size: Int,
) : Drawable() {

    enum class Kind { PLAY, PAUSE, PREVIOUS, NEXT }

    private val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = tint
        style = Paint.Style.FILL
    }
    private val path = Path()

    override fun draw(canvas: Canvas) {
        val b = bounds
        val cx = b.exactCenterX()
        val cy = b.exactCenterY()
        val half = size / 2f

        when (kind) {
            Kind.PLAY -> {
                path.reset()
                // Ligeramente desplazado a la derecha: un triangulo centrado
                // geometricamente se percibe descentrado dentro de un circulo.
                val left = cx - half * 0.78f + size * 0.06f
                path.moveTo(left, cy - half)
                path.lineTo(left + half * 1.62f, cy)
                path.lineTo(left, cy + half)
                path.close()
                canvas.drawPath(path, paint)
            }
            Kind.PREVIOUS, Kind.NEXT -> {
                val direction = if (kind == Kind.NEXT) 1f else -1f
                val barWidth = size * 0.16f
                val triangleWidth = size * 0.72f
                // Barra al lado del vertice, como en cualquier control de medios.
                val barInner = cx + direction * half
                canvas.drawRoundRect(
                    RectF(
                        minOf(barInner, barInner - direction * barWidth),
                        cy - half * 0.86f,
                        maxOf(barInner, barInner - direction * barWidth),
                        cy + half * 0.86f,
                    ),
                    barWidth * 0.35f,
                    barWidth * 0.35f,
                    paint,
                )
                path.reset()
                val base = cx - direction * half
                path.moveTo(base, cy - half * 0.86f)
                path.lineTo(base + direction * triangleWidth, cy)
                path.lineTo(base, cy + half * 0.86f)
                path.close()
                canvas.drawPath(path, paint)
            }
            Kind.PAUSE -> {
                val barWidth = size * 0.29f
                val gap = size * 0.22f
                val radius = barWidth * 0.32f
                canvas.drawRoundRect(
                    RectF(cx - gap / 2f - barWidth, cy - half, cx - gap / 2f, cy + half),
                    radius,
                    radius,
                    paint,
                )
                canvas.drawRoundRect(
                    RectF(cx + gap / 2f, cy - half, cx + gap / 2f + barWidth, cy + half),
                    radius,
                    radius,
                    paint,
                )
            }
        }
    }

    override fun getIntrinsicWidth(): Int = size

    override fun getIntrinsicHeight(): Int = size

    override fun setAlpha(alpha: Int) {
        paint.alpha = alpha
    }

    override fun setColorFilter(colorFilter: ColorFilter?) {
        paint.colorFilter = colorFilter
    }

    @Deprecated("Requerido por Drawable", ReplaceWith("PixelFormat.TRANSLUCENT"))
    override fun getOpacity(): Int = PixelFormat.TRANSLUCENT
}
