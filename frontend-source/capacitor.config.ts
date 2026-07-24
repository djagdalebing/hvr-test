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
    webDir: 'dist/client',
    server: {
        androidScheme: 'https',
        // Phase 1: load the live web app inside the native shell.
        // Change to your production domain (hervisionnetwork.com) when ready,
        // or comment out for bundled/offline assets (Phase 2).
        url: 'https://aqua-narwhal-640720.hostingersite.com',
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
