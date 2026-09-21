import {ChangeDetectorRef, Component, Input, OnInit, ViewEncapsulation} from '@angular/core';
import {AppHttpClient} from '@common/core/http/app-http-client.service';

/**
 * Season + episode guide for a series page.
 *
 * The stock title page showed a series as two tiny season numbers in the
 * secondary details panel, with the actual episode list reachable only at
 * /season/{n}. There was no way to tell a series had episodes, let alone
 * reach one, so this panel puts them on the page itself.
 *
 * Its own component rather than an edit to title-page-container's siblings
 * because extract-component-css.php regenerates every original component's
 * SCSS from the shipped bundle before each build -- styles on an HVN
 * component are the ones that survive.
 */
@Component({
    selector: 'hvn-series-episodes',
    templateUrl: './series-episodes.component.html',
    styleUrls: ['./series-episodes.component.scss'],
    encapsulation: ViewEncapsulation.None,
})
export class SeriesEpisodesComponent implements OnInit {
    @Input() titleId: number;
    @Input() titleName: string;

    public seasons: any[] = [];
    public active: any = null;
    public loading = true;

    constructor(private http: AppHttpClient, private cd: ChangeDetectorRef) {}

    ngOnInit() {
        if (!this.titleId) { this.loading = false; return; }
        this.http.get(`titles/${this.titleId}/episode-guide`).subscribe(
            (res: any) => {
                this.seasons = res?.seasons || [];
                this.active = this.seasons.length ? this.seasons[0] : null;
                this.loading = false;
                this.cd.markForCheck();
            },
            () => { this.loading = false; this.cd.markForCheck(); },
        );
    }

    public selectSeason(s: any) {
        this.active = s;
        this.cd.markForCheck();
    }

    public episodeLink(ep: any): any[] {
        return [
            '/titles', this.titleId, this.titleName || '-',
            'season', ep.season, 'episode', ep.number,
        ];
    }

    public posterFor(ep: any): string | null {
        const p = ep?.poster;
        if (!p) return null;
        if (/^https?:\/\//.test(p)) return p;
        return p.charAt(0) === '/' ? p : '/' + p;
    }
}
