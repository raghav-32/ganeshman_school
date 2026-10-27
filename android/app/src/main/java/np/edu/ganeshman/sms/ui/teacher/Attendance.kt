package np.edu.ganeshman.sms.ui.teacher

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.ChevronLeft
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Save
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import np.edu.ganeshman.sms.data.ApiException
import np.edu.ganeshman.sms.data.TeacherClass
import np.edu.ganeshman.sms.ui.common.*
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.theme.*
import java.time.LocalDate

private val statuses = listOf("Present" to "P", "Absent" to "A", "Late" to "L", "Leave" to "Lv")

@Composable
fun TeacherAttendanceScreen() {
    val repo = LocalRepo.current
    val scope = rememberCoroutineScope()

    val classes = rememberAsync { repo.teacherClasses() }
    var classId by remember { mutableStateOf<Int?>(null) }
    var date by remember { mutableStateOf(LocalDate.now()) }
    val marks = remember { mutableStateMapOf<Int, String>() }
    var saving by remember { mutableStateOf(false) }
    var msg by remember { mutableStateOf<String?>(null) }
    var ok by remember { mutableStateOf(false) }

    val list = classes.load.dataOrNull().orEmpty()
    val selected = classId ?: list.firstOrNull { it.isClassTeacher }?.id ?: list.firstOrNull()?.id

    val att = rememberAsync(selected to date) {
        val id = selected ?: return@rememberAsync null
        repo.teacherAttendance(id, date.iso())
    }
    val data = att.load.dataOrNull()

    // seed the editable map whenever a fresh roster arrives
    LaunchedEffect(data) {
        marks.clear()
        data?.students?.forEach { s -> marks[s.id] = s.status ?: "Present" }
    }

    fun save() {
        val id = selected ?: return
        saving = true; msg = null
        scope.launch {
            try {
                val r = repo.saveAttendance(id, date.iso(), marks.toMap())
                ok = true; msg = "Saved attendance for ${r.saved} students."
                att.reload()
            } catch (e: ApiException) { ok = false; msg = e.message }
            catch (e: Exception) { ok = false; msg = "Something went wrong." }
            finally { saving = false }
        }
    }

    FabEffect(
        if (data?.canMark == true && data.students.isNotEmpty())
            FabSpec(Icons.Outlined.Save, "Save", loading = saving, onClick = ::save) else null
    )

    Screen("Attendance") {
        AsyncContent(classes) { cs ->
            if (cs.isEmpty()) {
                EmptyState(Illustration.People, "No classes", "You haven't been assigned to a class yet.")
                return@AsyncContent
            }
            PillSelector(cs.map { it.id }, selected, { id -> cs.first { it.id == id }.label }) { classId = it }

            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = { date = date.minusDays(1) }) { Icon(Icons.Outlined.ChevronLeft, "Previous day") }
                Column(Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(prettyDate(date.iso()), style = MaterialTheme.typography.titleMedium)
                    Text(data?.dateBs ?: "", style = MaterialTheme.typography.bodySmall, color = InkSoft)
                }
                IconButton(onClick = { date = date.plusDays(1) }, enabled = date.isBefore(LocalDate.now())) {
                    Icon(Icons.Outlined.ChevronRight, "Next day")
                }
            }
            msg?.let { Text(it, color = if (ok) OnGreen else Bad, style = MaterialTheme.typography.bodyMedium) }

            AsyncContent(att) { a ->
                if (a == null) return@AsyncContent
                if (!a.canMark) {
                    Panel(color = PastelYellow) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            Icon(Icons.Outlined.Lock, null, tint = OnYellow)
                            Text("Only the class teacher can mark attendance for this class.",
                                style = MaterialTheme.typography.bodyMedium, color = OnYellow)
                        }
                    }
                }
                if (a.students.isEmpty()) {
                    EmptyState(Illustration.People, "No students", "This class has no active students.")
                } else {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        val counts = statuses.associate { it.first to marks.values.count { v -> v == it.first } }
                        StatTile("${counts["Present"]}", "Present", PastelGreen, OnGreen, Modifier.weight(1f))
                        StatTile("${counts["Absent"]}", "Absent", PastelPink, OnPink, Modifier.weight(1f))
                        StatTile("${counts["Late"]}", "Late", PastelYellow, OnYellow, Modifier.weight(1f))
                        StatTile("${counts["Leave"]}", "Leave", PastelBlue, OnBlue, Modifier.weight(1f))
                    }
                    a.students.forEach { s ->
                        Panel(padding = 14.dp) {
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                                Avatar(s.fullName, s.photoUrl, 40.dp)
                                Column(Modifier.weight(1f)) {
                                    Text(s.fullName, style = MaterialTheme.typography.titleSmall)
                                    Text(s.studentCode, style = MaterialTheme.typography.bodySmall, color = InkSoft)
                                }
                            }
                            StatusSegment(marks[s.id] ?: "Present", enabled = a.canMark) { marks[s.id] = it }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun StatusSegment(current: String, enabled: Boolean, onPick: (String) -> Unit) {
    Row(
        Modifier.fillMaxWidth().clip(CircleShape).background(Canvas).padding(4.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        statuses.forEach { (full, short) ->
            val on = full == current
            val (bg, fg) = np.edu.ganeshman.sms.ui.student.statusColors(full)
            Text(
                short,
                Modifier
                    .weight(1f)
                    .clip(CircleShape)
                    .background(if (on) bg else Color.Transparent)
                    .clickable(enabled = enabled) { onPick(full) }
                    .padding(vertical = 8.dp),
                textAlign = TextAlign.Center,
                style = MaterialTheme.typography.labelLarge,
                color = if (on) fg else InkSoft,
            )
        }
    }
}
