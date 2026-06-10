// @ts-nocheck
import {ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {Toast} from '@common/core/ui/toast.service';
import {CurrentUser} from '@common/auth/current-user';

/**
 * Self-contained moderation screen. Deliberately does NOT use Vebto's
 * DatatableService / datatable-filters / datatable-footer or the
 * .datatable-page-header wrapper, because those carry global CSS that
 * conflicted with this custom two-surface (content + people) layout.
 * Everything here is a plain http fetch into a local array with a simple
 * prev/next pager and inline-styled markup.
 */
@Component({
    selector: 'moderation-admin',
    templateUrl: './moderation-admin.component.html',
    styleUrls: ['./moderation-admin.component.scss'],
    encapsulation: ViewEncapsulation.None,
})
export class ModerationAdminComponent implements OnInit {
    public mode: 'content' | 'people' = 'content';
    public status: 'pending' | 'approved' | 'rejected' = 'pending';

    public rows: any[] = [];
    public loading = false;
    public page = 1;
    public lastPage = 1;
    public total = 0;
    public perPage = 15;

    public rejectingId: number | null = null;
    public rejectReason = '';

    constructor(
        public currentUser: CurrentUser,
        private http: AppHttpClient,
        private toast: Toast,
        private cd: ChangeDetectorRef,
    ) {}

    ngOnInit() { this.load(); }

    private uri(): string {
        return this.mode === 'people' ? 'admin/people-moderation' : 'admin/moderation';
    }

    public load(page = 1) {
        this.loading = true;
        this.rejectingId = null;
        this.cd.markForCheck();
        this.http.get(this.uri(), {status: this.status, page, perPage: this.perPage}).subscribe(
            (res: any) => {
                const p = res?.pagination || {};
                this.rows = p.data || [];
                this.page = p.current_page || 1;
                this.lastPage = p.last_page || 1;
                this.total = p.total || this.rows.length;
                this.loading = false;
                this.cd.markForCheck();
            },
            () => { this.rows = []; this.loading = false; this.cd.markForCheck(); },
        );
    }

    public switchMode(m: 'content' | 'people') {
        if (this.mode === m) return;
        this.mode = m;
        this.load(1);
    }

    public switchStatus(s: 'pending' | 'approved' | 'rejected') {
        if (this.status === s) return;
        this.status = s;
        this.load(1);
    }

    public prev() { if (this.page > 1) this.load(this.page - 1); }
    public next() { if (this.page < this.lastPage) this.load(this.page + 1); }

    // ---- shared helpers ----
    public posterUrl(t: any): string | null {
        const a = t?.poster;
        if (!a) return null;
        if (/^https?:\/\//.test(a)) return a;
        if (a.charAt(0) === '/') return a;
        if (a.indexOf('storage/') === 0) return '/' + a;
        return '/storage/' + a;
    }
    public personPhoto(p: any): string | null { return this.posterUrl(p); }

    public creator(t: any): any {
        return t?.videos && t.videos[0] && t.videos[0].user ? t.videos[0].user : null;
    }

    // ---- content (titles) ----
    public approve(t: any) {
        if (!confirm('Approve "' + t.name + '"? It will become visible to viewers.')) return;
        this.http.post('admin/moderation/' + t.id + '/approve', {}).subscribe(
            () => { this.toast.open('Approved.'); this.load(this.page); },
            () => this.toast.open('Failed to approve'),
        );
    }
    public openReject(t: any) { this.rejectingId = t.id; this.rejectReason = ''; }
    public cancelReject() { this.rejectingId = null; this.rejectReason = ''; }
    public confirmReject(t: any) {
        const reason = (this.rejectReason || '').trim();
        this.http.post('admin/moderation/' + t.id + '/reject', {reason}).subscribe(
            () => { this.rejectingId = null; this.rejectReason = ''; this.toast.open('Rejected.'); this.load(this.page); },
            () => this.toast.open('Failed to reject'),
        );
    }

    // ---- people ----
    public approvePerson(p: any) {
        if (!confirm('Approve "' + p.name + '"? They will appear on public pages.')) return;
        this.http.post('admin/people-moderation/' + p.id + '/approve', {}).subscribe(
            () => { this.toast.open('Approved.'); this.load(this.page); },
            () => this.toast.open('Failed to approve'),
        );
    }
    public rejectPerson(p: any) {
        if (!confirm('Reject "' + p.name + '"? They will be removed from any titles.')) return;
        this.http.post('admin/people-moderation/' + p.id + '/reject', {}).subscribe(
            () => { this.toast.open('Rejected.'); this.load(this.page); },
            () => this.toast.open('Failed to reject'),
        );
    }
}
