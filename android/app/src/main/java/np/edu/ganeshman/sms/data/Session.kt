package np.edu.ganeshman.sms.data

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.serialization.encodeToString

data class SessionData(val token: String, val role: String, val profile: Profile) {
    val isStudent get() = role == "student"
}

/** Keeps the login token + cached profile in encrypted storage and exposes it as a flow. */
class Session(context: Context) {
    private val prefs: SharedPreferences = try {
        val key = MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build()
        EncryptedSharedPreferences.create(
            context, "gms_session", key,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
        )
    } catch (e: Exception) {
        // Keystore problems on a few devices: fall back so the app still works.
        context.getSharedPreferences("gms_session_plain", Context.MODE_PRIVATE)
    }

    private val _data = MutableStateFlow(load())
    val data: StateFlow<SessionData?> = _data.asStateFlow()

    val token: String? get() = _data.value?.token

    private fun load(): SessionData? {
        val token = prefs.getString("token", null) ?: return null
        val role = prefs.getString("role", null) ?: return null
        val profile = try {
            Net.json.decodeFromString<Profile>(prefs.getString("profile", "{}") ?: "{}")
        } catch (e: Exception) {
            Profile()
        }
        return SessionData(token, role, profile)
    }

    fun save(token: String, role: String, profile: Profile) {
        prefs.edit()
            .putString("token", token)
            .putString("role", role)
            .putString("profile", Net.json.encodeToString(profile))
            .apply()
        _data.value = SessionData(token, role, profile)
    }

    fun updateProfile(profile: Profile) {
        val cur = _data.value ?: return
        prefs.edit().putString("profile", Net.json.encodeToString(profile)).apply()
        _data.value = cur.copy(profile = profile)
    }

    /** Highest notice id the user has already seen/been notified about (-1 = unknown yet). */
    var lastNoticeId: Int
        get() = prefs.getInt("last_notice_id", -1)
        set(v) { prefs.edit().putInt("last_notice_id", v).apply() }

    // Unread bell badge: "latest" = newest notice id we know of, "viewed" = newest the user has opened.
    private val _latest = MutableStateFlow(prefs.getInt("latest_notice_id", -1))
    private val _viewed = MutableStateFlow(prefs.getInt("viewed_notice_id", -1))
    val hasUnread: Flow<Boolean> = combine(_latest, _viewed) { l, v -> v >= 0 && l > v }
    val viewedNoticeId: Int get() = _viewed.value

    /** Called whenever notices are loaded (app or background check), so they are never notified twice. */
    fun markNoticesSeen(notices: List<Notice>) {
        val max = notices.maxOfOrNull { it.id }
        if (max == null) {
            if (lastNoticeId < 0) lastNoticeId = 0
            if (_viewed.value < 0) setViewed(0)
            if (_latest.value < 0) setLatest(0)
            return
        }
        if (max > lastNoticeId) lastNoticeId = max
        if (_viewed.value < 0) setViewed(max)          // first ever load: nothing is "new" yet
        if (max > _latest.value) setLatest(max)
    }

    /** The user opened the notices screen. */
    fun markNoticesViewed() { setViewed(maxOf(_latest.value, _viewed.value)) }

    private fun setLatest(v: Int) { prefs.edit().putInt("latest_notice_id", v).apply(); _latest.value = v }
    private fun setViewed(v: Int) { prefs.edit().putInt("viewed_notice_id", v).apply(); _viewed.value = v }

    fun clear() {
        prefs.edit().clear().apply()
        _latest.value = -1
        _viewed.value = -1
        _data.value = null
    }
}
