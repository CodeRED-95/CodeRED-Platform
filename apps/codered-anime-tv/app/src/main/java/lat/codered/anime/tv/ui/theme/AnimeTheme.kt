package lat.codered.anime.tv.ui.theme

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.State
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.withFrameNanos
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/**
 * Paleta unica de la app. Antes cada composable repetia literales hexadecimales,
 * asi que cualquier ajuste de marca obligaba a tocar decenas de lineas.
 */
@Immutable
object AnimeColors {
    val Base = Color(0xFF04060D)
    val BaseElevated = Color(0xFF080C17)
    val Surface = Color(0xFF0D1524)
    val SurfaceRaised = Color(0xFF141E31)
    val SurfaceFocused = Color(0xFF1D2A44)
    val Line = Color(0x1FFFFFFF)
    val LineStrong = Color(0x33FFFFFF)

    val Accent = Color(0xFFFF2E63)
    val AccentDeep = Color(0xFFE11D48)
    val AccentSoft = Color(0xFFFFB4C8)
    val AccentGlow = Color(0x59FF2E63)

    val TextPrimary = Color(0xFFF4F6FC)
    val TextSecondary = Color(0xFFB4BED4)
    val TextMuted = Color(0xFF7E8AA3)

    val ScrimSoft = Color(0xB3040610)
    val ScrimStrong = Color(0xF2040610)
}

/** Fondo ambiental de la app: base plana + halo carmesi en la esquina superior. */
val AmbientBackground: Brush = Brush.verticalGradient(
    colors = listOf(Color(0xFF100A18), AnimeColors.Base, Color(0xFF03050B)),
)

val AmbientGlow: Brush = Brush.radialGradient(
    colors = listOf(Color(0x4DE11D48), Color(0x1A6D28D9), Color(0x00000000)),
    radius = 1400f,
)

object AnimeShapes {
    val Card = RoundedCornerShape(18.dp)
    val Poster = RoundedCornerShape(14.dp)
    val Panel = RoundedCornerShape(24.dp)
    val Pill = RoundedCornerShape(999.dp)
    val Control = RoundedCornerShape(14.dp)
}

/** Escala tipografica pensada para lectura a 3 metros. */
object AnimeType {
    val Display = TextStyle(fontSize = 30.sp, lineHeight = 34.sp, fontWeight = FontWeight.Black, letterSpacing = (-0.5).sp)
    val Title = TextStyle(fontSize = 21.sp, lineHeight = 25.sp, fontWeight = FontWeight.Black, letterSpacing = (-0.2).sp)
    val Section = TextStyle(fontSize = 17.sp, lineHeight = 21.sp, fontWeight = FontWeight.Black)
    val CardTitle = TextStyle(fontSize = 14.sp, lineHeight = 18.sp, fontWeight = FontWeight.Bold)
    val Body = TextStyle(fontSize = 14.sp, lineHeight = 20.sp, fontWeight = FontWeight.Normal)
    val Meta = TextStyle(fontSize = 12.sp, lineHeight = 16.sp, fontWeight = FontWeight.SemiBold)
    val Label = TextStyle(fontSize = 11.sp, lineHeight = 14.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.6.sp)
}

private val AnimeColorScheme = darkColorScheme(
    primary = AnimeColors.Accent,
    onPrimary = Color.White,
    primaryContainer = AnimeColors.AccentDeep,
    onPrimaryContainer = Color.White,
    secondary = AnimeColors.AccentSoft,
    onSecondary = Color(0xFF2B0713),
    background = AnimeColors.Base,
    onBackground = AnimeColors.TextPrimary,
    surface = AnimeColors.Surface,
    onSurface = AnimeColors.TextPrimary,
    surfaceVariant = AnimeColors.SurfaceRaised,
    onSurfaceVariant = AnimeColors.TextSecondary,
    outline = AnimeColors.LineStrong,
    outlineVariant = AnimeColors.Line,
    error = AnimeColors.Accent,
)

/**
 * Tema de la app. Al alimentar el color scheme de Material los controles estandar
 * (botones, campos de texto, indicadores) dejan de mostrar el morado por defecto.
 */
@Composable
fun AnimeTvTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = AnimeColorScheme,
        shapes = Shapes(
            extraSmall = RoundedCornerShape(10.dp),
            small = AnimeShapes.Control,
            medium = AnimeShapes.Card,
            large = AnimeShapes.Panel,
            extraLarge = AnimeShapes.Panel,
        ),
        typography = Typography(
            bodyLarge = AnimeType.Body,
            bodyMedium = AnimeType.Body,
            labelLarge = TextStyle(fontSize = 14.sp, lineHeight = 18.sp, fontWeight = FontWeight.Bold),
        ),
        content = content,
    )
}

/** Estado de foco reutilizable: evita repetir el par interactionSource + collectIsFocused. */
class TvFocusState(val interactionSource: MutableInteractionSource, private val focusedState: State<Boolean>) {
    val focused: Boolean get() = focusedState.value
}

@Composable
fun rememberTvFocusState(): TvFocusState {
    val interactionSource = remember { MutableInteractionSource() }
    val focused = interactionSource.collectIsFocusedAsState()
    return remember(interactionSource) { TvFocusState(interactionSource, focused) }
}

/**
 * Realce de foco animado. El diseno previo saltaba de golpe entre escalas; con una
 * interpolacion corta el recorrido con el mando se lee mucho mejor.
 */
@Composable
fun Modifier.tvFocusScale(focused: Boolean, scale: Float = 1.06f, elevation: Float = 18f): Modifier {
    val animatedScale by animateFloatAsState(
        targetValue = if (focused) scale else 1f,
        animationSpec = tween(durationMillis = 140),
        label = "tvFocusScale",
    )
    val animatedElevation by animateFloatAsState(
        targetValue = if (focused) elevation else 0f,
        animationSpec = tween(durationMillis = 140),
        label = "tvFocusElevation",
    )
    return this.graphicsLayer {
        scaleX = animatedScale
        scaleY = animatedScale
        shadowElevation = animatedElevation
    }
}

/**
 * Pide el foco en cuanto el nodo esta disponible. Un unico intento en el primer
 * frame falla cuando el contenido aun no ha llegado de la red y el destino
 * todavia no esta adjunto, y el foco acaba cayendo en el menu lateral.
 */
suspend fun FocusRequester.requestFocusSoon(attempts: Int = 6) {
    repeat(attempts) {
        withFrameNanos { }
        if (runCatching { requestFocus() }.isSuccess) return
    }
}
