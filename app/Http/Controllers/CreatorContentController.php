<?php

namespace App\Http\Controllers;

use App\Title;
use App\Video;
use Common\Core\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CreatorContentController extends BaseController
{
    /**
     * GET /api/v1/creator/content
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        $userId = $user->id;

        $titles = Title::withoutGlobalScope('approved')
            ->whereHas('videos', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->with(['videos' => function ($q) use ($userId) {
                $q->where('user_id', $userId)->select('id', 'title_id', 'url', 'type', 'category', 'source');
            }])
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->success(['titles' => $titles]);
    }

    /**
     * POST /api/v1/creator/content
     * Accepts multipart/form-data with optional video_file and cover image.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (method_exists($user, 'isBlocked') && $user->isBlocked()) {
            return response()->json(['message' => 'Your account is blocked.'], 403);
        }
        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden — creators only.'], 403);
        }

        $this->validate($request, [
            'title'       => 'required|string|min:2|max:250',
            'type'        => 'required|in:movie,short,series,documentary',
            'year'        => 'nullable|integer|min:1900|max:2099',
            'description' => 'nullable|string|max:5000',
            'video_url'   => 'nullable|string|max:1000',
            // r2_video_url is the public URL of a file the browser already
            // uploaded straight to Cloudflare R2 via a presigned PUT — no
            // file passes through PHP, so the 413 limit doesn't apply.
            'r2_video_url' => 'nullable|string|max:1000',
            'video_file'  => 'nullable|file|mimetypes:video/mp4,video/webm,video/ogg,video/quicktime,video/x-msvideo|max:512000',
            'cover'       => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if (!$request->filled('video_url') && !$request->filled('r2_video_url') && !$request->hasFile('video_file')) {
            return response()->json([
                'errors' => ['video_url' => ['Provide a video URL, upload a file, or upload to cloud storage.']],
            ], 422);
        }

        $posterPath = $request->file('cover')->store('creator_content/covers', 'public');

        $type = $request->input('type');

        // Trusted creators (Phase 2) skip the queue. Phase 1: everyone pending.
        $isTrusted = (bool) ($user->trusted_creator ?? false);
        $initialStatus = $isTrusted ? 'approved' : 'pending';

        $record = new Title();
        $record->name         = $request->input('title');
        $record->type         = $type;
        $record->year         = $request->input('year');
        $record->description  = $request->input('description');
        $record->poster       = '/storage/' . $posterPath;
        // Player's setCoverImage() falls back through video.thumbnail → episode.poster
        // → title.images[last] → title.backdrop. It does NOT consult title.poster, so
        // creator titles without a backdrop render as a gray placeholder. Mirror the
        // poster into backdrop so the title-page header has an image to display.
        $record->backdrop     = '/storage/' . $posterPath;
        $record->adult        = false;
        $record->is_series    = ($type === 'series') ? true : false;
        $record->popularity   = 1;
        $record->fully_synced = true;
        $record->allow_update = false;
        $record->status       = $initialStatus;
        $record->save();

        if ($request->filled('r2_video_url')) {
            // Browser uploaded straight to Cloudflare R2; we just store the URL.
            $videoUrl  = $request->input('r2_video_url');
            $source    = 'r2';
            $videoType = Video::VIDEO_TYPE_DIRECT;
        } else if ($request->hasFile('video_file')) {
            $videoPath = $request->file('video_file')->store('creator_content/videos', 'public');
            $videoUrl  = '/storage/' . $videoPath;
            $source    = 'local';
            // Self-hosted MP4/WebM file → play directly in <video> tag.
            $videoType = Video::VIDEO_TYPE_DIRECT;
        } else {
            $videoUrl  = $request->input('video_url');
            $source    = 'external';
            // YouTube/Vimeo/etc embed iframes; raw .mp4 URLs play directly.
            $videoType = $this->detectExternalType($videoUrl);
        }

        Video::create([
            'title_id' => $record->id,
            'user_id'  => $user->id,
            'name'     => $record->name,
            'url'      => $videoUrl,
            'type'     => $videoType,
            'category' => 'full',
            'language' => 'en',
            'source'   => $source,
            // Track moderation state on the video too so /admin/videos
            // reflects pending (cross) vs approved (tick) instead of
            // always showing a tick.
            'approved' => ($initialStatus === 'approved') ? 1 : 0,
            'order'    => 1,
        ]);

        // Notify admins when a fresh upload is awaiting review. Trusted
        // creators (auto-approved) skip the alert.
        if ($initialStatus === 'pending') {
            \App\User::notifyAdmins(new \App\Notifications\HvnContentSubmitted($record, $user));
        }

        return response()->json(['title' => $record], 201);
    }

    /**
     * POST /secure/creator/content/presign
     * Returns a presigned PUT URL so the browser can upload a large
     * video straight to Cloudflare R2, plus the public URL the file
     * will be reachable at afterwards. Nothing about the file passes
     * through PHP, so the server upload-size limit never applies.
     */
    public function presign(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (method_exists($user, 'isBlocked') && $user->isBlocked()) {
            return response()->json(['message' => 'Your account is blocked.'], 403);
        }
        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden — creators only.'], 403);
        }

        if (!config('filesystems.disks.r2.bucket') || !config('filesystems.disks.r2.endpoint')) {
            return response()->json(['message' => 'Cloud storage is not configured.'], 503);
        }

        $this->validate($request, [
            'filename'     => 'required|string|max:255',
            'content_type' => 'required|string|max:120|starts_with:video/',
        ]);

        $ext = pathinfo($request->input('filename'), PATHINFO_EXTENSION);
        $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext) ?: 'mp4';
        $key = 'creator_content/videos/' . $user->id . '/' . \Str::random(40) . '.' . strtolower($ext);

        try {
            /** @var \League\Flysystem\AwsS3v3\AwsS3Adapter $adapter */
            $adapter = Storage::disk('r2')->getAdapter();
            $client  = $adapter->getClient();
            $bucket  = config('filesystems.disks.r2.bucket');

            $cmd = $client->getCommand('PutObject', [
                'Bucket'      => $bucket,
                'Key'         => $key,
                'ContentType' => $request->input('content_type'),
            ]);
            $presigned = $client->createPresignedRequest($cmd, '+30 minutes');

            $base = rtrim((string) config('filesystems.disks.r2.url'), '/');

            return response()->json([
                'upload_url'   => (string) $presigned->getUri(),
                'public_url'   => $base . '/' . $key,
                'key'          => $key,
                'content_type' => $request->input('content_type'),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('R2 presign failed: ' . $e->getMessage());
            return response()->json(['message' => 'Could not prepare upload.'], 500);
        }
    }

    /**
     * Choose the right video type for an external URL so the player
     * picks the correct renderer (iframe embed vs <video> tag).
     */
    private function detectExternalType(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $embedHosts = [
            'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be',
            'vimeo.com', 'player.vimeo.com',
            'dailymotion.com', 'www.dailymotion.com', 'dai.ly',
            'facebook.com', 'www.facebook.com', 'fb.watch',
        ];
        foreach ($embedHosts as $h) {
            if ($host === $h || str_ends_with($host, '.' . $h)) {
                return Video::VIDEO_TYPE_EMBED;
            }
        }
        // Direct file extension → playable in a <video> tag.
        if (preg_match('/\.(mp4|webm|ogg|m3u8|mov)$/i', $path)) {
            return Video::VIDEO_TYPE_DIRECT;
        }
        return Video::VIDEO_TYPE_EXTERNAL;
    }

    /**
     * DELETE /api/v1/creator/content/{id}
     */
    public function destroy(Request $request, int $titleId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (method_exists($user, 'isBlocked') && $user->isBlocked()) {
            return response()->json(['message' => 'Your account is blocked.'], 403);
        }
        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden — creators only.'], 403);
        }
        $userId = $user->id;

        $videos = Video::where('title_id', $titleId)->where('user_id', $userId)->get();
        if ($videos->isEmpty()) {
            return response()->json(['message' => 'Not found or unauthorized.'], 404);
        }

        foreach ($videos as $video) {
            if ($video->source === 'local' && $video->url) {
                $rel = ltrim(str_replace('/storage/', '', $video->url), '/');
                Storage::disk('public')->delete($rel);
            }
            $video->delete();
        }

        // Title lookup needs to bypass the approved global scope so we can
        // resolve pending/rejected titles too.
        $title = Title::withoutGlobalScope('approved')->find($titleId);
        $titleSnapshotForNotif = $title ? clone $title : null;

        if ($title && !Video::where('title_id', $titleId)->exists()) {
            if ($title->poster && strpos($title->poster, '/storage/creator_content') === 0) {
                $rel = ltrim(str_replace('/storage/', '', $title->poster), '/');
                Storage::disk('public')->delete($rel);
            }
            $title->delete();
        }

        if ($titleSnapshotForNotif) {
            \App\User::notifyAdmins(new \App\Notifications\HvnContentDeleted($titleSnapshotForNotif, $user));
        }

        return response()->json(['deleted' => true]);
    }
}
