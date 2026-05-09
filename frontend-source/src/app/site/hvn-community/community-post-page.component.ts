// @ts-nocheck
import {ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {ActivatedRoute} from '@angular/router';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {CurrentUser} from '@common/auth/current-user';
import {Toast} from '@common/core/ui/toast.service';

@Component({
    selector: 'community-post-page',
    templateUrl: './community-post-page.component.html',
    styleUrls: ['./community-post-page.component.scss'],
    encapsulation: ViewEncapsulation.None,
    changeDetection: ChangeDetectionStrategy.Default,
})
export class CommunityPostPageComponent implements OnInit {
    public loading = true;
    public notFound = false;
    public post: any = null;
    public comment = '';
    public submitting = false;

    constructor(
        private route: ActivatedRoute,
        public currentUser: CurrentUser,
        private http: AppHttpClient,
        private toast: Toast,
        private cd: ChangeDetectorRef,
    ) {}

    ngOnInit() {
        this.route.paramMap.subscribe(p => this.load(+p.get('id')));
    }

    private load(id: number) {
        if (!id) return;
        this.loading = true; this.notFound = false;
        this.http.get('community/' + id).subscribe(
            (res: any) => { this.post = res.post; this.loading = false; this.cd.markForCheck(); },
            () => { this.loading = false; this.notFound = true; this.cd.markForCheck(); },
        );
    }

    public submitComment() {
        if (!this.currentUser.isLoggedIn()) { this.toast.open('Please log in to comment.'); return; }
        const body = (this.comment || '').trim();
        if (!body) return;
        this.submitting = true;
        this.http.post('community/' + this.post.id + '/comments', {body}).subscribe(
            () => { this.submitting = false; this.comment = ''; this.toast.open('Comment posted.'); this.load(this.post.id); },
            () => { this.submitting = false; this.toast.open('Failed to post comment.'); },
        );
    }

    public initial(name: string): string {
        return ((name || '?') + '').charAt(0).toUpperCase();
    }
}
