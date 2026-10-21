import {ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {CurrentUser} from '@common/auth/current-user';

/**
 * Creator guide: how HVN works, end to end.
 *
 * The earnings figures are fetched rather than written into the page. An
 * admin can change the rate or the thresholds at any time, and a hard-coded
 * guide would immediately start telling creators something untrue about how
 * they get paid.
 *
 * Open to signed-out visitors -- this is the page that convinces someone to
 * become a creator, so it cannot sit behind the thing it is explaining.
 */
@Component({
    selector: 'creator-guide',
    templateUrl: './creator-guide.component.html',
    styleUrls: ['./creator-guide.component.scss'],
    encapsulation: ViewEncapsulation.None,
})
export class CreatorGuideComponent implements OnInit {
    public facts: any = null;
    public openSection: string = null;

    constructor(
        private http: AppHttpClient,
        private cd: ChangeDetectorRef,
        public currentUser: CurrentUser,
    ) {}

    ngOnInit() {
        this.http.get('creator-guide').subscribe(
            (res: any) => { this.facts = res; this.cd.markForCheck(); },
            () => { this.cd.markForCheck(); },
        );
    }

    public get earnings(): any {
        return this.facts?.earnings || {};
    }

    /** The rate needs more precision than currency formatting gives. */
    public rateLabel(): string {
        const r = Number(this.earnings.rate || 0);
        return '$' + r.toFixed(r < 0.01 ? 4 : 2);
    }

    public money(n: any): string {
        return '$' + Number(n || 0).toFixed(2);
    }

    /** Worked examples, so the rate means something concrete. */
    public examples(): {views: number; amount: string}[] {
        const rate = Number(this.earnings.rate || 0);
        return [1000, 10000, 50000, 100000, 1000000].map(v => ({
            views: v,
            amount: '$' + (v * rate).toFixed(2),
        }));
    }

    public toggle(id: string) {
        this.openSection = this.openSection === id ? null : id;
        this.cd.markForCheck();
    }

    public isOpen(id: string): boolean {
        return this.openSection === id;
    }
}
