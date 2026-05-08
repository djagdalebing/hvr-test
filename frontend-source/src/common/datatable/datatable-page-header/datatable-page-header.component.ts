import {ChangeDetectionStrategy, Component} from '@angular/core';

@Component({
    selector: 'datatable-page-header',
    template: '<ng-content></ng-content>',
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DatatablePageHeaderComponent {}
