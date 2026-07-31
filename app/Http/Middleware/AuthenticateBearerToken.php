<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Lets the native mobile app authenticate `secure/*` (session-guarded) routes
 * with a Sanctum bearer token instead of the web session cookie.
 *
 * Why this exists: the SPA's API lives under the `secure/*` prefix, which uses
 * the `web` (session-cookie) guard. That's fine in a browser, but the Capacitor
 * iOS WKWebView does not reliably persist/send the session cookie across XHR
 * requests, so after login the very next authenticated call 401s and the app
 * bounces back to the login screen ("login is failing"). The login/register
 * response already hands the app a Sanctum token (see LoginController), and the
 * MobileApiInterceptor attaches it as `Authorization: Bearer …` — this
 * middleware makes the session-guarded routes honour it.
 *
 * Web requests are unaffected: they carry no bearer token, or already have a
 * session user, so this is a no-op for them.
 */
class AuthenticateBearerToken
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();

        // Only act when there's a token and no session user was already resolved.
        if ($bearer && !Auth::guard()->check()) {
            $token = PersonalAccessToken::findToken($bearer);

            if ($token && $token->tokenable) {
                // Set the user on the default (web) guard for THIS request only —
                // no session is written, so it's pure token auth.
                Auth::setUser($token->tokenable);

                try {
                    $token->forceFill(['last_used_at' => now()])->save();
                } catch (\Throwable $e) {
                    // last_used_at is best-effort; never block the request on it.
                }
            }
        }

        return $next($request);
    }
}
