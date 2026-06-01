// @ts-nocheck
import {ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit, ViewEncapsulation} from '@angular/core';
import {FormControl} from '@angular/forms';
import {AppHttpClient} from '@common/core/http/app-http-client.service';
import {debounceTime, distinctUntilChanged} from 'rxjs/operators';

@Component({
    selector: 'creators-page',
    templateUrl: './creators-page.component.html',
    styleUrls: ['./creators-page.component.scss'],
    encapsulation: ViewEncapsulation.None,
    changeDetection: ChangeDetectionStrategy.Default,
})
export class CreatorsPageComponent implements OnInit {
    public loading = false;
    public creators: any[] = [];
    public pagination: any = null;
    public searchControl = new FormControl('');

    constructor(private http: AppHttpClient, private cd: ChangeDetectorRef) {}

    ngOnInit() {
        this.load(1);
        this.searchControl.valueChanges
            .pipe(debounceTime(300), distinctUntilChanged())
            .subscribe(() => this.load(1));
    }

    public load(page = 1) {
        this.loading = true;
        const params: any = {page, perPage: 24};
        const q = (this.searchControl.value || '').trim();
        if (q) params.query = q;
        this.http.get('creators', params).subscribe(
            (res: any) => {
                this.creators = res.pagination.data || [];
                this.pagination = res.pagination;
                this.loading = false;
                this.cd.markForCheck();
            },
            () => { this.loading = false; this.cd.markForCheck(); },
        );
    }

    public displayName(c: any): string {
        return (c.creator_profile && c.creator_profile.display_name) || c.username || '?';
    }

    public photoUrl(c: any): string | null {
        // Prefer the standard user.avatar (set via Account Settings); fall
        // back to the legacy creator_profile.profile_photo column.
        const a = c?.avatar;
        if (a) return /^https?:\/\/|^\//.test(a) ? a : '/storage/' + a;
        const p = c?.creator_profile?.profile_photo;
        return p ? '/storage/' + p : null;
    }

    public bio(c: any): string {
        return (c.creator_profile && c.creator_profile.bio) || '';
    }

    public initial(c: any): string {
        return (this.displayName(c) || '?').charAt(0).toUpperCase();
    }
}
