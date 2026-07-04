# Dejati POS Android APK

Native Android app that consumes `https://api.dejaticoffee.com`. It is intentionally separate from `/www` and from the API project.

## Open and Build

1. Open `mobile-apk` in Android Studio.
2. Let Android Studio install/sync the Android Gradle Plugin.
3. Build a debug APK: `Build > Build Bundle(s) / APK(s) > Build APK(s)`.
4. Install the generated APK on Android.

CLI, once Gradle/Android SDK is installed:

```bash
cd mobile-apk
gradle :app:assembleDebug
```

The debug APK will be generated under:

```text
mobile-apk/app/build/outputs/apk/debug/app-debug.apk
```

## API URL

Default API URL is set in `app/build.gradle`:

```gradle
buildConfigField 'String', 'API_BASE_URL', '"https://api.dejaticoffee.com"'
```

For local testing, change it to your machine IP, for example `http://192.168.1.20:8090`.

## Features Implemented

- Login with bearer-token API auth.
- Product/catalog loading.
- Mobile cashier cart.
- Paid transaction and open bill creation.
- Transaction history.
- Daily report summary.
- Product creation.
- Printer handoff using Android share intents and RawBT-compatible text payload flow.
