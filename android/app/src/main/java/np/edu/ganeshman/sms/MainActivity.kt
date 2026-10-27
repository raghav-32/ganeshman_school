package np.edu.ganeshman.sms

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.CompositionLocalProvider
import np.edu.ganeshman.sms.ui.common.LocalRepo
import np.edu.ganeshman.sms.ui.nav.AppRoot
import np.edu.ganeshman.sms.ui.theme.GmsTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        val repo = (application as GmsApp).repo
        setContent {
            GmsTheme {
                CompositionLocalProvider(LocalRepo provides repo) { AppRoot() }
            }
        }
    }
}
