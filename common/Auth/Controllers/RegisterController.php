<?php namespace Common\Auth\Controllers;

use Carbon\Carbon;
use Common\Auth\UserRepository;
use Common\Core\BaseController;
use Common\Core\Bootstrap\BootstrapData;
use Common\Core\Bootstrap\MobileBootstrapData;
use Common\Settings\Settings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;

class RegisterController extends BaseController
{
    use RegistersUsers;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var UserRepository
     */
    private $repository;

    /**
     * @param Settings $settings
     * @param UserRepository $repository
     */
    public function __construct(Settings $settings, UserRepository $repository)
    {
        $this->settings = $settings;
        $this->repository = $repository;

        $this->middleware('guest');

        // abort if registration should be disabled
        if ($this->settings->get('disable.registration')) abort(404);
    }

    public function register(Request $request)
    {
        // HVN: harder validation than the Vebto default — usernames are
        // exposed on /creators/{username} so they must be unique and url-safe,
        // names get a length cap, and passwords are 8+ with a letter+digit
        // to prevent throwaway 'password' / '12345' signups.
        $this->validate($request, [
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'   => [
                'required', 'string', 'min:8', 'confirmed',
                'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/',
            ],
            'username'   => [
                'required', 'string', 'min:3', 'max:30',
                'alpha_dash', 'unique:users,username',
            ],
            'first_name' => ['nullable', 'string', 'max:50'],
            'last_name'  => ['nullable', 'string', 'max:50'],
            'token_name' => 'string|min:3|max:50',
            'role'       => 'nullable|string|in:viewer,creator',
        ], [
            'password.regex' => 'Password must contain at least one letter and one number.',
            'username.alpha_dash' => 'Username may only contain letters, numbers, dashes and underscores.',
            'username.unique' => 'That username is already taken.',
        ]);

        $params = $request->all();

        // SECURITY: never let a self-registering user assign privileges or
        // admin-only attributes. UserRepository::create() applies any
        // 'roles'/'permissions' it receives with no authorization check, so
        // these must be stripped from public-registration input (otherwise a
        // registrant could POST permissions/roles and grant themselves admin).
        unset(
            $params['roles'],
            $params['permissions'],
            $params['available_space'],
            $params['email_verified_at'],
            $params['avatar'],
            $params['trusted_creator'],
            $params['blocked']
        );

        if ( ! $this->settings->get('require_email_confirmation')) {
            $params['email_verified_at'] = Carbon::now();
        }

        event(new Registered($user = $this->repository->create($params)));

        // HVN: mirror the users.role value into the user_role pivot so the
        // admin Roles tab lists viewer/creator members. New signups default
        // to viewer. Best-effort.
        if (method_exists($user, 'syncAudienceRole')) {
            $user->syncAudienceRole();
        }

        // HVN: warm welcome email. Only attempt when mail is actually set up
        // (otherwise the synchronous send would add latency / fail on the
        // dead SMTP host). Best-effort — never block or fail registration.
        if (filter_var(env('MAIL_SETUP', false), FILTER_VALIDATE_BOOLEAN) && !empty($user->email)) {
            try {
                \Mail::to($user->email)->send(new \App\Mail\WelcomeEmail($user));
            } catch (\Throwable $e) {
                \Log::warning('welcome email failed: ' . $e->getMessage());
            }
        }

        if ($user->hasVerifiedEmail()) {
            $this->guard()->login($user);
        }

        $response = ['status' => $user->hasVerifiedEmail() ? 'success' : 'needs_email_verification'];

        if ($user->hasVerifiedEmail()) {
            // for mobile
            if ($request->has('token_name')) {
                $bootstrapData = app(MobileBootstrapData::class)->init();
                $bootstrapData->refreshToken($request->get('token_name'));
                $response['boostrapData'] = $bootstrapData->get();

            // for web
            } else {
                $bootstrapData = app(BootstrapData::class)->init();
                $response['bootstrapData'] = $bootstrapData->getEncoded();
            }
        } else {
            $response['message'] = trans('We have sent you an email with instructions on how to activate your account.');
        }

        return $this->success($response);
    }
}
