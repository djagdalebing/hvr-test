<?php

namespace App\Http\Controllers\Web;

use App\Announcement;
use App\CommunityComment;
use App\CommunityLike;
use App\CommunityPost;
use App\CreatorProfile;
use App\Notifications\HvnAnnouncementPosted;
use App\User;
use Common\Core\BaseController as Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class HvnAdminController extends Controller
{
    private function adminOrAbort()
    {
        if (!auth()->check()) {
            return redirect('/login');
        }
        $user = auth()->user();
        if (!$user->hasPermission('admin')) {
            abort(403, 'Admin access required.');
        }
        return $user;
    }

    public function creators(Request $request)
    {
        $result = $this->adminOrAbort();
        if ($result instanceof \Illuminate\Http\RedirectResponse) return $result;

        $search = $request->input('q');
        $query = User::where('role', 'creator')
            ->with('creatorProfile')
            ->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        $creators = $query->paginate(20)->withQueryString();

        return view('hvn.admin.creators', compact('creators', 'search'));
    }

    /**
     * JSON API for native Angular admin tab — /secure/admin/creators
     */
    public function creatorsJson(Request $request)
    {
        $user = $this->adminOrAbort();
        if ($user instanceof \Illuminate\Http\RedirectResponse) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $perPage = (int) $request->input('perPage', 20);
        $page    = (int) $request->input('page', 1);
        $search  = $request->input('query');
        $order   = $request->input('order', 'created_at|desc');
        [$orderCol, $orderDir] = array_pad(explode('|', $order), 2, 'desc');

        $query = User::where('role', 'creator')->with('creatorProfile');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }
        $allowedCols = ['created_at', 'updated_at', 'username', 'email'];
        if (in_array($orderCol, $allowedCols, true)) {
            $query->orderBy($orderCol, $orderDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('created_at');
        }

        $pagination = $query->paginate($perPage, ['*'], 'page', $page);
        $pagination->getCollection()->transform(function (User $u) {
            return [
                'id'             => $u->id,
                'username'       => $u->username,
                'email'          => $u->email,
                'avatar'         => $u->avatar,
                'role'           => $u->role,
                'created_at'     => $u->created_at,
                'display_name'   => $u->creatorProfile->display_name ?? null,
                'bio'            => $u->creatorProfile->bio ?? null,
                'profile_photo'  => $u->creatorProfile->profile_photo ?? null,
                'website_url'    => $u->creatorProfile->website_url ?? null,
                'contact_email'  => $u->creatorProfile->contact_email ?? null,
                'youtube_url'    => $u->creatorProfile->youtube_url ?? null,
                'twitter_url'    => $u->creatorProfile->twitter_url ?? null,
                'instagram_url'  => $u->creatorProfile->instagram_url ?? null,
                'facebook_url'   => $u->creatorProfile->facebook_url ?? null,
                'model_type'     => 'creator',
            ];
        });

        return response()->json([
            'pagination' => $pagination,
            'status'     => 'success',
        ]);
    }

    /**
     * JSON API for community posts — /secure/admin/community
     */
    public function communityJson(Request $request)
    {
        $user = $this->adminOrAbort();
        if ($user instanceof \Illuminate\Http\RedirectResponse) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $perPage = (int) $request->input('perPage', 20);
        $page    = (int) $request->input('page', 1);
        $search  = $request->input('query');
        $status  = $request->input('status');
        $order   = $request->input('order', 'created_at|desc');
        [$orderCol, $orderDir] = array_pad(explode('|', $order), 2, 'desc');

        $query = CommunityPost::with('user:id,username,avatar')
            ->withCount(['comments', 'likes']);
        if ($search) {
            $query->where('title', 'like', "%$search%");
        }
        if ($status) {
            $query->where('status', $status);
        }
        $allowedCols = ['created_at', 'title', 'status'];
        if (in_array($orderCol, $allowedCols, true)) {
            $query->orderBy($orderCol, $orderDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('created_at');
        }

        $pagination = $query->paginate($perPage, ['*'], 'page', $page);
        $pagination->getCollection()->transform(function (CommunityPost $p) {
            return [
                'id'         => $p->id,
                'title'      => $p->title,
                'body'       => $p->body,
                'status'     => $p->status,
                'created_at' => $p->created_at,
                'author'     => $p->user ? [
                    'id'       => $p->user->id,
                    'username' => $p->user->username,
                    'avatar'   => $p->user->avatar,
                ] : null,
                'comments_count' => (int) $p->comments_count,
                'likes_count'    => (int) $p->likes_count,
                'model_type'     => 'community_post',
            ];
        });

        return response()->json([
            'pagination' => $pagination,
            'status'     => 'success',
        ]);
    }

    public function deleteCreatorsJson(Request $request)
    {
        $user = $this->adminOrAbort();
        if ($user instanceof \Illuminate\Http\RedirectResponse) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }
        $ids = (array) $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'No ids provided'], 422);
        }
        // Demote to viewer rather than delete — safer
        User::whereIn('id', $ids)->where('role', 'creator')->update(['role' => 'viewer']);
        return response()->json(['status' => 'success']);
    }

    public function deleteCommunityPostsJson(Request $request)
    {
        $user = $this->adminOrAbort();
        if ($user instanceof \Illuminate\Http\RedirectResponse) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }
        $ids = (array) $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'No ids provided'], 422);
        }
        CommunityComment::whereIn('post_id', $ids)->delete();
        CommunityLike::whereIn('post_id', $ids)->delete();
        CommunityPost::whereIn('id', $ids)->delete();
        return response()->json(['status' => 'success']);
    }

    public function community(Request $request)
    {
        $result = $this->adminOrAbort();
        if ($result instanceof \Illuminate\Http\RedirectResponse) return $result;

        $search = $request->input('q');
        $query = CommunityPost::with('user:id,username')
            ->withCount(['comments', 'likes'])
            ->orderByDesc('created_at');

        if ($search) {
            $query->where('title', 'like', "%$search%");
        }

        $posts = $query->paginate(20)->withQueryString();

        return view('hvn.admin.community', compact('posts', 'search'));
    }

    public function deletePost(Request $request, int $postId)
    {
        $result = $this->adminOrAbort();
        if ($result instanceof \Illuminate\Http\RedirectResponse) return $result;

        $post = CommunityPost::findOrFail($postId);
        CommunityComment::where('post_id', $postId)->delete();
        CommunityLike::where('post_id', $postId)->delete();
        $post->delete();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success']);
        }
        return back()->with('flash', ['type' => 'success', 'message' => 'Post deleted.']);
    }

    public function hidePost(Request $request, int $postId)
    {
        $result = $this->adminOrAbort();
        if ($result instanceof \Illuminate\Http\RedirectResponse) return $result;

        $post = CommunityPost::findOrFail($postId);
        $post->status = ($post->status === 'published') ? 'removed' : 'published';
        $post->save();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'post_status' => $post->status]);
        }
        $label = $post->status === 'published' ? 'Post restored.' : 'Post hidden.';
        return back()->with('flash', ['type' => 'success', 'message' => $label]);
    }

    public function toggleCreator(Request $request, int $userId)
    {
        $result = $this->adminOrAbort();
        if ($result instanceof \Illuminate\Http\RedirectResponse) return $result;

        $creator = User::findOrFail($userId);
        $creator->role = ($creator->role === 'creator') ? 'viewer' : 'creator';
        $creator->save();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'role' => $creator->role]);
        }
        $label = $creator->role === 'creator' ? 'Creator access restored.' : 'Creator access revoked.';
        return back()->with('flash', ['type' => 'success', 'message' => $label]);
    }

    public function editPost(Request $request, int $postId)
    {
        $result = $this->adminOrAbort();
        if ($result instanceof \Illuminate\Http\RedirectResponse) return $result;

        $post = CommunityPost::with('user:id,username')->findOrFail($postId);
        $comments = CommunityComment::with('user:id,username')
            ->where('post_id', $postId)
            ->orderByDesc('created_at')
            ->get();

        return view('hvn.admin.community_edit', compact('post', 'comments'));
    }

    public function updatePost(Request $request, int $postId)
    {
        $result = $this->adminOrAbort();
        if ($result instanceof \Illuminate\Http\RedirectResponse) return $result;

        $data = $request->validate([
            'title'  => 'required|string|max:255',
            'body'   => 'required|string',
            'status' => ['required', Rule::in(['published', 'draft', 'removed'])],
        ]);

        $post = CommunityPost::findOrFail($postId);
        $post->fill($data)->save();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success']);
        }
        return redirect('/admin/community/' . $postId . '/edit')
            ->with('flash', ['type' => 'success', 'message' => 'Post updated.']);
    }

    public function deleteComment(Request $request, int $commentId)
    {
        $result = $this->adminOrAbort();
        if ($result instanceof \Illuminate\Http\RedirectResponse) return $result;

        $comment = CommunityComment::findOrFail($commentId);
        $postId = $comment->post_id;
        $comment->delete();

        return redirect('/admin/community/' . $postId . '/edit')
            ->with('flash', ['type' => 'success', 'message' => 'Comment deleted.']);
    }

    public function editCreator(Request $request, int $userId)
    {
        $result = $this->adminOrAbort();
        if ($result instanceof \Illuminate\Http\RedirectResponse) return $result;

        $creator = User::with('creatorProfile')->findOrFail($userId);
        $profile = $creator->creatorProfile ?: new CreatorProfile(['user_id' => $creator->id]);

        return view('hvn.admin.creator_edit', compact('creator', 'profile'));
    }

    public function updateCreator(Request $request, int $userId)
    {
        $result = $this->adminOrAbort();
        if ($result instanceof \Illuminate\Http\RedirectResponse) return $result;

        $data = $request->validate([
            'display_name'   => 'nullable|string|max:100',
            'bio'            => 'nullable|string',
            'website_url'    => 'nullable|string|max:255',
            'contact_email'  => 'nullable|email|max:150',
            'youtube_url'    => 'nullable|string|max:255',
            'twitter_url'    => 'nullable|string|max:255',
            'instagram_url'  => 'nullable|string|max:255',
            'facebook_url'   => 'nullable|string|max:255',
            'profile_photo'  => 'nullable|string|max:255',
        ]);

        $creator = User::findOrFail($userId);
        $profile = CreatorProfile::firstOrNew(['user_id' => $creator->id]);
        $profile->fill($data)->save();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success']);
        }
        return redirect('/admin/creators/' . $userId . '/edit')
            ->with('flash', ['type' => 'success', 'message' => 'Creator profile updated.']);
    }

    // ====================================================================
    // JSON API for native Angular admin tabs.
    // All return JSON; permission check returns 401/403.
    // ====================================================================

    private function apiAdminOrAbort()
    {
        if (!auth()->check()) abort(401, 'Unauthorized');
        if (!auth()->user()->hasPermission('admin')) abort(403, 'Admin access required.');
    }

    public function apiCreators(Request $request)
    {
        $this->apiAdminOrAbort();
        $search = $request->input('query');
        $perPage = min((int) $request->input('perPage', 20), 100);

        $query = User::where('role', 'creator')
            ->with('creatorProfile')
            ->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        $pagination = $query->paginate($perPage);
        return ['pagination' => $pagination];
    }

    public function apiUpdateCreator(Request $request, int $userId)
    {
        $this->apiAdminOrAbort();

        $data = $request->validate([
            'display_name'   => 'nullable|string|max:100',
            'bio'            => 'nullable|string',
            'website_url'    => 'nullable|string|max:255',
            'contact_email'  => 'nullable|email|max:150',
            'youtube_url'    => 'nullable|string|max:255',
            'twitter_url'    => 'nullable|string|max:255',
            'instagram_url'  => 'nullable|string|max:255',
            'facebook_url'   => 'nullable|string|max:255',
            'profile_photo'  => 'nullable|string|max:255',
        ]);

        $creator = User::findOrFail($userId);
        $profile = CreatorProfile::firstOrNew(['user_id' => $creator->id]);
        $profile->fill($data)->save();

        return ['status' => 'success', 'profile' => $profile->fresh()];
    }

    public function apiToggleCreator(Request $request, int $userId)
    {
        $this->apiAdminOrAbort();
        $creator = User::findOrFail($userId);
        $creator->role = ($creator->role === 'creator') ? 'viewer' : 'creator';
        $creator->save();
        return ['status' => 'success', 'role' => $creator->role];
    }

    /**
     * GET /secure/admin/moderation
     * List creator-uploaded titles filtered by status (default: pending).
     */
    public function apiModerationList(Request $request)
    {
        $this->apiAdminOrAbort();
        $status  = $request->input('status', 'pending');
        if (!in_array($status, ['pending', 'approved', 'rejected'])) $status = 'pending';
        $perPage = min((int) $request->input('perPage', 15), 100);
        $search  = trim((string) $request->input('query', ''));

        $q = \App\Title::withoutGlobalScope('approved')
            ->where('status', $status)
            // Only show creator-uploaded titles — anything with a video that
            // has a user_id (TMDB imports have no user_id on their videos).
            ->whereHas('videos', function ($vq) {
                $vq->whereNotNull('user_id');
            })
            ->with(['videos' => function ($vq) {
                $vq->whereNotNull('user_id')->with('user:id,username,email');
            }])
            ->orderByDesc('created_at');

        if ($search !== '') {
            $q->where('name', 'like', '%' . $search . '%');
        }

        return ['pagination' => $q->paginate($perPage)];
    }

    public function apiApproveContent(Request $request, int $titleId)
    {
        $this->apiAdminOrAbort();
        $title = \App\Title::withoutGlobalScope('approved')->findOrFail($titleId);
        $title->status = 'approved';
        $title->rejection_reason = null;
        // Stamp the approval time so the homepage "Exclusive Content Release"
        // row can order by newest-approved-first. Only set it the first time.
        if (empty($title->approved_at)) {
            $title->approved_at = now();
        }
        $title->save();

        // Flip the linked creator video(s) to approved so /admin/videos
        // shows a tick and the title-page video player will load.
        \App\Video::where('title_id', $title->id)
            ->whereNotNull('user_id')
            ->update(['approved' => 1]);

        // Notify the uploading creator.
        try {
            $video = \App\Video::where('title_id', $title->id)->whereNotNull('user_id')->first();
            if ($video && $video->user) {
                $video->user->notify(new \App\Notifications\HvnContentApproved($title));
            }
        } catch (\Throwable $e) {
            \Log::warning('HvnContentApproved notify failed: ' . $e->getMessage());
        }

        return ['status' => 'success', 'title' => $title];
    }

    public function apiRejectContent(Request $request, int $titleId)
    {
        $this->apiAdminOrAbort();
        $request->validate(['reason' => 'nullable|string|max:1000']);
        $title = \App\Title::withoutGlobalScope('approved')->findOrFail($titleId);
        $title->status = 'rejected';
        $title->rejection_reason = $request->input('reason');
        $title->save();

        // Keep the linked video(s) unapproved so /admin/videos reflects
        // rejection (cross) and the public player won't pick them up.
        \App\Video::where('title_id', $title->id)
            ->whereNotNull('user_id')
            ->update(['approved' => 0]);

        try {
            $video = \App\Video::where('title_id', $title->id)->whereNotNull('user_id')->first();
            if ($video && $video->user) {
                $video->user->notify(new \App\Notifications\HvnContentRejected($title, $request->input('reason')));
            }
        } catch (\Throwable $e) {
            \Log::warning('HvnContentRejected notify failed: ' . $e->getMessage());
        }

        return ['status' => 'success', 'title' => $title];
    }

    // -----------------------------------------------------------------
    // Editor's Picks — admin curates up to 10 titles pinned to the
    // homepage "Editor's Pick" row. Stored as an ordered list of title IDs
    // in the `homepage.editor_picks` setting.
    // -----------------------------------------------------------------

    const EDITOR_PICKS_MAX = 10;

    public function apiGetEditorPicks()
    {
        $this->apiAdminOrAbort();
        $ids = $this->editorPickIds();
        if (empty($ids)) {
            return ['status' => 'success', 'titles' => []];
        }
        // Load in the pinned order; withoutGlobalScope so admins still see a
        // pinned title even if it later goes pending.
        $titles = \App\Title::withoutGlobalScope('approved')
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'poster', 'year', 'status']);
        $titles = $titles->sortBy(fn($t) => array_search($t->id, $ids))->values();
        return ['status' => 'success', 'titles' => $titles];
    }

    public function apiSetEditorPicks(Request $request)
    {
        $this->apiAdminOrAbort();
        $request->validate([
            'ids'   => 'present|array|max:' . self::EDITOR_PICKS_MAX,
            'ids.*' => 'integer',
        ]);
        // De-dupe, keep order, cap at max, and drop ids that don't exist.
        $ids = collect($request->input('ids', []))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->take(self::EDITOR_PICKS_MAX)
            ->values();
        $existing = \App\Title::withoutGlobalScope('approved')
            ->whereIn('id', $ids)->pluck('id')->all();
        $ids = $ids->filter(fn($id) => in_array($id, $existing))->values()->all();

        app(\Common\Settings\Settings::class)->save([
            'homepage.editor_picks' => json_encode($ids),
        ]);

        return ['status' => 'success', 'ids' => $ids];
    }

    private function editorPickIds(): array
    {
        $raw = app(\Common\Settings\Settings::class)->get('homepage.editor_picks');
        if (is_string($raw)) $raw = json_decode($raw, true);
        if (!is_array($raw)) return [];
        return array_values(array_filter(array_map('intval', $raw)));
    }

    /** Title search for the Editor's Picks admin picker (name match). */
    public function apiSearchTitles(Request $request)
    {
        $this->apiAdminOrAbort();
        $q = trim((string) $request->input('query', ''));
        if ($q === '') {
            return ['status' => 'success', 'titles' => []];
        }
        $titles = \App\Title::where('name', 'like', '%' . $q . '%')
            ->orderByDesc('popularity')
            ->limit(20)
            ->get(['id', 'name', 'poster', 'year', 'status']);
        return ['status' => 'success', 'titles' => $titles];
    }

    // -----------------------------------------------------------------
    // People moderation (Phase 2) — review people created by creators.
    // -----------------------------------------------------------------

    public function apiPeopleModerationList(Request $request)
    {
        $this->apiAdminOrAbort();
        $status  = $request->input('status', 'pending');
        if (!in_array($status, ['pending', 'approved', 'rejected'])) $status = 'pending';
        $perPage = min((int) $request->input('perPage', 15), 100);
        $search  = trim((string) $request->input('query', ''));

        // Only people that came through the creator flow (created_by set).
        $q = \App\Person::withoutGlobalScope('approved')
            ->where('status', $status)
            ->whereNotNull('created_by')
            ->with('creator:id,username,email')
            ->orderByDesc('created_at');

        if ($search !== '') {
            $q->where('name', 'like', '%' . $search . '%');
        }

        return ['pagination' => $q->paginate($perPage)];
    }

    public function apiApprovePerson(Request $request, int $personId)
    {
        $this->apiAdminOrAbort();
        $person = \App\Person::withoutGlobalScope('approved')->findOrFail($personId);
        $person->status = 'approved';
        $person->save();
        return ['status' => 'success', 'person' => $person];
    }

    public function apiRejectPerson(Request $request, int $personId)
    {
        $this->apiAdminOrAbort();
        $person = \App\Person::withoutGlobalScope('approved')->findOrFail($personId);
        // Reject = mark rejected and detach from any titles so the bad entry
        // stops showing in credits. We keep the row (not hard-delete) so the
        // creator sees it was reviewed rather than silently vanishing.
        $person->status = 'rejected';
        $person->save();
        // Detach from any titles so the bad entry stops showing in credits.
        \DB::table('creditables')->where('person_id', $person->id)->delete();
        return ['status' => 'success', 'person' => $person];
    }

    public function apiToggleTrusted(Request $request, int $userId)
    {
        $this->apiAdminOrAbort();
        $u = User::findOrFail($userId);
        $u->trusted_creator = !$u->trusted_creator;
        $u->save();

        // Notify the creator when they're promoted (silent on revoke).
        if ($u->trusted_creator) {
            try {
                $u->notify(new \App\Notifications\HvnTrustedPromoted());
            } catch (\Throwable $e) {
                \Log::warning('HvnTrustedPromoted notify failed: ' . $e->getMessage());
            }
        }

        return ['status' => 'success', 'trusted_creator' => (bool) $u->trusted_creator];
    }

    public function apiToggleBlock(Request $request, int $userId)
    {
        $this->apiAdminOrAbort();
        $u = User::findOrFail($userId);
        $u->blocked = !$u->blocked;
        $u->save();

        try {
            if ($u->blocked) {
                $u->notify(new \App\Notifications\HvnAccountBlocked());
            } else {
                $u->notify(new \App\Notifications\HvnAccountUnblocked());
            }
        } catch (\Throwable $e) {
            \Log::warning('Account block/unblock notify failed: ' . $e->getMessage());
        }

        return ['status' => 'success', 'blocked' => (bool) $u->blocked];
    }

    public function apiDeleteCreator(Request $request, int $userId)
    {
        $this->apiAdminOrAbort();
        $u = User::findOrFail($userId);

        // Best-effort cascade — these tables may have FKs that ON DELETE CASCADE
        // already, but we delete explicitly so it works regardless of schema.
        \App\CommunityComment::where('user_id', $u->id)->delete();
        \App\CommunityLike::where('user_id', $u->id)->delete();
        if (class_exists(\App\CommunityCommentLike::class)) {
            \App\CommunityCommentLike::where('user_id', $u->id)->delete();
        }
        \App\CommunityPost::where('user_id', $u->id)->delete();
        if (\Illuminate\Support\Facades\Schema::hasTable('creator_profiles')) {
            \Illuminate\Support\Facades\DB::table('creator_profiles')->where('user_id', $u->id)->delete();
        }

        $u->delete();
        return ['status' => 'success'];
    }

    public function apiCommunity(Request $request)
    {
        $this->apiAdminOrAbort();
        $search = $request->input('query');
        $perPage = min((int) $request->input('perPage', 20), 100);

        $query = CommunityPost::with('user:id,username')
            ->withCount(['comments', 'likes'])
            ->orderByDesc('created_at');

        if ($search) {
            $query->where('title', 'like', "%$search%");
        }

        $pagination = $query->paginate($perPage);
        return ['pagination' => $pagination];
    }

    public function apiUpdatePost(Request $request, int $postId)
    {
        $this->apiAdminOrAbort();
        $data = $request->validate([
            'title'  => 'required|string|max:255',
            'body'   => 'required|string',
            'status' => ['required', Rule::in(['published', 'draft', 'removed'])],
        ]);
        $post = CommunityPost::findOrFail($postId);
        $post->fill($data)->save();
        return ['status' => 'success', 'post' => $post->fresh()];
    }

    public function apiHidePost(Request $request, int $postId)
    {
        $this->apiAdminOrAbort();
        $post = CommunityPost::findOrFail($postId);
        $post->status = ($post->status === 'published') ? 'removed' : 'published';
        $post->save();
        return ['status' => 'success', 'post' => $post->fresh()];
    }

    public function apiPinPost(Request $request, int $postId)
    {
        $this->apiAdminOrAbort();
        $post = CommunityPost::findOrFail($postId);
        $post->pinned = !$post->pinned;
        $post->save();

        // Only notify when newly pinned (positive signal); silent on unpin.
        if ($post->pinned && $post->user_id) {
            $owner = User::find($post->user_id);
            if ($owner) {
                try {
                    $owner->notify(new \App\Notifications\HvnPostPinned($post));
                } catch (\Throwable $e) {
                    \Log::warning('HvnPostPinned notify failed: ' . $e->getMessage());
                }
            }
        }

        return ['status' => 'success', 'pinned' => (bool) $post->pinned];
    }

    public function apiDeletePost(Request $request, int $postId)
    {
        $this->apiAdminOrAbort();
        $post = CommunityPost::findOrFail($postId);
        CommunityComment::where('post_id', $postId)->delete();
        CommunityLike::where('post_id', $postId)->delete();
        $post->delete();
        return ['status' => 'success'];
    }

    public function apiPostComments(Request $request, int $postId)
    {
        $this->apiAdminOrAbort();
        $comments = CommunityComment::with('user:id,username')
            ->where('post_id', $postId)
            ->orderByDesc('created_at')
            ->get();
        return ['comments' => $comments];
    }

    public function apiDeleteComment(Request $request, int $commentId)
    {
        $this->apiAdminOrAbort();
        $comment = CommunityComment::findOrFail($commentId);
        $comment->delete();
        return ['status' => 'success'];
    }

    // -----------------------------------------------------------------
    // Announcements (Phase 1 — compose + in-app delivery to the bell)
    // -----------------------------------------------------------------

    public function apiAnnouncementsList(Request $request)
    {
        $this->apiAdminOrAbort();
        $perPage = min((int) $request->input('perPage', 20), 100);
        $page = Announcement::with('author:id,username')
            ->orderByDesc('created_at')
            ->paginate($perPage);
        return ['pagination' => $page];
    }

    public function apiCreateAnnouncement(Request $request)
    {
        $this->apiAdminOrAbort();
        $data = $this->validateAnnouncement($request);
        unset($data['image']);
        $data['created_by'] = auth()->id();
        $data['status'] = 'draft';

        if ($request->hasFile('image')) {
            $data['image_path'] = 'storage/' . $request->file('image')->store('announcements', 'public');
        }

        $announcement = Announcement::create($data);
        return ['status' => 'success', 'announcement' => $announcement->fresh()];
    }

    public function apiUpdateAnnouncement(Request $request, int $id)
    {
        $this->apiAdminOrAbort();
        $announcement = Announcement::findOrFail($id);
        if ($announcement->status === 'sent') {
            return response()->json(['status' => 'error', 'message' => 'Sent announcements cannot be edited.'], 422);
        }
        $data = $this->validateAnnouncement($request);
        unset($data['image']);
        if ($request->hasFile('image')) {
            $data['image_path'] = 'storage/' . $request->file('image')->store('announcements', 'public');
        }
        $announcement->update($data); // channels persisted via the array cast
        return ['status' => 'success', 'announcement' => $announcement->fresh()];
    }

    public function apiDeleteAnnouncement(Request $request, int $id)
    {
        $this->apiAdminOrAbort();
        Announcement::where('id', $id)->delete();
        return ['status' => 'success'];
    }

    /**
     * Send the announcement to its target audience over the in-app channel
     * (database notifications → the bell). Email is layered on in Phase 2.
     */
    public function apiSendAnnouncement(Request $request, int $id)
    {
        $this->apiAdminOrAbort();
        $announcement = Announcement::findOrFail($id);

        $channels = $announcement->channels ?: ['in_app'];
        $wantsInApp = in_array('in_app', $channels, true);
        $wantsEmail = in_array('email', $channels, true);

        // Only attempt email if mail is actually configured. Otherwise the
        // synchronous send would hang on the dead default SMTP host and the
        // request would never return (button stuck on "Sending…").
        $mailReady = filter_var(env('MAIL_SETUP', false), FILTER_VALIDATE_BOOLEAN);
        $emailSkipped = false;
        if ($wantsEmail && !$mailReady) {
            $wantsEmail = false;
            $emailSkipped = true;
        }

        $baseQuery = function () use ($announcement) {
            $q = User::query();
            if ($announcement->audience === 'viewers') {
                $q->where('role', 'viewer');
            } elseif ($announcement->audience === 'creators') {
                $q->where('role', 'creator');
            }
            return $q;
        };

        $count = 0;
        $emailCount = 0;

        // In-app — reliable, delivered to every targeted user's bell.
        if ($wantsInApp) {
            $baseQuery()->select('id', 'email', 'username', 'role')
                ->chunkById(500, function ($users) use ($announcement, &$count) {
                    Notification::send($users, new HvnAnnouncementPosted($announcement, ['database']));
                    $count += $users->count();
                });
        } else {
            $count = (clone $baseQuery())->count();
        }

        // Email — best-effort, per-user isolation so a broken mail transport
        // can't abort the batch. Skips users who unsubscribed or have no email.
        if ($wantsEmail) {
            $baseQuery()
                ->where(function ($q) {
                    $q->whereNull('newsletter_unsubscribed')->orWhere('newsletter_unsubscribed', false);
                })
                ->whereNotNull('email')
                ->select('id', 'email', 'username', 'role')
                ->chunkById(200, function ($users) use ($announcement, &$emailCount) {
                    foreach ($users as $user) {
                        try {
                            $user->notify(new HvnAnnouncementPosted($announcement, ['mail']));
                            $emailCount++;
                        } catch (\Throwable $e) {
                            \Log::warning('announcement email failed', ['uid' => $user->id, 'err' => $e->getMessage()]);
                        }
                    }
                });
        }

        $announcement->update([
            'status'           => 'sent',
            'sent_at'          => now(),
            'recipients_count' => $count,
            'channels'         => $channels,
        ]);

        return [
            'status'           => 'success',
            'recipients_count' => $count,
            'email_count'      => $emailCount,
            'email_skipped'    => $emailSkipped,
            'announcement'     => $announcement->fresh(),
        ];
    }

    private function validateAnnouncement(Request $request): array
    {
        $data = $request->validate([
            'title'      => 'required|string|min:2|max:200',
            'body'       => 'nullable|string|max:10000',
            'type'       => ['required', Rule::in(Announcement::TYPES)],
            'audience'   => ['required', Rule::in(Announcement::AUDIENCES)],
            'link_url'   => 'nullable|string|max:500',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            // channels: in_app and/or email (sent as channels[] in form-data)
            'channels'   => 'nullable|array',
            'channels.*' => Rule::in(['in_app', 'email']),
        ]);

        // Default to in-app if nothing was selected.
        $channels = array_values(array_unique($data['channels'] ?? []));
        if (empty($channels)) {
            $channels = ['in_app'];
        }
        $data['channels'] = $channels;

        return $data;
    }
}
