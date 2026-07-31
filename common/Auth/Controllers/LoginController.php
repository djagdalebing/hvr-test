<?php namespace Common\Auth\Controllers;

use App\User;
use Auth;
use Common\Core\BaseController;
use Common\Core\Bootstrap\BootstrapData;
use Common\Settings\Settings;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends BaseController
{
    use AuthenticatesUsers;

    /**
     * @var BootstrapData
     */
    private $bootstrapData;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @param BootstrapData $bootstrapData
     * @param Settings $settings
     */
    public function __construct(BootstrapData $bootstrapData, Settings $settings)
    {
        $this->middleware('guest', ['except' => 'logout']);

        $this->bootstrapData = $bootstrapData;
        $this->settings = $settings;
    }

    protected function validateLogin(Request $request)
    {
        $this->validate($request, [
            $this->username() => 'required|string|email_verified',
            'password' => 'required|string',
        ]);
    }

    protected function authenticated(Request $request, User $user)
    {
        if ($this->settings->get('single_device_login')) {
            Auth::logoutOtherDevices($request->get('password'));
        }

        $extra = [];

        // Mobile app (Capacitor): when a device/token name is supplied, issue a
        // Sanctum bearer token so the native app can authenticate its API calls
        // without the web session cookie. Web login omits token_name → unchanged.
        if ($request->filled('token_name')) {
            try {
                $extra['access_token'] = $user->refreshApiToken($request->get('token_name'));
            } catch (\Throwable $e) {
                // best-effort — never block a successful login on token issuance
            }
        }

        $data = $this->bootstrapData->init()->getEncoded();

        return $this->success(array_merge(['data' => $data], $extra));
    }
}
