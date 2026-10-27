package np.edu.ganeshman.sms.ui.components

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import np.edu.ganeshman.sms.ui.theme.*

enum class Illustration(val icon: ImageVector, val blob: Color, val accent: Color, val ink: Color) {
    Inbox(Icons.Outlined.Inbox, PastelGreen, PastelBlue, OnGreen),
    Notices(Icons.Outlined.Campaign, PastelYellow, PastelPeach, OnYellow),
    Results(Icons.Outlined.EmojiEvents, PastelLilac, PastelBlue, OnLilac),
    Calendar(Icons.Outlined.CalendarMonth, PastelBlue, PastelGreen, OnBlue),
    Books(Icons.Outlined.MenuBook, PastelPeach, PastelYellow, OnPeach),
    People(Icons.Outlined.Groups, PastelPink, PastelLilac, OnPink),
    Money(Icons.Outlined.Payments, PastelGreen, PastelYellow, OnGreen),
    Offline(Icons.Outlined.CloudOff, PastelPink, PastelPeach, OnPink),
}

/** Centered pastel illustration drawn on a Canvas — no image assets needed. */
@Composable
fun EmptyState(
    illustration: Illustration,
    title: String,
    message: String,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier
            .fillMaxWidth()
            .padding(vertical = 32.dp, horizontal = 12.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Box(Modifier.size(180.dp), contentAlignment = Alignment.Center) {
            Canvas(Modifier.fillMaxSize()) {
                val w = size.width
                val h = size.height
                // big soft blob
                drawCircle(illustration.blob, radius = w * 0.46f, center = Offset(w * 0.5f, h * 0.52f))
                // small accent blobs
                drawCircle(illustration.accent, radius = w * 0.13f, center = Offset(w * 0.12f, h * 0.22f))
                drawCircle(illustration.accent, radius = w * 0.07f, center = Offset(w * 0.9f, h * 0.18f))
                drawCircle(illustration.ink.copy(alpha = 0.25f), radius = w * 0.025f, center = Offset(w * 0.82f, h * 0.82f))
                drawCircle(illustration.ink.copy(alpha = 0.25f), radius = w * 0.02f, center = Offset(w * 0.2f, h * 0.8f))
                // little card behind the icon
                drawRoundRect(
                    Color.White,
                    topLeft = Offset(w * 0.26f, h * 0.3f),
                    size = Size(w * 0.48f, h * 0.44f),
                    cornerRadius = CornerRadius(w * 0.1f),
                )
                drawRoundRect(
                    illustration.accent,
                    topLeft = Offset(w * 0.34f, h * 0.62f),
                    size = Size(w * 0.32f, h * 0.035f),
                    cornerRadius = CornerRadius(w * 0.02f),
                )
            }
            Icon(
                illustration.icon, null,
                tint = illustration.ink,
                modifier = Modifier.size(54.dp).offset(y = (-8).dp),
            )
        }
        Text(title, style = MaterialTheme.typography.titleLarge, textAlign = TextAlign.Center)
        Text(
            message,
            style = MaterialTheme.typography.bodyMedium,
            color = InkSoft,
            textAlign = TextAlign.Center,
        )
        if (actionLabel != null && onAction != null) {
            Spacer(Modifier.height(6.dp))
            BigButton(actionLabel, onAction, Modifier.widthIn(max = 220.dp))
        }
    }
}
