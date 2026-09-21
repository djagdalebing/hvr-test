import {Injectable} from '@angular/core';
import {PlayerComponent} from './player.component';
import {OverlayPanel} from '@common/core/ui/overlay-panel/overlay-panel.service';
import {Video} from '../../models/video';
import {OverlayPanelRef} from '@common/core/ui/overlay-panel/overlay-panel-ref';
import {Title} from '../../models/title';
import {Episode} from '../../models/episode';
import {CurrentUser} from '@common/auth/current-user';
import {Router} from '@angular/router';

@Injectable({
    providedIn: 'root'
})
export class PlayerOverlayService {
    private overlayRef: OverlayPanelRef<PlayerComponent>;

    constructor(
        private overlay: OverlayPanel,
        private currentUser: CurrentUser,
        private router: Router,
    ) {}

    public open(video: Video, mediaItem?: Title|Episode) {
        // HVN: last line of defence in the client -- any other entry point into
        // the player (deep links, autoplay params, related videos) lands here.
        // The server withholds the URL for guests anyway; this keeps them from
        // reaching an empty player.
        if (video?.category === 'full' && !this.currentUser.isLoggedIn()) {
            this.currentUser.redirectUri = this.router.url;
            return this.router.navigate(['/login']);
        }
        if ( ! this.currentUser.hasPermission('videos.play')) {
            return this.router.navigate(['billing/upgrade']);
        }
        this.overlayRef = this.overlay.open(PlayerComponent, {
            origin: 'global',
            position: 'center',
            panelClass: 'player-overlay-container',
            hasBackdrop: false,
            fullScreen: true,
            data: {video, mediaItem}
        });
    }
}
