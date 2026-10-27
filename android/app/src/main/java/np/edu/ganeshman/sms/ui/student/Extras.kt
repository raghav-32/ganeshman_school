package np.edu.ganeshman.sms.ui.student

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import np.edu.ganeshman.sms.data.ApiException
import np.edu.ganeshman.sms.ui.common.*
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.theme.*

private val dayNames = mapOf(
    "Sunday" to "आइत", "Monday" to "सोम", "Tuesday" to "मंगल", "Wednesday" to "बुध",
    "Thursday" to "बिही", "Friday" to "शुक्र", "Saturday" to "शनि",
)

@Composable
fun TimetableScreen(onBack: () -> Unit) {
    val repo = LocalRepo.current
    val state = rememberAsync { repo.timetable() }
    var picked by remember { mutableStateOf<String?>(null) }
    Screen("Timetable", onBack = onBack) {
        AsyncContent(state) { t ->
            if (t.days.isEmpty()) {
                EmptyState(Illustration.Calendar, "No timetable", "Your class timetable hasn't been set up yet.")
                return@AsyncContent
            }
            val sel = picked ?: t.days.firstOrNull { it.isToday }?.day ?: t.days.first().day
            PillSelector(t.days.map { it.day }, sel, { dayNames[it] ?: it }) { picked = it }
            val day = t.days.first { it.day == sel }
            if (day.periods.isEmpty()) {
                EmptyState(Illustration.Calendar, "No classes", "Nothing scheduled on $sel.")
            } else day.periods.forEach { p ->
                Panel {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                        Column(Modifier.width(64.dp)) {
                            Text(hhmm(p.startTime), style = MaterialTheme.typography.titleSmall)
                            Text(hhmm(p.endTime), style = MaterialTheme.typography.bodySmall, color = InkSoft)
                        }
                        Column(Modifier.weight(1f)) {
                            Text(p.subjectName, style = MaterialTheme.typography.titleSmall)
                            Text(p.teacherName, style = MaterialTheme.typography.bodySmall, color = InkSoft)
                        }
                        if (!p.roomNumber.isNullOrBlank()) Chip("Room ${p.roomNumber}", PastelBlue, OnBlue)
                    }
                }
            }
        }
    }
}

