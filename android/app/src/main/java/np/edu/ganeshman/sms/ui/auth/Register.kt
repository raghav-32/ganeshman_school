package np.edu.ganeshman.sms.ui.auth

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AddAPhoto
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.ChevronLeft
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.ExpandLess
import androidx.compose.material.icons.outlined.ExpandMore
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.foundation.clickable
import androidx.compose.foundation.Image
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import np.edu.ganeshman.sms.data.ApiException
import np.edu.ganeshman.sms.data.ClassOption
import np.edu.ganeshman.sms.ui.common.LocalRepo
import np.edu.ganeshman.sms.ui.common.NepaliDate
import np.edu.ganeshman.sms.ui.common.iso
import np.edu.ganeshman.sms.ui.common.prettyDate
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.theme.*
import java.io.ByteArrayOutputStream
import java.time.LocalDate

/** Reads the picked image, shrinks it and returns JPEG bytes under ~1.8 MB (server limit is 2 MB). */
private suspend fun compressPhoto(ctx: Context, uri: Uri): Pair<ByteArray, Bitmap>? = withContext(Dispatchers.IO) {
    try {
        val opts = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        ctx.contentResolver.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, opts) }
        var sample = 1
        while (opts.outWidth / sample > 1600 || opts.outHeight / sample > 1600) sample *= 2
        val bmp = ctx.contentResolver.openInputStream(uri)?.use {
            BitmapFactory.decodeStream(it, null, BitmapFactory.Options().apply { inSampleSize = sample })
        } ?: return@withContext null
        var q = 88
        var bytes: ByteArray
        do {
            val out = ByteArrayOutputStream()
            bmp.compress(Bitmap.CompressFormat.JPEG, q, out)
            bytes = out.toByteArray()
            q -= 12
        } while (bytes.size > 1_800_000 && q > 30)
        bytes to bmp
    } catch (e: Exception) {
        null
    }
}

