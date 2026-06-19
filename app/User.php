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

    /**
     * Keep the user_role pivot in step with the users.role column so the
     * admin Roles tab lists viewer/creator members. Attaches the role that
     * matches the current users.role value and detaches the opposite one,
     * leaving any other roles (e.g. the default 'Users') untouched.
     * Best-effort — never throws into the caller.
     */
    public function syncAudienceRole(): void
    {
        try {
            $target = $this->role === 'creator' ? 'creator' : 'viewer';
            $other  = $target === 'creator' ? 'viewer' : 'creator';
            $roles  = \Common\Auth\Roles\Role::whereIn('name', [$target, $other])
                ->get()->keyBy('name');
            if (isset($roles[$target])) {
                $this->roles()->syncWithoutDetaching([$roles[$target]->id]);
            }
            if (isset($roles[$other])) {
                $this->roles()->detach($roles[$other]->id);
            }
        } catch (\Throwable $e) {
            \Log::warning('syncAudienceRole failed: ' . $e->getMessage());
        }
    }

    /**
     * Send a notification to every admin (best effort — failures are
     * logged but never thrown). Caps at 20 recipients.
     *
     * Vebto grants the 'admin' permission three different ways depending
     * on the install: directly on the user (permissionables polymorphic
     * to App\User), via a role the user belongs to (permissionables ->
     * roles), or by a literal 'admin' value in the users.role column.
     * SQL-filter on the union of all three, then PHP-filter the result
     * through hasPermission('admin') as a safety check.
     */
    public static function notifyAdmins($notification): void
    {
        try {
            $candidates = self::where(function ($q) {
                    $q->whereHas('permissions', function ($p) { $p->where('name', 'admin'); })
                      ->orWhereHas('roles.permissions', function ($p) { $p->where('name', 'admin'); })
                      ->orWhere('role', 'admin');
                })
                ->limit(50)
                ->get();

            $admins = $candidates->filter(function ($u) {
                return method_exists($u, 'hasPermission') && $u->hasPermission('admin');
            })->take(20);

            foreach ($admins as $admin) {
                try {
                    $admin->notify($notification);
                } catch (\Throwable $inner) {
                    \Log::warning('notifyAdmins inner failed: ' . $inner->getMessage());
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('notifyAdmins outer failed: ' . $e->getMessage());
        }
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
