// @ts-nocheck
import {ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {FormControl} from '@angular/forms';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {CurrentUser} from '@common/auth/current-user';
import {Toast} from '@common/core/ui/toast.service';
import {debounceTime, distinctUntilChanged} from 'rxjs/operators';

@Component({
    selector: 'community-page',
    templateUrl: './community-page.component.html',
    styleUrls: ['./community-page.component.scss'],
    encapsulation: ViewEncapsulation.None,
    changeDetection: ChangeDetectionStrategy.Default,
})
export class CommunityPageComponent implements OnInit {
    public loading = false;
    public posts: any[] = [];
    public pagination: any = null;
    public searchControl = new FormControl('');

    public composing = false;
    public draftTitle = '';
    public draftBody = '';
    public submitting = false;

    constructor(
        public currentUser: CurrentUser,
        private http: AppHttpClient,
        private toast: Toast,
        private cd: ChangeDetectorRef,
    ) {}

    ngOnInit() {
        this.load(1);
        this.searchControl.valueChanges
            .pipe(debounceTime(300), distinctUntilChanged())
            .subscribe(() => this.load(1));
    }

    public load(page = 1) {
        this.loading = true;
        const params: any = {page, perPage: 15};
        const q = (this.searchControl.value || '').trim();
        if (q) params.query = q;
        this.http.get('community', params).subscribe(
            (res: any) => {
                this.posts = res.pagination.data || [];
                this.pagination = res.pagination;
                this.loading = false;
                this.cd.markForCheck();
            },
            () => { this.loading = false; this.cd.markForCheck(); },
        );
    }

    public openCompose() {
        if (!this.currentUser.isLoggedIn()) {
            this.toast.open('Please log in to start a discussion.');
            return;
        }
        this.composing = true;
        this.draftTitle = '';
        this.draftBody = '';
    }

    public cancelCompose() { this.composing = false; }

    public submitPost() {
        if (this.submitting) return;
        const title = (this.draftTitle || '').trim();
        const body  = (this.draftBody  || '').trim();
        if (!title || !body) { this.toast.open('Title and body are required.'); return; }
        this.submitting = true;
        this.http.post('community/posts', {title, body}).subscribe(
            () => { this.submitting = false; this.composing = false; this.toast.open('Posted.'); this.load(1); },
            () => { this.submitting = false; this.toast.open('Failed to post.'); },
        );
    }

    public isAdmin(): boolean {
        return !!this.currentUser.hasPermission('admin');
    }

    public togglePin(p: any, ev?: Event) {
        if (ev) { ev.preventDefault(); ev.stopPropagation(); }
        this.http.post('admin/community/' + p.id + '/pin', {}).subscribe(
            (res: any) => {
                p.pinned = !!res?.pinned;
                this.toast.open(p.pinned ? 'Pinned to top.' : 'Unpinned.');
                this.load(this.pagination?.current_page || 1);
            },
            () => this.toast.open('Failed to update pin'),
        );
    }

    public toggleLike(p: any, ev?: Event) {
        if (ev) { ev.preventDefault(); ev.stopPropagation(); }
        if (!this.currentUser.isLoggedIn()) { this.toast.open('Please log in to like posts.'); return; }
        this.http.post('community/' + p.id + '/like', {}).subscribe(
            (res: any) => {
                p.liked_by_me = !!res?.liked;
                p.likes_count = +res?.likes_count || 0;
            },
            () => this.toast.open('Failed to update like'),
        );
    }

    public timeAgo(ts: string): string {
        if (!ts) return '';
        const diff = (Date.now() - new Date(ts).getTime()) / 1000;
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff/60) + 'm ago';
        if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff/86400) + 'd ago';
        return Math.floor(diff/604800) + 'w ago';
    }
}
