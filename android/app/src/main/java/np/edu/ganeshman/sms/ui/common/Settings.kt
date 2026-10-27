package np.edu.ganeshman.sms.ui.common

import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.Image
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.KeyboardArrowRight
import androidx.compose.material.icons.automirrored.outlined.Logout
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import np.edu.ganeshman.sms.BuildConfig
import np.edu.ganeshman.sms.R
import np.edu.ganeshman.sms.notify.NoticeAlerts
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.theme.*

const val WEBSITE_URL = "https://gms-sms.42web.io/home.php"
const val RELEASES_URL = "https://github.com/raghav-32/ganeshman_school/releases/tag/apk-latest"

fun openLink(ctx: Context, url: String) {
    try { ctx.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) } catch (_: Exception) { }
}

fun shareApp(ctx: Context) {
    val send = Intent(Intent.ACTION_SEND).apply {
        type = "text/plain"
        putExtra(Intent.EXTRA_SUBJECT, "Ganeshman School app")
        putExtra(Intent.EXTRA_TEXT, "Ganeshman Singh Academy student & teacher app:\n$RELEASES_URL")
    }
    try { ctx.startActivity(Intent.createChooser(send, "Share app").addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) } catch (_: Exception) { }
}

/** Confirmation before signing out. */
@Composable
fun SignOutDialog(onDismiss: () -> Unit, onConfirm: () -> Unit) {
    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = Surface,
        title = { Text("Sign out?") },
        text = { Text("You will need to sign in again to see your school information.") },
        confirmButton = { TextButton(onClick = { onDismiss(); onConfirm() }) { Text("Sign out", color = Bad) } },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}

@Composable
private fun Row3(icon: ImageVector, title: String, subtitle: String? = null, bg: androidx.compose.ui.graphics.Color = PastelBlue,
                 fg: androidx.compose.ui.graphics.Color = OnBlue, onClick: (() -> Unit)? = null, trailing: @Composable (() -> Unit)? = null) {
    Row(
        Modifier.fillMaxWidth()
            .let { if (onClick != null) it.clickable(onClick = onClick) else it }
            .padding(horizontal = 12.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        IconBubble(icon, bg, fg, 40.dp)
        Column(Modifier.weight(1f)) {
            Text(title, style = MaterialTheme.typography.titleSmall)
            if (subtitle != null) Text(subtitle, style = MaterialTheme.typography.bodySmall, color = InkSoft)
        }
        if (trailing != null) trailing()
        else if (onClick != null) Icon(Icons.AutoMirrored.Outlined.KeyboardArrowRight, null, tint = InkSoft)
    }
}

@Composable
private fun SwitchRow(icon: ImageVector, title: String, subtitle: String, bg: androidx.compose.ui.graphics.Color, fg: androidx.compose.ui.graphics.Color,
                      checked: Boolean, onChange: (Boolean) -> Unit) {
    Row3(icon, title, subtitle, bg, fg, onClick = { onChange(!checked) }, trailing = {
        Switch(checked = checked, onCheckedChange = onChange)
    })
}

@Composable
fun SettingsScreen(onBack: () -> Unit, onAbout: () -> Unit, onSignOut: () -> Unit) {
    val ctx = LocalContext.current
    val repo = LocalRepo.current
    val st = LocalSettings.current
    val scope = rememberCoroutineScope()

    val dark by st.darkMode.collectAsState()
    val notif by st.notifications.collectAsState()
    var confirm by remember { mutableStateOf(false) }
    var cleared by remember { mutableStateOf(false) }

    Screen("Settings", onBack = onBack) {
        SectionTitle("Appearance")
        Panel(padding = 4.dp) {
            SwitchRow(Icons.Outlined.DarkMode, "Dark mode", "Easier on the eyes in low light", PastelLilac, OnLilac, dark) { st.setDarkMode(it) }
        }

        SectionTitle("Notifications")
        Panel(padding = 4.dp) {
            SwitchRow(Icons.Outlined.NotificationsActive, "Notice alerts", "Get a notification when the school posts a notice", PastelGreen, OnGreen, notif) {
                st.setNotifications(it)
            }
            if (notif && !NoticeAlerts.canNotify(ctx)) {
                Text(
                    "Notifications are blocked for this app in your phone's settings.",
                    style = MaterialTheme.typography.bodySmall, color = Bad,
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 4.dp),
                )
                TextButton(onClick = {
                    ctx.startActivity(
                        Intent(android.provider.Settings.ACTION_APP_NOTIFICATION_SETTINGS)
                            .putExtra(android.provider.Settings.EXTRA_APP_PACKAGE, ctx.packageName)
                            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                    )
                }) { Text("Open phone settings", color = Brand) }
            }
        }

        SectionTitle("General")
        Panel(padding = 4.dp) {
            Row3(Icons.Outlined.CleaningServices, "Clear cached data", if (cleared) "Done — data will reload fresh" else "Fixes old information showing",
                PastelBlue, OnBlue, onClick = { repo.api.clearCache(); cleared = true })
            Row3(Icons.Outlined.SystemUpdate, "Check for updates", "Open the latest version download page", PastelPeach, OnPeach,
                onClick = { openLink(ctx, RELEASES_URL) })
            Row3(Icons.Outlined.Share, "Share this app", "Send the download link to a friend", PastelPink, OnPink,
                onClick = { shareApp(ctx) })
            Row3(Icons.Outlined.Language, "School website", "gms-sms.42web.io", PastelGreen, OnGreen,
                onClick = { openLink(ctx, WEBSITE_URL) })
            Row3(Icons.Outlined.HelpOutline, "Help & support", "Contact the school office through the website", PastelLilac, OnLilac,
                onClick = { openLink(ctx, WEBSITE_URL) })
            Row3(Icons.Outlined.Info, "About app", "Version ${BuildConfig.VERSION_NAME}", PastelBlue, OnBlue, onClick = onAbout)
        }

        Panel(padding = 4.dp, onClick = { confirm = true }) {
            Row3(Icons.AutoMirrored.Outlined.Logout, "Sign out", null, PastelPink, OnPink, onClick = { confirm = true }, trailing = {})
        }
    }
    if (confirm) SignOutDialog({ confirm = false }) { scope.launch { repo.logout() }; onSignOut() }
}