@Composable
fun ExamsScreen(onBack: () -> Unit) {
    val repo = LocalRepo.current
    val state = rememberAsync { repo.studentExams() }
    Screen("Exams", onBack = onBack) {
        AsyncContent(state) { exams ->
            if (exams.isEmpty()) {
                EmptyState(Illustration.Results, "No exams", "Upcoming exams and schedules will appear here.")
            } else exams.forEach { e ->
                Panel {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text(e.examName, style = MaterialTheme.typography.titleMedium)
                            Text(
                                (e.startDateBs ?: prettyDate(e.startDate)) + " – " + (e.endDateBs ?: prettyDate(e.endDate)),
                                style = MaterialTheme.typography.bodySmall, color = InkSoft,
                            )
                        }
                        Chip(e.status.replaceFirstChar { it.uppercase() }, PastelLilac, OnLilac)
                    }
                    e.schedule.forEach { s ->
                        Row(Modifier.fillMaxWidth().padding(top = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text(s.subjectName, style = MaterialTheme.typography.titleSmall)
                                Text(
                                    listOfNotNull(s.dateBs ?: prettyDate(s.date), s.weekday.ifBlank { null },
                                        s.startTime?.let { hhmm(it) + (s.endTime?.let { e2 -> "–" + hhmm(e2) } ?: "") }).joinToString(" • "),
                                    style = MaterialTheme.typography.bodySmall, color = InkSoft,
                                )
                            }
                            if (s.isToday) Chip("Today", PastelYellow, OnYellow)
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun FeesScreen(onBack: () -> Unit) {
    val repo = LocalRepo.current
    val state = rememberAsync { repo.fees() }
    Screen("Fees", onBack = onBack) {
        AsyncContent(state) { d ->
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                StatTile(money(d.summary.totalFee), "Total", PastelBlue, OnBlue, Modifier.weight(1f))
                StatTile(money(d.summary.totalPaid), "Paid", PastelGreen, OnGreen, Modifier.weight(1f))
                StatTile(money(d.summary.totalDue), "Due", PastelYellow, OnYellow, Modifier.weight(1f))
            }
            if (d.fees.isEmpty()) {
                EmptyState(Illustration.Money, "No fees", "No fee structure has been assigned to your class.")
            }
            d.fees.forEach { f ->
                Panel {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(f.feeType, style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
                        val paid = f.due <= 0.0
                        Chip(if (paid) "Paid" else "Due ${money(f.due)}", if (paid) PastelGreen else PastelYellow, if (paid) OnGreen else OnYellow)
                    }
                    Bar(f.percentage / 100f, Brand)
                    Text("${money(f.paid)} of ${money(f.amount)}", style = MaterialTheme.typography.bodySmall, color = InkSoft)
                }
            }
            if (d.payments.isNotEmpty()) {
                SectionTitle("Payment history")
                d.payments.forEach { p ->
                    Panel(padding = 14.dp) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            IconBubble(Icons.Outlined.Receipt, PastelGreen, OnGreen, 40.dp)
                            Spacer(Modifier.width(12.dp))
                            Column(Modifier.weight(1f)) {
                                Text(p.feeType, style = MaterialTheme.typography.titleSmall)
                                Text(
                                    listOfNotNull(p.paymentDateBs ?: prettyDate(p.paymentDate), p.receiptNumber).joinToString(" • "),
                                    style = MaterialTheme.typography.bodySmall, color = InkSoft,
                                )
                            }
                            Text(money(p.amountPaid), style = MaterialTheme.typography.titleSmall, color = OnGreen)
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun LibraryScreen(onBack: () -> Unit) {
    val repo = LocalRepo.current
    val scope = rememberCoroutineScope()
    var query by remember { mutableStateOf("") }
    var submitted by remember { mutableStateOf("") }
    val state = rememberAsync(submitted) { repo.library(submitted) }
    var message by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }

    fun act(block: suspend () -> Unit) {
        if (busy) return
        busy = true
        scope.launch {
            try { block(); message = null; state.reload() }
            catch (e: ApiException) { message = e.message }
            catch (e: Exception) { message = "Something went wrong." }
            finally { busy = false }
        }
    }

    Screen("Library", onBack = onBack) {
        Field(
            query, { query = it }, "Search books", trailing = {
                IconButton(onClick = { submitted = query.trim() }) { Icon(Icons.Outlined.Search, "Search") }
            },
        )
        message?.let { Text(it, color = Bad, style = MaterialTheme.typography.bodySmall) }
        AsyncContent(state) { d ->
            if (d.myIssued.isNotEmpty()) {
                SectionTitle("Borrowed by me")
                d.myIssued.forEach { b ->
                    Panel {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text(b.title, style = MaterialTheme.typography.titleSmall)
                                Text("Due ${b.dueDateBs ?: prettyDate(b.dueDate)}", style = MaterialTheme.typography.bodySmall, color = InkSoft)
                            }
                            if (b.isOverdue) Chip("Fine Rs. ${b.estimatedFine}", PastelPink, OnPink)
                            else Chip("${b.daysLeft}d left", PastelGreen, OnGreen)
                        }
                    }
                }
            }
            if (d.myRequests.isNotEmpty()) {
                SectionTitle("My requests")
                d.myRequests.forEach { r ->
                    Panel {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text(r.title, style = MaterialTheme.typography.titleSmall)
                                Text(if (r.available) "Available — collect from library" else "Waiting for a copy",
                                    style = MaterialTheme.typography.bodySmall, color = InkSoft)
                            }
                            TextButton(onClick = { act { repo.cancelRequest(r.id) } }, enabled = !busy) { Text("Cancel", color = Bad) }
                        }
                    }
                }
            }
            SectionTitle("Catalogue")
            if (d.books.isEmpty()) {
                EmptyState(Illustration.Books, "No books found", "Try a different title or author.")
            } else d.books.forEach { b ->
                Panel {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        IconBubble(Icons.Outlined.MenuBook, PastelPeach, OnPeach)
                        Column(Modifier.weight(1f)) {
                            Text(b.title, style = MaterialTheme.typography.titleSmall)
                            Text(listOfNotNull(b.author, b.category).joinToString(" • "), style = MaterialTheme.typography.bodySmall, color = InkSoft)
                            Text("${b.availableCopies}/${b.totalCopies} available" + (b.shelfLocation?.let { " • Shelf $it" } ?: ""),
                                style = MaterialTheme.typography.labelSmall, color = InkSoft)
                        }
                        if (b.requested) Chip("Requested", PastelBlue, OnBlue)
                        else TextButton(onClick = { act { repo.requestBook(b.id) } }, enabled = !busy) { Text("Request", color = Brand) }
                    }
                }
            }
        }
    }
}

@Composable
fun StudentMore(nav: androidx.navigation.NavHostController) {
    MoreList(
        listOf(
            MoreItem("Timetable", "Weekly class schedule", Icons.Outlined.Schedule, PastelBlue, OnBlue, Routes.S_TIMETABLE),
            MoreItem("Exams", "Schedules and status", Icons.Outlined.Quiz, PastelLilac, OnLilac, Routes.S_EXAMS),
            MoreItem("Fees", "Dues and payments", Icons.Outlined.Payments, PastelYellow, OnYellow, Routes.S_FEES),
            MoreItem("Library", "Borrow and request books", Icons.Outlined.MenuBook, PastelPeach, OnPeach, Routes.S_LIBRARY),
            MoreItem("Notices", "School announcements", Icons.Outlined.Campaign, PastelPink, OnPink, Routes.S_NOTICES),
            MoreItem("Profile", "Your details and password", Icons.Outlined.Person, PastelGreen, OnGreen, Routes.S_PROFILE),
        ),
        nav,
    )
}
