package np.edu.ganeshman.sms.ui.teacher

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.navigation.NavHostController
import np.edu.ganeshman.sms.ui.common.*
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.student.NoticeCard
import np.edu.ganeshman.sms.ui.theme.*

@Composable
fun TeacherHome(nav: NavHostController) {
    val repo = LocalRepo.current
    val state = rememberAsync { repo.teacherDashboard() }
    var sheet by remember { mutableStateOf(false) }
    FabEffect(FabSpec(Icons.Outlined.Add, rotated = sheet, onClick = { sheet = true }))

    val me by repo.session.data.collectAsState()
    val first = me?.profile?.firstName.orEmpty()
    Screen(
        refresh = state.reload,
        title = if (first.isBlank()) greeting() else "${greeting()}, $first",
        subtitle = state.load.dataOrNull()?.today?.let { listOfNotNull(it.weekday.ifBlank { null }, it.dateBs).joinToString(" • ") },
    ) {
        AsyncContent(state, skeletons = 4) { d ->
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                IconTile(Icons.Outlined.Groups, "${d.classes.size}", "My Classes", PastelBlue, OnBlue, Modifier.weight(1f))
                IconTile(Icons.Outlined.CheckCircle, "${d.classesMarkedToday}", "Marked Today", PastelGreen, OnGreen, Modifier.weight(1f))
            }
            SectionTitle("My classes")
            if (d.classes.isEmpty()) {
                EmptyState(Illustration.People, "No classes yet", "Ask the school office to assign you to a class.")
            } else d.classes.forEach { c ->
                Panel {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        IconBubble(Icons.Outlined.Groups, PastelLilac, OnLilac)
                        Column(Modifier.weight(1f)) {
                            Text(c.label, style = MaterialTheme.typography.titleSmall)
                            Text("${c.studentCount} students", style = MaterialTheme.typography.bodySmall, color = InkSoft)
                        }
                        if (c.isClassTeacher) Chip("Class teacher", PastelGreen, OnGreen)
                    }
                }
            }
            SectionTitle("Notices", "See all") { nav.navigate(Routes.T_NOTICES) }
            if (d.notices.isEmpty()) EmptyState(Illustration.Notices, "No notices", "Announcements will show up here.")
            else d.notices.take(3).forEach { NoticeCard(it) }
        }
    }

    if (sheet) QuickActionsSheet(
        "Quick actions",
        listOf(
            QuickAction("Attendance", Icons.Outlined.EventAvailable, PastelGreen, OnGreen) { nav.navigate(Routes.T_ATT) },
            QuickAction("Marks", Icons.Outlined.EditNote, PastelLilac, OnLilac) { nav.navigate(Routes.T_MARKS) },
            QuickAction("Students", Icons.Outlined.Groups, PastelBlue, OnBlue) { nav.navigate(Routes.T_STUDENTS) },
            QuickAction("Notices", Icons.Outlined.Campaign, PastelPink, OnPink) { nav.navigate(Routes.T_NOTICES) },
            QuickAction("Profile", Icons.Outlined.Person, PastelYellow, OnYellow) { nav.navigate(Routes.T_PROFILE) },
        ),
    ) { sheet = false }
}

@Composable
fun TeacherMore(nav: NavHostController) {
    MoreList(
        listOf(
            MoreItem("Students", "Class lists and attendance rates", Icons.Outlined.Groups, PastelBlue, OnBlue, Routes.T_STUDENTS),
            MoreItem("Notices", "School announcements", Icons.Outlined.Campaign, PastelPink, OnPink, Routes.T_NOTICES),
            MoreItem("Profile", "Your details and password", Icons.Outlined.Person, PastelGreen, OnGreen, Routes.T_PROFILE),
        ),
        nav,
    )
}
