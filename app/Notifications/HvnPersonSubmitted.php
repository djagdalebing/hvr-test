<?php

namespace App\Notifications;

use App\Person;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Str;

/**
 * Sent to admins when a creator creates a new person that needs review
 * before it appears on the public /people pages.
 */
class HvnPersonSubmitted extends Notification
{
    use Queueable;

    public $person;
    public $creator;

    public function __construct(Person $person, User $creator)
    {
        $this->person  = $person;
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
            'mainAction' => ['action' => '/admin/people'],
            'lines'      => [
                [
                    'content' => 'New person awaiting review',
                    'icon'    => 'person',
                    'type'    => 'primary',
                ],
                [
                    'content' => '"' . Str::limit((string) ($this->person->name ?? ''), 80) . '" added by ' . $creatorName,
                    'type'    => 'secondary',
                ],
                [
                    'content' => 'Approve or reject from the People admin.',
                    'type'    => 'secondary',
                ],
            ],
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New person awaiting review on Her Vision Network')
            ->greeting('Hi ' . ($notifiable->username ?: 'Admin') . ',')
            ->line(($this->creator->username ?? 'A creator') . ' added a new person for review:')
            ->line('"' . ($this->person->name ?? '') . '"')
            ->action('Review People', url('/admin/people'))
            ->salutation('— Her Vision Network');
    }
}
