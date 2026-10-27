package np.edu.ganeshman.sms.ui.student

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.KeyboardArrowRight
import androidx.compose.material.icons.outlined.ChevronLeft
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import np.edu.ganeshman.sms.data.AttendanceRecord
import np.edu.ganeshman.sms.ui.common.LocalRepo
import np.edu.ganeshman.sms.ui.common.parseDate
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.theme.*
import java.time.YearMonth
import java.time.format.TextStyle
import java.util.Locale

fun statusColors(s: String): Pair<Color, Color> = when (s.lowercase()) {
    "present" -> PastelGreen to OnGreen
    "absent" -> PastelPink to OnPink
    "late" -> PastelYellow to OnYellow
    else -> PastelBlue to OnBlue   // leave
}

@Composable
fun StudentAttendanceScreen() {
    val repo = LocalRepo.current
    var month by remember { mutableStateOf(YearMonth.now()) }
    val state = rememberAsync(month) { repo.studentAttendance(month.toString()) }

    Screen("Attendance") {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = { month = month.minusMonths(1) }) { Icon(Icons.Outlined.ChevronLeft, "Previous month") }
            Text(
                month.month.getDisplayName(TextStyle.FULL, Locale.ENGLISH) + " " + month.year,
                style = MaterialTheme.typography.titleMedium, textAlign = TextAlign.Center, modifier = Modifier.weight(1f),
            )
            IconButton(onClick = { month = month.plusMonths(1) }, enabled = month < YearMonth.now()) {
                Icon(Icons.Outlined.ChevronRight, "Next month")
            }
        }
        AsyncContent(state) { d ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                StatTile("${d.monthSummary.present}", "Present", PastelGreen, OnGreen, Modifier.weight(1f))
                StatTile("${d.monthSummary.absent}", "Absent", PastelPink, OnPink, Modifier.weight(1f))
                StatTile("${d.monthSummary.late}", "Late", PastelYellow, OnYellow, Modifier.weight(1f))
                StatTile("${d.monthSummary.leave}", "Leave", PastelBlue, OnBlue, Modifier.weight(1f))
            }
            if (d.records.isEmpty()) {
                EmptyState(Illustration.Calendar, "No records", "Nothing was marked for this month.")
            } else {
                Panel { CalendarGrid(month, d.records) }
                d.records.sortedByDescending { it.date }.forEach { r ->
                    val (bg, fg) = statusColors(r.status)
                    Panel(padding = 14.dp) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text(r.dateBs ?: r.date, style = MaterialTheme.typography.titleSmall)
                                Text(r.weekday + " • " + r.date, style = MaterialTheme.typography.bodySmall, color = InkSoft)
                                if (!r.remarks.isNullOrBlank()) Text(r.remarks, style = MaterialTheme.typography.bodySmall, color = InkSoft)
                            }
                            Chip(r.status, bg, fg)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun CalendarGrid(month: YearMonth, records: List<AttendanceRecord>) {
    val byDay = records.associateBy { runCatching { parseDate(it.date).dayOfMonth }.getOrDefault(0) }
    val offset = month.atDay(1).dayOfWeek.value % 7   // Sunday-first
    val cells = List(offset) { 0 } + (1..month.lengthOfMonth()).toList()
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Row(Modifier.fillMaxWidth()) {
            listOf("S", "M", "T", "W", "T", "F", "S").forEach {
                Text(it, Modifier.weight(1f), textAlign = TextAlign.Center, style = MaterialTheme.typography.labelSmall, color = InkSoft)
            }
        }
        cells.chunked(7).forEach { week ->
            Row(Modifier.fillMaxWidth()) {
                week.forEach { day ->
                    Box(Modifier.weight(1f).aspectRatio(1f).padding(2.dp), contentAlignment = Alignment.Center) {
                        if (day != 0) {
                            val rec = byDay[day]
                            val (bg, fg) = if (rec != null) statusColors(rec.status) else Color.Transparent to InkSoft
                            Box(Modifier.fillMaxSize().clip(CircleShape).background(bg), contentAlignment = Alignment.Center) {
                                Text("$day", style = MaterialTheme.typography.labelMedium, color = fg)
                            }
                        }
                    }
                }
                repeat(7 - week.size) { Spacer(Modifier.weight(1f)) }
            }
        }
    }
}
