import {Injectable} from '@angular/core';
import {HttpEvent, HttpHandler, HttpInterceptor, HttpRequest, HttpResponse} from '@angular/common/http';
import {Observable} from 'rxjs';
import {tap} from 'rxjs/operators';
import {isNativeApp, MOBILE_BACKEND_URL, MOBILE_TOKEN_KEY} from './mobile.config';

/** Device/token name used when requesting a Sanctum token for this app. */
const DEVICE_NAME = 'hvn-mobile-app';

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

        // 1) Prefix RELATIVE API calls (e.g. "secure/…") with the backend origin.
        //    Only rewrite URLs with no scheme — a URL that already carries any
        //    scheme (http://, https://, AND local ones like capacitor://,
        //    ionic://, file://) must be left untouched. The old check only
        //    excluded http(s), so it mangled local capacitor:// asset requests
        //    (e.g. the icon sprite) into "https://backend/capacitor://…",
        //    breaking them and blanking every mat-icon in the bundled app.
        if (MOBILE_BACKEND_URL && !/^[a-z][a-z0-9+.-]*:\/\//i.test(req.url)) {
            const base = MOBILE_BACKEND_URL.replace(/\/+$/, '');
            const path = req.url.replace(/^\/+/, '');
            request = request.clone({url: `${base}/${path}`});
        }

        // 2) On login/register, ask the backend to issue a bearer token
        //    (token_name) so we can authenticate without the web cookie.
        if (/auth\/(login|register)$/.test(request.url) && request.method === 'POST') {
            const body = request.body && typeof request.body === 'object' ? request.body : {};
            if (!(body as any).token_name) {
                request = request.clone({body: {...body, token_name: DEVICE_NAME}});
            }
        }

        // 3) Attach the bearer token when we have one and the call is to our API.
        const token = this.token();
        if (token && request.url.indexOf(MOBILE_BACKEND_URL) === 0) {
            request = request.clone({setHeaders: {Authorization: `Bearer ${token}`}});
        }

        // 4) Capture the token from a login response; clear it on logout.
        return next.handle(request).pipe(
            tap(event => {
                if (!(event instanceof HttpResponse)) return;
                const url = request.url;
                const b: any = event.body;
                if (/auth\/(login|register)$/.test(url) && b && b.access_token) {
                    this.store(b.access_token);
                } else if (/auth\/logout$/.test(url)) {
                    this.clear();
                }
            }),
        );
    }

    private store(token: string) {
        try { window.localStorage.setItem(MOBILE_TOKEN_KEY, token); } catch (e) {}
    }
    private clear() {
        try { window.localStorage.removeItem(MOBILE_TOKEN_KEY); } catch (e) {}
    }

    private token(): string | null {
        try {
            return window.localStorage.getItem(MOBILE_TOKEN_KEY);
        } catch (e) {
            return null;
        }
    }
}
