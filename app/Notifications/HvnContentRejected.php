<?php

namespace App\Notifications;

use App\Title;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Str;

class HvnContentRejected extends Notification
{
    use Queueable;

    public $title;
    public $reason;

    public function __construct(Title $title, ?string $reason = null)
    {
        $this->title  = $title;
        $this->reason = $reason ? trim($reason) : null;
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
        $lines = [
            [
                'content' => 'Your title was rejected by an admin',
                'icon'    => 'cancel',
                'type'    => 'primary',
            ],
            [
                'content' => '"' . Str::limit($name, 80) . '"',
                'type'    => 'secondary',
            ],
        ];
        if ($this->reason) {
            $lines[] = [
                'content' => 'Reason: ' . Str::limit($this->reason, 200),
                'type'    => 'secondary',
            ];
        }
        $lines[] = [
            'content' => 'You can edit the title in your dashboard and re-upload.',
            'type'    => 'secondary',
        ];
        return [
            'image'      => null,
            'mainAction' => ['action' => '/creator/dashboard'],
            'lines'      => $lines,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $name = $this->title->name ?? '';
        $mail = (new MailMessage)
            ->subject('Your title was not approved on Her Vision Network')
            ->greeting('Hi ' . ($notifiable->username ?: 'there') . ',')
            ->line('Your title "' . $name . '" was reviewed and not approved at this time.');
        if ($this->reason) {
            $mail->line('Admin note: ' . $this->reason);
        }
        return $mail->line('You can edit and re-submit it from your Creator Dashboard.')
                    ->salutation('— Her Vision Network');
    }
}
