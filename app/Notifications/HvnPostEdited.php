<?php

namespace App\Notifications;

use App\CommunityPost;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Str;

/**
 * Sent to admins when a community post owner edits an existing post.
 * Surfaces content changes that may need re-review.
 */
class HvnPostEdited extends Notification
{
    use Queueable;

    public $post;
    public $author;

    public function __construct(CommunityPost $post, User $author)
    {
        $this->post   = $post;
        $this->author = $author;
    }

    public function via($notifiable) { return ['database']; }

    public function toArray($notifiable)
    {
        $author = $this->author->username ?? 'an author';
        return [
            'image'      => null,
            'mainAction' => ['action' => '/community/' . $this->post->id],
            'lines'      => [
                [
                    'content' => 'Community post edited',
                    'icon'    => 'edit',
                    'type'    => 'primary',
                ],
                [
                    'content' => '"' . Str::limit((string) $this->post->title, 80) . '" by ' . $author,
                    'type'    => 'secondary',
                ],
            ],
        ];
    }
}
