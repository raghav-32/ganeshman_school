package np.edu.ganeshman.sms.ui.components

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import np.edu.ganeshman.sms.ui.theme.*

data class QuickAction(val label: String, val icon: ImageVector, val bg: Color, val fg: Color, val onClick: () -> Unit)

/** Bottom sheet opened by the home FAB. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun QuickActionsSheet(title: String, actions: List<QuickAction>, onDismiss: () -> Unit) {
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        containerColor = Surface,
        shape = RoundedCornerShape(topStart = 32.dp, topEnd = 32.dp),
    ) {
        Column(Modifier.padding(horizontal = 24.dp).padding(bottom = 32.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
            Text(title, style = MaterialTheme.typography.titleLarge)
            actions.chunked(3).forEach { row ->
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    row.forEach { a ->
                        Column(
                            Modifier.weight(1f).clip(RoundedCornerShape(20.dp)).clickable { onDismiss(); a.onClick() }.padding(vertical = 8.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            IconBubble(a.icon, a.bg, a.fg, 56.dp)
                            Text(a.label, style = MaterialTheme.typography.labelMedium, textAlign = TextAlign.Center)
                        }
                    }
                    repeat(3 - row.size) { Spacer(Modifier.weight(1f)) }
                }
            }
        }
    }
}

/** Circular percentage ring. */
@Composable
fun Ring(fraction: Float, color: Color, size: Dp = 88.dp, stroke: Dp = 10.dp, label: String) {
    Box(Modifier.size(size), contentAlignment = Alignment.Center) {
        Canvas(Modifier.fillMaxSize()) {
            val s = stroke.toPx()
            val inset = s / 2
            val arcSize = Size(this.size.width - s, this.size.height - s)
            drawArc(Line, 0f, 360f, false, Offset(inset, inset), arcSize, style = Stroke(s))
            drawArc(color, -90f, 360f * fraction.coerceIn(0f, 1f), false, Offset(inset, inset), arcSize,
                style = Stroke(s, cap = StrokeCap.Round))
        }
        Text(label, style = MaterialTheme.typography.titleMedium)
    }
}

/** Small stat tile: pastel background, big value, caption. */
@Composable
fun StatTile(value: String, caption: String, bg: Color, fg: Color, modifier: Modifier = Modifier) {
    Column(
        modifier.clip(RoundedCornerShape(24.dp)).background(bg).padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(2.dp),
    ) {
        Text(value, style = MaterialTheme.typography.titleLarge, color = fg)
        Text(caption, style = MaterialTheme.typography.labelMedium, color = fg.copy(alpha = 0.8f))
    }
}


/** Pastel tile with a small icon, a big value and a caption (2x2 grids on home/attendance). */
@Composable
fun IconTile(icon: ImageVector, value: String, caption: String, bg: Color, fg: Color, modifier: Modifier = Modifier) {
    Column(
        modifier.clip(RoundedCornerShape(24.dp)).background(bg).padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Box(Modifier.size(30.dp).clip(RoundedCornerShape(10.dp)).background(Surface.copy(alpha = 0.6f)), contentAlignment = Alignment.Center) {
            Icon(icon, null, tint = fg, modifier = Modifier.size(18.dp))
        }
        Column {
            Text(value, style = MaterialTheme.typography.titleLarge, color = Ink)
            Text(caption, style = MaterialTheme.typography.labelMedium, color = InkSoft)
        }
    }
}
