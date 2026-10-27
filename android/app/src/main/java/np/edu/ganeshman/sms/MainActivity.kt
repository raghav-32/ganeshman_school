package np.edu.ganeshman.sms

import android.graphics.Color as AndroidColor
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.SystemBarStyle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.*
import androidx.compose.ui.graphics.toArgb
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

        // Correct colours from the very first frame. First launch is always light.
        Palette.current = Palette.pick(settings.darkMode.value)

        setContent {
            val dark by settings.darkMode.collectAsState()

            // Status/navigation bar icons follow the app's theme.
            DisposableEffect(dark) {
                enableEdgeToEdge(
                    statusBarStyle = SystemBarStyle.auto(AndroidColor.TRANSPARENT, AndroidColor.TRANSPARENT) { dark },
                    navigationBarStyle = SystemBarStyle.auto(AndroidColor.TRANSPARENT, AndroidColor.TRANSPARENT) { dark },
                )
                onDispose { }
            }
            LaunchedEffect(dark) {
                window.decorView.setBackgroundColor(Palette.pick(dark).canvas.toArgb())
            }

            CompositionLocalProvider(
                LocalRepo provides app.repo,
                LocalSettings provides settings,
            ) {
                GmsTheme(dark = dark) { AppRoot() }
            }
        }
    }
}
