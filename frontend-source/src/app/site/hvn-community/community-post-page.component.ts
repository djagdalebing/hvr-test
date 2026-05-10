// @ts-nocheck
import {ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {ActivatedRoute, Router} from '@angular/router';
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

    // editing state
    public editingPost = false;
    public editTitle = '';
    public editBody = '';
    public editingCommentId: number | null = null;
    public editCommentBody = '';

    constructor(
        private route: ActivatedRoute,
        private router: Router,
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

    // -------- post edit / delete --------

    public ownsPost(): boolean {
        const u = this.currentUser.get('id');
        return !!(u && this.post && this.post.user_id && +u === +this.post.user_id);
    }

    public startEditPost() {
        this.editingPost = true;
        this.editTitle = this.post.title || '';
        this.editBody = this.post.body || '';
    }
    public cancelEditPost() { this.editingPost = false; }
    public saveEditPost() {
        const title = (this.editTitle || '').trim();
        const body  = (this.editBody  || '').trim();
        if (!title || !body) { this.toast.open('Title and body are required.'); return; }
        this.http.put('community/' + this.post.id, {title, body}).subscribe(
            () => { this.editingPost = false; this.toast.open('Post updated.'); this.load(this.post.id); },
            () => this.toast.open('Failed to save changes.'),
        );
    }
    public deletePost() {
        if (!confirm('Delete this post and all its comments?')) return;
        this.http.delete('community/' + this.post.id).subscribe(
            () => { this.toast.open('Post deleted.'); this.router.navigateByUrl('/community'); },
            () => this.toast.open('Failed to delete post.'),
        );
    }

    // -------- comment edit / delete --------

    public ownsComment(c: any): boolean {
        const u = this.currentUser.get('id');
        return !!(u && c && c.user_id && +u === +c.user_id);
    }

    public startEditComment(c: any) {
        this.editingCommentId = c.id;
        this.editCommentBody = c.body || '';
    }
    public cancelEditComment() {
        this.editingCommentId = null;
        this.editCommentBody = '';
    }
    public saveEditComment(c: any) {
        const body = (this.editCommentBody || '').trim();
        if (!body) return;
        this.http.put('community/comments/' + c.id, {body}).subscribe(
            () => { this.editingCommentId = null; this.toast.open('Comment updated.'); this.load(this.post.id); },
            () => this.toast.open('Failed to save comment.'),
        );
    }
    public deleteComment(c: any) {
        if (!confirm('Delete this comment?')) return;
        this.http.delete('community/comments/' + c.id).subscribe(
            () => { this.toast.open('Comment deleted.'); this.load(this.post.id); },
            () => this.toast.open('Failed to delete comment.'),
        );
    }
}
