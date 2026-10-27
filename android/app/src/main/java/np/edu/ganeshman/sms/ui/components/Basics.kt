package np.edu.ganeshman.sms.ui.components

import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.material.icons.outlined.VisibilityOff
import androidx.compose.material3.*
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.foundation.relocation.bringIntoViewRequester
import androidx.compose.ui.focus.onFocusEvent
import kotlinx.coroutines.launch
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import np.edu.ganeshman.sms.data.fixUrl
import np.edu.ganeshman.sms.ui.theme.*

/** When set (top-level screens), a round profile picture is shown at the top right, like Gmail. */
val LocalOpenProfile = staticCompositionLocalOf<(() -> Unit)?> { null }

/** Opens the global search from the magnifier in the top bar. */
val LocalOpenSearch = staticCompositionLocalOf<(() -> Unit)?> { null }

/** Opens the notices list from the bell in the top bar. */
val LocalOpenNotices = staticCompositionLocalOf<(() -> Unit)?> { null }

/** Standard page: soft canvas, title row (back button, or bell + profile picture on top-level pages), scrolling body. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun Screen(
    title: String,
    onBack: (() -> Unit)? = null,
    subtitle: String? = null,
    actions: @Composable RowScope.() -> Unit = {},
    bottomSpace: Dp = 120.dp,
    refresh: (() -> Unit)? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    Column(
        Modifier
            .fillMaxSize()
            .background(Canvas)
            .statusBarsPadding()
            .imePadding()
    ) {
        Row(
            Modifier
                .fillMaxWidth()
                .padding(start = if (onBack != null) 8.dp else 24.dp, end = 16.dp, top = 12.dp, bottom = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            if (onBack != null) {
                IconButton(onClick = onBack) {
                    Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Back")
                }
            }
            Column(Modifier.weight(1f)) {
                Text(
                    title,
                    style = if (subtitle != null) MaterialTheme.typography.titleLarge else MaterialTheme.typography.headlineSmall,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                if (subtitle != null) Text(
                    subtitle, style = MaterialTheme.typography.bodySmall, color = InkSoft,
                    maxLines = 1, overflow = TextOverflow.Ellipsis,
                )
            }
            actions()
            val openSearch = LocalOpenSearch.current
            val openNotices = LocalOpenNotices.current
            val openProfile = LocalOpenProfile.current
            if (onBack == null && openSearch != null) {
                Box(
                    Modifier.size(40.dp).clip(CircleShape).clickable(onClick = openSearch),
                    contentAlignment = Alignment.Center,
                ) { Icon(Icons.Outlined.Search, contentDescription = "Search", tint = Ink) }
            }
            if (onBack == null && openNotices != null) {
                val repo = np.edu.ganeshman.sms.ui.common.LocalRepo.current
                val unread by repo.session.hasUnread.collectAsState(initial = false)
                Box(
                    Modifier.size(40.dp).clip(CircleShape).clickable(onClick = openNotices),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Outlined.Notifications, contentDescription = "Notices", tint = Ink)
                    if (unread) Box(
                        Modifier.align(Alignment.TopEnd).padding(top = 8.dp, end = 8.dp)
                            .size(10.dp).clip(CircleShape).background(Bad)
                    )
                }
            }
            if (onBack == null && openProfile != null) {
                val me by np.edu.ganeshman.sms.ui.common.LocalRepo.current.session.data.collectAsState()
                Box(Modifier.padding(start = 4.dp).clip(CircleShape).clickable(onClick = openProfile)) {
                    Avatar(me?.profile?.fullName ?: "", me?.profile?.photoUrl, 40.dp)
                }
            }
        }

        @Composable
        fun body() {
            Column(
                Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = 20.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                content()
                Spacer(Modifier.height(bottomSpace))
            }
        }

        Box(Modifier.weight(1f).fillMaxWidth()) {
            if (refresh != null) {
                var refreshing by remember { mutableStateOf(false) }
                LaunchedEffect(refreshing) { if (refreshing) { kotlinx.coroutines.delay(900); refreshing = false } }
                PullToRefreshBox(
                    isRefreshing = refreshing,
                    onRefresh = { refreshing = true; refresh() },
                    modifier = Modifier.fillMaxSize(),
                ) { body() }
            } else {
                body()
            }
        }
    }
}

@Composable
fun Panel(
    modifier: Modifier = Modifier,
    color: Color = Surface,
    onClick: (() -> Unit)? = null,
    padding: Dp = 16.dp,
    content: @Composable ColumnScope.() -> Unit,
) {
    val shape = RoundedCornerShape(24.dp)
    val m = modifier
        .fillMaxWidth()
        .clip(shape)
        .background(color)
        .border(BorderStroke(1.dp, Line), shape)
        .let { if (onClick != null) it.clickable(onClick = onClick) else it }
        .padding(padding)
    Column(m, verticalArrangement = Arrangement.spacedBy(8.dp), content = content)
}

@Composable
fun SectionTitle(text: String, action: String? = null, onAction: (() -> Unit)? = null) {
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Text(text, style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
        if (action != null && onAction != null) {
            Text(
                action,
                style = MaterialTheme.typography.labelLarge,
                color = Brand,
                modifier = Modifier
                    .clip(CircleShape)
                    .clickable(onClick = onAction)
                    .padding(horizontal = 10.dp, vertical = 4.dp),
            )
        }
    }
}

@Composable
fun Chip(text: String, bg: Color = PastelGreen, fg: Color = OnGreen) {
    Text(
        text,
        style = MaterialTheme.typography.labelMedium,
        color = fg,
        modifier = Modifier
            .clip(CircleShape)
            .background(bg)
            .padding(horizontal = 10.dp, vertical = 4.dp),
    )
}

/** Round pastel bubble holding an icon. */
@Composable
fun IconBubble(icon: ImageVector, bg: Color, fg: Color, size: Dp = 44.dp) {
    Box(
        Modifier
            .size(size)
            .clip(CircleShape)
            .background(bg),
        contentAlignment = Alignment.Center,
    ) {
        Icon(icon, null, tint = fg, modifier = Modifier.size(size * 0.5f))
    }
}

