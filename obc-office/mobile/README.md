# OBC Office — mobile app (Android & iOS)

One Flutter codebase that builds **both the Android and the iOS app**. It talks to the Laravel web application in
`../web` through its REST API (`/api/v1`). Permissions and jurisdiction are exactly the same as on the web.

## What the app does

| Screen | Highlights |
|---|---|
| **Login** | Username or email, plus password. The server address can be changed under *Server settings*. |
| **Home** | Totals, today's schedule, open/overdue petitions, biodata this month, my tasks, state-wise (or district-wise) strength with posts filled. |
| **Members** | Search and filters (state, district, level, type, missing photo/mobile/DOB), photo list, profile with **Call** and **WhatsApp**. Add or edit with a **camera photo**, the configured required fields, custom fields and consent. |
| **Biodata** | Register with search and category chips, a record view showing all attached files (images open in-app, PDFs and Word files open in the phone's viewer), and **record a biodata by photographing its pages** one after another. |
| **More** | Schedule & Tours, Greetings (one-tap WhatsApp message), Circulars (marked read automatically, with attachments), Tasks (update status), Petitions (timeline, follow-ups, send status on WhatsApp), Programme reports with photos. |

The bottom menu and the *More* list are **built from the user's permissions** sent by the server. When the office
changes something in Settings, users tap *More → Refresh settings & lists* to pick up the change. This covers modules,
roles, dropdown lists, custom fields, required fields and the theme colour.

## Run it

Requirements: Flutter 3.50+ (Dart 3.14). Building the Android app also needs the Android SDK, and building the iOS app
needs a Mac with Xcode.

```bash
cd obc-office/mobile
flutter pub get
flutter run                                   # on a connected phone or emulator
```

The default server is `http://10.0.2.2:8000`, which is your computer's `php artisan serve` as seen from the Android
emulator. On a real phone, tap **Server settings** on the login screen and enter the server address.

## Build for release

Set the production server address once at build time, so users don't need to type it:

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://office.example.org
```

```bash
flutter build appbundle --release --dart-define=API_BASE_URL=https://office.example.org
```

```bash
flutter build ipa --release --dart-define=API_BASE_URL=https://office.example.org
```

- `apk` is for direct installation (WhatsApp it to users or host it on the portal). `appbundle` is for the Google
  Play Store. `ipa` is for the Apple App Store or TestFlight, and must be built on a Mac with Xcode.
- **Release builds only connect over HTTPS.** Plain `http://` works only in debug builds, for local testing.
- Before publishing, create your own Android signing key (`android/app/build.gradle.kts`, currently using the debug
  key) and set the Apple team in Xcode.
- The app ID is `com.vidhiinfo.obc_office`. Change it before the first store upload if needed.
- In the web app's `.env`, set `APP_URL` to the public HTTPS address. Photo links sent to the app are built from it.

## Code layout

```
lib/
  main.dart              app + theme (colour from server branding)
  api.dart               Dio client, token storage, multipart helper, error handling
  session.dart           signed-in user, permissions, master data (/meta)
  widgets/common.dart    avatars with auth, paged lists, search, file opener
  widgets/form_kit.dart  forms that show server validation errors per field, custom fields
  screens/               one file per module
test/app_test.dart       unit + widget tests (flutter test)
```
