# Voter Information System: Android and iOS app

This folder is the mobile app for the Voter Information System. It is a **hybrid app** made with [Capacitor](https://capacitorjs.com):
a native Android / iOS app that opens your own installation of the web application (for example `https://electionsoft.in/vis/`).

* Every screen, report and feature of the web application works in the app, with the same login, roles and permissions.
  New features added to the website appear in the app immediately, without publishing a new app version.
* Voter data stays on your server. The app keeps nothing on the phone except the login session.
* Native extras:
  * **Excel / PDF / voter slips / survey sheet** downloads are saved and opened in the phone's share sheet (WhatsApp, Drive, Files, print …).
  * Phone numbers open the dialler, WhatsApp links open WhatsApp.
  * App icon, splash screen, status bar colour, Android back button, offline screen when there is no internet.
* Web pages detect the app and hide what does not work inside it (browser Print buttons: use PDF instead).

| | |
|---|---|
| App name | Voter System |
| App ID (Android package / iOS bundle ID) | `in.electionsoft.vis` |
| Opens | `https://electionsoft.in/vis/login` |
| Android | 6.0 (API 23) and newer |
| iOS | 14 and newer |

## Change the server address or app ID

```bash
npm install
npm run server -- https://your-domain.in/vis     # https only
npx cap sync
```

To change the app ID or name, edit `capacitor.config.json` (and `android/app/build.gradle` `applicationId` / `namespace`)
before the first store upload. The ID cannot change after an app is published.

## Build the Android app

### Option A: on GitHub (no software to install)
1. The workflow `.github/workflows/mobile-app.yml` must be on the repository's default branch (merge this branch first).
2. GitHub > **Actions** > **Mobile app** > **Run workflow**. Running it accepts the Android SDK licence for that build.
3. When it finishes, download the **android-app** artifact: `app-debug.apk` can be installed on any Android phone for testing.
4. For the Play Store, add these repository secrets (Settings > Secrets and variables > Actions) and run it again; you then also get a
   signed `app-release.apk` and `app-release.aab`:
   `ANDROID_KEYSTORE_BASE64` (the .jks file, base64), `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD`.

### Option B: on a Windows / Mac / Linux PC
1. Install [Node.js 22](https://nodejs.org) and [Android Studio](https://developer.android.com/studio) (it installs the Android SDK and JDK).
2. In this folder: `npm install` then `npx cap sync android` then `npx cap open android`.
3. In Android Studio: **Build > Build App Bundle(s) / APK(s) > Build APK(s)** for a test APK,
   or **Build > Generate Signed App Bundle / APK** for the Play Store.

### Signing key (needed once, keep it safe for ever)
```bash
keytool -genkeypair -v -keystore voter-system.jks -alias voter-system -keyalg RSA -keysize 2048 -validity 10000
```
Then create `android/keystore.properties` (never commit it, it is in `.gitignore`):
```
storeFile=/full/path/voter-system.jks
storePassword=...
keyAlias=voter-system
keyPassword=...
```
If this key is lost, the Play Store app can never be updated again. Keep two copies off this computer.

### Share the APK without the Play Store
Copy `app-debug.apk` (or the signed release APK) to the website as `public/downloads/voter-system.apk`.
The page `https://electionsoft.in/vis/mobile-app` then shows a **Download Android app** button, with the steps for the phone.

## Build the iOS app (needs a Mac)
1. A Mac with [Xcode](https://apps.apple.com/app/xcode/id497799835), [CocoaPods](https://cocoapods.org) (`sudo gem install cocoapods`) and Node.js 22.
2. An [Apple Developer Program](https://developer.apple.com/programs/) membership (USD 99 per year) for App Store / TestFlight.
3. In this folder: `npm install`, `npx cap sync ios`, `cd ios/App && pod install`, then `npx cap open ios`.
4. In Xcode: select the **App** target > **Signing & Capabilities** > choose your Team. Then **Product > Archive** > **Distribute App** >
   App Store Connect. Testers can install it through **TestFlight** before it is public.

## Publishing notes
* **Google Play**: one-time USD 25 developer account. Upload the `.aab`. Fill in the Data safety form (the app shows data from your own server;
  it collects no data itself) and give the reviewers a demo login.
* **Apple App Store**: Apple rejects apps that are "just a website" (guideline 4.2). The native downloads / share sheet help, but give the
  reviewers a demo login with sample data and explain that it is a private tool for a campaign team. For a closed team,
  **TestFlight** (up to 10,000 testers) or Apple Business Manager custom apps avoid the public store review.
* Political-content rules: both stores require that the app is clearly a tool for your organisation and does not impersonate the
  Election Commission or a government body.

## Without any store: install from the website (PWA)
The web application itself is installable: open `https://electionsoft.in/vis/mobile-app` on the phone and follow the steps
(Android: **Install app** in Chrome; iPhone: Safari > Share > **Add to Home Screen**). It gets the same icon and opens full screen.

## Folder contents
| Path | What |
|---|---|
| `capacitor.config.json` | app ID, name, server address, splash / status bar settings |
| `www/` | offline screen shown when the phone has no internet |
| `assets/` | 1024 px icon and splash sources (`npm run assets` regenerates all sizes) |
| `android/` | Android Studio project |
| `ios/` | Xcode project |
| `scripts/set-server.js` | change the server address |
