import {ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit} from '@angular/core';
import {Settings} from '@common/core/config/settings.service';
import {ReplaySubject} from 'rxjs';
import {LandingContent} from './landing-content';
import {AppHttpClient} from '@common/core/http/app-http-client.service';

@Component({
    selector: 'landing',
    templateUrl: './landing.component.html',
    styleUrls: ['./landing.component.scss'],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class LandingComponent implements OnInit {
    public content$ = new ReplaySubject<LandingContent>(1);

    // Real catalog content for the cinematic hero + showcase row.
    public posterColumns: string[][] = [];
    public popular: any[] = [];

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
        this.http.get('titles', {perPage: 30, orderBy: 'popularity', orderDir: 'desc'})
            .subscribe((res: any) => {
                const data = (res && res.pagination && res.pagination.data) || res.data || [];
                const withPoster = data.filter((t: any) => !!t.poster);

                this.popular = withPoster.slice(0, 18);

                const posters = withPoster.map((t: any) => t.poster);
                const colCount = 6;
                const cols: string[][] = Array.from({length: colCount}, () => []);
                posters.forEach((p: string, i: number) => cols[i % colCount].push(p));
                // Duplicate each column so the vertical scroll loops seamlessly.
                this.posterColumns = cols
                    .filter(c => c.length)
                    .map(c => [...c, ...c]);

                this.cd.markForCheck();
            }, () => { /* fall back to gradient hero */ });
    }

    public isInlineIcon(url: string): boolean {
        return !url.includes('.') && !url.includes('/');
    }

    public scrollToFeatures(el: HTMLElement) {
        if (el) {
            el.scrollIntoView({behavior: 'smooth', block: 'start'});
        }
    }
}
