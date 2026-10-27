package np.edu.ganeshman.sms.ui.common

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import np.edu.ganeshman.sms.data.ApiException
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.student.NoticeCard
import np.edu.ganeshman.sms.ui.theme.*

@Composable
fun NoticesScreen(student: Boolean, onBack: () -> Unit) {
    val repo = LocalRepo.current
    val state = rememberAsync { if (student) repo.studentNotices() else repo.teacherNotices() }
    Screen("Notices", onBack = onBack) {
        AsyncContent(state) { list ->
            if (list.isEmpty()) EmptyState(Illustration.Notices, "No notices", "New school announcements will show up here.")
            else list.forEach { NoticeCard(it) }
        }
    }
}

@Composable
fun ProfileScreen(onBack: () -> Unit) {
    val repo = LocalRepo.current
    val state = rememberAsync { repo.me() }
    var pwOpen by remember { mutableStateOf(false) }

    Screen("Profile", onBack = onBack) {
        AsyncContent(state) { p ->
            Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Avatar(p.fullName, p.photoUrl, 96.dp)
                Text(p.fullName, style = MaterialTheme.typography.headlineSmall)
                Text(p.code, style = MaterialTheme.typography.bodyMedium, color = InkSoft)
                p.klass?.let { Chip(it.label) }
            }
            Panel {
                KeyValue("Username", p.username)
                KeyValue("Date of birth", listOfNotNull(prettyDate(p.dob).ifBlank { null }, p.dobBs).joinToString("  •  "))
                KeyValue("Gender", p.gender)
                KeyValue("Phone", p.phone)
                KeyValue("Email", p.email)
                KeyValue("Address", p.address)
                KeyValue("Qualification", p.qualification)
                KeyValue("Joined", p.joiningDateBs ?: prettyDate(p.joiningDate))
                KeyValue("Admitted", p.admissionDateBs ?: prettyDate(p.admissionDate))
            }
            if (p.isStudent) Panel {
                Text("Guardian", style = MaterialTheme.typography.titleSmall)
                KeyValue("Name", p.guardianName)
                KeyValue("Phone", p.guardianPhone)
                KeyValue("Relation", p.guardianRelation)
            }
            BigButton("Change password", { pwOpen = true }, outlined = true)
        }
    }
    if (pwOpen) ChangePasswordSheet { pwOpen = false }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ChangePasswordSheet(onDismiss: () -> Unit) {
    val repo = LocalRepo.current
    val scope = rememberCoroutineScope()
    var cur by remember { mutableStateOf("") }
    var new by remember { mutableStateOf("") }
    var conf by remember { mutableStateOf("") }
    var err by remember { mutableStateOf<String?>(null) }
    var errors by remember { mutableStateOf<Map<String, String>>(emptyMap()) }
    var busy by remember { mutableStateOf(false) }
    var done by remember { mutableStateOf(false) }

    ModalBottomSheet(onDismissRequest = onDismiss, containerColor = Surface) {
        Column(
            Modifier.padding(horizontal = 24.dp).padding(bottom = 32.dp).imePadding(),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text("Change password", style = MaterialTheme.typography.titleLarge)
            if (done) {
                Text("Password updated.", color = OnGreen)
                BigButton("Done", onDismiss)
            } else {
                Field(cur, { cur = it }, "Current password", password = true, error = errors["current_password"])
                Field(new, { new = it }, "New password", password = true, error = errors["new_password"])
                Field(conf, { conf = it }, "Confirm new password", password = true, error = errors["confirm_password"])
                err?.let { Text(it, color = Bad, style = MaterialTheme.typography.bodySmall) }
                BigButton("Update password", {
                    busy = true; err = null; errors = emptyMap()
                    scope.launch {
                        try { repo.changePassword(cur, new, conf); done = true }
                        catch (e: ApiException) { err = e.message; errors = e.errors }
                        catch (e: Exception) { err = "Something went wrong." }
                        finally { busy = false }
                    }
                }, loading = busy)
            }
        }
    }
}
