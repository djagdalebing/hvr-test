<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Refuse write actions from a blocked account.
 *
 * Blocking was enforced ad hoc inside individual controllers, so it covered
 * the web SPA's community endpoints (HvnController) and the creator upload
 * tools, but not the mobile API's community endpoints, nor reviews or
 * ratings on either client. A blocked creator could still post, comment,
 * like and review -- only their uploads were actually stopped.
 *
 * As route middleware the guarantee is uniform, and a new write route
 * inherits it by being placed in the right group rather than by somebody
 * remembering to add a check.
 *
 * Reads are deliberately untouched: a blocked user can still sign out,
 * browse and see their own content. Blocking is a write lock, not a ban.
 */
class EnsureUserNotBlocked
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && method_exists($user, 'isBlocked') && $user->isBlocked()) {
            return response()->json(
                [
                    'message' =>
                        'Your account is blocked. Contact support if you think this is a mistake.',
                    'errors' => [],
                ],
                403,
            );
        }

        return $next($request);
    }
}
