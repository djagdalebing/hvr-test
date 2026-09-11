// @ts-nocheck
import {
    ChangeDetectionStrategy,
    ChangeDetectorRef,
    Component,
    ElementRef,
    HostListener,
    OnInit,
    ViewChild,
    ViewEncapsulation,
} from '@angular/core';
import {ActivatedRoute, Router} from '@angular/router';
import {AppHttpClient} from '@common/core/http/app-http-client.service';

@Component({
    selector: 'creator-profile-page',
    templateUrl: './creator-profile-page.component.html',
    styleUrls: ['./creator-profile-page.component.scss'],
    encapsulation: ViewEncapsulation.None,
    changeDetection: ChangeDetectionStrategy.Default,
})
export class CreatorProfilePageComponent implements OnInit {
    public loading = true;
    public notFound = false;
    public user: any = null;
    public profile: any = null;
    public projects: any[] = [];
    public titles: any[] = [];
    public posts: any[] = [];

    constructor(
        private route: ActivatedRoute,
        private router: Router,
        private http: AppHttpClient,
        private cd: ChangeDetectorRef,
    ) {}

    ngOnInit() {
        this.route.paramMap.subscribe(p => this.load(p.get('username')));
    }

    private load(username: string) {
        if (!username) return;
        this.loading = true; this.notFound = false;
        this.http.get('creators/' + encodeURIComponent(username)).subscribe(
            (res: any) => {
                this.user = res.user;
                this.profile = res.profile;
                this.projects = res.projects || [];
                this.titles = res.titles || [];
                this.posts = res.posts || [];
                this.loading = false;
                this.bioExpanded = false;
                this.bioOverflows = false;
                this.cd.markForCheck();
                // Measure once the bio has actually rendered.
                setTimeout(() => this.measureBio());
            },
            () => { this.loading = false; this.notFound = true; this.cd.markForCheck(); },
        );
    }

    public photoUrl(): string | null {
        // See creators-page.component.ts for the URL-normalization rationale:
        // the public-disk filesystem 'url' is 'storage' (no leading slash),
        // so url() returns 'storage/avatars/...' which we must NOT re-prefix.
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
        return (this.profile && this.profile.display_name) || (this.user && this.user.username) || '';
    }

    public initial(): string {
        return (this.displayName() || '?').charAt(0).toUpperCase();
    }

    public projectImage(p: any): string | null {
        return p?.image_path ? '/storage/' + p.image_path : null;
    }

    // ----- biography -----
    /** Long bios are collapsed behind a "Read more" toggle. */
    public bioExpanded = false;

    /**
     * Whether the clamped bio is actually cut off. Measured from the DOM rather
     * than guessed from character count — a character threshold disagreed with
     * the line clamp, so "Read more" could appear on a bio that was already
     * fully visible (and did nothing when clicked).
     */
    public bioOverflows = false;

    @ViewChild('bioEl') private bioEl?: ElementRef<HTMLElement>;

    public measureBio() {
        const el = this.bioEl && this.bioEl.nativeElement;
        if (!el) {
            return;
        }
        // Only meaningful while clamped; once expanded the toggle must stay.
        if (this.bioExpanded) {
            return;
        }
        const overflowing = el.scrollHeight > el.clientHeight + 2;
        if (overflowing !== this.bioOverflows) {
            this.bioOverflows = overflowing;
            this.cd.markForCheck();
        }
    }

    /** Re-check on resize: a bio that fits on desktop may clamp on mobile. */
    @HostListener('window:resize')
    public onWindowResize() {
        setTimeout(() => this.measureBio());
    }

    // ----- featured work -----
    /** Route parts for a title, shared by every action button. */
    public titleLink(t: any): any[] {
        return ['/titles', t.id, t.name || '-'];
    }

    public socialLinks() {
        const p = this.profile || {};
        const list = [];
        if (p.website_url)   list.push({label: 'Website',   url: p.website_url,   icon: 'language'});
        if (p.youtube_url)   list.push({label: 'YouTube',   url: p.youtube_url,   icon: 'play-circle-filled'});
        if (p.twitter_url)   list.push({label: 'Twitter',   url: p.twitter_url,   icon: 'alternate-email'});
        if (p.instagram_url) list.push({label: 'Instagram', url: p.instagram_url, icon: 'photo-camera'});
        if (p.facebook_url)  list.push({label: 'Facebook',  url: p.facebook_url,  icon: 'facebook'});
        return list;
    }
}
