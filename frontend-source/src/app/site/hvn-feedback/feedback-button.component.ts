import {ChangeDetectionStrategy, ChangeDetectorRef, Component} from '@angular/core';
import {Router} from '@angular/router';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {Toast} from '@common/core/ui/toast.service';

/**
 * Site-wide "Send Feedback / Report a Problem" button for beta testers.
 *
 * Automatically records which page the tester was on (route, title and full
 * URL) so reports are actionable without them having to describe it.
 */
@Component({
    selector: 'hvn-feedback-button',
    templateUrl: './feedback-button.component.html',
    styleUrls: ['./feedback-button.component.scss'],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FeedbackButtonComponent {
    public open = false;
    public sending = false;

    public types = [
        {value: 'bug', label: 'Something is broken'},
        {value: 'confusing', label: 'Something is confusing'},
        {value: 'suggestion', label: 'I have a suggestion'},
        {value: 'other', label: 'Other'},
    ];

    public form: {type: string; message: string; contact_email: string} = {
        type: 'bug',
        message: '',
        contact_email: '',
    };

    constructor(
        private http: AppHttpClient,
        private toast: Toast,
        private router: Router,
        private cd: ChangeDetectorRef,
    ) {}

    public toggle() {
        this.open = !this.open;
        if (!this.open) {
            this.sending = false;
        }
        this.cd.markForCheck();
    }

    /** The page the tester is on, shown in the panel so they know it's captured. */
    public currentPage(): string {
        return this.router.url || '/';
    }

    public submit() {
        if (this.sending) {
            return;
        }
        const message = (this.form.message || '').trim();
        if (message.length < 5) {
            this.toast.open('Please describe the problem (at least a few words).');
            return;
        }

        this.sending = true;
        this.cd.markForCheck();

        this.http.post('feedback', {
            type: this.form.type,
            message,
            page_route: this.currentPage(),
            page_title: typeof document !== 'undefined' ? document.title : null,
            page_url: typeof window !== 'undefined' ? window.location.href : null,
            contact_email: (this.form.contact_email || '').trim() || null,
        }).subscribe(
            () => {
                this.sending = false;
                this.open = false;
                this.form = {type: 'bug', message: '', contact_email: ''};
                this.toast.open('Thanks — your feedback was sent.');
                this.cd.markForCheck();
            },
            () => {
                this.sending = false;
                this.toast.open('Could not send feedback. Please try again.');
                this.cd.markForCheck();
            },
        );
    }
}
