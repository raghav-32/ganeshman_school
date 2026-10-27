package np.edu.ganeshman.sms.ui.auth

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AddAPhoto
import androidx.compose.material.icons.outlined.CalendarMonth
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

/** Date picker in Bikram Sambat (BS). Returns the chosen day as an AD LocalDate (the server stores AD). */
@Composable
fun BsDatePickerDialog(initial: LocalDate, onDismiss: () -> Unit, onPick: (LocalDate) -> Unit) {
    val start = NepaliDate.fromAd(initial) ?: Triple(2070, 1, 1)
    var year by remember { mutableIntStateOf(start.first) }
    var month by remember { mutableIntStateOf(start.second) }
    var day by remember { mutableIntStateOf(start.third) }
    val maxDay = NepaliDate.daysIn(year, month)
    if (day > maxDay) day = maxDay
    val todayBs = remember { NepaliDate.fromAd(LocalDate.now()) }
    val maxYear = todayBs?.first ?: NepaliDate.endYear

    androidx.compose.material3.AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = Surface,
        title = { Text("Date of birth (BS)") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    "%d %s %d BS  •  %s AD".format(day, NepaliDate.monthNames[month - 1], year,
                        NepaliDate.toAd(year, month, day).let { prettyDate(it.iso()) }),
                    style = MaterialTheme.typography.titleSmall, color = BrandDark,
                )
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    PickColumn("Year", (NepaliDate.START_YEAR..maxYear).toList().reversed(), year, { it.toString() }, Modifier.weight(1.1f)) { year = it }
                    PickColumn("Month", (1..12).toList(), month, { NepaliDate.monthNames[it - 1] }, Modifier.weight(1.4f)) { month = it }
                    PickColumn("Day", (1..maxDay).toList(), day, { it.toString() }, Modifier.weight(0.8f)) { day = it }
                }
            }
        },
        confirmButton = {
            androidx.compose.material3.TextButton(onClick = {
                val ad = NepaliDate.toAd(year, month, day)
                onPick(if (ad.isAfter(LocalDate.now())) LocalDate.now() else ad)
            }) { Text("OK") }
        },
        dismissButton = { androidx.compose.material3.TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}

@Composable
private fun PickColumn(
    label: String, items: List<Int>, selected: Int, text: (Int) -> String, modifier: Modifier, onSelect: (Int) -> Unit,
) {
    val state = androidx.compose.foundation.lazy.rememberLazyListState()
    LaunchedEffect(Unit) { items.indexOf(selected).let { if (it > 1) state.scrollToItem(it - 1) } }
    Column(modifier, verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Text(label, style = MaterialTheme.typography.labelMedium, color = InkSoft)
        androidx.compose.foundation.lazy.LazyColumn(Modifier.height(200.dp), state = state) {
            items(items.size) { i ->
                val v = items[i]
                val on = v == selected
                Box(
                    Modifier.fillMaxWidth().padding(vertical = 2.dp).clip(androidx.compose.foundation.shape.RoundedCornerShape(12.dp))
                        .background(if (on) Brand else androidx.compose.ui.graphics.Color.Transparent)
                        .clickable { onSelect(v) }.padding(vertical = 9.dp, horizontal = 6.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(text(v), color = if (on) OnBrand else Ink, style = MaterialTheme.typography.bodyMedium, maxLines = 1)
                }
            }
        }
    }
}
