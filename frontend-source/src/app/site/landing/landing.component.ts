import {ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {Settings} from '@common/core/config/settings.service';
import {ReplaySubject} from 'rxjs';
import {LandingContent} from './landing-content';
import {AppHttpClient} from '@common/core/http/app-http-client.service';

@Component({
    selector: 'landing',
    templateUrl: './landing.component.html',
    styleUrls: ['./landing.component.scss'],
    // Global styles (uniquely lp-* prefixed) so the layout can't fail to
    // apply due to view-encapsulation attribute mismatches.
    encapsulation: ViewEncapsulation.None,
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LandingComponent implements OnInit {
    public content$ = new ReplaySubject<LandingContent>(1);

    public featured: any = null;
    public popular: any[] = [];
    public series: any[] = [];

    constructor(
        public settings: Settings,
        private http: AppHttpClient,
        private cd: ChangeDetectorRef,
    ) {}

    ngOnInit() {
        this.settings.all$().subscribe(() => {
            this.content$.next(this.settings.getJson('landing.appearance'));
        });
        this.loadCatalog();
    }

    private loadCatalog() {
        this.http.get('titles', {perPage: 40, orderBy: 'popularity', orderDir: 'desc'})
            .subscribe((res: any) => {
                const data = (res && res.pagination && res.pagination.data) || (res && res.data) || [];
                const withPoster = data.filter((t: any) => !!t.poster);

                this.featured = withPoster.find((t: any) => !!t.backdrop) || withPoster[0] || null;

                this.popular = withPoster.slice(0, 16);
                this.series = withPoster.filter((t: any) => t.is_series || t.type === 'series').slice(0, 16);

                this.cd.markForCheck();
            }, () => { /* graceful: hero falls back to gradient + copy */ });
    }

    public backdropUrl(t: any): string | null {
        return t ? (t.backdrop || t.poster || null) : null;
    }

    public scrollToFeatures(el: HTMLElement) {
        if (el) {
            el.scrollIntoView({behavior: 'smooth', block: 'start'});
        }
    }
}
