package np.edu.ganeshman.sms.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.ExperimentalSerializationApi
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNamingStrategy
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.booleanOrNull
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import kotlinx.serialization.serializer
import np.edu.ganeshman.sms.BuildConfig
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.IOException
import javax.crypto.Cipher
import javax.crypto.spec.IvParameterSpec
import javax.crypto.spec.SecretKeySpec
import java.util.concurrent.TimeUnit

/** Error shown to the user. [errors] holds per-field messages (HTTP 422). */
class ApiException(
    message: String,
    val code: Int = 0,
    val errors: Map<String, String> = emptyMap(),
) : Exception(message)

object Net {
    @OptIn(ExperimentalSerializationApi::class)
    val json = Json {
        ignoreUnknownKeys = true
        coerceInputValues = true
        explicitNulls = false
        isLenient = true
        encodeDefaults = true
        namingStrategy = JsonNamingStrategy.SnakeCase
    }
}

/** The server sends photo URLs as http://…; Android blocks cleartext, so upgrade them. */
fun fixUrl(url: String?): String? {
    if (url.isNullOrBlank()) return null
    return if (url.startsWith("http://")) "https://" + url.removePrefix("http://") else url
}

class ApiClient(private val session: Session) {

    private val http = OkHttpClient.Builder()
        .connectTimeout(20, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .writeTimeout(60, TimeUnit.SECONDS)
        .build()

    private val base = BuildConfig.API_BASE_URL.trimEnd('/') + "/"
    /** Endpoints are real .php files, so we don't rely on the server's URL-rewrite rules. */
    private fun endpoint(path: String): String {
        val p = path.trim('/')
        return base + if (p.endsWith(".php")) p else "$p.php"
    }

    private val jsonMedia = "application/json; charset=utf-8".toMediaType()

    private class Cached(val at: Long, val value: JsonElement)
    private val cache = HashMap<String, Cached>()
    private val cacheTtlMs = 30_000L

    fun clearCache() = synchronized(cache) { cache.clear() }

    suspend inline fun <reified T> get(
        path: String,
        query: Map<String, Any?> = emptyMap(),
        fresh: Boolean = false,
    ): T = Net.json.decodeFromJsonElement(serializer<T>(), rawGet(path, query, fresh))

    suspend inline fun <reified T> post(path: String, body: JsonObject = JsonObject(emptyMap())): T =
        Net.json.decodeFromJsonElement(serializer<T>(), rawPost(path, body))

    suspend inline fun <reified T> multipart(
        path: String,
        fields: Map<String, String>,
        fileField: String? = null,
        fileName: String = "photo.jpg",
        fileBytes: ByteArray? = null,
    ): T = Net.json.decodeFromJsonElement(
        serializer<T>(), rawMultipart(path, fields, fileField, fileName, fileBytes),
    )

    // -------- raw layer (returns the envelope's "data") --------

    suspend fun rawGet(path: String, query: Map<String, Any?>, fresh: Boolean): JsonElement {
        val url = endpoint(path).toHttpUrl().newBuilder().apply {
            query.forEach { (k, v) -> if (v != null && v.toString().isNotEmpty()) addQueryParameter(k, v.toString()) }
        }.build()
        val key = url.toString() + "|" + (session.token ?: "")
        if (!fresh) {
            synchronized(cache) {
                val c = cache[key]
                if (c != null && System.currentTimeMillis() - c.at < cacheTtlMs) return c.value
            }
        }
        val data = execute(Request.Builder().url(url).get(), auth = path.authRequired())
        synchronized(cache) { cache[key] = Cached(System.currentTimeMillis(), data) }
        return data
    }

    suspend fun rawPost(path: String, body: JsonObject): JsonElement {
        val data = execute(
            Request.Builder().url(endpoint(path))
                .post(body.toString().toRequestBody(jsonMedia)),
            auth = path.authRequired(),
        )
        clearCache()
        return data
    }

    suspend fun rawMultipart(
        path: String,
        fields: Map<String, String>,
        fileField: String?,
        fileName: String,
        fileBytes: ByteArray?,
    ): JsonElement {
        val mb = MultipartBody.Builder().setType(MultipartBody.FORM)
        fields.forEach { (k, v) -> mb.addFormDataPart(k, v) }
        if (fileField != null && fileBytes != null) {
            mb.addFormDataPart(fileField, fileName, fileBytes.toRequestBody("image/jpeg".toMediaType()))
        }
        return execute(Request.Builder().url(endpoint(path)).post(mb.build()), auth = false)
    }

    // Public endpoints don't need (and shouldn't send) a token.
    private fun String.authRequired(): Boolean =
        !(startsWith("auth/login") || startsWith("auth/register") || startsWith("auth/classes"))

    // ---- InfinityFree "browser check" ------------------------------------------------
    // The free host answers non-browser clients with a tiny JavaScript puzzle (AES-128-CBC):
    //   a=toNumbers("key"), b=toNumbers("iv"), c=toNumbers("cipher") -> cookie __test = hex(decrypt(c))
    // A browser solves it and then reloads. We do the same maths natively and retry once.
    @Volatile private var hostCookie: String? = null

    private fun looksLikeChallenge(body: String) = body.contains("toNumbers(") && body.contains("slowAES")

    private fun solveChallenge(html: String): String? {
        val parts = Regex("toNumbers\\(\"([0-9a-fA-F]+)\"\\)").findAll(html).map { it.groupValues[1] }.toList()
        if (parts.size < 3) return null
        fun bytes(h: String) = ByteArray(h.length / 2) { h.substring(it * 2, it * 2 + 2).toInt(16).toByte() }
        return try {
            val c = Cipher.getInstance("AES/CBC/NoPadding")
            c.init(Cipher.DECRYPT_MODE, SecretKeySpec(bytes(parts[0]), "AES"), IvParameterSpec(bytes(parts[1])))
            c.doFinal(bytes(parts[2])).joinToString("") { "%02x".format(it) }
        } catch (e: Exception) {
            null
        }
    }

    private fun withHostCookie(req: Request): Request {
        val c = hostCookie ?: return req
        return req.newBuilder()
            .header("Cookie", "__test=$c")
            .url(req.url.newBuilder().setQueryParameter("i", "1").build())
            .build()
    }

    private fun call(request: Request): Pair<Int, String> = try {
        http.newCall(request).execute().use { r -> r.code to (r.body?.string() ?: "") }
    } catch (e: IOException) {
        throw ApiException("Can't reach the school server. Check your internet connection and try again.")
    }

    private fun fetch(original: Request): Pair<Int, String> {
        var r = call(withHostCookie(original))
        if (looksLikeChallenge(r.second)) {
            val cookie = solveChallenge(r.second)
            if (cookie != null) {
                hostCookie = cookie
                r = call(withHostCookie(original))
            }
        }
        return r
    }

    private suspend fun execute(builder: Request.Builder, auth: Boolean): JsonElement =
        withContext(Dispatchers.IO) {
            builder.header("Accept", "application/json")
            builder.header("User-Agent", "GMS-Android/1.0")
            if (auth) session.token?.let { builder.header("Authorization", "Bearer $it") }

            val request = builder.build()
            val (status, text) = fetch(request)

            val root: JsonObject = try {
                Net.json.parseToJsonElement(text).jsonObject
            } catch (e: Exception) {
                // Free hosts (e.g. InfinityFree) sometimes answer API calls with an HTML bot-check page.
                val hint = when {
                    text.contains("slowAES", true) || text.contains("__test", true) || text.contains("aes.js", true) ->
                        "Host bot-check page (InfinityFree) blocked the app."
                    text.contains("<html", true) || text.contains("<!doctype", true) -> "Got a web page instead of JSON."
                    text.isBlank() -> "Empty reply."
                    else -> "Not JSON."
                }
                val preview = text.replace(Regex("<[^>]*>"), " ").replace(Regex("\\s+"), " ").trim().take(140)
                throw ApiException(
                    "Unexpected server reply (HTTP $status). $hint\nURL: ${request.url}\n$preview",
                    status,
                )
            }

            val ok = root["success"]?.jsonPrimitive?.booleanOrNull ?: false
            if (ok) return@withContext root["data"] ?: JsonObject(emptyMap())

            val message = root["message"]?.jsonPrimitive?.contentOrNull ?: "Something went wrong."
            val errors = (root["errors"] as? JsonObject)
                ?.mapValues { (it.value as? JsonPrimitive)?.contentOrNull ?: it.value.toString() }
                ?: emptyMap()

            if (status == 401 && auth) session.clear()   // token expired/revoked -> back to login
            throw ApiException(message, status, errors)
        }
}
