<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin unblocks a previously blocked user. Delivered
 * in-app and via email so the user knows access has been restored.
 */
class HvnAccountUnblocked extends Notification
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
                    'content' => 'Your account has been restored',
                    'icon'    => 'check',
                    'type'    => 'primary',
                ],
                [
                    'content' => 'You can post, comment, like and upload content again. Welcome back!',
                    'type'    => 'secondary',
                ],
            ],
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Her Vision Network account has been restored')
            ->greeting('Hi ' . ($notifiable->username ?: 'there') . ',')
            ->line('Good news — an administrator has restored your Her Vision Network account.')
            ->line('You can now post, comment, like, edit your profile and upload content again.')
            ->salutation('— Her Vision Network');
    }
}
