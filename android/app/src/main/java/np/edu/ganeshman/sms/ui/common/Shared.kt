package np.edu.ganeshman.sms.ui.common

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Logout
import androidx.compose.material.icons.outlined.*
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
fun NoticesScreen(student: Boolean, onBack: (() -> Unit)? = null) {
    val repo = LocalRepo.current
    val viewedBefore = remember { repo.session.viewedNoticeId }   // notices newer than this are "New"
    val state = rememberAsync { if (student) repo.studentNotices() else repo.teacherNotices() }
    LaunchedEffect(state.load is Load.Ready<*>) { if (state.load is Load.Ready<*>) repo.session.markNoticesViewed() }
    Screen("Notices", onBack = onBack, refresh = state.reload) {
        AsyncContent(state) { list ->
            if (list.isEmpty()) EmptyState(Illustration.Notices, "No notices", "New school announcements will show up here.")
            else list.forEach { NoticeCard(it, isNew = viewedBefore >= 0 && it.id > viewedBefore) }
        }
    }
}

@Composable
fun ProfileScreen(onBack: () -> Unit, onSettings: () -> Unit = {}) {
    val repo = LocalRepo.current
    val state = rememberAsync { repo.me() }
    var pwOpen by remember { mutableStateOf(false) }
    var signOut by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()

    Screen(
        "Profile", onBack = onBack,
        actions = {
            IconButton(onClick = onSettings) { Icon(Icons.Outlined.Settings, contentDescription = "Settings") }
        },
    ) {
        AsyncContent(state) { p ->
            Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Avatar(p.fullName, p.photoUrl, 96.dp)
                Text(p.fullName, style = MaterialTheme.typography.headlineSmall)
                Text(listOfNotNull(p.code.ifBlank { null }, p.klass?.label).joinToString(" • "), style = MaterialTheme.typography.bodyMedium, color = InkSoft)
            }
            Panel(padding = 8.dp) {
                InfoRow(Icons.Outlined.Person, "Full Name", p.fullName)
                InfoRow(Icons.Outlined.AccountCircle, "Username", p.username)
                InfoRow(Icons.Outlined.School, "Class", p.klass?.label)
                InfoRow(Icons.Outlined.Badge, "ID", p.code)
                InfoRow(Icons.Outlined.Cake, "Date of Birth", listOfNotNull(prettyDate(p.dob).ifBlank { null }, p.dobBs?.let { "$it (BS)" }).joinToString("  •  "))
                InfoRow(Icons.Outlined.Wc, "Gender", p.gender)
                InfoRow(Icons.Outlined.Call, "Contact", p.phone)
                InfoRow(Icons.Outlined.Email, "Email", p.email)
                InfoRow(Icons.Outlined.LocationOn, "Address", p.address)
                InfoRow(Icons.Outlined.WorkspacePremium, "Qualification", p.qualification)
                InfoRow(Icons.Outlined.Event, "Joined", p.joiningDateBs ?: prettyDate(p.joiningDate))
                InfoRow(Icons.Outlined.Event, "Admitted", p.admissionDateBs ?: prettyDate(p.admissionDate))
            }
            if (p.isStudent) Panel(padding = 8.dp) {
                Text("Guardian", style = MaterialTheme.typography.titleSmall, modifier = Modifier.padding(start = 8.dp, top = 8.dp))
                InfoRow(Icons.Outlined.Person, "Name", p.guardianName)
                InfoRow(Icons.Outlined.Call, "Phone", p.guardianPhone)
                InfoRow(Icons.Outlined.FamilyRestroom, "Relation", p.guardianRelation)
            }
            BigButton("Change password", { pwOpen = true }, outlined = true)
            Panel(color = PastelPink, onClick = { signOut = true }, padding = 16.dp) {
                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
                    Icon(Icons.AutoMirrored.Outlined.Logout, null, tint = OnPink)
                    Spacer(Modifier.width(10.dp))
                    Text("Sign out", style = MaterialTheme.typography.titleSmall, color = OnPink)
                }
            }
        }
    }
    if (pwOpen) ChangePasswordSheet { pwOpen = false }
    if (signOut) SignOutDialog({ signOut = false }) { scope.launch { repo.logout() } }
}

@Composable
private fun InfoRow(icon: androidx.compose.ui.graphics.vector.ImageVector, label: String, value: String?) {
    if (value.isNullOrBlank()) return
    Row(Modifier.fillMaxWidth().padding(horizontal = 8.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
        Icon(icon, null, tint = InkSoft, modifier = Modifier.size(22.dp))
        Spacer(Modifier.width(14.dp))
        Column {
            Text(label, style = MaterialTheme.typography.labelSmall, color = InkSoft)
            Text(value, style = MaterialTheme.typography.bodyMedium)
        }
    }
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
