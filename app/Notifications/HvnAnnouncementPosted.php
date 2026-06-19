<?php

namespace App\Notifications;

use App\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Str;

/**
 * Delivers an admin announcement.
 *  - database channel → in-app notification bell (always reliable)
 *  - mail channel     → email (Phase 2; best-effort, respects unsubscribe)
 *
 * The caller decides which channels to use by passing $channels, so in-app
 * and email can be dispatched separately and a broken mail transport can't
 * take the in-app delivery down with it.
 */
class HvnAnnouncementPosted extends Notification
{
    use Queueable;

    public $announcement;
    public $channels;

    public function __construct(Announcement $announcement, array $channels = ['database'])
    {
        $this->announcement = $announcement;
        $this->channels = $channels;
    }

    public function via($notifiable)
    {
        return $this->channels;
    }

    public function toArray($notifiable)
    {
        $a = $this->announcement;
        return [
            'image'      => null,
            'mainAction' => ['action' => $a->link_url ?: '/'],
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

    public function toMail($notifiable): MailMessage
    {
        $a = $this->announcement;
        $mail = (new MailMessage)
            ->subject($a->title)
            ->greeting('Hi ' . ($notifiable->username ?: 'there') . ',');

        if ($a->body) {
            $mail->line(strip_tags((string) $a->body));
        }
        if ($a->link_url) {
            $mail->action('View', $this->absoluteLink($a->link_url));
        }

        // One-click unsubscribe (signed URL — no login needed).
        $unsub = URL::signedRoute('announcements.unsubscribe', ['user' => $notifiable->id]);
        return $mail
            ->line('—')
            ->line('You are receiving this because you have an account on Her Vision Network.')
            ->salutation('— Her Vision Network')
            ->line('[Unsubscribe from announcement emails](' . $unsub . ')');
    }

    private function absoluteLink(string $url): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }
        return url($url);
    }

    private function iconFor(string $type): string
    {
        switch ($type) {
            case 'new_content':      return 'movie';
            case 'platform_update':  return 'system-update';
            case 'featured_creator': return 'star';
            case 'company_news':     return 'notifications';
            default:                 return 'notifications';
        }
    }
}
