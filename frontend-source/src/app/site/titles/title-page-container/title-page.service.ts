// @ts-nocheck
import {Injectable} from '@angular/core';
import {BehaviorSubject, ReplaySubject} from 'rxjs';
import {Title, TitleCredit} from '../../../models/title';
import {GetTitleQueryParams, GetTitleResponse, TitlesService} from '../titles.service';
import {Settings} from '@common/core/config/settings.service';
import {Video} from '../../../models/video';
import {Episode} from '../../../models/episode';
import {Season} from '../../../models/season';
import {createMap} from '@common/core/utils/create-map';
import {Modal} from '@common/core/ui/dialogs/modal.service';
import {CrupdateVideoModalComponent} from '../../videos/crupdate-video-modal/crupdate-video-modal.component';
import {PlayerOverlayService} from '../../player/player-overlay.service';
import {CurrentUser} from '@common/auth/current-user';
import {Router} from '@angular/router';

export enum TitlePageTab {
    cast, reviews, images
}

@Injectable({
    providedIn: 'root'
})
export class TitlePageService {
    public changed$ = new ReplaySubject<Title>(1);
    public selectedTab$ = new BehaviorSubject<TitlePageTab>(TitlePageTab.cast);
    public title: Title;
    public primaryVideo: Video;
    public activeEpisode: Episode;
    public activeSeason: Season;
    public currentEpisode: Episode;
    public nextEpisode: Episode;
    public videoCoverImage: string;
    public videos: Map<number, Video> = new Map();
    public shortCredits: {
        cast: TitleCredit[];
        directors: TitleCredit[];
        creators: TitleCredit[];
        writers: TitleCredit[]
    };

    constructor(
        private titlesApi: TitlesService,
        private settings: Settings,
        private modal: Modal,
        private playerOverlay: PlayerOverlayService,
        private currentUser: CurrentUser,
        private router: Router,
    ) {}

    public setTitleResponse(response: GetTitleResponse, params: GetTitleQueryParams) {
        this.title = response.title;

        this.activeSeason = response.title?.season;

        this.activeEpisode = (this.activeSeason?.episodes || []).find(ep => {
            return ep.episode_number === +params.episodeNumber;
        });

        this.currentEpisode = response.current_episode;
        this.nextEpisode = response.next_episode;

        this.setPrimaryVideo();
        this.setCoverImage();
        this.setShortCredits();
        this.setVideos();

        this.changed$.next(response.title);
    }

    public playVideo(video: Video) {
        // HVN: full-length videos are members-only. Send logged-out visitors to
        // login (not billing) and bring them back to this title afterwards.
        // Trailers and clips stay open to everyone.
        if (video.category === 'full' && !this.currentUser.isLoggedIn()) {
            this.currentUser.redirectUri = this.router.url;
            return this.router.navigate(['/login']);
        }
        if ( ! this.currentUser.hasPermission('videos.play')) {
            return this.router.navigate(['billing/upgrade']);
        }
        // Only send GENUINELY external links (arbitrary sites) to a new tab.
        // YouTube/Vimeo links are embeddable and play inline in the overlay even
        // when they were saved as type 'external' — window.open()ing those was
        // why pasted YouTube links opened a new tab instead of playing.
        if (video.type === 'external' && !this.isEmbeddable(video.url)) {
            window.open(video.url, '_blank');
        } else {
            this.playerOverlay.open(video, this.activeEpisode || this.title);
        }
    }

    public openCrupdateVideoModal(video?: Video) {
        this.modal.open(
            CrupdateVideoModalComponent,
            {
                video,
                title: this.title,
                episode_num: this.activeEpisode?.episode_number,
                season_num: this.activeEpisode?.season_number,
            },
        ).beforeClosed().subscribe((newVideo: Video) => {
            if (newVideo && newVideo.approved) {
                this.videos.set(newVideo.id, newVideo);
            }
        });
    }

    private setPrimaryVideo() {
        // A video counts as playable (and so can be the primary video, which is
        // what shows the Play button) unless it's a GENUINELY external link that
        // just opens in a new tab. YouTube/Vimeo links are embeddable and play
        // inline even when they were saved as type 'external' (older creator
        // uploads stored them that way) — so don't exclude those, otherwise the
        // title shows no Play button and the video appears unplayable.
        // A video whose URL the server withheld (full-length, viewer not signed
        // in) still counts as playable so the Play button stays on the page --
        // clicking it sends the visitor to login instead of dead-ending them on
        // a title with no play affordance at all.
        const playable = (video) =>
            video.requires_auth ||
            video.type !== 'external' ||
            this.isEmbeddable(video.url);
        if (this.settings.get('streaming.prefer_full')) {
            this.primaryVideo = this.title.videos.find(video => video.category === 'full' && playable(video));
        } else {
            this.primaryVideo = this.title.videos.find(video => video.category !== 'full' && playable(video));
        }
    }

    private isEmbeddable(url: string): boolean {
        return /(?:youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com)/i.test(url || '');
    }

    private setCoverImage() {
        let image = this.primaryVideo?.thumbnail ||
            this.activeEpisode?.poster ||
            this.title.images[this.title.images.length - 1] ||
            this.title.backdrop;

        if (typeof image !== 'string') {
            image = image?.url;
        }

        // Creator uploads copy the poster into backdrop when no separate hero
        // image was supplied. The cover box is a landscape slot, so a portrait
        // poster stretched into it rendered enormous — a second, taller copy of
        // the poster already shown to its left. Fall through to the empty state
        // instead of showing the same image twice.
        let poster: any = this.title.poster;
        if (typeof poster !== 'string') {
            poster = poster?.url;
        }
        if (image && poster && image === poster) {
            image = null;
        }

        this.videoCoverImage = image;
    }

    private setShortCredits() {
        const c = this.getTitleOrEpisodeCredits();
        this.shortCredits = {
            directors: c.filter(p => p.pivot.department === 'directing'),
            writers: c.filter(p => p.pivot.department === 'writing'),
            cast: c.filter(p => p.pivot.department === 'cast').slice(0, 3),
            creators: c.filter(p => p.pivot.department === 'creators'),
        };
    }

    public getTitleOrEpisodeCredits(): TitleCredit[] {
        return this.activeEpisode ?
            this.title.season.credits.concat(this.activeEpisode.credits) :
            this.title.credits;
    }

    private setVideos() {
        const selectedCategory = this.settings.get('streaming.video_panel_content');
        let videos: Video[];

        // Anyone who can manage videos sees EVERY video (trailers AND the full
        // movie/episode) in the panel, so they can edit/delete any of them from
        // the title page. Viewers still only see the configured category.
        if (this.currentUser.hasPermission('videos.update') ||
            this.currentUser.hasPermission('videos.create')) {
            videos = this.title.videos;
        } else if (selectedCategory === 'full') {
            videos = this.title.videos.filter(v => v.category === 'full');
        } else if (selectedCategory === 'short') {
            videos = this.title.videos.filter(v => v.category !== 'full');
        } else if (selectedCategory === 'trailer') {
            videos = this.title.videos.filter(v => v.category === 'trailer');
        } else if (selectedCategory === 'clip') {
            videos = this.title.videos.filter(v => v.category === 'clip');
        } else {
            videos = this.title.videos;
        }

        videos = videos.map(video => {
            video.thumbnail = video.thumbnail ||
                this.activeEpisode?.poster ||
                this.title.backdrop ||
                this.title.poster;
            return video;
        });

        this.videos = createMap(videos);
    }
}
