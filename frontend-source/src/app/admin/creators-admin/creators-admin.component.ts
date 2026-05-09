// @ts-nocheck
import {Component, OnInit, ViewEncapsulation} from '@angular/core';
import {Observable} from 'rxjs';
import {DatatableService} from '@common/datatable/datatable.service';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {Toast} from '@common/core/ui/toast.service';
import {CurrentUser} from '@common/auth/current-user';

@Component({
    selector: 'creators-admin',
    templateUrl: './creators-admin.component.html',
    styleUrls: ['./creators-admin.component.scss'],
    providers: [DatatableService],
    encapsulation: ViewEncapsulation.None,
})
export class CreatorsAdminComponent implements OnInit {
    public filters: any[] = [];
    public creators$ = this.datatable.data$ as Observable<any[]>;
    public editing: any = null;
    public form: any = {};

    constructor(
        public currentUser: CurrentUser,
        public datatable: DatatableService<any>,
        private http: AppHttpClient,
        private toast: Toast,
    ) {}

    ngOnInit() {
        this.datatable.init({uri: 'admin/creators'});
    }

    public openEdit(c: any) {
        this.editing = c;
        this.form = {
            display_name: c.display_name || '',
            bio: c.bio || '',
            profile_photo: c.profile_photo || '',
            contact_email: c.contact_email || '',
            website_url: c.website_url || '',
            youtube_url: c.youtube_url || '',
            twitter_url: c.twitter_url || '',
            instagram_url: c.instagram_url || '',
            facebook_url: c.facebook_url || '',
        };
    }

    public closeEdit() { this.editing = null; }

    public saveEdit() {
        if (!this.editing) return;
        this.http.post('admin/creators/' + this.editing.id, this.form).subscribe(
            () => { this.toast.open('Creator updated.'); this.closeEdit(); this.datatable.reset(); },
            () => this.toast.open('Failed to save'),
        );
    }

    public toggleRole(c: any) {
        const verb = c.role === 'creator' ? 'Revoke' : 'Restore';
        if (!confirm(verb + ' creator access for ' + c.username + '?')) return;
        this.http.post('admin/creators/' + c.id + '/toggle', {}).subscribe(
            () => { this.datatable.reset(); this.toast.open('Updated.'); },
            () => this.toast.open('Failed'),
        );
    }
}
