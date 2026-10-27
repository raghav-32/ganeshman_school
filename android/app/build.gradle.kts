plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    id("org.jetbrains.kotlin.plugin.compose")
    id("org.jetbrains.kotlin.plugin.serialization")
}

android {
    namespace = "np.edu.ganeshman.sms"
    compileSdk = 35

    defaultConfig {
        applicationId = "np.edu.ganeshman.sms"
        minSdk = 26
        targetSdk = 35
        versionCode = 2
        versionName = "1.1.0"

        // The school website's API. Change this one line if the site moves
        // (must end with a slash and use https).
        buildConfigField("String", "API_BASE_URL", "\"https://gms-sms.42web.io/api/v1/\"")
    }

    // One fixed signing key for every build (local or GitHub Actions). Android only allows
    // updating an installed app when the new APK is signed with the SAME key.
    signingConfigs {
        create("gms") {
            storeFile = file("gms-sign.jks")
            storePassword = "gmsschool"
            keyAlias = "gms"
            keyPassword = "gmsschool"
        }
    }

    buildTypes {
        debug {
            signingConfig = signingConfigs.getByName("gms")
        }
        release {
            // Not minified on purpose: the app is small, and shrinking can break
            // EncryptedSharedPreferences / serialization if keep rules drift.
            isMinifyEnabled = false
            signingConfig = signingConfigs.getByName("gms")
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions {
        jvmTarget = "17"
    }
    buildFeatures {
        compose = true
        buildConfig = true
    }
    packaging {
        resources {
            excludes += "/META-INF/{AL2.0,LGPL2.1}"
        }
    }
}

dependencies {
    val composeBom = platform("androidx.compose:compose-bom:2024.12.01")
    implementation(composeBom)
    implementation("androidx.compose.ui:ui")
    implementation("androidx.compose.ui:ui-graphics")
    implementation("androidx.compose.ui:ui-tooling-preview")
    implementation("androidx.compose.material3:material3")
    implementation("androidx.compose.material:material-icons-extended")
    debugImplementation("androidx.compose.ui:ui-tooling")

    implementation("androidx.core:core-ktx:1.15.0")
    implementation("androidx.activity:activity-compose:1.9.3")
    implementation("androidx.navigation:navigation-compose:2.8.5")
    implementation("androidx.lifecycle:lifecycle-runtime-compose:2.8.7")

    implementation("com.squareup.okhttp3:okhttp:4.12.0")
    implementation("org.jetbrains.kotlinx:kotlinx-serialization-json:1.7.3")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.9.0")
    implementation("io.coil-kt:coil-compose:2.7.0")
    implementation("androidx.work:work-runtime-ktx:2.9.1")
    implementation("androidx.security:security-crypto:1.1.0-alpha06")
}
