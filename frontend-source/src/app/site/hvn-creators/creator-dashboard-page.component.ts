// @ts-nocheck
import {ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {Router} from '@angular/router';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {CurrentUser} from '@common/auth/current-user';
import {Toast} from '@common/core/ui/toast.service';

@Component({
    selector: 'creator-dashboard-page',
    templateUrl: './creator-dashboard-page.component.html',
    styleUrls: ['./creator-dashboard-page.component.scss'],
    encapsulation: ViewEncapsulation.None,
    changeDetection: ChangeDetectionStrategy.Default,
})
export class CreatorDashboardPageComponent implements OnInit {
    public loading = true;
    public denied = false;
    public user: any = null;
    public profile: any = null;
    public posts: any[] = [];
    public content: any[] = [];
    public totals: any = {posts: 0, comments: 0, titles: 0};

    constructor(
        private router: Router,
        public currentUser: CurrentUser,
        private http: AppHttpClient,
        private toast: Toast,
        private cd: ChangeDetectorRef,
    ) {}

    ngOnInit() { this.load(); }

    private load() {
        this.loading = true; this.denied = false;
        this.http.get('creator/dashboard').subscribe(
            (res: any) => {
                this.user = res.user;
                this.profile = res.profile;
                this.posts = res.posts || [];
                this.content = res.content || [];
                this.totals = res.totals || this.totals;
                this.loading = false;
                this.cd.markForCheck();
            },
            err => {
                this.loading = false;
                if (err?.status === 401) this.router.navigateByUrl('/login');
                else if (err?.status === 403) this.denied = true;
                else this.toast.open('Failed to load dashboard');
                this.cd.markForCheck();
            },
        );
    }

    public initial(): string {
        const n = this.profile?.display_name || this.user?.username || '?';
        return n.charAt(0).toUpperCase();
    }

    public photoUrl(): string | null {
        const p = this.profile?.profile_photo;
        return p ? '/storage/' + p : null;
    }

    public displayName(): string {
        return this.profile?.display_name || this.user?.username || '';
    }

    // ----- upload state -----
    public showUpload = false;
    public uploading = false;
    public form: any = {
        title: '', type: 'movie', year: null, description: '',
        video_url: '', video_file: null, cover: null,
    };

    public toggleUpload() { this.showUpload = !this.showUpload; }

    public onFile(field: 'video_file' | 'cover', ev: Event) {
        const input = ev.target as HTMLInputElement;
        this.form[field] = input.files && input.files.length ? input.files[0] : null;
    }

    public submitUpload() {
        if (this.uploading) return;
        const f = this.form;
        if (!f.title || !f.title.trim()) { this.toast.open('Title is required.'); return; }
        if (!f.cover) { this.toast.open('Cover image is required.'); return; }
        if (!f.video_url && !f.video_file) {
            this.toast.open('Provide a video URL or upload a video file.'); return;
        }

        const fd = new FormData();
        fd.append('title', f.title.trim());
        fd.append('type', f.type || 'movie');
        if (f.year) fd.append('year', String(f.year));
        if (f.description) fd.append('description', f.description);
        if (f.video_url) fd.append('video_url', f.video_url);
        if (f.video_file) fd.append('video_file', f.video_file);
        fd.append('cover', f.cover);

        this.uploading = true;
        this.http.post('creator/content', fd).subscribe(
            () => {
                this.uploading = false;
                this.toast.open('Title uploaded.');
                this.form = {title: '', type: 'movie', year: null, description: '', video_url: '', video_file: null, cover: null};
                this.showUpload = false;
                this.load();
            },
            (err: any) => {
                this.uploading = false;
                const msg = err?.error?.message || (err?.error?.errors ? Object.values(err.error.errors).flat()[0] : 'Upload failed.');
                this.toast.open(String(msg));
            },
        );
    }

    public deleteTitle(t: any) {
        if (!confirm('Delete "' + (t.name || 'this title') + '"? This removes your video for it.')) return;
        this.http.delete('creator/content/' + t.id).subscribe(
            () => { this.toast.open('Deleted.'); this.load(); },
            (err: any) => this.toast.open(err?.error?.message || 'Failed to delete.'),
        );
    }
}
