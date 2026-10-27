package np.edu.ganeshman.sms.ui.common

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.navigation.NavHostController
import kotlinx.coroutines.delay
import np.edu.ganeshman.sms.data.ClassStudent
import np.edu.ganeshman.sms.data.LibraryData
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.theme.*

private class Hit(
    val section: String, val title: String, val subtitle: String?, val icon: ImageVector,
    val bg: Color, val fg: Color, val onClick: () -> Unit,
)

private class Page(val label: String, val icon: ImageVector, val route: String)

/** One search box across everything the user can see in the app. */
@Composable
fun SearchScreen(student: Boolean, nav: NavHostController, onBack: () -> Unit) {
    val repo = LocalRepo.current
    var q by remember { mutableStateOf("") }
    val focus = remember { FocusRequester() }
    LaunchedEffect(Unit) { runCatching { focus.requestFocus() } }

    // Library is searched on the server (debounced); everything else is filtered on the phone.
    var libQ by remember { mutableStateOf("") }
    LaunchedEffect(q) { delay(400); libQ = if (q.trim().length >= 2) q.trim() else "" }

    val notices = rememberAsync { if (student) repo.studentNotices() else repo.teacherNotices() }
    val results = rememberAsync { if (student) repo.results() else emptyList() }
    val exams = rememberAsync { if (student) repo.studentExams() else emptyList() }
    val timetable = rememberAsync { if (student) repo.timetable().days else emptyList() }
    val library = rememberAsync(libQ) { if (student && libQ.isNotBlank()) repo.library(libQ) else LibraryData() }
    val classes = rememberAsync { if (student) emptyList() else repo.teacherClasses() }
    val tExams = rememberAsync { if (student) emptyList() else repo.teacherExams() }
    val people = rememberAsync {
        if (student) emptyList<Pair<String, ClassStudent>>()
        else repo.teacherClasses().flatMap { c -> repo.classStudents(c.id).students.map { c.label to it } }
    }

    val pages = remember(student) {
        if (student) listOf(
            Page("Attendance", Icons.Outlined.EventAvailable, Routes.S_ATT),
            Page("Timetable", Icons.Outlined.Schedule, Routes.S_TIMETABLE),
            Page("Results", Icons.Outlined.EmojiEvents, Routes.S_RESULTS),
            Page("Exam schedule", Icons.Outlined.Quiz, Routes.S_EXAMS),
            Page("Fees", Icons.Outlined.Payments, Routes.S_FEES),
            Page("Library", Icons.Outlined.MenuBook, Routes.S_LIBRARY),
            Page("Notices", Icons.Outlined.Notifications, Routes.S_NOTICES),
            Page("Profile", Icons.Outlined.Person, Routes.S_PROFILE),
            Page("Settings", Icons.Outlined.Settings, Routes.SETTINGS),
            Page("About app", Icons.Outlined.Info, Routes.ABOUT),
        ) else listOf(
            Page("Attendance", Icons.Outlined.EventAvailable, Routes.T_ATT),
            Page("Marks", Icons.Outlined.EditNote, Routes.T_MARKS),
            Page("Students", Icons.Outlined.Groups, Routes.T_STUDENTS),
            Page("Notices", Icons.Outlined.Notifications, Routes.T_NOTICES),
            Page("Profile", Icons.Outlined.Person, Routes.T_PROFILE),
            Page("Settings", Icons.Outlined.Settings, Routes.SETTINGS),
            Page("About app", Icons.Outlined.Info, Routes.ABOUT),
        )
    }

    val needle = q.trim().lowercase()
    fun has(vararg s: String?) = s.any { it != null && it.lowercase().contains(needle) }
    fun go(route: String) { nav.navigate(route) }

    val hits = mutableListOf<Hit>()
    if (needle.isNotEmpty()) {
        pages.filter { has(it.label) }.forEach {
            hits += Hit("Go to", it.label, null, it.icon, PastelBlue, OnBlue) { go(it.route) }
        }
        notices.load.dataOrNull().orEmpty().filter { has(it.title, it.content) }.forEach { n ->
            hits += Hit("Notices", n.title, n.content, Icons.Outlined.Campaign, PastelYellow, OnYellow) {
                go(if (student) Routes.S_NOTICES else Routes.T_NOTICES)
            }
        }
        if (student) {
            results.load.dataOrNull().orEmpty().filter { has(it.examName, it.examType) }.forEach { r ->
                hits += Hit("Results", r.examName, "${num(r.percentage)}%  •  ${num(r.totalObtained)} / ${num(r.totalFull)}",
                    Icons.Outlined.EmojiEvents, PastelLilac, OnLilac) { go(Routes.result(r.examId)) }
            }
            exams.load.dataOrNull().orEmpty().filter { e -> has(e.examName) || e.schedule.any { has(it.subjectName) } }.forEach { e ->
                hits += Hit("Exams", e.examName, e.schedule.joinToString(", ") { it.subjectName }.take(80),
                    Icons.Outlined.Quiz, PastelBlue, OnBlue) { go(Routes.S_EXAMS) }
            }
            timetable.load.dataOrNull().orEmpty().forEach { d ->
                d.periods.filter { has(it.subjectName, it.teacherName) }.forEach { p ->
                    hits += Hit("Timetable", p.subjectName, "${d.day}  •  ${hhmm(p.startTime)}–${hhmm(p.endTime)}  •  ${p.teacherName}",
                        Icons.Outlined.Schedule, PastelGreen, OnGreen) { go(Routes.S_TIMETABLE) }
                }
            }
            library.load.dataOrNull()?.books.orEmpty().forEach { b ->
                hits += Hit("Library", b.title, listOfNotNull(b.author, if (b.available) "Available" else "Not available").joinToString("  •  "),
                    Icons.Outlined.MenuBook, PastelPeach, OnPeach) { go(Routes.S_LIBRARY) }
            }
        } else {
            classes.load.dataOrNull().orEmpty().filter { has(it.label, it.name) }.forEach { c ->
                hits += Hit("Classes", c.label, "${c.studentCount} students", Icons.Outlined.Groups, PastelLilac, OnLilac) { go(Routes.T_ATT) }
            }
            people.load.dataOrNull().orEmpty().filter { (_, s) -> has(s.fullName, s.studentCode, s.phone) }.forEach { (cls, s) ->
                hits += Hit("Students", s.fullName, "$cls  •  ${s.studentCode}", Icons.Outlined.Person, PastelPink, OnPink) { go(Routes.T_STUDENTS) }
            }
            tExams.load.dataOrNull().orEmpty().filter { has(it.examName, it.klass.label) }.forEach { e ->
                hits += Hit("Exams", e.examName, e.klass.label, Icons.Outlined.EditNote, PastelBlue, OnBlue) { go(Routes.entry(e.id)) }
            }
        }
    }

    Screen("Search", onBack = onBack, bottomSpace = 40.dp) {
        Field(q, { q = it }, "Search the app", modifier = Modifier.focusRequester(focus), trailing = {
            if (q.isNotEmpty()) IconButton(onClick = { q = "" }) { Icon(Icons.Outlined.Close, "Clear") }
            else Icon(Icons.Outlined.Search, null)
        })
        if (needle.isEmpty()) {
            EmptyState(
                Illustration.Inbox, "Search anything",
                if (student) "Notices, results, exams, timetable, library books and app pages." else "Notices, classes, students, exams and app pages.",
            )
        } else if (hits.isEmpty()) {
            val busy = listOf(notices.load, results.load, exams.load, timetable.load, people.load).any { it is Load.Loading }
            if (busy) SkeletonList(3, 64.dp)
            else EmptyState(Illustration.Inbox, "No matches", "Nothing found for \"$q\". Try a different word.")
        } else {
            hits.groupBy { it.section }.forEach { (section, list) ->
                SectionTitle(section)
                list.take(8).forEach { h ->
                    Panel(onClick = h.onClick, padding = 14.dp) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            IconBubble(h.icon, h.bg, h.fg, 40.dp)
                            Column(Modifier.weight(1f)) {
                                Text(h.title, style = MaterialTheme.typography.titleSmall, maxLines = 1,
                                    overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis)
                                if (!h.subtitle.isNullOrBlank()) Text(h.subtitle, style = MaterialTheme.typography.bodySmall, color = InkSoft,
                                    maxLines = 2, overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis)
                            }
                        }
                    }
                }
            }
        }
    }
}
