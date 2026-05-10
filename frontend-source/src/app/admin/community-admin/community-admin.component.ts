// @ts-nocheck
import {Component, OnInit, ViewEncapsulation} from '@angular/core';
import {Observable} from 'rxjs';
import {DatatableService} from '@common/datatable/datatable.service';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {Toast} from '@common/core/ui/toast.service';
import {CurrentUser} from '@common/auth/current-user';

@Component({
    selector: 'community-admin',
    templateUrl: './community-admin.component.html',
    styleUrls: ['./community-admin.component.scss'],
    providers: [DatatableService],
    encapsulation: ViewEncapsulation.None,
})
export class CommunityAdminComponent implements OnInit {
    public filters: any[] = [];
    public posts$ = this.datatable.data$ as Observable<any[]>;
    public editing: any = null;
    public form: any = {};

    constructor(
        public currentUser: CurrentUser,
        public datatable: DatatableService<any>,
        private http: AppHttpClient,
        private toast: Toast,
    ) {}

    ngOnInit() {
        this.datatable.init({uri: 'admin/community'});
    }

    public openEdit(p: any) {
        this.editing = p;
        this.form = {
            title: p.title || '',
            body: p.body || '',
            status: p.status || 'published',
        };
    }

    public closeEdit() { this.editing = null; }

    public saveEdit() {
        if (!this.editing) return;
        this.http.post('admin/community/' + this.editing.id, this.form).subscribe(
            () => { this.toast.open('Post updated.'); this.closeEdit(); this.datatable.reset(); },
            () => this.toast.open('Failed to save'),
        );
    }

    public toggleHide(p: any) {
        this.http.post('admin/community/' + p.id + '/hide', {}).subscribe(
            () => { this.datatable.reset(); this.toast.open('Status updated.'); },
            () => this.toast.open('Failed'),
        );
    }

    public togglePin(p: any) {
        this.http.post('admin/community/' + p.id + '/pin', {}).subscribe(
            (res: any) => {
                this.datatable.reset();
                this.toast.open(res?.pinned ? 'Pinned to top.' : 'Unpinned.');
            },
            () => this.toast.open('Failed to update pin'),
        );
    }

    public deletePost(p: any) {
        if (!confirm('Permanently delete this post and all its comments?')) return;
        this.http.delete('admin/community/' + p.id).subscribe(
            () => { this.datatable.reset(); this.toast.open('Deleted.'); },
            () => this.toast.open('Failed to delete'),
        );
    }
}
