# Ganeshman Singh Academy — Android app

Native Android app (Kotlin, Jetpack Compose, Material 3) for **students** and **teachers**.
It talks to the school server through the REST API in [`/api/v1`](../api/v1/README.md).

Website: https://gms-sms.42web.io/home.php
API base URL: `https://gms-sms.42web.io/api/v1/`

## Features
- **Sign in** (students & teachers), **student self-registration** with photo (goes to "pending" until the office approves)
- **Student:** home dashboard, attendance calendar, results + subject detail, timetable, exams, fees & payments, library (search / request / cancel), notices, profile, change password
- **Teacher:** dashboard, mark attendance (class teacher only), enter marks (class teacher only), class student lists, notices, profile
- Material 3 light theme, floating pill bottom navigation, floating action button, pastel illustrated empty states

## Build the APK
1. Install **Android Studio** (Ladybug or newer) — it brings its own JDK 17 and SDK.
2. Open the `android/` folder (File → Open). Let Gradle sync finish.
3. **Build → Build Bundle(s) / APK(s) → Build APK(s)**
   or from a terminal inside `android/`:
   ```
   ./gradlew assembleDebug        # Windows: gradlew.bat assembleDebug
   ```
4. The APK is at `app/build/outputs/apk/debug/app-debug.apk`. Copy it to your phone and install
   (allow "Install unknown apps").

`./gradlew assembleRelease` also works; the release build is signed with the debug key so it installs directly.
For the Play Store, create your own keystore.

## Pointing the app at your server
One line in `app/build.gradle.kts`:
```kotlin
buildConfigField("String", "API_BASE_URL", "\"https://gms-sms.42web.io/api/v1/\"")
```
It must end with `/api/v1/` and use **https** (Android blocks plain http).

## Hosting notes (important)
- Upload the whole project (including `api/`) to the host and import `database.sql` as usual.
  The API creates its own `api_tokens` / `book_requests` tables on first use.
- **InfinityFree** (`42web.io`) sometimes answers non-browser requests with an HTML "bot check" page.
  If the app shows *"The server sent an unexpected reply"*, that is the cause. Fixes: open the site
  once in a browser, try again, or move to a host without the bot check. The app cannot solve that challenge.
- In `includes/config.php` set `BASE_URL` to `https://…` so photo URLs are https
  (the app also upgrades `http://` image links automatically).
- Login token is stored encrypted on the phone (30-day sliding expiry). Signing out revokes it.

## Project layout
```
app/src/main/java/np/edu/ganeshman/sms/
  data/   Models, ApiClient (OkHttp), Session (encrypted prefs), Repository (one fn per endpoint)
  ui/     theme, components (pill nav, FAB, empty states…), nav, auth, student, teacher, common
```
