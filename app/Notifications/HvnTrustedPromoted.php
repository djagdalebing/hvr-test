<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin marks a creator as Trusted. This is a recognition badge —
 * all uploads (trusted creators included) are still reviewed before going live.
 */
class HvnTrustedPromoted extends Notification
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
            'mainAction' => ['action' => '/creator/dashboard'],
            'lines'      => [
                [
                    'content' => 'You are now a Trusted Creator',
                    'icon'    => 'check',
                    'type'    => 'primary',
                ],
                [
                    'content' => 'A mark of recognition for your work. Uploads are still reviewed before they go live.',
                    'type'    => 'secondary',
                ],
            ],
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You are now a Trusted Creator on Her Vision Network')
            ->greeting('Hi ' . ($notifiable->username ?: 'there') . ',')
            ->line('Good news — an administrator has recognised your account as a Trusted Creator.')
            ->line('It\'s a mark of recognition for your contributions. Your uploads are still reviewed before they go live, so quality stays consistent across the network.')
            ->salutation('— Her Vision Network');
    }
}
