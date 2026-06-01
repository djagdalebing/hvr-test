import {Injectable} from '@angular/core';
import {OverlayPanel} from '@common/core/ui/overlay-panel/overlay-panel.service';
import {CookieNoticeComponent} from '@common/gdpr/cookie-notice/cookie-notice.component';
import {Settings} from '@common/core/config/settings.service';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {catchError, map} from 'rxjs/operators';
import {COOKIE_LAW_COUNTRIES} from '@common/gdpr/cookie-notice/cookie-law-countries';
import {of} from 'rxjs';
import {AppearanceListenerService} from '@common/shared/appearance/appearance-listener.service';
import {Router} from '@angular/router';

@Injectable({
    providedIn: 'root'
})
export class CookieNoticeService {
    constructor(
        private panel: OverlayPanel,
        private settings: Settings,
        private http: AppHttpClient,
        private appearance: AppearanceListenerService,
        private router: Router,
    ) {}

    public async maybeShow() {
        if ( ! await this.shouldShow()) {
            return;
        }
        const position = this.settings.get('cookie-notice.position', 'bottom');
        this.panel.open(CookieNoticeComponent, {
            origin: 'global',
            position: {[position]: 0, left: 0},
            width: '100%',
            hasBackdrop: false,
        });
    }

    public shouldShow() {
        return this.settings.get('cookie_notice.enable') &&
            !this.appearance.active &&
            !this.router.url.includes('embed') &&
            !this.alreadyAccepted() &&
            this.userIsFromEu();
    }

    public alreadyAccepted(): boolean {
        return document.cookie.includes(
            `${this.settings.get('branding.site_name')}_cookie_notice`
        );
    }

    public userIsFromEu(): Promise<boolean> {
        // freegeoip.app is dead (project moved to ipstack) and CORS-blocks
        // from third-party origins, so this used to spam a noisy CORS error
        // into every page load. Assume EU (conservative default) — the
        // banner only shows anyway when cookie_notice.enable is on, so
        // skipping the geo round-trip is a no-op for sites that have it off.
        return Promise.resolve(true);
    }
}
