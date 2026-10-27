package np.edu.ganeshman.sms.ui.student

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
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
    val me by repo.session.data.collectAsState()
    var sheet by remember { mutableStateOf(false) }
    FabEffect(FabSpec(Icons.Outlined.Add, rotated = sheet, onClick = { sheet = true }))

    val first = me?.profile?.firstName.orEmpty()
    Screen(
        title = if (first.isBlank()) greeting() else "${greeting()}, $first",
        subtitle = me?.profile?.klass?.label,
        refresh = state.reload,
    ) {
        AsyncContent(state, skeletons = 4) { d ->
            Panel(color = PastelGreen, padding = 20.dp) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text("Keep going!", style = MaterialTheme.typography.titleLarge, color = OnGreen)
                        Text("Your future is built by what you do today.", style = MaterialTheme.typography.bodyMedium, color = OnGreen.copy(alpha = 0.8f))
                    }
                    Icon(Icons.Outlined.Eco, null, tint = OnGreen, modifier = Modifier.size(48.dp))
                }
            }

            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                IconTile(Icons.Outlined.CheckCircle, "${d.attendance.present}", "Days Present", PastelGreen, OnGreen, Modifier.weight(1f))
                IconTile(Icons.Outlined.Cancel, "${d.attendance.absent}", "Days Absent", PastelPink, OnPink, Modifier.weight(1f))
            }
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                IconTile(Icons.Outlined.Payments, money(d.fees.totalPaid), "Fee Paid", PastelLilac, OnLilac, Modifier.weight(1f))
                IconTile(Icons.Outlined.MenuBook, "${d.issuedBooks.size}", "Books Borrowed", PastelBlue, OnBlue, Modifier.weight(1f))
            }

            SectionTitle("Recent Results", "View All") { nav.navigate(Routes.S_RESULTS) }
            if (d.latestResults.isEmpty()) {
                Panel(color = SoftBg) {
                    EmptyState(Illustration.Results, "No published results yet.", "Your exam results will appear here once published.")
                }
            } else d.latestResults.take(2).forEach { r ->
                Panel(onClick = { nav.navigate(Routes.result(r.examId)) }) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(r.examName, style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
                        Chip("${num(r.percentage)}%", BrandSoft, BrandDark)
                    }
                    Bar((r.percentage / 100).toFloat(), pctColor(r.percentage))
                    Text("${num(r.totalObtained)} / ${num(r.totalFull)}", style = MaterialTheme.typography.bodySmall, color = InkSoft)
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
            QuickAction("Attendance", Icons.Outlined.EventAvailable, PastelGreen, OnGreen) { nav.navigate(Routes.S_ATT) },
            QuickAction("Results", Icons.Outlined.EmojiEvents, PastelLilac, OnLilac) { nav.navigate(Routes.S_RESULTS) },
            QuickAction("Exams", Icons.Outlined.Quiz, PastelBlue, OnBlue) { nav.navigate(Routes.S_EXAMS) },
            QuickAction("Fees", Icons.Outlined.Payments, PastelYellow, OnYellow) { nav.navigate(Routes.S_FEES) },
            QuickAction("Library", Icons.Outlined.MenuBook, PastelPeach, OnPeach) { nav.navigate(Routes.S_LIBRARY) },
            QuickAction("Profile", Icons.Outlined.Person, PastelPink, OnPink) { nav.navigate(Routes.S_PROFILE) },
        ),
    ) { sheet = false }
}

@Composable
fun NoticeCard(n: Notice, isNew: Boolean = false) {
    var open by remember { mutableStateOf(false) }
    Panel(onClick = { open = !open }) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(n.title, style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
            if (isNew) Chip("New", PastelPink, OnPink)
        }
        Text(
            n.content, style = MaterialTheme.typography.bodyMedium, color = InkSoft,
            maxLines = if (open) Int.MAX_VALUE else 2,
            overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
        )
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(Icons.Outlined.CalendarMonth, null, tint = InkSoft, modifier = Modifier.size(14.dp))
            Text(n.dateBs ?: prettyDate(n.createdAt), style = MaterialTheme.typography.labelSmall, color = InkSoft, modifier = Modifier.weight(1f))
            Chip(n.audience.replaceFirstChar { it.uppercase() }.ifBlank { "General" }, PastelGreen, OnGreen)
        }
        if (open) coil.compose.AsyncImage(
            model = np.edu.ganeshman.sms.data.fixUrl(n.imageUrl),
            contentDescription = null,
            modifier = Modifier.fillMaxWidth().clip24(),
            contentScale = androidx.compose.ui.layout.ContentScale.FillWidth,
        )
    }
}

private fun Modifier.clip24() = this.then(Modifier.clip(androidx.compose.foundation.shape.RoundedCornerShape(16.dp)))
