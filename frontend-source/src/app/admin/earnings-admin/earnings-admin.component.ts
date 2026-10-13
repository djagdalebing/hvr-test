// @ts-nocheck
import {ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {Toast} from '@common/core/ui/toast.service';
import {CurrentUser} from '@common/auth/current-user';

/**
 * Admin side of the Creator Earnings Program.
 *
 * Self-contained like the moderation screen, for the same reason: Vebto's
 * DatatableService carries global CSS that fights a custom multi-surface
 * layout.
 */
@Component({
    selector: 'earnings-admin',
    templateUrl: './earnings-admin.component.html',
    styleUrls: ['./earnings-admin.component.scss'],
    encapsulation: ViewEncapsulation.None,
})
export class EarningsAdminComponent implements OnInit {
    public tab: 'creators' | 'titles' | 'payouts' | 'activity' = 'creators';
    public loading = true;
    public saving = false;

    public settings: any = {};
    public totals: any = {};
    public creators: any[] = [];
    public titles: any[] = [];
    public payouts: any[] = [];
    public plays: any[] = [];
    public minPayout = 0;

    public payingId: number | null = null;
    public payForm: any = {method: '', reference: '', notes: ''};

    constructor(
        public currentUser: CurrentUser,
        private http: AppHttpClient,
        private toast: Toast,
        private cd: ChangeDetectorRef,
    ) {}

    ngOnInit() {
        this.loadOverview();
        this.switchTab('creators');
    }

    private loadOverview() {
        this.http.get('admin/earnings').subscribe(
            (res: any) => {
                this.settings = res.settings || {};
                this.totals = res.totals || {};
                this.loading = false;
                this.cd.markForCheck();
            },
            () => { this.loading = false; this.cd.markForCheck(); },
        );
    }

    public switchTab(t: 'creators' | 'titles' | 'payouts' | 'activity') {
        this.tab = t;
        this.payingId = null;
        const uri = t === 'creators' ? 'admin/earnings/creators'
            : t === 'titles' ? 'admin/earnings/titles'
            : t === 'activity' ? 'admin/earnings/recent-plays'
            : 'admin/earnings/payouts';

        this.http.get(uri).subscribe(
            (res: any) => {
                if (t === 'creators') {
                    this.creators = res.creators || [];
                    this.minPayout = res.min_payout || 0;
                } else if (t === 'titles') {
                    this.titles = res.titles || [];
                } else if (t === 'activity') {
                    this.plays = res.plays || [];
                } else {
                    this.payouts = res.payouts || [];
                }
                this.cd.markForCheck();
            },
            () => this.toast.open('Could not load earnings data'),
        );
    }

    public saveSettings() {
        if (this.saving) return;
        this.saving = true;
        this.http.post('admin/earnings/settings', this.settings).subscribe(
            () => {
                this.saving = false;
                this.toast.open('Settings saved. New rate applies to future views only.');
                this.loadOverview();
            },
            (err: any) => {
                this.saving = false;
                this.toast.open(this.firstError(err) || 'Could not save settings');
                this.cd.markForCheck();
            },
        );
    }

    public money(n: any, dp = 2): string {
        return '$' + Number(n || 0).toFixed(dp);
    }

    public belowThreshold(c: any): boolean {
        return Number(c.unpaid || 0) < Number(this.minPayout || 0);
    }

    public openPay(c: any) {
        this.payingId = c.creator_id;
        this.payForm = {method: '', reference: '', notes: ''};
        this.cd.markForCheck();
    }

    public cancelPay() {
        this.payingId = null;
        this.cd.markForCheck();
    }

    public confirmPay(c: any) {
        this.http.post('admin/earnings/payouts', {
            creator_id: c.creator_id,
            method: this.payForm.method || null,
            reference: this.payForm.reference || null,
            notes: this.payForm.notes || null,
        }).subscribe(
            (res: any) => {
                this.payingId = null;
                this.toast.open('Payout of ' + this.money(res.amount) + ' recorded as pending.');
                this.switchTab('creators');
                this.loadOverview();
            },
            (err: any) => this.toast.open(this.firstError(err) || 'Could not record payout'),
        );
    }

    public setStatus(p: any, status: string) {
        const verb = status === 'paid' ? 'Mark this payout as paid?'
            : 'Set this payout to ' + status + '? Its earnings return to the creator\'s unpaid balance.';
        if (!confirm(verb)) return;

        this.http.post('admin/earnings/payouts/' + p.id, {
            status,
            method: p.method || null,
            reference: p.reference || null,
            notes: p.notes || null,
        }).subscribe(
            () => { this.toast.open('Payout updated.'); this.switchTab('payouts'); this.loadOverview(); },
            (err: any) => this.toast.open(this.firstError(err) || 'Could not update payout'),
        );
    }

    private firstError(err: any): string | null {
        const e = err?.error?.errors;
        if (e) {
            const k = Object.keys(e)[0];
            if (k) return Array.isArray(e[k]) ? e[k][0] : e[k];
        }
        return err?.error?.message || null;
    }
}
