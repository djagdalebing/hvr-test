import {
    ChangeDetectionStrategy,
    ChangeDetectorRef,
    Component, OnDestroy,
    OnInit
} from '@angular/core';
import {PlayerOverlayService} from '../../../player/player-overlay.service';
import {TitlePageService} from '../../../titles/title-page-container/title-page.service';
import {Subscription} from 'rxjs';

@Component({
    selector: 'title-video-carousel',
    templateUrl: './title-video-carousel.component.html',
    styleUrls: ['./title-video-carousel.component.scss'],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class TitleVideoCarouselComponent implements OnInit, OnDestroy {
    private changeSub: Subscription;
    constructor(
        public titlePage: TitlePageService,
        private playerOverlay: PlayerOverlayService,
        private cd: ChangeDetectorRef,
    ) {}

    ngOnInit() {
        this.changeSub = this.titlePage.changed$.subscribe(() => {
            this.cd.markForCheck();
        });
    }

    ngOnDestroy() {
        this.changeSub.unsubscribe();
    }

    /**
     * True only for GENUINELY external links (arbitrary sites) that open in a new
     * tab. YouTube/Vimeo links saved as type 'external' actually play inline, so
     * they should show a play icon, not the "open in new tab" icon.
     */
    isExternalLink(video): boolean {
        return video && video.type === 'external' &&
            !/(?:youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com)/i.test(video.url || '');
    }
}
