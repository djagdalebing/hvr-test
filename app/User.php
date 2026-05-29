<?php

namespace App;

use Common\Auth\BaseUser;
use Common\Comments\Comment;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $role  'viewer' | 'creator'
 * @property-read Collection|ListModel[] $watchlist
 */
class User extends BaseUser
{
    use HasApiTokens;

    protected $casts = [
        'id'                => 'integer',
        'available_space'   => 'integer',
        'email_verified_at' => 'datetime',
        'role'              => 'string',
        'blocked'           => 'boolean',
        'trusted_creator'   => 'boolean',
    ];

    public function isBlocked(): bool
    {
        return (bool) ($this->blocked ?? false);
    }

    public function watchlist(): HasOne
    {
        return $this->hasOne(ListModel::class)
            ->where('system', 1)
            ->where('name', 'watchlist');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function lists(): HasMany
    {
        return $this->hasMany(ListModel::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function creatorProfile(): HasOne
    {
        return $this->hasOne(CreatorProfile::class);
    }

    public function communityPosts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function isCreator(): bool
    {
        return $this->role === 'creator';
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }
}