@Composable
fun AboutScreen(onBack: () -> Unit) {
    val ctx = LocalContext.current
    Screen("About", onBack = onBack) {
        Column(Modifier.fillMaxWidth().padding(top = 8.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Image(painterResource(R.drawable.ic_logo), null, Modifier.size(96.dp))
            Text("Ganeshman School", style = MaterialTheme.typography.headlineSmall)
            Text("Ganeshman Singh Academy", style = MaterialTheme.typography.bodyMedium, color = InkSoft)
            Text("Jhiljhille, Jhapa, Nepal", style = MaterialTheme.typography.bodySmall, color = InkSoft, textAlign = TextAlign.Center)
            Chip("Version ${BuildConfig.VERSION_NAME} (${BuildConfig.VERSION_CODE})", BrandSoft, BrandDark)
        }
        Panel {
            Text("About this app", style = MaterialTheme.typography.titleSmall)
            Text(
                "The official student and teacher app for Ganeshman Singh Academy. Students can follow attendance, results, " +
                    "timetable, exams, fees, library and notices. Teachers can mark attendance, enter marks and see their classes.",
                style = MaterialTheme.typography.bodyMedium, color = InkSoft,
            )
        }
        Panel(padding = 4.dp) {
            Row3(Icons.Outlined.Language, "Website", "gms-sms.42web.io", PastelGreen, OnGreen, onClick = { openLink(ctx, WEBSITE_URL) })
            Row3(Icons.Outlined.SystemUpdate, "Latest version", "Download the newest APK", PastelPeach, OnPeach, onClick = { openLink(ctx, RELEASES_URL) })
            Row3(Icons.Outlined.Share, "Share this app", null, PastelPink, OnPink, onClick = { shareApp(ctx) })
        }
        Text(
            "Your sign-in is stored encrypted on this phone. The app only talks to the school's server.",
            style = MaterialTheme.typography.bodySmall, color = InkSoft, textAlign = TextAlign.Center,
            modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp),
        )
    }
}
