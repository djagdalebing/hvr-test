// @ts-nocheck
import {ChangeDetectionStrategy, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {Toast} from '@common/core/ui/toast.service';
import {BehaviorSubject} from 'rxjs';

@Component({
    selector: 'creators-admin',
    templateUrl: './creators-admin.component.html',
    styleUrls: ['./creators-admin.component.scss'],
    encapsulation: ViewEncapsulation.None,
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CreatorsAdminComponent implements OnInit {
    public loading$ = new BehaviorSubject<boolean>(false);
    public creators$ = new BehaviorSubject<any[]>([]);
    public pagination$ = new BehaviorSubject<any>(null);
    public query = '';
    public editing: any = null;
    public form: any = {};

    constructor(private http: AppHttpClient, private toast: Toast) {}

    ngOnInit() { this.load(); }

    public load(page = 1) {
        this.loading$.next(true);
        this.http.get('admin/creators', {page, perPage: 20, query: this.query}).subscribe(
            (res: any) => {
                this.creators$.next(res.pagination.data || []);
                this.pagination$.next(res.pagination);
                this.loading$.next(false);
            },
            () => { this.toast.open('Failed to load creators'); this.loading$.next(false); },
        );
    }

    public search() { this.load(1); }

    public openEdit(creator: any) {
        this.editing = creator;
        // Our API returns the creator_profile fields flat on the creator object
        this.form = {
            display_name: creator.display_name || '',
            bio: creator.bio || '',
            website_url: creator.website_url || '',
            contact_email: creator.contact_email || '',
            youtube_url: creator.youtube_url || '',
            twitter_url: creator.twitter_url || '',
            instagram_url: creator.instagram_url || '',
            facebook_url: creator.facebook_url || '',
            profile_photo: creator.profile_photo || '',
        };
    }

    public closeEdit() { this.editing = null; }

    public saveEdit() {
        if (!this.editing) return;
        this.http.post('admin/creators/' + this.editing.id, this.form).subscribe(
            () => {
                this.toast.open('Profile updated.');
                this.closeEdit();
                this.load(this.pagination$.value?.current_page || 1);
            },
            () => this.toast.open('Failed to save'),
        );
    }

    public toggleRole(creator: any) {
        const verb = creator.role === 'creator' ? 'Revoke' : 'Restore';
        if (!confirm(verb + ' creator access for ' + creator.username + '?')) return;
        this.http.post('admin/creators/' + creator.id + '/toggle', {}).subscribe(
            () => { this.load(this.pagination$.value?.current_page || 1); this.toast.open('Role updated.'); },
            () => this.toast.open('Failed to update role'),
        );
    }
}
