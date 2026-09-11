// @ts-nocheck
import {ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {Toast} from '@common/core/ui/toast.service';

/**
 * Admin screen for beta feedback submitted via the site-wide "Send Feedback"
 * button. Shows what the tester said plus which page they were on.
 */
@Component({
    selector: 'feedback-admin',
    templateUrl: './feedback-admin.component.html',
    styleUrls: ['./feedback-admin.component.scss'],
    encapsulation: ViewEncapsulation.None,
})
export class FeedbackAdminComponent implements OnInit {
    public rows: any[] = [];
    public loading = false;
    public filter = 'all';

    public typeLabels = {
        bug: 'Broken',
        confusing: 'Confusing',
        suggestion: 'Suggestion',
        other: 'Other',
    };

    constructor(
        private http: AppHttpClient,
        private toast: Toast,
        private cd: ChangeDetectorRef,
    ) {}

    ngOnInit() {
        this.load();
    }

    public load() {
        this.loading = true;
        this.cd.markForCheck();
        this.http.get('admin/feedback').subscribe(
            (res: any) => {
                this.rows = res.feedback || [];
                this.loading = false;
                this.cd.markForCheck();
            },
            () => {
                this.loading = false;
                this.toast.open('Could not load feedback.');
                this.cd.markForCheck();
            },
        );
    }

    public visibleRows(): any[] {
        if (this.filter === 'all') {
            return this.rows;
        }
        return this.rows.filter(r => r.type === this.filter);
    }

    public countOf(type: string): number {
        return type === 'all'
            ? this.rows.length
            : this.rows.filter(r => r.type === type).length;
    }

    public setStatus(row: any, status: string) {
        const previous = row.status;
        row.status = status;
        this.cd.markForCheck();
        this.http.post('admin/feedback/' + row.id, {status}).subscribe(
            () => this.toast.open(status === 'done' ? 'Marked as done.' : 'Reopened.'),
            () => {
                row.status = previous;
                this.toast.open('Could not update.');
                this.cd.markForCheck();
            },
        );
    }

    public remove(row: any) {
        if (!confirm('Delete this feedback entry?')) {
            return;
        }
        this.http.delete('admin/feedback/' + row.id).subscribe(
            () => {
                this.rows = this.rows.filter(r => r.id !== row.id);
                this.toast.open('Deleted.');
                this.cd.markForCheck();
            },
            () => this.toast.open('Could not delete.'),
        );
    }
}
