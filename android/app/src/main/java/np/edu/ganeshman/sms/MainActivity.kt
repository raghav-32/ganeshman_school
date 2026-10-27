package np.edu.ganeshman.sms

import android.content.res.Configuration
import android.graphics.Color as AndroidColor
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.SystemBarStyle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.runtime.*
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.unit.Density
import np.edu.ganeshman.sms.ui.common.LocalRepo
import np.edu.ganeshman.sms.ui.common.LocalSettings
import np.edu.ganeshman.sms.ui.nav.AppRoot
import np.edu.ganeshman.sms.ui.theme.GmsTheme
import np.edu.ganeshman.sms.ui.theme.Palette

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val app = application as GmsApp
        val settings = app.settings

        // Correct colours from the very first frame (no light flash in dark mode).
        val systemDark = (resources.configuration.uiMode and Configuration.UI_MODE_NIGHT_MASK) == Configuration.UI_MODE_NIGHT_YES
        val startDark = when (settings.theme.value) { "dark" -> true; "light" -> false; else -> systemDark }
        Palette.current = Palette.pick(startDark, settings.highContrast.value)

        setContent {
            val theme by settings.theme.collectAsState()
            val scale by settings.textScale.collectAsState()
            val bold by settings.bold.collectAsState()
            val contrast by settings.highContrast.collectAsState()
            val dark = when (theme) { "dark" -> true; "light" -> false; else -> isSystemInDarkTheme() }

            // Status/navigation bar icons must follow the app theme, not only the phone's.
            DisposableEffect(dark) {
                enableEdgeToEdge(
                    statusBarStyle = SystemBarStyle.auto(AndroidColor.TRANSPARENT, AndroidColor.TRANSPARENT) { dark },
                    navigationBarStyle = SystemBarStyle.auto(AndroidColor.TRANSPARENT, AndroidColor.TRANSPARENT) { dark },
                )
                onDispose { }
            }
            LaunchedEffect(dark, contrast) {
                window.decorView.setBackgroundColor(Palette.pick(dark, contrast).canvas.toArgb())
            }

            val density = LocalDensity.current
            CompositionLocalProvider(
                LocalDensity provides Density(density.density, density.fontScale * scale),
                LocalRepo provides app.repo,
                LocalSettings provides settings,
            ) {
                GmsTheme(dark = dark, highContrast = contrast, bold = bold) { AppRoot() }
            }
        }
    }
}
