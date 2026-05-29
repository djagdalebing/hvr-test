<?php

namespace App\Notifications;

use App\CommunityPost;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Str;

/**
 * Sent to admins when a community post owner deletes their own post.
 */
class HvnPostDeleted extends Notification
{
    use Queueable;

    public $postTitle;
    public $author;

    public function __construct(CommunityPost $post, User $author)
    {
        $this->postTitle = (string) ($post->title ?? '');
        $this->author    = $author;
    }

    public function via($notifiable) { return ['database']; }

    public function toArray($notifiable)
    {
        $author = $this->author->username ?? 'an author';
        return [
            'image'      => null,
            'mainAction' => ['action' => '/admin/community'],
            'lines'      => [
                [
                    'content' => 'Community post removed',
                    'icon'    => 'delete',
                    'type'    => 'primary',
                ],
                [
                    'content' => '"' . Str::limit($this->postTitle, 80) . '" was deleted by ' . $author,
                    'type'    => 'secondary',
                ],
            ],
        ];
    }
}
