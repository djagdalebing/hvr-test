<?php

namespace App\Mail;

use App\User;
use Common\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Warm, branded welcome email sent to a user when they first register.
 * Rendered from resources/views/emails/welcome.blade.php.
 */
class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $siteName;
    public $logoUrl;
    public $heroUrl;
    public $browseUrl;
    public $creatorsUrl;
    public $communityUrl;
    public $settingsUrl;
    public $displayName;

    public function __construct(User $user)
    {
        $this->user = $user;

        $settings = app(Settings::class);
        $this->siteName = $settings->get('branding.site_name') ?: 'Her Vision Network';

        $this->logoUrl     = $this->absolute($settings->get('branding.logo_light'));
        $this->heroUrl     = url('client/assets/images/landing.jpg');
        $this->browseUrl   = url('/');
        $this->creatorsUrl = url('/creators');
        $this->communityUrl = url('/community');
        $this->settingsUrl = url('/account/settings');

        $name = $user->first_name ?: ($user->username ?: 'there');
        $this->displayName = $name;
    }

    public function build()
    {
        return $this
            ->subject('Welcome to ' . $this->siteName . ' 🎬✨')
            ->view('emails.welcome');
    }

    private function absolute($value): ?string
    {
        if (!$value) return null;
        if (Str::startsWith($value, ['http://', 'https://'])) return $value;
        return url(ltrim($value, '/'));
    }
}
