<?php

namespace App\Notifications;

use App\Title;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Str;

/**
 * Sent to admins when a creator submits a new title that needs review.
 */
class HvnContentSubmitted extends Notification
{
    use Queueable;

    public $title;
    public $creator;

    public function __construct(Title $title, User $creator)
    {
        $this->title   = $title;
        $this->creator = $creator;
    }

    public function via($notifiable)
    {
        $channels = ['database'];
        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toArray($notifiable)
    {
        $creatorName = $this->creator->username ?? 'a creator';
        return [
            'image'      => null,
            'mainAction' => ['action' => '/admin/moderation'],
            'lines'      => [
                [
                    'content' => 'New content awaiting review',
                    'icon'    => 'star',
                    'type'    => 'primary',
                ],
                [
                    'content' => '"' . Str::limit((string) ($this->title->name ?? ''), 80) . '" by ' . $creatorName,
                    'type'    => 'secondary',
                ],
                [
                    'content' => 'Open the moderation queue to approve or reject.',
                    'type'    => 'secondary',
                ],
            ],
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New creator upload awaiting review on Her Vision Network')
            ->greeting('Hi ' . ($notifiable->username ?: 'Admin') . ',')
            ->line(($this->creator->username ?? 'A creator') . ' submitted a new title for review:')
            ->line('"' . ($this->title->name ?? '') . '"')
            ->action('Open Moderation Queue', url('/admin/moderation'))
            ->salutation('— Her Vision Network');
    }
}
