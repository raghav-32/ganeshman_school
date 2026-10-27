package np.edu.ganeshman.sms.ui.components

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.expandHorizontally
import androidx.compose.animation.shrinkHorizontally
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import np.edu.ganeshman.sms.ui.theme.*

data class NavItem(val route: String, val label: String, val icon: ImageVector)

/** Floating, rounded pill bottom navigation. The active tab expands to icon + label. */
@Composable
fun PillNav(items: List<NavItem>, selected: String, onSelect: (String) -> Unit, modifier: Modifier = Modifier) {
    Row(
        modifier
            .shadow(10.dp, CircleShape, ambientColor = Color(0x22000000), spotColor = Color(0x22000000))
            .clip(CircleShape)
            .background(Surface)
            .padding(horizontal = 8.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        items.forEach { item ->
            val on = item.route == selected
            val bg by animateColorAsState(if (on) BrandSoft else Color.Transparent, label = "bg")
            Row(
                Modifier
                    .clip(CircleShape)
                    .background(bg)
                    .clickable { onSelect(item.route) }
                    .padding(horizontal = 16.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(item.icon, item.label, tint = if (on) BrandDark else InkSoft, modifier = Modifier.size(24.dp))
                AnimatedVisibility(on, enter = expandHorizontally(), exit = shrinkHorizontally()) {
                    Text(
                        item.label,
                        style = MaterialTheme.typography.labelLarge,
                        color = BrandDark,
                        maxLines = 1,
                        modifier = Modifier.padding(start = 8.dp),
                    )
                }
            }
        }
    }
}

// ------------------------------------------------------------------ FAB plumbing

data class FabSpec(
    val icon: ImageVector,
    val label: String? = null,   // non-null => extended FAB
    val loading: Boolean = false,
    val rotated: Boolean = false,
    val onClick: () -> Unit,
)

class FabHost {
    var spec by mutableStateOf<FabSpec?>(null)
}

val LocalFabHost = compositionLocalOf { FabHost() }

/** A screen calls this to show its floating action button while it is on screen. */
@Composable
fun FabEffect(spec: FabSpec?) {
    val host = LocalFabHost.current
    SideEffect { host.spec = spec }
    DisposableEffect(Unit) { onDispose { host.spec = null } }
}

@Composable
fun AppFab(spec: FabSpec, modifier: Modifier = Modifier) {
    val rot by animateFloatAsState(if (spec.rotated) 45f else 0f, label = "rot")
    if (spec.label != null) {
        ExtendedFloatingActionButton(
            onClick = { if (!spec.loading) spec.onClick() },
            modifier = modifier,
            containerColor = Brand,
            contentColor = Color.White,
            shape = CircleShape,
        ) {
            if (spec.loading) CircularProgressIndicator(Modifier.size(20.dp), color = Color.White, strokeWidth = 2.dp)
            else Icon(spec.icon, null)
            Spacer(Modifier.width(8.dp))
            Text(spec.label, style = MaterialTheme.typography.labelLarge)
        }
    } else {
        FloatingActionButton(
            onClick = spec.onClick,
            modifier = modifier,
            containerColor = Brand,
            contentColor = Color.White,
            shape = CircleShape,
        ) {
            Icon(spec.icon, null, modifier = Modifier.rotate(rot))
        }
    }
}
