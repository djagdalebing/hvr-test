import {Injectable} from '@angular/core';
import {VideoService} from '../videos/video.service';
import {Video} from '../../models/video';

@Injectable({
    providedIn: 'root',
})
export class VideoPlaysLoggerService {
    constructor(private videos: VideoService) {}

    log(video: Video, params?: {timeWatched: number}) {
        this.videos.logPlay(video, params).subscribe(
            () => {},
            () => {}
        );
    }

    /**
     * Accumulated seconds actually played, for the Creator Earnings Program.
     * Fire-and-forget: a dropped heartbeat must never interrupt playback, and
     * the next one carries the running total anyway.
     */
    watchProgress(video: Video, seconds: number) {
        this.videos.watchProgress(video, seconds).subscribe(
            () => {},
            () => {}
        );
    }
}
