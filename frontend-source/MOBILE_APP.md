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

---

## Phase 2 — bundled store-ready build (DONE, verified on simulator)

The app now runs as a **bundled, offline-start** build (no `server.url`), which
is what Apple guideline 4.2 expects. It boots from local assets and talks to the
backend over **token-authenticated XHR** (no session cookie).

### How it works
- `capacitor.config.ts` has **no `server.url`**; `webDir: 'mobile-dist'`.
- `mobile-dist/` holds the exact deployed JS/CSS bundles + a static `index.html`
  with the server-injected `bootstrapData` stripped, so the app fetches
  `bootstrap-data` at runtime. With a stored bearer token, it boots
  **already-authenticated**. (Regenerate with `./build-mobile-dist.sh`.)
- Auth: `MobileApiInterceptor` attaches `Authorization: Bearer <token>` to every
  backend call; the backend's `AuthenticateBearerToken` middleware resolves it
  onto the session guard so all `secure/*` routes work without a cookie. Login
  AND register return a top-level `access_token` the interceptor stores.
- Images: a small rewriter in `index.html` absolutizes backend-relative
  `storage/…` media (logo, avatars, posters) to the backend origin, since
  `<img>` can't load them from `capacitor://localhost`.

### Rebuild / iterate
```bash
cd frontend-source
./build-mobile-dist.sh     # refresh bundled assets from the live deploy
npx cap copy ios           # copy webDir into the iOS project
# then rebuild the "App" scheme (Xcode or the iOS-Simulator build tool)
```

### Verified on iPhone 16 simulator
- Boots bundled → fetches config → landing + browse render, posters + logo load.
- Token login works with no session cookie; **survives a cold restart** (stays
  logged in). Safe-area navbar correct.

### Still required for actual store submission (needs paid accounts)
- Apple Developer Program ($99/yr) + signing; App Store Connect listing.
- Google Play Console ($25 once) for the Android build.
- Push notifications (APNs + FCM) if desired — not required to submit.
