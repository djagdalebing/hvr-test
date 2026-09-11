<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'bio',
        'profile_photo',
        'website_url',
        'contact_email',
        'social_links',
        // These were missing, so fill() silently dropped them: saving a
        // profile reported success but the social links were never written,
        // and the settings form reloaded empty.
        'youtube_url',
        'twitter_url',
        'instagram_url',
        'facebook_url',
    ];

    protected $casts = [
        'social_links' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
