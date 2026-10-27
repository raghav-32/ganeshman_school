package np.edu.ganeshman.sms.ui.student

import androidx.compose.foundation.layout.*
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import np.edu.ganeshman.sms.ui.common.LocalRepo
import np.edu.ganeshman.sms.ui.common.prettyDate
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.theme.*

@Composable
fun ResultsScreen(onOpen: (Int) -> Unit) {
    val repo = LocalRepo.current
    val state = rememberAsync { repo.results() }
    Screen("Results") {
        AsyncContent(state) { list ->
            if (list.isEmpty()) {
                EmptyState(Illustration.Results, "No results yet", "Published exam results will appear here.")
            } else list.forEach { r ->
                Panel(onClick = { onOpen(r.examId) }) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text(r.examName, style = MaterialTheme.typography.titleMedium)
                            Text(
                                listOfNotNull(r.examType.ifBlank { null }, r.startDateBs ?: prettyDate(r.startDate)).joinToString(" • "),
                                style = MaterialTheme.typography.bodySmall, color = InkSoft,
                            )
                        }
                        Chip("${num(r.percentage)}%", PastelGreen, OnGreen)
                    }
                    Bar((r.percentage / 100).toFloat(), pctColor(r.percentage))
                    Text("${num(r.totalObtained)} / ${num(r.totalFull)} • ${r.subjectCount} subjects",
                        style = MaterialTheme.typography.bodySmall, color = InkSoft)
                }
            }
        }
    }
}

@Composable
fun ResultDetailScreen(examId: Int, onBack: () -> Unit) {
    val repo = LocalRepo.current
    val state = rememberAsync(examId) { repo.resultDetail(examId) }
    Screen("Result", onBack = onBack) {
        AsyncContent(state) { r ->
            Panel(color = BrandSoft) {
                Text(r.examName, style = MaterialTheme.typography.titleLarge, color = BrandDark)
                Row(horizontalArrangement = Arrangement.spacedBy(16.dp), verticalAlignment = Alignment.CenterVertically) {
                    Ring((r.percentage / 100).toFloat(), if (r.passed) Brand else Bad, label = "${num(r.percentage)}%")
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text("${num(r.totalObtained)} / ${num(r.totalFull)}", style = MaterialTheme.typography.titleMedium)
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            Chip("Grade ${r.grade}", PastelLilac, OnLilac)
                            if (r.passed) Chip("Passed", PastelGreen, OnGreen) else Chip("Failed", PastelPink, OnPink)
                        }
                    }
                }
            }
            SectionTitle("Subjects")
            r.subjects.forEach { s ->
                Panel {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(s.subjectName, style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
                        Chip(s.grade, if (s.passed) PastelGreen else PastelPink, if (s.passed) OnGreen else OnPink)
                    }
                    Bar((s.percentage / 100).toFloat(), pctColor(s.percentage))
                    Text("${num(s.marksObtained)} / ${s.fullMarks}  (pass ${s.passMarks})",
                        style = MaterialTheme.typography.bodySmall, color = InkSoft)
                    if (!s.remarks.isNullOrBlank()) Text(s.remarks, style = MaterialTheme.typography.bodySmall, color = InkSoft)
                }
            }
        }
    }
}
