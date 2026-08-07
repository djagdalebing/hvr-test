import type {CapacitorConfig} from '@capacitor/cli';

/**
 * Capacitor wraps the existing Angular web app as a native iOS/Android app.
 *
 * Two modes:
 *  - PHASE 1 (current, fastest): `server.url` points at the live site, so the
 *    native shell loads the real web app — auth, API and every feature work
 *    with zero changes (100% reuse). Great for internal builds / TestFlight.
 *  - PHASE 2 (store-ready): comment out `server.url`, run `npm run mobile:sync`
 *    to bundle the built web assets (webDir) into the app for offline start,
 *    and switch the API to token auth against an absolute base URL.
 */
const config: CapacitorConfig = {
    appId: 'com.hervisionnetwork.app',
    appName: 'Her Vision Network',
    // PHASE 2 (store-ready): bundled offline shell. `mobile-dist` holds the real
    // production web assets (the exact deployed JS/CSS bundles) plus a static
    // index.html with server-injected bootstrapData stripped, so the app boots
    // from local files and talks to the backend over token-authenticated XHR
    // (see MobileApiInterceptor). No `server.url` → Apple 4.2 compliant, works
    // offline-start, and the API base is the absolute backend URL.
    webDir: 'mobile-dist',
    server: {
        androidScheme: 'https',
        iosScheme: 'capacitor',
        // Phase 1 (internal/TestFlight) loaded the live site directly:
        //   url: 'https://aqua-narwhal-640720.hostingersite.com',
        // Phase 2 bundles the assets instead — no server.url.
        cleartext: false,
    },
    plugins: {
        SplashScreen: {
            launchShowDuration: 1200,
            backgroundColor: '#0d0b12',
            showSpinner: false,
        },
    },
};

export default config;
