<?php

namespace App\Notifications;

use App\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Str;

/**
 * Delivers an admin announcement to a user's in-app notification bell.
 * Phase 1 is database-only; the mail channel is added in Phase 2 once an
 * email transport is configured.
 */
class HvnAnnouncementPosted extends Notification
{
    use Queueable;

    public $announcement;

    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $a = $this->announcement;
        return [
            'image'      => null,
            'mainAction' => $a->link_url
                ? ['action' => $a->link_url]
                : ['action' => '/'],
            'lines'      => [
                [
                    'content' => $a->title,
                    'icon'    => $this->iconFor($a->type),
                    'type'    => 'primary',
                ],
                [
                    'content' => Str::limit(strip_tags((string) $a->body), 160),
                    'type'    => 'secondary',
                ],
            ],
        ];
    }

    private function iconFor(string $type): string
    {
        switch ($type) {
            case 'new_content':      return 'movie';
            case 'platform_update':  return 'system-update';
            case 'featured_creator': return 'star';
            case 'company_news':     return 'campaign';
            default:                 return 'notifications';
        }
    }
}
