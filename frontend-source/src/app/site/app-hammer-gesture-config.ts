import {Injectable} from '@angular/core';
import {HammerGestureConfig} from '@angular/platform-browser';

declare const Hammer: any;

@Injectable()
export class AppHammerGestureConfig extends HammerGestureConfig {
    public buildHammer(element: HTMLElement) {
        return new Hammer(element, {
            touchAction: 'pan-y',
        });
    }
}
