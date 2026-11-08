# Native Android client

The Android app uses Kotlin and Jetpack Compose for the native UI and platform integration. The Go API client is exposed through a small gomobile bind facade in go-client/mobilebridge.

## Build the Go Android library

Install Go, the Android SDK and NDK, then install the mobile tools:

```sh
go install golang.org/x/mobile/cmd/gomobile@latest
go install golang.org/x/mobile/cmd/gobind@latest
gomobile init
```

Build the AAR from the Go module:

```sh
cd go-client
gomobile bind -target=android -javapkg=com.klihub.shoppinglist -o ../android/app/libs/mobilebridge.aar ./mobilebridge
```

The generated AAR is a build artifact and is intentionally not committed. Rebuild it whenever the Go client or mobile bridge changes.

## Build the Android app

Open the android/ directory in Android Studio, allow Gradle to sync, then build and run the app configuration on an Android device or emulator. The project requires JDK 17, Android SDK 35, and an Android NDK for regenerating the Go AAR.

The first screen starts Nextcloud Login Flow v2 in the device's default browser. After the user grants access, the app polls the one-time endpoint, encrypts the returned per-device app password with an Android Keystore AES-GCM key, and verifies it by calling the Go client.

The app requires an HTTPS Nextcloud URL. It never asks for or stores the user's account password.
