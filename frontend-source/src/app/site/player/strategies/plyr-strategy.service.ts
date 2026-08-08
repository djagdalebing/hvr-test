import {Injectable} from '@angular/core';
import {Video} from '../../../models/video';
import {LazyLoaderService} from '@common/core/utils/lazy-loader.service';
import {Settings} from '@common/core/config/settings.service';
import {PlayerQualityVariantOptions} from './shaka-strategy.service';
import {Subject} from 'rxjs';
import {VideoPlaysLoggerService} from '../video-plays-logger.service';
import {CurrentUser} from '@common/auth/current-user';

declare const Plyr: any;

@Injectable({
    providedIn: 'root',
})
export class PlyrStrategyService {
    player: any;
    playbackEnded$ = new Subject();
    private video: Video;

    constructor(
        private lazyLoader: LazyLoaderService,
        private settings: Settings,
        private playLogger: VideoPlaysLoggerService,
        private currentUser: CurrentUser,
    ) {
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                this.logTimeWatched();
            }
        });
    }

    loadSource(
        videoEl: HTMLVideoElement,
        video: Video,
        variantOptions?: PlayerQualityVariantOptions
    ): Promise<void> {
        return this.initPlayer(videoEl, video, variantOptions).then(() => {
            if (!video) return;

            if (video.type !== 'stream') {
                this.player.source = this.buildSource(video);
            }

            if (video.latest_play?.time_watched) {
                // TODO: https://github.com/sampotts/plyr/issues/1978
                setTimeout(() => {
                    this.player.currentTime = video.latest_play.time_watched;
                }, 600);
            }
        });
    }

    loadAssets(): Promise<any> {
        return Promise.all([
            this.lazyLoader.loadAsset('js/plyr.min.js', {type: 'js'}),
            this.lazyLoader.loadAsset('css/plyr.css', {type: 'css'}),
        ]);
    }

    destroy() {
        this.logTimeWatched();
        this.player && this.player.destroy();
        this.player = null;
        this.video = null;
    }

    stop() {
        if (this.player) {
            this.logTimeWatched();
            this.player.stop();
        }
    }

    alreadyLoaded() {
        return !!this.player;
    }

    supported(video: Video) {
        return (
            video.type === 'video' ||
            // 'direct' is what creator file-uploads (R2 / local mp4) are saved
            // as — same as 'video', a self-hosted file Plyr plays in a <video>
            // tag. Without this they fell through and the browser downloaded
            // the raw file instead of playing it.
            video.type === 'direct' ||
            video.type === 'stream' ||
            // Any recognised embed-provider URL (YouTube / Vimeo) plays via
            // Plyr, REGARDLESS of the stored `type`. Some links were saved as
            // 'external' (which otherwise just window.open()s the URL in a new
            // tab, so nothing plays inline) instead of 'embed'. Keying off the
            // URL — not the type — makes those pasted links play.
            this.embedSupportedByPlyr(video.url)
        );
    }

    private initPlayer(
        videoEl: HTMLVideoElement,
        video: Video,
        variantOptions?: PlayerQualityVariantOptions
    ): Promise<void> {
        this.video = video;
        if (this.player) {
            return Promise.resolve();
        } else {
            return this.loadAssets().then(() => {
                const plyrOptions = {
                    autoplay: true,
                    quality: {},
                    // plyr doesn't allow "auto" quality for whatever reason,
                    // need to use zero for auto quality and translate it
                    i18n: {qualityLabel: {0: 'Auto'}},
                };
                if (variantOptions && variantOptions.variants.length) {
                    plyrOptions.quality = {
                        default: variantOptions.variants[0].quality,
                        forced: true,
                        onChange: variantOptions.onChange,
                        options: variantOptions.variants.map(qv => qv.quality),
                    };
                }

                // YouTube-style pre-roll video ad (Google IMA / VAST). Only for
                // self-hosted video (embeds bring their own ads), only when a
                // VAST tag is configured in admin, ads aren't globally disabled,
                // and the viewer isn't a paying subscriber.
                const adTag = this.settings.get('ads.video_tag_url');
                const adsAllowed =
                    !!adTag &&
                    this.video.type !== 'embed' &&
                    !this.settings.get('ads.disable') &&
                    !(this.currentUser && this.currentUser.isSubscribed && this.currentUser.isSubscribed());
                if (adsAllowed) {
                    (plyrOptions as any).ads = {enabled: true, tagUrl: adTag};
                }

                this.player = new Plyr(videoEl, plyrOptions);
                this.player.on('ended', () => {
                    this.playbackEnded$.next();
                });
            });
        }
    }

    private buildSource(video: Video) {
        // Treat any YouTube/Vimeo URL as an embed source (with a provider),
        // even if it was stored as 'external' rather than 'embed'.
        if (video.type === 'embed' || this.embedSupportedByPlyr(video.url)) {
            return {
                type: 'video',
                poster: video.thumbnail,
                sources: [
                    {
                        src: video.url,
                        provider: this.isYoutube(video.url)
                            ? 'youtube'
                            : 'vimeo',
                    },
                ],
            };
        } else {
            const tracks = (video.captions || []).map((caption, i) => {
                return {
                    kind: 'captions',
                    label: caption.name,
                    srclang: caption.language,
                    src: caption.url
                        ? caption.url
                        : this.settings.getBaseUrl() +
                          '/secure/caption/' +
                          caption.id,
                    default: i === 0,
                };
            });
            return {
                type: 'video',
                captions: {active: false},
                title: video.name,
                sources: [{src: video.url}],
                poster: video.thumbnail,
                tracks,
            };
        }
    }

    private isYoutube(url: string): boolean {
        // Match every YouTube URL shape, not just "youtube.com". In particular
        // youtu.be short links are what YouTube's Share button produces, and
        // missing them made the player fall back to an iframe of the raw URL —
        // which YouTube refuses to embed — so pasted links wouldn't play.
        return /(?:youtube\.com|youtu\.be|youtube-nocookie\.com)/i.test(url || '');
    }

    private isVimeo(url: string): boolean {
        return /vimeo\.com/i.test(url || '');
    }

    private embedSupportedByPlyr(url: string): boolean {
        return this.isYoutube(url) || this.isVimeo(url);
    }

    private logTimeWatched() {
        if (!this.player) return;
        // if user watched over 95%, we can assum video is full watched
        const fullyWatched =
            this.player.currentTime >= (95 / 100) * this.player.duration;
        this.playLogger.log(this.video, {
            timeWatched: fullyWatched ? 0 : this.player.currentTime,
        });
        if (!this.video.latest_play) {
            this.video.latest_play = {};
        }
        this.video.latest_play.time_watched = this.player.currentTime;
    }
}
