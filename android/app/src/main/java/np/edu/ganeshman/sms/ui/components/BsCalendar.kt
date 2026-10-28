package np.edu.ganeshman.sms.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.ChevronLeft
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.ExpandLess
import androidx.compose.material.icons.outlined.ExpandMore
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import np.edu.ganeshman.sms.ui.common.NepaliDate
import np.edu.ganeshman.sms.ui.theme.*
import java.time.LocalDate

/** Calendar-style date picker in Bikram Sambat (BS). Returns the chosen day as an AD LocalDate (the server stores AD). */
@Composable
fun BsDatePickerDialog(
    initial: LocalDate, onDismiss: () -> Unit, title: String = "Select date",
    allowFuture: Boolean = false, onPick: (LocalDate) -> Unit,
) {
    val today = remember { LocalDate.now() }
    val todayBs = remember { NepaliDate.fromAd(today) ?: Triple(NepaliDate.endYear, 12, 30) }
    val lastYear = if (allowFuture) NepaliDate.endYear else todayBs.first
    val start = NepaliDate.fromAd(initial) ?: Triple(2070, 1, 1)
    var year by remember { mutableIntStateOf(start.first) }
    var month by remember { mutableIntStateOf(start.second) }
    var yearMode by remember { mutableStateOf(false) }
    val sel = Triple(start.first, start.second, start.third)
    var chosen by remember { mutableStateOf(sel) }

    fun step(delta: Int) {
        var m = month + delta; var y = year
        if (m < 1) { m = 12; y-- } else if (m > 12) { m = 1; y++ }
        if (y < NepaliDate.START_YEAR || y > lastYear) return
        year = y; month = m
    }

    androidx.compose.material3.AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = Surface,
        title = {
            Column {
                Text(title, style = MaterialTheme.typography.labelLarge, color = InkSoft)
                Text(
                    "${NepaliDate.monthNames[chosen.second - 1]} ${chosen.third}, ${chosen.first}",
                    style = MaterialTheme.typography.headlineSmall,
                )
            }
        },
        text = {
            Column {
                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                    Row(
                        Modifier.clip(androidx.compose.foundation.shape.RoundedCornerShape(10.dp))
                            .clickable { yearMode = !yearMode }.padding(horizontal = 8.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text("${NepaliDate.monthNames[month - 1]} $year", style = MaterialTheme.typography.titleSmall)
                        Icon(
                            if (yearMode) Icons.Outlined.ExpandLess else Icons.Outlined.ExpandMore, null,
                            Modifier.size(20.dp),
                        )
                    }
                    Spacer(Modifier.weight(1f))
                    if (!yearMode) {
                        androidx.compose.material3.IconButton(onClick = { step(-1) }) { Icon(Icons.Outlined.ChevronLeft, "Previous month") }
                        androidx.compose.material3.IconButton(onClick = { step(1) }) { Icon(Icons.Outlined.ChevronRight, "Next month") }
                    }
                }
                if (yearMode) {
                    val years = remember { (NepaliDate.START_YEAR..lastYear).toList().reversed() }
                    val st = androidx.compose.foundation.lazy.rememberLazyListState()
                    LaunchedEffect(Unit) { years.indexOf(year).let { if (it > 2) st.scrollToItem(it - 2) } }
                    androidx.compose.foundation.lazy.LazyColumn(Modifier.height(260.dp), state = st) {
                        items(years.size) { i ->
                            val y = years[i]; val on = y == year
                            Box(
                                Modifier.fillMaxWidth().padding(vertical = 2.dp).clip(androidx.compose.foundation.shape.RoundedCornerShape(12.dp))
                                    .background(if (on) Brand else androidx.compose.ui.graphics.Color.Transparent)
                                    .clickable { year = y; yearMode = false }.padding(vertical = 10.dp),
                                contentAlignment = Alignment.Center,
                            ) { Text(y.toString(), color = if (on) OnBrand else Ink) }
                        }
                    }
                } else {
                    Row(Modifier.fillMaxWidth()) {
                        listOf("S", "M", "T", "W", "T", "F", "S").forEach {
                            Text(it, Modifier.weight(1f), textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                                style = MaterialTheme.typography.labelMedium, color = InkSoft)
                        }
                    }
                    Spacer(Modifier.height(4.dp))
                    val lead = NepaliDate.toAd(year, month, 1).dayOfWeek.value % 7     // Sunday = 0
                    val days = NepaliDate.daysIn(year, month)
                    val cells = lead + days
                    for (r in 0 until (cells + 6) / 7) {
                        Row(Modifier.fillMaxWidth()) {
                            for (c in 0 until 7) {
                                val d = r * 7 + c - lead + 1
                                Box(Modifier.weight(1f).aspectRatio(1f).padding(2.dp), contentAlignment = Alignment.Center) {
                                    if (d in 1..days) {
                                        val future = !allowFuture && NepaliDate.toAd(year, month, d).isAfter(today)
                                        val on = chosen == Triple(year, month, d)
                                        val isToday = Triple(year, month, d) == todayBs
                                        Box(
                                            Modifier.fillMaxSize().clip(CircleShape)
                                                .background(if (on) Brand else androidx.compose.ui.graphics.Color.Transparent)
                                                .let { if (isToday && !on) it.border(1.dp, Brand, CircleShape) else it }
                                                .let { if (future) it else it.clickable { chosen = Triple(year, month, d) } },
                                            contentAlignment = Alignment.Center,
                                        ) {
                                            Text(
                                                d.toString(), style = MaterialTheme.typography.bodyMedium,
                                                color = when { on -> OnBrand; future -> InkSoft.copy(alpha = 0.4f); else -> Ink },
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                                    }
            }
        },
        confirmButton = {
            androidx.compose.material3.TextButton(onClick = {
                onPick(NepaliDate.toAd(chosen.first, chosen.second, chosen.third))
            }) { Text("OK") }
        },
        dismissButton = { androidx.compose.material3.TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}
