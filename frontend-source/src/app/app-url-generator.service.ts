import {Injectable} from '@angular/core';
import {UrlGeneratorService} from '@common/core/services/url-generator.service';
import {Title} from './models/title';
import {slugifyString} from '@common/core/utils/slugify-string';
import {Episode} from './models/episode';
import {User} from '@common/core/types/models/User';
import {Person} from './models/person';
import {NewsArticle} from './models/news-article';

@Injectable({
    providedIn: 'root',
})
export class AppUrlGeneratorService extends UrlGeneratorService {
    title(title: Title, extra?: {seasonNum: number}): string {
        let url = `/titles/${title.id}/${slugifyString(title.name)}`;
        if (extra?.seasonNum) {
            url += `/season/${extra.seasonNum}`;
        }
        return url;
    }

    episode(e: Episode, title?: Title): string {
        const base = title ? this.title(title) : `/titles/${e.title_id}`;
        return `${base}/season/${e.season_number}/episode/${e.episode_number}`;
    }

    person(p: Person): string {
        return `/people/${p.id}/${slugifyString(p.name)}`;
    }

    newsArticle(n: NewsArticle): string {
        return `/news/${n.id}`;
    }

    // HVN: Vebto's /users/{id} page was removed; return null so any caller
    // that uses it ends up rendering plain text rather than a dead link.
    user(_user: User): string | null {
        return null;
    }
}
