<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin promotes a creator to Trusted status. Their
 * uploads bypass the moderation queue from then on.
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
                    'content' => 'Your future uploads go live immediately — no more waiting for review.',
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
            ->line('Good news — an administrator has marked your account as a Trusted Creator.')
            ->line('From now on your uploads will be published immediately without waiting for review.')
            ->salutation('— Her Vision Network');
    }
}
