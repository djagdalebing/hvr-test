<?php

namespace Common\Auth\Controllers;

use Common\Core\BaseController;
use Common\Core\Bootstrap\MobileBootstrapData;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class GetAccessTokenController extends BaseController
{
    use AuthenticatesUsers;

    protected function validateLogin(Request $request)
    {
        $this->validate($request, [
            $this->username() => 'required|string|email_verified',
            'password' => 'required|string',
            'token_name' => 'required|string|min:3|max:100',
        ]);
    }

    protected function sendLoginResponse(Request $request)
    {
        // HVN: same block as the web login. This controller overrides
        // sendLoginResponse outright, so AuthenticatesUsers never calls
        // authenticated() here -- the check has to live in this method or the
        // mobile app would happily hand a blocked account a bearer token.
        $user = $this->guard()->user();
        if ($user && method_exists($user, 'isBlocked') && $user->isBlocked()) {
            $this->guard()->logout();
            return $this->error(
                'Your account has been blocked. Contact support if you think this is a mistake.',
                [],
                403,
            );
        }

        $bootstrapData = app(MobileBootstrapData::class)
            ->init()
            ->refreshToken($request->get('token_name'))
            ->get();
        return $this->success($bootstrapData);
    }
}
