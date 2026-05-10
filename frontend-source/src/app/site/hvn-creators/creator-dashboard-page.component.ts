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
}
