# Mobile App

The Flutter app lives in `mobile/`. It talks to the same API as the web app.

## Android APK

Download `RescueLens-android.apk` from the [latest release](https://github.com/zenderfox20904/RescueLens/releases/latest).

The release APK points to `http://10.0.2.2:8000/api/v1`, which is your computer as seen from the **Android emulator**. So:

1. Start the API on port 8000 (`docker compose up --build` or `./scripts/setup.sh --run`).
2. Start an Android emulator and drag the APK onto it, or run `adb install RescueLens-android.apk`.
3. Sign in with any demo account, password `RescueLensDemo2026!`.

The map works without any key: when the build has no Google Maps key, the app shows OpenStreetMap tiles with the same incident markers. Add a Google Maps key (below) to use Google Maps instead.

## Point the app to another API

The API address is set at build time with `--dart-define=API_BASE_URL`:

| Target | Command |
|---|---|
| Android emulator, local API | `flutter run` (default `http://10.0.2.2:8000/api/v1`) |
| iOS simulator, local API | `flutter run -d ios` (default `http://127.0.0.1:8000/api/v1`) |
| Physical phone on the same WiFi | `flutter run --dart-define=API_BASE_URL=http://YOUR_LAN_IP:8000/api/v1` |
| Hosted API | `flutter build apk --release --dart-define=API_BASE_URL=https://your-api.example.com/api/v1` |

For a physical phone, start the API with `php artisan serve --host=0.0.0.0 --port=8000`.

## Build from source

```bash
cd mobile
flutter pub get
flutter build apk --release        # output: build/app/outputs/flutter-apk/app-release.apk
```

Optional Google Maps key for Android: add `MAPS_API_KEY=your_key` to `mobile/android/local.properties` (not committed), or set it for one build with `MAPS_API_KEY=your_key flutter build apk --release`. Without a key the app falls back to OpenStreetMap.

## iOS

Needs macOS, Xcode and CocoaPods.

1. Optional maps key (OpenStreetMap is used without one): create `mobile/ios/Flutter/Secrets.xcconfig` with `MAPS_API_KEY=your_key` (this file is gitignored).
2. `cd mobile && flutter pub get && cd ios && pod install && cd ..`
3. `open -a Simulator`
4. `flutter run -d ios --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1`
