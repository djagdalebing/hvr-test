import {ChangeDetectorRef, Component, Input, OnInit, ViewEncapsulation} from '@angular/core';
import {AppHttpClient} from '@common/core/http/app-http-client.service';

/**
 * Season index for a series page.
 *
 * The stock title page showed a series as two tiny season numbers in the
 * details panel, with the episode list reachable only at /season/{n} and
 * nothing pointing there. This puts a real, obvious way in on the page.
 *
 * It lists seasons, not episodes, on purpose: a show with a hundred episodes
 * would bury the rest of the page, and the per-season page already renders
 * them properly. Each row links straight to its season.
 *
 * Its own component rather than an edit to the sibling panels because
 * extract-component-css.php regenerates every original component's SCSS from
 * the shipped bundle before each build -- styles on an HVN component survive.
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
    public totalEpisodes = 0;
    public loading = true;

    constructor(private http: AppHttpClient, private cd: ChangeDetectorRef) {}

    ngOnInit() {
        if (!this.titleId) { this.loading = false; return; }
        this.http.get(`titles/${this.titleId}/episode-guide`).subscribe(
            (res: any) => {
                this.seasons = res?.seasons || [];
                this.totalEpisodes = res?.total_episodes || 0;
                this.loading = false;
                this.cd.markForCheck();
            },
            () => { this.loading = false; this.cd.markForCheck(); },
        );
    }

    public seasonLink(season: any): any[] {
        return ['/titles', this.titleId, this.titleName || '-', 'season', season.number];
    }

    public countLabel(season: any): string {
        const n = season.episode_count;
        const base = `${n} episode${n === 1 ? '' : 's'}`;
        // Only worth mentioning when some of the season is still unreleased.
        if (season.playable_count < n) {
            return `${base} · ${season.playable_count} available`;
        }
        return base;
    }
}
