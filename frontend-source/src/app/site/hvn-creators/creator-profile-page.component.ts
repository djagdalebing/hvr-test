// @ts-nocheck
import {ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
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
                this.loading = false;
                this.cd.markForCheck();
            },
            () => { this.loading = false; this.notFound = true; this.cd.markForCheck(); },
        );
    }

    public photoUrl(): string | null {
        const p = this.profile && this.profile.profile_photo;
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
