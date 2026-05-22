<?php

namespace App\Notifications;

use App\CommunityPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Str;

/**
 * Sent to the owner of a community post when an admin pins it
 * to the top of the public list — positive engagement signal.
 */
class HvnPostPinned extends Notification
{
    use Queueable;

    public $post;

    public function __construct(CommunityPost $post)
    {
        $this->post = $post;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'image'      => null,
            'mainAction' => ['action' => '/community/' . $this->post->id],
            'lines'      => [
                [
                    'content' => 'Your post was pinned by an admin',
                    'icon'    => 'star',
                    'type'    => 'primary',
                ],
                [
                    'content' => '"' . Str::limit((string) ($this->post->title ?: ''), 100) . '"',
                    'type'    => 'secondary',
                ],
                [
                    'content' => 'It will appear at the top of the Community for every visitor.',
                    'type'    => 'secondary',
                ],
            ],
        ];
    }
}
