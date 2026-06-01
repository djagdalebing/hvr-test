import {ChangeDetectionStrategy, Component, Input} from '@angular/core';
import {User} from '@common/core/types/models/User';
import {UrlGeneratorService} from '@common/core/services/url-generator.service';

@Component({
    selector: 'user-column',
    templateUrl: './user-column.component.html',
    styleUrls: ['./user-column.component.scss'],
    changeDetection: ChangeDetectionStrategy.OnPush,
    host: {class: 'column-with-image'},
})
export class UserColumnComponent {
    @Input() user: User;
    @Input() showEmail = false;
    haveUrl: boolean;

    constructor(public url: UrlGeneratorService) {
        // HVN: AppUrlGeneratorService.user() now returns null since the
        // /users/{id} page was removed. The previous check (function
        // exists?) is no longer meaningful — test the generated URL.
        // Falsy → render plain text via the *ngIf="!haveUrl" branch.
        this.haveUrl = false;
    }
}
