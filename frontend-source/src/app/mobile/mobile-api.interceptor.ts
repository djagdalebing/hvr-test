import {Injectable} from '@angular/core';
import {HttpEvent, HttpHandler, HttpInterceptor, HttpRequest} from '@angular/common/http';
import {Observable} from 'rxjs';
import {isNativeApp, MOBILE_BACKEND_URL, MOBILE_TOKEN_KEY} from './mobile.config';

/**
 * Makes the bundled native app talk to the remote backend:
 *  1. Rewrites relative API URLs (secure/… , api/…) to absolute MOBILE_BACKEND_URL.
 *  2. Attaches the stored bearer token (Sanctum) for token-based auth, since a
 *     native app can't rely on the web's same-origin session cookie.
 *
 * A complete no-op on the web build (isNativeApp() === false), so it can't
 * affect the existing site.
 */
@Injectable()
export class MobileApiInterceptor implements HttpInterceptor {
    intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
        if (!isNativeApp()) {
            return next.handle(req);
        }

        let request = req;

        // 1) Prefix relative API calls with the backend origin.
        if (MOBILE_BACKEND_URL && !/^https?:\/\//i.test(req.url)) {
            const base = MOBILE_BACKEND_URL.replace(/\/+$/, '');
            const path = req.url.replace(/^\/+/, '');
            request = request.clone({url: `${base}/${path}`});
        }

        // 2) Attach the bearer token when we have one and the call is to our API.
        const token = this.token();
        if (token && request.url.indexOf(MOBILE_BACKEND_URL) === 0) {
            request = request.clone({
                setHeaders: {Authorization: `Bearer ${token}`},
            });
        }

        return next.handle(request);
    }

    private token(): string | null {
        try {
            return window.localStorage.getItem(MOBILE_TOKEN_KEY);
        } catch (e) {
            return null;
        }
    }
}
