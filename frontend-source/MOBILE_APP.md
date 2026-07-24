# Her Vision Network — Mobile App (Capacitor)

The mobile app wraps the existing Angular web app in a native iOS/Android
shell using **Capacitor 5**. Phase 1 loads the live site inside the shell
(fastest, 100% reuse); Phase 2 bundles the web assets for App Store / Play
Store submission.

## Prerequisites (on your Mac)
- **Node 16** (same as the web build) and the repo dependencies installed.
- **iOS:** Xcode + CocoaPods (`sudo gem install cocoapods`).
- **Android:** Android Studio (with an SDK + an emulator or a device).

## One-time setup
```bash
cd frontend-source
npm install                      # installs Capacitor + everything else
npm run mobile:add:ios           # creates the ios/ project   (needs Xcode)
npm run mobile:add:android       # creates the android/ project (needs Android Studio)
npx cap sync                     # wires plugins + config into the native projects
```

## Run it (Phase 1 — loads the live site)
```bash
npm run mobile:ios       # opens Xcode → press ▶ to run on a simulator/device
npm run mobile:android   # opens Android Studio → press ▶
```
Because `capacitor.config.ts` sets `server.url` to the live site, the app
loads the real web app — login, API, playback and every feature work with no
code changes. This is ideal for internal testing and TestFlight.

## Going store-ready (Phase 2 — bundled assets)
1. In `capacitor.config.ts`, **comment out `server.url`** so the app starts
   from bundled assets instead of the live site.
2. Move API calls to **token auth** against an absolute base URL (the backend
   already supports this — Sanctum + `MobileBootstrapData` + the `token_name`
   registration flow). A single Angular HTTP interceptor that (a) prefixes
   relative `secure/`/`api/` calls with the production origin and (b) attaches
   the bearer token is enough.
3. Build + copy the web assets and sync:
   ```bash
   npm run mobile:sync
   ```
4. Set app **icons + splash** (place source art, then `npx @capacitor/assets generate`).
5. Add native niceties: `@capacitor/status-bar`, `@capacitor/splash-screen`,
   `@capacitor/app` (deep links / back button), and **push notifications**
   (FCM/APNs) wired to the existing notification system.

## Recommended feature roadmap (build order)
- **Phase 1 (now):** working native shell over the live app (this scaffold).
- **Phase 2:** bundled assets + token auth + icons/splash + push.
- **Phase 3:** offline "continue watching", Chromecast/AirPlay, downloads.
- **Prerequisite for great mobile playback:** server-side transcoding to
  **HLS/adaptive bitrate** — the single biggest quality win on cellular.

## Notes
- `ios/` and `android/` are generated and git-ignored — build them locally.
- Adding Capacitor does **not** affect the web build or deploy (CI ignores
  `frontend-source`; `ng build` is unchanged).
- Apple may reject a pure website wrapper (guideline 4.2) — the Phase 2 native
  features (push, offline, share) are what make it a real app for submission.
