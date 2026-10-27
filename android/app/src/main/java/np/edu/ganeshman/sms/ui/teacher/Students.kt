package np.edu.ganeshman.sms.ui.teacher

import androidx.compose.foundation.layout.*
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import np.edu.ganeshman.sms.ui.common.LocalRepo
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.theme.*

@Composable
fun StudentsScreen(onBack: () -> Unit) {
    val repo = LocalRepo.current
    val classes = rememberAsync { repo.teacherClasses() }
    var classId by remember { mutableStateOf<Int?>(null) }
    val list = classes.load.dataOrNull().orEmpty()
    val selected = classId ?: list.firstOrNull()?.id
    val students = rememberAsync(selected) {
        val id = selected ?: return@rememberAsync null
        repo.classStudents(id)
    }

    Screen("Students", onBack = onBack) {
        AsyncContent(classes) { cs ->
            if (cs.isEmpty()) {
                EmptyState(Illustration.People, "No classes", "You haven't been assigned to a class yet.")
                return@AsyncContent
            }
            PillSelector(cs.map { it.id }, selected, { id -> cs.first { it.id == id }.label }) { classId = it }
            AsyncContent(students) { d ->
                if (d == null || d.students.isEmpty()) {
                    EmptyState(Illustration.People, "No students", "This class has no active students.")
                } else d.students.forEach { s ->
                    Panel(padding = 14.dp) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            Avatar(s.fullName, s.photoUrl, 44.dp)
                            Column(Modifier.weight(1f)) {
                                Text(s.fullName, style = MaterialTheme.typography.titleSmall)
                                Text(listOfNotNull(s.studentCode, s.gender, s.phone).joinToString(" • "),
                                    style = MaterialTheme.typography.bodySmall, color = InkSoft)
                            }
                            Chip("${num(s.attendancePercentage)}%",
                                if (s.attendancePercentage >= 75) PastelGreen else PastelPink,
                                if (s.attendancePercentage >= 75) OnGreen else OnPink)
                        }
                        Bar((s.attendancePercentage / 100).toFloat(), pctColor(s.attendancePercentage), height = 6.dp)
                    }
                }
            }
        }
    }
}
