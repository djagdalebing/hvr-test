<?php

namespace App\Notifications;

use App\Title;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Str;

class HvnContentApproved extends Notification
{
    use Queueable;

    public $title;

    public function __construct(Title $title)
    {
        $this->title = $title;
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
        $name = (string) ($this->title->name ?? '');
        return [
            'image'      => null,
            'mainAction' => ['action' => '/titles/' . $this->title->id . '/' . urlencode($name ?: '-')],
            'lines'      => [
                [
                    'content' => 'Your title was approved',
                    'icon'    => 'check',
                    'type'    => 'primary',
                ],
                [
                    'content' => '"' . Str::limit($name, 80) . '" is now visible on Her Vision Network.',
                    'type'    => 'secondary',
                ],
            ],
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $name = $this->title->name ?? '';
        return (new MailMessage)
            ->subject('Your title was approved on Her Vision Network')
            ->greeting('Hi ' . ($notifiable->username ?: 'there') . ',')
            ->line('Good news — your title "' . $name . '" has been approved.')
            ->line('It is now visible to viewers on Her Vision Network.')
            ->salutation('— Her Vision Network');
    }
}
