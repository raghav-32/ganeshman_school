package np.edu.ganeshman.sms.ui.student

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
import np.edu.ganeshman.sms.data.Notice
import np.edu.ganeshman.sms.ui.common.*
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.theme.*

@Composable
fun StudentHome(nav: NavHostController) {
    val repo = LocalRepo.current
    val state = rememberAsync { repo.studentDashboard() }
    var sheet by remember { mutableStateOf(false) }
    FabEffect(FabSpec(Icons.Outlined.Add, onClick = { sheet = true }))

    Screen("Home") {
        AsyncContent(state, skeletons = 4) { d ->
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                Avatar(d.student.fullName, d.student.photoUrl, 56.dp)
                Column(Modifier.weight(1f)) {
                    Text(greeting(), style = MaterialTheme.typography.bodyMedium, color = InkSoft)
                    Text(d.student.firstName.ifBlank { d.student.fullName }, style = MaterialTheme.typography.headlineSmall)
                    Text(
                        listOfNotNull(d.student.klass?.label, d.today.dateBs).joinToString(" • "),
                        style = MaterialTheme.typography.bodySmall, color = InkSoft,
                    )
                }
            }

            Panel(onClick = { nav.navigate(Routes.S_ATT) }) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                    Ring(
                        (d.attendance.percentage / 100).toFloat(), pctColor(d.attendance.percentage),
                        label = "${num(d.attendance.percentage)}%",
                    )
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text("Attendance", style = MaterialTheme.typography.titleMedium)
                        Text(
                            "${d.attendance.present} present of ${d.attendance.total} days",
                            style = MaterialTheme.typography.bodyMedium, color = InkSoft,
                        )
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            Chip("${d.attendance.absent} absent", PastelPink, OnPink)
                            Chip("${d.attendance.late} late", PastelYellow, OnYellow)
                        }
                    }
                }
            }

            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                StatTile(money(d.fees.totalDue), "Fee due", PastelYellow, OnYellow, Modifier.weight(1f))
                StatTile(money(d.fees.totalPaid), "Paid", PastelGreen, OnGreen, Modifier.weight(1f))
            }

            if (d.latestResults.isNotEmpty()) {
                SectionTitle("Latest result", "See all") { nav.navigate(Routes.S_RESULTS) }
                val r = d.latestResults.first()
                Panel(onClick = { nav.navigate(Routes.result(r.examId)) }) {
                    Text(r.examName, style = MaterialTheme.typography.titleMedium)
                    Text("${num(r.totalObtained)} / ${num(r.totalFull)}", color = InkSoft, style = MaterialTheme.typography.bodyMedium)
                    Bar((r.percentage / 100).toFloat(), pctColor(r.percentage))
                    Text("${num(r.percentage)}%", style = MaterialTheme.typography.labelLarge)
                }
            }

            if (d.issuedBooks.isNotEmpty()) {
                SectionTitle("Library books", "Open") { nav.navigate(Routes.S_LIBRARY) }
                d.issuedBooks.take(2).forEach { b ->
                    Panel {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            IconBubble(Icons.Outlined.MenuBook, PastelPeach, OnPeach)
                            Column(Modifier.weight(1f)) {
                                Text(b.title, style = MaterialTheme.typography.titleSmall)
                                Text("Due ${b.dueDateBs ?: prettyDate(b.dueDate)}", style = MaterialTheme.typography.bodySmall, color = InkSoft)
                            }
                            if (b.isOverdue) Chip("Overdue", PastelPink, OnPink)
                            else Chip("${b.daysLeft}d left", PastelGreen, OnGreen)
                        }
                    }
                }
            }

            SectionTitle("Notices", "See all") { nav.navigate(Routes.S_NOTICES) }
            if (d.notices.isEmpty()) {
                EmptyState(Illustration.Notices, "No notices", "New school announcements will show up here.")
            } else d.notices.take(3).forEach { NoticeCard(it) }
        }
    }

    if (sheet) QuickActionsSheet(
        "Quick actions",
        listOf(
            QuickAction("Timetable", Icons.Outlined.Schedule, PastelBlue, OnBlue) { nav.navigate(Routes.S_TIMETABLE) },
            QuickAction("Exams", Icons.Outlined.Quiz, PastelLilac, OnLilac) { nav.navigate(Routes.S_EXAMS) },
            QuickAction("Fees", Icons.Outlined.Payments, PastelYellow, OnYellow) { nav.navigate(Routes.S_FEES) },
            QuickAction("Library", Icons.Outlined.MenuBook, PastelPeach, OnPeach) { nav.navigate(Routes.S_LIBRARY) },
            QuickAction("Notices", Icons.Outlined.Campaign, PastelPink, OnPink) { nav.navigate(Routes.S_NOTICES) },
            QuickAction("Profile", Icons.Outlined.Person, PastelGreen, OnGreen) { nav.navigate(Routes.S_PROFILE) },
        ),
    ) { sheet = false }
}

@Composable
fun NoticeCard(n: Notice) {
    var open by remember { mutableStateOf(false) }
    Panel(onClick = { open = !open }) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(n.title, style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
            Text(n.dateBs ?: prettyDate(n.createdAt), style = MaterialTheme.typography.labelSmall, color = InkSoft)
        }
        Text(
            n.content, style = MaterialTheme.typography.bodyMedium, color = InkSoft,
            maxLines = if (open) Int.MAX_VALUE else 2,
            overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
        )
        if (open) coil.compose.AsyncImage(
            model = np.edu.ganeshman.sms.data.fixUrl(n.imageUrl),
            contentDescription = null,
            modifier = Modifier.fillMaxWidth().clip24(),
            contentScale = androidx.compose.ui.layout.ContentScale.FillWidth,
        )
    }
}

private fun Modifier.clip24() = this.then(Modifier.clip(androidx.compose.foundation.shape.RoundedCornerShape(16.dp)))
