/**
 * Mobile (Capacitor) configuration.
 *
 * When the app runs bundled/offline inside a native shell, its web assets are
 * served from a local origin (capacitor://localhost / https://localhost), so
 * relative API calls ("secure/…") would hit the app itself instead of the
 * backend. MOBILE_BACKEND_URL is prepended to those calls by
 * MobileApiInterceptor — but ONLY when running as a native app, so the web
 * build is completely unaffected (same-origin, no prefix).
 *
 * Set this to your production API origin before shipping a store build.
 */
export const MOBILE_BACKEND_URL = 'https://aqua-narwhal-640720.hostingersite.com';

/** localStorage key holding the mobile bearer token (Sanctum personal token). */
export const MOBILE_TOKEN_KEY = 'hvn_mobile_token';

/** True when running inside the Capacitor native shell (iOS/Android). */
export function isNativeApp(): boolean {
    const cap = (window as any).Capacitor;
    return !!(cap && typeof cap.isNativePlatform === 'function' && cap.isNativePlatform());
}
