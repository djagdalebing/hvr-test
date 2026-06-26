<?php

namespace App\Mail;

use App\User;
use Common\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Branded congratulations email sent when a viewer upgrades to a creator.
 * Rendered from resources/views/emails/creator-welcome.blade.php.
 */
class CreatorWelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $siteName;
    public $logoUrl;
    public $heroUrl;
    public $dashboardUrl;
    public $uploadUrl;
    public $settingsUrl;
    public $displayName;

    public function __construct(User $user)
    {
        $this->user = $user;

        $settings = app(Settings::class);
        $this->siteName = $settings->get('branding.site_name') ?: 'Her Vision Network';

        $this->logoUrl      = $this->absolute($settings->get('branding.logo_light'));
        $this->heroUrl      = url('client/assets/images/landing.jpg');
        $this->dashboardUrl = url('/creator/dashboard');
        $this->uploadUrl    = url('/creator/dashboard');
        $this->settingsUrl  = url('/account/settings');

        $this->displayName = $user->first_name ?: ($user->username ?: 'there');
    }

    public function build()
    {
        return $this
            ->subject("You're now a Creator on " . $this->siteName . ' 🎬🎉')
            ->view('emails.creator-welcome');
    }

    private function absolute($value): ?string
    {
        if (!$value) return null;
        if (Str::startsWith($value, ['http://', 'https://'])) return $value;
        return url(ltrim($value, '/'));
    }
}
