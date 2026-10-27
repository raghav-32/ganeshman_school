package np.edu.ganeshman.sms.ui.theme

import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

// Soft off-white canvas + school-green brand + pastel accents.
val Canvas = Color(0xFFF7F8F5)
val Surface = Color(0xFFFFFFFF)
val Ink = Color(0xFF1B2420)
val InkSoft = Color(0xFF66726B)
val Line = Color(0xFFE6EAE4)
val Brand = Color(0xFF2F7D5B)
val BrandDark = Color(0xFF1F5C42)
val BrandSoft = Color(0xFFD6EBE3)

val PastelGreen = Color(0xFFDDF0E4)
val PastelBlue = Color(0xFFDCE8F7)
val PastelYellow = Color(0xFFFBF0CC)
val PastelPink = Color(0xFFF9DDE2)
val PastelLilac = Color(0xFFE6E0F6)
val PastelPeach = Color(0xFFFCE5D4)

val OnGreen = Color(0xFF2B6A48)
val OnBlue = Color(0xFF2F5C93)
val OnYellow = Color(0xFF8A6A0F)
val OnPink = Color(0xFFA33A4E)
val OnLilac = Color(0xFF5B49A0)
val OnPeach = Color(0xFFA8581F)

val Good = Color(0xFF3A9A6B)
val Warn = Color(0xFFE0A21A)
val Bad = Color(0xFFD9534F)

private val scheme = lightColorScheme(
    primary = Brand,
    onPrimary = Color.White,
    primaryContainer = BrandSoft,
    onPrimaryContainer = BrandDark,
    secondary = OnBlue,
    secondaryContainer = PastelBlue,
    onSecondaryContainer = OnBlue,
    tertiary = OnLilac,
    tertiaryContainer = PastelLilac,
    background = Canvas,
    onBackground = Ink,
    surface = Surface,
    onSurface = Ink,
    surfaceVariant = Color(0xFFEEF2EC),
    onSurfaceVariant = InkSoft,
    outline = Line,
    outlineVariant = Line,
    error = Bad,
    errorContainer = PastelPink,
    onErrorContainer = OnPink,
    surfaceContainerLowest = Surface,
    surfaceContainerLow = Surface,
    surfaceContainer = Surface,
    surfaceContainerHigh = Surface,
    surfaceContainerHighest = Surface,
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

private val type = Typography(
    headlineLarge = t(30, FontWeight.Bold, 38),
    headlineMedium = t(26, FontWeight.Bold, 34),
    headlineSmall = t(22, FontWeight.SemiBold, 28),
    titleLarge = t(20, FontWeight.SemiBold, 26),
    titleMedium = t(16, FontWeight.SemiBold, 22, 0.1),
    titleSmall = t(14, FontWeight.SemiBold, 20, 0.1),
    bodyLarge = t(16, FontWeight.Normal, 24, 0.3),
    bodyMedium = t(14, FontWeight.Normal, 20, 0.2),
    bodySmall = t(12, FontWeight.Normal, 16, 0.3),
    labelLarge = t(14, FontWeight.Medium, 20, 0.1),
    labelMedium = t(12, FontWeight.Medium, 16, 0.4),
    labelSmall = t(11, FontWeight.Medium, 14, 0.5),
)

@Composable
fun GmsTheme(content: @Composable () -> Unit) {
    MaterialTheme(colorScheme = scheme, typography = type, shapes = shapes, content = content)
}
