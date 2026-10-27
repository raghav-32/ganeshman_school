package np.edu.ganeshman.sms.ui.theme

import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.*
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/** Every colour the UI uses. Two instances exist: light and dark. */
class AppColors(
    val canvas: Color, val surface: Color, val ink: Color, val inkSoft: Color, val line: Color,
    val brand: Color, val brandDark: Color, val brandSoft: Color, val onBrand: Color,
    val pastelGreen: Color, val pastelBlue: Color, val pastelYellow: Color,
    val pastelPink: Color, val pastelLilac: Color, val pastelPeach: Color,
    val onGreen: Color, val onBlue: Color, val onYellow: Color,
    val onPink: Color, val onLilac: Color, val onPeach: Color,
    val good: Color, val warn: Color, val bad: Color,
    val soft: Color,        // quiet panel background / skeleton
    val dark: Boolean,
)

private val LightColors = AppColors(
    canvas = Color(0xFFF8F8FB), surface = Color(0xFFFFFFFF), ink = Color(0xFF1C1D2B), inkSoft = Color(0xFF6B6E80),
    line = Color(0xFFE8E9F0),
    brand = Color(0xFF6C6FD8), brandDark = Color(0xFF4A4DB3), brandSoft = Color(0xFFE7E8FB), onBrand = Color.White,
    pastelGreen = Color(0xFFDDF0E4), pastelBlue = Color(0xFFDCE8F7), pastelYellow = Color(0xFFFBF0CC),
    pastelPink = Color(0xFFF9DDE2), pastelLilac = Color(0xFFE6E0F6), pastelPeach = Color(0xFFFCE5D4),
    onGreen = Color(0xFF2B6A48), onBlue = Color(0xFF2F5C93), onYellow = Color(0xFF8A6A0F),
    onPink = Color(0xFFA33A4E), onLilac = Color(0xFF5B49A0), onPeach = Color(0xFFA8581F),
    good = Color(0xFF3A9A6B), warn = Color(0xFFE0A21A), bad = Color(0xFFD9534F),
    soft = Color(0xFFEFF0F7), dark = false,
)

private val DarkColors = AppColors(
    canvas = Color(0xFF121319), surface = Color(0xFF1C1D26), ink = Color(0xFFEDEEF5), inkSoft = Color(0xFF9EA1B4),
    line = Color(0xFF2E303C),
    brand = Color(0xFF8F92F0), brandDark = Color(0xFFC9CBFF), brandSoft = Color(0xFF2A2C4F), onBrand = Color(0xFF10111C),
    pastelGreen = Color(0xFF1E3A2B), pastelBlue = Color(0xFF1D3250), pastelYellow = Color(0xFF3B3314),
    pastelPink = Color(0xFF432029), pastelLilac = Color(0xFF2D2850), pastelPeach = Color(0xFF41291B),
    onGreen = Color(0xFF8FD8A9), onBlue = Color(0xFF8DB8F0), onYellow = Color(0xFFE8C766),
    onPink = Color(0xFFF0A0B0), onLilac = Color(0xFFB8ACF2), onPeach = Color(0xFFF0B07A),
    good = Color(0xFF52C48B), warn = Color(0xFFEDB83A), bad = Color(0xFFEF6F6B),
    soft = Color(0xFF242530), dark = true,
)

/** Slightly stronger contrast variants (Settings -> High contrast). */
private fun AppColors.highContrast(): AppColors = AppColors(
    canvas = canvas, surface = surface,
    ink = if (dark) Color.White else Color.Black,
    inkSoft = if (dark) Color(0xFFE0E1EC) else Color(0xFF2B2D3B),
    line = if (dark) Color(0xFF6A6D80) else Color(0xFF8C8FA3),
    brand = brand, brandDark = if (dark) Color.White else Color(0xFF2A2C8F), brandSoft = brandSoft, onBrand = onBrand,
    pastelGreen = pastelGreen, pastelBlue = pastelBlue, pastelYellow = pastelYellow,
    pastelPink = pastelPink, pastelLilac = pastelLilac, pastelPeach = pastelPeach,
    onGreen = onGreen, onBlue = onBlue, onYellow = onYellow, onPink = onPink, onLilac = onLilac, onPeach = onPeach,
    good = good, warn = warn, bad = bad, soft = soft, dark = dark,
)

/** The active palette. Changing it recomposes everything that read a colour below. */
object Palette {
    var current by mutableStateOf(LightColors)
    fun pick(dark: Boolean, highContrast: Boolean): AppColors {
        val base = if (dark) DarkColors else LightColors
        return if (highContrast) base.highContrast() else base
    }
}

