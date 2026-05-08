// @ts-nocheck
import {ChangeDetectionStrategy, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {Toast} from '@common/core/ui/toast.service';
import {BehaviorSubject} from 'rxjs';

@Component({
    selector: 'community-admin',
    templateUrl: './community-admin.component.html',
    styleUrls: ['./community-admin.component.scss'],
    encapsulation: ViewEncapsulation.None,
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CommunityAdminComponent implements OnInit {
    public loading$ = new BehaviorSubject<boolean>(false);
    public posts$ = new BehaviorSubject<any[]>([]);
    public pagination$ = new BehaviorSubject<any>(null);
    public query = '';
    public editing: any = null;
    public form: any = {};

    constructor(private http: AppHttpClient, private toast: Toast) {}

    ngOnInit() { this.load(); }

    public load(page = 1) {
        this.loading$.next(true);
        this.http.get('admin/community', {page, perPage: 20, query: this.query}).subscribe(
            (res: any) => {
                this.posts$.next(res.pagination.data || []);
                this.pagination$.next(res.pagination);
                this.loading$.next(false);
            },
            () => { this.toast.open('Failed to load posts'); this.loading$.next(false); },
        );
    }

    public search() { this.load(1); }

    public openEdit(post: any) {
        this.editing = post;
        this.form = {
            title: post.title || '',
            body: post.body || '',
            status: post.status || 'published',
        };
    }

    public closeEdit() { this.editing = null; }

    public saveEdit() {
        if (!this.editing) return;
        this.http.post('admin/community/' + this.editing.id, this.form).subscribe(
            () => {
                this.toast.open('Post updated.');
                this.closeEdit();
                this.load(this.pagination$.value?.current_page || 1);
            },
            () => this.toast.open('Failed to save'),
        );
    }

    public toggleHide(post: any) {
        this.http.post('admin/community/' + post.id + '/hide', {}).subscribe(
            () => { this.load(this.pagination$.value?.current_page || 1); this.toast.open('Status updated.'); },
            () => this.toast.open('Failed'),
        );
    }

    public deletePost(post: any) {
        if (!confirm('Permanently delete this post and all its comments?')) return;
        this.http.delete('admin/community/' + post.id).subscribe(
            () => { this.load(this.pagination$.value?.current_page || 1); this.toast.open('Deleted.'); },
            () => this.toast.open('Failed to delete'),
        );
    }
}
