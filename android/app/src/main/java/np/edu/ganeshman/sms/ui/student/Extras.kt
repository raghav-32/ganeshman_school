package np.edu.ganeshman.sms.ui.student

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.foundation.background
import androidx.compose.ui.draw.clip
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

private val periodColors = listOf(
    PastelBlue to OnBlue, PastelPeach to OnPeach, PastelGreen to OnGreen, PastelLilac to OnLilac, PastelYellow to OnYellow,
)

@Composable
fun TimetableScreen(onBack: (() -> Unit)? = null) {
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
            } else day.periods.forEachIndexed { i, p ->
                val (bg, fg) = periodColors[i % periodColors.size]
                Panel {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                        Box(
                            Modifier.size(40.dp).clip(androidx.compose.foundation.shape.CircleShape).background(bg),
                            contentAlignment = Alignment.Center,
                        ) { Text("${i + 1}", style = MaterialTheme.typography.titleSmall, color = fg) }
                        Column(Modifier.weight(1f)) {
                            Text(p.subjectName, style = MaterialTheme.typography.titleSmall)
                            Text(hhmm(p.startTime) + " - " + hhmm(p.endTime), style = MaterialTheme.typography.bodySmall, color = InkSoft)
                            val sub = listOfNotNull(p.roomNumber?.takeIf { it.isNotBlank() }?.let { "Room $it" }, p.teacherName.ifBlank { null })
                            if (sub.isNotEmpty()) Text(sub.joinToString(" • "), style = MaterialTheme.typography.bodySmall, color = InkSoft)
                        }
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
            Panel(color = BrandSoft, padding = 20.dp) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Total Due", style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
                    if (d.summary.totalDue <= 0.0) Chip("No dues", PastelGreen, OnGreen) else Chip("Payment pending", PastelYellow, OnYellow)
                }
                Text(money(d.summary.totalDue), style = MaterialTheme.typography.headlineLarge, color = BrandDark)
            }
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                IconTile(Icons.Outlined.Receipt, money(d.summary.totalFee), "Total Fee", PastelBlue, OnBlue, Modifier.weight(1f))
                IconTile(Icons.Outlined.CheckCircle, money(d.summary.totalPaid), "Paid", PastelGreen, OnGreen, Modifier.weight(1f))
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
                SectionTitle("Recent Transactions")
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

    var tab by remember { mutableStateOf("Books") }
    Screen("Library", onBack = onBack) {
        PillSelector(listOf("Books", "Borrowed"), tab, { it }) { tab = it }
        message?.let { Text(it, color = Bad, style = MaterialTheme.typography.bodySmall) }
        if (tab == "Books") Field(
            query, { query = it }, "Search books", trailing = {
                IconButton(onClick = { submitted = query.trim() }) { Icon(Icons.Outlined.Search, "Search") }
            },
        )
        AsyncContent(state) { d ->
            if (tab == "Borrowed") {
                if (d.myIssued.isEmpty() && d.myRequests.isEmpty()) {
                    EmptyState(Illustration.Books, "Nothing borrowed", "Books you borrow or request will appear here.")
                }
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
                if (d.myRequests.isNotEmpty()) SectionTitle("My requests")
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
            } else {
                if (d.books.isEmpty()) {
                    EmptyState(Illustration.Books, "No books found", "Try a different title or author.")
                } else d.books.forEach { b ->
                    Panel {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            IconBubble(Icons.Outlined.MenuBook, PastelBlue, OnBlue)
                            Column(Modifier.weight(1f)) {
                                Text(b.title, style = MaterialTheme.typography.titleSmall)
                                Text(listOfNotNull(b.author, b.category).joinToString(" • "), style = MaterialTheme.typography.bodySmall, color = InkSoft)
                                Text("${b.availableCopies}/${b.totalCopies} available" + (b.shelfLocation?.let { " • Shelf $it" } ?: ""),
                                    style = MaterialTheme.typography.labelSmall, color = InkSoft)
                            }
                            if (b.requested) Chip("Requested", BrandSoft, BrandDark)
                            else TextButton(onClick = { act { repo.requestBook(b.id) } }, enabled = !busy) { Text("View / Request", color = Brand) }
                        }
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
            MoreItem("My Attendance", "Overall and monthly", Icons.Outlined.EventAvailable, PastelGreen, OnGreen, Routes.S_ATT),
            MoreItem("My Results", "Published exam results", Icons.Outlined.EmojiEvents, PastelLilac, OnLilac, Routes.S_RESULTS),
            MoreItem("Exam Schedule", "Dates and subjects", Icons.Outlined.Quiz, PastelBlue, OnBlue, Routes.S_EXAMS),
            MoreItem("My Fees", "Dues and payments", Icons.Outlined.Payments, PastelYellow, OnYellow, Routes.S_FEES),
            MoreItem("Library", "Borrow and request books", Icons.Outlined.MenuBook, PastelPeach, OnPeach, Routes.S_LIBRARY),
            MoreItem("My Profile", "Details and password", Icons.Outlined.Person, PastelPink, OnPink, Routes.S_PROFILE),
        ),
        nav,
    )
}