@Composable
fun RegisterScreen(onBack: () -> Unit, onDone: () -> Unit) {
    val repo = LocalRepo.current
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()

    var classes by remember { mutableStateOf<List<ClassOption>>(emptyList()) }
    var classesError by remember { mutableStateOf<String?>(null) }
    LaunchedEffect(Unit) {
        try { classes = repo.publicClasses() } catch (e: Exception) {
            classesError = (e as? ApiException)?.message ?: "Couldn't load classes."
        }
    }

    val f = remember { mutableStateMapOf<String, String>() }
    var gender by remember { mutableStateOf("Male") }
    var classId by remember { mutableStateOf<Int?>(null) }
    var photoBytes by remember { mutableStateOf<ByteArray?>(null) }
    var photoBitmap by remember { mutableStateOf<Bitmap?>(null) }
    var errors by remember { mutableStateOf<Map<String, String>>(emptyMap()) }
    var banner by remember { mutableStateOf<String?>(null) }
    var loading by remember { mutableStateOf(false) }

    val picker = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
        if (uri != null) scope.launch {
            val r = compressPhoto(ctx, uri)
            if (r != null) { photoBytes = r.first; photoBitmap = r.second }
            else banner = "Couldn't read that photo."
        }
    }

    var dobOpen by remember { mutableStateOf(false) }
    fun pickDob() { dobOpen = true }

    fun submit() {
        val local = mutableMapOf<String, String>()
        listOf("first_name", "last_name", "dob", "phone", "address", "guardian_name", "guardian_phone", "username", "password")
            .forEach { if (f[it].isNullOrBlank()) local[it] = "Required" }
        if (classId == null) local["class_id"] = "Choose a class"
        if (f["password"] != f["confirm_password"]) local["confirm_password"] = "Passwords don't match"
        if ((f["password"]?.length ?: 0) in 1..5) local["password"] = "At least 6 characters"
        errors = local
        if (local.isNotEmpty()) { banner = "Please fix the highlighted fields."; return }

        banner = null
        loading = true
        scope.launch {
            try {
                val body = f.toMutableMap().apply {
                    put("gender", gender)
                    put("class_id", classId.toString())
                    if (this["email"].isNullOrBlank()) remove("email")
                }
                repo.register(body, photoBytes)
                onDone()
            } catch (e: ApiException) {
                errors = e.errors
                banner = e.message
            } catch (e: Exception) {
                banner = "Something went wrong. Please try again."
            } finally {
                loading = false
            }
        }
    }

    @Composable
    fun tf(key: String, label: String, kb: KeyboardType = KeyboardType.Text, pw: Boolean = false) {
        Field(f[key] ?: "", { f[key] = it }, label, error = errors[key], keyboard = kb, password = pw)
    }

    Screen("Create account", onBack = onBack, bottomSpace = 32.dp) {
        // photo
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
            Box(
                Modifier.size(96.dp).clip(CircleShape).background(BrandSoft)
                    .clickable { picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)) },
                contentAlignment = Alignment.Center,
            ) {
                val bmp = photoBitmap
                if (bmp != null) Image(bmp.asImageBitmap(), null, Modifier.fillMaxSize(), contentScale = ContentScale.Crop)
                else Icon(Icons.Outlined.AddAPhoto, "Add photo", tint = BrandDark, modifier = Modifier.size(32.dp))
            }
        }
        Text("Photo", style = MaterialTheme.typography.bodySmall, color = InkSoft,
            modifier = Modifier.align(Alignment.CenterHorizontally))
        errors["photo"]?.let { Text(it, color = Bad, style = MaterialTheme.typography.bodySmall) }

        Panel(padding = 16.dp) {
            Text("Student", style = MaterialTheme.typography.titleMedium)
            tf("first_name", "First name")
            tf("last_name", "Last name")
            Field(
                f["dob"]?.takeIf { it.isNotBlank() }?.let { runCatching { NepaliDate.format(LocalDate.parse(it)) }.getOrNull() } ?: "",
                {}, "Date of birth (BS)", error = errors["dob"], readOnly = true,
                trailing = { Icon(Icons.Outlined.CalendarMonth, null) }, onClick = ::pickDob,
            )
            Text("Gender", style = MaterialTheme.typography.labelLarge, color = InkSoft)
            PillSelector(listOf("Male", "Female", "Other"), gender, { it }) { gender = it }
            Text("Class", style = MaterialTheme.typography.labelLarge, color = InkSoft)
            if (classes.isEmpty()) {
                Text(classesError ?: "Loading classes…", style = MaterialTheme.typography.bodySmall, color = InkSoft)
            } else {
                PillSelector(classes.map { it.id }, classId, { id -> classes.first { it.id == id }.label }) { classId = it }
            }
            errors["class_id"]?.let { Text(it, color = Bad, style = MaterialTheme.typography.bodySmall) }
            tf("phone", "Phone", KeyboardType.Phone)
            tf("email", "Email", KeyboardType.Email)
            tf("address", "Address")
        }

        Panel(padding = 16.dp) {
            Text("Guardian", style = MaterialTheme.typography.titleMedium)
            tf("guardian_name", "Guardian name")
            tf("guardian_phone", "Guardian phone", KeyboardType.Phone)
            tf("guardian_relation", "Relation (e.g. Father)")
        }

        Panel(padding = 16.dp) {
            Text("Sign-in details", style = MaterialTheme.typography.titleMedium)
            tf("username", "Username")
            tf("password", "Password", pw = true)
            tf("confirm_password", "Confirm password", pw = true)
        }

        if (dobOpen) {
            val init = f["dob"]?.takeIf { it.isNotBlank() }?.let { runCatching { LocalDate.parse(it) }.getOrNull() }
                ?: LocalDate.now().minusYears(10)
            BsDatePickerDialog(init, onDismiss = { dobOpen = false }) { f["dob"] = it.iso(); dobOpen = false }
        }

        banner?.let { Text(it, color = Bad, style = MaterialTheme.typography.bodyMedium) }
        BigButton("Submit registration", ::submit, loading = loading)
    }
}

