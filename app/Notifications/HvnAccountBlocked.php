<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin blocks a user. Delivered both in-app and via
 * email so the user understands why writes are failing.
 */
class HvnAccountBlocked extends Notification
{
    use Queueable;

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
        return [
            'image'      => null,
            'mainAction' => ['action' => '/'],
            'lines'      => [
                [
                    'content' => 'Your account has been blocked',
                    'icon'    => 'block',
                    'type'    => 'primary',
                ],
                [
                    'content' => 'You can still browse the site, but cannot post, comment or upload until an admin restores access.',
                    'type'    => 'secondary',
                ],
            ],
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Her Vision Network account has been blocked')
            ->greeting('Hi ' . ($notifiable->username ?: 'there') . ',')
            ->line('Your account on Her Vision Network has been blocked by an administrator.')
            ->line('You can still browse the site, but you cannot post, comment, like, edit your profile or upload content while blocked.')
            ->line('If you believe this is a mistake, please reply to this email to appeal.')
            ->salutation('— Her Vision Network');
    }
}
