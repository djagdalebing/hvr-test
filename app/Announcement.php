<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    const TYPES = ['new_content', 'platform_update', 'featured_creator', 'company_news', 'general'];
    const AUDIENCES = ['all', 'viewers', 'creators'];

    protected $fillable = [
        'title', 'body', 'type', 'link_url', 'image_path',
        'audience', 'channels', 'status', 'recipients_count',
        'created_by', 'sent_at',
    ];

    protected $casts = [
        'id'               => 'integer',
        'channels'         => 'array',
        'recipients_count' => 'integer',
        'created_by'       => 'integer',
        'sent_at'          => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
