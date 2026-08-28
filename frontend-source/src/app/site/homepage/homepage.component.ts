import {
    ChangeDetectionStrategy,
    Component,
    OnInit,
    ViewEncapsulation,
} from '@angular/core';
import {BehaviorSubject} from 'rxjs';
import {List} from '../../models/list';
import {Settings} from '@common/core/config/settings.service';
import {CurrentUser} from '@common/auth/current-user';
import {ActivatedRoute} from '@angular/router';
import {MediaViewMode} from '../shared/media-view/media-view-mode';

@Component({
    selector: 'homepage',
    templateUrl: './homepage.component.html',
    styleUrls: ['./homepage.component.scss'],
    changeDetection: ChangeDetectionStrategy.OnPush,
    encapsulation: ViewEncapsulation.None,
})
export class HomepageComponent implements OnInit {
    modes = MediaViewMode;
    lists$ = new BehaviorSubject<List[]>([]);
    sliderList: List;

    constructor(
        public settings: Settings,
        public currentUser: CurrentUser,
        private route: ActivatedRoute
    ) {}

    ngOnInit() {
        this.route.data.subscribe(data => {
            this.sliderList = data.api.lists.shift();
            this.lists$.next(data.api.lists);
        });
    }

    /**
     * Scroll a Netflix-style row left (dir -1) or right (dir 1) by ~90% of its
     * visible width. Finds the scroll container relative to the clicked arrow so
     * we don't need a ViewChild per row.
     */
    scrollRow(event: Event, dir: number) {
        const row = (event.currentTarget as HTMLElement).closest('.nf-row');
        const scroller = row?.querySelector('.auto-height-grid') as HTMLElement;
        if (scroller) {
            scroller.scrollBy({
                left: dir * scroller.clientWidth * 0.9,
                behavior: 'smooth',
            });
        }
    }
}
