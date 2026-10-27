package np.edu.ganeshman.sms.ui.auth

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import np.edu.ganeshman.sms.ui.components.EmptyState
import np.edu.ganeshman.sms.ui.components.Illustration
import np.edu.ganeshman.sms.ui.theme.Canvas

@Composable
fun PendingScreen(onBackToLogin: () -> Unit) {
    Column(
        Modifier.fillMaxSize().background(Canvas).statusBarsPadding().navigationBarsPadding().padding(24.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        EmptyState(
            illustration = Illustration.Inbox,
            title = "Registration sent",
            message = "The school office will review your details. You can sign in once your account is approved.",
            actionLabel = "Back to sign in",
            onAction = onBackToLogin,
        )
    }
}
