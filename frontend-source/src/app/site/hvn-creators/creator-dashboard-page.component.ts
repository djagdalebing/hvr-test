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
        // See creators-page.component.ts: filesystem url is 'storage' (no
        // leading slash), so accessor returns 'storage/avatars/...' which
        // must NOT get a second '/storage/' prefix.
        const a = this.user?.avatar;
        if (a) {
            if (/^https?:\/\//.test(a)) return a;
            if (a.charAt(0) === '/') return a;
            if (a.indexOf('storage/') === 0) return '/' + a;
            return '/storage/' + a;
        }
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
        budget: null, revenue: null, imdb_id: '', tmdb_id: null,
        // People are now picker selections: {person_id, name}. cast rows
        // additionally carry {character}. A person_id of null means nothing
        // selected yet.
        director: {person_id: null, name: ''},
        writer: {person_id: null, name: ''},
        cast: [] as Array<{person_id: number | null, name: string, character: string}>,
        video_url: '', video_file: null, cover: null, backdrop_image: null,
    };

    private blankForm() {
        return {
            title: '', type: 'movie', year: null, description: '',
            tagline: '', runtime: null, genre: '', language: '', country: '',
            release_date: '', certification: '', original_title: '', trailer: '',
            budget: null, revenue: null, imdb_id: '', tmdb_id: null,
            director: {person_id: null, name: ''},
            writer: {person_id: null, name: ''},
            cast: [],
            video_url: '', video_file: null, cover: null, backdrop_image: null,
        };
    }

    public addCast() { this.form.cast.push({person_id: null, name: '', character: ''}); }
    public removeCast(i: number) { this.form.cast.splice(i, 1); }

    // ---- People picker -------------------------------------------------
    // Each "slot" is the object holding {person_id, name} — form.director,
    // form.writer, or a cast row. We keep transient search state keyed by a
    // stable slot key so multiple pickers don't clobber each other.
    public peopleSearch: any = {};   // { [key]: {q, results, open, loading} }

    private slotKey(slot: any, kind: string, i?: number): string {
        return kind === 'cast' ? 'cast-' + i : kind;
    }

    public pickerState(key: string) {
        if (!this.peopleSearch[key]) {
            this.peopleSearch[key] = {q: '', results: [], open: false, loading: false};
        }
        return this.peopleSearch[key];
    }

    public onPeopleQuery(key: string, slot: any) {
        const st = this.pickerState(key);
        // Typing a fresh query invalidates any previous selection.
        slot.person_id = null;
        slot.name = st.q;
        const q = (st.q || '').trim();
        if (q.length < 2) { st.results = []; st.open = false; this.cd.markForCheck(); return; }
        st.loading = true; st.open = true;
        this.http.get('creator/people/search', {q}).subscribe(
            (res: any) => {
                st.results = res?.people || [];
                st.loading = false;
                this.cd.markForCheck();
            },
            () => { st.loading = false; this.cd.markForCheck(); },
        );
    }

    public pickPerson(key: string, slot: any, person: any) {
        slot.person_id = person.id;
        slot.name = person.name;
        const st = this.pickerState(key);
        st.q = person.name;
        st.results = [];
        st.open = false;
        this.cd.markForCheck();
    }

    public clearPerson(key: string, slot: any) {
        slot.person_id = null;
        slot.name = '';
        const st = this.pickerState(key);
        st.q = ''; st.results = []; st.open = false;
        this.cd.markForCheck();
    }

    public personPhoto(p: any): string | null {
        const a = p?.poster;
        if (!a) return null;
        if (/^https?:\/\//.test(a)) return a;
        if (a.charAt(0) === '/') return a;
        if (a.indexOf('storage/') === 0) return '/' + a;
        return '/storage/' + a;
    }

    // ---- Create-new-person inline form --------------------------------
    public newPerson: any = {open: false, key: null, slot: null, saving: false,
        name: '', description: '', gender: '', birth_date: '', birth_place: '',
        death_date: '', known_for: '', imdb_id: '', photo: null};

    public openCreatePerson(key: string, slot: any) {
        const st = this.pickerState(key);
        this.newPerson = {open: true, key, slot, saving: false,
            name: (st.q || '').trim(), description: '', gender: '', birth_date: '',
            birth_place: '', death_date: '', known_for: '', imdb_id: '', photo: null};
        st.open = false;
        this.cd.markForCheck();
    }

    public closeCreatePerson() { this.newPerson.open = false; this.cd.markForCheck(); }

    public onPersonPhoto(ev: Event) {
        const input = ev.target as HTMLInputElement;
        this.newPerson.photo = input.files && input.files.length ? input.files[0] : null;
    }

    public saveNewPerson() {
        const np = this.newPerson;
        if (np.saving) return;
        if (!np.name || !np.name.trim()) { this.toast.open('Name is required.'); return; }
        np.saving = true;
        const fd = new FormData();
        fd.append('name', np.name.trim());
        ['description', 'gender', 'birth_date', 'birth_place', 'death_date', 'known_for', 'imdb_id']
            .forEach(k => { if (np[k]) fd.append(k, String(np[k])); });
        if (np.photo) fd.append('photo', np.photo);
        this.http.post('creator/people', fd).subscribe(
            (res: any) => {
                np.saving = false;
                const person = res?.person;
                if (person) {
                    this.pickPerson(np.key, np.slot, person);
                    this.toast.open('Person created — pending review, but attached to this title.');
                }
                np.open = false;
                this.cd.markForCheck();
            },
            (err: any) => {
                np.saving = false;
                this.toast.open(err?.error?.message || 'Could not create person.');
                this.cd.markForCheck();
            },
        );
    }

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
            'budget', 'revenue', 'imdb_id', 'tmdb_id',
        ];
        for (const k of textFields) {
            if (f[k] !== null && f[k] !== undefined && f[k] !== '') {
                fd.append(k, String(f[k]));
            }
        }
        // Director / writer — prefer the picked person_id; fall back to the
        // typed name (legacy) when nothing was selected from the dropdown.
        if (f.director?.person_id)      fd.append('director_id', String(f.director.person_id));
        else if (f.director?.name?.trim()) fd.append('director', f.director.name.trim());
        if (f.writer?.person_id)        fd.append('writer_id', String(f.writer.person_id));
        else if (f.writer?.name?.trim())   fd.append('writer', f.writer.name.trim());
        // Cast: send as JSON {person_id?, name, character}. Drop empty rows.
        const cast = (f.cast || [])
            .map((c: any) => ({
                person_id: c?.person_id || null,
                name: (c?.name || '').trim(),
                character: (c?.character || '').trim(),
            }))
            .filter((c: any) => c.person_id || c.name !== '');
        if (cast.length) fd.append('cast', JSON.stringify(cast));
        if (r2Url) fd.append('r2_video_url', r2Url);
        else if (f.video_file) fd.append('video_file', f.video_file);
        fd.append('cover', f.cover);
        if (f.backdrop_image) fd.append('backdrop_image', f.backdrop_image);

        this.http.post('creator/content', fd).subscribe(
            () => {
                this.uploading = false;
                this.uploadProgress = 0;
                this.toast.open('Title uploaded.');
                this.form = this.blankForm();
                this.peopleSearch = {};
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

    // ----- view-only profile display helpers -----
    // (Profile editing now lives at /account/settings.)
    public socialLinks(): Array<{label: string, url: string}> {
        const p = this.profile || {};
        const out: Array<{label: string, url: string}> = [];
        if (p.website_url)   out.push({label: 'Website',   url: p.website_url});
        if (p.youtube_url)   out.push({label: 'YouTube',   url: p.youtube_url});
        if (p.twitter_url)   out.push({label: 'Twitter',   url: p.twitter_url});
        if (p.instagram_url) out.push({label: 'Instagram', url: p.instagram_url});
        if (p.facebook_url)  out.push({label: 'Facebook',  url: p.facebook_url});
        return out;
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
