package np.edu.ganeshman.sms.ui.teacher

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Save
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import np.edu.ganeshman.sms.data.ApiException
import np.edu.ganeshman.sms.ui.common.LocalRepo
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.theme.*

@Composable
fun MarksListScreen(onOpen: (Int) -> Unit) {
    val repo = LocalRepo.current
    val state = rememberAsync { repo.teacherExams() }
    Screen("Marks") {
        AsyncContent(state) { exams ->
            if (exams.isEmpty()) {
                EmptyState(Illustration.Results, "No exams", "Exams for your classes will appear here.")
            } else exams.forEach { e ->
                Panel(onClick = { onOpen(e.id) }) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text(e.examName, style = MaterialTheme.typography.titleMedium)
                            Text(
                                listOfNotNull(e.klass.label.ifBlank { null }, e.startDateBs).joinToString(" • "),
                                style = MaterialTheme.typography.bodySmall, color = InkSoft,
                            )
                        }
                        if (e.canEnterMarks) Chip("Enter marks", PastelGreen, OnGreen)
                        else Chip("View only", PastelYellow, OnYellow)
                    }
                    if (e.resultsPublished) Chip("Results published", PastelBlue, OnBlue)
                }
            }
        }
    }
}

@Composable
fun MarksEntryScreen(examId: Int, onBack: () -> Unit) {
    val repo = LocalRepo.current
    val scope = rememberCoroutineScope()

    var subjectId by remember { mutableStateOf<Int?>(null) }
    val state = rememberAsync(examId to subjectId) { repo.marks(examId, subjectId) }
    val data = state.load.dataOrNull()

    val values = remember { mutableStateMapOf<Int, String>() }
    var saving by remember { mutableStateOf(false) }
    var msg by remember { mutableStateOf<String?>(null) }
    var ok by remember { mutableStateOf(false) }

    LaunchedEffect(data) {
        values.clear()
        data?.students?.forEach { s -> values[s.id] = s.marksObtained?.let { num(it) } ?: "" }
    }

    fun save() {
        val subj = data?.subject ?: return
        val out = mutableMapOf<Int, Double>()
        for ((id, text) in values) {
            if (text.isBlank()) continue
            val v = text.toDoubleOrNull()
            if (v == null || v < 0 || v > subj.fullMarks) {
                ok = false
                msg = "Marks must be between 0 and ${subj.fullMarks}."
                return
            }
            out[id] = v
        }
        if (out.isEmpty()) { ok = false; msg = "Enter at least one mark."; return }
        saving = true; msg = null
        scope.launch {
            try {
                val r = repo.saveMarks(examId, subj.id, out)
                ok = true; msg = "Saved marks for ${r.saved} students."
                state.reload()
            } catch (e: ApiException) { ok = false; msg = e.message }
            catch (e: Exception) { ok = false; msg = "Something went wrong." }
            finally { saving = false }
        }
    }

    FabEffect(
        if (data?.canEnterMarks == true && data.subject != null && data.students.isNotEmpty())
            FabSpec(Icons.Outlined.Save, "Save", loading = saving, onClick = ::save) else null
    )

    Screen("Enter marks", onBack = onBack) {
        AsyncContent(state) { d ->
            Panel(color = BrandSoft) {
                Text(d.exam.examName, style = MaterialTheme.typography.titleMedium, color = BrandDark)
                Text(d.exam.klass.label, style = MaterialTheme.typography.bodySmall, color = BrandDark)
            }
            if (d.subjects.isEmpty()) {
                EmptyState(Illustration.Results, "No subjects", "This class has no subjects set up.")
                return@AsyncContent
            }
            Text("Subject", style = MaterialTheme.typography.labelLarge, color = InkSoft)
            PillSelector(d.subjects.map { it.id }, d.subject?.id ?: subjectId, { id -> d.subjects.first { it.id == id }.subjectName }) {
                subjectId = it; msg = null
            }
            if (!d.canEnterMarks) {
                Panel(color = PastelYellow) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        Icon(Icons.Outlined.Lock, null, tint = OnYellow)
                        Text("Only the class teacher can enter marks.", color = OnYellow, style = MaterialTheme.typography.bodyMedium)
                    }
                }
            }
            msg?.let { Text(it, color = if (ok) OnGreen else Bad, style = MaterialTheme.typography.bodyMedium) }
            val subj = d.subject
            if (subj == null) {
                EmptyState(Illustration.Results, "Pick a subject", "Choose a subject above to see or enter marks.")
            } else {
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Chip("Full ${subj.fullMarks}", PastelBlue, OnBlue)
                    Chip("Pass ${subj.passMarks}", PastelGreen, OnGreen)
                }
                d.students.forEach { s ->
                    Panel(padding = 14.dp) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            Column(Modifier.weight(1f)) {
                                Text(s.fullName, style = MaterialTheme.typography.titleSmall)
                                Text(s.studentCode, style = MaterialTheme.typography.bodySmall, color = InkSoft)
                            }
                            if (!s.grade.isNullOrBlank()) Chip(s.grade, PastelLilac, OnLilac)
                            OutlinedTextField(
                                value = values[s.id] ?: "",
                                onValueChange = { values[s.id] = it.filter { c -> c.isDigit() || c == '.' }.take(6) },
                                enabled = d.canEnterMarks,
                                singleLine = true,
                                modifier = Modifier.width(96.dp),
                                shape = androidx.compose.foundation.shape.RoundedCornerShape(14.dp),
                                keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(keyboardType = KeyboardType.Decimal),
                                placeholder = { Text("—") },
                            )
                        }
                    }
                }
            }
        }
    }
}
