<?php

namespace App\Notifications;

use App\Title;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Str;

/**
 * Sent to admins when a creator deletes a title from their dashboard.
 */
class HvnContentDeleted extends Notification
{
    use Queueable;

    public $titleName;
    public $titleId;
    public $creator;

    public function __construct(Title $title, User $creator)
    {
        $this->titleName = (string) ($title->name ?? '');
        $this->titleId   = (int) $title->id;
        $this->creator   = $creator;
    }

    public function via($notifiable) { return ['database']; }

    public function toArray($notifiable)
    {
        $creatorName = $this->creator->username ?? 'a creator';
        return [
            'image'      => null,
            'mainAction' => ['action' => '/admin/moderation?status=approved'],
            'lines'      => [
                [
                    'content' => 'Creator title removed',
                    'icon'    => 'delete',
                    'type'    => 'primary',
                ],
                [
                    'content' => '"' . Str::limit($this->titleName, 80) . '" was deleted by ' . $creatorName,
                    'type'    => 'secondary',
                ],
            ],
        ];
    }
}
