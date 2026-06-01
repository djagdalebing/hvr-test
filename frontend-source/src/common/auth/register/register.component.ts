import {ChangeDetectionStrategy, Component, OnInit} from '@angular/core';
import {AuthService} from '../auth.service';
import {SocialAuthService} from '../social-auth.service';
import {CurrentUser} from '../current-user';
import {ActivatedRoute, Router} from '@angular/router';
import {Settings} from '../../core/config/settings.service';
import {Toast} from '../../core/ui/toast.service';
import {Bootstrapper} from '../../core/bootstrapper.service';
import {RecaptchaService} from '../../core/services/recaptcha.service';
import {FormBuilder, FormControl} from '@angular/forms';
import {BehaviorSubject} from 'rxjs';
import {MenuItem} from '@common/core/ui/custom-menu/menu-item';
import {slugifyString} from '@common/core/utils/slugify-string';
import {BackendErrorResponse} from '@common/core/types/backend-error-response';
import {filter} from 'rxjs/operators';

@Component({
    selector: 'register',
    templateUrl: './register.component.html',
    styleUrls: ['./register.component.scss'],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class RegisterComponent implements OnInit {
    public loading$ = new BehaviorSubject<boolean>(false);
    public registerPolicies: Partial<MenuItem>[] = [];
    public form = this.fb.group({
        username: [''],
        first_name: [''],
        last_name: [''],
        email: [''],
        password: [''],
        password_confirmation: [''],
        role: ['viewer'],
        purchase_code: [''],
    });
    public errors$ = new BehaviorSubject<{
        email?: string,
        password?: string,
        password_confirmation?: string,
        username?: string,
        first_name?: string,
        last_name?: string,
        general?: string,
        purchase_code?: string
    }>({});

    constructor(
        public auth: AuthService,
        public socialAuth: SocialAuthService,
        public settings: Settings,
        public route: ActivatedRoute,
        private user: CurrentUser,
        private router: Router,
        private toast: Toast,
        private bootstrapper: Bootstrapper,
        private recaptcha: RecaptchaService,
        private fb: FormBuilder,
    ) {}

    ngOnInit() {
        this.registerPolicies = this.settings.getJson('register_policies', []);
        this.registerPolicies.forEach(policy => {
            policy.id = slugifyString(policy.label, '_');
            this.form.addControl(policy.id, new FormControl(false));
        });
        if (this.recaptcha.enabledFor('registration')) {
            this.recaptcha.load();
        }
        this.auth.forcedEmail$
            .pipe(filter(email => !!email))
            .subscribe(email => {
                this.form.get('email').setValue(email);
                this.form.get('email').disable();
            });

        // HVN: /creator-signup sets defaultRole=creator via route data;
        // ?role=creator on /register also pre-selects creator.
        const dataRole = this.route.snapshot.data?.defaultRole;
        const queryRole = this.route.snapshot.queryParamMap.get('role');
        const role = (dataRole === 'creator' || queryRole === 'creator') ? 'creator' : 'viewer';
        this.form.patchValue({role});
    }

    /**
     * Client-side guard before hitting the server. Returns true if OK,
     * false (and surfaces an error) if not.
     */
    private validateClient(): boolean {
        const errs: any = {};
        const v = this.form.value;
        const password = (v.password || '') as string;
        const confirm  = (v.password_confirmation || '') as string;
        const username = (v.username || '').trim() as string;

        if (!username || username.length < 3 || username.length > 30) {
            errs.username = 'Username must be 3–30 characters.';
        } else if (!/^[A-Za-z0-9_-]+$/.test(username)) {
            errs.username = 'Use letters, digits, underscore or dash only.';
        }

        if (password.length < 8) {
            errs.password = 'Password must be at least 8 characters.';
        } else if (!/[A-Za-z]/.test(password) || !/\d/.test(password)) {
            errs.password = 'Password must include at least one letter and one number.';
        }

        if (password !== confirm) {
            errs.password_confirmation = 'Passwords do not match.';
        }

        if (Object.keys(errs).length) {
            this.errors$.next(errs);
            return false;
        }
        return true;
    }

    public async register() {
        if (!this.validateClient()) return;
        this.loading$.next(true);
        if (this.recaptcha.enabledFor('registration') && ! await this.recaptcha.verify('registration')) {
            this.loading$.next(false);
            return this.toast.open('Could not verify you are human.');
        }

        // Normalise: username trimmed, names trimmed.
        const raw = this.form.getRawValue();
        const payload: any = {
            ...raw,
            username: (raw.username || '').trim(),
            first_name: (raw.first_name || '').trim() || null,
            last_name: (raw.last_name || '').trim() || null,
        };
        this.auth.register(payload)
            .subscribe(response => {
                if (response.status === 'needs_email_verification') {
                    this.router.navigate(['/login']).then(() => {
                        this.loading$.next(false);
                        this.toast.open(response.message, {duration: 12000});
                    });
                } else {
                    this.bootstrapper.bootstrap(response.bootstrapData);
                    this.router.navigate([this.auth.getRedirectUri()]).then(() => {
                        this.loading$.next(false);
                        this.toast.open('Registered successfully.');
                    });
                }
            }, (errResponse: BackendErrorResponse) => {
                this.errors$.next(errResponse.errors);
                this.loading$.next(false);
            });
    }
}
