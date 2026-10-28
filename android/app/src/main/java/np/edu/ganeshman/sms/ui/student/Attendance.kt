package np.edu.ganeshman.sms.ui.student

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.KeyboardArrowRight
import androidx.compose.material.icons.outlined.*
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
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.theme.*
import np.edu.ganeshman.sms.ui.common.NepaliDate
import np.edu.ganeshman.sms.ui.common.prettyDate
import java.time.LocalDate
import java.time.YearMonth

fun statusColors(s: String): Pair<Color, Color> = when (s.lowercase()) {
    "present" -> PastelGreen to OnGreen
    "absent" -> PastelPink to OnPink
    "late" -> PastelYellow to OnYellow
    else -> PastelBlue to OnBlue   // leave
}

@Composable
fun StudentAttendanceScreen(onBack: (() -> Unit)? = null) {
    val repo = LocalRepo.current
    val nowBs = remember { NepaliDate.fromAd(LocalDate.now()) ?: Triple(2083, 1, 1) }
    var bsYear by remember { mutableIntStateOf(nowBs.first) }
    var bsMonth by remember { mutableIntStateOf(nowBs.second) }
    var pick by remember { mutableStateOf(false) }

    // The server filters by AD month, and one BS month straddles two AD months: fetch both and merge.
    val state = rememberAsync(bsYear to bsMonth) {
        val first = NepaliDate.toAd(bsYear, bsMonth, 1)
        val last = NepaliDate.toAd(bsYear, bsMonth, NepaliDate.daysIn(bsYear, bsMonth))
        val months = listOf(YearMonth.from(first), YearMonth.from(last)).distinct()
        val parts = months.map { repo.studentAttendance(it.toString()) }
        parts.first().copy(
            records = parts.flatMap { it.records }.filter { r ->
                val d = runCatching { LocalDate.parse(r.date.take(10)) }.getOrNull()
                d != null && !d.isBefore(first) && !d.isAfter(last)
            },
        )
    }
    var overall by remember { mutableStateOf<np.edu.ganeshman.sms.data.AttendanceSummary?>(null) }
    val data = state.load.dataOrNull()
    LaunchedEffect(data) { data?.let { overall = it.summary } }

    if (pick) BsDatePickerDialog(
        NepaliDate.toAd(bsYear, bsMonth, 1), onDismiss = { pick = false }, title = "Jump to month",
    ) { d -> NepaliDate.fromAd(d)?.let { bsYear = it.first; bsMonth = it.second }; pick = false }

    fun step(delta: Int) {
        var m = bsMonth + delta; var y = bsYear
        if (m < 1) { m = 12; y-- } else if (m > 12) { m = 1; y++ }
        if (y < NepaliDate.START_YEAR) return
        bsYear = y; bsMonth = m
    }
    val atCurrent = bsYear > nowBs.first || (bsYear == nowBs.first && bsMonth >= nowBs.second)

    Screen("My Attendance", onBack = onBack, refresh = state.reload) {
        overall?.let { o ->
            Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Ring((o.percentage / 100).toFloat(), pctColor(o.percentage), size = 130.dp, stroke = 12.dp, label = "${num(o.percentage)}%")
                Text("Overall Attendance", style = MaterialTheme.typography.titleSmall)
                Text("${o.present} / ${o.total} Days", style = MaterialTheme.typography.bodyMedium, color = InkSoft)
            }
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                IconTile(Icons.Outlined.CheckCircle, "${o.present}", "Present", PastelGreen, OnGreen, Modifier.weight(1f))
                IconTile(Icons.Outlined.Cancel, "${o.absent}", "Absent", PastelPink, OnPink, Modifier.weight(1f))
            }
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                IconTile(Icons.Outlined.EventBusy, "${o.leave}", "Leave", PastelYellow, OnYellow, Modifier.weight(1f))
                IconTile(Icons.Outlined.CalendarMonth, "${o.total}", "Total Days", PastelBlue, OnBlue, Modifier.weight(1f))
            }
        }
        SectionTitle("Monthly Attendance")
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = { step(-1) }) { Icon(Icons.Outlined.ChevronLeft, "Previous month") }
            Text(
                NepaliDate.monthNames[bsMonth - 1] + " " + bsYear,
                style = MaterialTheme.typography.titleMedium, textAlign = TextAlign.Center,
                modifier = Modifier.weight(1f).clip(androidx.compose.foundation.shape.RoundedCornerShape(10.dp))
                    .clickable { pick = true }.padding(vertical = 8.dp),
            )
            IconButton(onClick = { step(1) }, enabled = !atCurrent) { Icon(Icons.Outlined.ChevronRight, "Next month") }
        }
        AsyncContent(state) { d ->
            Panel { CalendarGrid(bsYear, bsMonth, d.records) }
            if (d.records.isEmpty()) {
                EmptyState(Illustration.Calendar, "No records", "Nothing was marked for this month.")
            } else {
                d.records.sortedByDescending { it.date }.forEach { r ->
                    val (bg, fg) = statusColors(r.status)
                    Panel(padding = 14.dp) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text(prettyDate(r.date), style = MaterialTheme.typography.titleSmall)
                                Text(r.weekday, style = MaterialTheme.typography.bodySmall, color = InkSoft)
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
private fun CalendarGrid(bsYear: Int, bsMonth: Int, records: List<AttendanceRecord>) {
    val byDay = records.mapNotNull { r ->
        val b = runCatching { NepaliDate.fromAd(LocalDate.parse(r.date.take(10))) }.getOrNull()
        if (b != null && b.first == bsYear && b.second == bsMonth) b.third to r else null
    }.toMap()
    val todayBs = remember { NepaliDate.fromAd(LocalDate.now()) }
    val offset = NepaliDate.toAd(bsYear, bsMonth, 1).dayOfWeek.value % 7   // Sunday-first
    val cells = List(offset) { 0 } + (1..NepaliDate.daysIn(bsYear, bsMonth)).toList()
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
                            val isToday = todayBs?.let { it.first == bsYear && it.second == bsMonth && it.third == day } == true
                            Box(
                                Modifier.fillMaxSize().clip(CircleShape).background(bg)
                                    .let { if (isToday) it.border(1.dp, Brand, CircleShape) else it },
                                contentAlignment = Alignment.Center,
                            ) {
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
