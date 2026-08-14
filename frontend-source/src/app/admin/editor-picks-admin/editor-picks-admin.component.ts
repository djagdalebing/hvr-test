// @ts-nocheck
import {ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {Toast} from '@common/core/ui/toast.service';
import {Settings} from '@common/core/config/settings.service';

/**
 * Admin screen to curate the homepage "Editor's Pick" row: pin up to 10 titles,
 * reorder them, and save. Self-contained (plain http + inline-styled markup),
 * matching the moderation-admin screen's approach.
 */
@Component({
    selector: 'editor-picks-admin',
    templateUrl: './editor-picks-admin.component.html',
    styleUrls: ['./editor-picks-admin.component.scss'],
    encapsulation: ViewEncapsulation.None,
})
export class EditorPicksAdminComponent implements OnInit {
    public readonly MAX = 10;
    public picks: any[] = [];       // pinned titles, in order
    public results: any[] = [];     // search results
    public query = '';
    public loading = false;
    public saving = false;
    public searching = false;
    private searchTimer: any;

    constructor(
        private http: AppHttpClient,
        private toast: Toast,
        public settings: Settings,
        private cd: ChangeDetectorRef,
    ) {}

    ngOnInit() {
        this.load();
    }

    load() {
        this.loading = true;
        this.cd.markForCheck();
        this.http.get('admin/editor-picks').subscribe(
            (res: any) => {
                this.picks = res?.titles || [];
                this.loading = false;
                this.cd.markForCheck();
            },
            () => {
                this.loading = false;
                this.cd.markForCheck();
            },
        );
    }

    onSearchChange() {
        clearTimeout(this.searchTimer);
        const q = (this.query || '').trim();
        if (!q) {
            this.results = [];
            this.searching = false;
            this.cd.markForCheck();
            return;
        }
        this.searching = true;
        this.cd.markForCheck();
        this.searchTimer = setTimeout(() => {
            this.http.get('admin/editor-picks/search', {query: q}).subscribe(
                (res: any) => {
                    this.results = res?.titles || [];
                    this.searching = false;
                    this.cd.markForCheck();
                },
                () => {
                    this.results = [];
                    this.searching = false;
                    this.cd.markForCheck();
                },
            );
        }, 300);
    }

    isPinned(t): boolean {
        return this.picks.some(p => p.id === t.id);
    }

    add(t) {
        if (this.isPinned(t)) return;
        if (this.picks.length >= this.MAX) {
            this.toast.open('You can pin at most ' + this.MAX + ' titles.');
            return;
        }
        this.picks = [...this.picks, {id: t.id, name: t.name, poster: t.poster, year: t.year, status: t.status}];
        this.cd.markForCheck();
    }

    remove(i: number) {
        this.picks.splice(i, 1);
        this.picks = [...this.picks];
        this.cd.markForCheck();
    }

    moveUp(i: number) {
        if (i <= 0) return;
        const a = this.picks;
        [a[i - 1], a[i]] = [a[i], a[i - 1]];
        this.picks = [...a];
        this.cd.markForCheck();
    }

    moveDown(i: number) {
        if (i >= this.picks.length - 1) return;
        const a = this.picks;
        [a[i + 1], a[i]] = [a[i], a[i + 1]];
        this.picks = [...a];
        this.cd.markForCheck();
    }

    save() {
        this.saving = true;
        this.cd.markForCheck();
        const ids = this.picks.map(p => p.id);
        this.http.post('admin/editor-picks', {ids}).subscribe(
            () => {
                this.saving = false;
                this.toast.open("Editor's Picks saved.");
                this.cd.markForCheck();
            },
            () => {
                this.saving = false;
                this.toast.open('Could not save — please try again.');
                this.cd.markForCheck();
            },
        );
    }

    posterUrl(t): string {
        const p = t && t.poster;
        if (!p) return '';
        if (/^https?:\/\//i.test(p)) return p;
        return this.settings.getBaseUrl() + '/' + String(p).replace(/^\/+/, '');
    }
}
