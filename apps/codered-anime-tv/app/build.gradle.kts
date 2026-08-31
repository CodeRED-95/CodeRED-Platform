import java.util.Properties

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    id("org.jetbrains.kotlin.plugin.compose")
}

// Datos de firma de release. El archivo esta fuera del control de versiones,
// asi que en una copia sin el (por ejemplo, una integracion continua) el build
// de release sale sin firmar en vez de fallar.
val keystoreProperties = Properties().apply {
    val file = rootProject.file("keystore.properties")
    if (file.exists()) file.inputStream().use { load(it) }
}

android {
    namespace = "lat.codered.anime.tv"
    compileSdk = 35

    defaultConfig {
        applicationId = "lat.codered.anime.tv"
        minSdk = 23
        targetSdk = 35
        versionCode = 1
        versionName = "0.1.0"
        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
    }

    signingConfigs {
        if (keystoreProperties.getProperty("storeFile") != null) {
            create("release") {
                storeFile = rootProject.file(keystoreProperties.getProperty("storeFile"))
                storePassword = keystoreProperties.getProperty("storePassword")
                keyAlias = keystoreProperties.getProperty("keyAlias")
                keyPassword = keystoreProperties.getProperty("keyPassword")
            }
        }
    }

    lint {
        // lintVital revienta por su cuenta ("Unexpected failure during lint
        // analysis", un fallo del propio lint) y bloqueaba el empaquetado de
        // release. El analisis se sigue pudiendo lanzar a mano con :app:lint.
        checkReleaseBuilds = false
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.findByName("release")
            // R8 queda desactivado a proposito: el parser usa Jsoup y reflexion
            // de OkHttp, y ofuscar sin reglas propias romperia el scraping.
            isMinifyEnabled = false
            isShrinkResources = false
        }
    }

    // Dos APK del mismo codigo: uno por formato de dispositivo.
    flavorDimensions += "form"

    productFlavors {
        create("tv") {
            dimension = "form"
            resValue("string", "app_name", "CodeRED Anime TV")
            buildConfigField("boolean", "IS_TV_BUILD", "true")
        }
        create("mobile") {
            dimension = "form"
            resValue("string", "app_name", "CodeRED Anime")
            buildConfigField("boolean", "IS_TV_BUILD", "false")
        }
    }

    buildFeatures {
        compose = true
        buildConfig = true
    }

    compileOptions {
        // Fire OS 7 (Android 7.1, API 25) no trae las APIs de Java 8 que usan
        // algunas dependencias: sin esto, Jsoup revienta con NoSuchMethodError
        // en ThreadLocal.withInitial y la portada no carga.
        isCoreLibraryDesugaringEnabled = true
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.5")

    implementation("androidx.activity:activity-compose:1.9.3")
    implementation("androidx.compose.foundation:foundation:1.7.6")
    implementation("androidx.compose.material3:material3:1.3.1")
    // Set curado de iconos para el carril de navegacion (ya venia como
    // dependencia transitiva de material3; se declara para fijarlo).
    implementation("androidx.compose.material:material-icons-core:1.7.8")
    implementation("androidx.compose.ui:ui:1.7.6")
    implementation("androidx.compose.ui:ui-tooling-preview:1.7.6")
    implementation("androidx.lifecycle:lifecycle-runtime-compose:2.8.7")
    implementation("androidx.lifecycle:lifecycle-viewmodel-compose:2.8.7")
    implementation("androidx.tv:tv-foundation:1.0.0")
    implementation("androidx.tv:tv-material:1.1.0")

    implementation("androidx.media3:media3-exoplayer:1.5.1")
    implementation("androidx.media3:media3-exoplayer-hls:1.5.1")
    implementation("androidx.media3:media3-session:1.5.1")
    implementation("androidx.media3:media3-ui:1.5.1")

    implementation("com.squareup.okhttp3:okhttp:4.12.0")
    implementation("org.jsoup:jsoup:1.18.3")
    implementation("io.coil-kt.coil3:coil-compose:3.0.4")
    implementation("io.coil-kt.coil3:coil-network-okhttp:3.0.4")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.9.0")

    testImplementation("junit:junit:4.13.2")
}