@Composable
fun Avatar(name: String, photoUrl: String?, size: Dp = 48.dp) {
    val url = fixUrl(photoUrl)
    Box(
        Modifier
            .size(size)
            .clip(CircleShape)
            .background(BrandSoft),
        contentAlignment = Alignment.Center,
    ) {
        if (url != null) {
            AsyncImage(
                model = url,
                contentDescription = name,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize(),
            )
        } else {
            Text(
                name.trim().take(1).uppercase(),
                color = BrandDark,
                fontWeight = FontWeight.Bold,
                style = MaterialTheme.typography.titleMedium,
            )
        }
    }
}

@Composable
fun BigButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    loading: Boolean = false,
    outlined: Boolean = false,
) {
    val shape = RoundedCornerShape(28.dp)
    if (outlined) {
        OutlinedButton(
            onClick = onClick, enabled = enabled && !loading, shape = shape,
            modifier = modifier.fillMaxWidth().height(54.dp),
            border = BorderStroke(1.dp, Line),
        ) { Text(text, style = MaterialTheme.typography.titleSmall) }
    } else {
        Button(
            onClick = onClick, enabled = enabled && !loading, shape = shape,
            modifier = modifier.fillMaxWidth().height(54.dp),
        ) {
            if (loading) CircularProgressIndicator(
                Modifier.size(22.dp), color = OnBrand, strokeWidth = 2.5.dp,
            ) else Text(text, style = MaterialTheme.typography.titleSmall)
        }
    }
}

@OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)
@Composable
fun Field(
    value: String,
    onChange: (String) -> Unit,
    label: String,
    modifier: Modifier = Modifier,
    error: String? = null,
    password: Boolean = false,
    keyboard: KeyboardType = KeyboardType.Text,
    singleLine: Boolean = true,
    readOnly: Boolean = false,
    trailing: @Composable (() -> Unit)? = null,
    onClick: (() -> Unit)? = null,
) {
    var visible by remember { mutableStateOf(false) }
    val bring = remember { androidx.compose.foundation.relocation.BringIntoViewRequester() }
    val focusScope = rememberCoroutineScope()
    OutlinedTextField(
        value = value,
        onValueChange = onChange,
        label = { Text(label) },
        modifier = modifier
            .fillMaxWidth()
            .bringIntoViewRequester(bring)
            .onFocusEvent { if (it.isFocused) focusScope.launch { kotlinx.coroutines.delay(350); bring.bringIntoView() } }
            .let { if (onClick != null) it.clickable(onClick = onClick) else it },
        singleLine = singleLine,
        readOnly = readOnly,
        enabled = onClick == null,
        isError = error != null,
        supportingText = error?.let { { Text(it) } },
        shape = RoundedCornerShape(16.dp),
        keyboardOptions = KeyboardOptions(keyboardType = if (password) KeyboardType.Password else keyboard),
        visualTransformation = if (password && !visible) PasswordVisualTransformation() else VisualTransformation.None,
        trailingIcon = when {
            password -> {
                {
                    IconButton(onClick = { visible = !visible }) {
                        Icon(if (visible) Icons.Outlined.VisibilityOff else Icons.Outlined.Visibility, null)
                    }
                }
            }
            else -> trailing
        },
        colors = OutlinedTextFieldDefaults.colors(
            focusedBorderColor = Brand,
            unfocusedBorderColor = Line,
            focusedContainerColor = Surface,
            unfocusedContainerColor = Surface,
            disabledTextColor = Ink,
            disabledBorderColor = Line,
            disabledLabelColor = InkSoft,
            disabledContainerColor = Surface,
            disabledTrailingIconColor = InkSoft,
        ),
    )
}

/** Horizontal pill-shaped selector (class picker, month picker, etc.). */
@Composable
fun <T> PillSelector(items: List<T>, selected: T?, label: (T) -> String, onSelect: (T) -> Unit) {
    Row(
        Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        items.forEach { item ->
            val on = item == selected
            Text(
                label(item),
                style = MaterialTheme.typography.labelLarge,
                color = if (on) OnBrand else Ink,
                modifier = Modifier
                    .clip(CircleShape)
                    .background(if (on) Brand else Surface)
                    .border(BorderStroke(1.dp, if (on) Brand else Line), CircleShape)
                    .clickable { onSelect(item) }
                    .padding(horizontal = 16.dp, vertical = 10.dp),
            )
        }
    }
}

/** Thin progress bar with rounded ends. */
@Composable
fun Bar(fraction: Float, color: Color = Brand, track: Color = Line, height: Dp = 8.dp, modifier: Modifier = Modifier) {
    Box(modifier.fillMaxWidth().height(height).clip(CircleShape).background(track)) {
        Box(
            Modifier
                .fillMaxWidth(fraction.coerceIn(0f, 1f))
                .fillMaxHeight()
                .clip(CircleShape)
                .background(color)
        )
    }
}

fun pctColor(p: Double): Color = when {
    p >= 75 -> Good
    p >= 50 -> Warn
    else -> Bad
}

@Composable
fun Skeleton(height: Dp = 90.dp, modifier: Modifier = Modifier) {
    val t = rememberInfiniteTransition(label = "sk")
    val a by t.animateFloat(
        0.45f, 1f,
        infiniteRepeatable(tween(800), RepeatMode.Reverse), label = "a",
    )
    Box(
        modifier
            .fillMaxWidth()
            .height(height)
            .alpha(a)
            .clip(RoundedCornerShape(24.dp))
            .background(SoftBg)
    )
}

@Composable
fun SkeletonList(count: Int = 4, height: Dp = 90.dp) {
    repeat(count) { Skeleton(height) }
}

@Composable
fun KeyValue(label: String, value: String?) {
    if (value.isNullOrBlank()) return
    Row(Modifier.fillMaxWidth().padding(vertical = 6.dp)) {
        Text(label, style = MaterialTheme.typography.bodyMedium, color = InkSoft, modifier = Modifier.weight(0.42f))
        Text(value, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(0.58f))
    }
}

fun money(v: Double): String = "Rs. " + java.text.NumberFormat.getIntegerInstance().format(Math.round(v))

fun num(v: Double): String = if (v == Math.floor(v)) v.toLong().toString() else String.format("%.1f", v)
