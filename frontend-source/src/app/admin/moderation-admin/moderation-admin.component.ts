// @ts-nocheck
import {Component, OnInit, ViewEncapsulation} from '@angular/core';
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

    public rejectingId: number | null = null;
    public rejectReason = '';

    constructor(
        public currentUser: CurrentUser,
        public datatable: DatatableService<any>,
        private http: AppHttpClient,
        private toast: Toast,
    ) {}

    ngOnInit() {
        this.datatable.init({uri: 'admin/moderation', staticParams: {status: this.status}});
    }

    public switchStatus(s: 'pending' | 'approved' | 'rejected') {
        this.status = s;
        this.datatable.reset({status: s});
    }

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
}
