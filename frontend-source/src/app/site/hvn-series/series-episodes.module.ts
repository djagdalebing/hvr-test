import {NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {RouterModule} from '@angular/router';
import {SeriesEpisodesComponent} from './series-episodes.component';

@NgModule({
    declarations: [SeriesEpisodesComponent],
    imports: [CommonModule, RouterModule],
    exports: [SeriesEpisodesComponent],
})
export class SeriesEpisodesModule {}
