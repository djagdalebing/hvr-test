<?php

namespace App\Http\Controllers\Web;

use App\CommunityPost;
use App\CreatorProfile;
use App\Title;
use Common\Core\BaseController as Controller;
use App\CommunityComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HvnController extends Controller
{
    // HVN: creatorSignup() removed — /creator-signup is now served by the
    // SPA's RegisterComponent (catch-all → app.blade.php).

    public function community(Request $request)
    {
        $posts = CommunityPost::with(['user:id,username'])
            ->published()
            ->withCount(['comments', 'likes'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('hvn.community', compact('posts'));
    }

    public function creators(Request $request)
    {
        $creators = \App\User::where('role', 'creator')
            ->whereNotNull('username')
            ->with('creatorProfile')
            ->orderBy('username')
            ->paginate(20);

        return view('hvn.creators', compact('creators'));
    }

    public function communityStore(Request $request): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if ($user->isBlocked()) {
            return response()->json(['message' => 'Your account is blocked.'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'body'  => 'required|string|max:10000',
        ]);

        $post = CommunityPost::create([
            'user_id' => $user->id,
            'title'   => $request->input('title'),
            'body'    => $request->input('body'),
            'status'  => 'published',
        ]);

        return response()->json(['post' => $post], 201);
    }

    public function communityShow(Request $request, int $postId, string $slug = null)
    {
        $post = CommunityPost::with(['user:id,username', 'comments.user:id,username'])
            ->published()
            ->withCount(['comments', 'likes'])
            ->findOrFail($postId);

        return view('hvn.community-post', compact('post'));
    }

    public function commentStore(Request $request, int $postId): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if ($user->isBlocked()) {
            return response()->json(['message' => 'Your account is blocked.'], 403);
        }

        $request->validate(['body' => 'required|string|max:5000']);

        $post = CommunityPost::published()->findOrFail($postId);

        $comment = CommunityComment::create([
            'post_id'    => $post->id,
            'user_id'    => $user->id,
            'body'       => $request->input('body'),
            'created_at' => now(),
        ]);

        // Notify the post owner — unless they're the one commenting.
        if ((int) $post->user_id !== (int) $user->id) {
            $owner = \App\User::find($post->user_id);
            if ($owner) {
                try {
                    $owner->notify(new \App\Notifications\HvnNewCommentOnPost($comment, $post));
                } catch (\Throwable $e) {
                    // never let a notification failure block the response
                    \Log::warning('HvnNewCommentOnPost notify failed: ' . $e->getMessage());
                }
            }
        }

        return response()->json(['comment' => $comment->load('user:id,username')], 201);
    }

    public function logout(Request $request)
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    public function creatorDashboard(Request $request)
    {
        $user = $this->resolveUser();
        if (!$user) {
            return redirect('/login');
        }
        if ($user->role !== 'creator') {
            return redirect('/community');
        }

        $profile = $user->creatorProfile;
        $posts   = CommunityPost::where('user_id', $user->id)
            ->published()
            ->withCount(['comments', 'likes'])
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $myContent = Title::whereHas('videos', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->orderByDesc('created_at')
            ->get();

        return view('hvn.creator-dashboard', compact('user', 'profile', 'posts', 'myContent'));
    }

    public function profileUpdate(Request $request): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if ($user->isBlocked()) {
            return response()->json(['message' => 'Your account is blocked.'], 403);
        }
        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'username'      => 'sometimes|required|string|min:3|max:30|alpha_dash|unique:users,username,' . $user->id,
            'display_name'  => 'nullable|string|max:100',
            'bio'           => 'nullable|string|max:2000',
            'website_url'   => 'nullable|url|max:255',
            'contact_email' => 'nullable|email|max:255',
            'youtube_url'   => 'nullable|url|max:255',
            'twitter_url'   => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'facebook_url'  => 'nullable|url|max:255',
            'profile_photo' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($request->filled('username')) {
            $user->username = $request->input('username');
            $user->save();
        }

        $profile = CreatorProfile::firstOrCreate(['user_id' => $user->id]);
        $profile->fill($request->only(
            'display_name', 'bio', 'website_url', 'contact_email',
            'youtube_url', 'twitter_url', 'instagram_url', 'facebook_url'
        ));

        if ($request->hasFile('profile_photo')) {
            $stored = $request->file('profile_photo')
                ->store('creator_profiles', 'public');
            $profile->profile_photo = $stored;
        }

        $profile->save();

        return response()->json(['message' => 'Profile updated.', 'profile' => $profile->fresh()]);
    }

    // -----------------------------------------------------------------
    // Creator projects (previous work / portfolio)
    // -----------------------------------------------------------------

    public function apiListProjects(): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->isBlocked()) return response()->json(['message' => 'Your account is blocked.'], 403);
        if ($user->role !== 'creator') return response()->json(['message' => 'Forbidden.'], 403);

        $projects = \App\CreatorProject::where('user_id', $user->id)
            ->orderBy('position')
            ->orderByDesc('id')
            ->get();
        return response()->json(['projects' => $projects]);
    }

    public function apiStoreProject(Request $request): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->isBlocked()) return response()->json(['message' => 'Your account is blocked.'], 403);
        if ($user->role !== 'creator') return response()->json(['message' => 'Forbidden.'], 403);

        // Be forgiving: if the user typed "youtube.com/x" without a protocol,
        // prepend https:// so Laravel's url validator passes.
        if ($request->filled('url') && !preg_match('#^https?://#i', $request->input('url'))) {
            $request->merge(['url' => 'https://' . ltrim($request->input('url'))]);
        }

        $request->validate([
            'title'       => 'required|string|max:200',
            'role'        => 'nullable|string|max:200',
            'year'        => 'nullable|integer|min:1900|max:2100',
            'description' => 'nullable|string|max:5000',
            'url'         => 'nullable|url|max:500',
            'image'       => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $data = $request->only(['title', 'role', 'year', 'description', 'url']);
        $data['user_id']  = $user->id;
        $data['position'] = (int) (\App\CreatorProject::where('user_id', $user->id)->max('position') ?? 0) + 1;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('creator_projects', 'public');
        }

        $project = \App\CreatorProject::create($data);
        return response()->json(['project' => $project], 201);
    }

    public function apiUpdateProject(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->isBlocked()) return response()->json(['message' => 'Your account is blocked.'], 403);
        if ($user->role !== 'creator') return response()->json(['message' => 'Forbidden.'], 403);

        $project = \App\CreatorProject::findOrFail($id);
        if ((int) $project->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($request->filled('url') && !preg_match('#^https?://#i', $request->input('url'))) {
            $request->merge(['url' => 'https://' . ltrim($request->input('url'))]);
        }

        $request->validate([
            'title'       => 'required|string|max:200',
            'role'        => 'nullable|string|max:200',
            'year'        => 'nullable|integer|min:1900|max:2100',
            'description' => 'nullable|string|max:5000',
            'url'         => 'nullable|url|max:500',
            'image'       => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $project->fill($request->only(['title', 'role', 'year', 'description', 'url']));
        if ($request->hasFile('image')) {
            $project->image_path = $request->file('image')->store('creator_projects', 'public');
        }
        $project->save();

        return response()->json(['project' => $project]);
    }

    public function apiDeleteProject(int $id): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->isBlocked()) return response()->json(['message' => 'Your account is blocked.'], 403);
        if ($user->role !== 'creator') return response()->json(['message' => 'Forbidden.'], 403);

        $project = \App\CreatorProject::findOrFail($id);
        if ((int) $project->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $project->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function creatorProfile(string $username)
    {
        $user = \App\User::where('username', $username)
            ->where('role', 'creator')
            ->firstOrFail();

        $profile = $user->creatorProfile;

        return view('hvn.creator-profile', compact('user', 'profile'));
    }

    private function resolveUser()
    {
        return auth()->user() ?? auth('sanctum')->user();
    }

    /**
     * Resolve the current user, return null if anonymous, OR a 403
     * JSON response if the user is blocked. Caller does:
     *   $user = $this->requireActiveUser($r); if ($user instanceof JsonResponse) return $user;
     */
    private function requireActiveUser()
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->isBlocked()) return response()->json(['message' => 'Your account is blocked.'], 403);
        if (method_exists($user, 'isBlocked') && $user->isBlocked()) {
            return response()->json(['message' => 'Your account is blocked.'], 403);
        }
        return $user;
    }

    // Lightweight identity probe — used by Account Settings to decide
    // whether to show the 'Become a Creator' card or the Public Profile
    // editor, without 403-ing viewers on the heavier /creator/dashboard.
    public function apiMe(): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) {
            return response()->json(['authenticated' => false], 200)
                ->header('Cache-Control', 'no-store, private');
        }

        // "Is this user a creator?" — true if either:
        //   - role column is explicitly 'creator' (or any non-'viewer' value
        //     that grants creator-tier access: admin, owner, etc.), OR
        //   - they already have a creator_profiles row (legacy / migrated).
        // This is intentionally lenient so users who were grandfathered in
        // before the role column existed still see the editor, not the
        // 'Become a Creator' upsell.
        $hasProfile = false;
        try {
            $hasProfile = \DB::table('creator_profiles')
                ->where('user_id', $user->id)->exists();
        } catch (\Throwable $e) {
            \Log::warning('apiMe: creator_profiles probe failed', ['err' => $e->getMessage()]);
        }
        $isCreator = $hasProfile
            || in_array($user->role, ['creator', 'admin', 'owner'], true);

        return response()->json([
            'authenticated'   => true,
            'id'              => (int) $user->id,
            'username'        => $user->username,
            'email'           => $user->email,
            'role'            => $user->role,
            'is_creator'      => (bool) $isCreator,
            'has_profile'     => (bool) $hasProfile,
            'blocked'         => (bool) ($user->blocked ?? false),
            'trusted_creator' => (bool) ($user->trusted_creator ?? false),
            // raw avatar value (full URL via HasAvatarAttribute accessor)
            'avatar'          => $user->avatar,
        ])->header('Cache-Control', 'no-store, private');
    }

    // Self-service: viewer → creator. One click from Account Settings;
    // POST /secure/me/become-creator. No moderation step — admins can
    // demote / block from /admin/creators if needed.
    public function apiBecomeCreator(Request $request): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->isBlocked()) return response()->json(['message' => 'Your account is blocked.'], 403);

        $debug = ['uid' => $user->id, 'role_before' => $user->role];

        // 1) Always ensure a creator_profiles row via RAW SQL — sidesteps any
        //    Eloquent model events / fillable filters / DI quirks that could
        //    silently swallow the write. firstOrCreate has historically
        //    failed here for reasons we couldn't pin down.
        try {
            if (!\Schema::hasTable('creator_profiles')) {
                $debug['profile_err'] = 'table missing';
            } else {
                $exists = \DB::table('creator_profiles')
                    ->where('user_id', $user->id)->exists();
                if (!$exists) {
                    \DB::table('creator_profiles')->insert([
                        'user_id'      => $user->id,
                        'display_name' => '',
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
                $debug['profile_existed'] = $exists;
            }
        } catch (\Throwable $e) {
            $debug['profile_err'] = $e->getMessage();
            \Log::warning('become-creator: profile insert failed', $debug);
        }

        // 2) Best-effort role upgrade via raw UPDATE (avoid model save events).
        try {
            if (\Schema::hasColumn('users', 'role')) {
                $affected = \DB::table('users')
                    ->where('id', $user->id)
                    ->update(['role' => 'creator']);
                $debug['role_rows'] = $affected;
            } else {
                $debug['role_err'] = 'column missing';
            }
        } catch (\Throwable $e) {
            $debug['role_err'] = $e->getMessage();
            \Log::warning('become-creator: role update failed', $debug);
        }

        // 3) Re-resolve straight from DB and recompute is_creator.
        $row = \DB::table('users')->where('id', $user->id)->first();
        $hasProfile = \DB::table('creator_profiles')->where('user_id', $user->id)->exists();
        $isCreator  = $hasProfile || (isset($row->role) && $row->role === 'creator');
        $debug['role_after']  = $row->role ?? null;
        $debug['has_profile'] = $hasProfile;
        \Log::info('become-creator: result', $debug);
        $user = (object) ['role' => $row->role ?? $user->role];

        return response()->json([
            'status'     => 'success',
            'role'       => $user->role,
            'is_creator' => (bool) $isCreator,
            'debug'      => $debug,
        ]);
    }

    // -----------------------------------------------------------------
    // Public JSON API for the native Angular SPA pages (Creators / Community)
    // Mounted under /secure/* in routes/web.php so AppHttpClient hits them.
    // -----------------------------------------------------------------

    public function apiCreatorsList(Request $request): JsonResponse
    {
        $perPage = min(48, max(1, (int) $request->input('perPage', 24)));
        $query   = trim((string) $request->input('query', ''));

        // "Creator" = either role='creator' OR has a creator_profiles row.
        // The role column can lag behind the profile row (best-effort writes
        // in apiBecomeCreator), so we union the two signals.
        $q = \App\User::where(function ($w) {
                $w->where('role', 'creator')
                  ->orWhereHas('creatorProfile');
            })
            ->whereNotNull('username')
            ->with('creatorProfile')
            ->orderBy('username');

        if ($query !== '') {
            $q->where(function ($w) use ($query) {
                $w->where('username', 'like', '%' . $query . '%')
                  ->orWhereHas('creatorProfile', function ($p) use ($query) {
                      $p->where('display_name', 'like', '%' . $query . '%')
                        ->orWhere('bio', 'like', '%' . $query . '%');
                  });
            });
        }

        $page = $q->paginate($perPage);

        return response()->json(['pagination' => $page]);
    }

    public function apiCreatorProfile(string $username): JsonResponse
    {
        // Same union as the list endpoint — role column may lag behind the
        // creator_profiles row, so accept either signal.
        $user = \App\User::where('username', $username)
            ->where(function ($w) {
                $w->where('role', 'creator')
                  ->orWhereHas('creatorProfile');
            })
            ->with('creatorProfile')
            ->firstOrFail();

        $projects = \App\CreatorProject::where('user_id', $user->id)
            ->orderBy('position')
            ->orderByDesc('id')
            ->get();

        // Public titles this creator uploaded (already filtered to approved
        // via the Title global scope). Pull a tidy subset so the SPA can
        // render a "Titles" grid without an extra round-trip.
        $titles = Title::whereHas('videos', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orderByDesc('created_at')
            ->take(40)
            ->get(['id', 'name', 'type', 'year', 'poster', 'tagline', 'runtime', 'genre', 'created_at']);

        // Recent community posts by this creator (also a way of "showing
        // what they're up to" on the public profile).
        $posts = CommunityPost::where('user_id', $user->id)
            ->published()
            ->withCount(['comments', 'likes'])
            ->orderByDesc('created_at')
            ->take(10)
            ->get(['id', 'title', 'body', 'created_at', 'user_id']);

        return response()->json([
            'user'     => $user,
            'profile'  => $user->creatorProfile,
            'projects' => $projects,
            'titles'   => $titles,
            'posts'    => $posts,
        ]);
    }

    public function apiCommunityList(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->input('perPage', 15)));
        $query   = trim((string) $request->input('query', ''));

        $user = $this->resolveUser();
        $q = CommunityPost::with(['user:id,username'])
            ->published()
            ->withCount(['comments', 'likes'])
            ->when($user, function ($query) use ($user) {
                $query->withExists(['likes as liked_by_me' => function ($lq) use ($user) {
                    $lq->where('user_id', $user->id);
                }]);
            })
            ->orderByDesc('pinned')
            ->orderByDesc('created_at');

        if ($query !== '') {
            $q->where(function ($w) use ($query) {
                $w->where('title', 'like', '%' . $query . '%')
                  ->orWhere('body', 'like', '%' . $query . '%');
            });
        }

        return response()->json(['pagination' => $q->paginate($perPage)]);
    }

    public function apiCommunityShow(int $postId): JsonResponse
    {
        $user = $this->resolveUser();

        $post = CommunityPost::with([
                'user:id,username',
                'comments' => function ($q) use ($user) {
                    $q->withCount('likes')
                      ->with('user:id,username');
                    if ($user) {
                        $q->withExists(['likes as liked_by_me' => function ($lq) use ($user) {
                            $lq->where('user_id', $user->id);
                        }]);
                    }
                },
            ])
            ->published()
            ->withCount(['comments', 'likes'])
            ->findOrFail($postId);

        $post->liked_by_me = $user ? \App\CommunityLike::where('post_id', $post->id)
            ->where('user_id', $user->id)->exists() : false;

        return response()->json(['post' => $post]);
    }

    public function apiCreatorDashboard(): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden — creators only.'], 403);
        }

        $profile = $user->creatorProfile;

        $posts = CommunityPost::where('user_id', $user->id)
            ->published()
            ->withCount(['comments', 'likes'])
            ->orderByDesc('created_at')
            ->take(20)
            ->get();

        // Creators see their own content regardless of moderation state, so
        // pending and rejected titles still show up in their dashboard with
        // the right status badge.
        $content = Title::withoutGlobalScope('approved')
            ->whereHas('videos', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with(['videos' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->orderByDesc('created_at')
            ->get(['*']);

        $projects = \App\CreatorProject::where('user_id', $user->id)
            ->orderBy('position')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'user'     => $user->only(['id', 'username', 'email', 'avatar', 'role', 'blocked']),
            'profile'  => $profile,
            'posts'    => $posts,
            'content'  => $content,
            'projects' => $projects,
            'totals'   => [
                'posts'    => CommunityPost::where('user_id', $user->id)->count(),
                'comments' => \App\CommunityComment::where('user_id', $user->id)->count(),
                'titles'   => $content->count(),
                'projects' => $projects->count(),
            ],
        ]);
    }

    public function apiToggleCommentLike(int $commentId): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->isBlocked()) return response()->json(['message' => 'Your account is blocked.'], 403);

        $comment = CommunityComment::findOrFail($commentId);

        $existing = \App\CommunityCommentLike::where('comment_id', $comment->id)
            ->where('user_id', $user->id)->first();
        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            \App\CommunityCommentLike::create([
                'comment_id' => $comment->id,
                'user_id'    => $user->id,
                'created_at' => now(),
            ]);
            $liked = true;
        }

        return response()->json([
            'liked'       => $liked,
            'likes_count' => \App\CommunityCommentLike::where('comment_id', $comment->id)->count(),
        ]);
    }

    public function apiToggleLike(int $postId): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->isBlocked()) return response()->json(['message' => 'Your account is blocked.'], 403);

        $post = CommunityPost::published()->findOrFail($postId);

        $existing = \App\CommunityLike::where('post_id', $post->id)
            ->where('user_id', $user->id)->first();
        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            \App\CommunityLike::create([
                'post_id'    => $post->id,
                'user_id'    => $user->id,
                'created_at' => now(),
            ]);
            $liked = true;
        }

        return response()->json([
            'liked'       => $liked,
            'likes_count' => \App\CommunityLike::where('post_id', $post->id)->count(),
        ]);
    }

    // -----------------------------------------------------------------
    // Owner edit / delete for community posts and comments.
    // Used by the SPA's community-post-page when current user owns the row.
    // -----------------------------------------------------------------

    public function apiUpdateOwnPost(Request $request, int $postId): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->isBlocked()) return response()->json(['message' => 'Your account is blocked.'], 403);

        $post = CommunityPost::findOrFail($postId);
        if ((int) $post->user_id !== (int) $user->id && !$user->hasPermission('admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'body'  => 'required|string|max:10000',
        ]);

        $post->title = $request->input('title');
        $post->body  = $request->input('body');
        $post->save();

        // Owners editing their own posts may have changed substance — let
        // admins see it in the bell. Admin edits skip the alert (an admin
        // doesn't need to be alerted to their own change).
        if ((int) $post->user_id === (int) $user->id) {
            \App\User::notifyAdmins(new \App\Notifications\HvnPostEdited($post, $user));
        }

        return response()->json(['post' => $post]);
    }

    public function apiDeleteOwnPost(int $postId): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->isBlocked()) return response()->json(['message' => 'Your account is blocked.'], 403);

        $post = CommunityPost::findOrFail($postId);
        if ((int) $post->user_id !== (int) $user->id && !$user->hasPermission('admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Snapshot before deletion so the notification still has the title.
        $postSnapshot = clone $post;

        // Cascade-delete comments alongside the post so we don't orphan rows.
        CommunityComment::where('post_id', $post->id)->delete();
        $post->delete();

        // Notify admins only when the deletion came from the owner (admins
        // already know about their own deletes via the /admin/community UI).
        if ((int) $postSnapshot->user_id === (int) $user->id) {
            \App\User::notifyAdmins(new \App\Notifications\HvnPostDeleted($postSnapshot, $user));
        }

        return response()->json(['message' => 'Deleted.']);
    }

    public function apiUpdateOwnComment(Request $request, int $commentId): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->isBlocked()) return response()->json(['message' => 'Your account is blocked.'], 403);

        $comment = CommunityComment::findOrFail($commentId);
        if ((int) $comment->user_id !== (int) $user->id && !$user->hasPermission('admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate(['body' => 'required|string|max:5000']);

        $comment->body = $request->input('body');
        $comment->save();

        return response()->json(['comment' => $comment]);
    }

    public function apiDeleteOwnComment(int $commentId): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->isBlocked()) return response()->json(['message' => 'Your account is blocked.'], 403);

        $comment = CommunityComment::findOrFail($commentId);
        if ((int) $comment->user_id !== (int) $user->id && !$user->hasPermission('admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