// Colour names are unchanged, so every screen keeps working; they now follow the palette.
val Canvas: Color get() = Palette.current.canvas
val Surface: Color get() = Palette.current.surface
val Ink: Color get() = Palette.current.ink
val InkSoft: Color get() = Palette.current.inkSoft
val Line: Color get() = Palette.current.line
val Brand: Color get() = Palette.current.brand
val BrandDark: Color get() = Palette.current.brandDark
val BrandSoft: Color get() = Palette.current.brandSoft
val OnBrand: Color get() = Palette.current.onBrand
val SoftBg: Color get() = Palette.current.soft

val PastelGreen: Color get() = Palette.current.pastelGreen
val PastelBlue: Color get() = Palette.current.pastelBlue
val PastelYellow: Color get() = Palette.current.pastelYellow
val PastelPink: Color get() = Palette.current.pastelPink
val PastelLilac: Color get() = Palette.current.pastelLilac
val PastelPeach: Color get() = Palette.current.pastelPeach

val OnGreen: Color get() = Palette.current.onGreen
val OnBlue: Color get() = Palette.current.onBlue
val OnYellow: Color get() = Palette.current.onYellow
val OnPink: Color get() = Palette.current.onPink
val OnLilac: Color get() = Palette.current.onLilac
val OnPeach: Color get() = Palette.current.onPeach

val Good: Color get() = Palette.current.good
val Warn: Color get() = Palette.current.warn
val Bad: Color get() = Palette.current.bad

private fun scheme(c: AppColors) = (if (c.dark) darkColorScheme() else lightColorScheme()).copy(
    primary = c.brand,
    onPrimary = c.onBrand,
    primaryContainer = c.brandSoft,
    onPrimaryContainer = c.brandDark,
    secondary = c.onBlue,
    secondaryContainer = c.pastelBlue,
    onSecondaryContainer = c.onBlue,
    tertiary = c.onLilac,
    tertiaryContainer = c.pastelLilac,
    background = c.canvas,
    onBackground = c.ink,
    surface = c.surface,
    onSurface = c.ink,
    surfaceVariant = c.soft,
    onSurfaceVariant = c.inkSoft,
    outline = c.line,
    outlineVariant = c.line,
    error = c.bad,
    errorContainer = c.pastelPink,
    onErrorContainer = c.onPink,
    surfaceContainerLowest = c.surface,
    surfaceContainerLow = c.surface,
    surfaceContainer = c.surface,
    surfaceContainerHigh = c.surface,
    surfaceContainerHighest = c.surface,
)

private val shapes = Shapes(
    extraSmall = RoundedCornerShape(8.dp),
    small = RoundedCornerShape(12.dp),
    medium = RoundedCornerShape(16.dp),
    large = RoundedCornerShape(24.dp),
    extraLarge = RoundedCornerShape(32.dp),
)

private fun t(size: Int, weight: FontWeight, line: Int, spacing: Double = 0.0) = TextStyle(
    fontFamily = FontFamily.SansSerif,
    fontWeight = weight,
    fontSize = size.sp,
    lineHeight = line.sp,
    letterSpacing = spacing.sp,
)

private fun typography(bold: Boolean): Typography {
    val body = if (bold) FontWeight.SemiBold else FontWeight.Normal
    val label = if (bold) FontWeight.Bold else FontWeight.Medium
    return Typography(
        headlineLarge = t(30, FontWeight.Bold, 38),
        headlineMedium = t(26, FontWeight.Bold, 34),
        headlineSmall = t(22, FontWeight.SemiBold, 28),
        titleLarge = t(20, FontWeight.SemiBold, 26),
        titleMedium = t(16, FontWeight.SemiBold, 22, 0.1),
        titleSmall = t(14, FontWeight.SemiBold, 20, 0.1),
        bodyLarge = t(16, body, 24, 0.3),
        bodyMedium = t(14, body, 20, 0.2),
        bodySmall = t(12, body, 16, 0.3),
        labelLarge = t(14, label, 20, 0.1),
        labelMedium = t(12, label, 16, 0.4),
        labelSmall = t(11, label, 14, 0.5),
    )
}

@Composable
fun GmsTheme(dark: Boolean, highContrast: Boolean = false, bold: Boolean = false, content: @Composable () -> Unit) {
    val colors = remember(dark, highContrast) { Palette.pick(dark, highContrast) }
    SideEffect { if (Palette.current !== colors) Palette.current = colors }
    MaterialTheme(colorScheme = scheme(colors), typography = typography(bold), shapes = shapes, content = content)
}
