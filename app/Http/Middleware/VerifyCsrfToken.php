<?php

namespace App\Http\Middleware;

use Closure;
use Common\Core\BaseVerifyCsrfToken;

class VerifyCsrfToken extends BaseVerifyCsrfToken
{
    /**
     * Token-authenticated requests (the native mobile app sends
     * `Authorization: Bearer …`) carry no ambient cookie, so they cannot be
     * forged cross-site and don't need CSRF protection. Skip it for them; all
     * cookie-based (web) requests still go through normal verification.
     */
    public function handle($request, Closure $next)
    {
        if ($request->bearerToken()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'secure/auth/login',
        'secure/auth/register',
        'secure/auth/logout',
        'secure/auth/password/email',
        'secure/update/run',
        'secure/videos/*/log-play'
    ];
}
