package np.edu.ganeshman.sms.ui.auth

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import np.edu.ganeshman.sms.R
import np.edu.ganeshman.sms.data.ApiException
import np.edu.ganeshman.sms.ui.common.LocalRepo
import np.edu.ganeshman.sms.ui.components.BigButton
import np.edu.ganeshman.sms.ui.components.Field
import np.edu.ganeshman.sms.ui.components.Panel
import np.edu.ganeshman.sms.ui.theme.*

@Composable
fun LoginScreen(onLoggedIn: (student: Boolean) -> Unit, onRegister: () -> Unit) {
    val repo = LocalRepo.current
    val scope = rememberCoroutineScope()
    var username by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    fun submit() {
        if (username.isBlank() || password.isBlank()) {
            error = "Enter your username and password."
            return
        }
        loading = true
        error = null
        scope.launch {
            try {
                val d = repo.login(username.trim(), password, null)
                onLoggedIn(d.role == "student")
            } catch (e: ApiException) {
                error = e.message
            } catch (e: Exception) {
                error = "Something went wrong. Please try again."
            } finally {
                loading = false
            }
        }
    }

    Column(
        Modifier
            .fillMaxSize()
            .background(Canvas)
            .statusBarsPadding()
            .navigationBarsPadding()
            .imePadding()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Spacer(Modifier.height(48.dp))
        Image(painterResource(R.drawable.ic_logo), null, Modifier.size(96.dp))
        Spacer(Modifier.height(16.dp))
        Text("Ganeshman Singh Academy", style = MaterialTheme.typography.headlineSmall, textAlign = TextAlign.Center)
        Text("Jhiljhille, Jhapa", style = MaterialTheme.typography.bodyMedium, color = InkSoft)
        Spacer(Modifier.height(32.dp))

        Panel(padding = 20.dp) {
            Text("Welcome back", style = MaterialTheme.typography.titleLarge)
            Text("Sign in as a student or teacher.", style = MaterialTheme.typography.bodyMedium, color = InkSoft)
            Spacer(Modifier.height(8.dp))
            Field(username, { username = it }, "Username")
            Field(password, { password = it }, "Password", password = true)
            error?.let { Text(it, color = Bad, style = MaterialTheme.typography.bodySmall) }
            Spacer(Modifier.height(4.dp))
            BigButton("Sign in", ::submit, loading = loading)
        }
        Spacer(Modifier.height(12.dp))
        TextButton(onClick = onRegister) {
            Text("New student? Create an account", color = Brand)
        }
        Spacer(Modifier.height(24.dp))
    }
}
