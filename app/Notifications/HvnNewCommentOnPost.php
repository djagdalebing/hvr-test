<?php

namespace App\Notifications;

use App\CommunityComment;
use App\CommunityPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Str;

/**
 * Sent to the owner of a community post whenever someone else
 * leaves a new comment on it.
 */
class HvnNewCommentOnPost extends Notification
{
    use Queueable;

    public $comment;
    public $post;

    public function __construct(CommunityComment $comment, CommunityPost $post)
    {
        $this->comment = $comment->load('user:id,username');
        $this->post    = $post;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $commenter = optional($this->comment->user)->username ?: 'someone';
        $title     = $this->post->title ?: 'your post';

        return [
            'image'      => null,
            'mainAction' => ['action' => '/community/' . $this->post->id],
            'lines'      => [
                [
                    'content' => $commenter . ' commented on your post:',
                    'type'    => 'secondary',
                ],
                [
                    'content' => '"' . Str::limit((string) $this->comment->body, 180) . '"',
                    'icon'    => 'comment',
                    'type'    => 'primary',
                ],
                [
                    'content' => 'on "' . Str::limit($title, 80) . '"',
                    'type'    => 'secondary',
                ],
            ],
        ];
    }
}
