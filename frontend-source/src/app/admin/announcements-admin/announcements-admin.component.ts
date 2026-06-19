// @ts-nocheck
import {ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {Toast} from '@common/core/ui/toast.service';
import {CurrentUser} from '@common/auth/current-user';

/**
 * Admin announcements (Phase 1): compose an announcement and send it to the
 * chosen audience over the in-app channel (database notifications → bell).
 * Self-contained — plain http fetch + local list, no Vebto datatable.
 */
@Component({
    selector: 'announcements-admin',
    templateUrl: './announcements-admin.component.html',
    styleUrls: ['./announcements-admin.component.scss'],
    encapsulation: ViewEncapsulation.None,
})
export class AnnouncementsAdminComponent implements OnInit {
    public rows: any[] = [];
    public loading = false;
    public saving = false;
    public sendingId: number | null = null;

    public readonly types = [
        {value: 'new_content',      label: 'New content release'},
        {value: 'platform_update',  label: 'Platform update'},
        {value: 'featured_creator', label: 'Featured creator'},
        {value: 'company_news',     label: 'Company news'},
        {value: 'general',          label: 'General'},
    ];
    public readonly audiences = [
        {value: 'all',      label: 'All users'},
        {value: 'viewers',  label: 'Viewers only'},
        {value: 'creators', label: 'Creators only'},
    ];

    public form: any = this.blankForm();

    constructor(
        public currentUser: CurrentUser,
        private http: AppHttpClient,
        private toast: Toast,
        private cd: ChangeDetectorRef,
    ) {}

    ngOnInit() { this.load(); }

    private blankForm() {
        return {id: null, title: '', body: '', type: 'general', audience: 'all', link_url: '', image: null};
    }

    public load() {
        this.loading = true;
        this.http.get('admin/announcements', {perPage: 50}).subscribe(
            (res: any) => {
                this.rows = res?.pagination?.data || [];
                this.loading = false;
                this.cd.markForCheck();
            },
            () => { this.loading = false; this.cd.markForCheck(); },
        );
    }

    public typeLabel(v: string): string {
        return (this.types.find(t => t.value === v) || {}).label || v;
    }
    public audienceLabel(v: string): string {
        return (this.audiences.find(a => a.value === v) || {}).label || v;
    }

    public onImage(ev: Event) {
        const input = ev.target as HTMLInputElement;
        this.form.image = input.files && input.files.length ? input.files[0] : null;
    }

    public edit(a: any) {
        this.form = {
            id: a.id, title: a.title || '', body: a.body || '',
            type: a.type || 'general', audience: a.audience || 'all',
            link_url: a.link_url || '', image: null,
        };
        this.cd.markForCheck();
    }

    public resetForm() { this.form = this.blankForm(); this.cd.markForCheck(); }

    public save() {
        if (this.saving) return;
        if (!this.form.title || !this.form.title.trim()) { this.toast.open('Title is required.'); return; }
        this.saving = true;
        const fd = new FormData();
        fd.append('title', this.form.title.trim());
        fd.append('type', this.form.type);
        fd.append('audience', this.form.audience);
        if (this.form.body) fd.append('body', this.form.body);
        if (this.form.link_url) fd.append('link_url', this.form.link_url);
        if (this.form.image) fd.append('image', this.form.image);
        const url = this.form.id ? 'admin/announcements/' + this.form.id : 'admin/announcements';
        this.http.post(url, fd).subscribe(
            () => {
                this.saving = false;
                this.toast.open(this.form.id ? 'Announcement updated.' : 'Announcement saved as draft.');
                this.resetForm();
                this.load();
            },
            (err: any) => {
                this.saving = false;
                this.toast.open(err?.error?.message || 'Could not save announcement.');
            },
        );
    }

    public send(a: any) {
        if (this.sendingId) return;
        const who = this.audienceLabel(a.audience).toLowerCase();
        if (!confirm('Send "' + a.title + '" to ' + who + '? This delivers to their notification bell and cannot be undone.')) return;
        this.sendingId = a.id;
        this.http.post('admin/announcements/' + a.id + '/send', {}).subscribe(
            (res: any) => {
                this.sendingId = null;
                this.toast.open('Sent to ' + (res?.recipients_count ?? 0) + ' user(s).');
                this.load();
            },
            () => { this.sendingId = null; this.toast.open('Failed to send.'); },
        );
    }

    public remove(a: any) {
        if (!confirm('Delete "' + a.title + '"?')) return;
        this.http.delete('admin/announcements/' + a.id).subscribe(
            () => { this.toast.open('Deleted.'); this.load(); },
            () => this.toast.open('Failed to delete.'),
        );
    }
}
