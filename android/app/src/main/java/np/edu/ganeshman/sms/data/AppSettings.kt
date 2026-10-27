package np.edu.ganeshman.sms.data

import android.content.Context
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/** User preferences (not secret): theme, text size, accessibility, notifications. */
class AppSettings(context: Context) {
    private val prefs = context.getSharedPreferences("gms_settings", Context.MODE_PRIVATE)

    private val _theme = MutableStateFlow(prefs.getString("theme", "system") ?: "system")
    private val _textScale = MutableStateFlow(prefs.getFloat("text_scale", 1f))
    private val _bold = MutableStateFlow(prefs.getBoolean("bold", false))
    private val _contrast = MutableStateFlow(prefs.getBoolean("contrast", false))
    private val _notifications = MutableStateFlow(prefs.getBoolean("notifications", true))

    /** "system", "light" or "dark" */
    val theme: StateFlow<String> = _theme.asStateFlow()
    val textScale: StateFlow<Float> = _textScale.asStateFlow()
    val bold: StateFlow<Boolean> = _bold.asStateFlow()
    val highContrast: StateFlow<Boolean> = _contrast.asStateFlow()
    val notifications: StateFlow<Boolean> = _notifications.asStateFlow()

    fun setTheme(v: String) { prefs.edit().putString("theme", v).apply(); _theme.value = v }
    fun setTextScale(v: Float) { prefs.edit().putFloat("text_scale", v).apply(); _textScale.value = v }
    fun setBold(v: Boolean) { prefs.edit().putBoolean("bold", v).apply(); _bold.value = v }
    fun setHighContrast(v: Boolean) { prefs.edit().putBoolean("contrast", v).apply(); _contrast.value = v }
    fun setNotifications(v: Boolean) { prefs.edit().putBoolean("notifications", v).apply(); _notifications.value = v }
}
