package np.edu.ganeshman.sms.data

import android.content.Context
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/** User preferences (not secret): dark mode and notice alerts. Light mode is the default on first launch. */
class AppSettings(context: Context) {
    private val prefs = context.getSharedPreferences("gms_settings", Context.MODE_PRIVATE)

    private val _dark = MutableStateFlow(prefs.getBoolean("dark_mode", false))
    private val _notifications = MutableStateFlow(prefs.getBoolean("notifications", true))

    val darkMode: StateFlow<Boolean> = _dark.asStateFlow()
    val notifications: StateFlow<Boolean> = _notifications.asStateFlow()

    fun setDarkMode(v: Boolean) { prefs.edit().putBoolean("dark_mode", v).apply(); _dark.value = v }
    fun setNotifications(v: Boolean) { prefs.edit().putBoolean("notifications", v).apply(); _notifications.value = v }
}
