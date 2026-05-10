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
    public function creatorSignup()
    {
        if (auth()->check()) {
            return redirect('/');
        }

        return view('hvn.creator-signup');
    }

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

        $request->validate(['body' => 'required|string|max:5000']);

        $post = CommunityPost::published()->findOrFail($postId);

        $comment = CommunityComment::create([
            'post_id'    => $post->id,
            'user_id'    => $user->id,
            'body'       => $request->input('body'),
            'created_at' => now(),
        ]);

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
        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'username'      => 'required|string|min:3|max:30|alpha_dash|unique:users,username,' . $user->id,
            'display_name'  => 'nullable|string|max:100',
            'bio'           => 'nullable|string|max:1000',
            'website_url'   => 'nullable|url|max:255',
            'contact_email' => 'nullable|email|max:255',
        ]);

        $user->username = $request->input('username');
        $user->save();

        $profile = CreatorProfile::firstOrCreate(['user_id' => $user->id]);
        $profile->fill($request->only('display_name', 'bio', 'website_url', 'contact_email'));
        $profile->save();

        return response()->json(['message' => 'Profile updated.']);
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

    // -----------------------------------------------------------------
    // Public JSON API for the native Angular SPA pages (Creators / Community)
    // Mounted under /secure/* in routes/web.php so AppHttpClient hits them.
    // -----------------------------------------------------------------

    public function apiCreatorsList(Request $request): JsonResponse
    {
        $perPage = min(48, max(1, (int) $request->input('perPage', 24)));
        $query   = trim((string) $request->input('query', ''));

        $q = \App\User::where('role', 'creator')
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
        $user = \App\User::where('username', $username)
            ->where('role', 'creator')
            ->with('creatorProfile')
            ->firstOrFail();

        return response()->json([
            'user'    => $user,
            'profile' => $user->creatorProfile,
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

    public function apiToggleCommentLike(int $commentId): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);

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

        return response()->json(['post' => $post]);
    }

    public function apiDeleteOwnPost(int $postId): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);

        $post = CommunityPost::findOrFail($postId);
        if ((int) $post->user_id !== (int) $user->id && !$user->hasPermission('admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Cascade-delete comments alongside the post so we don't orphan rows.
        CommunityComment::where('post_id', $post->id)->delete();
        $post->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function apiUpdateOwnComment(Request $request, int $commentId): JsonResponse
    {
        $user = $this->resolveUser();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);

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

        $comment = CommunityComment::findOrFail($commentId);
        if ((int) $comment->user_id !== (int) $user->id && !$user->hasPermission('admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
