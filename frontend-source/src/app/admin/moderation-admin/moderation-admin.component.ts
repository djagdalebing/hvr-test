// @ts-nocheck
import {ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {Observable} from 'rxjs';
import {DatatableService} from '@common/datatable/datatable.service';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {Toast} from '@common/core/ui/toast.service';
import {CurrentUser} from '@common/auth/current-user';

@Component({
    selector: 'moderation-admin',
    templateUrl: './moderation-admin.component.html',
    styleUrls: ['./moderation-admin.component.scss'],
    providers: [DatatableService],
    encapsulation: ViewEncapsulation.None,
})
export class ModerationAdminComponent implements OnInit {
    public filters: any[] = [];
    public titles$ = this.datatable.data$ as Observable<any[]>;
    public status: 'pending' | 'approved' | 'rejected' = 'pending';
    // Two moderation surfaces share this screen: creator titles ('content')
    // and creator-added people ('people'). Content uses the shared datatable;
    // people use a standalone fetch (separate array) so the two never bleed
    // into each other's table.
    public mode: 'content' | 'people' = 'content';

    public rejectingId: number | null = null;
    public rejectReason = '';

    // People surface (independent of the datatable).
    public people: any[] = [];
    public peopleLoading = false;

    constructor(
        public currentUser: CurrentUser,
        public datatable: DatatableService<any>,
        private http: AppHttpClient,
        private toast: Toast,
        private cd: ChangeDetectorRef,
    ) {}

    ngOnInit() {
        this.datatable.init({uri: 'admin/moderation', staticParams: {status: this.status}});
    }

    public switchMode(m: 'content' | 'people') {
        if (this.mode === m) return;
        this.mode = m;
        this.rejectingId = null;
        if (m === 'people') {
            this.loadPeople();
        } else {
            this.datatable.reset({status: this.status});
        }
    }

    public switchStatus(s: 'pending' | 'approved' | 'rejected') {
        this.status = s;
        if (this.mode === 'people') {
            this.loadPeople();
        } else {
            this.datatable.reset({status: s});
        }
    }

    // ---- Content (titles) moderation ----
    public posterUrl(t: any): string | null {
        return t?.poster || null;
    }

    public creator(t: any): any {
        return t?.videos && t.videos[0] && t.videos[0].user ? t.videos[0].user : null;
    }

    public approve(t: any) {
        if (!confirm('Approve "' + t.name + '"? It will become visible to viewers.')) return;
        this.http.post('admin/moderation/' + t.id + '/approve', {}).subscribe(
            () => { this.toast.open('Approved.'); this.datatable.reset({status: this.status}); },
            () => this.toast.open('Failed to approve'),
        );
    }

    public openReject(t: any) {
        this.rejectingId = t.id;
        this.rejectReason = '';
    }
    public cancelReject() {
        this.rejectingId = null;
        this.rejectReason = '';
    }
    public confirmReject(t: any) {
        const reason = (this.rejectReason || '').trim();
        this.http.post('admin/moderation/' + t.id + '/reject', {reason}).subscribe(
            () => {
                this.rejectingId = null;
                this.rejectReason = '';
                this.toast.open('Rejected.');
                this.datatable.reset({status: this.status});
            },
            () => this.toast.open('Failed to reject'),
        );
    }

    // ---- People moderation (standalone fetch) ----
    private loadPeople() {
        this.peopleLoading = true;
        this.people = [];
        this.cd.markForCheck();
        this.http.get('admin/people-moderation', {status: this.status, perPage: 50}).subscribe(
            (res: any) => {
                this.people = res?.pagination?.data || [];
                this.peopleLoading = false;
                this.cd.markForCheck();
            },
            () => { this.peopleLoading = false; this.cd.markForCheck(); },
        );
    }

    public personPhoto(p: any): string | null {
        const a = p?.poster;
        if (!a) return null;
        if (/^https?:\/\//.test(a)) return a;
        if (a.charAt(0) === '/') return a;
        if (a.indexOf('storage/') === 0) return '/' + a;
        return '/storage/' + a;
    }

    public approvePerson(p: any) {
        if (!confirm('Approve "' + p.name + '"? They will appear on public pages.')) return;
        this.http.post('admin/people-moderation/' + p.id + '/approve', {}).subscribe(
            () => { this.toast.open('Approved.'); this.loadPeople(); },
            () => this.toast.open('Failed to approve'),
        );
    }

    public rejectPerson(p: any) {
        if (!confirm('Reject "' + p.name + '"? They will be removed from any titles.')) return;
        this.http.post('admin/people-moderation/' + p.id + '/reject', {}).subscribe(
            () => { this.toast.open('Rejected.'); this.loadPeople(); },
            () => this.toast.open('Failed to reject'),
        );
    }
}