/** Calendar-style date picker in Bikram Sambat (BS). Returns the chosen day as an AD LocalDate (the server stores AD). */
@Composable
fun BsDatePickerDialog(initial: LocalDate, onDismiss: () -> Unit, onPick: (LocalDate) -> Unit) {
    val today = remember { LocalDate.now() }
    val todayBs = remember { NepaliDate.fromAd(today) ?: Triple(NepaliDate.endYear, 12, 30) }
    val start = NepaliDate.fromAd(initial) ?: Triple(2070, 1, 1)
    var year by remember { mutableIntStateOf(start.first) }
    var month by remember { mutableIntStateOf(start.second) }
    var yearMode by remember { mutableStateOf(false) }
    val sel = Triple(start.first, start.second, start.third)
    var chosen by remember { mutableStateOf(sel) }

    fun step(delta: Int) {
        var m = month + delta; var y = year
        if (m < 1) { m = 12; y-- } else if (m > 12) { m = 1; y++ }
        if (y < NepaliDate.START_YEAR || y > todayBs.first) return
        year = y; month = m
    }

    androidx.compose.material3.AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = Surface,
        title = {
            Column {
                Text("Date of birth (BS)", style = MaterialTheme.typography.labelLarge, color = InkSoft)
                Text(
                    "%d %s %d".format(chosen.third, NepaliDate.monthNames[chosen.second - 1], chosen.first),
                    style = MaterialTheme.typography.headlineSmall,
                )
            }
        },
        text = {
            Column {
                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                    Row(
                        Modifier.clip(androidx.compose.foundation.shape.RoundedCornerShape(10.dp))
                            .clickable { yearMode = !yearMode }.padding(horizontal = 8.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text("${NepaliDate.monthNames[month - 1]} $year", style = MaterialTheme.typography.titleSmall)
                        Icon(
                            if (yearMode) Icons.Outlined.ExpandLess else Icons.Outlined.ExpandMore, null,
                            Modifier.size(20.dp),
                        )
                    }
                    Spacer(Modifier.weight(1f))
                    if (!yearMode) {
                        androidx.compose.material3.IconButton(onClick = { step(-1) }) { Icon(Icons.Outlined.ChevronLeft, "Previous month") }
                        androidx.compose.material3.IconButton(onClick = { step(1) }) { Icon(Icons.Outlined.ChevronRight, "Next month") }
                    }
                }
                if (yearMode) {
                    val years = remember { (NepaliDate.START_YEAR..todayBs.first).toList().reversed() }
                    val st = androidx.compose.foundation.lazy.rememberLazyListState()
                    LaunchedEffect(Unit) { years.indexOf(year).let { if (it > 2) st.scrollToItem(it - 2) } }
                    androidx.compose.foundation.lazy.LazyColumn(Modifier.height(260.dp), state = st) {
                        items(years.size) { i ->
                            val y = years[i]; val on = y == year
                            Box(
                                Modifier.fillMaxWidth().padding(vertical = 2.dp).clip(androidx.compose.foundation.shape.RoundedCornerShape(12.dp))
                                    .background(if (on) Brand else androidx.compose.ui.graphics.Color.Transparent)
                                    .clickable { year = y; yearMode = false }.padding(vertical = 10.dp),
                                contentAlignment = Alignment.Center,
                            ) { Text(y.toString(), color = if (on) OnBrand else Ink) }
                        }
                    }
                } else {
                    Row(Modifier.fillMaxWidth()) {
                        listOf("S", "M", "T", "W", "T", "F", "S").forEach {
                            Text(it, Modifier.weight(1f), textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                                style = MaterialTheme.typography.labelMedium, color = InkSoft)
                        }
                    }
                    Spacer(Modifier.height(4.dp))
                    val lead = NepaliDate.toAd(year, month, 1).dayOfWeek.value % 7     // Sunday = 0
                    val days = NepaliDate.daysIn(year, month)
                    val cells = lead + days
                    for (r in 0 until (cells + 6) / 7) {
                        Row(Modifier.fillMaxWidth()) {
                            for (c in 0 until 7) {
                                val d = r * 7 + c - lead + 1
                                Box(Modifier.weight(1f).aspectRatio(1f).padding(2.dp), contentAlignment = Alignment.Center) {
                                    if (d in 1..days) {
                                        val future = NepaliDate.toAd(year, month, d).isAfter(today)
                                        val on = chosen == Triple(year, month, d)
                                        val isToday = Triple(year, month, d) == todayBs
                                        Box(
                                            Modifier.fillMaxSize().clip(CircleShape)
                                                .background(if (on) Brand else androidx.compose.ui.graphics.Color.Transparent)
                                                .let { if (isToday && !on) it.border(1.dp, Brand, CircleShape) else it }
                                                .let { if (future) it else it.clickable { chosen = Triple(year, month, d) } },
                                            contentAlignment = Alignment.Center,
                                        ) {
                                            Text(
                                                d.toString(), style = MaterialTheme.typography.bodyMedium,
                                                color = when { on -> OnBrand; future -> InkSoft.copy(alpha = 0.4f); else -> Ink },
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                    Text("= %s AD".format(prettyDate(NepaliDate.toAd(chosen.first, chosen.second, chosen.third).iso())),
                        style = MaterialTheme.typography.bodySmall, color = InkSoft, modifier = Modifier.padding(top = 6.dp))
                }
            }
        },
        confirmButton = {
            androidx.compose.material3.TextButton(onClick = {
                onPick(NepaliDate.toAd(chosen.first, chosen.second, chosen.third))
            }) { Text("OK") }
        },
        dismissButton = { androidx.compose.material3.TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}
