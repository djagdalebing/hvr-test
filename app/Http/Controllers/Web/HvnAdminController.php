<?php

namespace App\Http\Controllers\Web;

use App\CommunityComment;
use App\CommunityLike;
use App\CommunityPost;
use App\CreatorProfile;
use App\User;
use Common\Core\BaseController as Controller;
use Illuminate\Http\Request;
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
}
