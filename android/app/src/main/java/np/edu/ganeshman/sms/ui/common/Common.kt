package np.edu.ganeshman.sms.ui.common

import androidx.compose.runtime.staticCompositionLocalOf
import np.edu.ganeshman.sms.data.Repository
import java.time.LocalDate
import java.time.format.DateTimeFormatter

val LocalRepo = staticCompositionLocalOf<Repository> { error("Repository not provided") }
val LocalSettings = staticCompositionLocalOf<np.edu.ganeshman.sms.data.AppSettings> { error("Settings not provided") }

object Routes {
    const val LOGIN = "login"
    const val REGISTER = "register"
    const val PENDING = "pending"
    const val SETTINGS = "settings"
    const val ABOUT = "about"
    const val SEARCH = "search"

    const val S_HOME = "s/home"
    const val S_ATT = "s/attendance"
    const val S_RESULTS = "s/results"
    const val S_MORE = "s/more"
    const val S_RESULT = "s/result/{id}"
    const val S_MARKSHEET = "s/marksheet/{id}"
    const val S_TIMETABLE = "s/timetable"
    const val S_EXAMS = "s/exams"
    const val S_FEES = "s/fees"
    const val S_LIBRARY = "s/library"
    const val S_NOTICES = "s/notices"
    const val S_PROFILE = "s/profile"

    const val T_HOME = "t/home"
    const val T_ATT = "t/attendance"
    const val T_MARKS = "t/marks"
    const val T_MORE = "t/more"
    const val T_ENTRY = "t/marks/{examId}"
    const val T_STUDENTS = "t/students"
    const val T_NOTICES = "t/notices"
    const val T_PROFILE = "t/profile"

    fun result(id: Int) = "s/result/$id"
    fun marksheet(id: Int) = "s/marksheet/$id"
    fun entry(examId: Int) = "t/marks/$examId"
}

private val iso = DateTimeFormatter.ISO_LOCAL_DATE
fun today(): String = LocalDate.now().format(iso)
fun parseDate(s: String): LocalDate = LocalDate.parse(s, iso)
fun LocalDate.iso(): String = format(iso)

/** "2026-10-28" (AD, as stored) -> "Kartik 11, 2083" (BS, same layout as "Oct 28, 2026"). */
fun prettyDate(s: String?): String {
    if (s.isNullOrBlank()) return ""
    return try {
        val b = NepaliDate.fromAd(LocalDate.parse(s.take(10))) ?: return s
        "${NepaliDate.monthNames[b.second - 1]} ${b.third}, ${b.first}"
    } catch (e: Exception) { s }
}

/** "08:30:00" -> "08:30" */
fun hhmm(s: String?): String = s?.take(5) ?: ""

fun greeting(): String {
    val h = java.time.LocalTime.now().hour
    return when {
        h < 12 -> "Good morning"
        h < 17 -> "Good afternoon"
        else -> "Good evening"
    }
}
