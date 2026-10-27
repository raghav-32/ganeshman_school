package np.edu.ganeshman.sms.ui.common

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.KeyboardArrowRight
import androidx.compose.material.icons.automirrored.outlined.Logout
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.navigation.NavHostController
import kotlinx.coroutines.launch
import np.edu.ganeshman.sms.ui.components.IconBubble
import np.edu.ganeshman.sms.ui.components.Panel
import np.edu.ganeshman.sms.ui.components.Screen
import np.edu.ganeshman.sms.ui.theme.*

data class MoreItem(val title: String, val subtitle: String, val icon: ImageVector, val bg: Color, val fg: Color, val route: String)

@Composable
fun MoreList(items: List<MoreItem>, nav: NavHostController) {
    val repo = LocalRepo.current
    val scope = rememberCoroutineScope()
    val me by repo.session.data.collectAsState()
    var confirmOut by remember { mutableStateOf(false) }
    val profileRoute = if (me?.isStudent == false) Routes.T_PROFILE else Routes.S_PROFILE
    Screen("More") {
        me?.let { m ->
            Panel(onClick = { nav.navigate(profileRoute) }, padding = 16.dp) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                    np.edu.ganeshman.sms.ui.components.Avatar(m.profile.fullName, m.profile.photoUrl, 56.dp)
                    Column(Modifier.weight(1f)) {
                        Text(m.profile.fullName, style = MaterialTheme.typography.titleMedium)
                        Text(listOfNotNull(m.profile.code.ifBlank { null }, m.profile.klass?.label).joinToString(" • "),
                            style = MaterialTheme.typography.bodySmall, color = InkSoft)
                    }
                    Icon(Icons.AutoMirrored.Outlined.KeyboardArrowRight, null, tint = InkSoft)
                }
            }
        }
        items.forEach { i ->
            Panel(onClick = { nav.navigate(i.route) }, padding = 14.dp) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                    IconBubble(i.icon, i.bg, i.fg)
                    Column(Modifier.weight(1f)) {
                        Text(i.title, style = MaterialTheme.typography.titleSmall)
                        Text(i.subtitle, style = MaterialTheme.typography.bodySmall, color = InkSoft)
                    }
                    Icon(Icons.AutoMirrored.Outlined.KeyboardArrowRight, null, tint = InkSoft)
                }
            }
        }
        Panel(onClick = { confirmOut = true }, padding = 14.dp) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                IconBubble(Icons.AutoMirrored.Outlined.Logout, PastelPink, OnPink)
                Text("Sign out", style = MaterialTheme.typography.titleSmall, color = OnPink)
            }
        }
    }
    if (confirmOut) SignOutDialog({ confirmOut = false }) { scope.launch { repo.logout() } }
}
