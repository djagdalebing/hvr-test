import {NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FormsModule} from '@angular/forms';
import {MatIconModule} from '@angular/material/icon';
import {FeedbackButtonComponent} from './feedback-button.component';

/**
 * Self-contained so it can be mounted once at the app root and appear on
 * every page without touching feature modules.
 */
@NgModule({
    declarations: [FeedbackButtonComponent],
    imports: [CommonModule, FormsModule, MatIconModule],
    exports: [FeedbackButtonComponent],
})
export class FeedbackModule {}
