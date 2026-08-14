# Her Vision Network — iOS App Publishing Handoff

Everything needed to build, sign, and submit the **Her Vision Network** iOS app.

---

## 1. What this app is

A **Capacitor** app: a native iOS shell around the Her Vision Network web
front-end. It ships the web assets **bundled** inside the app (offline start,
App Store compliant) and talks to the live backend over token-authenticated API
calls. No web server is needed inside the app.

- **App name:** Her Vision Network
- **Bundle ID:** `com.hervisionnetwork.app`
- **Backend it talks to:** `https://aqua-narwhal-640720.hostingersite.com`
  (set in `frontend-source/capacitor.config.ts` and the bundled assets)
- **Platforms:** iOS and Android are both scaffolded and ready to build (see §5
  for iOS, §8 for Android).

---

## 2. Prerequisites (on the machine that will publish)

- A **Mac** with **Xcode** (latest; from the Mac App Store)
- **Apple Developer Program** membership — **$99/year**
  (required to sign, use TestFlight, and submit to the App Store)
- **Node.js 18+** and **npm**
- **CocoaPods** (`sudo gem install cocoapods` or `brew install cocoapods`)

---

## 3. What's in the handoff package

Inside `frontend-source/`:

| Path | What it is |
|---|---|
| `ios/` | The generated **Xcode project** — open `ios/App/App.xcworkspace` |
| `mobile-dist/` | The **bundled web assets** the app ships (already built) |
| `capacitor.config.ts` | App id, name, and bundled-mode config |
| `build-mobile-dist.sh` | Regenerates `mobile-dist/` from the live site (see §6) |
| `package.json` | Capacitor deps + helper scripts |
| `resources/icon.png` | 1024px app icon source (regenerate icons with `@capacitor/assets`) |
| `src/app/mobile/` | The app's mobile API layer (token auth, URL handling) |
| `MOBILE_APP.md` | Technical runbook / background |

> `node_modules/` is **not** included (run `npm install` — see §4).
> The full source is also in the Git repo: `https://github.com/djagdalebing/hvr-test`
> (branch `dummy`). The repo is the source of truth; `ios/` and `mobile-dist/`
> are regenerated from it via the commands below.

---

## 4. First-time setup

```bash
cd frontend-source
npm install                 # install Capacitor + tooling
npx cap sync ios            # sync web assets + native deps (runs pod install)
```

If `npx cap sync ios` errors on CocoaPods with a Ruby encoding message, run it
with a UTF-8 locale:

```bash
LANG=en_US.UTF-8 LC_ALL=en_US.UTF-8 npx cap sync ios
```

---

## 5. Build & run (to verify before submitting)

```bash
open ios/App/App.xcworkspace     # opens the project in Xcode
```

In Xcode:
1. Select the **App** target → **Signing & Capabilities** → set **Team** to your
   Apple Developer account (this provisions the bundle id automatically).
2. Pick a simulator or a connected iPhone and press **Run** (▶) to confirm it
   launches and login works.

---

## 6. If the web app changes (refresh the bundle)

The bundled assets are a snapshot of the deployed web front-end. Whenever the
website is redeployed and you want the app to include those changes:

```bash
cd frontend-source
./build-mobile-dist.sh      # re-pulls the live production assets into mobile-dist/
npx cap copy ios            # copies them into the Xcode project
```

Then rebuild in Xcode. (Day-to-day API/content changes are picked up live from
the backend and need **no** rebuild — only front-end code/UI changes do.)

---

## 7. Submit to the App Store

1. In Xcode, select **Any iOS Device (arm64)** as the run destination.
2. **Product ▸ Archive**.
3. When the Organizer opens: **Distribute App ▸ App Store Connect ▸ Upload**.
4. In **App Store Connect** (appstoreconnect.apple.com): create the app record
   (name, bundle id `com.hervisionnetwork.app`), add screenshots, description,
   privacy details, then submit the uploaded build for review.
5. Optional but recommended: push the build to **TestFlight** first to let
   reviewers/stakeholders try it on real devices before public release.

Apple review typically takes ~1–3 days.

---

## 8. Android (scaffolded — ready to build)

The Android project is already generated at `frontend-source/android/` — same
app id (`com.hervisionnetwork.app`), same bundled web app, same backend token
auth, with launcher icons + splash screens branded from `resources/icon.png`.
(Like `ios/`, the `android/` folder is git-ignored and generated locally; it's
included in the handoff package.)

**Prerequisites:** **Android Studio** (bundles a compatible JDK + the Android
SDK). Building from the command line otherwise needs the Android SDK and **JDK
17** (Capacitor 5's Gradle doesn't support newer JDKs).

**Build & run:**
```bash
cd frontend-source
open -a "Android Studio" android      # or: File ▸ Open ▸ frontend-source/android
```
In Android Studio: let Gradle sync, pick a device/emulator, press **Run** (▶).
CLI alternative once the SDK/JDK17 are set up: `cd android && ./gradlew assembleDebug`.

**If the web app changes** (same as iOS):
```bash
cd frontend-source
./build-mobile-dist.sh
npx cap copy android
```

**Publish (Google Play):**
1. In Android Studio: **Build ▸ Generate Signed Bundle / APK ▸ Android App Bundle
   (.aab)**, create/most a signing key.
2. Upload the `.aab` in the **Google Play Console** (**$25 one-time** fee) →
   create the app, fill store listing + data-safety, submit for review.

> Not built/verified on-device here — this machine has no Android SDK. The
> project is complete; Android Studio handles the SDK/JDK and the build.

---

## 9. Known follow-ups (not blocking submission)

- **Push notifications** — optional; needs Apple APNs + Google FCM setup.
- **Social sign-in (Google)** — needs native OAuth configuration; email/password
  login works today.
- **Production domain** — the app currently points at the Hostinger test URL.
  Before public launch, update the backend URL in `capacitor.config.ts` and the
  bundled assets to the production domain, then rebuild.
- A few throwaway QA accounts created during testing can be removed from
  **Admin → Users**.

---

## 10. Quick reference

```
Bundle ID:     com.hervisionnetwork.app
Open project:  frontend-source/ios/App/App.xcworkspace
Refresh bundle: ./build-mobile-dist.sh && npx cap copy ios
Submit:        Xcode ▸ Product ▸ Archive ▸ Distribute ▸ App Store Connect
Repo:          github.com/djagdalebing/hvr-test  (branch: dummy)
```
