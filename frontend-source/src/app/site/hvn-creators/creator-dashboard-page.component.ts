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
                this.projects = res.projects || [];
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
        tagline: '', runtime: null, genre: '', language: '', country: '',
        release_date: '', certification: '', original_title: '', trailer: '',
        video_url: '', video_file: null, cover: null, backdrop_image: null,
    };

    public toggleUpload() { this.showUpload = !this.showUpload; }

    public onFile(field: 'video_file' | 'cover', ev: Event) {
        const input = ev.target as HTMLInputElement;
        this.form[field] = input.files && input.files.length ? input.files[0] : null;
    }

    public uploadProgress = 0;

    public submitUpload() {
        if (this.uploading) return;
        const f = this.form;
        if (!f.title || !f.title.trim()) { this.toast.open('Title is required.'); return; }
        if (!f.cover) { this.toast.open('Cover image is required.'); return; }
        if (!f.video_url && !f.video_file) {
            this.toast.open('Provide a video URL or upload a video file.'); return;
        }

        // If a file was chosen, upload it straight to Cloudflare R2 first
        // (bypasses the PHP request-size limit), then save metadata with
        // the resulting public URL. A pasted URL skips straight to save.
        if (f.video_file) {
            this.uploading = true;
            this.uploadProgress = 0;
            this.http.post('creator/content/presign', {
                filename: f.video_file.name,
                content_type: f.video_file.type || 'video/mp4',
            }).subscribe(
                (res: any) => this.putToR2(res, f),
                (err: any) => {
                    this.uploading = false;
                    // 503 = cloud storage not configured → fall back to direct POST
                    if (err?.status === 503) {
                        this.toast.open('Cloud storage not set up; trying direct upload…');
                        this.saveContent(f, null);
                    } else {
                        this.toast.open(this.firstError(err) || 'Could not start upload.');
                    }
                },
            );
        } else {
            this.uploading = true;
            this.saveContent(f, null);
        }
    }

    private putToR2(presign: any, f: any) {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', presign.upload_url, true);
        xhr.setRequestHeader('Content-Type', presign.content_type || 'video/mp4');
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                this.cd.markForCheck();
            }
        };
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                this.saveContent(f, presign.public_url);
            } else {
                this.uploading = false;
                this.toast.open('Video upload to storage failed (' + xhr.status + ').');
                this.cd.markForCheck();
            }
        };
        xhr.onerror = () => {
            this.uploading = false;
            this.toast.open('Network error while uploading video.');
            this.cd.markForCheck();
        };
        xhr.send(f.video_file);
    }

    private saveContent(f: any, r2Url: string | null) {
        const fd = new FormData();
        fd.append('title', f.title.trim());
        fd.append('type', f.type || 'movie');
        // Optional metadata — only append when the user filled it in.
        const textFields = [
            'year', 'description', 'tagline', 'runtime', 'genre',
            'language', 'country', 'release_date', 'certification',
            'original_title', 'trailer', 'video_url',
        ];
        for (const k of textFields) {
            if (f[k] !== null && f[k] !== undefined && f[k] !== '') {
                fd.append(k, String(f[k]));
            }
        }
        if (r2Url) fd.append('r2_video_url', r2Url);
        else if (f.video_file) fd.append('video_file', f.video_file);
        fd.append('cover', f.cover);
        if (f.backdrop_image) fd.append('backdrop_image', f.backdrop_image);

        this.http.post('creator/content', fd).subscribe(
            () => {
                this.uploading = false;
                this.uploadProgress = 0;
                this.toast.open('Title uploaded.');
                this.form = {
                    title: '', type: 'movie', year: null, description: '',
                    tagline: '', runtime: null, genre: '', language: '', country: '',
                    release_date: '', certification: '', original_title: '', trailer: '',
                    video_url: '', video_file: null, cover: null, backdrop_image: null,
                };
                this.showUpload = false;
                this.load();
            },
            (err: any) => {
                this.uploading = false;
                this.uploadProgress = 0;
                this.toast.open(this.firstError(err) || 'Upload failed.');
            },
        );
    }

    // ----- profile edit -----
    public showProfileEdit = false;
    public savingProfile = false;
    public pForm: any = {};
    public pPhoto: File | null = null;

    public toggleProfileEdit() {
        this.showProfileEdit = !this.showProfileEdit;
        if (this.showProfileEdit) {
            const p = this.profile || {};
            this.pForm = {
                display_name:  p.display_name  || '',
                bio:           p.bio           || '',
                contact_email: p.contact_email || '',
                website_url:   p.website_url   || '',
                youtube_url:   p.youtube_url   || '',
                twitter_url:   p.twitter_url   || '',
                instagram_url: p.instagram_url || '',
                facebook_url:  p.facebook_url  || '',
            };
            this.pPhoto = null;
        }
    }

    public onProfilePhoto(ev: Event) {
        const input = ev.target as HTMLInputElement;
        this.pPhoto = input.files && input.files.length ? input.files[0] : null;
    }

    public saveProfile() {
        if (this.savingProfile) return;
        const fd = new FormData();
        Object.keys(this.pForm).forEach(k => {
            if (this.pForm[k] != null) fd.append(k, this.pForm[k]);
        });
        if (this.pPhoto) fd.append('profile_photo', this.pPhoto);

        this.savingProfile = true;
        this.http.post('creator/profile', fd).subscribe(
            (res: any) => {
                this.savingProfile = false;
                if (res?.profile) this.profile = res.profile;
                this.showProfileEdit = false;
                this.toast.open('Profile updated.');
            },
            (err: any) => {
                this.savingProfile = false;
                this.toast.open(this.firstError(err) || 'Failed to save.');
            },
        );
    }

    // ----- projects -----
    public projects: any[] = [];
    public projectForm: any = {open: false, id: null, title: '', role: '', year: null, description: '', url: '', image: null};

    public openNewProject() {
        this.projectForm = {open: true, id: null, title: '', role: '', year: null, description: '', url: '', image: null};
    }

    public openEditProject(p: any) {
        this.projectForm = {open: true, id: p.id, title: p.title || '', role: p.role || '', year: p.year || null,
            description: p.description || '', url: p.url || '', image: null};
    }

    public closeProject() { this.projectForm.open = false; }

    public onProjectImage(ev: Event) {
        const input = ev.target as HTMLInputElement;
        this.projectForm.image = input.files && input.files.length ? input.files[0] : null;
    }

    public saveProject() {
        const f = this.projectForm;
        if (!f.title || !f.title.trim()) { this.toast.open('Title is required.'); return; }
        const fd = new FormData();
        fd.append('title', f.title.trim());
        if (f.role) fd.append('role', f.role);
        if (f.year) fd.append('year', String(f.year));
        if (f.description) fd.append('description', f.description);
        if (f.url) fd.append('url', f.url);
        if (f.image) fd.append('image', f.image);

        const uri = f.id ? 'creator/projects/' + f.id : 'creator/projects';
        this.http.post(uri, fd).subscribe(
            () => { this.closeProject(); this.toast.open(f.id ? 'Project updated.' : 'Project added.'); this.load(); },
            (err: any) => this.toast.open(this.firstError(err) || 'Failed to save project.'),
        );
    }

    private firstError(err: any): string | null {
        // Laravel validation 422 returns {message, errors: {field: [msg, ...]}}.
        // Surface the first field error instead of the generic "given data
        // was invalid" message so the user knows what to fix.
        const errs = err?.error?.errors;
        if (errs && typeof errs === 'object') {
            for (const k of Object.keys(errs)) {
                if (Array.isArray(errs[k]) && errs[k].length) return errs[k][0];
            }
        }
        return err?.error?.message || null;
    }

    public deleteProject(p: any) {
        if (!confirm('Delete project "' + p.title + '"?')) return;
        this.http.delete('creator/projects/' + p.id).subscribe(
            () => { this.toast.open('Project deleted.'); this.load(); },
            (err: any) => this.toast.open(err?.error?.message || 'Failed to delete.'),
        );
    }

    public projectImage(p: any): string | null {
        return p?.image_path ? '/storage/' + p.image_path : null;
    }

    public deleteTitle(t: any) {
        if (!confirm('Delete "' + (t.name || 'this title') + '"? This removes your video for it.')) return;
        this.http.delete('creator/content/' + t.id).subscribe(
            () => { this.toast.open('Deleted.'); this.load(); },
            (err: any) => this.toast.open(err?.error?.message || 'Failed to delete.'),
        );
    }
}
